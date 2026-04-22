<?php

/*********************************
 * TRASH INDEX (GRID UI UPDATE)
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
   FETCH TRASH DATA (GRID VIEW)
========================= */
$sql = "
SELECT *
FROM (
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
    WHERE ss.deleted_at IS NOT NULL " . ($isAdmin ? "" : " AND ss.p_id = ?") . "

    UNION ALL

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
    WHERE qw.deleted_at IS NOT NULL " . ($isAdmin ? "" : " AND qw.p_id = ?") . "
) AS trash_all
ORDER BY deleted_at DESC
";

$stmt = $conn->prepare($sql);
if (!$isAdmin) {
    $stmt->execute([$user_id, $user_id]);
} else {
    $stmt->execute();
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ถังขยะ | ระบบนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Sarabun', sans-serif;
        }

        .trash-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .trash-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(220, 53, 69, 0.1);
        }

        .avatar-circle {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            background-color: #adb5bd;
            margin-right: 12px;
        }

        .delete-info {
            font-size: 0.8rem;
            color: #dc3545;
            background: #fff5f5;
            padding: 4px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 10px;
        }

        .card-actions {
            border-top: 1px solid #f8f9fa;
            padding: 12px;
            background: #fafafa;
            display: flex;
            justify-content: flex-end;
            gap: 5px;
        }

        .badge-type {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.7rem;
        }
    </style>
</head>

<body class="bg-light d-flex flex-column min-vh-100">

    <?php $nav_prefix = '../'; include '../navbar.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark"><i class="fas fa-trash-alt text-danger me-2"></i>ถังขยะ (Trash)</h2>
                <p class="text-muted">รายการที่ถูกลบจะปรากฏที่นี่ คุณสามารถกู้คืนหรือลบถาวรได้</p>
            </div>
            <a href="../my_sessions_list.php" class="btn btn-outline-secondary rounded-pill">
                <i class="fas fa-chevron-left me-1"></i> กลับหน้าหลัก
            </a>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (!$rows): ?>
                <div class="col-12 text-center py-5">
                    <div class="mb-3"><i class="fas fa-folder-open fa-4x text-light"></i></div>
                    <h5 class="text-muted">ไม่มีรายการในถังขยะ</h5>
                </div>
                <?php else: foreach ($rows as $r):
                    $initial = mb_substr($r['teacher_name'], 0, 1, 'utf-8');
                ?>
                    <div class="col">
                        <div class="card trash-card shadow-sm">
                            <span class="badge rounded-pill <?= $r['form_type'] === 'classroom' ? 'bg-primary' : 'bg-success' ?> badge-type">
                                <?= strtoupper($r['form_type']) ?>
                            </span>

                            <div class="card-body p-4">
                                <div class="delete-info">
                                    <i class="fas fa-clock me-1"></i> ลบเมื่อ: <?= date('d/m/Y H:i', strtotime($r['deleted_at'])) ?>
                                </div>

                                <div class="d-flex align-items-center mb-3">
                                    <div class="avatar-circle">
                                        <?= $initial ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="fw-bold mb-0 text-truncate"><?= htmlspecialchars($r['teacher_name']) ?></h6>
                                        <small class="text-muted">ปีการศึกษา <?= $r['academic_year'] ?></small>
                                    </div>
                                </div>

                                <p class="small text-secondary mb-1"><i class="fas fa-book me-2"></i><?= htmlspecialchars($r['subject_name']) ?></p>
                                <?php if ($r['inspection_time']): ?>
                                    <p class="small text-secondary mb-0"><i class="fas fa-list-ol me-2"></i>ครั้งที่นิเทศ: <?= $r['inspection_time'] ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="card-actions">
                                <form action="restore.php" method="POST" class="restore-form">
                                    <input type="hidden" name="form_type" value="<?= $r['form_type'] ?>">
                                    <input type="hidden" name="t_pid" value="<?= $r['t_pid'] ?>">
                                    <input type="hidden" name="p_id" value="<?= $r['supervisor_p_id'] ?>">
                                    <?php if ($r['form_type'] === 'classroom'): ?>
                                        <input type="hidden" name="subject_code" value="<?= $r['subject_code'] ?>">
                                        <input type="hidden" name="inspection_time" value="<?= $r['inspection_time'] ?>">
                                    <?php else: ?>
                                    <input type="hidden" name="academic_year" value="<?= $r['academic_year'] ?>">
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-success px-3 rounded-pill">
                                        <i class="fas fa-undo me-1"></i> กู้คืน
                                    </button>
                                </form>

                                <form action="delete_permanent.php" method="POST" class="delete-form">
                                    <input type="hidden" name="form_type" value="<?= $r['form_type'] ?>">
                                    <input type="hidden" name="t_pid" value="<?= $r['t_pid'] ?>">
                                    <input type="hidden" name="p_id" value="<?= $r['supervisor_p_id'] ?>">
                                    <?php if ($r['form_type'] === 'classroom'): ?>
                                        <input type="hidden" name="subject_code" value="<?= $r['subject_code'] ?>">
                                        <input type="hidden" name="inspection_time" value="<?= $r['inspection_time'] ?>">
                                    <?php else: ?>
                                    <input type="hidden" name="academic_year" value="<?= $r['academic_year'] ?>">
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger px-3 rounded-pill">
                                        <i class="fas fa-times me-1"></i> ลบถาวร
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // SweetAlert สำหรับปุ่มกู้คืน
        document.querySelectorAll('.restore-form button').forEach(btn => {
            btn.onclick = () => {
                const form = btn.closest('form');
                Swal.fire({
                    title: 'กู้คืนข้อมูล?',
                    text: 'รายการนี้จะกลับไปอยู่ที่หน้าหลัก',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'กู้คืน',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#198754'
                }).then(r => r.isConfirmed && form.submit());
            };
        });

        // SweetAlert สำหรับปุ่มลบถาวร
        document.querySelectorAll('.delete-form button').forEach(btn => {
            btn.onclick = () => {
                const form = btn.closest('form');
                Swal.fire({
                    title: 'ยืนยันลบถาวร?',
                    text: 'ข้อมูลจะถูกลบออกจากฐานข้อมูลและไม่สามารถกู้คืนได้!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ลบถาวร',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#dc3545'
                }).then(r => r.isConfirmed && form.submit());
            };
        });
    </script>
</body>

</html>