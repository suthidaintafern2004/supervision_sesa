<?php
// forms/save_quickwin_data.php

// 1. ตรวจสอบ Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// เชื่อมต่อฐานข้อมูล (PDO)
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

function redirect_with_flash_message($message, $location = '../index.php')
{
    $_SESSION['flash_message'] = $message;
    echo "<script>window.location.href='$location';</script>";
    exit();
}

// 2. ตรวจสอบ Method
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_SESSION['inspection_data'])) {
        redirect_with_flash_message("Session หมดอายุ กรุณาทำรายการใหม่", "../index.php");
    }

    // 3. รับข้อมูลจากฟอร์ม
    $p_id         = trim($_POST['supervisor_p_id'] ?? '');
    $t_id         = trim($_POST['teacher_t_pid']   ?? '');
    // รับค่าเป็น Array จาก Checkbox
    $option_ids   = $_POST['option_ids']           ?? [];
    $option_other = trim($_POST['option_other']    ?? '');
    // ใส่วงเล็บครอบชุดแรก แล้วต่อด้วยเวลาปัจจุบัน
    $supervision_date = ($_POST['supervision_date'] ?? date('Y-m-d')) . " " . date("H:i:s");

    // 4. Validation
    // ต้องเลือกอย่างน้อย 1 ข้อ หรือ กรอกช่องอื่นๆ
    if ($p_id === '' || $t_id === '' || (empty($option_ids) && $option_other === '')) {
        redirect_with_flash_message("กรุณาเลือกหัวข้อ Quick Win อย่างน้อย 1 ข้อ หรือระบุหัวข้ออื่นๆ", "quickwin_form.php");
    }

    // แปลง Array เป็น String คั่นด้วย / (เช่น "1/5/12")
    $options_str = !empty($option_ids) ? implode('/', $option_ids) : '';

    // 5. บันทึกข้อมูล
    try {
        $conn->beginTransaction();

        // ใช้คำสั่งนี้เพื่อให้สามารถ "บันทึกซ้ำ" (แก้ไข) ในวันเดิมได้
        $sql = "INSERT INTO quick_win (p_id, t_pid, options, option_other, supervision_date)
                VALUES (:pid, :tid, :opt, :other, :sdate)
                ON DUPLICATE KEY UPDATE
                options = :opt_update,
                option_other = :other_update";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            ':pid'   => $p_id,
            ':tid'   => $t_id,
            ':opt'   => $options_str,
            ':other' => $option_other,
            ':sdate' => $supervision_date,
            // ส่งค่าเดิมไปซ้ำเพื่อใช้ในส่วน UPDATE
            ':opt_update'   => $options_str,
            ':other_update' => $option_other
        ]);

        $conn->commit();

        unset($_SESSION['inspection_data']);

        redirect_with_flash_message("บันทึกข้อมูล Quick Win เรียบร้อยแล้ว", "../index.php");
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Save QuickWin Error: " . $e->getMessage());
        redirect_with_flash_message("เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage(), "quickwin_form.php");
    } catch (Exception $e) {
        $conn->rollBack();
        redirect_with_flash_message("เกิดข้อผิดพลาด: " . $e->getMessage(), "quickwin_form.php");
    }
} else {
    header('Location: ../index.php');
    exit();
}
