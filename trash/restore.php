<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

/* =========================
   AUTH
========================= */
if (empty($_SESSION['user_id'])) {
    die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request');
}

$supervisor_id = $_SESSION['user_id'];
$isAdmin       = ($_SESSION['role'] ?? '') === 'admin';

/* =========================
   RECEIVE POST
========================= */
$form_type       = $_POST['form_type'] ?? '';
$t_pid           = $_POST['t_pid'] ?? '';
$subject_code    = $_POST['subject_code'] ?? '';
$inspection_time = $_POST['inspection_time'] ?? '';
$supervision_date = $_POST['supervision_date'] ?? '';

try {

    /* ==================================================
       CLASSROOM FORM
    ================================================== */
    if ($form_type === 'classroom') {

        if (!$t_pid || !$subject_code || !$inspection_time) {
            throw new Exception('ข้อมูลไม่ครบ');
        }

        $sqlCheck = "
            SELECT supervisor_p_id
            FROM supervision_sessions
            WHERE teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND deleted_at IS NOT NULL
        ";

        $params = [$t_pid, $subject_code, $inspection_time];

        if (!$isAdmin) {
            $sqlCheck .= " AND supervisor_p_id = ? ";
            $params[] = $supervisor_id;
        }

        $sqlCheck .= " LIMIT 1";

        $stmt = $conn->prepare($sqlCheck);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('ไม่พบข้อมูลในถังขยะ หรือไม่มีสิทธิ์');
        }

        $restore = $conn->prepare("
            UPDATE supervision_sessions
            SET deleted_at = NULL
            WHERE teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND deleted_at IS NOT NULL
            LIMIT 1
        ");

        $restore->execute([$t_pid, $subject_code, $inspection_time]);
    }

    /* ==================================================
       QUICK WIN FORM
    ================================================== */ elseif ($form_type === 'quickwin') {

        if (!$t_pid || !$supervision_date) {
            throw new Exception('ข้อมูลไม่ครบ');
        }

        $sqlCheck = "
            SELECT p_id
            FROM quick_win
            WHERE t_pid = ?
              AND supervision_date = ?
              AND deleted_at IS NOT NULL
        ";

        $params = [$t_pid, $supervision_date];

        if (!$isAdmin) {
            $sqlCheck .= " AND p_id = ? ";
            $params[] = $supervisor_id;
        }

        $sqlCheck .= " LIMIT 1";

        $stmt = $conn->prepare($sqlCheck);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('ไม่พบข้อมูล Quick Win ในถังขยะ หรือไม่มีสิทธิ์');
        }

        $restore = $conn->prepare("
            UPDATE quick_win
            SET deleted_at = NULL
            WHERE t_pid = ?
              AND supervision_date = ?
              AND deleted_at IS NOT NULL
            LIMIT 1
        ");

        $restore->execute([$t_pid, $supervision_date]);
    } else {
        throw new Exception('ประเภทแบบฟอร์มไม่ถูกต้อง');
    }

    $_SESSION['flash_success'] = '♻️ กู้คืนข้อมูลเรียบร้อยแล้ว';
    header('Location: index.php');
    exit;
} catch (Exception $e) {

    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
