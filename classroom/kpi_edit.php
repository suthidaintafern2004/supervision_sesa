<?php
require_once '../config/session_config.php';

// เปิดการแสดง Error เพื่อตรวจสอบหากเกิดปัญหา (แนะนำให้ปิดเมื่อขึ้นระบบจริง)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$supervisor_id = $_SESSION['user_id'] ?? null;

// ===============================
// คำนวณปีการศึกษา
// ===============================
$todayYear  = date('Y') + 543;
$todayMonth = date('n');
$currentAcademicYear = ($todayMonth >= 5) ? $todayYear : $todayYear - 1;

$academicYearOptions = [
    $currentAcademicYear - 1,
    $currentAcademicYear,
    $currentAcademicYear + 1
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kpi_ref'])) {
    $_SESSION['kpi_edit_ref'] = $_POST['kpi_ref'];
}

if (empty($_SESSION['kpi_edit_ref'])) {
    header('Location: ../index.php');
    exit;
}

require_once '../config/db_connect.php';

$ref = $_SESSION['kpi_edit_ref'];
$t_pid           = $ref['t_pid'];
$subject_code    = $ref['subject_code'];
$inspection_time = $ref['inspection_time'];

/* ===============================
   ดึงข้อมูล session + ครู + ศน. (ปรับชื่อฟิลด์ตาม sesa_db.sql)
================================ */
$sqlSession = "
SELECT ss.*,
       CONCAT(pt.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name,
       CONCAT(ps.prefix_name,' ',sp.fname,' ',sp.lname) AS supervisor_name
FROM supervision_sessions ss
LEFT JOIN teacher t ON ss.t_pid = t.t_pid
LEFT JOIN prefix pt ON t.prefix_id = pt.prefix_id
LEFT JOIN supervisor sp ON ss.p_id = sp.p_id
LEFT JOIN prefix ps ON sp.prefix_id = ps.prefix_id
WHERE ss.t_pid = ?
  AND ss.subject_code = ?
  AND ss.inspection_time = ?
  AND ss.deleted_at IS NULL
";

$params = [$t_pid, $subject_code, $inspection_time];

if (!$isAdmin) {
    $sqlSession .= " AND ss.p_id = ? ";
    $params[] = $supervisor_id;
}

$stmt_session = $conn->prepare($sqlSession);
$stmt_session->execute($params);
$session_data = $stmt_session->fetch(PDO::FETCH_ASSOC);

if (!$session_data) {
    die('<div class="alert alert-warning text-center">ไม่พบข้อมูลการนิเทศ</div>');
}

/* ===============================
   รายชื่อครู / ศน. (สำหรับ Admin)
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
   ดึงคะแนนเดิม (ปรับชื่อฟิลด์ตาม kpi_answers ใน sesa_db.sql)
================================ */
$sqlRating = "
SELECT question_id, rating_score
FROM kpi_answers
WHERE t_pid = ?
  AND subject_code = ?
  AND inspection_time = ?
";
$params = [$t_pid, $subject_code, $inspection_time];

if (!$isAdmin) {
    $sqlRating .= " AND p_id = ? ";
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
   ข้อเสนอแนะรายตัวบ่งชี้ (ปรับชื่อฟิลด์ตาม kpi_indicator_suggestions)
================================ */
$sqlNote = "
SELECT indicator_id, suggestion_text
FROM kpi_indicator_suggestions
WHERE t_pid = ?
  AND subject_code = ?
  AND inspection_time = ?
";
$params = [$t_pid, $subject_code, $inspection_time];

if (!$isAdmin) {
    $sqlNote .= " AND p_id = ? ";
    $params[] = $supervisor_id;
}

$stmt = $conn->prepare($sqlNote);
$stmt->execute($params);
$indicator_suggestions = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

/* ===============================
   รูปภาพเดิม (ปรับชื่อฟิลด์ตาม images)
================================ */
$sqlImg = "
SELECT id, file_name, academic_year, form_type
FROM images
WHERE t_pid = ?
  AND subject_code = ?
  AND inspection_time = ?
";
$params = [$t_pid, $subject_code, $inspection_time];

if (!$isAdmin) {
    $sqlImg .= " AND p_id = ? ";
    $params[] = $supervisor_id;
}

$stmt = $conn->prepare($sqlImg);
$stmt->execute($params);
$existing_images_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แก้ไขข้อมูลการนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/kpi_style.css">
    <link rel="stylesheet" href="../css/styles.css">

    <style>
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
        }

        .select2-container--default .select2-selection--single {
            height: 46px;
            border-radius: 14px;
            border: 1px solid #dee2e6;
            padding: 8px 12px;
        }
    </style>
</head>

<body class="bg-light">
    <?php $nav_prefix = '../'; include '../navbar.php'; ?>

    <div class="container py-5">

        <form id="evaluationForm" method="POST" action="update_kpi_data.php" enctype="multipart/form-data">

            <input type="hidden" name="redirect_back" value="<?= htmlspecialchars($_SERVER['HTTP_REFERER'] ?? '../index.php') ?>">
            <input type="hidden" name="old_t_pid" value="<?= $session_data['t_pid'] ?>">
            <input type="hidden" name="old_subject_code" value="<?= $session_data['subject_code'] ?>">
            <input type="hidden" name="old_inspection_time" value="<?= $session_data['inspection_time'] ?>">
            <input type="hidden" name="old_p_id" value="<?= $session_data['p_id'] ?>">
            <input type="hidden" name="academic_year_hidden" value="<?= htmlspecialchars($session_data['academic_year']) ?>">
            <input type="hidden" name="form_type" value="cr">

            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-body p-4 bg-white">
                    <h2 class="text-primary fw-bold mb-4">แก้ไขข้อมูลการนิเทศ</h2>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="fw-bold">ผู้นิเทศ</label>
                            <?php if ($isAdmin): ?>
                                <select name="p_id" class="form-select modern-input">
                                    <?php foreach ($supervisors as $s): ?>
                                        <option value="<?= $s['p_id'] ?>" <?= $s['p_id'] == $session_data['p_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['supervisor_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="form-control modern-input bg-light"><?= htmlspecialchars($session_data['supervisor_name']) ?></div>
                                <input type="hidden" name="p_id" value="<?= $session_data['p_id'] ?>">
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="fw-bold">ผู้รับนิเทศ</label>
                            <?php if ($isAdmin): ?>
                                <select name="t_pid" class="form-select js-teacher-search modern-input">
                                    <?php foreach ($teachers as $t): ?>
                                        <option value="<?= $t['t_pid'] ?>" <?= $t['t_pid'] == $session_data['t_pid'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['teacher_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="form-control modern-input bg-light"><?= htmlspecialchars($session_data['teacher_name']) ?></div>
                                <input type="hidden" name="t_pid" value="<?= $session_data['t_pid'] ?>">
                            <?php endif; ?>
                        </div>

                        <div class="col-md-2">
                            <label class="fw-bold">รหัสวิชา</label>
                            <input type="text" name="subject_code" class="form-control modern-input" value="<?= htmlspecialchars($session_data['subject_code']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold">ชื่อวิชา</label>
                            <input type="text" name="subject_name" class="form-control modern-input" value="<?= htmlspecialchars($session_data['subject_name']) ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="fw-bold">ครั้งที่นิเทศ</label>
                            <select name="inspection_time" class="form-select modern-input">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($session_data['inspection_time'] == $i) ? 'selected' : '' ?>>ครั้งที่ <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="fw-bold">วันที่นิเทศ</label>
                            <input type="date" name="inspection_date" class="form-control modern-input" value="<?= htmlspecialchars($session_data['inspection_date']) ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="fw-bold">ปีการศึกษา</label>
                            <input type="text" class="form-control modern-input bg-light" value="<?= htmlspecialchars($session_data['academic_year']) ?>" readonly>
                            <input type="hidden" name="academic_year" value="<?= $session_data['academic_year'] ?>">
                        </div>
                    </div>
                </div>
            </div>

            <?php foreach ($indicators as $iid => $data): ?>
                <div class="indicator-section">
                    <div class="indicator-title"><?= htmlspecialchars($data['title']) ?></div>
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
                        <label class="fw-bold text-dark mb-2 small">📝 ข้อค้นพบ / ข้อเสนอแนะ</label>
                        <textarea name="indicator_suggestions[<?= $iid ?>]" class="form-control border-0" rows="2"><?= htmlspecialchars($indicator_suggestions[$iid] ?? '') ?></textarea>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-header bg-white fw-bold py-3">📝 ข้อเสนอแนะเพิ่มเติม</div>
                <div class="card-body">
                    <textarea class="form-control" name="overall_suggestion" rows="3"><?= htmlspecialchars($session_data['overall_suggestion']) ?></textarea>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-header bg-white fw-bold py-3">📷 รูปภาพกิจกรรม</div>
                <div class="card-body">
                    <div class="d-flex gap-3 flex-wrap align-items-start">
                        <div class="d-flex gap-3 flex-wrap" id="imageContainer">
                            <?php foreach ($existing_images_db as $img): ?>
                                <div class="img-item existing-img">
                                    <img src="../uploads/<?= htmlspecialchars($img['file_name']) ?>" class="kpi-image">
                                    <input type="hidden" name="existing_images[<?= $img['id'] ?>][file_name]" value="<?= htmlspecialchars($img['file_name']) ?>">
                                    <button type="button" class="btn-remove-custom" onclick="removeExistingImage(this)">×</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="d-flex gap-3 flex-wrap" id="newImageContainer"></div>
                    </div>
                    
                    <input type="file" id="imageInput" name="images[]" class="form-control mb-3" accept="image/*" multiple style="display: none;">
                    <div class="d-flex align-items-center mt-3">
                        <button type="button" id="selectImageBtn" class="btn btn-outline-primary" onclick="document.getElementById('imageInput').click()">
                            <i class="fas fa-plus"></i> เลือกรูปภาพ
                        </button>
                        <small class="text-muted ms-3">* รองรับไฟล์ .jpg, .png (รวมรูปเดิมและรูปใหม่ไม่เกิน 2 รูป)</small>
                    </div>
                </div>
            </div>

            <div class="text-center mb-5 d-flex justify-content-center gap-3">
                <button type="button" class="btn btn-success btn-lg shadow" onclick="confirmSave()">💾 บันทึก</button>
                <button type="button" class="btn btn-danger btn-lg shadow" onclick="history.back()">❌ ยกเลิก</button>
            </div>
        </form>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('.js-teacher-search').select2();
        });

        function confirmSave() {
            updateInputFiles(); // ให้แน่ใจว่าไฟล์ที่เลือกไว้ล่าสุดถูกอัปเดตลง input
            
            Swal.fire({
                title: 'ยืนยันการแก้ไข?',
                text: "คุณต้องการบันทึกข้อมูลที่แก้ไขใช่หรือไม่",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    const form = document.getElementById('evaluationForm');
                    const formData = new FormData(form);
                    
                    try {
                        const response = await fetch('update_kpi_data.php', { method: 'POST', body: formData });
                        const resultData = await response.json();
                        
                        if (resultData.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: resultData.message, timer: 2000, showConfirmButton: false }).then(() => {
                                window.location.href = '../my_sessions_list.php?success=update';
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'พบข้อผิดพลาด', text: resultData.message, confirmButtonText: 'ตกลง' });
                        }
                    } catch (error) {
                        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                    }
                }
            });
        }

        // ระบบจัดการรูปภาพ (จำกัด 2 รูป รวมของเก่า)
        let selectedFiles = [];
        const imageInput = document.getElementById('imageInput');
        const newImageContainer = document.getElementById('newImageContainer');
        const selectImageBtn = document.getElementById('selectImageBtn');

        function countExistingImages() {
            return document.querySelectorAll('.existing-img').length;
        }

        function checkImageLimit() {
            const total = countExistingImages() + selectedFiles.length;
            if (total >= 2) {
                selectImageBtn.style.display = 'none';
            } else {
                selectImageBtn.style.display = 'inline-block';
            }
        }

        function removeExistingImage(btn) {
            btn.parentElement.remove();
            checkImageLimit();
        }

        imageInput.addEventListener('change', function() {
            const newFiles = Array.from(this.files);
            for (let file of newFiles) {
                if (!file.type.startsWith('image/')) continue;
                if (countExistingImages() + selectedFiles.length >= 2) {
                    Swal.fire('จำกัดรูปภาพ', 'สามารถอัปโหลดและเก็บรูปภาพได้ไม่เกิน 2 รูป กรุณาลบรูปเดิมทิ้งก่อนเพิ่มรูปใหม่', 'warning');
                    break;
                }
                selectedFiles.push(file);
            }
            updateInputFiles();
            renderNewImagesPreview();
            checkImageLimit();
        });

        function renderNewImagesPreview() {
            newImageContainer.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'img-item new-img position-relative';
                    div.innerHTML = `
                        <img src="${e.target.result}" class="kpi-image">
                        <button type="button" class="btn-remove-custom" onclick="removeNewImage(${index})">×</button>
                    `;
                    newImageContainer.appendChild(div);
                }
                reader.readAsDataURL(file);
            });
        }

        function removeNewImage(index) {
            selectedFiles.splice(index, 1);
            updateInputFiles();
            renderNewImagesPreview();
            checkImageLimit();
        }

        function updateInputFiles() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            imageInput.files = dataTransfer.files;
        }

        // ทำงานเมื่อโหลดหน้าครั้งแรก
        checkImageLimit();
    </script>
</body>

</html>