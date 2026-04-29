<?php

/*********************************
 * LIST ALL FORMS (ADMIN - FINAL)
 *********************************/
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/session_config.php';

if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}
if (empty($_SESSION['user_id'])) {
    exit('Unauthorized');
}

require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/app.php';

/* =========================
   RECEIVE FILTER
========================= */
$teacher   = trim($_GET['teacher'] ?? '');
$form_type = $_GET['form_type'] ?? '';
$academic_year  = $_GET['academic_year'] ?? '';

$queryString = http_build_query(['teacher' => $teacher, 'form_type' => $form_type, 'academic_year' => $academic_year]);

/* =========================
   PAGINATION & WHERE SQL
========================= */
$limit  = 12; // ปรับเป็น 12 เพื่อให้หารลงตัวกับ Grid 3 หรือ 4 คอลัมน์
$page   = max((int)($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

$where = [];
$params = [];
if ($teacher !== '') {
    $where[] = 'teacher_name LIKE ?';
    $params[] = "%$teacher%";
}
if ($form_type !== '') {
    $where[] = 'form_type = ?';
    $params[] = $form_type;
}
if ($academic_year !== '') {
    $where[] = 'academic_year = ?';
    $params[] = $academic_year;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =========================
   COUNT & FETCH DATA (UNION)
========================= */
$baseUnion = "
    SELECT 
        'classroom' AS form_type, ss.p_id AS supervisor_p_id, ss.t_pid, ss.subject_code, ss.subject_name,
        ss.inspection_time, ss.supervision_date, ss.academic_year,
        CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name,
        s.school_name, CONCAT(IFNULL(pr.prefix_name,''), sp.fname,' ',sp.lname) AS supervisor_name
    FROM supervision_sessions ss
    LEFT JOIN teacher t ON ss.t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    LEFT JOIN school s ON t.school_id = s.school_id
    LEFT JOIN supervisor sp ON ss.p_id = sp.p_id
    LEFT JOIN prefix pr ON sp.prefix_id = pr.prefix_id
    WHERE ss.deleted_at IS NULL

    UNION ALL

    SELECT
        'quickwin' AS form_type, qw.p_id AS supervisor_p_id, qw.t_pid, NULL, 'Quick Win',
        NULL, qw.supervision_date, qw.academic_year,
        CONCAT(p2.prefix_name,' ',t2.f_name,' ',t2.l_name), s2.school_name,
        CONCAT(IFNULL(pr2.prefix_name,''), sp2.fname,' ',sp2.lname)
    FROM quick_win qw
    LEFT JOIN teacher t2 ON qw.t_pid = t2.t_pid
    LEFT JOIN prefix p2 ON t2.prefix_id = p2.prefix_id
    LEFT JOIN school s2 ON t2.school_id = s2.school_id
    LEFT JOIN supervisor sp2 ON qw.p_id = sp2.p_id
    LEFT JOIN prefix pr2 ON sp2.prefix_id = pr2.prefix_id
    WHERE qw.deleted_at IS NULL
";

$countSql = "SELECT COUNT(*) FROM ($baseUnion) all_forms $whereSQL";
$stmtCount = $conn->prepare($countSql);
$stmtCount->execute($params);
$total_rows = (int)$stmtCount->fetchColumn();
$total_pages = max(ceil($total_rows / $limit), 1);

$sql = "SELECT * FROM ($baseUnion) all_forms $whereSQL ORDER BY supervision_date DESC LIMIT $limit OFFSET $offset";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Years for Filter
$yearSql = "SELECT DISTINCT academic_year FROM (SELECT academic_year FROM supervision_sessions WHERE deleted_at IS NULL UNION SELECT academic_year FROM quick_win WHERE deleted_at IS NULL) y ORDER BY academic_year DESC";
$academicYears = $conn->query($yearSql)->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายการแบบฟอร์ม | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f1f3f9;
            font-family: 'Sarabun', sans-serif;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .session-card {
            background: #fff;
            border: none;
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
            height: 100%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .session-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .avatar-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .type-ribbon {
            position: absolute;
            top: 12px;
            right: -30px;
            width: 120px;
            text-align: center;
            font-size: 0.75rem;
            font-weight: bold;
            padding: 4px 0;
            transform: rotate(45deg);
            color: #fff;
            z-index: 10;
            text-transform: uppercase;
        }

        .bg-classroom {
            background-color: #6c5ce7;
        }

        .bg-quickwin {
            background-color: #00b894;
        }

        .info-label {
            font-size: 0.85rem;
            color: #636e72;
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .info-label i {
            width: 20px;
            margin-right: 8px;
            color: #b2bec3;
        }

        .card-actions {
            border-top: 1px solid #f1f2f6;
            padding: 12px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-5">
        <div class="page-header d-flex justify-content-between align-items-end">
            <div>
                <h2 class="fw-bold mb-1">รายการนิเทศติดตามและประเมินผล</h2>
                <p class="text-muted mb-0">รายการนิเทศติดตามผลทั้งหมดบนแพลตฟอร์ม</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge rounded-pill bg-white text-dark shadow-sm px-3 py-2">
                    <i class="fas fa-file-alt text-primary me-2"></i><?= number_format($total_rows) ?> รายการ
                </span>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4 mb-5 p-3">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-bold small">ค้นหาชื่อครู</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="teacher" class="form-control border-start-0" placeholder="พิมพ์ชื่อครู..." value="<?= htmlspecialchars($teacher) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">ประเภท</label>
                    <select name="form_type" class="form-select" onchange="this.form.submit()">
                        <option value="">ทั้งหมด</option>
                        <option value="classroom" <?= $form_type === 'classroom' ? 'selected' : '' ?>>Classroom</option>
                        <option value="quickwin" <?= $form_type === 'quickwin' ? 'selected' : '' ?>>Quick Win</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small">ปีการศึกษา</label>
                    <select name="academic_year" class="form-select" onchange="this.form.submit()">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?= $year ?>" <?= $academic_year == $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary px-4">ค้นหา</button>
                    <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="btn btn-light px-4">ล้างค่า</a>
                </div>
            </form>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if (!$rows): ?>
                <div class="col-12 text-center py-5">
                    <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="opacity-25 mb-3">
                    <p class="text-muted fs-5">ไม่พบข้อมูลแบบฟอร์มที่ค้นหา</p>
                </div>
                <?php else: foreach ($rows as $r):
                    $initial = mb_substr(explode(' ', $r['teacher_name'])[1] ?? 'T', 0, 1, 'utf-8');
                    $isClassroom = ($r['form_type'] === 'classroom');
                ?>
                    <div class="col">
                        <div class="card session-card shadow-sm">
                            <div class="type-ribbon <?= $isClassroom ? 'bg-classroom' : 'bg-quickwin' ?>">
                                <?= $isClassroom ? 'Classroom' : 'Quick Win' ?>
                            </div>

                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-4">
                                    <div class="avatar-circle <?= $isClassroom ? 'bg-classroom' : 'bg-quickwin' ?>">
                                        <?= $initial ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <h6 class="fw-bold mb-0 text-truncate"><?= htmlspecialchars($r['teacher_name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($r['school_name']) ?></small>
                                    </div>
                                </div>

                                <div class="info-label">
                                    <i class="fas fa-book-open"></i>
                                    <span><?= htmlspecialchars($r['subject_name'] ?: 'Quick Win Session') ?></span>
                                </div>
                                <?php if ($isClassroom): ?>
                                    <div class="info-label">
                                        <i class="fas fa-layer-group"></i>
                                        <span>ครั้งที่นิเทศ: <?= $r['inspection_time'] ?> / ปี <?= $r['academic_year'] ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="info-label">
                                    <i class="fas fa-user-tie"></i>
                                    <span>ผู้นิเทศ: <?= htmlspecialchars($r['supervisor_name']) ?></span>
                                </div>
                                <div class="info-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?= date('d M Y', strtotime($r['supervision_date'])) ?></span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <?php if ($isClassroom): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/classroom/kpi_edit.php" class="d-inline">
                                        <input type="hidden" name="kpi_ref[t_pid]" value="<?= $r['t_pid'] ?>">
                                        <input type="hidden" name="kpi_ref[subject_code]" value="<?= $r['subject_code'] ?>">
                                        <input type="hidden" name="kpi_ref[inspection_time]" value="<?= $r['inspection_time'] ?>">
                                        <input type="hidden" name="kpi_ref[p_id]" value="<?= $r['supervisor_p_id'] ?>">
                                        <input type="hidden" name="kpi_ref[academic_year]" value="<?= $r['academic_year'] ?>">
                                        <button type="submit" class="btn btn-sm btn-light text-warning shadow-sm"><i class="fas fa-edit"></i> แก้ไข</button>
                                    </form>
                                    <form method="POST" action="<?= BASE_URL ?>/classroom/delete_kpi_session.php" class="d-inline delete-soft-form">
                                        <input type="hidden" name="supervisor_p_id" value="<?= $r['supervisor_p_id'] ?>">
                                        <input type="hidden" name="t_pid" value="<?= $r['t_pid'] ?>">
                                        <input type="hidden" name="subject_code" value="<?= $r['subject_code'] ?>">
                                        <input type="hidden" name="inspection_time" value="<?= $r['inspection_time'] ?>">
                                        <input type="hidden" name="academic_year" value="<?= $r['academic_year'] ?>">
                                        <button type="button" class="btn btn-sm btn-light text-danger shadow-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="<?= BASE_URL ?>/quickwin/quickwin_edit.php" class="d-inline">
                                        <input type="hidden" name="qw_ref[t_pid]" value="<?= $r['t_pid'] ?>">
                                        <input type="hidden" name="qw_ref[p_id]" value="<?= $r['supervisor_p_id'] ?>">
                                        <input type="hidden" name="qw_ref[supervision_date]" value="<?= $r['supervision_date'] ?>">
                                        <button type="submit" class="btn btn-sm btn-light text-info shadow-sm"><i class="fas fa-edit"></i> แก้ไข</button>
                                    </form>
                                    <form method="POST" action="<?= BASE_URL ?>/quickwin/delete_quickwin.php" class="d-inline delete-soft-form">
                                        <input type="hidden" name="p_id" value="<?= $r['supervisor_p_id'] ?>">
                                        <input type="hidden" name="t_pid" value="<?= $r['t_pid'] ?>">
                                        <input type="hidden" name="supervision_date" value="<?= $r['supervision_date'] ?>">
                                        <input type="hidden" name="academic_year" value="<?= $r['academic_year'] ?>">
                                        <button type="button" class="btn btn-sm btn-light text-danger shadow-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
            <?php endforeach;
            endif; ?>
        </div>

        <?php if ($total_rows > $limit): ?>
            <nav class="mt-5">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link shadow-sm border-0 mx-1 rounded-3" href="?<?= $queryString ?>&page=<?= $page - 1 ?>">ก่อนหน้า</a>
                    </li>

                    <?php
                    $window = 2;
                    for ($i = 1; $i <= $total_pages; $i++):
                        if ($i == 1 || $i == $total_pages || ($i >= $page - $window && $i <= $page + $window)):
                    ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link shadow-sm border-0 mx-1 rounded-3" href="?<?= $queryString ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                    <?php
                        elseif ($i == $page - $window - 1 || $i == $page + $window + 1):
                    ?>
                            <li class="page-item disabled"><span class="page-link shadow-sm border-0 mx-1 rounded-3">...</span></li>
                    <?php
                        endif;
                    endfor;
                    ?>

                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link shadow-sm border-0 mx-1 rounded-3" href="?<?= $queryString ?>&page=<?= $page + 1 ?>">ถัดไป</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ลบข้อมูลแบบ Ajax (เหมือนเดิมแต่ปรับ Selector นิดหน่อย)
        document.querySelectorAll('.delete-soft-form button').forEach(btn => {
            btn.addEventListener('click', function() {
                const form = this.closest('form');
                Swal.fire({
                    title: 'ยืนยันการลบ',
                    text: 'ข้อมูลจะถูกย้ายไปยังถังขยะ',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'ลบ',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#dc3545'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form)
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    timer: 1000,
                                    showConfirmButton: false
                                });
                                const colItem = form.closest('.col');
                                colItem.style.transition = 'opacity 0.3s ease';
                                colItem.style.opacity = '0';
                                setTimeout(() => colItem.remove(), 300);
                            } else {
                                Swal.fire('ผิดพลาด', data.message, 'error');
                            }
                        });
                });
            });
        });
    </script>
</body>

</html>