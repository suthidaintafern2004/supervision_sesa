<?php

/****************************************
 * QUICK WIN UPDATE – FINAL PRODUCTION (FIXED)
 ****************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

/* =========================
   AUTH
========================= */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

/* =========================
   RECEIVE & SANITIZE POST
========================= */
// ข้อมูลสำหรับค้นหาแถวเดิม (Primary Keys)
$old_t_pid = $_POST['old_t_pid'] ?? null;
$old_p_id  = $_POST['old_p_id'] ?? null;
$old_date  = $_POST['old_supervision_date'] ?? null;

// ข้อมูลใหม่ที่ต้องการบันทึก
$new_t_pid         = $_POST['new_t_pid'] ?? null;
$new_p_id          = $_POST['new_p_id'] ?? null;
$new_academic_year = $_POST['academic_year'] ?? null;
$semester          = $_POST['semester'] ?? null;
$option_other      = trim($_POST['option_other'] ?? '');
$option_ids        = $_POST['options'] ?? [];

// จัดการรายการหัวข้อที่เลือก
if (!is_array($option_ids)) {
    $option_ids = [];
}
$option_ids = array_unique(array_map('intval', $option_ids));
$options_string = implode('/', $option_ids);

// จัดการ ID รูปภาพที่ต้องการลบ
$delete_image_ids = $_POST['delete_image_ids'] ?? [];
if (!is_array($delete_image_ids)) {
    $delete_image_ids = [];
}
$delete_image_ids = array_map('intval', $delete_image_ids);

/* =========================
   VALIDATE
========================= */
if (!$old_t_pid || !$old_p_id || !$old_date || !$new_t_pid || !$new_p_id || !$new_academic_year) {
    die('ข้อมูลไม่ครบถ้วน');
}

if (empty($option_ids) && $option_other === '') {
    $_SESSION['flash_message'] = 'กรุณาเลือกหัวข้อ Quick Win อย่างน้อย 1 ข้อ';
    $_SESSION['flash_type'] = 'warning';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

/* =========================
   PROCESS UPDATE
========================= */
try {
    $conn->beginTransaction();

    /* 1) ตรวจสอบข้อมูลซ้ำ (รัดกุมเป็นพิเศษ) 
       เช็คว่ามีครูคนนี้ในปีการศึกษานี้อยู่แล้วหรือไม่ 
       โดยต้อง "ไม่ใช่" แถวเดิมที่เรากำลังนั่งแก้อยู่ */

    // ใช้ LIKE เพื่อตัดปัญหาความคลาดเคลื่อนของวินาทีในหลักประมวลผล
    $date_pattern = substr($old_date, 0, 16) . '%';

    $checkStmt = $conn->prepare("
        SELECT 1 FROM quick_win 
        WHERE TRIM(t_pid) = TRIM(?) 
          AND academic_year = ? 
          AND NOT (
              TRIM(t_pid) = TRIM(?) 
              AND TRIM(p_id) = TRIM(?) 
              AND supervision_date LIKE ?
          )
        LIMIT 1
    ");

    $checkStmt->execute([
        $new_t_pid,          // ครูที่เลือกใหม่
        $new_academic_year,  // ปีการศึกษาที่เลือกใหม่
        $old_t_pid,          // รหัสครูเดิม
        $old_p_id,           // รหัสผู้นิเทศเดิม
        $date_pattern        // วันที่เดิม (ค้นหาแบบช่วงนาที)
    ]);

    if ($checkStmt->fetch()) {
        throw new Exception('ครูคนนี้ได้รับการนิเทศในปีการศึกษานี้แล้ว (ข้อมูลซ้ำกับรายการอื่น)');
    }

    /* 2) บันทึกการแก้ไขลงตาราง quick_win */
    $stmtUpdate = $conn->prepare("
        UPDATE quick_win
        SET t_pid = ?, 
            p_id = ?, 
            options = ?, 
            option_other = ?, 
            academic_year = ?, 
            semester = ?, 
            updated_at = NOW()
        WHERE t_pid = ? AND p_id = ? AND supervision_date = ?
    ");
    $stmtUpdate->execute([
        $new_t_pid,
        $new_p_id,
        $options_string,
        $option_other,
        $new_academic_year,
        $semester,
        $old_t_pid,
        $old_p_id,
        $old_date
    ]);

    /* 3) จัดการลบรูปภาพ (ลบจริงเมื่อบันทึกผ่านเท่านั้น) */
    if (!empty($delete_image_ids)) {
        foreach ($delete_image_ids as $img_id) {
            $stmtImg = $conn->prepare("SELECT file_name FROM images WHERE id = ? AND form_type = 'qw'");
            $stmtImg->execute([$img_id]);
            $fileName = $stmtImg->fetchColumn();

            if ($fileName) {
                $filePath = __DIR__ . "/../uploads/quickwin/" . $fileName;
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
            $conn->prepare("DELETE FROM images WHERE id = ?")->execute([$img_id]);
        }
    }

    /* 4) อัปโหลดรูปภาพใหม่ (ถ้ามี) */
    if (!empty($_FILES['quickwin_images']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/quickwin/';
        foreach ($_FILES['quickwin_images']['name'] as $i => $name) {
            if ($_FILES['quickwin_images']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $fileName = 'qw_' . $new_t_pid . '_' . time() . '_' . rand(100, 999) . '.' . $ext;

            if (move_uploaded_file($_FILES['quickwin_images']['tmp_name'][$i], $uploadDir . $fileName)) {
                $stmtIn = $conn->prepare("INSERT INTO images (supervisor_p_id, teacher_t_pid, file_name, form_type, academic_year) VALUES (?, ?, ?, 'qw', ?)");
                $stmtIn->execute([$new_p_id, $new_t_pid, $fileName, $new_academic_year]);
            }
        }
    }

    $conn->commit();

    $_SESSION['flash_message'] = 'บันทึกการแก้ไข Quick Win ปีการศึกษา ' . $new_academic_year . ' สำเร็จ';
    $_SESSION['flash_type'] = 'success';
    header('Location: ../my_sessions_list.php');
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type'] = 'warning';
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}
