<?php
// File: api/get_teachers.php
header('Content-Type: application/json');
require_once '../config/db_connect.php';

$search = $_GET['search'] ?? '';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit  = 20; // จำนวนที่จะโหลดต่อครั้ง (แนะนำ 20 เพื่อให้เต็มหน้าจอพอดี)

try {
    // Query หลัก (เหมือนหน้าเดิมแต่เพิ่ม WHERE และ LIMIT)
    $sql = "SELECT 
                t.t_pid,
                p.prefix_name,
                t.f_name,
                t.l_name,
                pos.position_name,
                r.rank_name,
                s.school_name
            FROM teacher t
            LEFT JOIN prefix p ON p.prefix_id = t.prefix_id
            LEFT JOIN position pos ON pos.position_id = t.position_id
            LEFT JOIN ranks r ON r.rank_id = t.rank_id
            LEFT JOIN school s ON s.school_id = t.school_id
            WHERE 1=1 ";

    $params = [];

    // ถ้ามีการค้นหา
    if (!empty($search)) {
        $sql .= " AND (t.f_name LIKE :s OR t.l_name LIKE :s OR t.t_pid LIKE :s OR s.school_name LIKE :s) ";
        $params[':s'] = "%$search%";
    }

    $sql .= " ORDER BY t.f_name ASC LIMIT $limit OFFSET $offset";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ส่งกลับเป็น JSON
    echo json_encode(['success' => true, 'data' => $teachers]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>