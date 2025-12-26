<?php
// config/db_connect.php

// ----------------------------------------------------------------
// ⚠️ ส่วนสำหรับ Admin (แก้ไขข้อมูลการเชื่อมต่อตรงนี้)
// ----------------------------------------------------------------
$host     = "localhost";        // ถ้า Database อยู่เครื่องเดียวกับเว็บใช้ localhost (ถ้าอยู่คนละเครื่องให้ใส่ IP)
$dbname   = "sesa_db";      // ชื่อฐานข้อมูล (ควรตรงกับไฟล์ .sql ที่ Import)
$username = "root";             // ⚠️ แก้เป็น Username ของ Server
$password = "";                 // ⚠️ แก้เป็น Password ของ Server
// ----------------------------------------------------------------

try {
    // สร้างการเชื่อมต่อ PDO
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // ตั้งค่า Error Mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // กรณีเชื่อมต่อไม่ได้ ให้แสดง Error (ใน Production อาจปิด die() นี้ได้)
    die("Connection failed: " . $e->getMessage());
}
?>