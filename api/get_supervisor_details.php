<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db_connect.php';

if (
    !isset($_SESSION['is_logged_in']) ||
    $_SESSION['role'] !== 'admin'
) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$p_id = $_GET['p_id'] ?? '';

if ($p_id === '') {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

$sql = "SELECT * FROM supervisor WHERE p_id = :pid LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([':pid' => $p_id]);

$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Not found']);
}
