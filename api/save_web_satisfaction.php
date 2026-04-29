<?php
// ปิดการแสดง Error ของ PHP เพื่อไม่ให้โครงสร้าง JSON พัง
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // 1. สร้างตารางอัตโนมัติหากยังไม่มี
    $sql_create = "
        CREATE TABLE IF NOT EXISTS `web_satisfaction` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `rating` int(1) NOT NULL COMMENT 'คะแนน 1-5',
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->exec($sql_create);

    // 2. รับค่าคะแนน (1-5)
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

    if ($rating >= 1 && $rating <= 5) {
        // 3. บันทึกลงฐานข้อมูล
        $stmt = $conn->prepare("INSERT INTO web_satisfaction (rating) VALUES (?)");
        $stmt->execute([$rating]);

        ob_clean(); // ล้าง Output ขยะก่อนหน้าทั้งหมด
        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลสำเร็จ']);
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'คะแนนไม่ถูกต้อง']);
    }
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>