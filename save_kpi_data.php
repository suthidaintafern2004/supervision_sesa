<?php
// ===========================================
// save_kpi_data.php (FIX DUPLICATE INSPECTION)
// ===========================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

require_once __DIR__ . '/config/db_connect.php';

// -------------------------------
function redirect_with_message($msg, $url)
{
    $_SESSION['flash_message'] = $msg;
    header("Location: $url");
    exit();
}

// -------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('Invalid request', 'index.php');
}

// -------------------------------
// รับค่าจากฟอร์ม
// -------------------------------
$supervisor_p_id = $_SESSION['user_id'];
$teacher_t_pid   = $_POST['t_pid'] ?? null;

$subject_code    = trim($_POST['subject_code'] ?? '');
$subject_name    = trim($_POST['subject_name'] ?? '');
$inspection_time = intval($_POST['inspection_time'] ?? 0);
$inspection_date = $_POST['supervision_date'] ?? date('Y-m-d');
$overall_suggestion = trim($_POST['overall_suggestion'] ?? '');

$ratings               = $_POST['ratings'] ?? [];
$indicator_suggestions = $_POST['indicator_suggestions'] ?? [];

// -------------------------------
// Validate
// -------------------------------
if (!$teacher_t_pid || !$subject_code || !$inspection_time) {
    redirect_with_message("ข้อมูลไม่ครบถ้วน", "supervision_start.php");
}

$conn->beginTransaction();

try {

    // =====================================================
    // A) ตรวจซ้ำ (ซ้ำได้ ยกเว้น ชุดเดิม)
    // =====================================================
    $checkSql = "
        SELECT 1 FROM supervision_sessions
        WHERE supervisor_p_id = ?
          AND teacher_t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
    ";
    $stmtCheck = $conn->prepare($checkSql);
    $stmtCheck->execute([
        $supervisor_p_id,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    if ($stmtCheck->fetch()) {
        $conn->rollBack();
        redirect_with_message(
            "มีข้อมูลการนิเทศครั้งที่ {$inspection_time} ของวิชานี้แล้ว",
            "supervision_start.php"
        );
    }

    // =====================================================
    // B) supervision_sessions (INSERT ใหม่เสมอ)
    // =====================================================
    $sqlSession = "
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
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmtSession = $conn->prepare($sqlSession);
    $stmtSession->execute([
        $supervisor_p_id,
        $teacher_t_pid,
        $subject_code,
        $subject_name,
        $inspection_time,
        $inspection_date,
        $overall_suggestion
    ]);

    // =====================================================
    // C) kpi_answers
    // =====================================================
    if (!empty($ratings)) {
        $sqlRating = "
            INSERT INTO kpi_answers
            (question_id, rating_score, supervisor_p_id, teacher_t_pid, subject_code, inspection_time)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $stmtRating = $conn->prepare($sqlRating);

        foreach ($ratings as $qid => $score) {
            $stmtRating->execute([
                intval($qid),
                intval($score),
                $supervisor_p_id,
                $teacher_t_pid,
                $subject_code,
                $inspection_time
            ]);
        }
    }

    // =====================================================
    // D) kpi_indicator_suggestions
    // =====================================================
    if (!empty($indicator_suggestions)) {
        $sqlSug = "
            INSERT INTO kpi_indicator_suggestions
            (indicator_id, suggestion_text, supervisor_p_id, teacher_t_pid, subject_code, inspection_time)
            VALUES (?, ?, ?, ?, ?, ?)
        ";
        $stmtSug = $conn->prepare($sqlSug);

        foreach ($indicator_suggestions as $iid => $text) {
            $text = trim($text);
            if ($text !== '') {
                $stmtSug->execute([
                    intval($iid),
                    $text,
                    $supervisor_p_id,
                    $teacher_t_pid,
                    $subject_code,
                    $inspection_time
                ]);
            }
        }
    }

    // =====================================================
    // E) Upload Images
    // =====================================================
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!empty($_FILES['images']['name'][0])) {
        $stmtImg = $conn->prepare("
            INSERT INTO images
            (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, file_name)
            VALUES (?, ?, ?, ?, ?)
        ");

        for ($i = 0; $i < min(2, count($_FILES['images']['name'])); $i++) {
            if ($_FILES['images']['error'][$i] === 0) {
                $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                $newName = uniqid('img_', true) . '.' . $ext;
                move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $newName);

                $stmtImg->execute([
                    $supervisor_p_id,
                    $teacher_t_pid,
                    $subject_code,
                    $inspection_time,
                    $newName
                ]);
            }
        }
    }

    $conn->commit();
    redirect_with_message("บันทึกข้อมูลเรียบร้อยแล้ว", "index.php");
} catch (Exception $e) {

    $conn->rollBack();
    error_log("SAVE_KPI_ERROR: " . $e->getMessage());
    echo "<pre style='color:red'>{$e->getMessage()}</pre>";
    exit();
}
