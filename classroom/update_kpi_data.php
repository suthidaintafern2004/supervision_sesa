<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/session_config.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'เซสชันหมดอายุ ไม่สามารถบันทึกการแก้ไขได้ กรุณาล็อกอินใหม่อีกครั้ง']);
    exit;
}

require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

/* =====================================================
   1) KEY เดิม (อ้างอิงตาม Primary Key ใน SQL: t_pid, subject_code, inspection_time, academic_year)
===================================================== */
$old_t_pid           = $_POST['old_t_pid'] ?? null;
$old_subject_code    = $_POST['old_subject_code'] ?? null;
$old_inspection_time = $_POST['old_inspection_time'] ?? null;
$old_p_id            = $_POST['old_p_id'] ?? null;
$old_academic_year   = $_POST['academic_year_hidden'] ?? null;

/* =====================================================
   2) ค่าใหม่จากฟอร์ม
===================================================== */
$new_p_id            = $_POST['p_id'] ?? $old_p_id;
$new_t_pid           = $_POST['t_pid'] ?? $old_t_pid;
$new_subject_code    = $_POST['subject_code'] ?? null;
$new_subject_name    = $_POST['subject_name'] ?? null;
$new_inspection_time = $_POST['inspection_time'] ?? null;
$overall_suggestion  = $_POST['overall_suggestion'] ?? null;
$new_academic_year   = $_POST['academic_year'] ?? null;
$new_inspection_date = $_POST['inspection_date'] ?? null;
$semester            = $_POST['semester'] ?? 0; // ตาม SQL เป็น tinyint(4)
$form_type           = $_POST['form_type'] ?? 'cr';

/* =====================================================
   3) ตรวจสอบข้อมูลเบื้องต้น
===================================================== */
if (!$old_t_pid || !$old_subject_code || !$old_inspection_time || !$new_p_id || !$new_academic_year) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลสำหรับระบุรายการหรือข้อมูลใหม่ไม่ครบถ้วน']);
    exit;
}

