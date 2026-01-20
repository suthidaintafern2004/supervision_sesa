<?php
// File: api/get_supervisors.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db_connect.php';

/* =========================
   ตรวจสิทธิ์ Admin
========================= */
if (
    !isset($_SESSION['is_logged_in']) ||
    $_SESSION['role'] !== 'admin'
) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

/* =========================
   Pagination
========================= */
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if ($limit <= 0 || $limit > 100) {
    $limit = 50;
}

$offset = ($page - 1) * $limit;

try {

    $sql = "
        SELECT 
            sp.p_id,
            sp.prefix_id,
            sp.fname,
            sp.lname,
            sp.office_id,
            sp.position_id,
            sp.rank_id,
            sp.role,
            p.prefix_name,
            o.office_name,
            pos.position_name,
            r.rank_name
        FROM supervisor sp
        LEFT JOIN prefix p ON p.prefix_id = sp.prefix_id
        LEFT JOIN office o ON o.office_id = sp.office_id
        LEFT JOIN position pos ON pos.position_id = sp.position_id
        LEFT JOIN ranks r ON r.rank_id = sp.rank_id
        ORDER BY sp.fname ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $data
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database Error'
    ]);
}
