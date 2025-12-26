<?php
require_once '../config/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Invalid Request");

$supervisor_id = $_SESSION['user_id'];
// รับค่า Key เดิม
$old_t_pid = $_POST['old_t_pid'];
$old_sub = $_POST['old_subject_code'];
$old_time = $_POST['old_inspection_time'];

// รับค่าใหม่
$new_sub = $_POST['subject_code'];
$new_sub_name = $_POST['subject_name'];
$new_time = $_POST['inspection_time'];
$new_date = $_POST['supervision_date'];
$ratings = $_POST['ratings'] ?? [];
$indicator_sug = $_POST['indicator_suggestions'] ?? [];
$overall_sug = $_POST['overall_suggestion'] ?? '';

try {
    $conn->beginTransaction();

    // 1. อัปเดตข้อมูล Session หลัก
    $stmt = $conn->prepare("UPDATE supervision_sessions SET 
        subject_code = ?, subject_name = ?, inspection_time = ?, supervision_date = ?, overall_suggestion = ?
        WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ?");
    $stmt->execute([$new_sub, $new_sub_name, $new_time, $new_date, $overall_sug, $supervisor_id, $old_t_pid, $old_sub, $old_time]);

    // 2. ลบคะแนนเดิมและลงใหม่ (เพื่อรองรับการเปลี่ยนรหัสวิชา/ครั้งที่)
    $conn->prepare("DELETE FROM kpi_answers WHERE supervisor_p_id=? AND teacher_t_pid=? AND subject_code=? AND inspection_time=?")
         ->execute([$supervisor_id, $old_t_pid, $new_sub, $new_time]);

    $ins = $conn->prepare("INSERT INTO kpi_answers (question_id, rating_score, supervisor_p_id, teacher_t_pid, subject_code, inspection_time) VALUES (?,?,?,?,?,?)");
    foreach ($ratings as $qid => $score) {
        $ins->execute([$qid, $score, $supervisor_id, $old_t_pid, $new_sub, $new_time]);
    }

    // 3. จัดการข้อเสนอแนะรายตัวชี้วัด
    $conn->prepare("DELETE FROM kpi_indicator_suggestions WHERE supervisor_p_id=? AND teacher_t_pid=? AND subject_code=? AND inspection_time=?")
         ->execute([$supervisor_id, $old_t_pid, $new_sub, $new_time]);

    $ins_sug = $conn->prepare("INSERT INTO kpi_indicator_suggestions (indicator_id, suggestion_text, supervisor_p_id, teacher_t_pid, subject_code, inspection_time) VALUES (?,?,?,?,?,?)");
    foreach ($indicator_sug as $iid => $txt) {
        if(trim($txt) != "") $ins_sug->execute([$iid, $txt, $supervisor_id, $old_t_pid, $new_sub, $new_time]);
    }

    $conn->commit();
    header("Location: supervision_list.php?status=success");
} catch (Exception $e) {
    $conn->rollBack();
    echo "เกิดข้อผิดพลาด: " . $e->getMessage();
}