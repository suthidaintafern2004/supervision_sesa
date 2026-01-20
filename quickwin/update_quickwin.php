<?php

/****************************************
 * QUICK WIN UPDATE (FINAL - STABLE)
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
$old_t_pid = $_POST['old_t_pid'] ?? null;
$old_p_id  = $_POST['old_p_id'] ?? null;
$old_date  = $_POST['old_supervision_date'] ?? null;

$new_t_pid = $_POST['new_t_pid'] ?? null;
$new_p_id  = $_POST['new_p_id'] ?? null;

$option_ids   = $_POST['options'] ?? [];
$option_other = trim($_POST['option_other'] ?? '');

/* =========================
   VALIDATE : KEY
========================= */
if (!$old_t_pid || !$old_p_id || !$old_date || !$new_t_pid || !$new_p_id) {
    exit('ข้อมูลไม่ครบ (KEY)');
}

/* =========================
   SANITIZE OPTIONS
========================= */
if (!is_array($option_ids)) {
    $option_ids = [];
}
$option_ids = array_unique(array_map('intval', $option_ids));

/* =========================
   VALIDATE : MUST SELECT
========================= */
if (empty($option_ids) && $option_other === '') {
    $_SESSION['flash_message']
        = 'กรุณาเลือกหัวข้อ Quick Win อย่างน้อย 1 ข้อ หรือระบุหัวข้ออื่น';
    $_SESSION['flash_type'] = 'warning';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

/* =========================
   PREPARE OPTIONS STRING
========================= */
$options_string = implode('/', $option_ids);

/* =========================
   CALCULATE ACADEMIC YEAR RANGE
   (พ.ค. – เม.ย.)
========================= */
$ts = strtotime($old_date);
$year  = (int)date('Y', $ts);
$month = (int)date('n', $ts);

if ($month >= 5) {
    $startDate = "$year-05-01 00:00:00";
    $endDate   = ($year + 1) . "-04-30 23:59:59";
} else {
    $startDate = ($year - 1) . "-05-01 00:00:00";
    $endDate   = "$year-04-30 23:59:59";
}

/* =========================
   CHECK DUPLICATE : TEACHER / YEAR
========================= */
if ($new_t_pid != $old_t_pid) {

    $chk = $conn->prepare("
        SELECT 1
        FROM quick_win
        WHERE t_pid = ?
          AND supervision_date BETWEEN ? AND ?
        LIMIT 1
    ");

    $chk->execute([
        $new_t_pid,
        $startDate,
        $endDate
    ]);

    if ($chk->fetch()) {
        $_SESSION['flash_message']
            = 'ไม่สามารถเปลี่ยนชื่อครูได้ เนื่องจากครูคนนี้ได้รับการนิเทศแบบ Quick Win ในปีการศึกษานี้แล้ว';
        $_SESSION['flash_type'] = 'error';

        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit;
    }
}

/* =========================
   UPDATE DATA
========================= */
try {
    $conn->beginTransaction();

    $sql = "
        UPDATE quick_win
        SET
            t_pid = ?,
            p_id = ?,
            options = ?,
            option_other = ?,
            updated_at = NOW()
        WHERE t_pid = ?
          AND p_id = ?
          AND supervision_date = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $new_t_pid,
        $new_p_id,
        $options_string,
        $option_other,
        $old_t_pid,
        $old_p_id,
        $old_date
    ]);

    /* =========================
   HANDLE QUICK WIN IMAGES
========================= */
    if (!empty($_FILES['quickwin_images']['name'][0])) {

        // 🔹 นับรูปเดิม
        $countStmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM inspection_images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code IS NULL
          AND inspection_time IS NULL
    ");
        $countStmt->execute([$new_p_id, $new_t_pid]);
        $existingCount = (int)$countStmt->fetchColumn();

        $newFilesCount = count($_FILES['quickwin_images']['name']);

        // 🔒 จำกัดรวมไม่เกิน 2 รูป
        if (($existingCount + $newFilesCount) > 2) {
            throw new Exception('สามารถแนบรูปได้ไม่เกิน 2 รูปต่อ Quick Win');
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

            $tmp  = $_FILES['quickwin_images']['tmp_name'][$i];
            $size = $_FILES['quickwin_images']['size'][$i];
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt) || $size > 5 * 1024 * 1024) {
                continue;
            }

            $newFileName =
                'qw_' . $new_t_pid . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

            if (move_uploaded_file($tmp, $uploadDir . $newFileName)) {

                $imgStmt = $conn->prepare("
                INSERT INTO inspection_images
                    (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, file_name)
                VALUES
                    (?, ?, NULL, NULL, ?)
            ");

                $imgStmt->execute([
                    $new_p_id,
                    $new_t_pid,
                    $newFileName
                ]);
            }
        }
    }
    $conn->commit();

    $_SESSION['flash_message'] = 'บันทึกการแก้ไข Quick Win สำเร็จ';
    $_SESSION['flash_type']    = 'success';
    $_SESSION['flash_once']    = true;

    header('Location: ../my_sessions_list.php');
    exit;
} catch (Exception $e) {

    $conn->rollBack();

    echo '<pre>';
    echo 'ERROR MESSAGE: ' . $e->getMessage() . "\n";
    echo 'POST DATA:\n';
    print_r($_POST);
    exit;
}
