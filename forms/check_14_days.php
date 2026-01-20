<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db_connect.php';

$teacher_t_pid = $_GET['t_pid'] ?? '';
$subject_code  = trim($_GET['subject_code'] ?? '');

if (!$teacher_t_pid || !$subject_code) {
    echo json_encode(['ok' => false]);
    exit;
}

$subject_code_db = preg_replace('/\s+/', '', strtolower($subject_code));

$sql = "
    SELECT inspection_date
    FROM supervision_sessions
    WHERE teacher_t_pid = ?
      AND REPLACE(LOWER(subject_code),' ','') = ?
      AND inspection_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->execute([$teacher_t_pid, $subject_code_db]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode([
        'ok' => false,
        'last_date' => $row['inspection_date']
    ]);
} else {
    echo json_encode(['ok' => true]);
}
