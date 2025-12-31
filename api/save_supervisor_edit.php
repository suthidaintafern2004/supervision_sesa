<?php
// ------------------------------------------------------------
// ไฟล์: /api/save_supervisor_edit.php
// หน้าที่: บันทึกการแก้ไขข้อมูลผู้นิเทศ
// ------------------------------------------------------------

session_start();
require_once '../config/db_connect.php';

// ต้องล็อกอินก่อน
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo "<script>alert('กรุณาเข้าสู่ระบบก่อน'); window.location.href='../login.php';</script>";
    exit();
}

// ตรวจสอบการส่งข้อมูลแบบ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('ไม่อนุญาตให้เข้าถึงหน้าโดยตรง'); window.location.href='../edit_supervisor_list.php';</script>";
    exit();
}

// รับข้อมูลจากฟอร์ม
$p_id       = $_POST['p_id']       ?? null;
$prefix_id  = $_POST['prefix_id']  ?? null;
$fname      = $_POST['fname']      ?? '';
$lname      = $_POST['lname']      ?? '';
$office_id  = $_POST['office_id']  ?? null;
$position_id= $_POST['position_id']?? null;
$rank_id    = $_POST['rank_id']    ?? null;

// ตรวจสอบค่าที่จำเป็น
if (!$p_id || !$prefix_id || !$fname || !$lname || !$office_id || !$position_id || !$rank_id) {
    echo "<script>alert('กรุณากรอกข้อมูลให้ครบถ้วน'); window.location.href='../edit_supervisor.php?p_id=$p_id';</script>";
    exit();
}

try {
    // Update supervisor
    $sql = "
        UPDATE supervisor SET
            prefix_id   = :prefix_id,
            fname       = :fname,
            lname       = :lname,
            office_id   = :office_id,
            position_id = :position_id,
            rank_id     = :rank_id
        WHERE p_id = :p_id
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':prefix_id'   => $prefix_id,
        ':fname'       => $fname,
        ':lname'       => $lname,
        ':office_id'   => $office_id,
        ':position_id' => $position_id,
        ':rank_id'     => $rank_id,
        ':p_id'        => $p_id
    ]);

    echo "<script>
            alert('บันทึกข้อมูลสำเร็จ');
            window.location.href='../edit_supervisor_list.php';
          </script>";
    exit();

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Error saving supervisor: " . $e->getMessage() . "</h3>";
    exit();
}
