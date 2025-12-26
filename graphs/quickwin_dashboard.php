<?php
// ==============================================
// Quick Win Dashboard (Center & Full Width)
// ==============================================

if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

$page_title = "Dashboard สรุปผลการนิเทศ (Quick Win)";

$school_data = $position_data = $lg_data = [];

try {

    // โรงเรียน
    $school_data = $conn->query("
        SELECT s.school_name AS SchoolName, COUNT(*) AS supervision_count
        FROM quick_win qw
        LEFT JOIN teacher t ON qw.t_pid = t.t_pid
        LEFT JOIN school s ON t.school_id = s.school_id
        GROUP BY s.school_name
        ORDER BY supervision_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // กลุ่มสาระ
    $lg_data = $conn->query("
        SELECT COALESCE(sg.subjectgroup_name,'ไม่ระบุ') AS learning_group,
               COUNT(*) AS supervised_teacher_count
        FROM quick_win qw
        LEFT JOIN teacher t ON qw.t_pid = t.t_pid
        LEFT JOIN subject s ON t.subject_id = s.subject_id
        LEFT JOIN subject_group sg ON s.subjectgroup_id = sg.subjectgroup_id
        GROUP BY sg.subjectgroup_name
        ORDER BY supervised_teacher_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ตำแหน่ง
    $position_data = $conn->query("
        SELECT COALESCE(p.position_name,'ไม่ระบุ') AS teacher_position,
               COUNT(*) AS supervised_teacher_count
        FROM quick_win qw
        LEFT JOIN teacher t ON qw.t_pid = t.t_pid
        LEFT JOIN position p ON t.position_id = p.position_id
        GROUP BY p.position_name
        ORDER BY supervised_teacher_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">'.$e->getMessage().'</div>';
}

$colors = json_encode([
    'rgba(54,162,235,0.7)','rgba(255,99,132,0.7)','rgba(255,206,86,0.7)',
    'rgba(75,192,192,0.7)','rgba(153,102,255,0.7)','rgba(255,159,64,0.7)'
]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title><?php echo $page_title; ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
    background:#f8f9fa url('../images/bg001.jpg') fixed;
    background-size:cover;
    font-size:15px;
}
.container{
    max-width:1000px;
}
.card{
    border:none;
    box-shadow:0 3px 6px rgba(0,0,0,.12);
    margin-bottom:2rem;
}
.card h5{
    font-weight:600;
    text-align:center;
    margin-bottom:1rem;
}
canvas{
    max-height:420px;
}
</style>
</head>

<body>
<div class="container mt-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="../index.php" class="btn btn-danger">กลับหน้าหลัก</a>
    <h4 class="mb-0 fw-bold"><?php echo $page_title; ?></h4>
    <div style="width:120px;"></div>
</div>

<!-- โรงเรียน -->
<div class="card p-4 text-center">
    <h5>📊 สรุป Quick Win แยกตามโรงเรียน</h5>
    <canvas id="schoolChart"></canvas>
</div>

<!-- กลุ่มสาระ -->
<div class="card p-4 text-center">
    <h5>📚 สรุป Quick Win แยกตามกลุ่มสาระ</h5>
    <canvas id="lgChart"></canvas>
</div>

<!-- ตำแหน่ง -->
<div class="card p-4 text-center">
    <h5>👤 สรุป Quick Win แยกตามตำแหน่ง</h5>
    <canvas id="positionChart"></canvas>
</div>

</div>

<script>
const colors = <?php echo $colors; ?>;

new Chart(schoolChart,{
    type:'bar',
    data:{
        labels: <?php echo json_encode(array_column($school_data,'SchoolName')); ?>,
        datasets:[{
            data: <?php echo json_encode(array_column($school_data,'supervision_count')); ?>,
            backgroundColor:colors
        }]
    },
    options:{
        responsive:true,
        plugins:{legend:{display:false}}
    }
});

new Chart(lgChart,{
    type:'pie',
    data:{
        labels: <?php echo json_encode(array_column($lg_data,'learning_group')); ?>,
        datasets:[{
            data: <?php echo json_encode(array_column($lg_data,'supervised_teacher_count')); ?>,
            backgroundColor:colors
        }]
    }
});

new Chart(positionChart,{
    type:'doughnut',
    data:{
        labels: <?php echo json_encode(array_column($position_data,'teacher_position')); ?>,
        datasets:[{
            data: <?php echo json_encode(array_column($position_data,'supervised_teacher_count')); ?>,
            backgroundColor:colors
        }]
    }
});
</script>

</body>
</html>
