<?php
// ===============================
// session_details.php (PHP ONLY)
// ===============================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Bangkok');

require_once __DIR__ . '/config/db_connect.php';

/* ===============================
   ตรวจสิทธิ์
=============================== */
$is_supervisor = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

/* ===============================
   รับค่า teacher_pid
=============================== */
$teacher_pid = $_GET['teacher_pid'] ?? $_POST['teacher_pid'] ?? null;
$academic_year = $_GET['academic_year'] ?? $_POST['academic_year'] ?? '';

// ⭐ ป้องกันค่าปีการศึกษาที่ไม่ใช่ตัวเลข
if (!empty($academic_year) && !ctype_digit((string)$academic_year)) {
    $academic_year = '';
}

if (!$teacher_pid) {
    die('<div class="alert alert-danger mt-5 text-center">ไม่พบรหัสครู</div>');
}

$teacher_info = null;
$results      = [];

try {

    /* ===============================
       1) ข้อมูลครู
    =============================== */
    $sql_teacher = "
        SELECT 
            CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_full_name,
            s.school_name AS SchoolName,
            pos.position_name AS teacher_position,
            sg.subjectgroup_name
        FROM teacher t
        LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
        LEFT JOIN school s ON t.school_id = s.school_id
        LEFT JOIN position pos ON t.position_id = pos.position_id
        LEFT JOIN subject_group sg ON t.subjectgroup_id = sg.subjectgroup_id
        WHERE t.t_pid = :pid
    ";

    $stmt = $conn->prepare($sql_teacher);
    $stmt->execute([':pid' => $teacher_pid]);
    $teacher_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$teacher_info) {
        die('<div class="alert alert-danger mt-5 text-center">ไม่พบข้อมูลครู</div>');
    }

    /* ===============================
       2) ประวัติการนิเทศ (Normal + Quick Win)
    =============================== */
    $sql_history = "
        SELECT * FROM (

            /* ===== NORMAL ===== */
            SELECT 
                ss.p_id AS supervisor_p_id,
                ss.t_pid AS teacher_t_pid,
                ss.subject_code,
                ss.inspection_time,
                'normal' AS session_type,
                ss.supervision_date,
                ss.inspection_time AS time_info,
                ss.subject_name AS topic,
                CONCAT(IFNULL(p.prefix_name,''), s.fname, ' ', s.lname) AS supervisor_full_name,
                ss.academic_year,

                (
                    SELECT COUNT(*)
                    FROM kpi_answers ka
                    WHERE ka.p_id = ss.p_id
                      AND ka.t_pid   = ss.t_pid
                      AND ka.subject_code    = ss.subject_code
                      AND ka.inspection_time = ss.inspection_time
                ) AS kpi_count,

                /* status: ประเมินแล้วหรือยัง */
                (CASE WHEN EXISTS (
                    SELECT 1
                    FROM satisfaction_answers sa
                    WHERE sa.p_id = ss.p_id
                      AND sa.t_pid   = ss.t_pid
                      AND sa.subject_code    = ss.subject_code
                      AND sa.inspection_time = ss.inspection_time
                      AND sa.academic_year   = ss.academic_year
                ) THEN 1 ELSE 0 END) AS status,

                NULL AS qw_t_id,
                NULL AS qw_p_id,
                NULL AS qw_date

            FROM supervision_sessions ss
            LEFT JOIN supervisor s ON ss.p_id = s.p_id
            LEFT JOIN prefix p ON s.prefix_id = p.prefix_id
            WHERE ss.t_pid = :pid1
                AND ss.deleted_at IS NULL
                AND (:year = '' OR ss.academic_year = :year)

            UNION ALL

            /* ===== QUICK WIN ===== */
                SELECT
                    NULL AS supervisor_p_id,
                    qw.t_pid AS teacher_t_pid,
                    NULL AS subject_code,
                    NULL AS inspection_time,
                    'quickwin' AS session_type,
                    qw.supervision_date,
                    '-' AS time_info,
                    'การประเมินจุดเน้น' AS topic,
                    CONCAT(IFNULL(p.prefix_name,''), s.fname, ' ', s.lname) AS supervisor_full_name,
                    qw.academic_year,
                    0 AS kpi_count,

                    /* ✅ ตรวจจากตารางคำตอบจริง */
                    (CASE WHEN EXISTS (
                        SELECT 1
                        FROM quickwin_satisfaction_answers saq
                        WHERE saq.t_pid = qw.t_pid
                        AND saq.p_id = qw.p_id
                        AND saq.academic_year = qw.academic_year
                    ) THEN 1 ELSE 0 END) AS status,

                    qw.t_pid AS qw_t_id,
                    qw.p_id  AS qw_p_id,
                    qw.supervision_date AS qw_date

                FROM quick_win qw
                LEFT JOIN supervisor s ON qw.p_id = s.p_id
                LEFT JOIN prefix p ON s.prefix_id = p.prefix_id
                LEFT JOIN quickwin_options qo ON qw.options = qo.OptionID
                WHERE qw.t_pid = :pid2
                AND qw.deleted_at IS NULL
                AND (:year = '' OR qw.academic_year = :year)

        ) AS history
        ORDER BY supervision_date DESC
    ";

    $stmt = $conn->prepare($sql_history);
    $stmt->execute([
        ':pid1' => $teacher_pid,
        ':pid2' => $teacher_pid,
        ':year' => $academic_year
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('<div class="alert alert-danger mt-5 text-center">
        เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()) . '
    </div>');
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการนิเทศ - <?php echo htmlspecialchars($teacher_info['teacher_full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        :root {
            --warm-primary: #e76f51; /* สีส้มอมแดง สบายตา */
            --warm-secondary: #ff8c42; /* สีส้มสว่าง (เข้ากับ Footer) */
            --warm-light: #ffe8d6; /* สีพื้นหลังอ่อนๆ */
            --warm-hover: #d65d40; /* สีตอนนำเมาส์ไปชี้ */
        }
        
        .badge-normal {
            background-color: var(--warm-primary);
            color: white;
        }

        .badge-qw {
            background-color: #f4a261;
            color: white;
        }

        /* ตารางสไตล์โมเดิร์น (ไม่มีเส้นขอบ เว้นระยะแถว) */
        .table-modern {
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        .table-modern thead th {
            border: none;
            background-color: var(--warm-primary);
            color: #ffffff;
            font-weight: bold;
            padding: 15px;
        }
        .table-modern tbody tr {
            background-color: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
        }
        .table-modern tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            background-color: #fbfbfb;
        }
        .table-modern tbody td {
            border: none;
            padding: 15px;
            vertical-align: middle;
        }
        .table-modern tbody td:first-child, .table-modern thead th:first-child {
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }
        .table-modern tbody td:last-child, .table-modern thead th:last-child {
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }

        /* ปุ่มสีโทนร้อน */
        .btn-warm {
            background-color: var(--warm-primary);
            color: white;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-warm:hover {
            background-color: var(--warm-hover);
            color: white;
        }

        /* การปรับแต่งสำหรับ Mobile */
        @media (max-width: 768px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }

            .card {
                padding: 15px !important;
            }

            .card-title {
                font-size: 1.25rem;
            }

            /* แปลงตารางเป็นแบบ Card บนหน้าจอมือถือ */
            .table-modern thead {
                display: none;
            }
            .table-modern tbody tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #eee;
                border-radius: 12px;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            }
            .table-modern tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-align: right !important;
                padding: 12px 15px !important;
                border-bottom: 1px solid #f9f9f9;
            }
            .table-modern tbody td::before {
                content: attr(data-label);
                font-weight: bold;
                color: var(--warm-primary);
                text-align: left;
                margin-right: 15px;
            }
            .table-modern tbody td:last-child {
                border-bottom: none;
                flex-direction: column;
            }
            .table-modern tbody td:last-child::before {
                margin-bottom: 10px;
                margin-right: 0;
                text-align: center;
            }
            .btn-group {
                display: flex;
                flex-direction: column;
                width: 100%;
                gap: 5px;
            }

            .btn-group form {
                display: block;
                width: 100%;
            }

            .btn-group .btn {
                width: 100%;
                padding: 8px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>

<body class="bg-light d-flex flex-column min-vh-100">
    <?php include 'navbar.php'; ?>

    <div class="container mt-3 mt-md-5">
        <div class="card shadow-lg p-4">
            <h2 class="card-title text-center mb-4">
                <i class="fas fa-user-clock" style="color: var(--warm-primary);"></i> รายละเอียดประวัติการนิเทศ
            </h2>

            <?php if (!empty($academic_year)): ?>
                <div class="alert text-center fw-bold mb-3 py-2" style="background-color: var(--warm-light); color: #b7472a; border: 1px solid #f4cbba;">
                    ปีการศึกษา <?= htmlspecialchars($academic_year) ?>
                </div>
            <?php endif; ?>

            <div class="card mb-4" style="border: 2px solid var(--warm-secondary);">
                <div class="card-body bg-light p-3">
                    <div class="row g-2">
                        <div class="col-12 col-md-6">
                            <strong>ผู้รับการนิเทศ:</strong> <?php echo htmlspecialchars($teacher_info['teacher_full_name']); ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>โรงเรียน:</strong> <?php echo htmlspecialchars($teacher_info['SchoolName']); ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>ตำแหน่ง:</strong> <?php echo htmlspecialchars($teacher_info['teacher_position']); ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <strong>กลุ่มสาระการเรียนรู้:</strong> <?php echo htmlspecialchars($teacher_info['subjectgroup_name'] ?? '-'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-modern text-center w-100">
                    <thead>
                        <tr>
                            <th>วันที่/เวลา</th>
                            <th>ปีการศึกษา</th>
                            <th>ประเภท</th>
                            <th>หัวข้อ / วิชา</th>
                            <th>ผู้นิเทศ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)) : ?>
                            <tr>
                                <td colspan="5" class="text-center text-danger fw-bold p-4">
                                    ไม่พบประวัติการนิเทศ
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($results as $row) : ?>
                                <tr>
                                    <td class="text-center" data-label="วันที่/เวลา" style="min-width: 90px;">
                                        <?php echo (new DateTime($row['supervision_date']))->format('d/m/y'); ?><br>
                                        <small class="text-muted"><?php echo (new DateTime($row['supervision_date']))->format('H:i'); ?> น.</small>
                                    </td>

                                    <td class="text-center fw-bold text-secondary" data-label="ปีการศึกษา">
                                        <?php echo htmlspecialchars($row['academic_year'] ?? '-'); ?>
                                    </td>

                                    <td class="text-center" data-label="ประเภท" style="min-width: 100px;">
                                        <?php if ($row['session_type'] === 'normal'): ?>
                                            <span class="badge badge-normal">นิเทศชั้นเรียน</span>
                                            <?php if ($row['time_info']): ?><br><small>ครั้งที่ <?php echo $row['time_info']; ?></small><?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge badge-qw">Quick Win</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center" data-label="หัวข้อ / วิชา">
                                        <div class="text-wrap mx-auto" style="min-width: 120px;">
                                            <?php echo htmlspecialchars($row['topic'] ?? ''); ?>
                                        </div>
                                    </td>

                                    <td class="text-center" data-label="ผู้นิเทศ">
                                        <small><?php echo htmlspecialchars($row['supervisor_full_name']); ?></small>
                                    </td>

                                    <td class="text-center" data-label="จัดการ" style="min-width: 110px;">
                                        <div class="btn-group" role="group">
                                            <?php
                                            $report_action = ($row['session_type'] === 'normal') ? 'supervision_report.php' : 'quickwin_report.php';
                                            $t_id_val = ($row['session_type'] === 'normal') ? $row['teacher_t_pid'] : $row['qw_t_id'];
                                            $p_id_val = ($row['session_type'] === 'normal') ? $row['supervisor_p_id'] : $row['qw_p_id'];
                                            ?>
                                            <form method="POST" action="<?= $report_action ?>" style="display:inline;" target="_blank">
                                                <?php if ($row['session_type'] === 'normal'): ?>
                                                    <input type="hidden" name="s_pid" value="<?php echo $row['supervisor_p_id']; ?>">
                                                    <input type="hidden" name="t_pid" value="<?php echo $row['teacher_t_pid']; ?>">
                                                    <input type="hidden" name="sub_code" value="<?php echo $row['subject_code']; ?>">
                                                    <input type="hidden" name="time" value="<?php echo $row['inspection_time']; ?>">
                                                <?php else: ?>
                                                    <input type="hidden" name="t_id" value="<?php echo $row['qw_t_id']; ?>">
                                                    <input type="hidden" name="p_id" value="<?php echo $row['qw_p_id']; ?>">
                                                    <input type="hidden" name="date" value="<?php echo $row['qw_date']; ?>">
                                                <?php endif; ?>
                                            <button type="submit" class="btn btn-sm btn-warm w-100">
                                                    <i class="fas fa-file-alt"></i> รายงาน
                                                </button>
                                            </form>

                                            <?php if (!$is_supervisor): ?>
                                                <?php if ($row['status'] == 0): ?>
                                                    <form method="POST" action="forms/satisfaction_form.php" style="display:inline;">
                                                        <input type="hidden" name="mode" value="<?= $row['session_type'] ?>">
                                                        <input type="hidden" name="t_pid" value="<?= $t_id_val ?>">
                                                        <?php if ($row['session_type'] === 'normal'): ?>
                                                            <input type="hidden" name="s_pid" value="<?php echo $row['supervisor_p_id']; ?>">
                                                            <input type="hidden" name="sub_code" value="<?php echo $row['subject_code']; ?>">
                                                            <input type="hidden" name="time" value="<?php echo $row['inspection_time']; ?>">
                                                            <input type="hidden" name="academic_year" value="<?php echo $row['academic_year']; ?>">
                                                        <?php else: ?>
                                                            <input type="hidden" name="p_id" value="<?php echo $row['qw_p_id']; ?>">
                                                            <input type="hidden" name="date" value="<?php echo $row['qw_date']; ?>">
                                                            <input type="hidden" name="academic_year" value="<?php echo $row['academic_year']; ?>">
                                                        <?php endif; ?>
                                                        <button type="submit" class="btn btn-sm btn-warning w-100">
                                                            <i class="fas fa-star"></i> ประเมิน
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <?php $cert_action = ($row['session_type'] === 'normal') ? 'certificate.php' : 'certificate_quickwin.php'; ?>
                                                    <form method="POST" action="<?= $cert_action ?>" style="display:inline;" target="_blank">
                                                        <?php if ($row['session_type'] === 'normal'): ?>
                                                            <input type="hidden" name="s_pid" value="<?php echo $row['supervisor_p_id']; ?>">
                                                            <input type="hidden" name="t_pid" value="<?php echo $row['teacher_t_pid']; ?>">
                                                            <input type="hidden" name="sub_code" value="<?php echo $row['subject_code']; ?>">
                                                            <input type="hidden" name="time" value="<?php echo $row['inspection_time']; ?>">
                                                            <input type="hidden" name="academic_year" value="<?php echo $row['academic_year']; ?>">
                                                        <?php else: ?>
                                                            <input type="hidden" name="t_id" value="<?php echo $row['qw_t_id']; ?>">
                                                            <input type="hidden" name="p_id" value="<?php echo $row['qw_p_id']; ?>">
                                                            <input type="hidden" name="date" value="<?php echo $row['qw_date']; ?>">
                                                            <input type="hidden" name="academic_year" value="<?php echo $row['academic_year']; ?>">
                                                        <?php endif; ?>
                                                        <button type="submit" class="btn btn-sm btn-success w-100">
                                                            <i class="fas fa-certificate"></i> เกียรติบัตร
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>

        <script>
            let mode = "<?= $_GET['mode'] ?? '' ?>";

            let title = 'บันทึกสำเร็จ';
            let text = 'ขอบคุณสำหรับการทำแบบประเมิน';

            if (mode === 'normal') {
                text = 'บันทึกผลการประเมินชั้นเรียนเรียบร้อยแล้ว';
            }

            if (mode === 'quickwin') {
                text = 'บันทึกผลการประเมิน Quick Win เรียบร้อยแล้ว';
            }

            Swal.fire({
                icon: 'success',
                title: title,
                text: text,
                confirmButtonText: 'ตกลง',
                timer: 2500,
                timerProgressBar: true
            }).then(() => {
                // ลบ success & mode ออกจาก URL (กันเด้งซ้ำ)
                const url = new URL(window.location);
                url.searchParams.delete('success');
                url.searchParams.delete('mode');
                window.history.replaceState({}, document.title, url);
            });
        </script>

    <?php endif; ?>
</body>

</html>