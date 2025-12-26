<?php
// ไฟล์: graphs/quick_win_chart.php

// ตรวจสอบ path ของ config
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

$qw_data = [];
$chart_labels = '[]';
$chart_values = '[]';
$js_background_colors = '[]';

try {
    // ---------------------------------------------------------------------------
    // SQL: นับจำนวน Quick Win แยกตามโรงเรียน
    // ---------------------------------------------------------------------------
    $query = "
        SELECT 
            s.school_name AS SchoolName, 
            COUNT(*) AS supervision_count
        FROM 
            quick_win qw
        LEFT JOIN 
            teacher t ON qw.t_pid = t.t_pid  -- แก้ t_id เป็น t_pid ให้ตรงกับ DB
        LEFT JOIN 
            school s ON t.school_id = s.school_id
        GROUP BY 
            s.school_name
        ORDER BY 
            supervision_count DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $qw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เตรียมข้อมูล Chart.js
    $labels = [];
    $data_values = [];

    foreach ($qw_data as $row) {
        $labels[] = $row['SchoolName'];
        $data_values[] = (int)$row['supervision_count'];
    }

    $chart_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
    $chart_values = json_encode($data_values);

    // ชุดสี Theme ม่วง (Quick Win Style)
    $colors = [
        '#6f42c1',
        '#e83e8c',
        '#d63384',
        '#fd7e14',
        '#ffc107',
        '#28a745',
        '#20c997',
        '#17a2b8',
        '#0dcaf0',
        '#6610f2'
    ];
    $bg_colors_final = [];
    $count = count($qw_data);
    for ($i = 0; $i < $count; $i++) {
        $bg_colors_final[] = $colors[$i % count($colors)];
    }
    $js_background_colors = json_encode($bg_colors_final);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูลกราฟ: ' . $e->getMessage() . '</div>';
}

if (empty($qw_data)) {
    echo "<div class='alert alert-info text-center'>ยังไม่มีข้อมูลการนิเทศแบบ Quick Win</div>";
    return;
}
?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #6f42c1;">
        <h2 class="h4 mb-0 text-white"><i class="fas fa-trophy"></i> สรุปจำนวนการนิเทศ (Quick Win) แยกตามโรงเรียน</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">กราฟแสดงจำนวนครั้ง</h5>
                <div style="position: relative; height: 350px;">
                    <canvas id="quickWinSchoolChart"></canvas>
                </div>
            </div>

            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูล</h5>
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-striped table-hover table-bordered table-sm">
                        <thead class="table-primary sticky-top" style="background-color: #6f42c1; color: white;">
                            <tr class="text-center">
                                <th scope="col">โรงเรียน</th>
                                <th scope="col" style="width: 30%;">จำนวนครั้ง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qw_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($data['SchoolName']); ?></td>
                                    <td class="text-center fw-bold"><?php echo $data['supervision_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('quickWinSchoolChart');
        if (ctx) {
            const labels = <?php echo $chart_labels; ?>;
            const data = <?php echo $chart_values; ?>;
            const bgColors = <?php echo $js_background_colors; ?>;

            new Chart(ctx, {
                type: 'bar', // หรือ 'horizontalBar' ถ้าชอบแนวนอน (indexAxis: 'y')
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'จำนวนครั้ง',
                        data: data,
                        backgroundColor: bgColors,
                        borderColor: bgColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y', // แนวนอนจะอ่านชื่อโรงเรียนง่ายกว่า
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