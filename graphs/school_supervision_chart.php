<?php
// ไฟล์: graphs/school_supervision_chart.php

// ตรวจสอบ path ของ config ให้ถูกต้อง
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

$school_supervision_data = [];
$school_chart_labels = '[]';
$school_chart_values = '[]';
$js_background_colors = '[]';

try {
    // ---------------------------------------------------------------------------
    // SQL: นับจำนวนการนิเทศ แยกตามโรงเรียน
    // ---------------------------------------------------------------------------
    // ✅ แก้ไขตรงนี้: COUNT(*) แทน COUNT(ss.id)
    $query = "
        SELECT 
            s.school_name AS SchoolName, 
            COUNT(*) AS supervision_count 
        FROM 
            supervision_sessions ss
        INNER JOIN 
            teacher t ON ss.teacher_t_pid = t.t_pid
        INNER JOIN 
            school s ON t.school_id = s.school_id
        GROUP BY 
            s.school_id, s.school_name
        ORDER BY 
            supervision_count DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $school_supervision_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เตรียมข้อมูลสำหรับ Chart.js
    $labels = [];
    $data_values = [];

    foreach ($school_supervision_data as $row) {
        $labels[] = $row['SchoolName'];
        $data_values[] = (int)$row['supervision_count'];
    }

    $school_chart_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
    $school_chart_values = json_encode($data_values);

    // ชุดสี
    $colors = [
        '#007bff',
        '#6610f2',
        '#6f42c1',
        '#e83e8c',
        '#dc3545',
        '#fd7e14',
        '#ffc107',
        '#28a745',
        '#20c997',
        '#17a2b8',
        '#adb5bd',
        '#343a40',
        '#0d6efd',
        '#198754'
    ];
    $bg_colors_final = [];
    $count = count($school_supervision_data);
    for ($i = 0; $i < $count; $i++) {
        $bg_colors_final[] = $colors[$i % count($colors)];
    }
    $js_background_colors = json_encode($bg_colors_final);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูลกราฟ: ' . $e->getMessage() . '</div>';
}
?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #007bff;">
        <h2 class="h4 mb-0 text-white"><i class="fas fa-school"></i> สรุปจำนวนการนิเทศในแต่ละโรงเรียน</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h5 class="card-title text-center mb-3">กราฟแสดงจำนวนครั้งที่ได้รับการนิเทศ</h5>
                <div style="position: relative; height: 350px;">
                    <canvas id="schoolSupervisionChart"></canvas>
                </div>
            </div>

            <div class="col-lg-5">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูล</h5>
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-striped table-hover table-bordered table-sm">
                        <thead class="table-primary sticky-top">
                            <tr class="text-center">
                                <th scope="col">โรงเรียน</th>
                                <th scope="col" style="width: 30%;">จำนวนครั้ง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($school_supervision_data) > 0): ?>
                                <?php foreach ($school_supervision_data as $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($data['SchoolName']); ?></td>
                                        <td class="text-center fw-bold"><?php echo $data['supervision_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">ไม่พบข้อมูลการนิเทศ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('schoolSupervisionChart');
        if (ctx) {
            const chartLabels = <?php echo $school_chart_labels; ?>;
            const chartValues = <?php echo $school_chart_values; ?>;
            const bgColors = <?php echo $js_background_colors; ?>;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'จำนวนครั้ง',
                        data: chartValues,
                        backgroundColor: bgColors,
                        borderColor: bgColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'right',
                            color: '#363636',
                            font: {
                                weight: 'bold'
                            }
                        }
                    }
                }
            });
        }
    });
</script>