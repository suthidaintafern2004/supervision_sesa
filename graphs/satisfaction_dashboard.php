<?php
// 1. เชื่อมต่อฐานข้อมูลและเริ่ม Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

// ตรวจสอบสิทธิ์ ศน.
$supervisor_p_id = $_SESSION['user_id'] ?? null;

// 2. รับค่าจาก Filter
$form_type = isset($_GET['form_type']) ? $_GET['form_type'] : '1';

// --- ส่วนที่ปรับปรุงใหม่ ---
$current_year_th = (int)date("Y") + 543;

// 1. ดึงรายการปีการศึกษาทั้งหมดที่มีในฐานข้อมูล
$years_sql = "SELECT academic_year FROM supervision_sessions UNION SELECT academic_year FROM quick_win ORDER BY academic_year DESC";
$available_years = $conn->query($years_sql)->fetchAll(PDO::FETCH_COLUMN);

// 2. หาปีล่าสุดที่มีข้อมูลในฐานข้อมูล (ถ้าไม่มีเลย ให้ใช้ปีปัจจุบัน)
$latest_data_year = !empty($available_years) ? (int)$available_years[0] : $current_year_th;

// 3. กำหนดค่า Default: 
// - ถ้ามีการเลือกผ่าน Dropdown ($_GET) ให้ใช้ค่านั้น
// - ถ้าเปิดหน้าเว็บมาครั้งแรก ให้ใช้ $latest_data_year (ปีล่าสุดที่มีข้อมูล)
$selected_year = isset($_GET['academic_year']) ? (int)$_GET['academic_year'] : $latest_data_year;

// สีพาสเทลแบบเข้ม
$vividPastelColors = ['#FF9AA2', '#FFB7B2', '#FFDAC1', '#E2F0CB', '#B5EAD7', '#C7CEEA', '#F3B0C3', '#97C1A9', '#8FCACA', '#CCA9DD'];

