<?php
// ไฟล์: graphs/learning_group_chart.php

// ตรวจสอบ path ของ config ให้ถูกต้อง (ถอย 2 ชั้น ถ้าไฟล์นี้อยู่ใน graphs/)
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

$lg_supervision_data = [];
$json_chart_labels = '[]';
$json_chart_values = '[]';
$js_background_colors = '[]';

try {
    // ---------------------------------------------------------------------------
    // SQL: นับจำนวนการนิเทศ แยกตามกลุ่มสาระฯ (JOIN teacher -> subject -> subject_group)
    // ---------------------------------------------------------------------------
    $query = "
        SELECT
            COALESCE(sg.subjectgroup_name, 'ไม่ระบุกลุ่มสาระ') AS learning_group, 
            COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count,
            COUNT(ss.teacher_t_pid) AS total_supervision_count
        FROM
            supervision_sessions ss
        INNER JOIN
            teacher t ON ss.teacher_t_pid = t.t_pid
        LEFT JOIN
            subject s ON t.subject_id = s.subject_id
        LEFT JOIN
            subject_group sg ON s.subjectgroup_id = sg.subjectgroup_id
        GROUP BY
            sg.subjectgroup_name
        ORDER BY
            supervised_teacher_count DESC;
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $lg_supervision_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เตรียมข้อมูล Chart.js
    $lg_chart_labels = [];
    $lg_chart_values = [];

    foreach ($lg_supervision_data as $data) {
        $lg_chart_labels[] = $data['learning_group'];
        $lg_chart_values[] = (int)$data['total_supervision_count']; // ใช้จำนวนครั้งรวม (หรือจะใช้ supervised_teacher_count ก็ได้ตามโจทย์)
    }

    $json_chart_labels = json_encode($lg_chart_labels, JSON_UNESCAPED_UNICODE);
    $json_chart_values = json_encode($lg_chart_values, JSON_UNESCAPED_UNICODE);

    // สีพื้นหลัง
    $colors = [
        '#ffc107',
        '#0d6efd',
        '#198754',
        '#6f42c1',
        '#dc3545',
        '#0dcaf0',
        '#fd7e14',
        '#20c997',
        '#6610f2',
        '#d63384',
        '#adb5bd',
        '#343a40',
        '#e83e8c',
        '#17a2b8',
        '#28a745'
    ];
    // ตัดสีให้พอดีจำนวนข้อมูล (วนซ้ำถ้าไม่พอ)
    $bg_colors_final = [];
    $count_data = count($lg_supervision_data);
    for ($i = 0; $i < $count_data; $i++) {
        $bg_colors_final[] = $colors[$i % count($colors)];
    }
    $js_background_colors = json_encode($bg_colors_final);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูลกราฟ: ' . $e->getMessage() . '</div>';
}
?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #ffc107;">
        <h2 class="h4 mb-0 text-dark"><i class="fas fa-book-open"></i> สรุปจำนวนการนิเทศแยกตามกลุ่มสาระฯ</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h5 class="card-title text-center mb-3">แผนภูมิแสดงจำนวนครั้งการนิเทศ</h5>
                <div style="position: relative; height: 300px;">
                    <canvas id="learningGroupChart"></canvas>
                </div>
            </div>

            <div class="col-lg-5">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูล</h5>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-striped table-hover table-bordered table-sm">
                        <thead class="table-warning sticky-top">
                            <tr class="text-center">
                                <th scope="col">กลุ่มสาระการเรียนรู้</th>
                                <th scope="col" style="width: 30%;">จำนวนครั้ง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lg_supervision_data) > 0): ?>
                                <?php foreach ($lg_supervision_data as $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($data['learning_group']); ?></td>
                                        <td class="text-center fw-bold"><?php echo $data['total_supervision_count']; ?></td>
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
        const ctx = document.getElementById('learningGroupChart');
        if (ctx) {
            const chartLabels = <?php echo $json_chart_labels; ?>;
            const chartValues = <?php echo $json_chart_values; ?>;
            const bgColors = <?php echo $js_background_colors; ?>;

            new Chart(ctx, {
                type: 'doughnut', // หรือ 'pie', 'bar' ตามชอบ
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'จำนวนครั้ง',
                        data: chartValues,
                        backgroundColor: bgColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            formatter: (value, ctx) => {
                                let sum = 0;
                                let dataArr = ctx.chart.data.datasets[0].data;
                                dataArr.map(data => {
                                    sum += Number(data);
                                });
                                let percentage = (value * 100 / sum).toFixed(1) + "%";
                                return percentage; // แสดงเปอร์เซ็นต์ในกราฟวงกลม
                            },
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