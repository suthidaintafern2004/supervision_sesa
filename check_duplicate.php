<?php
// check_duplicate.php
require_once __DIR__ . '/config/db_connect.php';

// รับค่าที่ส่งมาจาก JavaScript
$t_pid           = $_POST['t_pid'] ?? '';
$subject_code    = trim($_POST['subject_code'] ?? '');
$inspection_time = intval($_POST['inspection_time'] ?? 0);

// ตรวจซ้ำในฐานข้อมูล
$sql = "SELECT 1 FROM supervision_sessions 
        WHERE t_pid = ? 
          AND subject_code = ? 
          AND inspection_time = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$t_pid, $subject_code, $inspection_time]);

// คืนค่ากลับเป็น JSON
echo json_encode(['is_duplicate' => (bool)$stmt->fetch()]);
