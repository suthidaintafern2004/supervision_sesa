<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db_connect.php';

$t_pid        = $_GET['t_pid'] ?? '';
$subject_code = $_GET['subject_code'] ?? '';

if (!$t_pid || !$subject_code) {
    echo json_encode(['success' => false]);
    exit;
}

$sql = "
    SELECT inspection_time
    FROM supervision_sessions
    WHERE teacher_t_pid = :t_pid
      AND subject_code = :subject_code
";  
$stmt = $conn->prepare($sql);
$stmt->execute([
    ':t_pid' => $t_pid,
    ':subject_code' => $subject_code
]);

$usedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

$available = [];
for ($i = 1; $i <= 9; $i++) {
    if (!in_array($i, $usedTimes)) {
        $available[] = $i;
    }
}

echo json_encode([
    'success' => true,
    'available_times' => $available
]);
