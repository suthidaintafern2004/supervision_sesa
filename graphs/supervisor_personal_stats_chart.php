<?php

/*************************************************
 * supervisor_personal_stats_modern.php
 * Dashboard สถิติการนิเทศรายบุคคล (ศน.)
 *************************************************/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

/* =========================
   รับค่า ศน.
========================= */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$supervisor_p_id = $_SESSION['user_id'];

/* =========================
   ข้อมูล ศน.
========================= */
$stmt = $conn->prepare("
    SELECT CONCAT(fname,' ',lname) AS fullname
    FROM supervisor
    WHERE p_id = :pid
");
$stmt->execute(['pid' => $supervisor_p_id]);
$supervisor = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   สรุปภาพรวม
========================= */
$summaryStmt = $conn->prepare("
    SELECT
        COUNT(DISTINCT ss.teacher_t_pid) AS teachers,
        COUNT(DISTINCT t.school_id) AS schools,
        COUNT(DISTINCT t.subjectgroup_id) AS subjects
    FROM supervision_sessions ss
    JOIN teacher t ON ss.teacher_t_pid = t.t_pid
    WHERE ss.supervisor_p_id = :pid
");
$summaryStmt->execute(['pid' => $supervisor_p_id]);
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   กราฟกลุ่มสาระ
========================= */
$subjectStmt = $conn->prepare("
    SELECT 
        sg.subjectgroup_name AS label,
        COUNT(*) AS total
    FROM supervision_sessions ss
    JOIN teacher t ON ss.teacher_t_pid = t.t_pid
    JOIN subject_group sg ON t.subjectgroup_id = sg.subjectgroup_id
    WHERE ss.supervisor_p_id = :pid
    GROUP BY sg.subjectgroup_name
    ORDER BY total DESC
");
$subjectStmt->execute(['pid' => $supervisor_p_id]);
$subjects = $subjectStmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   กราฟโรงเรียน
========================= */
$schoolStmt = $conn->prepare("
    SELECT 
        sc.school_name AS label,
        COUNT(*) AS total
    FROM supervision_sessions ss
    JOIN teacher t ON ss.teacher_t_pid = t.t_pid
    JOIN school sc ON t.school_id = sc.school_id
    WHERE ss.supervisor_p_id = :pid
    GROUP BY sc.school_name
    ORDER BY total DESC
");
$schoolStmt->execute(['pid' => $supervisor_p_id]);
$schools = $schoolStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>Dashboard สถิติการนิเทศรายบุคคล</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', sans-serif;
        }

        h4 {
            font-weight: 600;
        }

        .card {
            border: none;
            border-radius: 18px;
        }

        .card-body {
            padding: 1.5rem;
        }

        .chart-container {
            height: 260px;
        }

        .chart-title {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 12px;
            text-align: center;
        }

        .btn-back {
            border-radius: 50px;
            padding: 10px 25px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-back:hover {
            transform: translateX(-5px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }
    </style>
</head>

<body>
    <div class="container mt-3">
        <a href="../index.php" class="btn btn-danger">
            <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
        </a>
    </div>
    <div class="container py-4">

        <!-- Header -->
        <h4 class="mb-4">
            📊 Dashboard สถิติการนิเทศรายบุคคล<br>
            <small class="text-muted">ศน. <?= htmlspecialchars($supervisor['fullname'] ?? '-') ?></small>
        </h4>

        <!-- =========================
         กราฟที่ 1 : ภาพรวม
    ========================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="chart-title">ภาพรวมการนิเทศ</div>
                <div class="chart-container">
                    <canvas id="summaryChart"></canvas>
                </div>
            </div>
        </div>

        <!-- =========================
         กราฟที่2  : โรงเรียน
    ========================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="chart-title">การนิเทศจำแนกตามโรงเรียน</div>
                <div class="chart-container">
                    <canvas id="schoolChart"></canvas>
                </div>
            </div>
        </div>

        <!-- =========================
         กราฟที่ 3 : สัดส่วน
    ========================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="chart-title">สัดส่วนงานนิเทศ (ตามกลุ่มสาระ)</div>
                <div class="chart-container">
                    <canvas id="ratioChart"></canvas>
                </div>
            </div>
        </div>

    </div>

    <script>
        /* =========================
   เตรียมข้อมูล
========================= */
        const subjectLabels = <?= json_encode(array_column($subjects, 'label')) ?>;
        const subjectData = <?= json_encode(array_column($subjects, 'total')) ?>;
        const schoolLabels = <?= json_encode(array_column($schools, 'label')) ?>;
        const schoolData = <?= json_encode(array_column($schools, 'total')) ?>;

        /* =========================
           กราฟภาพรวม (Doughnut)
        ========================= */
        new Chart(document.getElementById('summaryChart'), {
            type: 'doughnut',
            data: {
                labels: ['ครู', 'โรงเรียน', 'กลุ่มสาระ'],
                datasets: [{
                    data: [
                        <?= (int)$summary['teachers'] ?>,
                        <?= (int)$summary['schools'] ?>,
                        <?= (int)$summary['subjects'] ?>
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        /* =========================
           กราฟกลุ่มสาระ (Bar แนวนอน)
        ========================= */
        new Chart(document.getElementById('subjectChart'), {
            type: 'bar',
            data: {
                labels: subjectLabels,
                datasets: [{
                    data: subjectData
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false
            }
        });

        /* =========================
           กราฟโรงเรียน (Bar)
        ========================= */
        const schoolColors = [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e',
            '#e74a3b', '#858796', '#fd7e14', '#20c997',
            '#6f42c1', '#0dcaf0'
        ];

        new Chart(document.getElementById('schoolChart'), {
            type: 'bar',
            data: {
                labels: schoolLabels,
                datasets: [{
                    label: 'จำนวนนิเทศ',
                    data: schoolData,
                    backgroundColor: schoolLabels.map(
                        (_, i) => schoolColors[i % schoolColors.length]
                    ),
                    borderRadius: 8 // ⭐ มุมโค้ง ดูทันสมัย
                }]
            },
            options: {
                indexAxis: 'y', // แท่งแนวนอน
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ` ${context.raw} ครั้ง`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });



        /* =========================
           กราฟสัดส่วน (Pie)
        ========================= */
        new Chart(document.getElementById('ratioChart'), {
            type: 'pie',
            data: {
                labels: subjectLabels,
                datasets: [{
                    data: subjectData
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>

</body>

</html>