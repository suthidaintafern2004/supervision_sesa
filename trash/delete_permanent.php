<?php

/*************************************************
 * DELETE PERMANENT (CLASSROOM + QUICK WIN)
 * WITH SATISFACTION DELETE
 *************************************************/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

/* =========================
   AUTH
========================= */
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request');
}

$supervisor_id = $_SESSION['user_id'];
$isAdmin       = ($_SESSION['role'] ?? '') === 'admin';

$form_type = $_POST['form_type'] ?? '';

if (!in_array($form_type, ['classroom', 'quickwin'], true)) {
    $_SESSION['flash_error'] = 'ไม่ระบุประเภทแบบฟอร์ม';
    header('Location: index.php');
    exit;
}

$uploadDir = __DIR__ . '/../uploads/';
$files = [];

try {
    $conn->beginTransaction();

    /* =====================================================
       CASE 1 : CLASSROOM
    ===================================================== */
    if ($form_type === 'classroom') {

        $t_pid           = $_POST['t_pid'] ?? '';
        $subject_code    = $_POST['subject_code'] ?? '';
        $inspection_time = $_POST['inspection_time'] ?? '';
        $academic_year   = $_POST['academic_year'] ?? '';

        if (!$t_pid || !$subject_code || !$inspection_time || !$academic_year) {
            throw new Exception('ข้อมูลไม่ครบ (Classroom)');
        }

        /* --- ตรวจสิทธิ์ + ต้องอยู่ในถังขยะ --- */
        $checkSql = "
            SELECT supervisor_p_id
            FROM supervision_sessions
            WHERE teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND academic_year = ?
              AND deleted_at IS NOT NULL
        ";

        $params = [$t_pid, $subject_code, $inspection_time, $academic_year];

        if (!$isAdmin) {
            $checkSql .= " AND supervisor_p_id = ? ";
            $params[] = $supervisor_id;
        }

        $checkSql .= " LIMIT 1";

        $stmt = $conn->prepare($checkSql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('ไม่พบข้อมูลในถังขยะ หรือไม่มีสิทธิ์');
        }

        $ownerPid = $row['supervisor_p_id'];

        /* --- ดึงไฟล์รูป --- */
        $imgStmt = $conn->prepare("
            SELECT file_name
            FROM images
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND academic_year = ?
              AND form_type = 'classroom'
        ");
        $imgStmt->execute([
            $ownerPid,
            $t_pid,
            $subject_code,
            $inspection_time,
            $academic_year
        ]);
        $files = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        /* --- ลบรูป --- */
        $conn->prepare("
            DELETE FROM images
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND academic_year = ?
              AND form_type = 'classroom'
        ")->execute([
            $ownerPid,
            $t_pid,
            $subject_code,
            $inspection_time,
            $academic_year
        ]);

        /* --- ลบ KPI --- */
        $conn->prepare("
            DELETE FROM kpi_answers
            WHERE teacher_t_pid = ?
            AND subject_code = ?
            AND inspection_time = ?
            AND (academic_year = ? OR academic_year = 0) 
        ")->execute([
            $t_pid,
            $subject_code,
            $inspection_time,
            $academic_year
        ]);

        $conn->prepare("
            DELETE FROM kpi_indicator_suggestions
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND academic_year = ?
        ")->execute([
            $ownerPid,
            $t_pid,
            $subject_code,
            $inspection_time,
            $academic_year
        ]);

        /* --- 🔥 ลบคะแนนความพึงพอใจ (Classroom) --- */
        $conn->prepare("
            DELETE FROM satisfaction_answers
            WHERE teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND academic_year = ?
        ")->execute([
            $t_pid,
            $subject_code,
            $inspection_time,
            $academic_year
        ]);

        /* --- ลบฟอร์มหลัก --- */
        $conn->prepare("
            DELETE FROM supervision_sessions
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND academic_year = ?
              AND deleted_at IS NOT NULL
            LIMIT 1
        ")->execute([
            $ownerPid,
            $t_pid,
            $subject_code,
            $inspection_time,
            $academic_year
        ]);
    }

    /* =====================================================
       CASE 2 : QUICK WIN
    ===================================================== */
    if ($form_type === 'quickwin') {

        $t_pid         = $_POST['t_pid'] ?? '';
        $p_id          = $_POST['p_id'] ?? '';
        $academic_year = $_POST['academic_year'] ?? '';

        if (!$t_pid || !$p_id || !$academic_year) {
            throw new Exception('ข้อมูลไม่ครบ (Quick Win)');
        }

        /* --- ตรวจว่าอยู่ในถังขยะ --- */
        $stmt = $conn->prepare("
            SELECT 1
            FROM quick_win
            WHERE t_pid = ?
              AND p_id = ?
              AND academic_year = ?
              AND deleted_at IS NOT NULL
            LIMIT 1
        ");
        $stmt->execute([$t_pid, $p_id, $academic_year]);

        if (!$stmt->fetch()) {
            throw new Exception('ไม่พบข้อมูลในถังขยะ หรือไม่มีสิทธิ์');
        }

        /* --- ดึงรูป --- */
        $imgStmt = $conn->prepare("
            SELECT file_name
            FROM images
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND academic_year = ?
              AND form_type = 'qw'
        ");
        $imgStmt->execute([$p_id, $t_pid, $academic_year]);
        $files = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        /* --- ลบรูป --- */
        $conn->prepare("
            DELETE FROM images
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND academic_year = ?
              AND form_type = 'qw'
        ")->execute([$p_id, $t_pid, $academic_year]);

        /* --- 🔥 ลบคะแนนความพึงพอใจ (Quick Win) --- */
        $conn->prepare("
            DELETE FROM quickwin_satisfaction_answers
            WHERE t_pid = ?
              AND p_id = ?
              AND academic_year = ?
        ")->execute([$t_pid, $p_id, $academic_year]);

        /* --- ลบฟอร์ม Quick Win --- */
        $conn->prepare("
            DELETE FROM quick_win
            WHERE t_pid = ?
              AND p_id = ?
              AND academic_year = ?
              AND deleted_at IS NOT NULL
            LIMIT 1
        ")->execute([$t_pid, $p_id, $academic_year]);
    }

    $conn->commit();

    /* =========================
       ลบไฟล์จริง
    ========================= */
    foreach ($files as $f) {
        $path = $uploadDir . $f;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $_SESSION['flash_success'] = '🗑️ ลบข้อมูลถาวรและคะแนนความพึงพอใจเรียบร้อยแล้ว';
    header('Location: index.php');
    exit;
} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: index.php');
    exit;
}
