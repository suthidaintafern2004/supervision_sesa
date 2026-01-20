<?php

/*************************************************
 * save_satisfaction.php
 * FINAL – Normal + Quickwin (Safe / Production)
 *************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

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
       MODE : NORMAL (ชั้นเรียน)
    ================================================== */
    if ($mode === 'normal') {

        // 👉 ใช้ POST เท่านั้น (ไม่พึ่ง session)
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

        /* === กันบันทึกซ้ำ === */
        $chk = $conn->prepare("
            SELECT 1
            FROM satisfaction_answers
            WHERE supervisor_p_id = ?
              AND teacher_t_pid   = ?
              AND subject_code    = ?
              AND inspection_time = ?
            LIMIT 1
        ");
        $chk->execute([
            $c['s_pid'],
            $c['t_pid'],
            $c['sub_code'],
            $c['time']
        ]);

        if ($chk->fetch()) {
            throw new Exception('ท่านได้ทำการประเมินชั้นเรียนนี้ไปแล้ว');
        }

        /* === บันทึกคะแนน === */
        $stmt = $conn->prepare("
            INSERT INTO satisfaction_answers
            (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, question_id, rating)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        foreach ($ratings as $qid => $score) {
            $stmt->execute([
                $c['s_pid'],
                $c['t_pid'],
                $c['sub_code'],
                $c['time'],
                $qid,
                $score
            ]);
        }

        /* === บันทึกข้อเสนอแนะ === */
        if ($suggestion !== '') {
            $stmt = $conn->prepare("
        UPDATE supervision_sessions
        SET satisfaction_suggestion = ?
        WHERE supervisor_p_id = ?
          AND teacher_t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
          AND deleted_at IS NULL
    ");
            $stmt->execute([
                $suggestion,
                $c['s_pid'],
                $c['t_pid'],
                $c['sub_code'],
                $c['time']
            ]);
        }
    }

    /* ==================================================
       MODE : QUICKWIN
    ================================================== */
    if ($mode === 'quickwin') {

        if (!isset($_SESSION['quickwin_context'])) {
            throw new Exception('quickwin_context ไม่พบใน session');
        }

        $c = $_SESSION['quickwin_context'];

        foreach (['t_pid', 'p_id', 'date'] as $k) {
            if (empty($c[$k])) {
                throw new Exception("quickwin_context ไม่ครบ: {$k}");
            }
        }

        /* === กันบันทึกซ้ำ === */
        $chk = $conn->prepare("
            SELECT 1
            FROM quickwin_satisfaction_answers
            WHERE t_pid = ?
              AND p_id  = ?
              AND supervision_date = ?
            LIMIT 1
        ");
        $chk->execute([
            $c['t_pid'],
            $c['p_id'],
            $c['date']
        ]);

        if ($chk->fetch()) {
            throw new Exception('ท่านได้ทำการประเมิน Quick Win นี้ไปแล้ว');
        }

        /* === บันทึกคะแนน === */
        $stmt = $conn->prepare("
            INSERT INTO quickwin_satisfaction_answers
            (t_pid, p_id, supervision_date, question_id, rating)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($ratings as $qid => $score) {
            $stmt->execute([
                $c['t_pid'],
                $c['p_id'],
                $c['date'],
                $qid,
                $score
            ]);
        }

        /* === บันทึกข้อเสนอแนะ === */
        if ($suggestion !== '') {
            $stmt = $conn->prepare("
        UPDATE quick_win
        SET satisfaction_suggestion = ?
        WHERE t_pid = ?
          AND p_id  = ?
          AND supervision_date = ?
    ");
            $stmt->execute([
                $suggestion,
                $c['t_pid'],
                $c['p_id'],
                $c['date']
            ]);
        }
    }

    /* ===============================
       COMMIT
    =============================== */
    $conn->commit();

    // ===== redirect หลังบันทึกสำเร็จ =====
    $teacher_pid = $c['t_pid'] ?? null;

    header(
        'Location: ../session_details.php?teacher_pid=' . urlencode($teacher_pid) . '&success=1'
    );
    exit;
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Save Satisfaction Error: " . $e->getMessage());

    $_SESSION['flash_message'] = $e->getMessage();
    $_SESSION['flash_type']    = 'warning';

    header('Location: ../session_details.php?teacher_pid=' . urlencode($c['t_pid'] ?? ''));
    exit;
}
