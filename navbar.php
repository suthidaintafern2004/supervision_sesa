<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$allow_supervisor_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_supervisor_or_admin = (isset($_SESSION['role']) && in_array($_SESSION['role'], ['supervisor', 'admin']));
$user_display_name = $_SESSION['f_name'] ?? $_SESSION['user_name'] ?? 'ผู้ใช้งาน';
$nav_prefix = $nav_prefix ?? '';
?>
<style>
    :root {
        --primary-color: #ff8c42; /* เปลี่ยนสีเป็นโทนเดียวกับ Footer และเว็บไซต์ */
    }

    .navbar-custom {
        background-color: var(--primary-color);
    }

    .offcanvas-header {
        background-color: var(--primary-color);
        color: white;
    }

    .nav-link {
        color: #333;
        transition: 0.3s;
        padding: 10px 15px !important;
        border-radius: 8px;
    }

    .nav-link:hover {
        background-color: #f0f4f8;
        color: var(--primary-color);
    }

    .user-profile {
        font-size: 0.9rem;
    }
</style>

<nav class="navbar navbar-dark navbar-custom sticky-top shadow">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <?php if ($is_supervisor_or_admin): ?>
                <button class="navbar-toggler border-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu">
                    <span class="navbar-toggler-icon"></span>
                </button>
            <?php endif; ?>
        <a class="navbar-brand fw-bold d-none d-sm-block" href="<?= $nav_prefix ?>index.php">ระบบสารสนเทศการนิเทศ</a>
        <a class="navbar-brand fw-bold d-block d-sm-none" href="<?= $nav_prefix ?>index.php">Supervision</a>
        </div>

        <div class="d-flex align-items-center">
            <?php if (!empty($_SESSION['is_logged_in'])): ?>
                <div class="text-white me-3 d-none d-md-block user-profile">
                    <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($user_display_name) ?>
                </div>
            <?php endif; ?>

            <a href="<?= $nav_prefix ?>manual.php" class="btn btn-outline-light btn-sm rounded-pill me-3 d-none d-md-block">
                <i class="fas fa-book"></i> คู่มือการใช้งาน
            </a>
            
            <?php if (!empty($_SESSION['is_logged_in'])): ?>
                <a href="<?= $nav_prefix ?>logout.php" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                </a>
            <?php else: ?>
                <a href="<?= $nav_prefix ?>login.php" class="btn btn-light btn-sm fw-bold px-3">เข้าสู่ระบบ</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if ($is_supervisor_or_admin): ?>
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarMenu">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title"><i class="fas fa-bars me-2"></i>MENU</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush mt-3">
            <a href="<?= $nav_prefix ?>index.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                <i class="fas fa-home me-2 text-primary"></i> หน้าแรก
            </a>
            <a href="<?= $nav_prefix ?>manual.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                <i class="fas fa-book me-2 text-success"></i> คู่มือการใช้งาน
            </a>
            <a href="<?= $nav_prefix ?>supervision_start.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                <i class="fas fa-clipboard-list me-2 text-warning"></i> บันทึกการนิเทศ
            </a>

            <div class="px-4 py-2 mt-2 small text-muted text-uppercase fw-bold">Dashboard & สถิติ</div>
            <a href="<?= $nav_prefix ?>graphs/satisfaction_dashboard.php?form_type=1" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                <i class="fas fa-chart-line me-2 text-info"></i> Classroom
            </a>
            <a href="<?= $nav_prefix ?>graphs/satisfaction_dashboard.php?form_type=3" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                <i class="fas fa-bolt me-2 text-warning"></i> Quick Win
            </a>
            <a href="<?= $nav_prefix ?>graphs/web_satisfaction_dashboard.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                <i class="fas fa-star me-2 text-primary"></i> ความพึงพอใจระบบเว็บ
            </a>

            <div class="px-4 py-2 mt-2 small text-muted text-uppercase fw-bold">จัดการข้อมูล</div>
            <a href="<?= $nav_prefix ?>edit_teacher_list.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                <i class="fas fa-user-edit me-2 text-primary"></i> ข้อมูลครู
            </a>

            <?php if ($allow_supervisor_admin): ?>
                <a href="<?= $nav_prefix ?>edit_supervisor_list.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-user-tie me-2 text-success"></i> ข้อมูลศึกษานิเทศ (Admin)
                </a>
                <a href="<?= $nav_prefix ?>edit_quickwin_options.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-list-check me-2 text-info"></i> จัดการหัวข้อ Quick Win (Admin)
                </a>
                <a href="<?= $nav_prefix ?>edit_certificate_template.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-certificate me-2 text-warning"></i> จัดการแม่แบบเกียรติบัตร (Admin)
                </a>
            <?php else: ?>
                <a href="#" onclick="denyAccess(event)" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-user-tie me-2 text-muted"></i> ข้อมูลศึกษานิเทศ (Admin)
                </a>
                <a href="#" onclick="denyAccess(event)" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-list-check me-2 text-muted"></i> จัดการหัวข้อ Quick Win (Admin)
                </a>
                <a href="#" onclick="denyAccess(event)" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-certificate me-2 text-muted"></i> จัดการแม่แบบเกียรติบัตร (Admin)
                </a>
            <?php endif; ?>

            <div class="px-4 py-2 mt-2 small text-muted text-uppercase fw-bold">จัดการประวัติ</div>
            <?php if ($allow_supervisor_admin): ?>
                <a href="<?= $nav_prefix ?>my_sessions_list.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-history me-2 text-info"></i> รายการที่บันทึก (Admin)
                </a>
                <a href="<?= $nav_prefix ?>trash/index.php" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-trash me-2 text-danger"></i> ถังขยะ (Admin)
                </a>
            <?php else: ?>
                <a href="#" onclick="denyAccess(event)" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-history me-2 text-muted"></i> รายการที่บันทึก (Admin)
                </a>
                <a href="#" onclick="denyAccess(event)" class="list-group-item list-group-item-action border-0 nav-link mx-2">
                    <i class="fas fa-trash me-2 text-muted"></i> ถังขยะ (Admin)
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function denyAccess(e) {
        e.preventDefault();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'ไม่มีสิทธิ์เข้าใช้งาน',
                    html: 'สิทธิ์การใช้งานสำหรับแอดมินเท่านั้น<br>หากต้องการใช้งานกรุณาติดต่อแอดมิน',
                confirmButtonText: 'รับทราบ'
            });
        } else {
                alert('ไม่มีสิทธิ์เข้าใช้งาน ส่วนนี้สำหรับแอดมินเท่านั้น\nหากต้องการใช้งานกรุณาติดต่อแอดมิน');
        }
    }
</script>