try {
    if ($form_type === 'personal') {
        // ========================= โหมดสถิติรายบุคคล (ศน.) =========================
        $page_title = "สถิติการนิเทศรายบุคคล (ศน.)";

        $stmt = $conn->prepare("
            SELECT CONCAT(pre.prefix_name, s.fname, ' ', s.lname) AS fullname 
            FROM supervisor s
            LEFT JOIN prefix pre ON s.prefix_id = pre.prefix_id
            WHERE s.p_id = :pid
        ");
        $stmt->execute(['pid' => $supervisor_p_id]);
        $supervisor_info = $stmt->fetch(PDO::FETCH_ASSOC);

        $cr_stmt = $conn->prepare("SELECT COUNT(*) FROM supervision_sessions WHERE supervisor_p_id = :pid AND academic_year = :year");
        $cr_stmt->execute(['pid' => $supervisor_p_id, 'year' => $selected_year]);
        $count_cr = $cr_stmt->fetchColumn();

        $qw_stmt = $conn->prepare("SELECT COUNT(*) FROM quick_win WHERE p_id = :pid AND academic_year = :year");
        $qw_stmt->execute(['pid' => $supervisor_p_id, 'year' => $selected_year]);
        $count_qw = $qw_stmt->fetchColumn();

        $summaryStmt = $conn->prepare("
            SELECT COUNT(DISTINCT ss.teacher_t_pid) AS teachers, COUNT(DISTINCT t.school_id) AS schools, COUNT(DISTINCT t.subjectgroup_id) AS subjects 
            FROM supervision_sessions ss JOIN teacher t ON ss.teacher_t_pid = t.t_pid 
            WHERE ss.supervisor_p_id = :pid AND ss.academic_year = :year
        ");
        $summaryStmt->execute(['pid' => $supervisor_p_id, 'year' => $selected_year]);
        $personal_summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

        $stmt_sch = $conn->prepare("
            SELECT sc.school_name, COUNT(*) AS count FROM supervision_sessions ss 
            JOIN teacher t ON ss.teacher_t_pid = t.t_pid JOIN school sc ON t.school_id = sc.school_id 
            WHERE ss.supervisor_p_id = :pid AND ss.academic_year = :year GROUP BY sc.school_name ORDER BY count DESC
        ");
        $stmt_sch->execute(['pid' => $supervisor_p_id, 'year' => $selected_year]);
        $data_school = $stmt_sch->fetchAll(PDO::FETCH_ASSOC);

        $stmt_lg = $conn->prepare("
            SELECT sg.subjectgroup_name AS label, COUNT(*) AS value FROM supervision_sessions ss 
            JOIN teacher t ON ss.teacher_t_pid = t.t_pid JOIN subject_group sg ON t.subjectgroup_id = sg.subjectgroup_id 
            WHERE ss.supervisor_p_id = :pid AND ss.academic_year = :year GROUP BY sg.subjectgroup_name ORDER BY value DESC
        ");
        $stmt_lg->execute(['pid' => $supervisor_p_id, 'year' => $selected_year]);
        $data_lg = $stmt_lg->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // ========================= โหมดภาพรวมปกติ (Classroom / Quick Win) =========================
        $page_title = ($form_type == '3') ? "สถิติรายงานการประเมินการนิเทศจุดเน้น ( Quick Win )" : "สถิติรายงานการประเมินการนิเทศชั้นเรียน ( Classroom)";
        $ans_table = ($form_type == '3') ? "quickwin_satisfaction_answers" : "satisfaction_answers";

        $stmt_sat = $conn->prepare("
            SELECT q.id, q.question_text, COALESCE(AVG(ans.rating), 0) as avg_rating FROM satisfaction_questions q 
            LEFT JOIN $ans_table ans ON q.id = ans.question_id AND ans.academic_year = :year GROUP BY q.id ORDER BY q.id ASC
        ");
        $stmt_sat->execute(['year' => $selected_year]);
        $data_main = $stmt_sat->fetchAll(PDO::FETCH_ASSOC);

        $main_table = ($form_type == '3') ? "quick_win" : "supervision_sessions";
        $pid_col = ($form_type == '3') ? "t_pid" : "teacher_t_pid";

        $stmt_sch = $conn->prepare("
            SELECT s.school_name, COUNT(m.$pid_col) as count FROM school s 
            LEFT JOIN teacher t ON s.school_id = t.school_id LEFT JOIN $main_table m ON t.t_pid = m.$pid_col AND m.academic_year = :year 
            GROUP BY s.school_id ORDER BY count DESC
        ");
        $stmt_sch->execute(['year' => $selected_year]);
        $data_school = $stmt_sch->fetchAll(PDO::FETCH_ASSOC);

        $stmt_lg = $conn->prepare("
            SELECT sg.subjectgroup_name AS label, COUNT(m.$pid_col) AS value FROM subject_group sg 
            INNER JOIN teacher t ON sg.subjectgroup_id = t.subjectgroup_id INNER JOIN $main_table m ON t.t_pid = m.$pid_col AND m.academic_year = :year 
            GROUP BY sg.subjectgroup_name ORDER BY value DESC
        ");
        $stmt_lg->execute(['year' => $selected_year]);
        $data_lg = $stmt_lg->fetchAll(PDO::FETCH_ASSOC);

        $stmt_p = $conn->prepare("
            SELECT COALESCE(p.position_name, 'ไม่ระบุ') as label, COUNT(m.$pid_col) as value FROM position p 
            INNER JOIN teacher t ON p.position_id = t.position_id INNER JOIN $main_table m ON t.t_pid = m.$pid_col AND m.academic_year = :year 
            GROUP BY p.position_id ORDER BY value DESC
        ");
        $stmt_p->execute(['year' => $selected_year]);
        $data_pos = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard - <?= $page_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --p-blue: #A2D2FF;
            --p-pink: #FFB3BA;
            --p-green: #B9FBC0;
            --p-purple: #DDBAFF;
            --p-orange: #FFDAC1;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Sarabun', sans-serif;
        }

        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: 0.3s;
            margin-bottom: 20px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .card-title-bar {
            border-radius: 20px 20px 0 0;
            padding: 12px;
            font-weight: bold;
            text-align: center;
            color: #333;
        }

        .nav-pills .nav-link.active {
            background-color: var(--p-blue);
            color: #000;
            font-weight: bold;
        }

        .nav-pills .nav-link.active.personal {
            background-color: var(--p-purple);
        }

        .btn-home {
            background: white;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #555;
            transition: 0.3s;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <div class="container-fluid py-4 px-4">
        <div class="filter-section shadow-sm">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <h4 class="mb-0 text-dark fw-bold"><i class="fas fa-chart-line text-primary me-2"></i> <?= $page_title ?></h4>
                    <?php if ($form_type === 'personal'): ?>
                        <span class="badge rounded-pill bg-info text-dark mt-2 px-3 fw-bold">ศึกษานิเทศก์: <?= htmlspecialchars($supervisor_info['fullname'] ?? '-') ?></span>
                    <?php endif; ?>
                    <span class="badge rounded-pill bg-primary mt-2 px-3">ปีการศึกษา <?= $selected_year ?></span>
                </div>
                <div class="col-md-7 text-md-end mt-3 mt-md-0 d-flex justify-content-md-end gap-2 align-items-center">
                    <form action="" method="GET" class="d-flex gap-2">
                        <input type="hidden" name="form_type" value="<?= $form_type ?>">
                        <select name="academic_year" class="form-select rounded-pill border-0 bg-light fw-bold shadow-sm" onchange="this.form.submit()">
                            <?php if (!in_array($current_year_th, $available_years)): ?>
                                <option value="<?= $current_year_th ?>" <?= $selected_year == $current_year_th ? 'selected' : '' ?>>ปีการศึกษา <?= $current_year_th ?></option>
                            <?php endif; ?>
                            <?php foreach ($available_years as $y): ?>
                                <option value="<?= $y ?>" <?= $y == $selected_year ? 'selected' : '' ?>>ปีการศึกษา <?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <div class="nav nav-pills bg-light rounded-pill p-1 shadow-sm">
                        <a class="nav-link <?= $form_type == '1' ? 'active' : '' ?>" href="?form_type=1&academic_year=<?= $selected_year ?>">Classroom</a>
                        <a class="nav-link <?= $form_type == '3' ? 'active' : '' ?>" href="?form_type=3&academic_year=<?= $selected_year ?>">Quick Win</a>
                        <a class="nav-link personal <?= $form_type == 'personal' ? 'active' : '' ?>" href="?form_type=personal&academic_year=<?= $selected_year ?>">สถิติส่วนตัว</a>
                    </div>
                    <a href="../index.php" class="btn-home shadow-sm" title="กลับหน้าหลัก"><i class="fas fa-home"></i></a>
                </div>
            </div>
        </div>

        <div class="row">
            <?php if ($form_type === 'personal'): ?>
                <div class="col-12 mb-4">
                    <div class="stat-card p-4">
                        <div class="mb-4 text-center border-bottom pb-3">
                            <h4 class="fw-bold text-secondary">สรุปสถิติการนิเทศของ: <span class="text-primary"><?= htmlspecialchars($supervisor_info['fullname'] ?? '-') ?></span></h4>
                        </div>
                        <div class="row text-center align-items-center">
                            <div class="col-md-3 border-end">
                                <h5>ครูที่ได้รับการนิเทศ</h5>
                                <h2 class="fw-bold text-primary"><?= (int)$personal_summary['teachers'] ?></h2><small>คน</small>
                            </div>
                            <div class="col-md-3 border-end">
                                <h5>จำนวนโรงเรียน</h5>
                                <h2 class="fw-bold text-success"><?= (int)$personal_summary['schools'] ?></h2><small>แห่ง</small>
                            </div>
                            <div class="col-md-3 border-end">
                                <h5>กลุ่มสาระการเรียนรู้ฯ</h5>
                                <h2 class="fw-bold text-warning"><?= (int)$personal_summary['subjects'] ?></h2><small>กลุ่ม</small>
                            </div>
                            <div class="col-md-3">
                                <h5>รวมจำนวน(ครั้ง)</h5>
                                <h2 class="fw-bold text-danger"><?= (int)$count_cr + (int)$count_qw ?></h2><small>ครั้ง</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="stat-card">
                        <div class="card-title-bar" style="background-color: var(--p-orange);">สัดส่วนการบันทึกแบบฟอร์ม</div>
                        <div class="card-body" style="height: 400px;"><canvas id="formCompareChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="stat-card">
                        <div class="card-title-bar" style="background-color: var(--p-green);">การนิเทศรายโรงเรียน (ส่วนตัว)</div>
                        <div class="card-body" style="height: 400px;"><canvas id="schoolChart"></canvas></div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="stat-card">
                        <div class="card-title-bar" style="background-color: var(--p-purple);">สัดส่วนงานนิเทศตามกลุ่มสาระ (ส่วนตัว)</div>
                        <div class="card-body" style="height: 400px;"><canvas id="lgChart"></canvas></div>
                    </div>
                </div>

            <?php else: ?>
                <div class="col-lg-7 mb-4">
                    <div class="stat-card">
                        <div class="card-title-bar" style="background-color: var(--p-blue);">วิเคราะห์ความพึงพอใจรายข้อ</div>
                        <div class="card-body" style="height: 450px;"><canvas id="mainChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-5 mb-4">
                    <div class="stat-card">
                        <div class="card-title-bar" style="background-color: var(--p-pink);">สถิติค่าน้ำหนักคะแนนเฉลี่ยรายข้อ</div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 450px;">
                                <table class="table table-hover mb-0 fw-bold">
                                    <thead class="bg-light sticky-top">
                                        <tr>
                                            <th class="ps-3">ประเด็นการประเมิน</th>
                                            <th class="text-center" width="20%">คะแนน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data_main as $s): ?>
                                            <tr>
                                                <td class="ps-3 text-muted small"><?= $s['id'] ?>. <?= htmlspecialchars($s['question_text']) ?></td>
                                                <td class="text-center text-primary fs-6"><?= number_format($s['avg_rating'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 mb-4">
                    <div class="stat-card">
                        <div class="card-title-bar" style="background-color: var(--p-green);">สถิติจำนวนครั้งการนิเทศรายโรงเรียน</div>
                        <div class="card-body" style="height: 550px;"><canvas id="schoolChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-7 mb-4">
                    <div class="stat-card">
                        <div class="card-title-bar" style="background-color: var(--p-purple);">จำนวนการนิเทศแยกตามกลุ่มสาระการเรียนรู้</div>
                        <div class="card-body" style="height: 400px;"><canvas id="lgChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-5 mb-4">
                    <div class="stat-card">
                        <div class="card-title-bar" style="background-color: var(--p-orange);">สัดส่วนผู้รับการนิเทศตามตำแหน่ง</div>
                        <div class="card-body" style="height: 400px;"><canvas id="posChart"></canvas></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const colors = <?= json_encode($vividPastelColors) ?>;
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false
        };

        <?php if ($form_type === 'personal'): ?>
            new Chart(document.getElementById('formCompareChart'), {
                type: 'pie',
                data: {
                    labels: ['Classroom', 'Quick Win'],
                    datasets: [{
                        data: [<?= (int)$count_cr ?>, <?= (int)$count_qw ?>],
                        backgroundColor: ['#A2D2FF', '#FFB3BA']
                    }]
                },
                options: commonOptions
            });
        <?php else: ?>
            new Chart(document.getElementById('mainChart'), {
                type: 'radar',
                data: {
                    labels: <?= json_encode(array_map(fn($v) => 'ข้อ ' . $v['id'], $data_main)) ?>,
                    datasets: [{
                        label: 'คะแนนเฉลี่ย',
                        data: <?= json_encode(array_column($data_main, 'avg_rating')) ?>,
                        backgroundColor: 'rgba(162, 210, 255, 0.5)',
                        borderColor: '#007bff',
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        r: {
                            min: 0,
                            max: 5
                        }
                    }
                }
            });
            new Chart(document.getElementById('posChart'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($data_pos, 'label')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($data_pos, 'value')) ?>,
                        backgroundColor: colors
                    }]
                },
                options: commonOptions
            });
        <?php endif; ?>

        new Chart(document.getElementById('schoolChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($data_school, 'school_name')) ?>,
                datasets: [{
                    label: 'จำนวนครั้ง',
                    data: <?= json_encode(array_column($data_school, 'count')) ?>,
                    backgroundColor: colors,
                    borderRadius: 10
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        new Chart(document.getElementById('lgChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($data_lg, 'label')) ?>,
                datasets: [{
                    label: 'จำนวนครั้ง',
                    data: <?= json_encode(array_column($data_lg, 'value')) ?>,
                    backgroundColor: colors,
                    borderRadius: 10
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>

</html>