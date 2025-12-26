<?php
// ไฟล์: graphs/position_supervision_chart.php

// ตรวจสอบ path ของ config ให้ถูกต้อง
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

$position_supervision_data = [];
$position_chart_labels = '[]';
$position_chart_values = '[]';
$js_background_colors = '[]';

try {
    // ---------------------------------------------------------------------------
    // SQL: นับจำนวนผู้รับการนิเทศ แยกตามตำแหน่ง (JOIN teacher -> position)
    // ---------------------------------------------------------------------------
    $query = "
        SELECT
            COALESCE(p.position_name, 'ไม่ระบุตำแหน่ง') AS teacher_position,
            COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count
        FROM
            supervision_sessions ss
        INNER JOIN
            teacher t ON ss.teacher_t_pid = t.t_pid
        LEFT JOIN
            position p ON t.position_id = p.position_id
        GROUP BY
            p.position_name
        ORDER BY
            supervised_teacher_count DESC;
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $position_supervision_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เตรียมข้อมูล Chart.js
    $labels = [];
    $data_values = [];

    foreach ($position_supervision_data as $row) {
        $labels[] = $row['teacher_position'];
        $data_values[] = (int)$row['supervised_teacher_count'];
    }

    $position_chart_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
    $position_chart_values = json_encode($data_values);

    // ชุดสี
    $colors = [
        '#28a745',
        '#17a2b8',
        '#ffc107',
        '#dc3545',
        '#6610f2',
        '#e83e8c',
        '#fd7e14',
        '#20c997',
        '#007bff',
        '#6c757d',
        '#343a40',
        '#6f42c1'
    ];
    $bg_colors_final = [];
    $count_data = count($position_supervision_data);
    for ($i = 0; $i < $count_data; $i++) {
        $bg_colors_final[] = $colors[$i % count($colors)];
    }
    $js_background_colors = json_encode($bg_colors_final);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูลกราฟ: ' . $e->getMessage() . '</div>';
}
?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #28a745;">
        <h2 class="h4 mb-0 text-white"><i class="fas fa-user-graduate"></i> สรุปจำนวนผู้รับการนิเทศตามตำแหน่ง</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">สัดส่วนผู้รับการนิเทศ (คน)</h5>
                <div style="position: relative; height: 300px;">
                    <canvas id="positionSupervisionChart"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูล</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered table-sm">
                        <thead class="table-success">
                            <tr class="text-center">
                                <th scope="col">ตำแหน่ง</th>
                                <th scope="col">จำนวน (คน)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($position_supervision_data) > 0): ?>
                                <?php foreach ($position_supervision_data as $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($data['teacher_position']); ?></td>
                                        <td class="text-center fw-bold"><?php echo $data['supervised_teacher_count']; ?></td>
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
        const ctx = document.getElementById('positionSupervisionChart');
        if (ctx) {
            const chartLabels = <?php echo $position_chart_labels; ?>;
            const chartValues = <?php echo $position_chart_values; ?>;
            const bgColors = <?php echo $js_background_colors; ?>;

            new Chart(ctx, {
                type: 'pie', // หรือ 'doughnut'
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'จำนวนคน',
                        data: chartValues,
                        backgroundColor: bgColors,
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: {
                                weight: 'bold',
                                size: 14
                            },
                            formatter: (value, ctx) => {
                                let sum = 0;
                                let dataArr = ctx.chart.data.datasets[0].data;
                                dataArr.map(data => {
                                    sum += Number(data);
                                });
                                let percentage = (value * 100 / sum).toFixed(1) + "%";
                                return percentage; // แสดง %
                            }
                        }
                    }
                }
            });
        }
    });
</script>