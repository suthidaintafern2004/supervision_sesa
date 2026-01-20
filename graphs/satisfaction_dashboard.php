<?php
// ไฟล์: graphs/satisfaction_dashboard.php

// เชื่อมต่อฐานข้อมูล (PDO)
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

$form_titles = [
    1 => "แบบบันทึกการจัดการเรียนรู้และการจัดการชั้นเรียน (Classroom)",
    3 => "แบบกรอกข้อมูลผู้รับการนิเทศนโยบายและจุดเน้น (Quick Win)",
];

$form_type = isset($_GET['form_type']) ? (int)$_GET['form_type'] : 1;
$page_title = $form_titles[$form_type] ?? "สรุปผลความพึงพอใจ";

$satisfaction_data = [];
$school_supervision_data = [];
$position_supervision_data = [];
$lg_supervised_teacher_data = [];

try {
    // =================================================================================
    // 1. ดึงข้อมูลกราฟหลัก
    // =================================================================================
    if ($form_type == 3) {
        // Quick Win
        $sql = "SELECT 
                    s.school_name AS SchoolName, 
                    COUNT(*) AS supervision_count 
                FROM quick_win qw
                LEFT JOIN teacher t ON qw.t_pid = t.t_pid
                LEFT JOIN school s ON t.school_id = s.school_id
                GROUP BY s.school_name 
                ORDER BY supervision_count DESC";
        $stmt = $conn->query($sql);
        $satisfaction_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Normal (KPI)
        $sql = "SELECT 
                    q.id AS question_id, 
                    q.question_text, 
                    AVG(ans.rating) AS average_score, 
                    COUNT(ans.rating) AS response_count 
                FROM satisfaction_questions q 
                LEFT JOIN satisfaction_answers ans ON q.id = ans.question_id 
                GROUP BY q.id, q.question_text 
                ORDER BY q.id ASC";

        $stmt = $conn->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $item_number = 1;
        foreach ($result as $row) {
            $row['question_text_with_number'] = $item_number . '. ' . $row['question_text'];
            $satisfaction_data[] = $row;
            $item_number++;
        }
    }

    // =================================================================================
    // 2. ดึงข้อมูลกราฟสรุป
    // =================================================================================

    if ($form_type == 3) {
        // =========================================================
        // โหมด Quick Win (ดึงจากตาราง quick_win)
        // =========================================================

        // 2.1 ใช้ข้อมูลชุดเดียวกับกราฟหลักสำหรับโรงเรียน (School)
        $school_supervision_data = $satisfaction_data;

        // 2.2 สรุปตามตำแหน่ง (Position)
        // ใช้ COUNT(qw.t_pid) เพื่อนับจำนวนครั้งการนิเทศทั้งหมด (รวมคนซ้ำ)
        $sql_pos = "SELECT 
                        COALESCE(pos.position_name, 'ไม่ระบุ') AS teacher_position, 
                        COUNT(qw.t_pid) AS supervised_teacher_count 
                    FROM quick_win qw
                    LEFT JOIN teacher t ON qw.t_pid = t.t_pid
                    LEFT JOIN position pos ON t.position_id = pos.position_id
                    GROUP BY pos.position_name 
                    ORDER BY supervised_teacher_count DESC";

        $stmt_pos = $conn->query($sql_pos);
        $position_supervision_data = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);

        // 2.3 สรุปตามกลุ่มสาระ (Learning Group)
        // ใช้ COUNT(qw.t_pid) เพื่อนับจำนวนครั้งการนิเทศทั้งหมด (รวมคนซ้ำ)
        $sql_lg = "SELECT 
                        COALESCE(sg.subjectgroup_name, 'ไม่ระบุ') AS learning_group, 
                        COUNT(qw.t_pid) AS supervised_teacher_count 
                   FROM quick_win qw
                   LEFT JOIN teacher t ON qw.t_pid = t.t_pid
                   LEFT JOIN subject s ON t.subject_id = s.subject_id
                   LEFT JOIN subject_group sg ON s.subjectgroup_id = sg.subjectgroup_id
                   GROUP BY sg.subjectgroup_name 
                   ORDER BY supervised_teacher_count DESC";

        $stmt_lg = $conn->query($sql_lg);
        $lg_supervised_teacher_data = $stmt_lg->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // === Normal (KPI) ===

        // 2.1 สรุปรายโรงเรียน (✅ แก้ไขตรงนี้: เปลี่ยน COUNT(ss.id) เป็น COUNT(*))
        $sql_school = "SELECT 
                            s.school_name AS SchoolName, 
                            COUNT(*) AS supervision_count 
                       FROM supervision_sessions ss
                       INNER JOIN teacher t ON ss.teacher_t_pid = t.t_pid
                       LEFT JOIN school s ON t.school_id = s.school_id
                       GROUP BY s.school_name 
                       HAVING COUNT(*) > 0 
                       ORDER BY supervision_count DESC";
        $stmt_school = $conn->query($sql_school);
        $school_supervision_data = $stmt_school->fetchAll(PDO::FETCH_ASSOC);

        // 2.2 สรุปตามตำแหน่ง
        $sql_pos = "SELECT 
                        COALESCE(pos.position_name, 'ไม่ระบุ') AS teacher_position, 
                        COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count 
                    FROM supervision_sessions ss
                    INNER JOIN teacher t ON ss.teacher_t_pid = t.t_pid
                    LEFT JOIN position pos ON t.position_id = pos.position_id
                    GROUP BY pos.position_name 
                    ORDER BY supervised_teacher_count DESC";
        $stmt_pos = $conn->query($sql_pos);
        $position_supervision_data = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);

        // 2.3 สรุปตามกลุ่มสาระ
        $sql_lg = "SELECT 
                        COALESCE(sg.subjectgroup_name, 'ไม่ระบุ') AS learning_group, 
                        COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count 
                   FROM supervision_sessions ss
                   INNER JOIN teacher t ON ss.teacher_t_pid = t.t_pid
                   LEFT JOIN subject s ON t.subject_id = s.subject_id
                   LEFT JOIN subject_group sg ON s.subjectgroup_id = sg.subjectgroup_id
                   GROUP BY sg.subjectgroup_name 
                   ORDER BY supervised_teacher_count DESC";
        $stmt_lg = $conn->query($sql_lg);
        $lg_supervised_teacher_data = $stmt_lg->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage() . '</div>';
}

