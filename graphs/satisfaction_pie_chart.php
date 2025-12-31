<?php
// ไฟล์: graphs/satisfaction_pie_chart.php

// ตรวจสอบ path ของ config ให้ถูกต้อง
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

$dashboard_data = [];
$chart_labels = '[]';
$chart_values = '[]';
$js_background_colors = '[]';
$js_response_counts = '[]';

try {
    // ---------------------------------------------------------------------------
    // SQL: ดึงคะแนนเฉลี่ยความพึงพอใจรายข้อ
    // ---------------------------------------------------------------------------
    $query = "
        SELECT 
            sq.question_text,
            COALESCE(AVG(sa.rating), 0) AS average_score,
            COUNT(sa.rating) AS response_count
        FROM 
            satisfaction_questions sq
        LEFT JOIN 
            satisfaction_answers sa ON sq.id = sa.question_id
        GROUP BY 
            sq.id, sq.question_text
        ORDER BY 
            sq.id ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $values = [];
    $counts = [];

    $item_number = 1;
    foreach ($result as $row) {
        $text_with_num = $item_number . '. ' . $row['question_text'];
        $row['question_text_with_number'] = $text_with_num;

        $dashboard_data[] = $row;
        $labels[] = $text_with_num;
        $values[] = number_format($row['average_score'], 2);
        $counts[] = $row['response_count'];

        $item_number++;
    }

    $chart_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
    $chart_values = json_encode($values);
    $js_response_counts = json_encode($counts);

    // ชุดสี
    $colors = [
        '#FF6384',
        '#36A2EB',
        '#FFCE56',
        '#4BC0C0',
        '#9966FF',
        '#FF9F40',
        '#C9CBCF',
        '#E7E9ED',
        '#28a745',
        '#17a2b8',
        '#6f42c1',
        '#fd7e14',
        '#20c997',
        '#d63384',
        '#6610f2'
    ];

    $bg_colors_final = [];
    $count_data = count($dashboard_data);
    for ($i = 0; $i < $count_data; $i++) {
        $bg_colors_final[] = $colors[$i % count($colors)];
    }
    $js_background_colors = json_encode($bg_colors_final);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการดึงข้อมูลกราฟ: ' . $e->getMessage() . '</div>';
}
?>

<div class="card shadow-sm">
    <div class="card-header card-header-custom text-center">
        <h2 class="h4 mb-0"><i class="fas fa-chart-pie"></i> สรุปผลความพึงพอใจต่อการนิเทศศึกษา</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">คะแนนเฉลี่ยแต่ละประเด็น (เต็ม 5)</h5>
                <div style="position: relative; height: 400px;">
                    <canvas id="satisfactionChart"></canvas>
                </div>
            </div>

            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered table-sm">
                        <thead class="table-info">
                            <tr class="text-center">
                                <th scope="col" style="width: 15%;">ข้อที่</th>
                                <th scope="col" style="width: 45%;">คะแนนเฉลี่ย</th>
                                <th scope="col" style="width: 40%;">จำนวนผู้ตอบ (คน)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($dashboard_data) > 0): ?>
                                <?php foreach ($dashboard_data as $data): ?>
                                    <tr>
                                        <td class="text-center"><?php echo explode('.', $data['question_text_with_number'])[0]; ?></td>
                                        <td class="text-center fw-bold text-primary"><?php echo number_format($data['average_score'], 2); ?></td>
                                        <td class="text-center"><?php echo $data['response_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">ไม่พบข้อมูลการประเมิน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-12 custom-legend">
                <h5 class="card-title mb-3 border-bottom pb-2">ประเด็นการประเมิน</h5>
                <div class="row">
                    <?php foreach ($dashboard_data as $index => $data): ?>
                        <div class="col-md-6 mb-2">
                            <div class="d-flex align-items-center">
                                <div style="width: 15px; height: 15px; background-color: <?php echo json_decode($js_background_colors)[$index]; ?>; margin-right: 10px; border-radius: 50%;"></div>
                                <span class="small">
                                    <?php echo htmlspecialchars($data['question_text_with_number']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('satisfactionChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'pie', // หรือ 'polarArea' ก็สวยดีครับสำหรับคะแนน
                data: {
                    labels: <?php echo $chart_labels; ?>,
                    datasets: [{
                        label: 'คะแนนเฉลี่ย',
                        data: <?php echo $chart_values; ?>,
                        backgroundColor: <?php echo $js_background_colors; ?>,
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }, // ซ่อน Legend ในกราฟ (เพราะเราทำเองข้างล่างแล้ว)
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.formattedValue;
                                    let count = <?php echo $js_response_counts; ?>[context.dataIndex];
                                    return ` ${label}: ${value} คะแนน (จาก ${count} คน)`;
                                }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: {
                                weight: 'bold',
                                size: 14
                            },
                            formatter: (value, context) => {
                                // แสดงเฉพาะเลขข้อ (เช่น "1")
                                return context.chart.data.labels[context.dataIndex].split('.')[0];
                            }
                        }
                    }
                }
            });
        }
    });
</script>