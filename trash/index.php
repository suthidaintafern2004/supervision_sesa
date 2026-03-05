<?php

/*********************************
 * TRASH INDEX (UPDATED FOR NEW DB)
 *********************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

/* =========================
   AUTH
========================= */
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

/* =========================
   FETCH TRASH DATA
========================= */
$sql = "
SELECT *
FROM (
    /* ---------- CLASSROOM (ปรับชื่อฟิลด์เป็น p_id / t_pid) ---------- */
    SELECT
        'classroom' AS form_type,
        ss.p_id AS supervisor_p_id,
        ss.t_pid,
        ss.subject_code,
        ss.subject_name,
        ss.inspection_time,
        ss.supervision_date,
        ss.academic_year, 
        ss.deleted_at,
        CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name
    FROM supervision_sessions ss
    LEFT JOIN teacher t ON ss.t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    WHERE ss.deleted_at IS NOT NULL

    UNION ALL

    /* ---------- QUICK WIN ---------- */
    SELECT
        'quickwin' AS form_type,
        qw.p_id AS supervisor_p_id,
        qw.t_pid,
        NULL AS subject_code,
        'Quick Win' AS subject_name,
        NULL AS inspection_time,
        qw.supervision_date,
        qw.academic_year,
        qw.deleted_at,
        CONCAT(p2.prefix_name,' ',t2.f_name,' ',t2.l_name) AS teacher_name
    FROM quick_win qw
    LEFT JOIN teacher t2 ON qw.t_pid = t2.t_pid
    LEFT JOIN prefix p2 ON t2.prefix_id = p2.prefix_id
    WHERE qw.deleted_at IS NOT NULL
) trash
";

$params = [];

if (!$isAdmin) {
    // กรองข้อมูลเฉพาะของผู้นิเทศที่ล็อกอินอยู่
    $sql .= " WHERE supervisor_p_id = ? ";
    $params[] = $user_id;
}

$sql .= " ORDER BY deleted_at DESC ";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ถังขยะ</title>
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

                <?php if (!$rows): ?>
                    <div class="text-center text-muted p-5">
                        <i class="fas fa-trash fa-3x mb-3"></i><br>
                        ไม่มีข้อมูลในถังขยะ
                    </div>
                <?php else: ?>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-danger text-center">
                                <tr>
                                    <th>ครู</th>
                                    <th>ประเภท</th>
                                    <th>วิชา</th>
                                    <th>ครั้งที่</th>
                                    <th>วันที่นิเทศ</th>
                                    <th>วันที่ลบ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($r['teacher_name']) ?></td>

                                        <td class="text-center">
                                            <span class="badge <?= $r['form_type'] === 'classroom' ? 'bg-primary' : 'bg-success' ?>">
                                                <?= $r['form_type'] === 'classroom' ? 'ชั้นเรียน' : 'Quick Win' ?>
                                            </span>
                                        </td>

                                        <td><?= htmlspecialchars($r['subject_name'] ?? '-') ?></td>
                                        <td class="text-center"><?= $r['inspection_time'] ?? '-' ?></td>

                                        <td class="text-center">
                                            <?= ($r['supervision_date']) ? date('d/m/Y', strtotime($r['supervision_date'])) : '-' ?>
                                        </td>

                                        <td class="text-center text-danger">
                                            <?= date('d/m/Y H:i', strtotime($r['deleted_at'])) ?>
                                        </td>

                                        <td class="text-center">

                                            <form method="POST" action="restore.php" class="d-inline restore-form">
                                                <input type="hidden" name="form_type" value="<?= $r['form_type'] ?>">
                                                <input type="hidden" name="p_id" value="<?= $r['supervisor_p_id'] ?>">
                                                <input type="hidden" name="t_pid" value="<?= $r['t_pid'] ?>">
                                                <input type="hidden" name="subject_code" value="<?= $r['subject_code'] ?>">
                                                <input type="hidden" name="inspection_time" value="<?= $r['inspection_time'] ?>">
                                                <input type="hidden" name="academic_year" value="<?= $r['academic_year'] ?>">
                                                <button type="button" class="btn btn-success btn-sm" title="กู้คืน">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="delete_permanent.php" class="d-inline delete-form">
                                                <input type="hidden" name="form_type" value="<?= $r['form_type'] ?>">
                                                <input type="hidden" name="p_id" value="<?= $r['supervisor_p_id'] ?>">
                                                <input type="hidden" name="t_pid" value="<?= $r['t_pid'] ?>">
                                                <input type="hidden" name="subject_code" value="<?= $r['subject_code'] ?>">
                                                <input type="hidden" name="inspection_time" value="<?= $r['inspection_time'] ?>">
                                                <input type="hidden" name="academic_year" value="<?= $r['academic_year'] ?>">
                                                <button type="button" class="btn btn-danger btn-sm" title="ลบถาวร">
                                                    <i class="fas fa-times"></i>
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
        document.querySelectorAll('.delete-form button').forEach(btn => {
            btn.onclick = () => {
                const form = btn.closest('form');
                Swal.fire({
                    title: 'ลบถาวร?',
                    text: 'ข้อมูลจะไม่สามารถกู้คืนได้',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonText: 'ลบถาวร',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#dc3545'
                }).then(r => r.isConfirmed && form.submit());
            };
        });

        document.querySelectorAll('.restore-form button').forEach(btn => {
            btn.onclick = () => {
                const form = btn.closest('form');
                Swal.fire({
                    title: 'กู้คืนข้อมูล?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'กู้คืน',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#198754'
                }).then(r => r.isConfirmed && form.submit());
            };
        });
    </script>

</body>

</html>