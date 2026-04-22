<?php
// เรียกใช้ไฟล์ตั้งค่า Session 5 ชั่วโมง (ถอยออกไป 1 ชั้น)
if (file_exists('../config/session_config.php')) {
  require_once '../config/session_config.php';
}

date_default_timezone_set('Asia/Bangkok');

function getAcademicYear($date = null)
{
  $date = $date ?? date('Y-m-d');
  $year  = (int)date('Y', strtotime($date));
  $month = (int)date('n', strtotime($date));

  return ($month >= 5)
    ? $year + 543
    : $year + 542;
}

$currentAcademicYear = getAcademicYear();

$academicYears = [
  $currentAcademicYear - 1,
  $currentAcademicYear,
  $currentAcademicYear + 1
];


// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (file_exists('config/db_connect.php')) {
  require_once 'config/db_connect.php';
} elseif (file_exists('../config/db_connect.php')) {
  require_once '../config/db_connect.php';
}

$inspection_data = $_SESSION['inspection_data'] ?? [];

if (empty($inspection_data['t_pid'])) {
  die('ไม่พบข้อมูลครู (t_pid)');
}

$teacher_id = $inspection_data['t_pid'];


// ดึงรายการ KPI และคำถาม
$sql = "SELECT ind.id AS indicator_id, ind.title AS indicator_title, q.id AS question_id, q.question_text
        FROM kpi_indicators ind
        LEFT JOIN kpi_questions q ON ind.id = q.indicator_id
        ORDER BY ind.display_order, q.display_order";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$indicators = [];
$total_questions_count = 0;

foreach ($result as $row) {
  $iid = $row['indicator_id'];

  // สร้าง indicator เสมอ
  if (!isset($indicators[$iid])) {
    $indicators[$iid] = [
      'title' => $row['indicator_title'],
      'questions' => []
    ];
  }

  // ถ้ามีคำถาม → ค่อย push
  if (!empty($row['question_id'])) {
    $indicators[$iid]['questions'][] = $row;
    $total_questions_count++;
  }
}

?>

<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/kpi_form.css">

