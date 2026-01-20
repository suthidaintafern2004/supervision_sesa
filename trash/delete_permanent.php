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

$supervisor_id   = $_SESSION['user_id'];
$isAdmin         = ($_SESSION['role'] ?? '') === 'admin';

/* =========================
   รับค่าจากฟอร์ม
========================= */
$teacher_t_pid   = $_POST['t_pid'] ?? '';
$subject_code    = $_POST['subject_code'] ?? '';
$inspection_time = $_POST['inspection_time'] ?? '';

if (!$teacher_t_pid || !$subject_code || !$inspection_time) {
    die('ข้อมูลไม่ครบ');
}

$uploadDir = __DIR__ . '/../uploads/';
$error = null;

try {
    $conn->beginTransaction();

    /* =========================
       0) ตรวจว่าข้อมูลอยู่ในถังขยะจริง
    ========================= */
    $sqlCheck = "
        SELECT supervisor_p_id
        FROM supervision_sessions
        WHERE teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
          AND deleted_at IS NOT NULL
        LIMIT 1
    ";

    $params = [$teacher_t_pid, $subject_code, $inspection_time];

    if (!$isAdmin) {
        $sqlCheck .= " AND supervisor_p_id = ? ";
        $params[] = $supervisor_id;
    }

    $checkStmt = $conn->prepare($sqlCheck);
    $checkStmt->execute($params);
    $sessionRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sessionRow) {
        throw new Exception('ไม่พบข้อมูลในถังขยะ หรือไม่มีสิทธิ์ลบ');
    }

    // supervisor ที่เป็นเจ้าของข้อมูลจริง
    $targetSupervisorId = $sessionRow['supervisor_p_id'];

    /* =========================
       1) ดึงชื่อไฟล์รูป (ยังไม่ลบไฟล์)
    ========================= */
    $sqlImg = "
        SELECT file_name
        FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ";

    $imgStmt = $conn->prepare($sqlImg);
    $imgStmt->execute([
        $targetSupervisorId,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    $filesToDelete = [];
    foreach ($imgStmt->fetchAll(PDO::FETCH_COLUMN) as $file) {
        $filesToDelete[] = $uploadDir . $file;
    }

    /* =========================
       2) ลบข้อมูลรูปภาพใน DB
    ========================= */
    $conn->prepare("
        DELETE FROM images
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ")->execute([
        $targetSupervisorId,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    /* =========================
       3) ลบ KPI ANSWERS
    ========================= */
    $conn->prepare("
        DELETE FROM kpi_answers
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ")->execute([
        $targetSupervisorId,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    /* =========================
       4) ลบ KPI INDICATOR SUGGESTIONS
    ========================= */
    $conn->prepare("
        DELETE FROM kpi_indicator_suggestions
        WHERE supervisor_p_id = ?
          AND teacher_t_pid = ?
          AND subject_code = ?
          AND inspection_time = ?
    ")->execute([
        $targetSupervisorId,
        $teacher_t_pid,
        $subject_code,
        $inspection_time
    ]);

    /* =========================
       5) ลบ session หลัก (ถาวร)
    ========================= */
    $stmt = $conn->prepare("
        DELETE FROM supervision_sessions
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
        throw new Exception('ไม่สามารถลบข้อมูลหลักได้');
    }

    /* =========================
       6) COMMIT
    ========================= */
    $conn->commit();

    /* =========================
       7) ลบไฟล์จริง (หลัง commit)
    ========================= */
    foreach ($filesToDelete as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ลบข้อมูลถาวร</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <script>
        <?php if ($error === null): ?>
            Swal.fire({
                icon: 'success',
                title: 'ลบถาวรสำเร็จ',
                text: 'ข้อมูลถูกลบออกจากระบบเรียบร้อยแล้ว',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#d33'
            }).then(() => {
                window.location.href = 'index.php';
            });
        <?php else: ?>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: <?= json_encode($error) ?>,
                confirmButtonText: 'ตกลง'
            }).then(() => {
                window.history.back();
            });
        <?php endif; ?>
    </script>
</body>

</html>