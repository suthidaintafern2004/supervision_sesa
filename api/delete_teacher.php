<?php
// /api/delete_teacher.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_connect.php';

// รองรับ GET หรือ POST
$t_pid = $_GET['t_pid'] ?? $_POST['t_pid'] ?? null;

// ตรวจสอบว่ามีค่า t_pid หรือไม่ และเป็นตัวเลข 13 หลัก
if (!$t_pid || !preg_match('/^\d{13}$/', $t_pid)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid t_pid']);
    exit;
}

try {
    // ตรวจสอบว่าครูคนนี้มีประวัติการนิเทศหรือยัง
    $check = $conn->prepare("SELECT COUNT(*) AS cnt FROM supervision_sessions WHERE t_pid = :pid");
    $check->execute([':pid' => $t_pid]);
    $count = $check->fetch()['cnt'];

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ เนื่องจากมีประวัติการนิเทศแล้ว']);
        exit;
    }

    // ตรวจสอบว่าครูคนนี้มีประวัติ Quick Win หรือยัง
    $checkQW = $conn->prepare("SELECT COUNT(*) AS cnt FROM quick_win WHERE t_pid = :pid");
    $checkQW->execute([':pid' => $t_pid]);
    if ($checkQW->fetch()['cnt'] > 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบได้ เนื่องจากมีประวัติการนิเทศ Quick Win แล้ว']);
        exit;
    }

    // ลบข้อมูลครู
    $sql = $conn->prepare("DELETE FROM teacher WHERE t_pid = :pid");
    $sql->execute([':pid' => $t_pid]);

    echo json_encode(['success' => true, 'message' => 'ลบข้อมูลสำเร็จ']);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
