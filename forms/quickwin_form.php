<?php
// forms/quickwin_form.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Bangkok');

// 1. เชื่อมต่อฐานข้อมูล (PDO)
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

/*
|--------------------------------------------------------------------------
| FIX: ล้าง flash ถ้าไม่ได้มาจากการบันทึก
|--------------------------------------------------------------------------
*/
if (
    isset($_SESSION['flash_message'], $_SESSION['flash_from']) &&
    $_SESSION['flash_from'] !== 'quickwin_save'
) {
    unset(
        $_SESSION['flash_message'],
        $_SESSION['flash_type'],
        $_SESSION['flash_from']
    );
}

// 2. ตรวจสอบข้อมูลใน Session
$inspection_data = $_SESSION['inspection_data'] ?? null;
$form_type = $inspection_data['form_type'] ?? $inspection_data['evaluation_type'] ?? '';

if (!$inspection_data || $form_type !== 'quickwin_form') {
    header('Location: ../index.php');
    exit();
}

// 3. ดึงข้อมูลตัวแปร
$supervisor_name = $inspection_data['supervisor_name'] ?? $_SESSION['user_name'] ?? 'ไม่ระบุ';
$teacher_name    = $inspection_data['teacher_name']    ?? 'ไม่ระบุ';
$supervisor_pid =
    $inspection_data['s_p_id']
    ?? $inspection_data['supervisor_p_id']
    ?? $_SESSION['user_id']
    ?? '';
$teacher_pid     = $inspection_data['t_pid']           ?? '';
$school_name     = $inspection_data['school_name']     ?? '';
$subject_name    = $inspection_data['subject_name']    ?? '';

/* =========================
   CALCULATE ACADEMIC YEAR
========================= */
$currentYear  = (int)date('Y');
$currentMonth = (int)date('n');

// ปีการศึกษาปัจจุบัน (พ.ศ.)
if ($currentMonth >= 5) {
    $currentAcademicYear = $currentYear + 543;
} else {
    $currentAcademicYear = ($currentYear - 1) + 543;
}

// ตัวเลือก 3 ปี
$academicYears = [
    $currentAcademicYear - 1,
    $currentAcademicYear,
    $currentAcademicYear + 1
];


/* =========================
   CALCULATE SEMESTER
========================= */
// กำหนดภาคเรียนอัตโนมัติ (คร่าว ๆ)
if ($currentMonth >= 5 && $currentMonth <= 10) {
    $currentSemester = 1;
} else {
    $currentSemester = 2;
}

// ตัวเลือกภาคเรียน
$semesters = [
    1 => 'ภาคเรียนที่ 1',
    2 => 'ภาคเรียนที่ 2'
];


// 4. ดึงข้อมูลตัวเลือก (Options)
$options = [];
try {
    // ตรวจสอบว่ามีคอลัมน์ display_order หรือไม่ (รองรับระบบเรียงลำดับใหม่)
    $has_display_order = false;
    try {
        $conn->query("SELECT display_order FROM quickwin_options LIMIT 1");
        $has_display_order = true;
    } catch (Exception $e) {}

    $order_clause = $has_display_order ? "ORDER BY display_order ASC, OptionID ASC" : "ORDER BY OptionID ASC";
    $sql = "SELECT OptionID, OptionText FROM quickwin_options $order_clause";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $seenTexts = [];
    $display_number = 1;
    foreach ($result as $row) {
        $key = trim($row['OptionText']);
        if (isset($seenTexts[$key])) {
            continue;
        }
        $seenTexts[$key] = true;
        $row['display_number'] = $display_number++; // รันลำดับใหม่เสมอเพื่อป้องกันตัวเลขฟันหลอ
        $options[] = $row;
    }
} catch (PDOException $e) {
    echo "Error fetching options: " . $e->getMessage();
}

// แบ่งข้อมูลเป็น 2 ส่วน สำหรับแสดง 2 คอลัมน์
$total_options = count($options);
$half = ceil($total_options / 2);
$col1_options = array_slice($options, 0, $half);
$col2_options = array_slice($options, $half);
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบบันทึกจุดเน้น (Quick Win)</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/quickwin_form.css">
    <style>
        .btn-modern {
            transition: all 0.3s ease;
        }
        .btn-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15) !important;
        }
    </style>
</head>

