<?php
session_start();
require_once '../config/db_connect.php';

/* ===== Auth ===== */
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$id = $_POST['id'] ?? null;
if (!$id) {
    http_response_code(400);
    exit('Invalid ID');
}

/* ===== Load image ===== */
$stmt = $conn->prepare("
    SELECT file_name 
    FROM inspection_images 
    WHERE id = ?
");
$stmt->execute([$id]);
$file = $stmt->fetchColumn();

if ($file) {
    @unlink(__DIR__ . '/../uploads/quickwin/' . $file);

    $del = $conn->prepare("
        DELETE FROM inspection_images 
        WHERE id = ?
    ");
    $del->execute([$id]);
}

echo 'ok';
