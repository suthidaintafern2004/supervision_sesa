<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // 1. รับค่าจากฟอร์ม
    $t_pid           = trim($_POST['t_pid'] ?? '');
    $prefix_id       = $_POST['prefix_id'] ?? '';
    $f_name          = trim($_POST['f_name'] ?? '');
    $l_name          = trim($_POST['l_name'] ?? '');
    $position_id     = $_POST['position_id'] ?? '';
    $rank_id         = $_POST['rank_id'] ?? null;
    $school_id       = $_POST['school_id'] ?? '';
    $subjectgroup_id = $_POST['subjectgroup_id'] ?? null;

    // 2. Validation
    if (
        empty($t_pid) ||
        empty($prefix_id) ||
        empty($f_name) ||
        empty($l_name) ||
        empty($position_id) ||
        empty($school_id) ||
        empty($subjectgroup_id)
    ) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
        ]);
        exit;
    }

    if (strlen($t_pid) !== 13 || !ctype_digit($t_pid)) {
        echo json_encode([
            'success' => false,
            'message' => 'รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'
        ]);
        exit;
    }

    // 3. ตรวจสอบซ้ำ
    $stmt = $conn->prepare("SELECT COUNT(*) FROM teacher WHERE t_pid = :pid");
    $stmt->execute([':pid' => $t_pid]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'รหัสบัตรประชาชนนี้มีอยู่ในระบบแล้ว'
        ]);
        exit;
    }

    // 4. office_id
    $office_id = $conn->query("SELECT office_id FROM office LIMIT 1")->fetchColumn() ?: '1000520001';

    // 5. INSERT
    $sql = "
        INSERT INTO teacher
        (
            office_id,
            school_id,
            t_pid,
            prefix_id,
            f_name,
            m_name,
            l_name,
            subjectgroup_id,
            position_id,
            rank_id
        )
        VALUES
        (
            :office,
            :school,
            :pid,
            :prefix,
            :fname,
            NULL,
            :lname,
            :subjectgroup_id,
            :pos,
            :rank
        )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':office'          => $office_id,
        ':school'          => $school_id,
        ':pid'             => $t_pid,
        ':prefix'          => $prefix_id,
        ':fname'           => $f_name,
        ':lname'           => $l_name,
        ':subjectgroup_id' => $subjectgroup_id,
        ':pos'             => $position_id,
        ':rank'            => $rank_id ?: null
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()   // 👈 ดู error จริง
    ]);
}
