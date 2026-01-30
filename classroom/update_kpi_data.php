<?php
// 1. เรียกใช้การตั้งค่า Session 5 ชั่วโมง (ถอยออกไป 1 ชั้น)
require_once '../config/session_config.php';

// 2. ตรวจเช็คสิทธิ์การเข้าใช้งาน
if (empty($_SESSION['user_id'])) {
    die('Session หมดอายุ ไม่สามารถบันทึกการแก้ไขได้ กรุณาล็อกอินใหม่อีกครั้ง');
}

require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

/* =====================================================
   1) KEY เดิม (ใช้หา session เก่า)
===================================================== */
$old_t_pid           = $_POST['old_t_pid'] ?? null;
$old_subject_code    = $_POST['old_subject_code'] ?? null;
$old_inspection_time = $_POST['old_inspection_time'] ?? null;
$old_supervisor_id   = $_POST['old_supervisor_p_id'] ?? null;

/* =====================================================
   2) ค่าใหม่จากฟอร์ม
===================================================== */
$new_supervisor_id   = $_POST['supervisor_p_id'] ?? $old_supervisor_id;
$new_t_pid           = $_POST['teacher_t_pid'] ?? $old_t_pid;
$new_subject_code    = $_POST['subject_code'] ?? null;
$new_subject_name    = $_POST['subject_name'] ?? null;
$new_inspection_time = $_POST['inspection_time'] ?? null;
$overall_suggestion  = $_POST['overall_suggestion'] ?? null;
$new_academic_year = $_POST['academic_year'] ?? null;
$new_inspection_date = $_POST['inspection_date'] ?? null;
$semester = $_POST['semester'] ?? null;

/* =====================================================
   3) ข้อมูลย่อย
===================================================== */
$ratings               = $_POST['ratings'] ?? [];
$indicator_suggestions = $_POST['indicator_suggestions'] ?? [];
$existing_images       = $_POST['existing_images'] ?? [];

$redirect_back = $_POST['redirect_back'] ?? '../index.php';

$form_type = $_POST['form_type'] ?? 'kpi';

$academic_year_for_image =
    $_POST['academic_year']
    ?? $_POST['academic_year_hidden']
    ?? null;

if (!$academic_year_for_image) {
    throw new Exception('ไม่พบปีการศึกษาสำหรับรูปภาพ');
}

/* =====================================================
   4) ตรวจสอบข้อมูล
===================================================== */
if (
    !$old_t_pid || !$old_subject_code || !$old_inspection_time || !$old_supervisor_id ||
    !$new_supervisor_id || !$new_t_pid || !$new_subject_code || !$new_inspection_time ||
    !$new_academic_year || !$new_inspection_date || !$semester
) {
    die('ข้อมูลไม่ครบ');
}

