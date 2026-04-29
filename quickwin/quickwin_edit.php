<?php

/****************************************
 * QUICK WIN EDIT (STYLE SAME AS ADD)
 ****************************************/
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* รับค่าจาก POST ครั้งแรก */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qw_ref'])) {
    $_SESSION['quickwin_edit_ref'] = $_POST['qw_ref'];
}

/* ถ้าไม่มี session อ้างอิง → ห้ามเข้า */
if (empty($_SESSION['quickwin_edit_ref'])) {
    header('Location: ../list_all_forms.php');
    exit;
}

$ref = $_SESSION['quickwin_edit_ref'];


require_once __DIR__ . '/../config/db_connect.php';

/* =========================
   AUTH
========================= */
if (empty($_SESSION['user_id'])) {
    exit('Unauthorized');
}

/* =========================
   AJAX : SEARCH TEACHER (LIMIT 5)
========================= */
if (
    isset($_GET['ajax']) &&
    $_GET['ajax'] === 'search_teacher'
) {
    header('Content-Type: application/json; charset=utf-8');

    $term = trim($_GET['q'] ?? '');

    $sql = "
        SELECT 
            t.t_pid AS id,
            CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS text
        FROM teacher t
        LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
        WHERE CONCAT(t.f_name,' ',t.l_name) LIKE ?
        ORDER BY t.f_name
        LIMIT 5
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['%' . $term . '%']);

    echo json_encode([
        'results' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
    exit;
}

// ศน. ทั้งหมด
$supervisors = $conn->query("
    SELECT p_id,
           CONCAT(IFNULL(pr.prefix_name,''), sp.fname,' ',sp.lname) AS fullname
    FROM supervisor sp
    LEFT JOIN prefix pr ON sp.prefix_id = pr.prefix_id
    ORDER BY sp.fname
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   LOAD REF FROM SESSION
========================= */
$ref = $_SESSION['quickwin_edit_ref'];

$t_pid            = $ref['t_pid'];
$p_id             = $ref['p_id'];
$supervision_date = $ref['supervision_date'];

/* =========================
   LOAD QUICK WIN DATA
========================= */
$sql = "
SELECT 
    qw.*,
    CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name,
    s.school_name,
    CONCAT(IFNULL(pr.prefix_name,''), sp.fname,' ',sp.lname) AS supervisor_name
FROM quick_win qw
LEFT JOIN teacher t ON qw.t_pid = t.t_pid
LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
LEFT JOIN school s ON t.school_id = s.school_id
LEFT JOIN supervisor sp ON qw.p_id = sp.p_id
LEFT JOIN prefix pr ON sp.prefix_id = pr.prefix_id
WHERE qw.t_pid = ?
  AND qw.p_id = ?
  AND qw.supervision_date = ?
";

$stmt = $conn->prepare($sql);
$stmt->execute([$t_pid, $p_id, $supervision_date]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    exit('<div class="alert alert-warning text-center">ไม่พบข้อมูล Quick Win</div>');
}

/* =========================
   SEMESTER (ภาคเรียน)
========================= */
$savedSemester = $data['semester'] ?? 1;

/* =========================
   CALCULATE ACADEMIC YEAR
========================= */
$currentYear = (int)date('Y');
$currentMonth = (int)date('n');

// คำนวณปีการศึกษาปัจจุบัน (พ.ศ.)
if ($currentMonth >= 5) {
    $academicYear = $currentYear + 543;
} else {
    $academicYear = ($currentYear - 1) + 543;
}

// 3 ตัวเลือก
$academicYears = [
    $academicYear - 1,
    $academicYear,
    $academicYear + 1
];

// กรณี edit: ถ้ามีปีการศึกษาที่เคยบันทึกไว้
$savedAcademicYear = $data['academic_year'] ?? $academicYear;

/* =========================
   PARSE SAVED OPTIONS
========================= */
$savedOptions = array_filter(array_map('trim', explode('/', $data['options'] ?? '')));
$optionOther  = $data['option_other'] ?? '';

/* =========================
   LOAD OPTIONS FROM DB
========================= */
$sqlOpt = "SELECT OptionID, OptionText FROM quickwin_options ORDER BY OptionID ASC";
$stmtOpt = $conn->prepare($sqlOpt);
$stmtOpt->execute();
$options = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   SPLIT 2 COLUMNS
========================= */
$total = count($options);
$half  = ceil($total / 2);
$col1  = array_slice($options, 0, $half);
$col2  = array_slice($options, $half);

/* =========================
   LOAD QUICK WIN IMAGES
========================= */
$sqlImg = "
    SELECT id, file_name
    FROM images
    WHERE p_id = ?
      AND t_pid = ?
      AND form_type = 'qw'
      AND (subject_code = '' OR subject_code IS NULL)
      AND (inspection_time = '' OR inspection_time IS NULL)
    ORDER BY id ASC
";

$stmtImg = $conn->prepare($sqlImg);
$stmtImg->execute([$p_id, $t_pid]);
$images = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แก้ไขแบบบันทึกจุดเน้น (Quick Win)</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/quickwin_form.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap-5-theme@1.5.2/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .btn-remove-custom {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .img-container-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        /* Modern Buttons Style */
        .btn-modern {
            border-radius: 999px;
            padding: 12px 28px;
            font-size: 1.05rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.25s ease;
            border: none;
        }
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(0,0,0,0.15) !important;
            color: #fff;
        }
        .btn-save {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: #fff;
        }
        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #ff6b6b);
            color: #fff;
        }
    </style>
</head>

<body class="bg-light">
    <?php $nav_prefix = '../'; include '../navbar.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">

                <form method="POST" action="update_quickwin.php" id="quickwinEditForm" enctype="multipart/form-data">
                    <div class="card shadow-lg">
                        <div class="card-header bg-danger text-white text-center py-3">
                            <h4 class="mb-0 fw-bold">
                                <i class="fas fa-bullseye me-2"></i> แก้ไขแบบบันทึกจุดเน้น (Quick Win)
                            </h4>
                        </div>

                        <div class="card-body p-4 p-md-5">

                            <div class="alert alert-light border rounded-3 mb-4">
                                <h5 class="fw-bold mb-3 border-bottom pb-2">
                                    <i class="fas fa-id-card"></i> ข้อมูลการนิเทศ
                                </h5>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="text-muted">ผู้นิเทศ</label>
                                        <select name="new_p_id" class="form-select" required>
                                            <?php foreach ($supervisors as $sp): ?>
                                                <option value="<?= $sp['p_id'] ?>"
                                                    <?= $sp['p_id'] == $p_id ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($sp['fullname']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="text-muted">ผู้รับการนิเทศ</label>
                                        <select name="new_t_pid" class="select-search" required>
                                            <option value="<?= $t_pid ?>" selected>
                                                <?= htmlspecialchars($data['teacher_name']) ?>
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <small class="text-muted">โรงเรียน</small>
                                        <div><?= htmlspecialchars($data['school_name']) ?></div>
                                    </div>

                                    <div class="col-md-6">
                                        <small class="text-muted">วันที่บันทึก</small>
                                        <div><?= date('d/m/Y H:i', strtotime($data['supervision_date'])) ?></div>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="text-muted">ภาคเรียน</label>
                                        <select name="semester" class="form-select" required>
                                            <option value="1" <?= $savedSemester == 1 ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                                            <option value="2" <?= $savedSemester == 2 ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="text-muted">ปีการศึกษา</label>
                                        <select name="academic_year" class="form-select" required>
                                            <?php foreach ($academicYears as $year): ?>
                                                <option value="<?= $year ?>" <?= ($year == $savedAcademicYear) ? 'selected' : '' ?>><?= $year ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <input type="hidden" name="old_t_pid" value="<?= htmlspecialchars($data['t_pid']) ?>">
                            <input type="hidden" name="old_p_id" value="<?= htmlspecialchars($data['p_id']) ?>">
                            <input type="hidden" name="old_supervision_date" value="<?= htmlspecialchars($data['supervision_date']) ?>">

                            <div class="mb-4">
                                <label class="form-label fw-bold text-danger fs-5 mb-3">
                                    <i class="fas fa-list-check"></i> เลือกหัวข้อจุดเน้น (Quick Win) ที่ต้องการนิเทศ
                                </label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <?php foreach ($col1 as $opt): ?>
                                            <div class="qw-select">
                                                <input type="checkbox" class="qw-checkbox" id="opt<?= $opt['OptionID'] ?>" name="options[]" value="<?= $opt['OptionID'] ?>" <?= in_array((string)$opt['OptionID'], $savedOptions, true) ? 'checked' : '' ?>>
                                                <label for="opt<?= $opt['OptionID'] ?>" class="option-text"><?= htmlspecialchars($opt['OptionID'] . '. ' . $opt['OptionText']) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?php foreach ($col2 as $opt): ?>
                                            <div class="qw-select">
                                                <input type="checkbox" class="qw-checkbox" id="opt<?= $opt['OptionID'] ?>" name="options[]" value="<?= $opt['OptionID'] ?>" <?= in_array((string)$opt['OptionID'], $savedOptions, true) ? 'checked' : '' ?>>
                                                <label for="opt<?= $opt['OptionID'] ?>" class="option-text"><?= htmlspecialchars($opt['OptionID'] . '. ' . $opt['OptionText']) ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">อื่นๆ ( กรณีหัวข้อที่ต้องการนิเทศไม่ได้อยู่ในรายการด้านบน )</label>
                                <textarea class="form-control" name="option_other" rows="4"><?= htmlspecialchars($optionOther) ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">รูปภาพประกอบ (Quick Win)</label>
                                <div class="row g-3 mb-3" id="existingImagesBox">
                                    <?php foreach ($images as $img) : ?>
                                        <div class="col-md-3 text-center existing-img-item" id="img<?= $img['id'] ?>">
                                            <div class="img-container-wrapper">
                                                <img src="../uploads/quickwin/<?= htmlspecialchars($img['file_name']) ?>" class="img-fluid rounded shadow-sm" style="width: 100%; height: 150px; object-fit: cover;">
                                                <button type="button" class="btn-remove-custom" onclick="deleteImage(<?= $img['id'] ?>)">×</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <input type="file" name="quickwin_images[]" id="quickwin_images" class="form-control" accept="image/*" multiple>
                                <small class="text-muted">เพิ่มได้ไม่เกิน 2 รูป (jpg, png ขนาดไม่เกิน 5MB)</small>
                                <div class="row g-3 mt-2" id="previewBox"></div>
                            </div>

                            <div class="row mt-5">
                                <div class="col-md-10 mx-auto">
                                    <div class="d-flex justify-content-center gap-3">
                                        <div class="col-md-6 mb-2">
                                            <button type="submit" class="btn btn-modern btn-save w-100 shadow">SAVE CHANGES</button>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <a href="../my_sessions_list.php" class="btn btn-modern btn-delete w-100 shadow">CANCEL</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // ฟังก์ชันลบรูปเดิม (หายไปจากจอเฉยๆ และสร้าง input hidden)
        function deleteImage(id) {
            const imgDiv = document.getElementById('img' + id);
            if (imgDiv) {
                imgDiv.remove(); // ลบออกจากหน้าจอทันที
            }

            // สร้าง input hidden เพื่อส่ง ID ไปลบใน update_quickwin.php
            const form = document.getElementById('quickwinEditForm');
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'delete_image_ids[]';
            hiddenInput.value = id;
            form.appendChild(hiddenInput);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('quickwinEditForm');
            form.addEventListener('submit', function(e) {
                const checkedCount = document.querySelectorAll('.qw-checkbox:checked').length;
                const otherText = document.querySelector('textarea[name="option_other"]').value.trim();

                if (checkedCount === 0 && otherText === '') {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'กรุณาเลือกหัวข้อ',
                        text: 'กรุณาเลือกหัวข้อ Quick Win อย่างน้อย 1 ข้อ หรือระบุหัวข้ออื่น',
                        confirmButtonColor: '#dc3545'
                    });
                    return;
                }

                e.preventDefault();
                Swal.fire({
                    icon: 'question',
                    title: 'ยืนยันการบันทึก',
                    text: 'ต้องการบันทึกการแก้ไข Quick Win ใช่หรือไม่?',
                    showCancelButton: true,
                    confirmButtonText: 'บันทึก',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            // Select2
            $('.select-search').select2({
                theme: 'bootstrap-5',
                width: '100%',
                ajax: {
                    url: 'quickwin_edit.php?ajax=search_teacher',
                    dataType: 'json',
                    delay: 250,
                    data: params => ({
                        q: params.term || ''
                    }),
                    processResults: data => data,
                    cache: true
                }
            });
        });

        // จัดการรูปใหม่
        const input = document.getElementById('quickwin_images');
        const previewBox = document.getElementById('previewBox');
        let newFiles = [];

        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            newFiles.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }

        function renderPreviews() {
            previewBox.innerHTML = '';
            newFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = e => {
                    const div = document.createElement('div');
                    div.className = 'col-md-3 position-relative';
                    div.innerHTML = `
                        <div class="img-container-wrapper">
                            <img src="${e.target.result}" class="img-fluid rounded shadow-sm" style="width: 100%; height: 150px; object-fit: cover;">
                            <button type="button" class="btn-remove-custom" onclick="removeNewFile(${index})">×</button>
                            <span class="badge bg-primary position-absolute top-0 start-0 m-2">ใหม่</span>
                        </div>
                    `;
                    previewBox.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        }

        function removeNewFile(index) {
            newFiles.splice(index, 1);
            updateFileInput();
            renderPreviews();
        }

        input.addEventListener('change', function() {
            const filesFromInput = Array.from(this.files);
            const existingImageCount = document.querySelectorAll('.existing-img-item').length;
            const maxTotalImages = 2;

            if (existingImageCount + newFiles.length + filesFromInput.length > maxTotalImages) {
                Swal.fire({
                    icon: 'warning',
                    title: 'เลือกรูปได้ไม่เกิน ' + maxTotalImages + ' รูป',
                    text: 'จำนวนรูปเกินกำหนด กรุณาลบรูปเดิมออกก่อน',
                    confirmButtonColor: '#dc3545'
                });
                this.value = '';
                return;
            }
            newFiles = [...newFiles, ...filesFromInput];
            this.value = '';
            updateFileInput();
            renderPreviews();
        });
    </script>
</body>

</html>