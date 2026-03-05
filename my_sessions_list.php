<?php

/*********************************
 * LIST ALL FORMS (ADMIN - FINAL)
 *********************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/config/session_config.php';

/* =========================
   AUTH
========================= */
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

$queryString = http_build_query([
    'teacher'   => $teacher,
    'form_type' => $form_type,
    'academic_year'  => $academic_year
]);

/* =========================
   PAGINATION
========================= */
$limit  = 50;
$page   = max((int)($_GET['page'] ?? 1), 1);
$offset = ($page - 1) * $limit;

/* =========================
   WHERE (ใช้ร่วม COUNT + LIST)
========================= */
$where  = [];
$params = [];

if ($teacher !== '') {
    $where[]  = 'teacher_name LIKE ?';
    $params[] = "%$teacher%";
}

if ($form_type !== '') {
    $where[]  = 'form_type = ?';
    $params[] = $form_type;
}

if ($academic_year !== '') {
    $where[]  = 'academic_year = ?';
    $params[] = $academic_year;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =========================
   COUNT (ผูกกับ FILTER)
========================= */
$countSql = "
SELECT COUNT(*) FROM (
    SELECT 
        CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name,
        'classroom' AS form_type,
        ss.academic_year
    FROM supervision_sessions ss
    LEFT JOIN teacher t ON ss.t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    WHERE ss.deleted_at IS NULL

    UNION ALL

    SELECT
        CONCAT(p2.prefix_name,' ',t2.f_name,' ',t2.l_name) AS teacher_name,
        'quickwin' AS form_type,
        qw.academic_year
    FROM quick_win qw
        LEFT JOIN teacher t2 ON qw.t_pid = t2.t_pid
        LEFT JOIN prefix p2 ON t2.prefix_id = p2.prefix_id
        LEFT JOIN school s2 ON t2.school_id = s2.school_id
        LEFT JOIN supervisor sp2 ON qw.p_id = sp2.p_id
        LEFT JOIN prefix pr2 ON sp2.prefix_id = pr2.prefix_id
        WHERE qw.deleted_at IS NULL
) all_forms
$whereSQL
";

$stmtCount = $conn->prepare($countSql);
$stmtCount->execute($params);
$total_rows  = (int)$stmtCount->fetchColumn();
$total_pages = max(ceil($total_rows / $limit), 1);

/* =========================
   FETCH DATA
========================= */
$sql = "
SELECT *
FROM (
    SELECT 
        'classroom' AS form_type,
        ss.p_id AS supervisor_p_id,
        ss.t_pid AS t_pid,
        ss.subject_code,
        ss.subject_name,
        ss.inspection_time,
        ss.supervision_date,
        ss.academic_year,
        CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name,
        s.school_name,
        CONCAT(IFNULL(pr.prefix_name,''), sp.fname,' ',sp.lname) AS supervisor_name
    FROM supervision_sessions ss
    LEFT JOIN teacher t ON ss.t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    LEFT JOIN school s ON t.school_id = s.school_id
    LEFT JOIN supervisor sp ON ss.p_id = sp.p_id
    LEFT JOIN prefix pr ON sp.prefix_id = pr.prefix_id
    WHERE ss.deleted_at IS NULL

    UNION ALL

    SELECT
        'quickwin' AS form_type,
        qw.p_id AS supervisor_p_id,
        qw.t_pid,
        NULL,
        'Quick Win',
        NULL,
        qw.supervision_date,
        qw.academic_year,
        CONCAT(p2.prefix_name,' ',t2.f_name,' ',t2.l_name),
        s2.school_name,
        CONCAT(IFNULL(pr2.prefix_name,''), sp2.fname,' ',sp2.lname)
    FROM quick_win qw
        LEFT JOIN teacher t2 ON qw.t_pid = t2.t_pid
        LEFT JOIN prefix p2 ON t2.prefix_id = p2.prefix_id
        LEFT JOIN school s2 ON t2.school_id = s2.school_id
        LEFT JOIN supervisor sp2 ON qw.p_id = sp2.p_id
        LEFT JOIN prefix pr2 ON sp2.prefix_id = pr2.prefix_id
        WHERE qw.deleted_at IS NULL
) all_forms
$whereSQL
ORDER BY supervision_date DESC
LIMIT $limit OFFSET $offset
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH ACADEMIC YEARS
========================= */
$yearSql = "
SELECT DISTINCT academic_year
FROM (
    SELECT academic_year
    FROM supervision_sessions
    WHERE deleted_at IS NULL
      AND academic_year IS NOT NULL

    UNION

    SELECT academic_year
    FROM quick_win
    WHERE deleted_at IS NULL
      AND academic_year IS NOT NULL
) y
ORDER BY academic_year DESC
";

$yearStmt = $conn->query($yearSql);
$academicYears = $yearStmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายการแบบฟอร์มทั้งหมด</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold text-primary mb-0">
                <i class="fas fa-list me-2"></i>
                รายการแบบฟอร์มทั้งหมด
                <span class="badge bg-primary ms-2">
                    <?= number_format($total_rows) ?> แบบฟอร์ม
                </span>
            </h4>

            <a href="index.php" class="btn btn-danger shadow-sm rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
            </a>
        </div>

        <!-- SEARCH -->
        <form method="GET" class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">

                    <div class="col-md-6">
                        <label class="fw-bold">ค้นหาชื่อครู</label>
                        <input type="text" name="teacher" class="form-control"
                            value="<?= htmlspecialchars($teacher) ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="fw-bold">ประเภทแบบฟอร์ม</label>
                        <select name="form_type" class="form-select"
                            onchange="this.form.submit()">
                            <option value="">ทั้งหมด</option>
                            <option value="classroom" <?= $form_type === 'classroom' ? 'selected' : '' ?>>Classroom</option>
                            <option value="quickwin" <?= $form_type === 'quickwin' ? 'selected' : '' ?>>Quick Win</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="fw-bold">ปีการศึกษา</label>
                        <select name="academic_year" class="form-select" onchange="this.form.submit()">
                            <option value="">ทั้งหมด</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?= $year ?>"
                                    <?= $academic_year == $year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 d-grid gap-2">
                        <button class="btn btn-primary">ค้นหา</button>
                        <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>"
                            class="btn btn-outline-secondary">ล้างค่า</a>
                    </div>

                </div>
            </div>
        </form>

        <!-- TABLE -->
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ครู</th>
                            <th>โรงเรียน</th>
                            <th>วิชา</th>
                            <th class="text-center">ครั้งที่</th>
                            <th class="text-center">ประเภท</th>
                            <!-- <th class="text-center">ปีการศึกษา</th> -->
                            <th>ผู้นิเทศ</th>
                            <th class="text-center">วันที่</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="8" class="text-center text-danger py-4">ไม่พบข้อมูล</td>
                            </tr>
                            <?php else: foreach ($rows as $r): ?>
                                <tr data-row>
                                    <td><?= htmlspecialchars($r['teacher_name']) ?></td>
                                    <td><?= htmlspecialchars($r['school_name']) ?></td>
                                    <td><?= htmlspecialchars($r['subject_name']) ?></td>
                                    <td class="text-center"><?= $r['inspection_time'] ?? '-' ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $r['form_type'] === 'classroom' ? 'bg-primary' : 'bg-success' ?>">
                                            <?= $r['form_type'] === 'classroom' ? 'ชั้นเรียน' : 'Quick Win' ?>
                                        </span>
                                    </td>
                                    <!-- <td class="text-center"><?= htmlspecialchars($r['academic_year'] ?? '-') ?></td> -->
                                    <td><?= htmlspecialchars($r['supervisor_name']) ?></td>
                                    <td class="text-center"><?= date('d/m/Y', strtotime($r['supervision_date'])) ?></td>
                                    <td class="text-center">

                                        <?php if ($r['form_type'] === 'classroom'): ?>

                                            <!-- EDIT CLASSROOM -->
                                            <form method="POST"
                                                action="<?= BASE_URL ?>/classroom/kpi_edit.php"
                                                class="d-inline">

                                                <input type="hidden" name="kpi_ref[t_pid]" value="<?= htmlspecialchars($r['t_pid']) ?>">
                                                <input type="hidden" name="kpi_ref[subject_code]" value="<?= htmlspecialchars($r['subject_code']) ?>">
                                                <input type="hidden" name="kpi_ref[inspection_time]" value="<?= htmlspecialchars($r['inspection_time']) ?>">
                                                <input type="hidden" name="kpi_ref[p_id]" value="<?= htmlspecialchars($r['supervisor_p_id']) ?>">
                                                <input type="hidden" name="kpi_ref[academic_year]" value="<?= htmlspecialchars($r['academic_year']) ?>">

                                                <button type="submit"
                                                    class="btn btn-sm btn-warning me-1"
                                                    title="แก้ไข Classroom">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </form>

                                            <!-- DELETE CLASSROOM -->
                                            <form method="POST"
                                                action="<?= BASE_URL ?>/classroom/delete_kpi_session.php"
                                                class="d-inline delete-soft-form"
                                                data-type="classroom">

                                                <input type="hidden" name="supervisor_p_id" value="<?= $r['supervisor_p_id'] ?>">
                                                <input type="hidden" name="t_pid" value="<?= $r['t_pid'] ?>">
                                                <input type="hidden" name="subject_code" value="<?= $r['subject_code'] ?>">
                                                <input type="hidden" name="inspection_time" value="<?= $r['inspection_time'] ?>">

                                                <button type="button"
                                                    class="btn btn-sm btn-danger"
                                                    title="ลบชั้นเรียน">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>

                                        <?php else: ?>

                                            <!-- EDIT QUICK WIN (NO URL PARAM) -->
                                            <form method="POST"
                                                action="<?= BASE_URL ?>/quickwin/quickwin_edit.php"
                                                class="d-inline">

                                                <input type="hidden" name="qw_ref[t_pid]" value="<?= htmlspecialchars($r['t_pid']) ?>">
                                                <input type="hidden" name="qw_ref[p_id]" value="<?= htmlspecialchars($r['supervisor_p_id']) ?>">
                                                <input type="hidden" name="qw_ref[supervision_date]" value="<?= htmlspecialchars($r['supervision_date']) ?>">

                                                <button type="submit"
                                                    class="btn btn-sm btn-info me-1"
                                                    title="แก้ไข Quick Win">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </form>

                                            <!-- DELETE QUICK WIN -->
                                            <form method="POST"
                                                action="<?= BASE_URL ?>/quickwin/delete_quickwin.php"
                                                class="d-inline delete-soft-form"
                                                data-type="quickwin">

                                                <input type="hidden" name="p_id" value="<?= $r['supervisor_p_id'] ?>">
                                                <input type="hidden" name="t_pid" value="<?= $r['t_pid'] ?>">
                                                <input type="hidden" name="supervision_date" value="<?= $r['supervision_date'] ?>">

                                                <button type="button"
                                                    class="btn btn-sm btn-danger"
                                                    title="ลบ Quick Win">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>

                                        <?php endif; ?>

                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_rows > $limit): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= $queryString ?>&page=<?= $i ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
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

                    const formData = new FormData(form);

                    fetch(form.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'สำเร็จ',
                                    text: 'ย้ายข้อมูลไปถังขยะแล้ว',
                                    timer: 1200,
                                    showConfirmButton: false
                                });

                                // ⭐ ลบแถวออกจากตารางทันที
                                form.closest('tr').remove();

                            } else {
                                Swal.fire('ผิดพลาด', data.message, 'error');
                            }
                        })
                        .catch(() => {
                            Swal.fire('ผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error');
                        });
                });
            });
        });
    </script>

    <?php if (!empty($_SESSION['flash_message'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // 1. ตรวจสอบพารามิเตอร์ success จาก URL (เช่น ?success=update หรือ ?success=delete)
                const urlParams = new URLSearchParams(window.location.search);
                const successStatus = urlParams.get('success');

                <?php if (!empty($_SESSION['flash_message'])): ?>
                    // 2. แสดงป๊อปอัปเฉพาะเมื่อมีพารามิเตอร์ success ยืนยันใน URL เท่านั้น
                    if (successStatus === 'update' || successStatus === 'delete' || successStatus === 'save') {
                        Swal.fire({
                            icon: '<?= $_SESSION['flash_type'] ?? 'success' ?>',
                            title: 'สำเร็จ',
                            text: '<?= addslashes($_SESSION['flash_message']) ?>',
                            timer: 2000,
                            showConfirmButton: false,
                            timerProgressBar: true
                        }).then(() => {
                            // 3. ล้างพารามิเตอร์บน URL ออกหลังกด OK เพื่อป้องกันการ Refresh แล้วเด้งซ้ำ
                            const newUrl = window.location.pathname;
                            window.history.replaceState({}, document.title, newUrl);
                        });
                    }

                    <?php
                    // ล้างค่า Session ทันทีเพื่อไม่ให้ค้างไปหน้าอื่น
                    unset($_SESSION['flash_message'], $_SESSION['flash_type'], $_SESSION['flash_once']);
                    ?>
                <?php endif; ?>
            });
        </script>
    <?php endif; ?>
</body>

</html>