<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$old_t_pid = $_POST['old_t_pid'] ?? null;
$old_p_id  = $_POST['old_p_id'] ?? null;
$old_date  = $_POST['old_supervision_date'] ?? null;

$new_t_pid         = $_POST['new_t_pid'] ?? $old_t_pid;
$new_p_id          = $_POST['new_p_id'] ?? $old_p_id;
$new_academic_year = $_POST['academic_year'] ?? null;
$semester          = $_POST['semester'] ?? null;
$option_other      = trim($_POST['option_other'] ?? '');
$option_ids        = $_POST['options'] ?? [];

$options_string    = implode('/', array_map('intval', $option_ids));
$delete_image_ids  = $_POST['delete_image_ids'] ?? [];

if (!$old_t_pid || !$old_p_id || !$old_date || !$new_academic_year) {
    die('ข้อมูลไม่ครบถ้วนสำหรับการแก้ไข');
}

try {
    $conn->beginTransaction();

    // 1) อัปเดตข้อมูลหลัก
    $stmtUpdate = $conn->prepare("
        UPDATE quick_win
        SET t_pid = ?, p_id = ?, options = ?, option_other = ?, academic_year = ?, semester = ?, updated_at = NOW()
        WHERE t_pid = ? AND p_id = ? AND supervision_date = ?
    ");
    $stmtUpdate->execute([$new_t_pid, $new_p_id, $options_string, $option_other, $new_academic_year, $semester, $old_t_pid, $old_p_id, $old_date]);

    // 2) อัปเดตรูปภาพเดิมให้ผูกกับ ID ใหม่
    $stmtUpdImg = $conn->prepare("
        UPDATE images 
        SET p_id = ?, t_pid = ?, academic_year = ? 
        WHERE p_id = ? AND t_pid = ? AND form_type = 'qw'
    ");
    $stmtUpdImg->execute([$new_p_id, $new_t_pid, $new_academic_year, $old_p_id, $old_t_pid]);

    // 3) จัดการลบรูปภาพที่สั่งลบจากหน้า Form
    if (!empty($delete_image_ids)) {
        foreach ($delete_image_ids as $img_id) {
            $stmtImg = $conn->prepare("SELECT file_name FROM images WHERE id = ?");
            $stmtImg->execute([$img_id]);
            $fileName = $stmtImg->fetchColumn();

            if ($fileName) {
                $path = __DIR__ . "/../uploads/quickwin/" . $fileName;
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            $conn->prepare("DELETE FROM images WHERE id = ?")->execute([$img_id]);
        }
    }

    // 4) อัปโหลดรูปภาพใหม่
    if (!empty($_FILES['quickwin_images']['name'][0])) {
        $targetDir = __DIR__ . '/../uploads/quickwin/';
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        foreach ($_FILES['quickwin_images']['name'] as $i => $name) {
            if ($_FILES['quickwin_images']['error'][$i] === UPLOAD_ERR_OK) {
                $tmpName = $_FILES['quickwin_images']['tmp_name'][$i];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $newFileName = 'qw_' . $new_t_pid . '_' . time() . '_' . rand(100, 999) . '.' . $ext;

                if (move_uploaded_file($tmpName, $targetDir . $newFileName)) {
                    $stmtIn = $conn->prepare("
                        INSERT INTO images 
                        (p_id, t_pid, subject_code, inspection_time, file_name, academic_year, form_type) 
                        VALUES (?, ?, '', 0, ?, ?, 'qw')
                    ");
                    $stmtIn->execute([$new_p_id, $new_t_pid, $newFileName, $new_academic_year]);
                }
            }
        }
    }

    $conn->commit();
    $_SESSION['flash_message'] = 'บันทึกการแก้ไขเรียบร้อยแล้ว';
    $_SESSION['flash_type'] = 'success';
    header('Location: ../my_sessions_list.php?success=update');
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $_SESSION['flash_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    $_SESSION['flash_type'] = 'danger';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
