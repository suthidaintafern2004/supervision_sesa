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

/* ===== Load image (FROM images table) ===== */
$stmt = $conn->prepare("
    SELECT file_name 
    FROM images 
    WHERE id = ?
");
$stmt->execute([$id]);
$file = $stmt->fetchColumn();

if ($file) {

    // ลบไฟล์จริง
    $path = __DIR__ . '/../uploads/quickwin/' . $file;
    if (is_file($path)) {
        unlink($path);
    }

    // ลบ record
    $del = $conn->prepare("
        DELETE FROM images
        WHERE id = ?
    ");
    $del->execute([$id]);
}

echo 'ok';
