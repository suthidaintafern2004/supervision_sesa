<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

/* =========================
   ตรวจสอบสิทธิ์
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
   รับค่าจากฟอร์ม
========================= */
$teacher_t_pid   = $_POST['t_pid'] ?? '';
$subject_code    = $_POST['subject_code'] ?? '';
$inspection_time = $_POST['inspection_time'] ?? '';

if (!$teacher_t_pid || !$subject_code || !$inspection_time) {
    $_SESSION['flash_error'] = 'ข้อมูลไม่ครบ';
    header('Location: trash_sessions.php');
    exit;
}

try {

    /* =========================
       1) ตรวจว่าข้อมูลอยู่ในถังขยะจริง
    ========================= */
    $sqlCheck = "
    SELECT supervisor_p_id
    FROM supervision_sessions
    WHERE teacher_t_pid = ?
      AND subject_code = ?
      AND inspection_time = ?
      AND deleted_at IS NOT NULL
";

    $params = [$teacher_t_pid, $subject_code, $inspection_time];

    if (!$isAdmin) {
        $sqlCheck .= " AND supervisor_p_id = ? ";
        $params[] = $supervisor_id;
    }

    $sqlCheck .= " LIMIT 1";

    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->execute($params);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $_SESSION['flash_error'] = 'ไม่พบข้อมูลในถังขยะ หรือไม่มีสิทธิ์กู้คืน';
        header('Location: index.php');
        exit;
    }

    $targetSupervisorId = $row['supervisor_p_id'];

    /* =========================
       2) กู้คืน (set deleted_at = NULL)
    ========================= */
    $stmt = $conn->prepare("
        UPDATE supervision_sessions
        SET deleted_at = NULL
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
          AND deleted_at IS NOT NULL
        LIMIT 1
    ");

    $stmt->execute([
        $targetSupervisorId,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    if ($stmt->rowCount() === 0) {
        $_SESSION['flash_error'] = 'กู้คืนไม่สำเร็จ';
    } else {
        $_SESSION['flash_success'] = '♻️ กู้คืนข้อมูลเรียบร้อยแล้ว';
    }

    header('Location: index.php');
    exit;
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
