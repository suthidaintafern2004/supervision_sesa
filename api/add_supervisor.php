<?php
// File: api/add_supervisor.php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once "../config/db_connect.php";

/* =======================
   ตรวจสิทธิ์ Admin
======================= */
if (
    !isset($_SESSION['is_logged_in']) ||
    $_SESSION['role'] !== 'admin'
) {
    echo json_encode([
        'success' => false,
        'message' => 'ไม่มีสิทธิ์ดำเนินการ'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Request'
    ]);
    exit;
}

/* =======================
   รับค่า
======================= */
$p_id        = trim($_POST['p_id'] ?? '');
$prefix_id   = $_POST['prefix_id'] ?? '';
$fname       = trim($_POST['fname'] ?? '');
$lname       = trim($_POST['lname'] ?? '');
$office_id   = $_POST['office_id'] ?? '';
$position_id = $_POST['position_id'] ?? '';
$rank_id     = $_POST['rank_id'] ?? null;
$role        = $_POST['role'] ?? 'supervisor';

/* =======================
   Validation
======================= */
if (
    empty($p_id) || empty($prefix_id) || empty($fname) ||
    empty($lname) || empty($office_id) || empty($position_id)
) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
    ]);
    exit;
}

if (!ctype_digit($p_id) || strlen($p_id) !== 13) {
    echo json_encode([
        'success' => false,
        'message' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'
    ]);
    exit;
}

try {
    /* =======================
       เช็คซ้ำ
    ======================= */
    $chk = $conn->prepare("SELECT COUNT(*) FROM supervisor WHERE p_id = :pid");
    $chk->execute([':pid' => $p_id]);

    if ($chk->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'รหัสบัตรประชาชนนี้มีอยู่ในระบบแล้ว'
        ]);
        exit;
    }

    /* =======================
       Insert
    ======================= */
    $sql = "INSERT INTO supervisor 
        (p_id, prefix_id, fname, lname, office_id, position_id, rank_id, role)
        VALUES
        (:pid, :prefix, :fname, :lname, :office, :position, :rank, :role)";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':pid'      => $p_id,
        ':prefix'   => $prefix_id,
        ':fname'    => $fname,
        ':lname'    => $lname,
        ':office'   => $office_id,
        ':position' => $position_id,
        ':rank'     => $rank_id ?: null,
        ':role'     => $role
    ]);

    echo json_encode([
        'success' => true
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'DB Error: ' . $e->getMessage()
    ]);
}
