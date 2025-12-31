<?php
session_start();
require_once 'config/db_connect.php';

// --- ส่วนโลจิกนับจำนวนผู้เข้าชม (คงเดิม) ---
if (!isset($_COOKIE['site_visited'])) {
    $update = $conn->prepare("UPDATE site_views SET total_views = total_views + 1");
    $update->execute();
    setcookie('site_visited', 'yes', time() + 86400, "/");
}

$stmt = $conn->prepare("SELECT total_views FROM site_views LIMIT 1");
$stmt->execute();
$views = $stmt->fetchColumn();

if (!isset($_SESSION['visited'])) {
    unset($_SESSION['is_logged_in']);
    unset($_SESSION['user_id']);
    $_SESSION['visited'] = true;
}

// --- ส่วนการตั้งค่า Pagination (เพิ่มเติม) ---
$limit = 50; // จำนวนรายการต่อหน้า
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$search_name = $_GET['search_name'] ?? '';
$results = [];

try {
    // 1. หาจำนวนรายการทั้งหมดสำหรับแบ่งหน้า
    $count_sql = "SELECT COUNT(t.t_pid) FROM teacher t 
                  WHERE (t.t_pid IN (SELECT teacher_t_pid FROM supervision_sessions) 
                  OR t.t_pid IN (SELECT t_pid FROM quick_win))";
    $count_params = [];

    if (!empty($search_name)) {
        $count_sql .= " AND (CONCAT(IFNULL((SELECT prefix_name FROM prefix WHERE prefix_id = t.prefix_id),''), t.f_name, ' ', t.l_name) LIKE :search 
                         OR (SELECT position_name FROM position WHERE position_id = t.position_id) LIKE :search)";
        $count_params[':search'] = "%$search_name%";
    }

    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->execute($count_params);
    $total_rows = $stmt_count->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    // 2. ดึงข้อมูลครู (เพิ่ม LIMIT และ OFFSET)
    $sql = "SELECT 
                t.t_pid AS teacher_t_pid,
                CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_full_name,
                pos.position_name AS teacher_position,
                s.school_name AS t_school,
                (SELECT COUNT(*) FROM supervision_sessions WHERE teacher_t_pid = t.t_pid) AS count_normal,
                (SELECT COUNT(*) FROM quick_win WHERE t_pid = t.t_pid) AS count_quickwin,
                GREATEST(
                    IFNULL((SELECT MAX(supervision_date) FROM supervision_sessions WHERE teacher_t_pid = t.t_pid), '0000-00-00'),
                    IFNULL((SELECT MAX(supervision_date) FROM quick_win WHERE t_pid = t.t_pid), '0000-00-00')
                ) AS latest_date
            FROM teacher t
            LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
            LEFT JOIN school s ON t.school_id = s.school_id
            LEFT JOIN position pos ON t.position_id = pos.position_id
            WHERE (
                t.t_pid IN (SELECT teacher_t_pid FROM supervision_sessions)
                OR 
                t.t_pid IN (SELECT t_pid FROM quick_win)
            )";

    $params = [];
    if (!empty($search_name)) {
        $search_term = "%" . $search_name . "%";
        $sql .= " AND (CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) LIKE :search 
                 OR pos.position_name LIKE :search)";
        $params[':search'] = $search_term;
    }

    $sql .= " ORDER BY latest_date DESC LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    // ใช้ bindValue เพื่อให้ Limit/Offset ทำงานถูกต้องใน PDO
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    if (!empty($search_name)) {
        $stmt->bindValue(':search', $search_term, PDO::PARAM_STR);
    }

    $stmt->execute();
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาด: ' . $e->getMessage() . '</div>';
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
                                <a class="dropdown-item" href="graphs/supervisor_personal_stats_chart.php?form_type=4">
                                    <i class="fas fa-chart-bar text-success me-2"></i> สถิติการนิเทศรายบุคคล
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- ปุ่มจัดการข้อมูล -->
                    <div class="btn-group">
                        <button class="btn btn-custom btn-success dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-cogs me-1"></i> จัดการข้อมูล
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            <li>
                                <a class="dropdown-item" href="edit_teacher_list.php">
                                    <i class="fas fa-user-edit text-primary me-2"></i> ข้อมูลครู
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="edit_supervisor_list.php">
                                    <i class="fas fa-user-tie text-success me-2"></i> ข้อมูลผู้นิเทศ
                                </a>
                            </li>
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

            <form method="GET" class="mb-3">
                <div class="input-group">
                    <input type="text" name="search_name" class="form-control"
                        placeholder="ค้นหาครู..." value="<?= htmlspecialchars($search_name) ?>">
                    <button class="btn btn-warning"><i class="fas fa-search"></i></button>
                    <a href="index.php" class="btn btn-secondary"><i class="fas fa-redo"></i></a>
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
                                    <td><?= htmlspecialchars($row['teacher_full_name']) ?></td>
                                    <td><?= htmlspecialchars($row['t_school']) ?></td>
                                    <td><?= htmlspecialchars($row['teacher_position']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-warning">
                                            <?= $row['count_normal'] + $row['count_quickwin'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <form action="session_details.php" method="POST">
                                            <input type="hidden" name="teacher_pid" value="<?= $row['teacher_t_pid'] ?>">
                                            <button class="btn btn-info btn-sm">
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

    <!-- ⭐ SweetAlert Flash Message -->
    <?php if (!empty($_SESSION['flash_message'])): ?>
        <script>
            Swal.fire({
                icon: "<?= $_SESSION['flash_type'] === 'danger' ? 'error' : $_SESSION['flash_type'] ?>",
                title: <?= json_encode(
                            $_SESSION['flash_type'] === 'success' ? '🎉 สำเร็จ'
                                : ($_SESSION['flash_type'] === 'warning' ? '⚠️ แจ้งเตือน' : '❌ เกิดข้อผิดพลาด')
                        ) ?>,
                text: <?= json_encode($_SESSION['flash_message']) ?>,
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: "ตกลง",
                allowOutsideClick: false
            });
        </script>
    <?php
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    endif;
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>