<form id="mainForm" method="POST" action="save_kpi_data.php" enctype="multipart/form-data">

  <input type="hidden" name="t_pid" id="t_pid" value="<?php echo htmlspecialchars($teacher_id); ?>">

  <h4 class="fw-bold text-primary">ข้อมูลผู้นิเทศ</h4>
  <div class="row mb-4">
    <div class="col-md-6">
      <strong>ชื่อผู้นิเทศ:</strong> <?php echo htmlspecialchars($inspection_data['supervisor_name'] ?? $_SESSION['user_name'] ?? 'ไม่มีข้อมูล'); ?>
    </div>
    <div class="col-md-6">
      <strong>ผู้รับการนิเทศ:</strong> <?php echo htmlspecialchars($inspection_data['teacher_name'] ?? 'ไม่มีข้อมูล'); ?>
    </div>
  </div>

  <hr class="my-4">

  <h4 class="fw-bold text-success">กรอกข้อมูลการนิเทศ</h4>

  <div class="row g-3 align-items-end mt-2 mb-4">

    <!-- รหัสวิชา -->
    <div class="col-md-2">
      <label class="form-label fw-bold">รหัสวิชา <span class="text-danger">*</span></label>
      <input type="text"
        name="subject_code"
        id="subject_code"
        class="form-control"
        placeholder="ระบุรหัสวิชา"
        required>
    </div>

    <!-- ชื่อวิชา -->
    <div class="col-md-3">
      <label class="form-label fw-bold">ชื่อวิชา <span class="text-danger">*</span></label>
      <input type="text"
        name="subject_name"
        id="subject_name"
        class="form-control"
        placeholder="ระบุชื่อวิชา"
        required>
    </div>

    <!-- ครั้งที่นิเทศ -->
    <div class="col-md-2">
      <label class="form-label fw-bold">ครั้งที่นิเทศ <span class="text-danger">*</span></label>
      <select name="inspection_time"
        id="inspection_time"
        class="form-select fw-bold text-center"
        required>
        <option value="">เลือกครั้งที่นิเทศ</option>
        <?php for ($i = 1; $i <= 9; $i++): ?>
          <option value="<?= $i ?>">ครั้งที่ <?= $i ?></option>
        <?php endfor; ?>
      </select>

    </div>

    <!-- วันที่นิเทศ -->
    <div class="col-md-2">
      <label class="form-label fw-bold">วันที่นิเทศ <span class="text-danger">*</span></label>
      <input type="date"
        name="supervision_date"
        id="supervision_date"
        class="form-control"
        value="<?= date('Y-m-d'); ?>"
        required>
    </div>

    <!-- ภาคเรียน -->
    <div class="col-md-1">
      <label class="form-label fw-bold">ภาคเรียน <span class="text-danger">*</span></label>
      <select name="semester" id="semester"
        class="form-select text-center" required>
        <option value="">เลือก</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">ฤดูร้อน</option>
      </select>
    </div>

    <!-- ปีการศึกษา -->
    <div class="col-md-2">
      <label class="form-label fw-bold">ปีการศึกษา <span class="text-danger">*</span></label>
      <select name="academic_year"
        id="academic_year"
        class="form-select fw-bold text-center"
        required>
        <?php foreach ($academicYears as $year): ?>
          <option value="<?= $year ?>"
            <?= $year === $currentAcademicYear ? 'selected' : '' ?>>
            <?= $year ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

  </div>


  <hr class="my-5">

  <?php foreach ($indicators as $indicator_id => $indicator_data) : ?>
    <div class="section-header mb-3">
      <h2 class="h5 fw-bold">
        <?php 
          $title = htmlspecialchars($indicator_data['title']);
          echo preg_replace('/^(ตัวชี้วัดที่\s*\d+)/u', '<span class="text-primary">$1</span>', $title);
        ?>
      </h2>
    </div>

    <?php if (!empty($indicator_data['questions'])) : ?>
      <?php foreach ($indicator_data['questions'] as $question) :
        $question_id = $question['question_id'];
      ?>
        <div class="card mb-3">
          <div class="card-body p-4">
            <div class="mb-3">
              <label class="form-label-question" for="rating_<?php echo $question_id; ?>">
                <?php echo htmlspecialchars($question['question_text']); ?> <span class="text-danger">*</span>
              </label>
            </div>

            <?php for ($i = 3; $i >= 0; $i--) : ?>
              <div class="form-check form-check-inline rating-radio-item">
                <input
                  class="form-check-input"
                  type="radio"
                  name="ratings[<?php echo $question_id; ?>]"
                  id="q<?php echo $question_id; ?>-<?php echo $i; ?>"
                  value="<?php echo $i; ?>"
                  <?php echo ($i == 3) ? 'required' : ''; ?>>
                <label class="form-check-label" for="q<?php echo $question_id; ?>-<?php echo $i; ?>">
                  <?php echo $i; ?>
                </label>
              </div>
            <?php endfor; ?>

          </div>
        </div>
      <?php endforeach; ?>
      <div class="card mb-4">
        <div class="card-body p-4">
          <div class="mb-3">
            <label for="indicator_suggestion_<?php echo $indicator_id; ?>" class="form-label fw-bold">ข้อค้นพบ / ข้อเสนอแนะ</label>
            <textarea class="form-control" id="indicator_suggestion_<?php echo $indicator_id; ?>" name="indicator_suggestions[<?php echo $indicator_id; ?>]" rows="3" placeholder="กรอกข้อเสนอแนะ..."></textarea>
          </div>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="card mt-4 border-primary">
    <div class="card-header bg-primary text-white fw-bold">ข้อเสนอแนะเพิ่มเติม</div>
    <div class="card-body">
      <textarea class="form-control" id="overall_suggestion" name="overall_suggestion" rows="4" placeholder="กรอกข้อเสนอแนะเพิ่มเติมเกี่ยวกับการนิเทศครั้งนี้..."></textarea>
    </div>
  </div>

  <div class="card mt-4 border-info">
    <div class="card-header bg-info text-white fw-bold">
      <i class="fas fa-images"></i> อัปโหลดรูปภาพประกอบ (สูงสุด 2 รูป)
    </div>
    <div class="card-body">

      <input type="file" id="imageInput" name="images[]" accept="image/*" multiple style="display: none;">

      <div class="d-flex align-items-center mb-3">
        <button type="button" id="selectImageBtn" class="btn btn-outline-primary" onclick="document.getElementById('imageInput').click()">
          <i class="fas fa-plus"></i> เลือกรูปภาพ
        </button>
        <small class="text-muted ms-3">* รองรับไฟล์ .jpg, .png (ไม่เกิน 2 รูป)</small>
      </div>

      <div id="previewContainer" class="d-flex flex-wrap gap-3"></div>
    </div>
  </div>

  <div class="d-flex justify-content-center gap-4 my-5">
    <button type="button"
      class="btn btn-success btn-lg px-5 rounded-pill shadow-sm"
      onclick="submitKpiForm()">
      Save Data
    </button>

    <button type="button"
      class="btn btn-outline-danger btn-lg px-5 rounded-pill shadow-sm"
      onclick="confirmBack()">
      Cancel
    </button>
  </div>

