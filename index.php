<?php
session_start();

// เชื่อมต่อฐานข้อมูล
require_once 'config/db_connect.php';

/* =========================
   ROLE & SESSION CHECK
========================= */
$allow_supervisor_admin = (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
);

// ดึงชื่อผู้ใช้จาก Session (ปรับเปลี่ยน key ตามที่คุณใช้ในระบบจริง เช่น f_name)
$user_display_name = $_SESSION['f_name'] ?? $_SESSION['user_name'] ?? 'ผู้ใช้งาน';

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
   FILTER & SEARCH
========================= */
$search_name   = $_GET['search_name'] ?? '';
$selected_year = $_GET['academic_year'] ?? '';

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
            t.t_pid IN (SELECT t_pid FROM supervision_sessions WHERE deleted_at IS NULL " . (!empty($selected_year) ? " AND academic_year = :year " : "") . ")
            OR
            t.t_pid IN (SELECT t_pid FROM quick_win WHERE deleted_at IS NULL " . (!empty($selected_year) ? " AND academic_year = :year " : "") . ")
        )
    ";

    $count_params = [];
    if (!empty($search_name)) {
        $count_sql .= " AND (CONCAT(IFNULL((SELECT prefix_name FROM prefix WHERE prefix_id = t.prefix_id),''), t.f_name,' ',t.l_name) LIKE :search OR (SELECT position_name FROM position WHERE position_id = t.position_id) LIKE :search)";
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
            (SELECT COUNT(*) FROM supervision_sessions WHERE t_pid = t.t_pid AND deleted_at IS NULL " . (!empty($selected_year) ? " AND academic_year = :year " : "") . ") AS count_normal,
            (SELECT COUNT(*) FROM quick_win WHERE t_pid = t.t_pid AND deleted_at IS NULL " . (!empty($selected_year) ? " AND academic_year = :year " : "") . ") AS count_quickwin,
            GREATEST(
                IFNULL((SELECT MAX(supervision_date) FROM supervision_sessions WHERE t_pid = t.t_pid AND deleted_at IS NULL " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "), '0000-00-00'),
                IFNULL((SELECT MAX(supervision_date) FROM quick_win WHERE t_pid = t.t_pid AND deleted_at IS NULL " . (!empty($selected_year) ? " AND academic_year = :year " : "") . "), '0000-00-00')
            ) AS latest_date
        FROM teacher t
        LEFT JOIN prefix p   ON t.prefix_id = p.prefix_id
        LEFT JOIN position pos ON t.position_id = pos.position_id
        LEFT JOIN school s   ON t.school_id = s.school_id
        WHERE (
            t.t_pid IN (SELECT t_pid FROM supervision_sessions WHERE deleted_at IS NULL " . (!empty($selected_year) ? " AND academic_year = :year " : "") . ")
            OR
            t.t_pid IN (SELECT t_pid FROM quick_win WHERE deleted_at IS NULL " . (!empty($selected_year) ? " AND academic_year = :year " : "") . ")
        )
    ";

    if (!empty($search_name)) {
        $sql .= " AND (CONCAT(IFNULL(p.prefix_name,''), t.f_name,' ',t.l_name) LIKE :search OR pos.position_name LIKE :search)";
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
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>ระบบสารสนเทศการนิเทศการศึกษา</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/table_teacher.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-light">

    <?php include 'navbar.php'; ?>

    <div class="container mt-4 mb-5">
        <div class="card border-0 shadow-sm p-4">

            <div class="row justify-content-center mb-4">
                <div class="col-12 col-lg-10">
                    <div class="text-center">
                        <img src="images/banner.png"
                            class="img-fluid rounded shadow-sm"
                            style="width: 100%; max-height: 250px; object-fit: contain; background-color: #fff;">
                    </div>
                </div>
            </div>
            <div class="alert alert-warning text-center fw-bold py-2 shadow-sm border-0">
                👁️ จำนวนผู้เข้าชมเว็บไซต์
                <span class="badge bg-danger fs-6 ms-2"><?= number_format($views); ?></span> คน
            </div>

            <form method="GET" class="mb-4" id="filterForm">
                <div class="row g-2">
                    <div class="col-md-2">
                        <select name="academic_year" class="form-select border-primary" onchange="this.form.submit();">
                            <option value="">ทุกปีการศึกษา</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?= $year ?>" <?= ($selected_year == $year) ? 'selected' : '' ?>>ปีการศึกษา <?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <input type="text" name="search_name" class="form-control border-primary" placeholder="ค้นหาชื่อครู หรือโรงเรียนที่สังกัด" value="<?= htmlspecialchars($search_name) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
                        <a href="index.php" class="btn btn-secondary w-100"><i class="fas fa-sync"></i></a>
                    </div>
                </div>
            </form>

            <div class="table-responsive" id="search-results">
                <table class="table table-hover align-middle teacher-table">
                    <thead class="table-light">
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
                                <td colspan="5" class="text-center text-danger py-4">ไม่พบข้อมูลที่ค้นหา</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results as $row): ?>
                                <tr>
                                    <td class="text-center"><strong><?= htmlspecialchars($row['teacher_full_name']) ?></strong></td>
                                    <td class="text-center"><?= htmlspecialchars($row['t_school']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['teacher_position']) ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-warning text-dark px-3">
                                            <?= $row['count_normal'] + $row['count_quickwin'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <form action="session_details.php" method="POST">
                                            <input type="hidden" name="teacher_pid" value="<?= $row['teacher_t_pid'] ?>">
                                            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($selected_year) ?>">
                                            <button class="btn btn-info btn-sm px-3 text-white rounded-pill shadow-sm">
                                                <i class="fas fa-eye me-1"></i> ดูประวัติ
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
                <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $queryString = http_build_query(['search_name' => $search_name, 'academic_year' => $selected_year]);
                    ?>
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $queryString ?>">ก่อนหน้า</a>
                    </li>

                    <?php
                    $window = 2;
                    for ($i = 1; $i <= $total_pages; $i++):
                        if ($i == 1 || $i == $total_pages || ($i >= $page - $window && $i <= $page + $window)):
                    ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&<?= $queryString ?>"><?= $i ?></a>
                            </li>
                    <?php
                        elseif ($i == $page - $window - 1 || $i == $page + $window + 1):
                    ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php
                        endif;
                    endfor;
                    ?>

                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $queryString ?>">ถัดไป</a>
                    </li>
                </ul>
                </nav>
            <?php endif; ?>

        </div>
    </div>

    <?php include 'footer.php'; ?>
    <?php include 'includes/alert.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>