<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===============================
// คำนวณปีการศึกษา (เหมือนตอนบันทึกครั้งแรก)
// ===============================
$todayYear  = date('Y') + 543;
$todayMonth = date('n');

// ถ้าเดือน >= พ.ค. ถือว่าเป็นปีการศึกษาใหม่
$currentAcademicYear = ($todayMonth >= 5)
    ? $todayYear
    : $todayYear - 1;

$academicYearOptions = [
    $currentAcademicYear - 1, // ปีก่อน
    $currentAcademicYear,     // ปีปัจจุบัน
    $currentAcademicYear + 1  // ปีหน้า
];

require_once '../config/db_connect.php';

/* ===============================
   รับค่าจาก URL
================================ */
$supervisor_id   = $_SESSION['user_id'] ?? '';
$t_pid           = $_GET['t_pid'] ?? '';
$subject_code    = $_GET['subject_code'] ?? '';
$inspection_time = $_GET['inspection_time'] ?? '';
$isAdmin         = ($_SESSION['role'] ?? '') === 'admin';

if (!$t_pid || !$subject_code || !$inspection_time) {
    die('<div class="alert alert-danger text-center">ข้อมูลไม่ครบถ้วน</div>');
}

/* ===============================
   ดึงข้อมูล session + ครู + ศน.
================================ */
$sqlSession = "
SELECT ss.*,
       CONCAT(pt.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name,
       CONCAT(ps.prefix_name,' ',sp.fname,' ',sp.lname) AS supervisor_name
FROM supervision_sessions ss
LEFT JOIN teacher t ON ss.teacher_t_pid = t.t_pid
LEFT JOIN prefix pt ON t.prefix_id = pt.prefix_id
LEFT JOIN supervisor sp ON ss.supervisor_p_id = sp.p_id
LEFT JOIN prefix ps ON sp.prefix_id = ps.prefix_id
WHERE ss.teacher_t_pid = ?
  AND ss.subject_code = ?
  AND ss.inspection_time = ?
  AND ss.deleted_at IS NULL
";

$params = [$t_pid, $subject_code, $inspection_time];

if (!$isAdmin) {
    $sqlSession .= " AND ss.supervisor_p_id = ? ";
    $params[] = $supervisor_id;
}

$stmt_session = $conn->prepare($sqlSession);
$stmt_session->execute($params);
$session_data = $stmt_session->fetch(PDO::FETCH_ASSOC);

if (!$session_data) {
    die('<div class="alert alert-warning text-center">ไม่พบข้อมูลการนิเทศ</div>');
}

/* ===============================
   รายชื่อครู / ศน. (admin)
================================ */
$teachers = [];
$supervisors = [];

if ($isAdmin) {
    $teachers = $conn->query("
        SELECT t.t_pid,
               CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name
        FROM teacher t
        LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
        ORDER BY t.f_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    $supervisors = $conn->query("
        SELECT sp.p_id,
               CONCAT(p.prefix_name,' ',sp.fname,' ',sp.lname) AS supervisor_name
        FROM supervisor sp
        LEFT JOIN prefix p ON sp.prefix_id = p.prefix_id
        ORDER BY sp.fname
    ")->fetchAll(PDO::FETCH_ASSOC);
}

/* ===============================
   ดึงคะแนนเดิม (แก้ bug)
================================ */
$sqlRating = "
SELECT question_id, rating_score
FROM kpi_answers
WHERE teacher_t_pid = ?
  AND subject_code = ?
  AND inspection_time = ?
";
$params = [$t_pid, $subject_code, $inspection_time];

if (!$isAdmin) {
    $sqlRating .= " AND supervisor_p_id = ? ";
    $params[] = $supervisor_id;
}

$stmt = $conn->prepare($sqlRating);
$stmt->execute($params);

$ratings = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $ratings[$r['question_id']] = $r['rating_score'];
}

/* ===============================
   KPI + คำถาม
================================ */
$sql_kpi = "
SELECT ind.id AS indicator_id,
       ind.title AS indicator_title,
       q.id AS question_id,
       q.question_text
FROM kpi_indicators ind
LEFT JOIN kpi_questions q ON ind.id = q.indicator_id
ORDER BY ind.display_order, q.display_order
";
$kpi_res = $conn->query($sql_kpi)->fetchAll(PDO::FETCH_ASSOC);

$indicators = [];
$total_questions_count = 0;
foreach ($kpi_res as $row) {
    $iid = $row['indicator_id'];
    $indicators[$iid]['title'] = $row['indicator_title'];
    if ($row['question_id']) {
        $indicators[$iid]['questions'][] = $row;
        $total_questions_count++;
    }
}

/* ===============================
   ข้อเสนอแนะรายตัวบ่งชี้
================================ */
$sqlNote = "
SELECT indicator_id, suggestion_text
FROM kpi_indicator_suggestions
WHERE teacher_t_pid = ?
  AND subject_code = ?
  AND inspection_time = ?
";
$params = [$t_pid, $subject_code, $inspection_time];

if (!$isAdmin) {
    $sqlNote .= " AND supervisor_p_id = ? ";
    $params[] = $supervisor_id;
}

$stmt = $conn->prepare($sqlNote);
$stmt->execute($params);
$indicator_suggestions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/* ===============================
   รูปภาพเดิม
================================ */
$sqlImg = "
SELECT file_name
FROM images
WHERE teacher_t_pid = ?
  AND subject_code = ?
  AND inspection_time = ?
";
$params = [$t_pid, $subject_code, $inspection_time];

if (!$isAdmin) {
    $sqlImg .= " AND supervisor_p_id = ? ";
    $params[] = $supervisor_id;
}

$stmt = $conn->prepare($sqlImg);
$stmt->execute($params);
$existing_images_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แก้ไขข้อมูลการนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/kpi_style.css">
    <link rel="stylesheet" href="../css/styles.css">

    <style>
        /* ===== CSS เดิมของคุณ (คงไว้ทั้งหมด) ===== */
        .img-item {
            width: 180px;
            height: 135px;
            position: relative;
            border-radius: 10px;
            border: 2px solid #ddd;
            overflow: hidden;
            background: #eee;
        }

        .kpi-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .btn-remove-custom {
            position: absolute;
            top: 5px;
            right: 5px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            z-index: 20;
        }

        .badge-new {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: #0d6efd;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .modern-input {
            border-radius: 14px;
            padding: 10px 14px;
            font-size: 0.95rem;
            border: 1px solid #dee2e6;
            transition: all .2s ease;
        }

        .modern-input:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
        }

        /* ===== Select2 Modern ===== */
        .select2-container--default .select2-selection--single {
            height: 46px;
            border-radius: 14px;
            border: 1px solid #dee2e6;
            padding: 8px 12px;
        }

        .select2-selection__rendered {
            line-height: 28px !important;
            font-size: 0.95rem;
        }

        .select2-selection__arrow {
            height: 44px !important;
        }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #0d6efd;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">

        <form id="evaluationForm" method="POST" action="update_kpi_data.php" enctype="multipart/form-data">

            <input type="hidden" name="redirect_back"
                value="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '../index.php') ?>">
            <input type="hidden" id="formSnapshot" value="">
            <input type="hidden" name="old_t_pid" value="<?= $session_data['teacher_t_pid'] ?>">
            <input type="hidden" name="old_subject_code" value="<?= $session_data['subject_code'] ?>">
            <input type="hidden" name="old_inspection_time" value="<?= $session_data['inspection_time'] ?>">
            <input type="hidden" name="old_supervisor_p_id" value="<?= $session_data['supervisor_p_id'] ?>">

            <!-- ===== ส่วนครู / ศน. ===== -->
            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-body p-4 bg-white">
                    <h2 class="text-primary fw-bold mb-4">แก้ไขข้อมูลการนิเทศ</h2>

                    <div class="row g-4">

                        <!-- ผู้นิเทศ -->
                        <div class="col-md-6">
                            <label class="fw-bold">ผู้นิเทศ</label>
                            <?php if ($isAdmin): ?>
                                <select name="supervisor_p_id" class="form-select modern-input">
                                    <?php foreach ($supervisors as $s): ?>
                                        <option value="<?= $s['p_id'] ?>"
                                            <?= $s['p_id'] == $session_data['supervisor_p_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['supervisor_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="form-control modern-input bg-light">
                                    <?= htmlspecialchars($session_data['supervisor_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- ผู้รับนิเทศ -->
                        <div class="col-md-6">
                            <label class="fw-bold">ผู้รับนิเทศ</label>
                            <?php if ($isAdmin): ?>
                                <select name="teacher_t_pid"
                                    class="form-select js-teacher-search modern-input"
                                    data-placeholder="พิมพ์ชื่อครูเพื่อค้นหา...">
                                    <option></option>
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= $t['t_pid'] ?>"
                                            <?= $t['t_pid'] == $session_data['teacher_t_pid'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['teacher_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="form-control modern-input bg-light">
                                    <?= htmlspecialchars($session_data['teacher_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- รหัสวิชา -->
                        <div class="col-md-2">
                            <label class="fw-bold">รหัสวิชา</label>
                            <input type="text" name="subject_code"
                                class="form-control modern-input"
                                value="<?= htmlspecialchars($session_data['subject_code']) ?>" required>
                        </div>

                        <!-- ชื่อวิชา -->
                        <div class="col-md-4">
                            <label class="fw-bold">ชื่อวิชา</label>
                            <input type="text" name="subject_name"
                                class="form-control modern-input"
                                value="<?= htmlspecialchars($session_data['subject_name']) ?>" required>
                        </div>

                        <!-- ครั้งที่นิเทศ -->
                        <div class="col-md-2">
                            <label class="fw-bold">ครั้งที่นิเทศ</label>
                            <select name="inspection_time" class="form-select modern-input">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>"
                                        <?= ($session_data['inspection_time'] == $i) ? 'selected' : '' ?>>
                                        ครั้งที่ <?= $i ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- วันที่นิเทศ -->
                        <div class="col-md-2">
                            <label class="fw-bold">วันที่นิเทศ</label>
                            <input type="date"
                                name="inspection_date"
                                class="form-control modern-input"
                                value="<?= htmlspecialchars($session_data['inspection_date']) ?>"
                                required>
                        </div>

                        <!-- ปีการศึกษา -->
                        <div class="col-md-2">
                            <label class="fw-bold">ปีการศึกษา</label>

                            <?php if ($isAdmin): ?>
                                <select name="academic_year"
                                    class="form-select modern-input"
                                    required>

                                    <?php foreach ($academicYearOptions as $year): ?>
                                        <option value="<?= $year ?>"
                                            <?= ($session_data['academic_year'] == $year) ? 'selected' : '' ?>>
                                            <?= $year ?>
                                        </option>
                                    <?php endforeach; ?>

                                </select>
                            <?php else: ?>
                                <div class="form-control modern-input bg-light">
                                    ปีการศึกษา <?= htmlspecialchars($session_data['academic_year']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>


            <?php foreach ($indicators as $iid => $data): ?>
                <div class="indicator-section">
                    <div class="indicator-title">
                        <?= htmlspecialchars($data['title']) ?>
                    </div>

                    <?php foreach ($data['questions'] as $q): ?>
                        <div class="question-item">
                            <label class="question-label"><?= htmlspecialchars($q['question_text']) ?></label>
                            <div class="rating-container">
                                <?php for ($i = 3; $i >= 0; $i--):
                                    $checked = (isset($ratings[$q['question_id']]) && $ratings[$q['question_id']] == $i) ? 'checked' : '';
                                    $u_id = "q_" . $q['question_id'] . "_" . $i;
                                ?>
                                    <input type="radio" name="ratings[<?= $q['question_id'] ?>]" id="<?= $u_id ?>" value="<?= $i ?>" <?= $checked ?> required>
                                    <label for="<?= $u_id ?>"><?= $i ?></label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-4 p-3 bg-light rounded-3">
                        <label class="fw-bold text-dark mb-2 small">
                            📝 ข้อค้นพบ / ข้อเสนอแนะ
                        </label>
                        <textarea name="indicator_suggestions[<?= $iid ?>]" class="form-control border-0" rows="2" placeholder="ระบุข้อเสนอแนะเพิ่มเติมที่นี่..."><?= htmlspecialchars($indicator_suggestions[$iid] ?? '') ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-header bg-white fw-bold py-3">📝 ข้อเสนอแนะเพิ่มเติม</div>
                <div class="card-body">
                    <textarea class="form-control" name="overall_suggestion" rows="3" placeholder="ระบุข้อเสนอแนะเพิ่มเติมที่นี่..."><?= htmlspecialchars($session_data['overall_suggestion']) ?></textarea>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-header bg-white fw-bold py-3">📷 รูปภาพกิจกรรม (สูงสุด 2 รูป)</div>
                <div class="card-body">
                    <div class="d-flex gap-3 flex-wrap mb-3" id="imageContainer">
                        <?php foreach ($existing_images_db as $img): ?>
                            <div class="img-item existing-img">
                                <img src="../uploads/<?= htmlspecialchars($img['file_name']) ?>" class="kpi-image">
                                <input type="hidden" name="existing_images[]" value="<?= htmlspecialchars($img['file_name']) ?>">
                                <button type="button" class="btn-remove-custom" onclick="this.parentElement.remove()">×</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="file" id="imageInput" name="images[]" class="form-control" accept="image/*" multiple>
                    <small class="text-danger">* หากเลือกรูปใหม่ รูปที่เคยพรีวิวไว้ก่อนกดบันทึกจะถูกแทนที่ (กรุณาเลือกทีเดียว 1 หรือ 2 รูป)</small>
                </div>
            </div>

            <div class="text-center mb-5 d-flex justify-content-center gap-3 flex-wrap">

                <!-- บันทึก -->
                <?php if ($isAdmin): ?>
                    <button type="button"
                        class="btn btn-modern btn-save shadow"
                        onclick="confirmSave()">
                        <i class="fas fa-save"></i> บันทึกการแก้ไข
                    </button>
                <?php endif; ?>

                <!-- ยกเลิก -->
                <button type="button"
                    class="btn btn-modern btn-cancel shadow"
                    onclick="window.location.href='<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '../index.php') ?>'">
                    <i class="fas fa-arrow-left"></i> กลับหน้าที่แล้ว
                </button>

            </div>

        </form>

        <form id="deleteForm" action="delete_kpi_session.php" method="POST">
            <input type="hidden" name="t_pid" value="<?= htmlspecialchars($t_pid) ?>">
            <input type="hidden" name="subject_code" value="<?= htmlspecialchars($subject_code) ?>">
            <input type="hidden" name="inspection_time" value="<?= htmlspecialchars($inspection_time) ?>">
        </form>


        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            function showPopup(icon, title, text, timer = 3000) {
                Swal.fire({
                    icon: icon,
                    title: title,
                    text: text,
                    timer: timer,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
        </script>

        <script>
            const imageInput = document.getElementById('imageInput');
            const imageContainer = document.getElementById('imageContainer');
            const totalQuestions = <?= $total_questions_count ?>;

            imageInput.addEventListener('change', function() {
                const existingCount = imageContainer.querySelectorAll('.existing-img').length;
                const files = Array.from(this.files);

                if ((existingCount + files.length) > 2) {
                    alert('รูปภาพรวมทั้งหมดต้องไม่เกิน 2 รูปครับ (ตอนนี้มีรูปเดิม ' + existingCount + ' รูป)');
                    this.value = '';
                    return;
                }

                // ล้างพรีวิวเก่าของ "รูปใหม่" ออกก่อน เพื่อแสดงรูปที่เพิ่งเลือกเข้าไปล่าสุด
                imageContainer.querySelectorAll('.new-preview').forEach(p => p.remove());

                files.forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.classList.add('img-item', 'new-preview');
                        div.innerHTML = `
            <img src="${e.target.result}" class="kpi-image" style="border:2px solid #0d6efd">
            <span class="badge-new">รูปใหม่</span>
            <button type="button" class="btn-remove-custom" onclick="this.parentElement.remove()">×</button>
          `;
                        imageContainer.appendChild(div);
                    }
                    reader.readAsDataURL(file);
                });
            });

            function confirmSave() {

                const checked = document.querySelectorAll('input[type="radio"]:checked');
                if (checked.length < totalQuestions) {
                    showPopup(
                        'warning',
                        '⚠️ ตอบคำถามไม่ครบ',
                        `คุณตอบไปแล้ว ${checked.length} / ${totalQuestions} ข้อ`
                    );
                    return;
                }

                const currentSnapshot = getFormSnapshot();
                if (currentSnapshot === initialSnapshot) {
                    Swal.fire({
                        icon: 'info',
                        title: 'ℹ️ ไม่มีการเปลี่ยนแปลง',
                        text: 'คุณยังไม่ได้แก้ไขข้อมูลใด ๆ',
                    });
                    return;
                }

                Swal.fire({
                    icon: 'question',
                    title: '📌 ยืนยันการแก้ไข',
                    text: 'ต้องการบันทึกการแก้ไขข้อมูลนี้ใช่หรือไม่?',
                    showCancelButton: true,
                    confirmButtonText: '💾 บันทึก',
                    cancelButtonText: '❌ ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('evaluationForm').submit();
                    }
                });
            }

            function confirmDelete() {
                Swal.fire({
                    icon: 'warning',
                    title: '⚠️ ลบแบบบันทึก',
                    text: 'ข้อมูลจะถูกย้ายไปถังขยะ และสามารถกู้คืนได้ภายหลัง',
                    showCancelButton: true,
                    confirmButtonText: '🗑️ ลบ',
                    cancelButtonText: '❌ ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('deleteForm').submit();
                    }
                });
            }
        </script>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


        <script>
            document.addEventListener('DOMContentLoaded', function() {
                $('.js-teacher-search').select2({
                    placeholder: 'ค้นหาชื่อครู...',
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: function() {
                            return "ไม่พบชื่อครู";
                        }
                    }
                });
            });

            let initialSnapshot = null;

            function getFormSnapshot() {
                const data = new FormData(document.getElementById('evaluationForm'));
                const obj = {};

                for (let [key, value] of data.entries()) {
                    if (key === 'images[]') continue; // ไม่เช็ครูป
                    obj[key] = value;
                }

                return JSON.stringify(obj);
            }

            document.addEventListener('DOMContentLoaded', () => {
                initialSnapshot = getFormSnapshot();
                document.getElementById('formSnapshot').value = initialSnapshot;
            });
        </script>

</body>

</html>