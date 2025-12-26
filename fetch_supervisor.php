<?php

/**
 * API สำหรับดึงข้อมูลผู้นิเทศ (Refactored for PDO & New DB)
 */
header('Content-Type: application/json; charset=utf-8');
require_once 'config/db_connect.php'; // เรียกใช้การเชื่อมต่อ PDO

// รับค่า Action หรือ ชื่อที่ต้องการค้นหา
$action = $_GET['action'] ?? '';
$full_name = $_GET['full_name'] ?? '';

try {
    // กรณีที่ 1: ดึงรายชื่อผู้นิเทศทั้งหมด (สำหรับ Dropdown)
    if ($action === 'get_names') {
        // ⭐️ SQL: เชื่อมตาราง prefix เพื่อเอาคำนำหน้าชื่อมาต่อกับชื่อ-นามสกุล
        $sql = "SELECT 
                    CONCAT(IFNULL(p.prefix_name, ''), s.fname, ' ', s.lname) AS full_name 
                FROM supervisor s
                LEFT JOIN prefix p ON s.prefix_id = p.prefix_id
                ORDER BY s.fname ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        $names = $stmt->fetchAll(PDO::FETCH_COLUMN); // ดึงเฉพาะคอลัมน์ full_name เป็น Array
        echo json_encode($names);
        exit;
    }

    // กรณีที่ 2: ดึงรายละเอียดผู้นิเทศตามชื่อ (สำหรับเติมลงช่อง Input)
    if (!empty($full_name)) {
        // ⭐️ SQL: เชื่อมตาราง office และ position เพื่อดึงชื่อหน่วยงานและตำแหน่ง
        $sql = "SELECT 
                    s.p_id,
                    o.office_name,
                    pos.position_name,
                    CONCAT(IFNULL(p.prefix_name, ''), s.fname, ' ', s.lname) AS full_name
                FROM supervisor s
                LEFT JOIN office o ON s.office_id = o.office_id
                LEFT JOIN position pos ON s.position_id = pos.position_id
                LEFT JOIN prefix p ON s.prefix_id = p.prefix_id
                WHERE CONCAT(IFNULL(p.prefix_name, ''), s.fname, ' ', s.lname) = :full_name
                LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้นิเทศ']);
        }
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
