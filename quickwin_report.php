<?php
// ไฟล์: quickwin_report.php
session_start();
require_once 'config/db_connect.php';

// รับ Composite Key จาก POST หรือ GET
$p_id = $_POST['p_id'] ?? $_GET['p_id'] ?? null;
$t_id = $_POST['t_id'] ?? $_GET['t_id'] ?? null;
$date = $_POST['date'] ?? $_GET['date'] ?? null;

if (!$p_id || !$t_id || !$date) {
    die("ข้อมูลการประเมินจุดเน้นไม่ครบถ้วน");
}

try {
    // ========================
    // 1. ข้อมูลการประเมินจุดเน้น (Quick Win)
    // ========================
    $sql_info = "SELECT
                    qw.*,
                    -- เอาการ JOIN quickwin_options แบบเก่าออก เพราะตอนนี้เก็บเป็น 1/6/9
                    
                    /* ข้อมูลครู */
                    CONCAT(IFNULL(pt.prefix_name,''), t.f_name, ' ', t.l_name) AS t_fullname,
                    t.t_pid, 
                    pos_t.position_name AS t_position,
                    IFNULL(sg.subjectgroup_name, IFNULL(sub.subject_name, '-')) AS learning_group,
                    s_school.school_name AS t_school,
                    
                    /* ข้อมูลผู้นิเทศ */
                    CONCAT(IFNULL(ps.prefix_name,''), sp.fname, ' ', sp.lname) AS s_fullname,
                    sp.p_id AS s_pid, 
                    r.rank_name AS s_rank, 
                    o.office_name AS s_office

                FROM quick_win qw
                -- Join ครู
                LEFT JOIN teacher t ON qw.t_pid = t.t_pid
                LEFT JOIN prefix pt ON t.prefix_id = pt.prefix_id
                LEFT JOIN school s_school ON t.school_id = s_school.school_id
                LEFT JOIN position pos_t ON t.position_id = pos_t.position_id
                LEFT JOIN subject sub ON t.subject_id = sub.subject_id
                LEFT JOIN subject_group sg ON sub.subjectgroup_id = sg.subjectgroup_id
                
                -- Join ผู้นิเทศ
                LEFT JOIN supervisor sp ON qw.p_id = sp.p_id
                LEFT JOIN prefix ps ON sp.prefix_id = ps.prefix_id
                LEFT JOIN ranks r ON sp.rank_id = r.rank_id 
                LEFT JOIN office o ON sp.office_id = o.office_id
                
                WHERE qw.p_id = :pid
                  AND qw.t_pid = :tid
                  AND qw.supervision_date = :sdate";

    $stmt = $conn->prepare($sql_info);
    $stmt->execute([':pid' => $p_id, ':tid' => $t_id, ':sdate' => $date]);
    $info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        die("ไม่พบข้อมูลการประเมินจุดเน้นสำหรับรหัสนี้");
    }

    // ========================
    // 2. ดึงชื่อหัวข้อ Option จากที่บันทึกไว้แบบ 1/6/9
    // ========================
    $selected_topics = [];
    if (!empty($info['options'])) {
        // แยก string ด้วยตัว /
        $opt_ids = explode('/', $info['options']);
        // กรองเฉพาะค่าที่เป็นตัวเลข (เพื่อความปลอดภัยและป้องกันช่องว่าง)
        $opt_ids = array_filter($opt_ids, 'is_numeric');

        if (!empty($opt_ids)) {
            // สร้าง placeholder ตามจำนวน ID (?,?,?)
            $placeholders = str_repeat('?,', count($opt_ids) - 1) . '?';

            $sql_opts = "SELECT OptionText FROM quickwin_options WHERE OptionID IN ($placeholders)";
            $stmt_opts = $conn->prepare($sql_opts);

            // execute โดยส่ง array ของ ids เข้าไป (array_values เพื่อ reset index ให้เรียง 0,1,2..)
            $stmt_opts->execute(array_values($opt_ids));

            // ดึงผลลัพธ์ทั้งหมดมาเก็บใน array
            $selected_topics = $stmt_opts->fetchAll(PDO::FETCH_COLUMN);
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายงานผลการประเมินจุดเน้น (Quick Win)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/report_quickwin.css">
</head>

<body>
    <div class="container">
        <div class="report-container" style="position: relative;">

            <div class="text-center mb-5" style="margin-bottom: 25px !important;">
                <img src="images/logo.png" alt="โลโก้กระทรวงศึกษาธิการ" style="max-width: 80px; margin-bottom: 10px;">
                <p style="margin-bottom: 0; font-weight: bold; font-size: 0.95rem;">รายงานผลการประเมินจุดเน้น (Quick Win) ภาคเรียนที่ ๒ ปีการศึกษา ๒๕๖๘</p>
                <p style="margin-bottom: 0; font-weight: bold; font-size: 0.9rem;">สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาลำปาง ลำพูน</p>
            </div>

            <h5 class="header-title"><i class="fas fa-user-tie"></i> ข้อมูลผู้รับการประเมิน</h5>
            <div class="row mb-3">
                <div class="col-6">
                    <strong>ชื่อ-นามสกุล:</strong>
                    <?php echo htmlspecialchars($info['t_fullname']); ?>
                </div>
                <div class="col-6">
                    <strong>สังกัด (โรงเรียน):</strong>
                    <?php echo htmlspecialchars($info['t_school']); ?>
                </div>
                <div class="col-6">
                    <strong>ตำแหน่ง/วิทยฐานะ:</strong>
                    <?php echo htmlspecialchars($info['t_position']); ?>
                </div>
                <div class="col-6">
                    <strong>กลุ่มสาระการเรียนรู้:</strong>
                    <?php echo htmlspecialchars($info['learning_group']); ?>
                </div>
            </div>

            <h5 class="header-title"><i class="fas fa-user-check"></i> ข้อมูลผู้ประเมิน</h5>
            <div class="row mb-3">
                <div class="col-6">
                    <strong>ชื่อ-นามสกุล:</strong>
                    <?php echo htmlspecialchars($info['s_fullname']); ?>
                </div>
                <div class="col-6">
                    <strong>วิทยฐานะ/ตำแหน่ง:</strong>
                    <?php echo htmlspecialchars($info['s_rank']); ?> (<?php echo htmlspecialchars($info['s_office']); ?>)
                </div>
            </div>

            <h5 class="header-title"><i class="fas fa-clipboard-list"></i> ข้อมูลการประเมินจุดเน้น (Quick Win)</h5>
            <div class="info-box">
                <div class="row mb-2">
                    <div class="col-12">
                        <strong>หัวข้อจุดเน้นที่เลือก:</strong><br>
                        <div class="mt-2">
                            <?php if (!empty($selected_topics)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($selected_topics as $topic): ?>
                                        <li class="list-group-item bg-transparent py-1">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <?php echo htmlspecialchars($topic); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="text-muted">- ไม่ระบุ -</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($info['option_other'])): ?>
                    <div class="row mb-2 mt-2">
                        <div class="col-12">
                            <strong>รายละเอียดเพิ่มเติม (หมายเหตุ):</strong><br>
                            <div class="p-2 bg-light border rounded">
                                <?php echo nl2br(htmlspecialchars($info['option_other'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mt-3">
                    <div class="col-12">
                        <strong>วันที่ประเมิน:</strong> <?php echo date('d/m/Y', strtotime($info['supervision_date'])); ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($info['satisfaction_suggestion'])): ?>
                <div class="card mt-4 border-info">
                    <div class="card-header bg-info text-dark fw-bold">
                        <i class="fas fa-lightbulb"></i> ข้อเสนอแนะจากการประเมิน
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($info['satisfaction_suggestion'])); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center mt-5 no-print">
                <button onclick="window.close()" class="btn btn-danger me-2">
                    <i class="fas fa-times"></i> ปิดรายงาน
                </button>

                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> พิมพ์รายงาน
                </button>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>