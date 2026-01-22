<?php
/*************************************************
 * save_satisfaction.php
 * FINAL – Normal + Quickwin + Academic Year
 *************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

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
    exit('Invalid request method');
}

/* ===============================
   รับค่าพื้นฐาน
=============================== */
$mode       = $_POST['mode'] ?? null;
$ratings    = $_POST['ratings'] ?? [];
$suggestion = trim($_POST['overall_suggestion'] ?? '');

if (!in_array($mode, ['normal', 'quickwin'], true)) {
    exit('โหมดไม่ถูกต้อง');
}

if (empty($ratings)) {
    exit('กรุณาประเมินให้ครบทุกข้อ');
}

$conn->beginTransaction();

try {

    /* ==================================================
       MODE : NORMAL
    ================================================== */
    if ($mode === 'normal') {

        $c = [
            's_pid'    => $_POST['s_pid'] ?? null,
            't_pid'    => $_POST['t_pid'] ?? null,
            'sub_code' => $_POST['sub_code'] ?? null,
            'time'     => $_POST['time'] ?? null,
        ];

        foreach ($c as $k => $v) {
            if (empty($v)) {
                throw new Exception("ข้อมูล normal ไม่ครบ: {$k}");
            }
        }

        /* === ดึง supervision_date === */
        $stmt = $conn->prepare("
            SELECT supervision_date
            FROM supervision_sessions
            WHERE supervisor_p_id = ?
              AND teacher_t_pid   = ?
              AND subject_code    = ?
              AND inspection_time = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([
            $c['s_pid'],
            $c['t_pid'],
            $c['sub_code'],
            $c['time']
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('ไม่พบข้อมูลการนิเทศ');
        }

        $academic_year = getAcademicYearFromDate($row['supervision_date']);

        /* === INSERT คะแนน (ใส่ academic_year) === */
        $stmt = $conn->prepare("
            INSERT INTO satisfaction_answers
            (supervisor_p_id, teacher_t_pid, subject_code, inspection_time,
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

        /* === update supervision_sessions === */
        $stmt = $conn->prepare("
            UPDATE supervision_sessions
            SET satisfaction_submitted = 1,
                satisfaction_date = NOW(),
                academic_year = ?
            WHERE supervisor_p_id = ?
              AND teacher_t_pid   = ?
              AND subject_code    = ?
              AND inspection_time = ?
        ");
        $stmt->execute([
            $academic_year,
            $c['s_pid'],
            $c['t_pid'],
            $c['sub_code'],
            $c['time']
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

        /* === คำนวณปีการศึกษา === */
        $academic_year = getAcademicYearFromDate($c['date']);

        /* === INSERT คะแนน (ใส่ academic_year) === */
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

        /* === update quick_win === */
        $stmt = $conn->prepare("
            UPDATE quick_win
            SET satisfaction_submitted = 1,
                satisfaction_date = NOW(),
                academic_year = ?
            WHERE t_pid = ?
              AND p_id  = ?
              AND supervision_date = ?
        ");
        $stmt->execute([
            $academic_year,
            $c['t_pid'],
            $c['p_id'],
            $c['date']
        ]);
    }

    $conn->commit();

    header('Location: ../session_details.php?teacher_pid=' . urlencode($c['t_pid']) . '&success=1');
    exit;

} catch (Exception $e) {
    $conn->rollBack();
    error_log($e->getMessage());
    exit($e->getMessage());
}
