<?php

/****************************************
 * QUICK WIN EDIT (STYLE SAME AS ADD)
 ****************************************/
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


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
   COMPOSITE KEY
========================= */
$t_pid            = $_GET['t_pid'] ?? null;
$p_id             = $_GET['p_id'] ?? null;
$supervision_date = $_GET['supervision_date'] ?? null;

if (!$t_pid || !$p_id || !$supervision_date) {
    exit('<div class="alert alert-danger text-center">ข้อมูลไม่ครบ</div>');
}

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
    WHERE supervisor_p_id = ?
      AND teacher_t_pid = ?
      AND subject_code IS NULL
      AND inspection_time IS NULL
    ORDER BY uploaded_on ASC
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

    <style>
        /* =====================================
           Select2 ให้เหมือน Bootstrap 5
        ===================================== */

        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
            padding: 0.375rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            background-color: #fff;
            font-size: 1rem;
            display: flex;
            align-items: center;
        }

        .select2-container--bootstrap-5 .select2-selection__rendered {
            padding-left: 0;
            color: #212529;
            line-height: normal;
        }

        .select2-container--bootstrap-5 .select2-selection__arrow {
            height: 100%;
        }

        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, .25);
        }

        .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
        }

        .select2-container--bootstrap-5 .select2-dropdown {
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .15);
        }

        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: #dc3545;
            color: #fff;
        }
    </style>

</head>

