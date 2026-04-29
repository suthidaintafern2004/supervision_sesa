<?php

/*************************************************
 * save_satisfaction.php
 * FINAL – Normal + Quickwin + Academic Year
 *************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

/* =========================================================
   AUTO-FIX DATABASE SCHEMA: แก้ไขคอลัมน์ให้รองรับการเก็บ "เวลา"
========================================================= */
try {
    // เปลี่ยนชนิดคอลัมน์จาก DATE เป็น DATETIME เพื่อให้เก็บทั้งวันและเวลาได้
    $stmt = $conn->query("SHOW COLUMNS FROM supervision_sessions LIKE 'satisfaction_date'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper($col['Type']) === 'DATE') {
        $conn->exec("ALTER TABLE supervision_sessions MODIFY satisfaction_date DATETIME NULL");
    }

    $stmt = $conn->query("SHOW COLUMNS FROM quick_win LIKE 'satisfaction_date'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($col && strtoupper($col['Type']) === 'DATE') {
        $conn->exec("ALTER TABLE quick_win MODIFY satisfaction_date DATETIME NULL");
    }
} catch (Exception $e) {}

/* ===============================
   function: คำนวณปีการศึกษา
=============================== */
function getAcademicYearFromDate(string $date): int
{
    $y = (int)date('Y', strtotime($date));
    $m = (int)date('n', strtotime($date));
    return ($m >= 5) ? $y + 543 : $y + 542;
}

/* ===============================
   ตรวจ method
=============================== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

/* ===============================
   รับค่าพื้นฐาน
=============================== */
$mode       = $_POST['mode'] ?? null;
$ratings    = $_POST['ratings'] ?? [];
$suggestion = trim($_POST['overall_suggestion'] ?? '');

if (!in_array($mode, ['normal', 'quickwin'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'โหมดการประเมินไม่ถูกต้อง']);
    exit;
}

if (empty($ratings)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาประเมินให้ครบทุกข้อ']);
    exit;
}

$current_datetime = date('Y-m-d H:i:s');

$conn->beginTransaction();

try {

    /* ==================================================
       MODE : NORMAL
    ================================================== */
    if ($mode === 'normal') {

        $c = [
            's_pid'    => $_POST['s_pid'] ?? null,
            't_pid'    => $_POST['t_pid'] ?? null,
            'sub_code' => trim($_POST['sub_code'] ?? ''),
            'time'     => $_POST['time'] ?? null,
            'academic_year' => $_POST['academic_year'] ?? null,
        ];

        foreach ($c as $k => $v) {
            if (empty($v)) {
                throw new Exception("ข้อมูล normal ไม่ครบ: {$k}");
            }
        }

        /* === 1) ดึง supervision_date ก่อน === */
        $stmt = $conn->prepare("
            SELECT supervision_date, academic_year
            FROM supervision_sessions
            WHERE p_id = ?
              AND t_pid   = ?
              AND subject_code    = ?
              AND inspection_time = ?
              AND academic_year = ?
            LIMIT 1
        ");
        $stmt->execute([
            $c['s_pid'],
            $c['t_pid'],
            $c['sub_code'],
            $c['time'],
            $c['academic_year']
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('ไม่พบข้อมูลการนิเทศ');
        }

        /* === 2) ใช้ปีการศึกษาจากฐานข้อมูล === */
        $academic_year = $row['academic_year'] ?: getAcademicYearFromDate($row['supervision_date']);

        /* === 3) INSERT คะแนน === */
        $stmt = $conn->prepare("
            INSERT INTO satisfaction_answers
            (p_id, t_pid, subject_code, inspection_time,
             question_id, rating, academic_year)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($ratings as $qid => $score) {
            $stmt->execute([
                $c['s_pid'],
                $c['t_pid'],
                $c['sub_code'],
                $c['time'],
                $qid,
                $score,
                $academic_year
            ]);
        }

        /* === 4) UPDATE supervision_sessions (ข้อเสนอแนะ + flag) === */
        $stmt = $conn->prepare("
            UPDATE supervision_sessions
            SET satisfaction_suggestion = ?,
                satisfaction_submitted  = 1,
                satisfaction_date       = NOW()
            WHERE p_id = ?
              AND t_pid   = ?
              AND subject_code    = ?
              AND inspection_time = ?
              AND academic_year = ?
        ");
        $stmt->execute([
            $suggestion,
            $c['s_pid'],
            $c['t_pid'],
            $c['sub_code'],
            $c['time'],
            $academic_year
        ]);
    }

    /* ==================================================
       MODE : QUICKWIN
    ================================================== */
    if ($mode === 'quickwin') {

        if (!isset($_SESSION['quickwin_context'])) {
            throw new Exception('quickwin_context ไม่พบ');
        }

        $c = $_SESSION['quickwin_context'];

        /* === 1) ดึงข้อมูล Quick Win จากฐานข้อมูล === */
        $stmt = $conn->prepare("
            SELECT supervision_date, academic_year
            FROM quick_win
            WHERE t_pid = ? AND p_id = ? AND supervision_date = ? AND academic_year = ?
            LIMIT 1
        ");
        $stmt->execute([$c['t_pid'], $c['p_id'], $c['date'], $c['academic_year']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            throw new Exception('ไม่พบข้อมูล Quick Win');
        }

        /* === 2) ใช้ปีการศึกษาจากฐานข้อมูล === */
        $academic_year = $row['academic_year'] ?: getAcademicYearFromDate($row['supervision_date']);

        /* === INSERT คะแนน === */
        $stmt = $conn->prepare("
            INSERT INTO quickwin_satisfaction_answers
            (t_pid, p_id, supervision_date, question_id, rating, academic_year)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($ratings as $qid => $score) {
            $stmt->execute([
                $c['t_pid'],
                $c['p_id'],
                $c['date'],
                $qid,
                $score,
                $academic_year
            ]);
        }

        /* === UPDATE quick_win === */
        $stmt = $conn->prepare("
            UPDATE quick_win
            SET satisfaction_suggestion = ?,
                satisfaction_submitted  = 1,
                satisfaction_date       = NOW()
            WHERE t_pid = ?
              AND p_id  = ?
              AND supervision_date = ?
              AND academic_year = ?
        ");
        $stmt->execute([
            $suggestion,
            $c['t_pid'],
            $c['p_id'],
            $c['date'],
            $academic_year
        ]);
    }

    $conn->commit();

    echo json_encode(['status' => 'success', 'message' => 'บันทึกการประเมินเรียบร้อยแล้ว', 'redirect' => '../session_details.php?teacher_pid=' . urlencode($c['t_pid']) . '&success=1']);
    exit;
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log($e->getMessage());
    
    $errorMsg = 'เกิดข้อผิดพลาด ไม่สามารถบันทึกการประเมินได้';
    if ($e->getCode() == '23000') {
        $errorMsg = 'พบปัญหาข้อมูลซ้ำ หรือขัดแย้งกับข้อมูลเดิมในระบบ';
    }
    
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
    exit;
}
