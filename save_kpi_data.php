<?php
// ===========================================
// save_kpi_data.php (FINAL VERSION)
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

// --------------------------------------------
// Helper Functions
// --------------------------------------------
function redirect_with_message($msg, $url)
{
    $_SESSION['flash_message'] = $msg;
    header("Location: {$url}");
    exit();
}

// --------------------------------------------
// ตรวจสอบ Method
// --------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('Invalid request method', 'index.php');
}

// --------------------------------------------
// 1) รับค่าพื้นฐานจากฟอร์ม
// --------------------------------------------
$supervisor_p_id = $_SESSION['user_id'];

$teacher_t_pid   = $_POST['t_pid'] ?? '';
$subject_code    = trim($_POST['subject_code'] ?? '');
$subject_name    = trim($_POST['subject_name'] ?? '');
$inspection_date = $_POST['supervision_date'] ?? date('Y-m-d');
$overall_suggestion    = trim($_POST['overall_suggestion'] ?? '');
$ratings               = $_POST['ratings'] ?? [];
$indicator_suggestions = $_POST['indicator_suggestions'] ?? [];
$inspection_time = (int)($_POST['inspection_time'] ?? 0);

if ($inspection_time < 1 || $inspection_time > 9) {
    redirect_with_message('กรุณาเลือกครั้งที่นิเทศ', 'summary.php');
}

// normalize subject_code (ใช้ตรวจซ้ำ)
$subject_code_db = preg_replace('/\s+/', '', strtolower($subject_code));

$academic_year = (int)($_POST['academic_year'] ?? 0);

if ($academic_year <= 0) {
    redirect_with_message('กรุณาเลือกปีการศึกษา', 'summary.php');
}

$semester = (int)($_POST['semester'] ?? 0);

if (!in_array($semester, [1, 2, 3])) {
    redirect_with_message('กรุณาเลือกภาคเรียน', 'summary.php');
}

// --------------------------------------------
// Validation
// --------------------------------------------
if (
    empty($teacher_t_pid) ||
    empty($subject_code) ||
    empty($subject_name)
) {
    redirect_with_message(
        'ข้อมูลไม่ครบถ้วน (ครู / รหัสวิชา / ชื่อวิชา)',
        'summary.php'
    );
}

try {
    $conn->beginTransaction();
    // --------------------------------------------
    // 2) 🔒 ตรวจซ้ำ: ครู + วิชา + ครั้ง + ปีการศึกษา
    // --------------------------------------------
    $sqlDup = "
            SELECT 1
            FROM supervision_sessions
            WHERE teacher_t_pid = ?
            AND REPLACE(LOWER(subject_code),' ','') = ?
            AND inspection_time = ?
            AND academic_year = ?
            AND semester = ?
            LIMIT 1
        ";

    $stmtDup = $conn->prepare($sqlDup);
    $stmtDup->execute([
        $teacher_t_pid,
        $subject_code_db,
        $inspection_time,
        $academic_year,
        $semester
    ]);

    if ($stmtDup->fetch()) {
        $conn->rollBack();

        $_SESSION['flash_error'] = [
            'title' => 'ไม่สามารถบันทึกได้',
            'text'  => "ครูคนนี้ได้รับการนิเทศ\nวิชานี้ ครั้งที่ {$inspection_time}\nปีการศึกษา {$academic_year} แล้ว",
            'icon'  => 'warning'
        ];

        header("Location: summary.php");
        exit;
    }

    // --------------------------------------------
    // 4) บันทึก supervision_sessions
    // --------------------------------------------
    $sqlSession = "
            INSERT INTO supervision_sessions
            (
            supervisor_p_id,
            teacher_t_pid,
            subject_code,
            subject_name,
            inspection_time,
            inspection_date,
            academic_year,
            semester,
            overall_suggestion,
            supervision_date
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";

    $stmtSession = $conn->prepare($sqlSession);
    $stmtSession->execute([
        $supervisor_p_id,
        $teacher_t_pid,
        $subject_code,
        $subject_name,
        $inspection_time,
        $inspection_date,
        $academic_year,
        $semester,
        $overall_suggestion
    ]);


    // --------------------------------------------
    // 5) บันทึกคะแนน KPI
    // --------------------------------------------
    if (!empty($ratings)) {
        $stmtRating = $conn->prepare("
            INSERT INTO kpi_answers
                (question_id, rating_score, supervisor_p_id,
                 teacher_t_pid, subject_code, inspection_time, academic_year)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($ratings as $qid => $score) {
            if ($score === '') continue;

            $stmtRating->execute([
                (int)$qid,
                (int)$score,
                $supervisor_p_id,
                $teacher_t_pid,
                $subject_code,
                $inspection_time,
                $academic_year
            ]);
        }
    }

    // --------------------------------------------
    // 6) บันทึกข้อเสนอแนะรายตัวชี้วัด
    // --------------------------------------------
    if (!empty($indicator_suggestions)) {
        $stmtSug = $conn->prepare("
                INSERT INTO kpi_indicator_suggestions
                    (
                        indicator_id,
                        suggestion_text,
                        supervisor_p_id,
                        teacher_t_pid,
                        subject_code,
                        inspection_time,
                        supervision_date,
                        academic_year
                    )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

        foreach ($indicator_suggestions as $iid => $text) {
            if (trim($text) === '') continue;

            $stmtSug->execute([
                (int)$iid,
                trim($text),
                $supervisor_p_id,
                $teacher_t_pid,
                $subject_code,
                $inspection_time,
                $inspection_date,   // 👈 วันที่นิเทศ (ตัวเดียวกับ supervision_sessions)
                $academic_year      // 👈 ปีการศึกษาที่เลือกจากฟอร์ม
            ]);
        }
    }

    // --------------------------------------------
    // 7) อัปโหลดรูปภาพ
    // --------------------------------------------

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (
        !empty($_FILES['images']['tmp_name']) &&
        is_array($_FILES['images']['tmp_name']) &&
        !empty($_FILES['images']['tmp_name'][0])
    ) {
        $form_type = 'cr'; // class room
        $stmtImg = $conn->prepare("
                INSERT INTO images
                    (supervisor_p_id, teacher_t_pid,
                    subject_code, inspection_time,
                    file_name, academic_year, form_type)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

        $maxFiles = min(2, count($_FILES['images']['tmp_name']));

        for ($i = 0; $i < $maxFiles; $i++) {

            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $tmpPath = $_FILES['images']['tmp_name'][$i];
            if (!is_uploaded_file($tmpPath)) continue;

            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;

            $newName = uniqid('img_', true) . '.' . $ext;
            $target  = $uploadDir . $newName;

            if (!move_uploaded_file($tmpPath, $target)) {
                error_log("UPLOAD FAILED: {$target}");
                continue;
            }

            $stmtImg->execute([
                $supervisor_p_id,
                $teacher_t_pid,
                $subject_code,
                $inspection_time,
                $newName,
                $academic_year,
                $form_type
            ]);
        }
    }

    // --------------------------------------------
    // 8) Commit
    // --------------------------------------------
    $conn->commit();

    redirect_with_message(
        "✅ บันทึกข้อมูลการนิเทศสำเร็จ (ปีการศึกษา {$academic_year})",
        "index.php"
    );
} catch (Exception $e) {

    $conn->rollBack();
    error_log("SAVE_KPI_ERROR: " . $e->getMessage());
    exit("เกิดข้อผิดพลาดทางระบบ กรุณาตรวจสอบ error log");
}
