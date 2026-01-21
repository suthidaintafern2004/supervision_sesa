<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*************************************************
 * summary.php
 * ตัวกลางเตรียมข้อมูลการนิเทศ (Controller)
 *************************************************/

session_start();
require_once 'config/db_connect.php';

/* =====================================================
 A) รับ flash message (ถ้ามี)
===================================================== */
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

/* =====================================================
 B) รับ POST จาก supervision_start.php
===================================================== */
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $selected_form = $_POST['form_type'] ?? null;
    $t_pid         = trim($_POST['t_pid'] ?? '');
    $teacher_name  = trim($_POST['teacher_name'] ?? '');

    if (!$selected_form || !$t_pid) {
        $error_message = 'ข้อมูลไม่ครบถ้วน กรุณาเลือกผู้รับนิเทศและแบบฟอร์ม';
    } else {

        $supervisor_pid  = $_SESSION['user_id'] ?? null;
        $supervisor_name = $_SESSION['user_name'] ?? null;

        if (!$supervisor_pid || !$supervisor_name) {
            $error_message = 'ไม่พบข้อมูลผู้นิเทศ กรุณาเข้าสู่ระบบใหม่';
        } else {

            /* =========================================
               C) ดึงข้อมูลครู + โรงเรียน + กลุ่มสาระ
            ========================================= */
            $sql = "
                    SELECT 
                        CONCAT(
                            IFNULL(p.prefix_name, ''),
                            IFNULL(t.f_name, ''),
                            ' ',
                            IFNULL(t.l_name, '')
                        ) AS teacher_name,
                        s.school_name,
                        sg.subjectgroup_name
                    FROM teacher t
                    LEFT JOIN prefix p 
                        ON t.prefix_id = p.prefix_id
                    LEFT JOIN school s 
                        ON t.school_id = s.school_id
                    LEFT JOIN subject_group sg ON t.subjectgroup_id = sg.subjectgroup_id
                    WHERE t.t_pid = :t_pid
                    LIMIT 1
                ";
            $stmt = $conn->prepare($sql);
            $stmt->execute(['t_pid' => $t_pid]);
            $teacherInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$teacherInfo) {
                $error_message = 'ไม่พบข้อมูลครูในระบบ';
            } else {

                /* =========================================
                   D) เก็บ context กลางไว้ใน session
                ========================================= */
                $_SESSION['inspection_data'] = [
                    't_pid'           => $t_pid,
                    'teacher_name'    => $teacherInfo['teacher_name'],
                    'school_name'     => $teacherInfo['school_name'],
                    'subject_name'    => $teacherInfo['subjectgroup_name'],
                    'supervisor_pid'  => $supervisor_pid,
                    'supervisor_name' => $supervisor_name,
                    'form_type'       => $selected_form
                ];

                /* =========================================
                   E) Redirect ตามฟอร์มที่เลือก
                ========================================= */
                switch ($selected_form) {
                    case 'quickwin_form':

                        /* =========================
                        ฟังก์ชันคำนวณปีการศึกษา
                        ========================= */
                        function getAcademicYear($date)
                        {
                            $year  = (int)date('Y', strtotime($date));
                            $month = (int)date('m', strtotime($date));
                            return ($month < 5) ? $year - 1 : $year;
                        }

                        $academic_year = getAcademicYear(date('Y-m-d'));

                        /* =========================
                        ตรวจ Quick Win ซ้ำ
                        ========================= */
                        $sql = "
                                SELECT 1
                                FROM quick_win
                                WHERE t_pid = :t_pid
                                AND (
                                        CASE
                                            WHEN MONTH(supervision_date) < 5
                                            THEN YEAR(supervision_date) - 1
                                            ELSE YEAR(supervision_date)
                                        END
                                    ) = :year
                                LIMIT 1
                            ";

                        $stmt = $conn->prepare($sql);
                        $stmt->execute([
                            ':t_pid' => $t_pid,
                            ':year'  => $academic_year
                        ]);

                        // ❌ ถ้าซ้ำ → กลับหน้าเลือกครู + แจ้งเตือน
                        if ($stmt->fetch()) {

                            $_SESSION['flash_message'] =
                                "ครูท่านนี้ได้รับการนิเทศ Quick Win ปีการศึกษา {$academic_year} แล้ว";
                            $_SESSION['flash_type'] = 'warning';
                            $_SESSION['flash_from'] = 'quickwin_duplicate';

                            header("Location: supervision_start.php?edit=true");
                            exit();
                        }

                        // ✅ ไม่ซ้ำ → เข้า Quick Win
                        header("Location: forms/quickwin_form.php");
                        exit();


                    case 'kpi_form':
                        $_SESSION['inspection_data']['render_in_summary'] = true;
                        break;

                    default:
                        $error_message = 'แบบฟอร์มที่เลือกไม่ถูกต้อง';
                }
            }
        }
    }
}

/* =====================================================
 F) ตรวจ session ก่อนแสดงหน้า
===================================================== */
$inspection_data = $_SESSION['inspection_data'] ?? null;

if (
    !$inspection_data ||
    empty($inspection_data['t_pid']) ||
    empty($inspection_data['supervisor_pid'])
) {
    $error_message = $error_message ?: 'ไม่พบข้อมูลการนิเทศ กรุณาเริ่มจากหน้าแรก';
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>บันทึกการนิเทศการสอน (KPI)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-light">

    <?php if ($flash_error): ?>
        <script>
            Swal.fire({
                title: <?= json_encode($flash_error['title']) ?>,
                text: <?= json_encode($flash_error['text']) ?>,
                icon: <?= json_encode($flash_error['icon']) ?>,
                confirmButtonText: 'รับทราบ'
            });
        </script>
    <?php endif; ?>

    <div class="container my-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>
                    แบบบันทึกการนิเทศการสอน (KPI)
                </h4>
            </div>

            <div class="card-body p-4">

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger text-center">
                        <h5>พบข้อผิดพลาด</h5>
                        <p><?= htmlspecialchars($error_message) ?></p>
                        <a href="supervision_start.php" class="btn btn-danger">กลับหน้าเริ่มต้น</a>
                    </div>
                <?php else: ?>
                    <?php include 'forms/kpi_form.php'; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>



</body>

</html>