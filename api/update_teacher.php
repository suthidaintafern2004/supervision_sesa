<?php
// File: api/update_teacher.php

header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_connect.php';

// ปิด error HTML (สำคัญมาก)
ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request Method']);
    exit;
}

try {
    // =========================
    // 1. รับค่าจากฟอร์ม
    // =========================
    $t_pid           = $_POST['t_pid'] ?? '';
    $prefix_id       = $_POST['prefix_id'] ?? null;
    $f_name          = trim($_POST['f_name'] ?? '');
    $l_name          = trim($_POST['l_name'] ?? '');
    $position_id     = $_POST['position_id'] ?? null;
    $rank_id         = $_POST['rank_id'] ?? null;
    $subjectgroup_id = $_POST['subjectgroup_id'] ?? null;
    $school_id       = $_POST['school_id'] ?? '';

    // =========================
    // 2. Validation
    // =========================
    if (!$t_pid || !$f_name || !$l_name || !$school_id || !$position_id) {
        echo json_encode(['success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน']);
        exit;
    }

    // =========================
    // 3. หา subject_id จาก subjectgroup_id
    // =========================
    $subject_id = null;

    if ($subjectgroup_id) {
        $stmt = $conn->prepare("
            SELECT subject_id
            FROM subject
            WHERE subjectgroup_id = :subjectgroup_id
            LIMIT 1
        ");
        $stmt->execute([':subjectgroup_id' => $subjectgroup_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $subject_id = $row['subject_id'];
        }
    }

    // =========================
    // 4. UPDATE teacher
    // =========================
    $sql = "
        UPDATE teacher SET
            prefix_id   = :prefix_id,
            f_name      = :f_name,
            l_name      = :l_name,
            position_id = :position_id,
            rank_id     = :rank_id,
            subject_id  = :subject_id,
            school_id   = :school_id
        WHERE t_pid = :t_pid
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':prefix_id'   => $prefix_id,
        ':f_name'      => $f_name,
        ':l_name'      => $l_name,
        ':position_id' => $position_id,
        ':rank_id'     => $rank_id,
        ':subject_id'  => $subject_id,
        ':school_id'   => $school_id,
        ':t_pid'       => $t_pid,
    ]);

    echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

} catch (PDOException $e) {
    // 🔥 ส่งเป็น JSON เท่านั้น
    echo json_encode([
        'success' => false,
        'message' => 'Database Error',
        'error'   => $e->getMessage()
    ]);
}
