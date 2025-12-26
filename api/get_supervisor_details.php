<?php
// File: api/get_supervisor.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_connect.php';

// 1. รับค่า p_id จาก URL (GET Method)
$p_id = $_GET['p_id'] ?? '';

// 2. ตรวจสอบว่ามีค่าส่งมาหรือไม่
if (empty($p_id)) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสผู้นิเทศ (Missing p_id)']);
    exit;
}

try {
    // 3. เตรียมคำสั่ง SQL ดึงข้อมูลผู้นิเทศ 1 รายการ
    $sql = "SELECT 
                p_id, 
                prefix_id, 
                fname, 
                lname, 
                office_id, 
                position_id, 
                rank_id 
            FROM supervisor 
            WHERE p_id = :pid 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':pid' => $p_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. ส่งค่ากลับเป็น JSON
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้นิเทศรายนี้ในระบบ']);
    }
} catch (PDOException $e) {
    // กรณี Database Error
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
