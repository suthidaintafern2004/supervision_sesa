<?php
session_start();
require_once '../config/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$t_pid  = $_POST['t_pid'] ?? null;
$p_id   = $_POST['p_id'] ?? null;
$date   = $_POST['supervision_date'] ?? null;
$academic_year = $_POST['academic_year'] ?? null;

if (!$t_pid || !$p_id || !$date) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบ']);
    exit;
}

$sql = "UPDATE quick_win SET deleted_at = NOW() WHERE t_pid = ? AND p_id = ? AND supervision_date = ? AND deleted_at IS NULL";
$params = [$t_pid, $p_id, $date];

if ($academic_year) {
    $sql .= " AND academic_year = ?";
    $params[] = $academic_year;
}

$stmt = $conn->prepare($sql);
$stmt->execute($params);

if ($stmt->rowCount() === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบข้อมูล หรือข้อมูลถูกลบไปแล้ว'
    ]);
    exit;
}

// ลบข้อมูลความพึงพอใจที่เกี่ยวข้อง
$delSatSql = "DELETE FROM quickwin_satisfaction_answers WHERE p_id = ? AND t_pid = ?";
$delSatParams = [$p_id, $t_pid];
if ($academic_year) {
    $delSatSql .= " AND academic_year = ?";
    $delSatParams[] = $academic_year;
}
$conn->prepare($delSatSql)->execute($delSatParams);

echo json_encode(['status' => 'success']);
exit;
