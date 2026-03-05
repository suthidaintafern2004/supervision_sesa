<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

if (empty($_SESSION['user_id'])) {
    die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request');
}

$supervisor_id = $_SESSION['user_id'];
$isAdmin       = ($_SESSION['role'] ?? '') === 'admin';
$form_type     = $_POST['form_type'] ?? '';

$uploadDir = __DIR__ . '/../uploads/';
$files = [];

try {
    $conn->beginTransaction();

    if ($form_type === 'classroom') {
        $t_pid           = $_POST['t_pid'] ?? '';
        $subject_code    = $_POST['subject_code'] ?? '';
        $inspection_time = $_POST['inspection_time'] ?? '';

        // 1. ดึงชื่อไฟล์รูปเพื่อไปลบใน Folder
        $stmtImg = $conn->prepare("SELECT file_name FROM images WHERE t_pid = ? AND subject_code = ? AND inspection_time = ?");
        $stmtImg->execute([$t_pid, $subject_code, $inspection_time]);
        $files = $stmtImg->fetchAll(PDO::FETCH_COLUMN);

        // 2. ลบข้อมูลจากตารางต่างๆ (ใช้ชื่อฟิลด์ p_id, t_pid ตามโครงสร้างใหม่)
        $conn->prepare("DELETE FROM images WHERE t_pid = ? AND subject_code = ? AND inspection_time = ?")->execute([$t_pid, $subject_code, $inspection_time]);
        $conn->prepare("DELETE FROM kpi_answers WHERE t_pid = ? AND subject_code = ? AND inspection_time = ?")->execute([$t_pid, $subject_code, $inspection_time]);
        $conn->prepare("DELETE FROM kpi_indicator_suggestions WHERE t_pid = ? AND subject_code = ? AND inspection_time = ?")->execute([$t_pid, $subject_code, $inspection_time]);
        $conn->prepare("DELETE FROM supervision_sessions WHERE t_pid = ? AND subject_code = ? AND inspection_time = ? AND deleted_at IS NOT NULL")->execute([$t_pid, $subject_code, $inspection_time]);
    } elseif ($form_type === 'quickwin') {
        $t_pid         = $_POST['t_pid'] ?? '';
        $academic_year = $_POST['academic_year'] ?? '';

        $stmtImg = $conn->prepare("SELECT file_name FROM images WHERE t_pid = ? AND academic_year = ? AND form_type = 'qw'");
        $stmtImg->execute([$t_pid, $academic_year]);
        $files = $stmtImg->fetchAll(PDO::FETCH_COLUMN);

        $conn->prepare("DELETE FROM images WHERE t_pid = ? AND academic_year = ? AND form_type = 'qw'")->execute([$t_pid, $academic_year]);
        $conn->prepare("DELETE FROM quickwin_satisfaction_answers WHERE t_pid = ? AND academic_year = ?")->execute([$t_pid, $academic_year]);
        $conn->prepare("DELETE FROM quick_win WHERE t_pid = ? AND academic_year = ? AND deleted_at IS NOT NULL")->execute([$t_pid, $academic_year]);
    }

    $conn->commit();

    // ลบไฟล์จริงออกจาก Folder uploads
    foreach ($files as $f) {
        if ($f && is_file($uploadDir . $f)) {
            @unlink($uploadDir . $f);
        }
    }

    $_SESSION['flash_success'] = 'ลบข้อมูลถาวรเรียบร้อยแล้ว';
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}

header('Location: index.php');
exit;
