<?php
session_start();
require_once 'config/db_connect.php';

/* =========================
   ROLE
========================= */
$allow_supervisor_admin = (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
);

/* =========================
   SITE VIEW COUNTER
========================= */
if (!isset($_COOKIE['site_visited'])) {
    $update = $conn->prepare("UPDATE site_views SET total_views = total_views + 1");
    $update->execute();
    setcookie('site_visited', 'yes', time() + 86400, "/");
}

$stmt = $conn->prepare("SELECT total_views FROM site_views LIMIT 1");
$stmt->execute();
$views = $stmt->fetchColumn();

/* =========================
   PAGINATION
========================= */
$limit  = 30;
$page   = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
$page   = max($page, 1);
$offset = ($page - 1) * $limit;

/* =========================
   FILTER
========================= */
$search_name   = $_GET['search_name'] ?? '';
$selected_year = $_GET['academic_year'] ?? '';

$results = [];

/* =========================
   LOAD ACADEMIC YEARS
========================= */
$year_sql = "
    SELECT DISTINCT academic_year FROM supervision_sessions WHERE academic_year IS NOT NULL
    UNION
    SELECT DISTINCT academic_year FROM quick_win WHERE academic_year IS NOT NULL
    ORDER BY academic_year DESC
";
$stmt_year = $conn->prepare($year_sql);
$stmt_year->execute();
$academic_years = $stmt_year->fetchAll(PDO::FETCH_COLUMN);

