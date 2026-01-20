<?php
// graphs/learning_group_chart.php

if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
}

$lg_supervision_data = [];

try {
    /* =========================================================
       ✅ ใช้ teacher.subjectgroup_id ตรง ๆ (ไม่ผ่าน subject)
       ========================================================= */
    $sql = "
        SELECT
            sg.subjectgroup_id,
            sg.subjectgroup_name AS learning_group,
            COUNT(*) AS total_supervision_count
        FROM supervision_sessions ss
        INNER JOIN teacher t 
            ON ss.teacher_t_pid = t.t_pid
        INNER JOIN subject_group sg
            ON t.subjectgroup_id = sg.subjectgroup_id
        GROUP BY
            sg.subjectgroup_id,
            sg.subjectgroup_name
        ORDER BY total_supervision_count DESC
    ";

    $stmt = $conn->query($sql);
    $lg_supervision_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">' . $e->getMessage() . '</div>';
}

/* =======================
   เตรียมข้อมูลกราฟ
======================= */
$labels = [];
$values = [];

foreach ($lg_supervision_data as $row) {
    $labels[] = $row['learning_group'];
    $values[] = (int)$row['total_supervision_count'];
}

$json_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
$json_values = json_encode($values);

$colors = [
    '#0d6efd',
    '#198754',
    '#ffc107',
    '#dc3545',
    '#6f42c1',
    '#20c997',
    '#fd7e14',
    '#0dcaf0',
    '#6610f2',
    '#adb5bd'
];

$bg = [];
for ($i = 0; $i < count($values); $i++) {
    $bg[] = $colors[$i % count($colors)];
}
$json_colors = json_encode($bg);
?>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-warning text-center">
        <h5 class="mb-0 fw-bold text-dark">
            <i class="fas fa-book"></i> สรุปจำนวนการนิเทศแยกตามกลุ่มสาระ
        </h5>
    </div>

    <div class="card-body">
        <div class="row">

            <div class="col-lg-7">
                <div style="height:300px">
                    <canvas id="learningGroupChart"></canvas>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="table-responsive" style="max-height:300px">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="table-warning sticky-top">
                            <tr class="text-center">
                                <th>กลุ่มสาระ</th>
                                <th width="30%">จำนวนครั้ง</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lg_supervision_data as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['learning_group']) ?></td>
                                    <td class="text-center fw-bold"><?= $r['total_supervision_count'] ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (!$lg_supervision_data): ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted">ไม่พบข้อมูล</td>
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
    document.addEventListener('DOMContentLoaded', () => {
        new Chart(document.getElementById('learningGroupChart'), {
            type: 'doughnut',
            data: {
                labels: <?= $json_labels ?>,
                datasets: [{
                    data: <?= $json_values ?>,
                    backgroundColor: <?= $json_colors ?>
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold'
                        },
                        formatter: (v, ctx) => {
                            const sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            return ((v * 100) / sum).toFixed(1) + '%';
                        }
                    }
                }
            }
        });
    });
</script>