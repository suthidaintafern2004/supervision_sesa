<?php
session_start();
require_once 'config/db_connect.php';

// ------------------------------------------------------------
// 1. ตรวจสอบสิทธิ์และการระบุตัวตน หากเปิดตรงนี้ คนที่ไม่ได้ล็อกอินจะไม่สามารถเข้าดูรายงานได้ ake 23:35 14/12/68
// ------------------------------------------------------------
// if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
//     header("Location: login.php");
//     exit();
// }

// 1.1 รับค่า (รองรับทั้ง GET และ POST)
$s_pid   = $_REQUEST['s_pid'] ?? $_SESSION['user_id'] ?? null;
$t_pid   = $_REQUEST['t_pid'] ?? null;
$subcode = $_REQUEST['sub_code'] ?? null;
$time    = $_REQUEST['time'] ?? null;

if (!$t_pid) {
    die("<div class='container mt-5'><div class='alert alert-danger text-center'>❌ Error: ไม่พบรหัสครูผู้รับการนิเทศ (t_pid missing)</div></div>");
}

// ------------------------------------------------------------
// 2. ดึงข้อมูลพื้นฐาน (Supervisor & Teacher)
// ------------------------------------------------------------
$supervisor = null;
$teacher = null;

try {
    // ข้อมูลผู้นิเทศ
    $sql_sp = "SELECT sp.*, p.prefix_name, pos.position_name, o.office_name
               FROM supervisor sp
               LEFT JOIN prefix p ON p.prefix_id = sp.prefix_id
               LEFT JOIN position pos ON pos.position_id = sp.position_id
               LEFT JOIN office o ON o.office_id = sp.office_id
               WHERE sp.p_id = :pid";
    $stmt = $conn->prepare($sql_sp);
    $stmt->execute([':pid' => $s_pid]);
    $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($supervisor) {
        $supervisor['fullname'] = trim(($supervisor['prefix_name'] ?? '') . ' ' . ($supervisor['fname'] ?? '') . ' ' . ($supervisor['lname'] ?? ''));
    }

    // ข้อมูลครู
    $sql_tc = "SELECT t.*, p.prefix_name, s.school_name, pos.position_name
               FROM teacher t
               LEFT JOIN prefix p ON p.prefix_id = t.prefix_id
               LEFT JOIN school s ON s.school_id = t.school_id
               LEFT JOIN position pos ON pos.position_id = t.position_id
               WHERE t.t_pid = :tpid";
    $stmt = $conn->prepare($sql_tc);
    $stmt->execute([':tpid' => $t_pid]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($teacher) {
        $teacher['fullname'] = trim(($teacher['prefix_name'] ?? '') . ' ' . ($teacher['f_name'] ?? '') . ' ' . ($teacher['l_name'] ?? ''));
    }
} catch (PDOException $e) { /* ข้าม Error */
}

// ------------------------------------------------------------
// 3. Logic การดึงข้อมูล KPI Session หลัก
// ------------------------------------------------------------
$kpi = null;
try {
    if ($subcode && $time) {
        $sql_kpi = "SELECT * FROM supervision_sessions 
                    WHERE supervisor_p_id = :spid AND teacher_t_pid = :tpid 
                    AND subject_code = :sub AND inspection_time = :time";
        $stmt = $conn->prepare($sql_kpi);
        $stmt->execute([':spid' => $s_pid, ':tpid' => $t_pid, ':sub' => $subcode, ':time' => $time]);
        $kpi = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $sql_kpi = "SELECT * FROM supervision_sessions 
                    WHERE supervisor_p_id = :spid AND teacher_t_pid = :tpid 
                    ORDER BY supervision_date DESC LIMIT 1";
        $stmt = $conn->prepare($sql_kpi);
        $stmt->execute([':spid' => $s_pid, ':tpid' => $t_pid]);
        $kpi = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($kpi) {
            $subcode = $kpi['subject_code'];
            $time = $kpi['inspection_time'];
        }
    }
} catch (PDOException $e) {
}

// ------------------------------------------------------------
// 4. ดึงรายละเอียดคะแนน (Indicators & Answers)
// ------------------------------------------------------------
$indicators_data = [];
$total_score = 0; // ตัวแปรเก็บคะแนนรวม
$eval_level = ''; // ระดับผลการประเมิน
$eval_color = ''; // สีแสดงผล

if ($kpi) {
    try {
        // 4.1 ดึงตัวชี้วัด
        $sql_ind = "SELECT i.id AS indicator_id, i.title, kis.suggestion_text 
                    FROM kpi_indicators i
                    LEFT JOIN kpi_indicator_suggestions kis 
                    ON i.id = kis.indicator_id 
                    AND kis.supervisor_p_id = :spid 
                    AND kis.teacher_t_pid = :tpid 
                    AND kis.subject_code = :sub 
                    AND kis.inspection_time = :time
                    ORDER BY i.display_order ASC";
        $stmt_ind = $conn->prepare($sql_ind);
        $stmt_ind->execute([':spid' => $s_pid, ':tpid' => $t_pid, ':sub' => $subcode, ':time' => $time]);
        $indicators = $stmt_ind->fetchAll(PDO::FETCH_ASSOC);

        // 4.2 ดึงคำถาม + คะแนน
        $sql_qa = "SELECT q.id AS question_id, q.indicator_id, q.question_text, ka.rating_score
                   FROM kpi_questions q
                   LEFT JOIN kpi_answers ka 
                   ON q.id = ka.question_id 
                   AND ka.supervisor_p_id = :spid 
                   AND ka.teacher_t_pid = :tpid 
                   AND ka.subject_code = :sub 
                   AND ka.inspection_time = :time
                   ORDER BY q.indicator_id ASC, q.display_order ASC";
        $stmt_qa = $conn->prepare($sql_qa);
        $stmt_qa->execute([':spid' => $s_pid, ':tpid' => $t_pid, ':sub' => $subcode, ':time' => $time]);
        $questions = $stmt_qa->fetchAll(PDO::FETCH_ASSOC);

        // --- ส่วนที่เพิ่ม: คำนวณคะแนนรวม ---
        foreach ($questions as $q) {
            $total_score += (int)($q['rating_score'] ?? 0);
        }

        // กำหนดระดับคุณภาพ
        if ($total_score >= 54) {
            $eval_level = 'ดีมาก';
            $eval_color = 'success'; // สีเขียว
        } elseif ($total_score >= 36) {
            $eval_level = 'ดี';
            $eval_color = 'primary'; // สีน้ำเงิน
        } elseif ($total_score >= 18) {
            $eval_level = 'พอใช้';
            $eval_color = 'warning'; // สีเหลือง/ส้ม
        } else {
            $eval_level = 'ปรับปรุง';
            $eval_color = 'danger'; // สีแดง
        }
        // ---------------------------------

        $questions_map = [];
        foreach ($questions as $q) {
            $questions_map[$q['indicator_id']][] = $q;
        }

        foreach ($indicators as $ind) {
            $ind['questions'] = $questions_map[$ind['indicator_id']] ?? [];
            $indicators_data[] = $ind;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// ------------------------------------------------------------
// 5. ดึงรูปภาพประกอบ
// ------------------------------------------------------------
$images = [];
if ($kpi) {
    try {
        $sql_img = "SELECT file_name FROM images 
                    WHERE supervisor_p_id = :spid AND teacher_t_pid = :tpid 
                    AND subject_code = :sub AND inspection_time = :time";
        $stmt = $conn->prepare($sql_img);
        $stmt->execute([':spid' => $s_pid, ':tpid' => $t_pid, ':sub' => $subcode, ':time' => $time]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานผลการนิเทศ (ฉบับเต็ม)</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/report.css">
</head>

<body>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="text-center mb-5 mt-4" style="margin-bottom: 20px !important;">
                        <img src="images/logo.png" alt="โลโก้กระทรวงศึกษาธิการ" style="max-width: 80px; margin-bottom: 10px;">
                        <p style="margin-bottom: 0; font-weight: bold; font-size: 0.95rem;">รายงานผลการประเมินจุดเน้น (Quick Win) ภาคเรียนที่ ๒ ปีการศึกษา ๒๕๖๘</p>
                        <p style="margin-bottom: 0; font-weight: bold; font-size: 0.9rem;">สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาลำปาง ลำพูน</p>
                    </div>

                    <div class="card-body p-4 p-md-5">

                        <div class="alert alert-light border border-secondary border-opacity-25 rounded-3 mb-4">
                            <h5 class="text-primary fw-bold mb-3 border-bottom pb-2">
                                <i class="fas fa-id-card"></i> ข้อมูลพื้นฐาน
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">ผู้นิเทศ</small>
                                    <span class="fs-5 fw-bold text-dark"><?= htmlspecialchars($supervisor['fullname'] ?? '-') ?></span>
                                    <br><small class="text-muted"><?= htmlspecialchars($supervisor['position_name'] ?? '') ?></small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">ผู้รับการนิเทศ</small>
                                    <span class="fs-5 fw-bold text-dark"><?= htmlspecialchars($teacher['fullname'] ?? '-') ?></span>
                                    <br><small class="text-muted"><?= htmlspecialchars($teacher['school_name'] ?? '') ?></small>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="d-flex gap-3 flex-wrap">
                                        <span class="badge bg-info text-dark"><i class="fas fa-book"></i> วิชา: <?= htmlspecialchars($kpi['subject_code'] ?? '') ?> - <?= htmlspecialchars($kpi['subject_name'] ?? '-') ?></span>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> ครั้งที่: <?= htmlspecialchars($kpi['inspection_time'] ?? '-') ?></span>
                                        <span class="badge bg-secondary"><i class="fas fa-calendar-alt"></i> วันที่: <?= !empty($kpi['supervision_date']) ? date('d/m/Y', strtotime($kpi['supervision_date'])) : '-' ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($kpi): ?>

                            <h5 class="text-primary fw-bold mb-3">
                                <i class="fas fa-list-ol"></i> ผลการประเมินตามตัวชี้วัด
                            </h5>

                            <div class="accordion mb-4" id="kpiAccordion">
                                <?php foreach ($indicators_data as $index => $ind): ?>
                                    <div class="card mb-3 border shadow-sm kpi-card">
                                        <div class="card-header bg-light">
                                            <strong><?= htmlspecialchars($ind['title']) ?></strong>
                                        </div>
                                        <div class="card-body p-0">
                                            <table class="table table-bordered mb-0 table-score align-middle">
                                                <thead>
                                                    <tr class="text-center">
                                                        <th style="width: 85%;">รายการประเมิน</th>
                                                        <th style="width: 15%;">คะแนน</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($ind['questions'] as $q):
                                                        $score = $q['rating_score'] ?? null;
                                                        $scoreClass = '';
                                                        if ($score == 3) $scoreClass = 'score-3';
                                                        elseif ($score == 2) $scoreClass = 'score-2';
                                                        elseif ($score == 1) $scoreClass = 'score-1';
                                                        elseif ($score == 0 && $score !== null) $scoreClass = 'score-0';
                                                    ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($q['question_text']) ?></td>
                                                            <td class="text-center">
                                                                <?php if ($score !== null): ?>
                                                                    <span class="score-circle <?= $scoreClass ?>"><?= $score ?></span>
                                                                <?php else: ?>
                                                                    <span class="text-muted">-</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>

                                            <?php if (!empty($ind['suggestion_text'])): ?>
                                                <div class="p-3 bg-white border-top">
                                                    <div class="suggestion-box">
                                                        <strong><i class="fas fa-lightbulb"></i> ข้อค้นพบ / ข้อเสนอแนะ:</strong>
                                                        <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($ind['suggestion_text'])) ?></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="d-flex justify-content-end align-items-center mb-3 mt-2 border-top pt-2">
                                <div class="lg-justify-content-end d-flex align-items-center">
                                    <span class="fw-bold text-muted me-2">สรุปผลการประเมิน:</span>
                                    <span class="fw-bold text-<?= $eval_color ?> me-2" style="font-size: 1.1em;"><?= $total_score ?> / 72 คะแนน</span>
                                    <span class="badge bg-<?= $eval_color ?>">ระดับ <?= $eval_level ?></span>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="text-primary fw-bold mb-2">
                                    <i class="fas fa-comment-dots"></i> ข้อเสนอแนะเพิ่มเติม (ภาพรวม)
                                </h5>
                                <div class="alert alert-secondary shadow-sm">
                                    <?= !empty($kpi['overall_suggestion']) ? nl2br(htmlspecialchars($kpi['overall_suggestion'])) : '<span class="text-muted">- ไม่มีข้อเสนอแนะ -</span>' ?>
                                </div>
                            </div>

                            <?php if (!empty($images)): ?>
                                <div class="mt-4 pt-3 border-top">
                                    <h5 class="text-primary fw-bold mb-3">
                                        <i class="fas fa-images"></i> หลักฐาน/รูปภาพประกอบ
                                    </h5>
                                    <div class="row g-3">
                                        <?php foreach ($images as $img): ?>
                                            <div class="col-6 col-md-3">
                                                <a href="uploads/<?= htmlspecialchars($img['file_name']) ?>" target="_blank">
                                                    <img src="uploads/<?= htmlspecialchars($img['file_name']) ?>" class="img-gallery shadow-sm" alt="Evidence">
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
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

                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fa-3x mb-3"></i><br>
                                <h4>ไม่พบข้อมูลการนิเทศ</h4>
                                <p>ยังไม่มีการบันทึกข้อมูล KPI ในรอบการนิเทศนี้</p>
                                <form method="POST" action="session_details.php" style="display:inline;">
                                    <input type="hidden" name="teacher_pid" value="<?= htmlspecialchars($t_pid) ?>">
                                    <button type="submit" class="btn btn-danger mt-3">
                                        <i class="fas fa-arrow-left"></i> กลับหน้ารายการ
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>