<?php
// ================================
// save_kpi_data.php
// ================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db_connect.php';

/* =========================
   ฟังก์ชัน redirect + flash
   ========================= */
function redirect_with_flash($type, $message, $location = 'index.php')
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
    header("Location: {$location}");
    exit();
}

/* =========================
   อนุญาตเฉพาะ POST
   ========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

/* =========================
   ตรวจ session หลัก
   ========================= */
if (!isset($_SESSION['inspection_data'])) {
    redirect_with_flash('danger', 'Session หมดอายุ กรุณาเริ่มใหม่');
}

/* =========================
   รับค่าหลัก
   ========================= */
$teacher_t_pid   = $_POST['t_pid'] ?? null;
$subject_code    = $_POST['subject_code'] ?? null;
$subject_name    = $_POST['subject_name'] ?? null;

$ratings               = $_POST['ratings'] ?? [];
$indicator_suggestions = $_POST['indicator_suggestions'] ?? [];
$overall_suggestion    = trim($_POST['overall_suggestion'] ?? '');

$supervisor_p_id = $_SESSION['inspection_data']['s_p_id']
                    ?? $_SESSION['user_id']
                    ?? null;

/* =========================
   Validation
   ========================= */
if (!$teacher_t_pid || !$subject_code || !$supervisor_p_id) {
    redirect_with_flash('danger', 'ข้อมูลหลักไม่ครบ กรุณาลองใหม่');
}

/* =========================
   สร้าง inspection_date
   (วัน + เวลา เพื่อไม่ชน PK)
   ========================= */
$inspection_date = date('Y-m-d'); // สำหรับ PRIMARY KEY
$inspection_datetime = date('Y-m-d H:i:s'); // เก็บเวลาแยกไว้

try {
    $conn->beginTransaction();

    /* =========================
       คำนวณ inspection_time
       (ต่อครู + วิชา)
       ========================= */
    $stmt = $conn->prepare("
        SELECT MAX(inspection_time)
        FROM supervision_sessions
        WHERE teacher_t_pid = ?
          AND subject_code = ?
    ");
    $stmt->execute([$teacher_t_pid, $subject_code]);
    $inspection_time = ((int)$stmt->fetchColumn()) + 1;

    /* =========================
       INSERT supervision_sessions
       ========================= */
    $stmt = $conn->prepare("
        INSERT INTO supervision_sessions
        (
            supervisor_p_id,
            teacher_t_pid,
            subject_code,
            subject_name,
            inspection_time,
            inspection_date,
            overall_suggestion,
            supervision_date
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $supervisor_p_id,
        $teacher_t_pid,
        $subject_code,
        $subject_name,
        $inspection_time,
        $inspection_date,
        $overall_suggestion,
        $inspection_datetime
    ]);

    /* =========================
       KPI Answers
       ========================= */
    if (!empty($ratings)) {
        $stmt = $conn->prepare("
            INSERT INTO kpi_answers
            (
                question_id,
                rating_score,
                supervisor_p_id,
                teacher_t_pid,
                subject_code,
                inspection_time
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($ratings as $qid => $score) {
            $score = (int)$score;
            if ($score === 0) continue;

            $stmt->execute([
                $qid,
                $score,
                $supervisor_p_id,
                $teacher_t_pid,
                $subject_code,
                $inspection_time
            ]);
        }
    }

    /* =========================
       Indicator Suggestions
       ========================= */
    if (!empty($indicator_suggestions)) {
        $stmt = $conn->prepare("
            INSERT INTO kpi_indicator_suggestions
            (
                indicator_id,
                suggestion_text,
                supervisor_p_id,
                teacher_t_pid,
                subject_code,
                inspection_time
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($indicator_suggestions as $iid => $text) {
            $text = trim($text);
            if ($text === '') continue;

            $stmt->execute([
                $iid,
                $text,
                $supervisor_p_id,
                $teacher_t_pid,
                $subject_code,
                $inspection_time
            ]);
        }
    }

    /* =========================
       Commit
       ========================= */
    $conn->commit();

    // เคลียร์ session เพื่อไม่ให้บันทึกซ้ำ
    unset($_SESSION['inspection_data']);

    redirect_with_flash(
        'success',
        'บันทึกข้อมูลการนิเทศเรียบร้อยแล้ว 🎉',
        'index.php'
    );

} catch (PDOException $e) {
    $conn->rollBack();

    if ($e->getCode() == 23000) {
        redirect_with_flash(
            'warning',
            'ข้อมูลซ้ำ (ครู / วิชา / วันที่) กรุณาลองใหม่'
        );
    }

    redirect_with_flash(
        'danger',
        'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
    );
}
