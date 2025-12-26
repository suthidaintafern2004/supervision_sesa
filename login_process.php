<?php
// login_process.php
session_start();
require_once 'config/db_connect.php'; // ต้องคืนค่า $conn เป็น PDO

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['error_message'] = "กรุณากรอก Username และ Password";
    header("Location: login.php");
    exit();
}

// ------------------------------------------------------------
// ตรวจรหัสผ่าน (4 ตัวท้ายของเลขบัตรประชาชน)
// ------------------------------------------------------------
$expected_password = substr($username, -4);

if ($password !== $expected_password) {
    $_SESSION['error_message'] = "รหัสผ่านไม่ถูกต้อง (รหัสผ่านคือ 4 ตัวท้ายของเลขบัตรประชาชน)";
    header("Location: login.php");
    exit();
}

try {
    // ------------------------------------------------------------
    // ดึงข้อมูลผู้นิเทศด้วยตาราง ranks
    // ------------------------------------------------------------
    $sql = "
        SELECT 
            s.p_id,
            s.fname,
            s.lname,
            p.prefix_name,
            o.office_name,
            pos.position_name,
            r.rank_name
        FROM supervisor s
        LEFT JOIN prefix p ON p.prefix_id = s.prefix_id
        LEFT JOIN office o ON o.office_id = s.office_id
        LEFT JOIN position pos ON pos.position_id = s.position_id
        LEFT JOIN ranks r ON r.rank_id = s.rank_id
        WHERE s.p_id = :p_id
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':p_id' => $username]);

    if ($stmt->rowCount() !== 1) {
        $_SESSION['error_message'] = "ไม่พบข้อมูลผู้ใช้นี้ในระบบ";
        header("Location: login.php");
        exit();
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ------------------------------------------------------------
    // สร้างชื่อเต็ม
    // ------------------------------------------------------------
    $prefix = $user['prefix_name'] ?? '';
    $full_name = trim($prefix . ' ' . $user['fname'] . ' ' . $user['lname']);

    // ------------------------------------------------------------
    // เก็บข้อมูลลง SESSION
    // ------------------------------------------------------------
    $_SESSION['user_id']        = $user['p_id'];
    $_SESSION['user_name']      = $full_name;
    $_SESSION['office_name']    = $user['office_name'] ?? '';
    $_SESSION['position_name']  = $user['position_name'] ?? '';
    $_SESSION['rank_name']      = $user['rank_name'] ?? '';
    $_SESSION['is_logged_in']   = true;

    // ไปหน้า index หลังล็อกอิน
    header("Location: index.php");
    exit();

} catch (PDOException $e) {
    error_log("Login Process Error: " . $e->getMessage());
    $_SESSION['error_message'] = "เกิดข้อผิดพลาดในระบบฐานข้อมูล";
    header("Location: login.php");
    exit();
}
