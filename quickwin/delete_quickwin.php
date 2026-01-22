<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request');
}

$t_pid  = $_POST['t_pid'] ?? null;
$p_id   = $_POST['p_id'] ?? null;
$date   = $_POST['supervision_date'] ?? null;

if (!$t_pid || !$p_id || !$date) {
    exit('ข้อมูลไม่ครบ');
}

$stmt = $conn->prepare("
    UPDATE quick_win
    SET deleted_at = NOW()
    WHERE t_pid = ?
      AND p_id = ?
      AND supervision_date = ?
      AND deleted_at IS NULL
    LIMIT 1
");

$stmt->execute([$t_pid, $p_id, $date]);

/* 👉 ไปหน้าถังขยะ */
header('Location: ../trash/index.php?type=quickwin');
exit;
