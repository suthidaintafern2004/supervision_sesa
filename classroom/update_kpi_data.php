<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

$supervisor_id      = $_SESSION['user_id'] ?? null;
$teacher_t_pid      = $_POST['old_t_pid'] ?? null;
$subject_code       = $_POST['old_subject_code'] ?? null;
$inspection_time    = $_POST['old_inspection_time'] ?? null;

if (!$supervisor_id || !$teacher_t_pid || !$subject_code || !$inspection_time) {
    die('ข้อมูลไม่ครบ ไม่สามารถลบได้');
}

try {
    $conn->beginTransaction();

    /* =========================
       1) ลบรูปภาพ
    ========================= */
    $stmtImg = $conn->prepare("
        SELECT file_name 
        FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ");
    $stmtImg->execute([
        $supervisor_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    foreach ($stmtImg->fetchAll(PDO::FETCH_COLUMN) as $file) {
        $path = '../uploads/' . $file;
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    $conn->prepare("
        DELETE FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ")->execute([
        $supervisor_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    /* =========================
       2) ลบ KPI answers
    ========================= */
    $conn->prepare("
        DELETE FROM kpi_answers
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ")->execute([
        $supervisor_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    /* =========================
       3) ลบข้อค้นพบ
    ========================= */
    $conn->prepare("
        DELETE FROM kpi_indicator_suggestions
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ")->execute([
        $supervisor_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    /* =========================
       4) ลบ session หลัก
    ========================= */
    $conn->prepare("
        DELETE FROM supervision_sessions
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ")->execute([
        $supervisor_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    $conn->commit();

    echo "<script>
        alert('ลบแบบบันทึกเรียบร้อยแล้ว');
        window.location.href = '../index.php';
    </script>";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo 'Error: ' . $e->getMessage();
}
