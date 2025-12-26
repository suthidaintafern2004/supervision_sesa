<?php
// File: api/update_supervisor.php
header('Content-Type: application/json');
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่า
    $p_id        = $_POST['p_id'] ?? '';
    $prefix_id   = $_POST['prefix_id'] ?? '';
    $fname       = trim($_POST['fname'] ?? '');
    $lname       = trim($_POST['lname'] ?? '');
    $office_id   = $_POST['office_id'] ?? '';
    $position_id = $_POST['position_id'] ?? '';
    $rank_id     = $_POST['rank_id'] ?? null;

    // 2. ตรวจสอบความครบถ้วน
    if (empty($p_id) || empty($prefix_id) || empty($fname) || empty($lname) || empty($office_id) || empty($position_id)) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    try {
        // 3. บันทึกข้อมูล
        $sql = "UPDATE supervisor SET 
                    prefix_id   = :prefix,
                    fname       = :fname,
                    lname       = :lname,
                    office_id   = :office,
                    position_id = :pos,
                    rank_id     = :rank
                WHERE p_id = :pid";

        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':prefix' => $prefix_id,
            ':fname'  => $fname,
            ':lname'  => $lname,
            ':office' => $office_id,
            ':pos'    => $position_id,
            ':rank'   => empty($rank_id) ? NULL : $rank_id,
            ':pid'    => $p_id
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'บันทึกสำเร็จ']);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถบันทึกข้อมูลได้']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
}
