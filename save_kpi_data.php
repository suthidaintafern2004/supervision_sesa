<?php
// ===========================================
// save_kpi_data.php (AJAX VERSION)
// ===========================================
header('Content-Type: application/json');
require_once __DIR__ . '/config/session_config.php'; // กำหนดให้ส่งค่ากลับเป็น JSON

date_default_timezone_set('Asia/Bangkok');

error_reporting(E_ALL);
ini_set('display_errors', 0); // ปิดการแสดง error โดยตรงเพื่อไม่ให้ขัดขวาง JSON


if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/config/db_connect.php';

/* =========================================================
   AUTO-FIX DATABASE SCHEMA (ปรับปรุง Primary/Unique Key ให้รองรับ academic_year)
========================================================= */
try {
    // 1. ตรวจสอบและแก้ไข PRIMARY KEY ของตาราง supervision_sessions
    $stmt = $conn->query("SHOW KEYS FROM supervision_sessions WHERE Key_name = 'PRIMARY'");
    $pk_cols = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $pk_cols[] = $row['Column_name']; }
    if (!empty($pk_cols) && !in_array('academic_year', $pk_cols)) {
        $conn->exec("ALTER TABLE supervision_sessions DROP PRIMARY KEY, ADD PRIMARY KEY(t_pid, subject_code, inspection_time, academic_year)");
    }

    // 2. ลบ UNIQUE KEY เก่าที่ไม่มี academic_year ใน supervision_sessions
    $stmt = $conn->query("SHOW INDEX FROM supervision_sessions WHERE Non_unique = 0 AND Key_name != 'PRIMARY'");
    $indexes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $indexes[$row['Key_name']][] = $row['Column_name']; }
    foreach ($indexes as $key_name => $columns) {
        if (!in_array('academic_year', $columns)) {
            $conn->exec("ALTER TABLE supervision_sessions DROP INDEX `$key_name`");
        }
    }

    // 3. แก้ไข PRIMARY KEY ของ kpi_answers
    $stmt = $conn->query("SHOW KEYS FROM kpi_answers WHERE Key_name = 'PRIMARY'");
    $pk_cols = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $pk_cols[] = $row['Column_name']; }
    if (!empty($pk_cols) && !in_array('academic_year', $pk_cols)) {
        $conn->exec("ALTER TABLE kpi_answers DROP PRIMARY KEY, ADD PRIMARY KEY(question_id, t_pid, subject_code, inspection_time, academic_year)");
    }

    // 4. แก้ไข PRIMARY KEY ของ kpi_indicator_suggestions
    $stmt = $conn->query("SHOW KEYS FROM kpi_indicator_suggestions WHERE Key_name = 'PRIMARY'");
    $pk_cols = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $pk_cols[] = $row['Column_name']; }
    if (!empty($pk_cols) && !in_array('academic_year', $pk_cols)) {
        $conn->exec("ALTER TABLE kpi_indicator_suggestions DROP PRIMARY KEY, ADD PRIMARY KEY(indicator_id, t_pid, subject_code, inspection_time, academic_year)");
    }
} catch (Exception $e) {}

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
$academic_year   = (int)($_POST['academic_year'] ?? $_SESSION['inspection_data']['academic_year'] ?? 0);
$semester        = (int)($_POST['semester'] ?? 0);

// กันกรณีปีการศึกษาไม่มีค่า ให้ใช้ปีปัจจุบันคำนวณสำรอง
if ($academic_year < 2500) {
    $academic_year = (int)date('Y') + 543;
    if ((int)date('n') < 5) $academic_year--;
}

$subject_code_db = preg_replace('/\s+/', '', strtolower($subject_code));

$current_datetime = date('Y-m-d H:i:s');

try {
    $conn->beginTransaction();

    // --------------------------------------------
    // 2) ตรวจซ้ำ: ครู + วิชา + ครั้ง + ปีการศึกษา + ภาคเรียน
    // --------------------------------------------
    $sqlDup = "SELECT 1 FROM supervision_sessions 
               WHERE t_pid = ? 
               AND REPLACE(LOWER(subject_code),' ','') = ? 
               AND inspection_time = ? 
               AND academic_year = ? 
               AND deleted_at IS NULL LIMIT 1";

    $stmtDup = $conn->prepare($sqlDup);
    $stmtDup->execute([$teacher_t_pid, $subject_code_db, $inspection_time, $academic_year]);

    if ($stmtDup->fetch()) {
        $conn->rollBack();
        echo json_encode([
            'status' => 'duplicate',
            'title'  => 'ไม่สามารถบันทึกได้',
            'text'   => "ครูคนนี้ได้รับการนิเทศ วิชานี้ ครั้งที่ {$inspection_time} ปีการศึกษา {$academic_year} แล้ว",
            'icon'   => 'warning'
        ]);
        exit;
    }

    // --------------------------------------------
    // 3) บันทึก supervision_sessions
    // --------------------------------------------
    $sqlSession = "INSERT INTO supervision_sessions 
                   (p_id, t_pid, subject_code, subject_name, 
                    inspection_time, inspection_date, academic_year, semester, 
                    overall_suggestion, supervision_date) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
        $overall_suggestion,
        $current_datetime
    ]);

    // --------------------------------------------
    // 4) บันทึกคะแนน KPI และข้อเสนอแนะรายตัวชี้วัด
    // --------------------------------------------
    if (!empty($ratings)) {
        $stmtRating = $conn->prepare("INSERT INTO kpi_answers (question_id, rating_score, p_id, t_pid, subject_code, inspection_time, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($ratings as $qid => $score) {
            if ($score === '') continue;
            $stmtRating->execute([(int)$qid, (int)$score, $supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time, $academic_year]);
        }
    }

    if (!empty($indicator_suggestions)) {
        $stmtSug = $conn->prepare("INSERT INTO kpi_indicator_suggestions (indicator_id, suggestion_text, p_id, t_pid, subject_code, inspection_time, supervision_date, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
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
        $stmtImg = $conn->prepare("INSERT INTO images (p_id, t_pid, subject_code, inspection_time, file_name, academic_year, form_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
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

    // ดักจับ Error 1062 (Duplicate entry) เพื่อแสดงข้อความแจ้งเตือนที่เข้าใจง่าย
    if ($e->getCode() == '23000' && strpos($e->getMessage(), '1062') !== false) {
        echo json_encode([
            'status' => 'duplicate',
            'title'  => 'ข้อมูลซ้ำ',
            'text'   => "ครูคนนี้ได้รับการนิเทศวิชานี้ครั้งที่ {$inspection_time} ปีการศึกษา {$academic_year} ไปแล้วกรุณากลับไปแก้ไขข้อมูลเบื้องต้น",
            'icon'   => 'warning'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
}
