<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

$t_pid           = $_POST['t_pid'] ?? '';
$subject_code    = $_POST['subject_code'] ?? '';
$inspection_time = $_POST['inspection_time'] ?? '';

if (!$t_pid || !$subject_code || !$inspection_time) {
    die('ข้อมูลไม่ครบ');
}

$stmt = $conn->prepare("
    UPDATE quick_win
    SET deleted_at = NOW()
    WHERE t_pid = ?
      AND subject_code = ?
      AND inspection_time = ?
      AND deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$t_pid, $subject_code, $inspection_time]);

header('Location: ../index.php?deleted=quickwin');
exit;
