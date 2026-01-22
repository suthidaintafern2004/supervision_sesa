<?php
// ===============================
// session_details.php (PHP ONLY)
// ===============================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db_connect.php';

/* ===============================
   ตรวจสิทธิ์
=============================== */
$is_supervisor = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

/* ===============================
   รับค่า teacher_pid
=============================== */
$teacher_pid = $_GET['teacher_pid'] ?? $_POST['teacher_pid'] ?? null;

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
                ss.supervisor_p_id,
                ss.teacher_t_pid,
                ss.subject_code,
                ss.inspection_time,
                'normal' AS session_type,
                ss.supervision_date,
                ss.inspection_time AS time_info,
                ss.subject_name AS topic,
                CONCAT(IFNULL(p.prefix_name,''), s.fname, ' ', s.lname) AS supervisor_full_name,

                (
                    SELECT COUNT(*)
                    FROM kpi_answers ka
                    WHERE ka.supervisor_p_id = ss.supervisor_p_id
                      AND ka.teacher_t_pid   = ss.teacher_t_pid
                      AND ka.subject_code    = ss.subject_code
                      AND ka.inspection_time = ss.inspection_time
                ) AS kpi_count,

                /* status: ประเมินแล้วหรือยัง */
                (CASE WHEN EXISTS (
                    SELECT 1
                    FROM satisfaction_answers sa
                    WHERE sa.supervisor_p_id = ss.supervisor_p_id
                      AND sa.teacher_t_pid   = ss.teacher_t_pid
                      AND sa.subject_code    = ss.subject_code
                      AND sa.inspection_time = ss.inspection_time
                ) THEN 1 ELSE 0 END) AS status,

                NULL AS qw_t_id,
                NULL AS qw_p_id,
                NULL AS qw_date

            FROM supervision_sessions ss
            LEFT JOIN supervisor s ON ss.supervisor_p_id = s.p_id
            LEFT JOIN prefix p ON s.prefix_id = p.prefix_id
            WHERE ss.teacher_t_pid = :pid1
              AND ss.deleted_at IS NULL

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
                qo.OptionText AS topic,
                CONCAT(IFNULL(p.prefix_name,''), s.fname, ' ', s.lname) AS supervisor_full_name,
                0 AS kpi_count,

                /* ⭐ ใช้ flag จาก quick_win โดยตรง */
                qw.satisfaction_submitted AS status,

                qw.t_pid AS qw_t_id,
                qw.p_id  AS qw_p_id,
                qw.supervision_date AS qw_date

            FROM quick_win qw
            LEFT JOIN supervisor s ON qw.p_id = s.p_id
            LEFT JOIN prefix p ON s.prefix_id = p.prefix_id
            LEFT JOIN quickwin_options qo ON qw.options = qo.OptionID
            WHERE qw.t_pid = :pid2
              AND qw.deleted_at IS NULL

        ) AS history
        ORDER BY supervision_date DESC
    ";

    $stmt = $conn->prepare($sql_history);
    $stmt->execute([
        ':pid1' => $teacher_pid,
        ':pid2' => $teacher_pid
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
        .badge-normal {
            background-color: #0d6efd;
            color: white;
        }

        .badge-qw {
            background-color: #ffc107;
            color: black;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow-lg p-4">
            <h2 class="card-title text-center mb-4">
                <i class="fas fa-user-clock"></i> รายละเอียดประวัติการนิเทศ
            </h2>

            <div class="card mb-4 border-primary">
                <div class="card-body bg-light">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <strong>ผู้รับการนิเทศ:</strong>
                            <?php echo htmlspecialchars($teacher_info['teacher_full_name']); ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>โรงเรียน:</strong>
                            <?php echo htmlspecialchars($teacher_info['SchoolName']); ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>ตำแหน่ง:</strong>
                            <?php echo htmlspecialchars($teacher_info['teacher_position']); ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>กลุ่มสาระฯ:</strong>
                            <?php echo htmlspecialchars($teacher_info['subjectgroup_name'] ?? '-'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-primary">
                        <tr class="text-center">
                            <th style="width: 15%;">วันที่</th>
                            <th style="width: 10%;">ประเภท</th>
                            <th style="width: 25%;" class="text-center">หัวข้อ / วิชา</th>
                            <th style="width: 20%;">ผู้นิเทศ</th>
                            <th style="width: 30%;">การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)) : ?>
                            <tr>
                                <td colspan="5" class="text-center text-danger fw-bold p-4">
                                    ไม่พบประวัติการนิเทศสำหรับครูท่านนี้
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($results as $row) : ?>
                                <tr>
                                    <td class="text-center">
                                        <?php echo (new DateTime($row['supervision_date']))->format('d/m/Y H:i'); ?> น.
                                    </td>

                                    <td class="text-center">
                                        <?php if ($row['session_type'] === 'normal'): ?>
                                            <span class="badge badge-normal">นิเทศ</span><br>
                                            <small class="text-muted">ครั้งที่ <?php echo $row['time_info']; ?></small>
                                        <?php else: ?>
                                            <span class="badge badge-qw">จุดเน้น (Quick Win)</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <?php echo htmlspecialchars($row['topic'] ?? ''); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['supervisor_full_name']); ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($row['session_type'] === 'normal'): ?>
                                            <div class="btn-group" role="group">
                                                <form method="POST" action="supervision_report.php" style="display:inline;" target="_blank">
                                                    <input type="hidden" name="s_pid" value="<?php echo $row['supervisor_p_id']; ?>">
                                                    <input type="hidden" name="t_pid" value="<?php echo $row['teacher_t_pid']; ?>">
                                                    <input type="hidden" name="sub_code" value="<?php echo $row['subject_code']; ?>">
                                                    <input type="hidden" name="time" value="<?php echo $row['inspection_time']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-info text-white">
                                                        <i class="fas fa-file-alt"></i> รายงาน
                                                    </button>
                                                </form>

                                                <?php if (!$is_supervisor): ?>
                                                    <?php if ($row['status'] == 0): ?>
                                                        <form method="POST" action="forms/satisfaction_form.php" style="display:inline;">
                                                            <input type="hidden" name="mode" value="normal">
                                                            <input type="hidden" name="s_pid" value="<?php echo $row['supervisor_p_id']; ?>">
                                                            <input type="hidden" name="t_pid" value="<?php echo $row['teacher_t_pid']; ?>">
                                                            <input type="hidden" name="sub_code" value="<?php echo $row['subject_code']; ?>">
                                                            <input type="hidden" name="time" value="<?php echo $row['inspection_time']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-star"></i> ประเมิน
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" action="certificate.php" style="display:inline;" target="_blank">
                                                            <input type="hidden" name="s_pid" value="<?php echo $row['supervisor_p_id']; ?>">
                                                            <input type="hidden" name="t_pid" value="<?php echo $row['teacher_t_pid']; ?>">
                                                            <input type="hidden" name="sub_code" value="<?php echo $row['subject_code']; ?>">
                                                            <input type="hidden" name="time" value="<?php echo $row['inspection_time']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="fas fa-certificate"></i> เกียรติบัตร
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>

                                        <?php else: ?>
                                            <div class="btn-group" role="group">

                                                <form method="POST" action="quickwin_report.php" style="display:inline;" target="_blank">
                                                    <input type="hidden" name="t_id" value="<?php echo $row['qw_t_id']; ?>">
                                                    <input type="hidden" name="p_id" value="<?php echo $row['qw_p_id']; ?>">
                                                    <input type="hidden" name="date" value="<?php echo $row['qw_date']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-info text-white">
                                                        <i class="fas fa-file-alt"></i> รายงาน
                                                    </button>
                                                </form>

                                                <?php if (!$is_supervisor): ?>

                                                    <?php if ($row['status'] == 0): ?>
                                                        <!-- ยังไม่ประเมิน -->
                                                        <form method="POST" action="forms/satisfaction_form.php" style="display:inline;">
                                                            <input type="hidden" name="mode" value="quickwin">
                                                            <input type="hidden" name="t_pid" value="<?php echo $row['qw_t_id']; ?>">
                                                            <input type="hidden" name="p_id" value="<?php echo $row['qw_p_id']; ?>">
                                                            <input type="hidden" name="date" value="<?php echo $row['qw_date']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-star"></i> ประเมินจุดเน้น
                                                            </button>
                                                        </form>


                                                    <?php else: ?>
                                                        <!-- ประเมินแล้ว -->
                                                        <form method="POST" action="certificate_quickwin.php" style="display:inline;" target="_blank">
                                                            <input type="hidden" name="t_id" value="<?php echo $row['qw_t_id']; ?>">
                                                            <input type="hidden" name="p_id" value="<?php echo $row['qw_p_id']; ?>">
                                                            <input type="hidden" name="date" value="<?php echo $row['qw_date']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="fas fa-certificate"></i> เกียรติบัตร
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                <?php endif; ?>

                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-danger">
                    <i class="fas fa-chevron-left"></i> กลับไปหน้าประวัติรวม
                </a>
            </div>
        </div>
    </div>

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