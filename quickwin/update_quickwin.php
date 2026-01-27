<?php

/****************************************
 * QUICK WIN UPDATE – FINAL PRODUCTION
 ****************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

/* =========================
   AUTH
========================= */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

/* =========================
   RECEIVE POST
========================= */
$old_t_pid  = $_POST['old_t_pid'] ?? null;
$old_p_id   = $_POST['old_p_id'] ?? null;
$old_date   = $_POST['old_supervision_date'] ?? null;

$new_t_pid  = $_POST['new_t_pid'] ?? null;
$new_p_id   = $_POST['new_p_id'] ?? null;

$option_ids     = $_POST['options'] ?? [];
$option_other   = trim($_POST['option_other'] ?? '');
$new_academic_year = $_POST['academic_year'] ?? null;
$semester       = $_POST['semester'] ?? null;

$form_type = 'qw';

$delete_image_ids = $_POST['delete_image_ids'] ?? [];
$delete_image_ids = is_array($delete_image_ids)
    ? array_map('intval', $delete_image_ids)
    : [];

/* =========================
   VALIDATE
========================= */
if (
    !$old_t_pid || !$old_p_id || !$old_date ||
    !$new_t_pid || !$new_p_id ||
    !$new_academic_year || !$semester
) {
    exit('ข้อมูลไม่ครบ');
}

/* =========================
   SANITIZE OPTIONS
========================= */
if (!is_array($option_ids)) {
    $option_ids = [];
}
$option_ids = array_unique(array_map('intval', $option_ids));

if (empty($option_ids) && $option_other === '') {
    $_SESSION['flash_message']
        = 'กรุณาเลือกหัวข้อ Quick Win อย่างน้อย 1 ข้อ หรือระบุหัวข้ออื่น';
    $_SESSION['flash_type'] = 'warning';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

$options_string = implode('/', $option_ids);

/* =========================
   TRANSACTION
========================= */
try {
    $conn->beginTransaction();

    /* ==================================================
       1) CHECK DUPLICATE
       ครู 1 คน / 1 ปีการศึกษา (ไม่สน p_id)
    ================================================== */
    $checkStmt = $conn->prepare("
        SELECT 1
        FROM quick_win
        WHERE t_pid = ?
          AND academic_year = ?
          AND NOT (
              t_pid = ?
              AND academic_year = ?
              AND supervision_date = ?
          )
        LIMIT 1
    ");
    $checkStmt->execute([
        $new_t_pid,
        $new_academic_year,
        $old_t_pid,
        $new_academic_year,
        $old_date
    ]);

    if ($checkStmt->fetch()) {
        throw new Exception(
            'ครูคนนี้ได้รับการนิเทศ Quick Win ในปีการศึกษานี้แล้ว กรุณาไปแก้ไขจากรายการเดิม'
        );
    }

    /* =========================
       2) DELETE IMAGES
    ========================= */
    if (!empty($delete_image_ids)) {

        $in = implode(',', array_fill(0, count($delete_image_ids), '?'));

        $stmtSel = $conn->prepare("
            SELECT file_name
            FROM images
            WHERE id IN ($in)
              AND form_type = 'qw'
        ");
        $stmtSel->execute($delete_image_ids);

        foreach ($stmtSel->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $path = __DIR__ . '/../uploads/quickwin/' . $f['file_name'];
            if (is_file($path)) {
                unlink($path);
            }
        }

        $stmtDel = $conn->prepare("
            DELETE FROM images
            WHERE id IN ($in)
              AND form_type = 'qw'
        ");
        $stmtDel->execute($delete_image_ids);
    }

    /* =========================
       3) UPDATE QUICK WIN
    ========================= */
    $stmt = $conn->prepare("
        UPDATE quick_win
        SET
            t_pid = ?,
            p_id = ?,
            options = ?,
            option_other = ?,
            academic_year = ?,
            semester = ?,
            updated_at = NOW()
        WHERE t_pid = ?
          AND p_id = ?
          AND supervision_date = ?
    ");
    $stmt->execute([
        $new_t_pid,
        $new_p_id,
        $options_string,
        $option_other,
        $new_academic_year,
        $semester,
        $old_t_pid,
        $old_p_id,
        $old_date
    ]);

    /* =========================
       4) SYNC IMAGE META
    ========================= */
    $stmtImgSync = $conn->prepare("
        UPDATE images
        SET academic_year = ?
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code IS NULL
          AND inspection_time IS NULL
          AND form_type = 'qw'
    ");
    $stmtImgSync->execute([
        $new_academic_year,
        $new_p_id,
        $new_t_pid
    ]);

    /* =========================
       5) ADD NEW IMAGES
    ========================= */
    if (!empty($_FILES['quickwin_images']['name'][0])) {

        $countStmt = $conn->prepare("
            SELECT COUNT(*)
            FROM images
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND academic_year = ?
              AND form_type = 'qw'
        ");
        $countStmt->execute([
            $new_p_id,
            $new_t_pid,
            $new_academic_year
        ]);

        if ((int)$countStmt->fetchColumn() + count($_FILES['quickwin_images']['name']) > 2) {
            throw new Exception('แนบรูปได้ไม่เกิน 2 รูป');
        }

        $uploadDir = __DIR__ . '/../uploads/quickwin/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['quickwin_images']['name'] as $i => $name) {

            if ($_FILES['quickwin_images']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;

            $fileName = 'qw_' . $new_t_pid . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

            if (move_uploaded_file(
                $_FILES['quickwin_images']['tmp_name'][$i],
                $uploadDir . $fileName
            )) {
                $stmtInsert = $conn->prepare("
                    INSERT INTO images
                    (supervisor_p_id, teacher_t_pid, subject_code, inspection_time,
                     file_name, form_type, academic_year)
                    VALUES (?, ?, NULL, NULL, ?, 'qw', ?)
                ");
                $stmtInsert->execute([
                    $new_p_id,
                    $new_t_pid,
                    $fileName,
                    $new_academic_year
                ]);
            }
        }
    }

    /* =========================
       COMMIT
    ========================= */
    $conn->commit();

    $_SESSION['flash_message'] = 'บันทึกการแก้ไข Quick Win สำเร็จ';
    $_SESSION['flash_type'] = 'success';
    header('Location: ../my_sessions_list.php');
    exit;
} catch (PDOException $e) {

    $conn->rollBack();

    if ($e->getCode() === '23000') {
        $_SESSION['flash_message']
            = 'ครูคนนี้ได้รับการนิเทศ Quick Win ในปีการศึกษานี้แล้ว กรุณาไปแก้ไขจากรายการเดิม';
    } else {
        $_SESSION['flash_message'] = $e->getMessage();
    }

    $_SESSION['flash_type'] = 'warning';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
} catch (Exception $e) {

    $conn->rollBack();

    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type'] = 'warning';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
