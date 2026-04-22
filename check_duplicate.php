<?php
// check_duplicate.php
require_once __DIR__ . '/config/db_connect.php';

// รับค่าที่ส่งมาจาก JavaScript
$t_pid           = $_POST['t_pid'] ?? '';
$academic_year   = intval($_POST['academic_year'] ?? 0);
$form_type       = $_POST['form_type'] ?? 'classroom';

if ($form_type === 'quickwin') {
    // ตรวจซ้ำสำหรับ Quick Win (1 ครั้ง / 1 ปีการศึกษา)
    $sql = "SELECT 1 FROM quick_win 
            WHERE t_pid = ? 
              AND deleted_at IS NULL
              AND academic_year = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$t_pid, $academic_year]);
} else {
    // ตรวจซ้ำสำหรับ Classroom
    $subject_code    = trim($_POST['subject_code'] ?? '');
    $inspection_time = intval($_POST['inspection_time'] ?? 0);
    $subject_code_db = preg_replace('/\s+/', '', strtolower($subject_code));

    $sql = "SELECT 1 FROM supervision_sessions 
            WHERE t_pid = ? 
              AND REPLACE(LOWER(subject_code),' ','') = ? 
              AND inspection_time = ?
              AND deleted_at IS NULL
              AND academic_year = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$t_pid, $subject_code_db, $inspection_time, $academic_year]);
}

// คืนค่ากลับเป็น JSON
echo json_encode(['is_duplicate' => (bool)$stmt->fetch()]);