try {

    /* =========================
       COUNT TOTAL ROWS
    ========================= */
    $count_sql = "
        SELECT COUNT(DISTINCT t.t_pid)
        FROM teacher t
        WHERE (
            t.t_pid IN (
                SELECT teacher_t_pid
                FROM supervision_sessions
                WHERE deleted_at IS NULL
                " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "
            )
            OR
            t.t_pid IN (
                SELECT t_pid
                FROM quick_win
                WHERE deleted_at IS NULL
                " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "
            )
        )
    ";

    $count_params = [];

    if (!empty($search_name)) {
        $count_sql .= "
            AND (
                CONCAT(
                    IFNULL((SELECT prefix_name FROM prefix WHERE prefix_id = t.prefix_id),''),
                    t.f_name,' ',t.l_name
                ) LIKE :search
                OR
                (SELECT position_name FROM position WHERE position_id = t.position_id) LIKE :search
            )
        ";
        $count_params[':search'] = "%{$search_name}%";
    }

    if (!empty($selected_year)) {
        $count_params[':year'] = $selected_year;
    }

    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->execute($count_params);
    $total_rows  = (int)$stmt_count->fetchColumn();
    $total_pages = max(ceil($total_rows / $limit), 1);

    /* =========================
       MAIN DATA QUERY
    ========================= */
    $sql = "
        SELECT 
            t.t_pid AS teacher_t_pid,
            CONCAT(IFNULL(p.prefix_name,''), t.f_name,' ',t.l_name) AS teacher_full_name,
            pos.position_name AS teacher_position,
            s.school_name AS t_school,

            /* COUNT NORMAL */
            (
                SELECT COUNT(*)
                FROM supervision_sessions
                WHERE teacher_t_pid = t.t_pid
                AND deleted_at IS NULL
                " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "
            ) AS count_normal,

            /* COUNT QUICK WIN */
            (
                SELECT COUNT(*)
                FROM quick_win
                WHERE t_pid = t.t_pid
                AND deleted_at IS NULL
                " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "
            ) AS count_quickwin,

            /* LATEST DATE */
            GREATEST(
                IFNULL((
                    SELECT MAX(supervision_date)
                    FROM supervision_sessions
                    WHERE teacher_t_pid = t.t_pid
                    AND deleted_at IS NULL
                    " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "
                ), '0000-00-00'),
                IFNULL((
                    SELECT MAX(supervision_date)
                    FROM quick_win
                    WHERE t_pid = t.t_pid
                    AND deleted_at IS NULL
                    " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "
                ), '0000-00-00')
            ) AS latest_date

        FROM teacher t
        LEFT JOIN prefix p   ON t.prefix_id = p.prefix_id
        LEFT JOIN position pos ON t.position_id = pos.position_id
        LEFT JOIN school s   ON t.school_id = s.school_id

        WHERE (
            t.t_pid IN (
                SELECT teacher_t_pid
                FROM supervision_sessions
                WHERE deleted_at IS NULL
                " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "
            )
            OR
            t.t_pid IN (
                SELECT t_pid
                FROM quick_win
                WHERE deleted_at IS NULL
                " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "
            )
        )
    ";

    $params = [];

    if (!empty($search_name)) {
        $sql .= "
            AND (
                CONCAT(IFNULL(p.prefix_name,''), t.f_name,' ',t.l_name) LIKE :search
                OR pos.position_name LIKE :search
            )
        ";
        $params[':search'] = "%{$search_name}%";
    }

    $sql .= " ORDER BY latest_date DESC LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    if (!empty($selected_year)) {
        $stmt->bindValue(':year', $selected_year, PDO::PARAM_INT);
    }
    if (!empty($search_name)) {
        $stmt->bindValue(':search', "%{$search_name}%", PDO::PARAM_STR);
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ระบบสารสนเทศนิเทศศึกษา</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/table_teacher.css">

    <!-- ⭐ SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="container mt-4 mb-4">
        <div class="card p-4">

            <div class="text-center mb-4">
                <img src="images/banner.png" class="img-fluid rounded">
            </div>

            <div class="alert alert-warning text-center fw-bold">
                👁️ จำนวนผู้เข้าชมเว็บไซต์
                <span class="badge bg-danger fs-6"><?= number_format($views); ?></span> คน
            </div>

            <?php if (!empty($_SESSION['is_logged_in'])): ?>
                <div class="d-flex flex-wrap justify-content-end align-items-center mb-3 gap-2">

                    <a href="supervision_start.php" class="btn btn-custom btn-warning">
                        <i class="fas fa-clipboard-list me-2"></i> บันทึกการนิเทศ
                    </a>

                    <!-- ปุ่มแก้ไขข้อมูล -->
                    <?php if ($allow_supervisor_admin): ?>
                        <div class="btn-group">
                            <button class="btn btn-custom btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-edit me-1"></i> แก้ไขข้อมูล
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <a class="dropdown-item" href="my_sessions_list.php">
                                        <i class="fas fa-list text-primary me-2"></i>
                                        รายการที่ฉันบันทึก
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="trash/index.php">
                                        <i class="fas fa-trash text-danger me-2"></i>
                                        ถังขยะ
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <!-- ปุ่ม Dashboard -->
                    <div class="btn-group">
                        <button class="btn btn-custom btn-info dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-chart-pie me-1"></i> Dashboard
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <a class="dropdown-item" href="graphs/satisfaction_dashboard.php?form_type=1">
                                    <i class="fas fa-chart-line text-primary me-2"></i> การนิเทศปกติ
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="graphs/satisfaction_dashboard.php?form_type=3">
                                    <i class="fas fa-bolt text-warning me-2"></i> Quick Win
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="graphs/satisfaction_dashboard.php?form_type=personal">
                                    <i class="fas fa-chart-bar text-success me-2"></i> สถิติการนิเทศรายบุคคล
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- ปุ่มจัดการข้อมูล -->
                    <div class="btn-group">
                        <button class="btn btn-custom btn-success dropdown-toggle"
                            data-bs-toggle="dropdown">
                            <i class="fas fa-cogs me-1"></i> จัดการข้อมูล
                        </button>

                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">

                            <!-- ข้อมูลครู (ทุกคนที่ล็อกอินเข้าได้) -->
                            <li>
                                <a class="dropdown-item" href="edit_teacher_list.php">
                                    <i class="fas fa-user-edit text-primary me-2"></i> ข้อมูลครู
                                </a>
                            </li>

                            <!-- ข้อมูลผู้นิเทศ (เฉพาะ admin) -->
                            <?php if ($allow_supervisor_admin): ?>
                                <li>
                                    <a class="dropdown-item" href="edit_supervisor_list.php">
                                        <i class="fas fa-user-tie text-success me-2"></i> ข้อมูลผู้นิเทศ
                                    </a>
                                </li>
                            <?php else: ?>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="denyAccess(event)">
                                        <i class="fas fa-user-tie text-success me-2"></i> ข้อมูลผู้นิเทศ
                                    </a>
                                </li>
                            <?php endif; ?>

                        </ul>
                    </div>


                    <a href="logout.php" class="btn btn-custom btn-danger">
                        <i class="fas fa-sign-out-alt me-1"></i> ออกจากระบบ
                    </a>

                </div>
            <?php else: ?>
                <div class="d-flex justify-content-end mb-3">
                    <a href="login.php" class="btn btn-custom btn-primary">
                        <i class="fas fa-sign-in-alt me-1"></i> Login
                    </a>
                </div>
            <?php endif; ?>

            <form method="GET" class="mb-3" id="filterForm">
                <div class="row g-2">

                    <!-- ฟิลเตอร์ปีการศึกษา -->
                    <div class="col-md-2">
                        <select name="academic_year" class="form-select"
                            onchange="document.getElementById('filterForm').submit();">
                            <option value="">ทุกปีการศึกษา</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?= $year ?>" <?= ($selected_year == $year) ? 'selected' : '' ?>>
                                    ปีการศึกษา <?= $year ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ค้นหาชื่อ -->
                    <div class="col-md-8">
                        <input type="text" name="search_name" class="form-control"
                            placeholder="ค้นหาครู..." value="<?= htmlspecialchars($search_name) ?>">
                    </div>

                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-warning w-100"><i class="fas fa-search"></i></button>
                        <a href="index.php" class="btn btn-secondary w-100"><i class="fas fa-redo"></i></a>
                    </div>

                </div>
            </form>


            <div class="table-responsive" id="search-results">
                <table class="table table-hover align-middle teacher-table">
                    <thead>
                        <tr>
                            <th>ชื่อผู้รับนิเทศ</th>
                            <th>โรงเรียน</th>
                            <th>ตำแหน่ง</th>
                            <th class="text-center">จำนวนครั้ง</th>
                            <th class="text-center">ดูข้อมูล</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-danger">ไม่พบข้อมูล</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td data-label="ชื่อผู้รับนิเทศ"><?= htmlspecialchars($row['teacher_full_name']) ?></td>
                                    <td data-label="โรงเรียน"><?= htmlspecialchars($row['t_school']) ?></td>
                                    <td data-label="ตำแหน่ง"><?= htmlspecialchars($row['teacher_position']) ?></td>
                                    <td data-label="จำนวนครั้ง" class="text-center">
                                        <span class="badge bg-warning">
                                            <?= $row['count_normal'] + $row['count_quickwin'] ?>
                                        </span>
                                    </td>
                                    <td data-label="ดูข้อมูล" class="text-center">
                                        <form action="session_details.php" method="POST" class="d-inline">
                                            <input type="hidden" name="teacher_pid" value="<?= $row['teacher_t_pid'] ?>">
                                            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>">
                                            <button class="btn btn-info btn-sm btn-custom px-3">
                                                <i class="fas fa-eye"></i> ดูประวัติ
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=1&search_name=<?= urlencode($search_name) ?>#search-results">หน้าแรก</a>
                        </li>

                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&search_name=<?= urlencode($search_name) ?>#search-results">ก่อนหน้า</a>
                        </li>

                        <?php
                        // แสดงเลขหน้าแบบจำกัดช่วง (เพื่อไม่ให้ยาวเกินไป)
                        $range = 2;
                        for ($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search_name=<?= urlencode($search_name) ?>#search-results"><?= $i ?></a>
                                </li>
                            <?php elseif ($i == $page - $range - 1 || $i == $page + $range + 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif;
                        endfor; ?>

                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&search_name=<?= urlencode($search_name) ?>#search-results">ถัดไป</a>
                        </li>
                    </ul>
                </nav>
                <div class="text-center text-muted small mt-2">
                    หน้า <?= $page ?> จาก <?= $total_pages ?> (รวมทั้งหมด <?= number_format($total_rows) ?> รายการ)
                </div>
            <?php endif; ?>

        </div>
    </div>
    </div>
    </div>

    <?php include 'footer.php'; ?>
    <?php include 'includes/alert.php'; ?>
    <script>
        function denyAccess(e) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'ไม่มีสิทธิ์เข้าใช้งาน',
                html: 'ระบบนี้เป็นสิทธิการใช้งานของแอดมินเท่านั้น<br>กรุณาติดต่อแอดมิน',
                confirmButtonText: 'ตกลง'
            });
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>