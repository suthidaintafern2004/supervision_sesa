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

$supervisor_id = $_SESSION['user_id'];
$isAdmin       = ($_SESSION['role'] ?? '') === 'admin';

/* =========================
   ดึงข้อมูลที่ถูกลบ
========================= */
$sql = "
    SELECT 
        ss.supervisor_p_id,
        ss.teacher_t_pid,
        ss.subject_code,
        ss.subject_name,
        ss.inspection_time,
        ss.supervision_date,
        ss.deleted_at,
        CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name
    FROM supervision_sessions ss
    LEFT JOIN teacher t ON ss.teacher_t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    WHERE ss.deleted_at IS NOT NULL
";

$params = [];

if (!$isAdmin) {
    $sql .= " AND ss.supervisor_p_id = ? ";
    $params[] = $supervisor_id;
}

$sql .= " ORDER BY ss.deleted_at DESC ";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$trashList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ถังขยะข้อมูลการนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-danger">
                <i class="fas fa-trash"></i> ถังขยะข้อมูลการนิเทศ
            </h3>
            <a href="../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">

                <?php if (empty($trashList)): ?>
                    <div class="text-center text-muted p-5">
                        <i class="fas fa-trash fa-3x mb-3"></i><br>
                        ไม่มีข้อมูลในถังขยะ
                    </div>
                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead class="table-danger text-center">
                                <tr>
                                    <th>ผู้รับการนิเทศ</th>
                                    <th>วิชา</th>
                                    <th>ครั้งที่</th>
                                    <th>วันที่นิเทศ</th>
                                    <th>วันที่ลบ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trashList as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['teacher_name']) ?></td>
                                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                        <td class="text-center"><?= (int)$row['inspection_time'] ?></td>
                                        <td class="text-center">
                                            <?= date('d/m/Y', strtotime($row['supervision_date'])) ?>
                                        </td>
                                        <td class="text-center text-danger">
                                            <?= date('d/m/Y H:i', strtotime($row['deleted_at'])) ?>
                                        </td>
                                        <td class="text-center">

                                            <!-- กู้คืน -->
                                            <form method="POST"
                                                action="restore.php"
                                                class="d-inline restore-form">
                                                <input type="hidden" name="t_pid" value="<?= htmlspecialchars($row['teacher_t_pid']) ?>">
                                                <input type="hidden" name="subject_code" value="<?= htmlspecialchars($row['subject_code']) ?>">
                                                <input type="hidden" name="inspection_time" value="<?= (int)$row['inspection_time'] ?>">
                                                <button type="button" class="btn btn-success btn-sm">
                                                    <i class="fas fa-undo"></i> กู้คืน
                                                </button>
                                            </form>

                                            <!-- ลบถาวร -->
                                            <form method="POST"
                                                action="delete_permanent.php"
                                                class="d-inline delete-hard-form">
                                                <input type="hidden" name="t_pid" value="<?= htmlspecialchars($row['teacher_t_pid']) ?>">
                                                <input type="hidden" name="subject_code" value="<?= htmlspecialchars($row['subject_code']) ?>">
                                                <input type="hidden" name="inspection_time" value="<?= (int)$row['inspection_time'] ?>">
                                                <button type="button" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> ลบถาวร
                                                </button>
                                            </form>

                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.querySelectorAll('.delete-hard-form button').forEach(btn => {
            btn.addEventListener('click', () => {
                const form = btn.closest('form');
                Swal.fire({
                    title: 'ลบถาวร?',
                    text: 'ข้อมูลจะถูกลบออกจากระบบและไม่สามารถกู้คืนได้',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonText: 'ลบถาวร',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#d33'
                }).then(res => res.isConfirmed && form.submit());
            });
        });

        document.querySelectorAll('.restore-form button').forEach(btn => {
            btn.addEventListener('click', () => {
                const form = btn.closest('form');
                Swal.fire({
                    title: 'กู้คืนข้อมูล?',
                    text: 'ข้อมูลจะถูกย้ายกลับไปยังรายการปกติ',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'กู้คืน',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#198754'
                }).then(res => res.isConfirmed && form.submit());
            });
        });
    </script>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: <?= json_encode($_SESSION['flash_success']) ?>,
                timer: 2000,
                showConfirmButton: false
            });
        </script>
    <?php unset($_SESSION['flash_success']);
    endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: <?= json_encode($_SESSION['flash_error']) ?>
            });
        </script>
    <?php unset($_SESSION['flash_error']);
    endif; ?>

</body>

</html>