<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$t_pid  = $_POST['t_pid'] ?? null;
$p_id   = $_POST['p_id'] ?? null;
$date   = $_POST['supervision_date'] ?? null;

if (!$t_pid || !$p_id || !$date) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE quick_win
    SET deleted_at = NOW()
    WHERE t_pid = ?
      AND p_id = ?
      AND supervision_date = ?
      AND deleted_at IS NULL
");

$stmt->execute([$t_pid, $p_id, $date]);

if ($stmt->rowCount() === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบข้อมูล หรือข้อมูลถูกลบไปแล้ว'
    ]);
    exit;
}

echo json_encode(['status' => 'success']);
exit;
