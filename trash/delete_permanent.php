<?php

/*************************************************
 * DELETE PERMANENT (CLASSROOM + QUICK WIN)
 * PRODUCTION FINAL
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

/* =========================
   RECEIVE COMMON
========================= */
$form_type = $_POST['form_type'] ?? '';

if (!in_array($form_type, ['classroom', 'quickwin'], true)) {
    $_SESSION['flash_error'] = 'ไม่ระบุประเภทแบบฟอร์ม';
    header('Location: index.php');
    exit;
}

$uploadDir = __DIR__ . '/../uploads/';
$error = null;

try {
    $conn->beginTransaction();

    /* =====================================================
       CASE 1 : CLASSROOM
    ===================================================== */
    if ($form_type === 'classroom') {

        $t_pid           = $_POST['t_pid'] ?? '';
        $subject_code    = $_POST['subject_code'] ?? '';
        $inspection_time = $_POST['inspection_time'] ?? '';

        if (!$t_pid || !$subject_code || !$inspection_time) {
            throw new Exception('ข้อมูลไม่ครบ (Classroom)');
        }

        /* --- ตรวจสิทธิ์ + อยู่ในถังขยะ --- */
        $checkSql = "
            SELECT supervisor_p_id
            FROM supervision_sessions
            WHERE teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND deleted_at IS NOT NULL
        ";

        $params = [$t_pid, $subject_code, $inspection_time];

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
              AND form_type = 'classroom'
        ");
        $imgStmt->execute([$ownerPid, $t_pid, $subject_code, $inspection_time]);
        $files = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        /* --- ลบข้อมูลที่เกี่ยวข้อง --- */
        $conn->prepare("
            DELETE FROM images
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND form_type = 'classroom'
        ")->execute([$ownerPid, $t_pid, $subject_code, $inspection_time]);

        $conn->prepare("
            DELETE FROM kpi_answers
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
        ")->execute([$ownerPid, $t_pid, $subject_code, $inspection_time]);

        $conn->prepare("
            DELETE FROM kpi_indicator_suggestions
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
        ")->execute([$ownerPid, $t_pid, $subject_code, $inspection_time]);

        $delStmt = $conn->prepare("
            DELETE FROM supervision_sessions
            WHERE supervisor_p_id = ?
              AND teacher_t_pid = ?
              AND subject_code = ?
              AND inspection_time = ?
              AND deleted_at IS NOT NULL
            LIMIT 1
        ");
        $delStmt->execute([$ownerPid, $t_pid, $subject_code, $inspection_time]);
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

        // ตรวจสิทธิ์ + อยู่ในถังขยะ
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

        // ดึงรูป
        $imgStmt = $conn->prepare("
        SELECT file_name
        FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code IS NULL
          AND inspection_time IS NULL
          AND academic_year = ?
          AND form_type = 'qw'
    ");
        $imgStmt->execute([$p_id, $t_pid, $academic_year]);
        $files = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

        // ลบรูป
        $conn->prepare("
        DELETE FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code IS NULL
          AND inspection_time IS NULL
          AND academic_year = ?
          AND form_type = 'qw'
    ")->execute([$p_id, $t_pid, $academic_year]);

        // ลบ quick win
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
    if (!empty($files)) {
        foreach ($files as $f) {
            $path = $uploadDir . $f;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    $_SESSION['flash_success'] = '🗑️ ลบข้อมูลถาวรเรียบร้อยแล้ว';
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