try {
    $conn->beginTransaction();

    /* =====================================================
       4) ลบข้อมูลเดิมในตารางหลักและตารางที่เกี่ยวข้อง (Delete)
       ลบตามลำดับเพื่อความปลอดภัยของ Transaction
    ===================================================== */
    $delParams = [$old_p_id, $old_t_pid, $old_subject_code, $old_inspection_time, $old_academic_year];
    $whereClause = "WHERE p_id = ? AND t_pid = ? AND subject_code = ? AND inspection_time = ? AND academic_year = ?";

    $conn->prepare("DELETE FROM kpi_answers $whereClause")->execute($delParams);
    $conn->prepare("DELETE FROM kpi_indicator_suggestions $whereClause")->execute($delParams);

    // === เก็บข้อมูลเก่าที่ไม่อยากให้หาย ===
    $stmtOld = $conn->prepare("SELECT supervision_date, satisfaction_submitted, satisfaction_date, satisfaction_suggestion, deleted_at FROM supervision_sessions WHERE t_pid = ? AND subject_code = ? AND inspection_time = ? AND academic_year = ?");
    $stmtOld->execute([$old_t_pid, $old_subject_code, $old_inspection_time, $old_academic_year]);
    $oldSession = $stmtOld->fetch(PDO::FETCH_ASSOC);

    // ลบ session หลัก (Primary Key: t_pid, subject_code, inspection_time, academic_year)
    $stmtDelSession = $conn->prepare("DELETE FROM supervision_sessions WHERE t_pid = ? AND subject_code = ? AND inspection_time = ? AND academic_year = ?");
    $stmtDelSession->execute([$old_t_pid, $old_subject_code, $old_inspection_time, $old_academic_year]);

    /* =====================================================
       5) แทรกข้อมูลใหม่ (Insert) ลงใน supervision_sessions
    ===================================================== */
    $stmtIns = $conn->prepare("
        INSERT INTO supervision_sessions (
            p_id, t_pid, subject_code, subject_name, 
            inspection_time, inspection_date, overall_suggestion, 
            academic_year, semester,
            supervision_date, satisfaction_submitted, satisfaction_date, satisfaction_suggestion, deleted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtIns->execute([
        $new_p_id,
        $new_t_pid,
        $new_subject_code,
        $new_subject_name,
        $new_inspection_time,
        $new_inspection_date,
        $overall_suggestion,
        $new_academic_year,
        $semester,
        $oldSession['supervision_date'] ?? date('Y-m-d H:i:s'),
        $oldSession['satisfaction_submitted'] ?? 0,
        $oldSession['satisfaction_date'] ?? null,
        $oldSession['satisfaction_suggestion'] ?? null,
        $oldSession['deleted_at'] ?? null
    ]);

    /* =====================================================
       6) แทรกคะแนน KPI (kpi_answers)
    ===================================================== */
    $stmtAns = $conn->prepare("
        INSERT INTO kpi_answers (question_id, rating_score, p_id, t_pid, subject_code, inspection_time, supervision_date, academic_year)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach (($_POST['ratings'] ?? []) as $qid => $score) {
        $stmtAns->execute([$qid, $score, $new_p_id, $new_t_pid, $new_subject_code, $new_inspection_time, $new_inspection_date, $new_academic_year]);
    }

    /* =====================================================
       7) แทรกข้อเสนอแนะรายตัวบ่งชี้ (kpi_indicator_suggestions)
    ===================================================== */
    $stmtNote = $conn->prepare("
        INSERT INTO kpi_indicator_suggestions (indicator_id, suggestion_text, p_id, t_pid, subject_code, inspection_time, supervision_date, academic_year)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach (($_POST['indicator_suggestions'] ?? []) as $iid => $text) {
        if (trim($text) !== '') {
            $stmtNote->execute([$iid, $text, $new_p_id, $new_t_pid, $new_subject_code, $new_inspection_time, $new_inspection_date, $new_academic_year]);
        }
    }

    /* =====================================================
       8) จัดการรูปภาพ (Images)
    ===================================================== */
    // 8.1 จัดการไฟล์ใหม่
    $uploaded_files = [];
    if (!empty($_FILES['images']['name'][0])) {
        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {
            if ($_FILES['images']['error'][$index] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['images']['name'][$index], PATHINFO_EXTENSION));
                $newFileName = uniqid('kpi_', true) . '.' . $ext;
                if (move_uploaded_file($tmpName, $upload_dir . $newFileName)) {
                    $uploaded_files[] = $newFileName;
                }
            }
        }
    }

    // 8.2 ลบรูปภาพที่ถูกคัดออก และอัปเดต Key รูปเดิม
    $existing_ids = !empty($_POST['existing_images']) ? array_keys($_POST['existing_images']) : [];
    if (!empty($existing_ids)) {
        $placeholders = implode(',', array_fill(0, count($existing_ids), '?'));
        
        $delParamsImg = array_merge([$old_p_id, $old_t_pid, $old_subject_code, $old_inspection_time], $existing_ids);
        $conn->prepare("DELETE FROM images WHERE p_id = ? AND t_pid = ? AND subject_code = ? AND inspection_time = ? AND id NOT IN ($placeholders)")
            ->execute($delParamsImg);

        $updParamsImg = array_merge([$new_p_id, $new_t_pid, $new_subject_code, $new_inspection_time, $new_academic_year], $existing_ids);
        $conn->prepare("UPDATE images SET p_id=?, t_pid=?, subject_code=?, inspection_time=?, academic_year=? WHERE id IN ($placeholders)")
            ->execute($updParamsImg);
    } else {
        $conn->prepare("DELETE FROM images WHERE p_id = ? AND t_pid = ? AND subject_code = ? AND inspection_time = ?")
            ->execute([$old_p_id, $old_t_pid, $old_subject_code, $old_inspection_time]);
    }

    // 8.3 เพิ่มรูปภาพใหม่ลงฐานข้อมูล
    $stmtImg = $conn->prepare("INSERT INTO images (p_id, t_pid, subject_code, inspection_time, file_name, academic_year, form_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($uploaded_files as $fname) {
        $stmtImg->execute([$new_p_id, $new_t_pid, $new_subject_code, $new_inspection_time, $fname, $new_academic_year, $form_type]);
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'บันทึกข้อมูลการแก้ไขสำเร็จ']);
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    
    $errorMsg = 'ไม่สามารถบันทึกการแก้ไขได้ กรุณาลองใหม่อีกครั้ง';
    if ($e->getCode() == '23000' && strpos($e->getMessage(), '1062') !== false) {
        $errorMsg = 'ข้อมูลซ้ำ! มีการบันทึกการนิเทศในครั้งและปีการศึกษานี้อยู่แล้ว';
    }
    
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}