<body class="bg-light">

    <?php $nav_prefix = '../'; include '../navbar.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-10">
                <div class="card shadow-lg">
                    <div class="card-header bg-danger text-white text-center py-3">
                        <h4 class="mb-0 fw-bold">
                            <i class="fas fa-bullseye me-2"></i> แบบบันทึกจุดเน้น (Quick Win)
                        </h4>
                    </div>

                    <form id="evaluationForm" action="save_quickwin_data.php" method="POST" enctype="multipart/form-data">

                        <div class="card-body p-4 p-md-5">

                            <div class="alert alert-light border border-secondary border-opacity-25 rounded-3 mb-4">
                                <h5 class="text-primary fw-bold mb-3 border-bottom pb-2">
                                    <i class="fas fa-id-card"></i> ข้อมูลการนิเทศ
                                </h5>
                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <small class="text-muted d-block">ผู้นิเทศ</small>
                                        <span class="fs-5 fw-bold text-dark"><?php echo htmlspecialchars($supervisor_name); ?></span>
                                    </div>

                                    <div class="col-md-6">
                                        <small class="text-muted d-block">ผู้รับการนิเทศ</small>
                                        <span class="fs-5 fw-bold text-dark"><?php echo htmlspecialchars($teacher_name); ?></span>
                                    </div>

                                    <div class="col-md-6">
                                        <small class="text-muted d-block">โรงเรียน</small>
                                        <span><?php echo htmlspecialchars($school_name); ?></span>
                                    </div>

                                    <div class="col-md-6">
                                        <small class="text-muted d-block">กลุ่มสาระฯ/วิชา</small>
                                        <span><?php echo htmlspecialchars($subject_name); ?></span>
                                    </div>

                                    <div class="col-md-3">
                                        <small class="text-muted d-block">ภาคเรียน <span class="text-danger">*</span></small>
                                        <select name="semester" class="form-select mt-1" required>
                                            <option value="" selected disabled>โปรดเลือกภาคเรียน</option>
                                            <?php foreach ($semesters as $key => $label): ?>
                                                <option value="<?= $key ?>">
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <small class="text-muted d-block">ปีการศึกษา <span class="text-danger">*</span></small>
                                        <select name="academic_year" class="form-select mt-1" required>
                                            <option value="" selected disabled>โปรดเลือกปีการศึกษา</option>
                                            <?php foreach ($academicYears as $year): ?>
                                                <option value="<?= $year ?>">
                                                    <?= $year ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                </div>
                            </div>

                            <input type="hidden" name="supervisor_p_id" value="<?php echo htmlspecialchars($supervisor_pid); ?>">
                            <input type="hidden" name="teacher_t_pid" value="<?php echo htmlspecialchars($teacher_pid); ?>">

                            <div class="mb-4">
                                <label class="form-label form-label-bold text-danger fs-5 mb-1">
                                    <i class="fas fa-list-check"></i> เลือกหัวข้อจุดเน้น (Quick Win) ที่ต้องการนิเทศ
                                </label>
                                <div class="text-muted small mb-3">โปรดเลือกหัวข้อการนิเทศอย่างน้อย 1 หัวข้อ</div>

                                <div class="row">
                                    <div class="col-md-6 border-end">
                                        <?php foreach ($col1_options as $opt): ?>
                                            <div class="qw-select">

                                                <input
                                                    class="form-check-input qw-checkbox"
                                                    type="checkbox"
                                                    name="option_ids[]"
                                                    value="<?= $opt['OptionID']; ?>"
                                                    id="opt_<?= $opt['OptionID']; ?>">
                                                <label
                                                    class="form-check-label option-text"
                                                    for="opt_<?= $opt['OptionID']; ?>">
                                                    <?= htmlspecialchars($opt['display_number'] . '. ' . $opt['OptionText']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <?php foreach ($col2_options as $opt): ?>
                                            <div class="qw-select">
                                                <input
                                                    class="form-check-input qw-checkbox"
                                                    type="checkbox"
                                                    name="option_ids[]"
                                                    value="<?= $opt['OptionID']; ?>"
                                                    id="opt_<?= $opt['OptionID']; ?>">
                                                <label
                                                    class="form-check-label option-text"
                                                    for="opt_<?= $opt['OptionID']; ?>">
                                                    <?= htmlspecialchars($opt['display_number'] . '. ' . $opt['OptionText']); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="option_other" class="form-label form-label-bold">
                                        หรือ อื่นๆ ( กรณีหัวข้อที่ต้องการนิเทศไม่ได้อยู่ในรายการด้านบน )
                                    </label>
                                    <textarea class="form-control" name="option_other" id="option_other" rows="4"
                                        placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label form-label-bold">
                                        แนบรูปภาพประกอบการนิเทศ (ถ้ามี)
                                    </label>

                                    <input type="file"
                                        name="quickwin_images[]"
                                        id="quickwin_images"
                                        class="form-control"
                                        accept="image/*"
                                        multiple>

                                    <small class="text-muted">
                                        รองรับ JPG / PNG (เลือกได้หลายรูป)
                                    </small>
                                </div>

                                <!-- กล่องแสดงพรีวิว -->
                                <div id="imagePreview"
                                    class="d-flex flex-wrap gap-3 mt-3"></div>


                                <div class="row mt-5">
                                    <div class="col-md-10 mx-auto">
                                        <div class="d-flex justify-content-center gap-4">

                                            <button type="submit"
                                                class="btn btn-success btn-lg px-5 rounded-pill shadow-sm btn-modern">
                                                Save
                                            </button>

                                            <a href="../supervision_start.php?edit=true"
                                                class="btn btn-outline-danger btn-lg px-5 rounded-pill shadow-sm btn-modern">
                                                Cancel
                                            </a>

                                        </div>
                                    </div>
                                </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- SweetAlert2 (ต้องมาก่อน JS ที่เรียกใช้ Swal) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // เน้นแถวเมื่อ checkbox ถูกเลือก
            const checkboxes = document.querySelectorAll('.qw-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.checked) {
                        this.parentElement.classList.add('selected');
                    } else {
                        this.parentElement.classList.remove('selected');
                    }
                });
            });

        });
    </script>

    <script>
        document.getElementById('evaluationForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const checkedCount = document.querySelectorAll('.qw-checkbox:checked').length;
            const otherText = document.getElementById('option_other').value.trim();

            if (checkedCount === 0 && otherText === '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'แจ้งเตือน',
                    text: 'กรุณาเลือกหัวข้อ Quick Win อย่างน้อย 1 ข้อ หรือระบุหัวข้ออื่น ๆ',
                    confirmButtonText: 'รับทราบ'
                });
                return;
            }

            const semesterVal = document.querySelector('select[name="semester"]').value;
            const academicYearVal = document.querySelector('select[name="academic_year"]').value;
            const tPidVal = document.querySelector('input[name="teacher_t_pid"]').value;

            if (!semesterVal || !academicYearVal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบ',
                    text: 'กรุณาเลือกภาคเรียนและปีการศึกษา',
                    confirmButtonText: 'ตกลง'
                });
                return;
            }

            // --- เริ่มตรวจสอบข้อมูลซ้ำแบบ Live Check ก่อนกดยืนยัน ---
            try {
                const dupFormData = new FormData();
                dupFormData.append('form_type', 'quickwin');
                dupFormData.append('t_pid', tPidVal);
                dupFormData.append('academic_year', academicYearVal);

                const dupResponse = await fetch('../check_duplicate.php', {
                    method: 'POST',
                    body: dupFormData
                });
                
                const dupResult = await dupResponse.json();

                if (dupResult.is_duplicate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'ข้อมูลซ้ำ',
                        text: `ครูท่านนี้ได้รับการนิเทศจุดเน้นในปีการศึกษา ${academicYearVal} นี้แล้วไม่สามารถบันทึกซ้ำได้`,
                        confirmButtonText: 'ตกลง'
                    });
                    return; // หยุดการทำงานทันที ข้อมูลฟอร์มยังคงอยู่
                }
            } catch (error) {
                console.error('Check duplicate error:', error);
            }
            // --- สิ้นสุดการตรวจสอบ ---

            Swal.fire({
                icon: 'question',
                title: 'ยืนยันการบันทึก',
                text: 'ต้องการบันทึกข้อมูล Quick Win ใช่หรือไม่?',
                showCancelButton: true,
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก'
            }).then(async result => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    const formData = new FormData(e.target);
                    try {
                        const response = await fetch('save_quickwin_data.php', { method: 'POST', body: formData });
                        const resultData = await response.json();
                        
                        if (resultData.status === 'success') {
                            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: resultData.message, timer: 2000, showConfirmButton: false }).then(() => {
                                window.location.href = '../index.php?success=save';
                            });
                        } else {
                            Swal.fire({ icon: 'error', title: 'พบข้อผิดพลาด', text: resultData.message, confirmButtonText: 'ตกลง' });
                        }
                    } catch (error) {
                        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                    }
                }
            });
        });
    </script>

    <script>
        const input = document.getElementById('quickwin_images');
        const previewContainer = document.getElementById('imagePreview');

        let selectedFiles = []; // เก็บไฟล์ที่เลือกจริง (สูงสุด 2)

        input.addEventListener('change', function() {

            const newFiles = Array.from(this.files);

            for (let file of newFiles) {

                if (!file.type.startsWith('image/')) {
                    continue;
                }

                if (selectedFiles.length >= 2) {
                    Swal.fire('จำกัดรูปภาพ', 'อัปโหลดได้ไม่เกิน 2 รูป', 'warning');
                    break;
                }

                selectedFiles.push(file);
            }

            updateInputFiles();
            renderPreview();
        });

        /* ---------- แสดงพรีวิว ---------- */
        function renderPreview() {
            previewContainer.innerHTML = '';

            selectedFiles.forEach((file, index) => {

                const reader = new FileReader();

                reader.onload = function(e) {

                    const wrapper = document.createElement('div');
                    wrapper.className = 'position-relative';

                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.width = '160px';
                    img.style.height = '160px';
                    img.style.objectFit = 'cover';
                    img.className = 'rounded shadow border';

                    // ปุ่มลบ
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    removeBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0 m-1 rounded-circle d-flex justify-content-center align-items-center';
                    removeBtn.style.width = '28px';
                    removeBtn.style.height = '28px';
                    removeBtn.style.padding = '0';

                    removeBtn.onclick = function() {
                        selectedFiles.splice(index, 1);
                        updateInputFiles();
                        renderPreview();
                    };

                    wrapper.appendChild(img);
                    wrapper.appendChild(removeBtn);
                    previewContainer.appendChild(wrapper);
                };

                reader.readAsDataURL(file);
            });
        }

        /* ---------- sync input.files ---------- */
        function updateInputFiles() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }
    </script>



</body>

</html>