// ... (ส่วน HTML ด้านล่างเหมือนเดิมทุกประการ ไม่ต้องแก้) ...
// เพื่อความชัวร์ ให้ Copy HTML จากไฟล์ก่อนหน้านี้มาแปะต่อท้ายตรงนี้ได้เลยครับ
// หรือถ้าต้องการแค่ส่วน PHP ที่แก้ ก็เอาเฉพาะด้านบนไปทับได้ครับ

// =================================================================================
// เตรียมข้อมูล JSON สำหรับ Chart.js
// =================================================================================

// 1. กราฟหลัก (Pie/Bar)
if ($form_type == 1) {
    // คะแนนความพึงพอใจ
    $chart_labels = json_encode(array_column($satisfaction_data, 'question_text_with_number'));
    $scores = array_map(fn($score) => $score ?? 0, array_column($satisfaction_data, 'average_score'));
    $chart_values = json_encode($scores);
} else {
    // จำนวน Quick Win แยกโรงเรียน
    $chart_labels = json_encode(array_column($satisfaction_data, 'SchoolName'));
    $chart_values = json_encode(array_column($satisfaction_data, 'supervision_count'));
}

// 2. กราฟย่อยต่างๆ
$school_chart_labels   = json_encode(array_column($school_supervision_data, 'SchoolName'));
$school_chart_values   = json_encode(array_column($school_supervision_data, 'supervision_count'));

$position_chart_labels = json_encode(array_column($position_supervision_data, 'teacher_position'));
$position_chart_values = json_encode(array_column($position_supervision_data, 'supervised_teacher_count'));

$lg_chart_labels       = json_encode(array_column($lg_supervised_teacher_data, 'learning_group'));
$lg_chart_values       = json_encode(array_column($lg_supervised_teacher_data, 'supervised_teacher_count'));

// สีพื้นหลัง
$background_colors = [
    'rgba(255, 99, 132, 0.7)',
    'rgba(54, 162, 235, 0.7)',
    'rgba(255, 206, 86, 0.7)',
    'rgba(75, 192, 192, 0.7)',
    'rgba(153, 102, 255, 0.7)',
    'rgba(255, 159, 64, 0.7)',
    'rgba(46, 204, 113, 0.7)',
    'rgba(231, 76, 60, 0.7)',
    'rgba(142, 68, 173, 0.7)',
    'rgba(26, 188, 156, 0.7)',
    'rgba(241, 196, 15, 0.7)',
    'rgba(52, 73, 94, 0.7)'
];
$js_background_colors = json_encode($background_colors);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            background-image: url('../images/bg001.jpg');
            background-size: cover;
            background-attachment: fixed;
        }

        .card-header-custom {
            background-color: #17a2b8;
            color: white;
        }

        .chart-card {
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            border-radius: 18px;
            padding: 18px 24px;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            border-left: 6px solid #0d6efd;
        }

        .dashboard-main {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 22px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body>
    <div class="container mt-5">

        <div class="card dashboard-main shadow-lg mb-4">
            <div class="card-body">

                <!-- Header + Back -->
                <div class="d-flex align-items-center mb-3">
                    <a href="../index.php" class="btn btn-danger me-3">
                        <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                    </a>
                    <h2 class="mb-0 fw-bold text-center flex-grow-1">
                        📊 Dashboard สรุปผลการนิเทศ
                    </h2>
                </div>

                <hr class="my-4">

                <!-- กราฟแรก -->
                <?php if ($form_type == 1): ?>
                    <?php
                    $dashboard_data = $satisfaction_data;
                    include 'satisfaction_pie_chart.php';
                    ?>
                <?php elseif ($form_type == 3): ?>
                    <?php
                    $dashboard_data = $satisfaction_data;
                    include 'quick_win_chart.php';
                    ?>
                <?php endif; ?>

            </div>
        </div>


        <div class="row">
            <div class="col-lg-12 chart-card">
                <?php $lg_supervision_data = $lg_supervised_teacher_data;
                include 'learning_group_chart.php'; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12 chart-card">
                <?php include 'position_supervision_chart.php'; ?>
            </div>
        </div>

        <?php if ($form_type == 1): ?>
            <div class="row">
                <div class="col-lg-12 chart-card">
                    <?php include 'school_supervision_chart.php'; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ลงทะเบียน Plugin
        Chart.register(ChartDataLabels);
    </script>
</body>

</html>