<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

/* =========================
   ตรวจสอบสิทธิ์
========================= */
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request');
}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

/* =========================
   รับค่าจากฟอร์ม
========================= */
$teacher_t_pid   = $_POST['t_pid'] ?? '';
$subject_code    = $_POST['subject_code'] ?? '';
$inspection_time = $_POST['inspection_time'] ?? '';

/* ⭐ admin ต้องส่ง supervisor_p_id มาด้วย */
$supervisor_id = $isAdmin
    ? ($_POST['supervisor_p_id'] ?? null)
    : $_SESSION['user_id'];

if (!$teacher_t_pid || !$subject_code || !$inspection_time || !$supervisor_id) {
    $error = 'ข้อมูลไม่ครบ';
}

/* =========================
   ลบ (Soft delete)
========================= */
if (empty($error)) {
    try {
        $stmt = $conn->prepare("
            UPDATE supervision_sessions
            SET deleted_at = NOW()
            WHERE supervisor_p_id = ?
              AND teacher_t_pid   = ?
              AND subject_code    = ?
              AND inspection_time = ?
              AND deleted_at IS NULL
            LIMIT 1
        ");

        $stmt->execute([
            $supervisor_id,
            $teacher_t_pid,
            $subject_code,
            $inspection_time
        ]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('ไม่พบข้อมูล หรือไม่มีสิทธิ์ลบ');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ลบข้อมูล</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <script>
        <?php if (empty($error)): ?>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: 'ย้ายข้อมูลไปยังถังขยะแล้ว',
                confirmButtonText: 'ตกลง'
            }).then(() => {
                window.location.href = '../my_sessions_list.php';
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