<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

/* =========================
   AUTH
========================= */
if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request'
    ]);
    exit;
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

/* =========================
   INPUT
========================= */
$teacher_t_pid   = $_POST['t_pid'] ?? null;
$subject_code    = $_POST['subject_code'] ?? null;
$inspection_time = $_POST['inspection_time'] ?? null;

$supervisor_id = $isAdmin
    ? ($_POST['supervisor_p_id'] ?? null)
    : ($_SESSION['user_id'] ?? null);

if (!$teacher_t_pid || !$subject_code || !$inspection_time || !$supervisor_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ข้อมูลไม่ครบ'
    ]);
    exit;
}

/* =========================
   SOFT DELETE
========================= */
try {
    $stmt = $conn->prepare("
        UPDATE supervision_sessions
        SET deleted_at = NOW()
        WHERE p_id = ?
          AND t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");

    $stmt->execute([
        $supervisor_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('ไม่พบข้อมูล หรือไม่มีสิทธิ์ลบ');
    }

    echo json_encode([
        'status' => 'success'
    ]);
    exit;
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