try {
    $conn->beginTransaction();

    // ===============================
    // CHECK DATA CHANGE
    // ===============================
    $check = $conn->prepare("
    SELECT supervisor_p_id, teacher_t_pid, subject_code, subject_name,
       inspection_time, inspection_date, academic_year, semester, overall_suggestion
    FROM supervision_sessions
    WHERE supervisor_p_id = ?
      AND teacher_t_pid   = ?
      AND subject_code    = ?
      AND inspection_time = ?
      AND deleted_at IS NULL
");
    $check->execute([
        $old_supervisor_id,
        $old_t_pid,
        $old_subject_code,
        $old_inspection_time
    ]);
    $oldData = $check->fetch(PDO::FETCH_ASSOC);

    if (!$oldData) {
        throw new Exception('ไม่พบข้อมูลเดิม');
    }

    $noChange =
        $oldData['supervisor_p_id'] == $new_supervisor_id &&
        $oldData['teacher_t_pid']   == $new_t_pid &&
        $oldData['subject_code']    == $new_subject_code &&
        $oldData['subject_name']    == $new_subject_name &&
        $oldData['inspection_time'] == $new_inspection_time &&
        $oldData['academic_year']   == $new_academic_year &&
        $oldData['semester']        == $semester &&
        $oldData['inspection_date'] == $new_inspection_date &&
        trim($oldData['overall_suggestion']) == trim($overall_suggestion);

    if ($noChange && empty($ratings) && empty($indicator_suggestions) && empty($_FILES['images']['name'][0])) {
        throw new Exception('ไม่มีการเปลี่ยนแปลงข้อมูล');
    }


    /* =====================================================
       5) UPDATE supervision_sessions
    ===================================================== */
    $stmt = $conn->prepare("
        UPDATE supervision_sessions
        SET supervisor_p_id    = ?,
            teacher_t_pid      = ?,
            subject_code       = ?,
            subject_name       = ?,
            inspection_time    = ?,
            inspection_date   = ?, 
            academic_year      = ?,
            semester           = ?, 
            overall_suggestion = ?
        WHERE supervisor_p_id = ?
          AND teacher_t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
          AND deleted_at IS NULL
    ");

    $stmt->execute([
        $new_supervisor_id,
        $new_t_pid,
        $new_subject_code,
        $new_subject_name,
        $new_inspection_time,
        $new_inspection_date,
        $new_academic_year,
        $semester,
        $overall_suggestion,
        $old_supervisor_id,
        $old_t_pid,
        $old_subject_code,
        $old_inspection_time
    ]);

    /* =====================================================
       6) KPI ANSWERS
    ===================================================== */
    $conn->prepare("
        DELETE FROM kpi_answers
        WHERE supervisor_p_id = ?
          AND teacher_t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
    ")->execute([
        $old_supervisor_id,
        $old_t_pid,
        $old_subject_code,
        $old_inspection_time
    ]);

    $stmtAns = $conn->prepare("
        INSERT INTO kpi_answers
        (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, question_id, rating_score)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($ratings as $qid => $score) {
        $stmtAns->execute([
            $new_supervisor_id,
            $new_t_pid,
            $new_subject_code,
            $new_inspection_time,
            $qid,
            $score
        ]);
    }

    /* =====================================================
       7) KPI INDICATOR SUGGESTIONS
    ===================================================== */
    $conn->prepare("
        DELETE FROM kpi_indicator_suggestions
        WHERE supervisor_p_id = ?
          AND teacher_t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
    ")->execute([
        $old_supervisor_id,
        $old_t_pid,
        $old_subject_code,
        $old_inspection_time
    ]);

    $stmtNote = $conn->prepare("
        INSERT INTO kpi_indicator_suggestions
        (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, indicator_id, suggestion_text)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($indicator_suggestions as $iid => $text) {
        if (trim($text) !== '') {
            $stmtNote->execute([
                $new_supervisor_id,
                $new_t_pid,
                $new_subject_code,
                $new_inspection_time,
                $iid,
                $text
            ]);
        }
    }

    /* =====================================================
       8) HANDLE IMAGE UPLOAD (NEW IMAGES)
    ===================================================== */
    $uploaded_files = [];

    if (!empty($_FILES['images']['name'][0])) {

        $upload_dir = __DIR__ . '/../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $index => $tmpName) {

            if ($_FILES['images']['error'][$index] === UPLOAD_ERR_OK) {

                $ext = strtolower(pathinfo($_FILES['images']['name'][$index], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed)) {
                    continue;
                }

                $newFileName = uniqid('kpi_', true) . '.' . $ext;

                if (move_uploaded_file($tmpName, $upload_dir . $newFileName)) {
                    $uploaded_files[] = $newFileName;
                }
            }
        }
    }

    /* =====================================================
       9) IMAGES (DELETE + INSERT)
    ===================================================== */
    $existing_ids = array_keys($existing_images);

    if (!empty($existing_ids)) {
        $placeholders = implode(',', array_fill(0, count($existing_ids), '?'));

        $params = array_merge(
            [$old_supervisor_id, $old_t_pid, $old_subject_code, $old_inspection_time],
            $existing_ids
        );

        $conn->prepare("
        DELETE FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
          AND id NOT IN ($placeholders)
    ")->execute($params);
    } else {
        // ถ้า user ลบรูปเก่าหมด
        $conn->prepare("
        DELETE FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
    ")->execute([
            $old_supervisor_id,
            $old_t_pid,
            $old_subject_code,
            $old_inspection_time
        ]);
    }

    $stmtImg = $conn->prepare("
    INSERT INTO images
    (supervisor_p_id, teacher_t_pid, subject_code, inspection_time,
     file_name, academic_year, form_type)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

    foreach ($uploaded_files as $file_name) {
        $stmtImg->execute([
            $new_supervisor_id,
            $new_t_pid,
            $new_subject_code,
            $new_inspection_time,
            $file_name,
            $academic_year_for_image,
            $form_type
        ]);
    }

    /* =====================================================
       10) COMMIT
    ===================================================== */
    $conn->commit();

    header("Location: ../my_sessions_list.php?success=update");
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo '❌ Error: ' . $e->getMessage();
}
