<?php

/****************************************
 * QUICK WIN UPDATE (PRODUCTION FINAL)
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

$option_ids   = $_POST['options'] ?? [];
$option_other = trim($_POST['option_other'] ?? '');
$new_academic_year = $_POST['academic_year'] ?? null;

$form_type = 'qw'; // FIXED สำหรับ Quick Win

$delete_image_ids = $_POST['delete_image_ids'] ?? [];
$delete_image_ids = is_array($delete_image_ids)
    ? array_map('intval', $delete_image_ids)
    : [];

/* =========================
   VALIDATE
========================= */
if (
    !$old_t_pid || !$old_p_id || !$old_date ||
    !$new_t_pid || !$new_p_id || !$new_academic_year
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

    /* =========================
       1) DELETE IMAGES (ที่ผู้ใช้กดลบ)
    ========================= */
    if (!empty($delete_image_ids)) {

        $in = implode(',', array_fill(0, count($delete_image_ids), '?'));

        $stmtSel = $conn->prepare("
            SELECT id, file_name
            FROM images
            WHERE id IN ($in)
              AND form_type = 'qw'
        ");
        $stmtSel->execute($delete_image_ids);
        $files = $stmtSel->fetchAll(PDO::FETCH_ASSOC);

        foreach ($files as $f) {
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
       2) UPDATE QUICK_WIN
    ========================= */
    $stmt = $conn->prepare("
        UPDATE quick_win
        SET
            t_pid = ?,
            p_id = ?,
            options = ?,
            option_other = ?,
            academic_year = ?,
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
        $old_t_pid,
        $old_p_id,
        $old_date
    ]);

    /* =========================
       3) SYNC IMAGE META (รูปที่เหลืออยู่)
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
       4) ADD NEW IMAGES
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
        $existingCount = (int)$countStmt->fetchColumn();

        $newFilesCount = count($_FILES['quickwin_images']['name']);
        if (($existingCount + $newFilesCount) > 2) {
            throw new Exception('แนบรูปได้ไม่เกิน 2 รูป');
        }

        $uploadDir = __DIR__ . '/../uploads/quickwin/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedExt = ['jpg', 'jpeg', 'png'];

        foreach ($_FILES['quickwin_images']['name'] as $i => $name) {

            if ($_FILES['quickwin_images']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt)) {
                continue;
            }

            $newFileName =
                'qw_' . $new_t_pid . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

            if (move_uploaded_file(
                $_FILES['quickwin_images']['tmp_name'][$i],
                $uploadDir . $newFileName
            )) {

                $stmtInsertImg = $conn->prepare("
                    INSERT INTO images
                        (supervisor_p_id, teacher_t_pid,
                         subject_code, inspection_time,
                         file_name, form_type, academic_year)
                    VALUES
                        (?, ?, NULL, NULL, ?, 'qw', ?)
                ");

                $stmtInsertImg->execute([
                    $new_p_id,
                    $new_t_pid,
                    $newFileName,
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
} catch (Exception $e) {

    $conn->rollBack();
    echo '<pre>ERROR: ' . $e->getMessage();
    exit;
}
