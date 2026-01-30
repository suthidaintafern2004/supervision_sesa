<?php
// ===========================================
// save_kpi_data.php (AJAX VERSION)
// ===========================================
header('Content-Type: application/json'); // กำหนดให้ส่งค่ากลับเป็น JSON
error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิดการแสดง error โดยตรงเพื่อไม่ให้ขัดขวาง JSON

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
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
$academic_year   = (int)($_POST['academic_year'] ?? 0);
$semester        = (int)($_POST['semester'] ?? 0);

$subject_code_db = preg_replace('/\s+/', '', strtolower($subject_code));

try {
    $conn->beginTransaction();

    // --------------------------------------------
    // 2) ตรวจซ้ำ: ครู + วิชา + ครั้ง + ปีการศึกษา + ภาคเรียน
    // --------------------------------------------
    $sqlDup = "SELECT 1 FROM supervision_sessions 
               WHERE teacher_t_pid = ? 
               AND REPLACE(LOWER(subject_code),' ','') = ? 
               AND inspection_time = ? 
               AND academic_year = ? 
               AND semester = ? LIMIT 1";

    $stmtDup = $conn->prepare($sqlDup);
    $stmtDup->execute([$teacher_t_pid, $subject_code_db, $inspection_time, $academic_year, $semester]);

    if ($stmtDup->fetch()) {
        $conn->rollBack();
        echo json_encode([
            'status' => 'duplicate',
            'title'  => 'ไม่สามารถบันทึกได้',
            'text'   => "ครูคนนี้ได้รับการนิเทศ วิชานี้ ครั้งที่ {$inspection_time} ปีการศึกษา {$academic_year} ภาคเรียนที่ {$semester} แล้ว",
            'icon'   => 'warning'
        ]);
        exit;
    }

    // --------------------------------------------
    // 3) บันทึก supervision_sessions
    // --------------------------------------------
    $sqlSession = "INSERT INTO supervision_sessions 
                   (supervisor_p_id, teacher_t_pid, subject_code, subject_name, 
                    inspection_time, inspection_date, academic_year, semester, 
                    overall_suggestion, supervision_date) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

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
    // 4) บันทึกคะแนน KPI และข้อเสนอแนะรายตัวชี้วัด
    // --------------------------------------------
    if (!empty($ratings)) {
        $stmtRating = $conn->prepare("INSERT INTO kpi_answers (question_id, rating_score, supervisor_p_id, teacher_t_pid, subject_code, inspection_time, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($ratings as $qid => $score) {
            if ($score === '') continue;
            $stmtRating->execute([(int)$qid, (int)$score, $supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time, $academic_year]);
        }
    }

    if (!empty($indicator_suggestions)) {
        $stmtSug = $conn->prepare("INSERT INTO kpi_indicator_suggestions (indicator_id, suggestion_text, supervisor_p_id, teacher_t_pid, subject_code, inspection_time, supervision_date, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($indicator_suggestions as $iid => $text) {
            if (trim($text) === '') continue;
            $stmtSug->execute([(int)$iid, trim($text), $supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time, $inspection_date, $academic_year]);
        }
    }

    // --------------------------------------------
    // 5) จัดการรูปภาพ (ย้ายมาไว้ก่อน commit เพื่อความปลอดภัย)
    // --------------------------------------------
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    if (isset($_FILES['images']) && !empty($_FILES['images']['tmp_name'][0])) {
        $stmtImg = $conn->prepare("INSERT INTO images (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, file_name, academic_year, form_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < min(2, count($_FILES['images']['tmp_name'])); $i++) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
            $newName = uniqid('img_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $uploadDir . $newName)) {
                $stmtImg->execute([$supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time, $newName, $academic_year, 'cr']);
            }
        }
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => "✅ บันทึกข้อมูลการนิเทศสำเร็จ"]);
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
