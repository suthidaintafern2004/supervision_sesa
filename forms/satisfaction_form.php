<?php
// ==================================================
// forms/satisfaction_form.php
// FINAL – clean + production-ready
// รองรับ normal + quickwin
// ==================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db_connect.php';

/* ==================================================
   1) รับโหมด
================================================== */
$mode = $_POST['mode'] ?? $_GET['mode'] ?? null;

if (!in_array($mode, ['normal', 'quickwin'], true)) {
    die('<div class="alert alert-danger text-center mt-5">โหมดการประเมินไม่ถูกต้อง</div>');
}

/* ==================================================
   2) POST → เก็บ context ลง session
================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($mode === 'normal') {

        foreach (['s_pid', 't_pid', 'sub_code', 'time'] as $k) {
            if (empty($_POST[$k])) {
                die("ข้อมูล normal ไม่ครบ ({$k})");
            }
        }

        $_SESSION['normal_context'] = [
            's_pid'    => $_POST['s_pid'],
            't_pid'    => $_POST['t_pid'],
            'sub_code' => $_POST['sub_code'],
            'time'     => $_POST['time']
        ];
    }

    if ($mode === 'quickwin') {

        foreach (['t_pid', 'p_id', 'date'] as $k) {
            if (empty($_POST[$k])) {
                die("ข้อมูล quickwin ไม่ครบ ({$k})");
            }
        }

        $_SESSION['quickwin_context'] = [
            't_pid' => $_POST['t_pid'],
            'p_id'  => $_POST['p_id'],
            'date'  => $_POST['date']
        ];
    }

    header("Location: satisfaction_form.php?mode={$mode}");
    exit;
}

/* ==================================================
   3) GET → อ่าน context จาก session
================================================== */
if ($mode === 'normal') {

    if (!isset($_SESSION['normal_context'])) {
        die('<div class="alert alert-danger text-center mt-5">ข้อมูลการประเมิน (normal) หาย</div>');
    }

    $data = $_SESSION['normal_context'];
}

if ($mode === 'quickwin') {

    if (!isset($_SESSION['quickwin_context'])) {
        die('<div class="alert alert-danger text-center mt-5">ข้อมูลการประเมิน (quickwin) หาย</div>');
    }

    $data = $_SESSION['quickwin_context'];
}

$session_info = null;
$status = 0;

/* ==================================================
   4) NORMAL MODE
================================================== */
if ($mode === 'normal') {

    $sql = "
        SELECT
            ss.supervision_date,
            ss.subject_name,
            ss.satisfaction_submitted AS status,
            CONCAT(ps.prefix_name, sp.fname,' ',sp.lname) AS supervisor_full_name,
            CONCAT(pt.prefix_name, t.f_name,' ',t.l_name) AS teacher_full_name
        FROM supervision_sessions ss
        JOIN supervisor sp ON ss.supervisor_p_id = sp.p_id
        JOIN teacher t ON ss.teacher_t_pid = t.t_pid
        LEFT JOIN prefix ps ON sp.prefix_id = ps.prefix_id
        LEFT JOIN prefix pt ON t.prefix_id = pt.prefix_id
        WHERE ss.supervisor_p_id = ?
          AND ss.teacher_t_pid   = ?
          AND ss.subject_code    = ?
          AND ss.inspection_time = ?
          AND ss.deleted_at IS NULL
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['s_pid'],
        $data['t_pid'],
        $data['sub_code'],
        (int)$data['time']
    ]);

    $session_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ==================================================
   5) QUICKWIN MODE
================================================== */
if ($mode === 'quickwin') {

    $sql = "
        SELECT
            qw.supervision_date,
            qo.OptionText AS subject_name,
            CONCAT(p.prefix_name, s.fname,' ',s.lname) AS supervisor_full_name,
            CONCAT(pt.prefix_name, t.f_name,' ',t.l_name) AS teacher_full_name,
            CASE WHEN EXISTS (
                SELECT 1 FROM quickwin_satisfaction_answers qsa
                WHERE qsa.t_pid = qw.t_pid
                  AND qsa.p_id  = qw.p_id
                  AND qsa.supervision_date = qw.supervision_date
            ) THEN 1 ELSE 0 END AS status
        FROM quick_win qw
        JOIN supervisor s ON qw.p_id = s.p_id
        JOIN teacher t ON qw.t_pid = t.t_pid
        LEFT JOIN prefix p ON s.prefix_id = p.prefix_id
        LEFT JOIN prefix pt ON t.prefix_id = pt.prefix_id
        LEFT JOIN quickwin_options qo ON qw.options = qo.OptionID
        WHERE qw.t_pid = ?
          AND qw.p_id  = ?
          AND qw.supervision_date = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['t_pid'],
        $data['p_id'],
        $data['date']
    ]);

    $session_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$session_info) {
    die('<div class="alert alert-danger text-center mt-5">ไม่พบข้อมูลการนิเทศ</div>');
}

$status = (int)$session_info['status'];

