<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['is_logged_in'])) {
    echo json_encode(['success' => false]);
    exit;
}

$pid = $_GET['pid'] ?? '';
if ($pid === '') {
    echo json_encode(['success' => false]);
    exit;
}

$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$login_pid = $_SESSION['user_id'] ?? '';

if (!$is_admin && $pid !== $login_pid) {
    echo json_encode(['success' => false, 'message' => 'forbidden']);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        sp.p_id,
        p.prefix_name,
        sp.fname,
        sp.lname,
        o.office_name,
        pos.position_name,
        r.rank_name,
        sp.role
    FROM supervisor sp
    LEFT JOIN prefix p ON p.prefix_id = sp.prefix_id
    LEFT JOIN office o ON o.office_id = sp.office_id
    LEFT JOIN position pos ON pos.position_id = sp.position_id
    LEFT JOIN ranks r ON r.rank_id = sp.rank_id
    WHERE sp.p_id = ?
    LIMIT 1
");
$stmt->execute([$pid]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $data
]);