</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  let formChanged = false;

  // ตรวจจับการเปลี่ยนแปลงในฟอร์ม
  document.querySelectorAll('#mainForm input, #mainForm textarea, #mainForm select').forEach(el => {
    el.addEventListener('change', () => {
      formChanged = true;
    });
  });

  async function submitKpiForm() {
    // 1. ตรวจสอบข้อมูลพื้นฐาน
    const requiredInputs = [
      { id: 'subject_code', name: 'รหัสวิชา' },
      { id: 'subject_name', name: 'ชื่อวิชา' },
      { id: 'inspection_time', name: 'ครั้งที่นิเทศ' },
      { id: 'supervision_date', name: 'วันที่นิเทศ' },
      { id: 'semester', name: 'ภาคเรียน' },
      { id: 'academic_year', name: 'ปีการศึกษา' }
    ];

    for (let field of requiredInputs) {
      const el = document.getElementById(field.id);
      if (!el.value.trim()) {
        Swal.fire({
          icon: 'warning',
          title: 'ข้อมูลไม่ครบถ้วน',
          text: `กรุณากรอกข้อมูลในช่อง: ${field.name}`,
          confirmButtonText: 'ตกลง'
        }).then(() => {
          el.classList.add('is-invalid');
          setTimeout(() => el.classList.remove('is-invalid'), 3000);
        });
        return;
      }
    }

    // 2. ตรวจสอบการประเมิน KPI (Radio Buttons)
    const radioGroups = new Set([...document.querySelectorAll('input[type="radio"]')].map(el => el.name));
    for (let name of radioGroups) {
      const isChecked = document.querySelector(`input[name="${name}"]:checked`);
      if (!isChecked) {
        const radioEl = document.querySelector(`input[name="${name}"]`);
        const questionCard = radioEl.closest('.card');
        const questionLabel = questionCard.querySelector('.form-label-question').innerText.replace('*', '').trim();
        
        Swal.fire({
          icon: 'warning',
          title: 'ประเมินไม่ครบ',
          text: `กรุณาให้คะแนน: ${questionLabel}`,
          confirmButtonText: 'ตกลง'
        }).then(() => {
          questionCard.classList.add('border-danger', 'shadow');
          setTimeout(() => questionCard.classList.remove('border-danger', 'shadow'), 3000);
        });
        return;
      }
    }

    const timeVal = document.getElementById('inspection_time').value;
    const subjectCodeVal = document.getElementById('subject_code').value.trim();
    const tPidVal = document.getElementById('t_pid').value;
    const academicYearVal = document.getElementById('academic_year').value;

    // --- เริ่มตรวจสอบข้อมูลซ้ำแบบ Live Check ก่อนกดยืนยัน ---
    try {
      const dupFormData = new FormData();
      dupFormData.append('t_pid', tPidVal);
      dupFormData.append('subject_code', subjectCodeVal);
      dupFormData.append('inspection_time', timeVal);
      dupFormData.append('academic_year', academicYearVal);

      const dupResponse = await fetch('check_duplicate.php', {
        method: 'POST',
        body: dupFormData
      });
      
      const dupResult = await dupResponse.json();

      if (dupResult.is_duplicate) {
        Swal.fire({
          icon: 'warning',
          title: 'ข้อมูลซ้ำ',
          text: `ครูท่านนี้ได้รับการนิเทศวิชานี้ ในครั้งที่ ${timeVal} ปีการศึกษา ${academicYearVal} ไปแล้ว กรุณาเปลี่ยนครั้งที่นิเทศหรือรายวิชาใหม่`,
          confirmButtonText: 'ตกลง'
        });
        return; // หยุดการทำงานทันที ข้อมูลฟอร์มยังคงอยู่
      }
    } catch (e) {
      console.error('Check duplicate error:', e);
    }
    // --- สิ้นสุดการตรวจสอบ ---

    const confirm = await Swal.fire({
      title: 'ยืนยันการบันทึก?',
      text: 'คุณกำลังจะบันทึกข้อมูลการนิเทศครั้งที่ ' + timeVal,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'ตกลง',
      cancelButtonText: 'ยกเลิก'
    });

    if (confirm.isConfirmed) {
      // แสดง Loading ระหว่างส่งข้อมูล
      Swal.fire({
        title: 'กำลังบันทึก...',
        allowOutsideClick: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      const formData = new FormData(document.getElementById('mainForm'));

      try {
        const response = await fetch('save_kpi_data.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'duplicate') {
          // กรณีข้อมูลซ้ำ: แจ้งเตือนแต่ไม่ล้างค่า
          Swal.fire({
            title: result.title,
            text: result.text,
            icon: result.icon
          });
        } else if (result.status === 'success') {
          // กรณีสำเร็จ: แจ้งเตือนแล้วย้ายหน้า
          formChanged = false;
          Swal.fire('สำเร็จ', result.message, 'success').then(() => {
            window.location.href = 'index.php';
          });
        } else {
          Swal.fire('เกิดข้อผิดพลาด', result.message, 'error');
        }
      } catch (error) {
        console.error('Error:', error);
        Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
      }
    }
  }

  // จัดการรูปภาพ (อัปโหลดทีละรูป, ลบรูปได้, จำกัด 2 รูป)
  const imageInput = document.getElementById('imageInput');
  const previewContainer = document.getElementById('previewContainer');
  const selectImageBtn = document.getElementById('selectImageBtn');

  let selectedFiles = [];

  imageInput.addEventListener('change', function() {
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

    // ซ่อนปุ่มเลือกรูปถ้ารูปครบ 2 รูปแล้ว
    if (selectedFiles.length >= 2) {
      selectImageBtn.style.display = 'none';
    } else {
      selectImageBtn.style.display = 'block';
    }
  }

  function updateInputFiles() {
    const dataTransfer = new DataTransfer();
    selectedFiles.forEach(file => dataTransfer.items.add(file));
    imageInput.files = dataTransfer.files;
  }

  function confirmBack() {
    if (!formChanged) {
      window.location.href = 'index.php';
      return;
    }
    Swal.fire({
      title: 'คุณยังไม่ได้บันทึกข้อมูล',
      text: 'หากย้อนกลับ ข้อมูลจะหายทั้งหมด',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'ย้อนกลับ',
      cancelButtonText: 'อยู่หน้านี้'
    }).then(result => {
      if (result.isConfirmed) window.location.href = 'index.php';
    });
  }
</script>