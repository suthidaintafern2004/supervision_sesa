<?php
// supervision_start.php
session_start();
// ตรวจสิทธิ์: ถ้าไม่ล็อกอิน ให้ไปหน้า login
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    // แสดงลิงก์ไปหน้า login (ไม่ใช้ die() แบบดิบเพื่อ UX ที่ดีกว่า)
    header("Location: login.php");
    exit();
}

// โหลดข้อมูลผู้นิเทศจาก session
$supervisor_pid      = $_SESSION['user_id'];
$supervisor_name     = $_SESSION['user_name'] ?? '-';
$supervisor_office   = $_SESSION['office_name'] ?? '-';
$supervisor_position = $_SESSION['position_name'] ?? '-';
$supervisor_rank     = $_SESSION['rank_name'] ?? '-';

// กรณีแก้ไข (edit) ให้โหลด inspection_data จาก session ถ้ามี
if (isset($_GET['edit']) && $_GET['edit'] === 'true' && isset($_SESSION['inspection_data'])) {
    $inspection_data = $_SESSION['inspection_data'];
} else {
    $inspection_data = null;

    if (isset($_GET['edit']) && $_GET['edit'] === 'true') {
        $inspection_data = $_SESSION['inspection_data'] ?? null;
    } else {
        // เข้าใหม่ → ล้างข้อมูลเก่า
        unset($_SESSION['inspection_data']);
    }
}

// require DB ถ้าต้องการใช้ fetch ครู (teacher.php จะเรียก fetch_teacher.php)
require_once 'config/db_connect.php';

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>แบบบันทึกข้อมูลนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .display-field {
            background: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="container my-4">
        <div class="main-card card">
            <div class="form-header card-header text-center">
                <i class="fas fa-file-alt"></i>
                <span class="fw-bold">แบบบันทึกข้อมูลผู้นิเทศ และ ผู้รับนิเทศ</span>
            </div>

            <form method="POST" action="summary.php" enctype="multipart/form-data" onsubmit="return validateSelection(event)">

                <!-- A: ข้อมูลผู้นิเทศ (ดึงจาก session) -->
                <!-- A: ข้อมูลผู้นิเทศ -->
                <div class="card-body mt-2">
                    <h5 class="card-title fw-bold text-primary">
                        <i class="fas fa-user-tie"></i> ข้อมูลผู้นิเทศ
                    </h5>
                    <hr>

                    <div class="row g-3">

                        <!-- ชื่อผู้นิเทศ -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ชื่อผู้นิเทศ</label>
                            <input type="text" class="form-control display-field"
                                value="<?= htmlspecialchars($supervisor_name); ?>" readonly>
                            <input type="hidden" name="s_p_id"
                                value="<?= htmlspecialchars($supervisor_pid); ?>">
                        </div>

                        <!-- เลขประจำตัวประชาชน -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">เลขประจำตัวประชาชน</label>
                            <input type="text" id="display_pid"
                                class="form-control display-field"
                                value="<?= htmlspecialchars($supervisor_pid); ?>" readonly>
                        </div>

                        <!-- สังกัด -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">สังกัด</label>
                            <input type="text" id="display_office"
                                class="form-control display-field"
                                value="<?= htmlspecialchars($supervisor_office); ?>" readonly>
                        </div>

                        <!-- ตำแหน่ง / วิทยฐานะ -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">ตำแหน่ง / วิทยฐานะ</label>
                            <input type="text" id="display_position"
                                class="form-control display-field"
                                value="<?= htmlspecialchars($supervisor_position . ' / ' . $supervisor_rank); ?>" readonly>
                        </div>

                    </div>
                </div>


                <!-- B: ส่วนข้อมูลผู้รับนิเทศ (ยังคงใช้ teacher.php ของคุณ ที่มีระบบค้นหา) -->
                <?php
                $teacher_name = '';
                $teacher_pid  = '';

                if (!empty($inspection_data)) {
                    $teacher_name = $inspection_data['teacher_name'] ?? '';
                    $teacher_pid  = $inspection_data['t_pid'] ?? '';
                }
                require 'teacher.php'; ?>

                <div class="card-body">
                    <div class="row g-3 mt-4 justify-content-center">
                        <div class="mt-4 mb-4">
                            <?php require_once 'forms/form_selector.php'; ?>
                        </div>

                        <div class="col-auto">
                            <a href="index.php" class="btn btn-danger">
                                <i class="fas fa-arrow-left"></i> ย้อนกลับ
                            </a>
                        </div>

                        <div class="col-auto">
                            <button type="submit" class="btn btn-success btn-l">
                                ดำเนินการต่อ
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateSelection(e) {
            const teacherName = document.getElementById('teacher_name_input')?.value.trim() || '';
            const teacherPid = document.getElementById('t_pid')?.value.trim() || '';
            const formType = document.querySelector('input[name="form_type"]:checked');

            let msg = '';

            if (teacherName === '' || teacherPid === '') {
                msg += '- กรุณาเลือก "ผู้รับนิเทศ" จากรายชื่อที่ระบบแนะนำ\n';
            }

            if (!formType) {
                msg += '- กรุณาเลือก "แบบฟอร์มการนิเทศ"\n';
            }

            if (msg !== '') {
                alert('ข้อมูลไม่ครบถ้วน:\n' + msg);
                e.preventDefault();
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {

            const isEdit = new URLSearchParams(window.location.search).get('edit');
            const nameInput = document.getElementById('teacher_name_input');
            const pidInput = document.getElementById('t_pid');

            // 👉 ล้างค่าเฉพาะ "เพิ่มใหม่"
            if (isEdit !== 'true') {
                if (nameInput) nameInput.value = '';
                if (pidInput) pidInput.value = '';
            }

            // 👉 ค่อย init ระบบค้นหาครู หลังจากจัดการค่าแล้ว
            if (typeof initTeacherSearch === 'function') {
                initTeacherSearch();
            }

        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php
    if (
        isset($_SESSION['flash_message'], $_SESSION['flash_from']) &&
        $_SESSION['flash_from'] === 'quickwin_duplicate'
    ):
    ?>
        <script>
            Swal.fire({
                icon: <?= json_encode($_SESSION['flash_type'] ?? 'warning') ?>,
                title: 'แจ้งเตือน',
                text: <?= json_encode($_SESSION['flash_message']) ?>,
                confirmButtonText: 'รับทราบ'
            });
        </script>
    <?php
        // ล้างให้จบในรอบเดียว
        unset(
            $_SESSION['flash_message'],
            $_SESSION['flash_type'],
            $_SESSION['flash_from']
        );
    endif;
    ?>



    <?php include 'footer.php'; ?>
</body>

</html>