/* ==================================================
   6) คำถามประเมิน
================================================== */
$q = $conn->query("SELECT id, question_text FROM satisfaction_questions ORDER BY id");
$questions = $q->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แบบประเมินความพึงพอใจ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f1f3f4;
            font-family: 'Prompt', 'Segoe UI', sans-serif;
        }

        .form-card {
            max-width: 900px;
            margin: auto;
            border-radius: 16px;
            border: none;
        }

        .form-header {
            background: linear-gradient(135deg, #fbbc04, #fdd663);
            color: #000;
            border-radius: 16px 16px 0 0;
            padding: 24px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .question-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #e0e0e0;
        }

        .question-title {
            font-weight: 500;
            margin-bottom: 12px;
        }

        .rating-group {
            display: flex;
            justify-content: space-between;
            max-width: 420px;
        }

        .rating-group input {
            display: none;
        }

        .rating-group label {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-weight: bold;
            transition: all .2s;
            background: #fafafa;
        }

        .rating-group input:checked+label {
            background: #1a73e8;
            color: #fff;
            border-color: #1a73e8;
            transform: scale(1.1);
        }

        textarea {
            border-radius: 12px;
        }

        .submit-btn {
            background: #1a73e8;
            border: none;
            border-radius: 24px;
            padding: 10px 32px;
            font-size: 1.1rem;
        }

        .question-number {
            color: #000000;
            font-weight: 600;
            margin-right: 6px;
        }

        .form-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 999px;
            /* โค้งเท่ากัน */
            padding: 12px 36px;
            /* ขนาดเท่ากัน */
            font-size: 1.1rem;
            font-weight: 500;
            min-width: 200px;

            cursor: pointer;
            transition: all .2s ease;
            text-decoration: none;
        }

        /* ปุ่มหลัก */
        .form-btn.primary {
            background: #1a73e8;
            color: #fff;
            border: none;
        }

        .form-btn.primary:hover {
            background: #1558b0;
        }

        /* ปุ่มกลับ */
        .form-btn.secondary {
            background: #ea4335;
            color: #fff;
            border: none;
        }

        .form-btn.secondary:hover {
            background: #c5221f;
        }

        .btn-lg {
            min-width: 200px;
            font-weight: 500;
        }

        .info-label {
            font-size: 0.9rem;
            font-weight: 600;
            /* หนาขึ้น */
            color: #202124;
            /* ดำเข้มแบบ Google */
            letter-spacing: 0.2px;
        }

        .info-value {
            font-size: 1.05rem;
            font-weight: 400;
            color: #5f6368;
            /* เทาอ่อนกว่า */
        }

        .question-card.invalid {
            border: 2px solid #dc3545;
            background: #fff5f5;
        }

        @media (max-width: 576px) {
            .d-flex.justify-content-between {
                flex-direction: column-reverse;
                gap: 12px;
            }

            .btn-lg {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="container my-5">
        <div class="card form-card shadow-sm">

            <div class="form-header">
                แบบประเมินความพึงพอใจ
            </div>

            <div class="card-body p-4">

                <div class="info-box mb-4">

                    <div class="row mb-2">
                        <div class="col-md-6">
                            <span class="info-label">ครูผู้รับการนิเทศก์</span><br>
                            <span class="info-value">
                                <?= htmlspecialchars($session_info['teacher_full_name']) ?>
                            </span>
                        </div>

                        <div class="col-md-6">
                            <span class="info-label">ผู้นิเทศก์</span><br>
                            <span class="info-value">
                                <?= htmlspecialchars($session_info['supervisor_full_name']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <span class="info-label">หัวข้อ</span><br>
                            <span class="info-value">
                                <?= htmlspecialchars($session_info['subject_name']) ?>
                            </span>
                        </div>

                        <div class="col-md-6">
                            <span class="info-label">วันที่</span><br>
                            <span class="info-value">
                                <?= (new DateTime($session_info['supervision_date']))->format('d/m/Y') ?>
                            </span>
                        </div>
                    </div>

                </div>


                <?php if ($status === 1): ?>
                    <div class="alert alert-success text-center">ท่านได้ทำการประเมินแล้ว</div>
                <?php else: ?>

                    <form method="POST" action="save_satisfaction.php" id="satisfactionForm">
                        <input type="hidden" name="mode" value="<?= $mode ?>">

                        <?php foreach ($data as $k => $v): ?>
                            <input type="hidden" name="<?= $k ?>" value="<?= htmlspecialchars($v) ?>">
                        <?php endforeach; ?>

                        <?php foreach ($questions as $q): ?>
                            <div class="question-card">
                                <div class="question-title">
                                    <span class="question-number">
                                        <?= (int)$q['id'] ?>.
                                    </span>
                                    <?= htmlspecialchars($q['question_text']) ?>
                                </div>

                                <div class="rating-group">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <input
                                            type="radio"
                                            id="q<?= $q['id'] ?>_<?= $i ?>"
                                            name="ratings[<?= $q['id'] ?>]"
                                            value="<?= $i ?>">
                                        <label for="q<?= $q['id'] ?>_<?= $i ?>">
                                            <?= $i ?>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>


                        <div class="question-card">
                            <label class="question-title">
                                ข้อเสนอแนะเพิ่มเติม (ไม่บังคับ)
                            </label>
                            <textarea name="overall_suggestion" rows="4" class="form-control"></textarea>
                        </div>

                        <div class="text-center mt-4">

                            <button type="submit"
                                class="btn btn-primary btn-lg rounded-pill px-4">
                                บันทึกการประเมิน
                            </button>

                            <a href="../session_details.php?teacher_pid=<?= urlencode($data['t_pid']) ?>"
                                class="btn btn-danger btn-lg rounded-pill px-4">
                                ยกเลิก
                            </a>
                        </div>

                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.getElementById('satisfactionForm').addEventListener('submit', function(e) {

            const questions = document.querySelectorAll('[name^="ratings["]');
            const answered = new Set();

            questions.forEach(input => {
                if (input.checked) {
                    answered.add(input.name);
                }
            });

            // นับจำนวนคำถามจริงจาก PHP
            const totalQuestions = <?= count($questions) ?>;

            if (answered.size < totalQuestions) {
                e.preventDefault(); // ❌ ไม่ให้ submit

                Swal.fire({
                    icon: 'warning',
                    title: 'ประเมินยังไม่ครบ',
                    text: 'กรุณาเลือกคะแนนให้ครบทุกข้อก่อนบันทึก',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#dc3545'
                });
            }
        });
    </script>

</body>

</html>