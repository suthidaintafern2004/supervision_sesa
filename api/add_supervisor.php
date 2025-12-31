<?php
// File: api/add_supervisor.php
header('Content-Type: application/json');
require_once "../config/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่า
    $p_id        = trim($_POST['p_id'] ?? ''); // รับรหัสบัตรประชาชนด้วย
    $prefix_id   = $_POST['prefix_id'] ?? '';
    $fname       = trim($_POST['fname'] ?? '');
    $lname       = trim($_POST['lname'] ?? '');
    $office_id   = $_POST['office_id'] ?? '';
    $position_id = $_POST['position_id'] ?? '';
    $rank_id     = $_POST['rank_id'] ?? null;

    // 2. Validation
    if (empty($p_id) || empty($prefix_id) || empty($fname) || empty($lname) || empty($office_id) || empty($position_id)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    if (strlen($p_id) != 13 || !is_numeric($p_id)) {
        echo json_encode(['success' => false, 'message' => 'รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลัก']);
        exit;
    }

    try {
        // เช็คซ้ำ
        $check = $conn->prepare("SELECT COUNT(*) FROM supervisor WHERE p_id = :pid");
        $check->execute([':pid' => $p_id]);
        if ($check->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'รหัสบัตรประชาชนนี้มีอยู่ในระบบแล้ว']);
            exit;
        }

        $sql = "INSERT INTO supervisor (p_id, prefix_id, fname, lname, office_id, position_id, rank_id)
                VALUES (:pid, :prefix_id, :fname, :lname, :office_id, :position_id, :rank_id)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ":pid"         => $p_id,
            ":prefix_id"   => $prefix_id,
            ":fname"       => $fname,
            ":lname"       => $lname,
            ":office_id"   => $office_id,
            ":position_id" => $position_id,
            ":rank_id"     => empty($rank_id) ? NULL : $rank_id
        ]);

        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
}
