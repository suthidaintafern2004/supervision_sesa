<?php
// File: api/get_supervisors.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_connect.php';

// รับค่าค้นหาและ Pagination
$search = $_GET['search'] ?? '';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit  = 20; // จำนวนรายการต่อหน้า

try {
    // Query ข้อมูลผู้นิเทศพร้อม Join ตารางที่เกี่ยวข้อง
    $sql = "SELECT 
                sp.p_id,
                p.prefix_name,
                sp.fname,
                sp.lname,
                o.office_name,
                pos.position_name,
                r.rank_name
            FROM supervisor sp
            LEFT JOIN prefix p ON p.prefix_id = sp.prefix_id
            LEFT JOIN office o ON o.office_id = sp.office_id
            LEFT JOIN position pos ON pos.position_id = sp.position_id
            LEFT JOIN ranks r ON r.rank_id = sp.rank_id
            WHERE 1=1 ";

    $params = [];

    // เงื่อนไขการค้นหา
    if (!empty($search)) {
        $sql .= " AND (sp.fname LIKE :s OR sp.lname LIKE :s OR sp.p_id LIKE :s OR o.office_name LIKE :s) ";
        $params[':s'] = "%$search%";
    }

    $sql .= " ORDER BY sp.fname ASC LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ส่งค่ากลับเป็น JSON
    echo json_encode(['success' => true, 'data' => $data]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
?>