<body>
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
                                        <label class="text-muted">ปีการศึกษา</label>
                                        <select name="academic_year" class="form-select" required>
                                            <?php foreach ($academicYears as $year): ?>
                                                <option value="<?= $year ?>"
                                                    <?= ($year == $savedAcademicYear) ? 'selected' : '' ?>>
                                                    <?= $year ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                </div>

                            </div>

                            <!-- KEY เดิม (สำหรับ WHERE ตอน UPDATE) -->
                            <input type="hidden" name="old_t_pid" value="<?= htmlspecialchars($t_pid) ?>">
                            <input type="hidden" name="old_p_id" value="<?= htmlspecialchars($p_id) ?>">
                            <input type="hidden" name="old_supervision_date" value="<?= htmlspecialchars($supervision_date) ?>">


                            <div class="mb-4">
                                <label class="form-label fw-bold text-danger fs-5 mb-3">
                                    <i class="fas fa-list-check"></i> เลือกหัวข้อจุดเน้น (Quick Win) ที่ต้องการนิเทศ
                                </label>

                                <div class="row">
                                    <!-- คอลัมน์ซ้าย -->
                                    <div class="col-md-6">
                                        <?php foreach ($col1 as $opt): ?>
                                            <div class="qw-select">
                                                <input
                                                    type="checkbox"
                                                    class="qw-checkbox"
                                                    id="opt<?= $opt['OptionID'] ?>"
                                                    name="options[]"
                                                    value="<?= $opt['OptionID'] ?>"
                                                    <?= in_array((string)$opt['OptionID'], $savedOptions, true) ? 'checked' : '' ?>>
                                                <label
                                                    for="opt<?= $opt['OptionID'] ?>"
                                                    class="option-text">
                                                    <?= htmlspecialchars($opt['OptionID'] . '. ' . $opt['OptionText']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- คอลัมน์ขวา -->
                                    <div class="col-md-6">
                                        <?php foreach ($col2 as $opt): ?>
                                            <div class="qw-select">
                                                <input
                                                    type="checkbox"
                                                    class="qw-checkbox"
                                                    id="opt<?= $opt['OptionID'] ?>"
                                                    name="options[]"
                                                    value="<?= $opt['OptionID'] ?>"
                                                    <?= in_array((string)$opt['OptionID'], $savedOptions, true) ? 'checked' : '' ?>>
                                                <label
                                                    for="opt<?= $opt['OptionID'] ?>"
                                                    class="option-text">
                                                    <?= htmlspecialchars($opt['OptionID'] . '. ' . $opt['OptionText']) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    หรือ อื่นๆ ( กรณีหัวข้อที่ต้องการนิเทศไม่ได้อยู่ในรายการด้านบน )
                                </label>
                                <textarea class="form-control"
                                    name="option_other"
                                    rows="4"><?= htmlspecialchars($optionOther) ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    รูปภาพประกอบ (Quick Win)
                                </label>

                                <!-- รูปเดิม -->
                                <div class="row g-3 mb-3">
                                    <?php foreach ($images as $img): ?>
                                        <div class="col-md-3 text-center" id="img<?= $img['id'] ?>">
                                            <img src="../uploads/quickwin/<?= htmlspecialchars($img['file_name']) ?>"
                                                class="img-thumbnail mb-2"
                                                style="height:120px; object-fit:cover;">
                                            <button type="button"
                                                class="btn btn-sm btn-outline-danger w-100"
                                                onclick="deleteImage(<?= $img['id'] ?>)">
                                                <i class="fas fa-trash"></i> ลบ
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- เพิ่มรูปใหม่ -->
                                <input type="file"
                                    name="quickwin_images[]"
                                    id="quickwin_images"
                                    class="form-control"
                                    accept="image/*"
                                    multiple>

                                <small class="text-muted">
                                    เพิ่มได้ไม่เกิน 2 รูป (jpg, png ขนาดไม่เกิน 5MB)
                                </small>

                                <!-- Preview รูปใหม่ -->
                                <div class="row g-3 mt-2" id="previewBox"></div>
                            </div>

                            <div class="row mt-5">
                                <div class="col-md-10 mx-auto">
                                    <div class="d-flex justify-content-center gap-3">

                                        <!-- ปุ่มบันทึกสีเขียว -->
                                        <div class="col-md-6 mb-2">
                                            <button type="submit"
                                                class="btn btn-success btn-lg w-100 shadow">
                                                <i class="fas fa-save me-2"></i> บันทึกการแก้ไข
                                            </button>
                                        </div>

                                        <!-- ปุ่มย้อนกลับ (แดงแบบโปร่ง) -->
                                        <div class="col-md-6 mb-2">
                                            <a href="../my_sessions_list.php"
                                                class="btn btn-danger btn-lg w-100 shadow">
                                                <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                </form>

            </div>
        </div>
    </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
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

        });
    </script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(document).ready(function() {

            $('.select-search').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'เลือกหรือพิมพ์ค้นหาชื่อครู',
                minimumInputLength: 0,
                dropdownParent: $('#quickwinEditForm'),
                ajax: {
                    url: 'quickwin_edit.php?ajax=search_teacher',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return data;
                    },
                    cache: true
                }
            });

        });
    </script>

    <script>
        const input = document.getElementById('quickwin_images');
        const previewBox = document.getElementById('previewBox');

        input.addEventListener('change', () => {
            previewBox.innerHTML = '';

            if (input.files.length > 2) {
                Swal.fire({
                    icon: 'warning',
                    title: 'เลือกรูปได้ไม่เกิน 2 รูป',
                    confirmButtonColor: '#dc3545'
                });
                input.value = '';
                return;
            }

            [...input.files].forEach(file => {
                const reader = new FileReader();
                reader.onload = e => {
                    const div = document.createElement('div');
                    div.className = 'col-md-3';
                    div.innerHTML = `
                <img src="${e.target.result}"
                     class="img-thumbnail"
                     style="height:120px; object-fit:cover;">
            `;
                    previewBox.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
        });
    </script>

    <script>
        function deleteImage(id) {
            Swal.fire({
                title: 'ลบรูปนี้?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#dc3545'
            }).then(result => {
                if (result.isConfirmed) {
                    fetch('quickwin_image_delete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: 'id=' + id
                        })
                        .then(res => res.text())
                        .then(() => {
                            document.getElementById('img' + id).remove();
                        });
                }
            });
        }
    </script>


</body>

</html>