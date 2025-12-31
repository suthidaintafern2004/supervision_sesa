<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

/* =========================
   ตรวจสอบสิทธิ์
========================= */
if (empty($_SESSION['user_id'])) {
    die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

/* =========================
   รับค่าจากฟอร์ม (ต้องตรงกับ kpi_edit.php)
========================= */
$supervisor_id   = $_SESSION['user_id'];
$teacher_t_pid   = $_POST['t_pid'] ?? '';
$subject_code    = $_POST['subject_code'] ?? '';
$inspection_time = $_POST['inspection_time'] ?? '';

if (!$teacher_t_pid || !$subject_code || !$inspection_time) {
    die('ข้อมูลไม่ครบ');
}

$uploadDir = "../uploads/";

try {
    $conn->beginTransaction();

    /* =========================
       1) ลบรูปภาพ (ไฟล์ + DB)
    ========================= */
    $imgStmt = $conn->prepare("
        SELECT file_name
        FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ");
    $imgStmt->execute([
        $supervisor_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($images as $img) {
        $filePath = $uploadDir . $img;
        if (is_file($filePath)) {
            @unlink($filePath);
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
       2) ลบคำตอบ KPI
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
       3) ลบข้อค้นพบรายตัวชี้วัด
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
       4) ลบ session หลัก (ตัวตัดสิน)
    ========================= */
    $stmt = $conn->prepare("
        DELETE FROM supervision_sessions
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ");
    $stmt->execute([
        $supervisor_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    // ถ้าไม่ลบได้จริง → rollback
    if ($stmt->rowCount() === 0) {
        $conn->rollBack();
        die('❌ ไม่พบข้อมูลการนิเทศที่ต้องการลบ');
    }

    $conn->commit();

    echo "<script>
        alert('🗑️ ลบข้อมูลการนิเทศเรียบร้อยแล้ว');
        window.location.href = '../index.php';
    </script>";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}
