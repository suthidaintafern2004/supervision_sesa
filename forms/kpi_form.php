<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

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

  <div class="alert alert-info py-2">
    <small><i class="fas fa-info-circle"></i> ท่านสามารถเลือก "ครั้งที่นิเทศ" ซ้ำกับเดิมได้ หากเป็นการนิเทศใน <strong>รหัสวิชาอื่น</strong></small>
  </div>
  <hr class="my-4">

  <h4 class="fw-bold text-success">กรอกข้อมูลการนิเทศ</h4>
  <div class="row g-3 align-items-end mt-2 mb-4">

    <!-- รหัสวิชา -->
    <div class="col-md-2">
      <label class="form-label fw-bold">รหัสวิชา</label>
      <input type="text"
        name="subject_code"
        id="subject_code"
        class="form-control"
        placeholder="ระบุรหัสวิชา"
        required>
    </div>

    <!-- ชื่อวิชา -->
    <div class="col-md-3">
      <label class="form-label fw-bold">ชื่อวิชา</label>
      <input type="text"
        name="subject_name"
        id="subject_name"
        class="form-control"
        placeholder="ระบุชื่อวิชา"
        required>
    </div>

    <!-- ครั้งที่นิเทศ -->
    <div class="col-md-2">
      <label class="form-label fw-bold text-danger">ครั้งที่นิเทศ</label>
      <select name="inspection_time"
        id="inspection_time"
        class="form-select fw-bold text-center"
        required>
        <option value="">เลือก</option>
      </select>
    </div>

    <!-- วันที่นิเทศ -->
    <div class="col-md-2">
      <label class="form-label fw-bold">วันที่นิเทศ</label>
      <input type="date"
        name="supervision_date"
        class="form-control"
        value="<?= date('Y-m-d'); ?>"
        required>
    </div>

    <!-- ปีการศึกษา -->
    <div class="col-md-3">
      <label class="form-label fw-bold">ปีการศึกษา</label>
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
      <h2 class="h5">
        <?php echo htmlspecialchars($indicator_data['title']); ?>
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
                <?php echo htmlspecialchars($question['question_text']); ?>
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

      <input type="file" id="imageInput" name="images[]" accept="image/*" multiple style="display: none;" onchange="handleFiles(this)">

      <div class="d-flex align-items-center mb-3">
        <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('imageInput').click()">
          <i class="fas fa-plus"></i> เลือกรูปภาพ
        </button>
        <small class="text-muted ms-3">* รองรับไฟล์ .jpg, .png (ไม่เกิน 2 รูป)</small>
      </div>

      <div id="previewContainer" class="d-flex flex-wrap gap-3"></div>
    </div>
  </div>

  <div class="d-flex justify-content-center my-4">
    <button type="button"
      class="btn btn-success fs-5 btn-hover-blue px-4 py-2"
      onclick="submitKpiForm()">
      บันทึกข้อมูล
    </button>

    <button type="button"
      class="btn btn-danger fs-5 px-4 py-2 ms-4"
      onclick="confirmBack()">
      ย้อนกลับ
    </button>
  </div>

</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  function loadInspectionTimes() {
    const subjectInput = document.getElementById('subject_code');
    const subjectCode = subjectInput.value.trim();
    const teacherId = document.getElementById('t_pid').value;
    const select = document.getElementById('inspection_time');

    // reset dropdown
    select.innerHTML = '<option value="">กำลังตรวจสอบ...</option>';
    select.disabled = true;

    if (!subjectCode || !teacherId) {
      select.innerHTML = '<option value="">กรุณากรอกรหัสวิชา</option>';
      return;
    }

    fetch(`forms/get_available_times.php?t_pid=${teacherId}&subject_code=${encodeURIComponent(subjectCode)}`)
      .then(res => res.json())
      .then(data => {

        // 🔴 เข้าเงื่อนไข 14 วัน
        if (data.success === false && data.reason === '14_DAYS') {

          Swal.fire({
            icon: 'warning',
            title: 'ไม่สามารถบันทึกได้',
            text: data.message || 'วิชานี้ถูกนิเทศภายใน 14 วันที่ผ่านมา',
            confirmButtonText: 'เข้าใจแล้ว'
          });

          // ✅ ล้างเฉพาะรหัสวิชา + ครั้งที่นิเทศ
          subjectInput.value = '';
          select.innerHTML = '<option value="">กรุณากรอกรหัสวิชาใหม่</option>';
          select.disabled = true;

          // ❌ ห้าม reload
          return;
        }

        // ❌ error อื่น ๆ
        if (!data.success) {
          select.innerHTML = '<option value="">เกิดข้อผิดพลาด</option>';
          return;
        }

        // ❌ ครบ 9 ครั้ง
        if (!Array.isArray(data.available_times) || data.available_times.length === 0) {
          select.innerHTML = '<option value="">ครบ 9 ครั้งแล้ว</option>';
          return;
        }

        // ✅ ปกติ
        select.disabled = false;
        select.innerHTML = '<option value="">เลือกครั้งที่นิเทศ</option>';

        data.available_times.forEach(time => {
          const intTime = parseInt(time, 10);
          if (!Number.isInteger(intTime)) return;

          const opt = document.createElement('option');
          opt.value = intTime;
          opt.textContent = `ครั้งที่ ${intTime}`;
          select.appendChild(opt);
        });
      })
      .catch(err => {
        console.error(err);
        select.innerHTML = '<option value="">ไม่สามารถโหลดข้อมูล</option>';
      });
  }

  // debounce ตอนพิมพ์รหัสวิชา
  let subjectTimer = null;
  document.getElementById('subject_code').addEventListener('input', () => {
    clearTimeout(subjectTimer);
    subjectTimer = setTimeout(loadInspectionTimes, 500);
  });

  // Validation เดิมของคุณ
  const totalQuestions = <?php echo $total_questions_count; ?>;
  async function validateKpiForm() {
    const timeVal = document.getElementById('inspection_time').value;

    const timeSelect = document.getElementById('inspection_time');
    if (!timeSelect.value) {
      Swal.fire('ครั้งที่นิเทศ', 'กรุณาเลือกครั้งที่นิเทศ', 'warning');
      return false;
    }

    const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
    if (checkedRadios.length < totalQuestions) {
      Swal.fire('ข้อมูลไม่ครบ', `กรุณาตอบคำถามให้ครบทุกข้อ`, 'warning');
      return false;
    }

    const confirm = await Swal.fire({
      title: 'ยืนยันการบันทึก?',
      text: "คุณกำลังจะบันทึกการนิเทศครั้งที่ " + timeVal,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'ตกลง',
      cancelButtonText: 'ยกเลิก'
    });

    return confirm.isConfirmed;
  }

  // รักษาฟังก์ชัน Scroll และ Image Preview เดิม
  function scrollToTop() {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  }
  window.onscroll = function() {
    const btn = document.getElementById('scrollToTopBtn');
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) btn.style.display = "block";
    else btn.style.display = "none";
  };

  async function submitKpiForm() {

    const subjectName = document.getElementById('subject_name').value.trim();
    const subjectCode = document.getElementById('subject_code').value.trim();
    const timeVal = document.getElementById('inspection_time').value;

    if (!subjectCode) {
      Swal.fire('ข้อมูลไม่ครบ', 'กรุณากรอกรหัสวิชา', 'warning');
      return;
    }

    if (!subjectName) {
      Swal.fire('ข้อมูลไม่ครบ', 'กรุณากรอกชื่อวิชา', 'warning');
      return;
    }

    if (!timeVal) {
      Swal.fire('ครั้งที่นิเทศ', 'กรุณาเลือกครั้งที่นิเทศ', 'warning');
      return;
    }

    const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
    if (checkedRadios.length < <?= $total_questions_count ?>) {
      Swal.fire('ข้อมูลไม่ครบ', 'กรุณาตอบคำถามให้ครบทุกข้อ', 'warning');
      return;
    }

    const confirm = await Swal.fire({
      title: 'ยืนยันการบันทึก?',
      text: 'คุณกำลังจะบันทึกข้อมูลการนิเทศ',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'ตกลง',
      cancelButtonText: 'ยกเลิก'
    });

    if (confirm.isConfirmed) {
      document.getElementById('mainForm').submit();
    }
  }

  function handleFiles(input) {
    const preview = document.getElementById('previewContainer');
    preview.innerHTML = ''; // ล้าง preview เดิม

    const files = Array.from(input.files);

    if (files.length > 2) {
      Swal.fire('จำกัดรูปภาพ', 'อัปโหลดได้ไม่เกิน 2 รูป', 'warning');
      input.value = '';
      return;
    }

    files.forEach(file => {
      if (!file.type.startsWith('image/')) return;

      const reader = new FileReader();

      reader.onload = e => {
        const wrapper = document.createElement('div');
        wrapper.className = 'position-relative';

        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.width = '160px';
        img.style.height = 'auto';
        img.className = 'rounded shadow border';

        // ปุ่มลบรูป
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.innerHTML = '&times;';
        removeBtn.className = 'btn btn-danger btn-sm position-absolute top-0 end-0';
        removeBtn.onclick = () => {
          input.value = '';
          preview.innerHTML = '';
        };

        wrapper.appendChild(img);
        wrapper.appendChild(removeBtn);
        preview.appendChild(wrapper);
      };

      reader.readAsDataURL(file);
    });
  }

  let formChanged = false;

  // ตรวจว่ามีการแก้ไขฟอร์มหรือยัง
  document.querySelectorAll('#mainForm input, #mainForm textarea, #mainForm select')
    .forEach(el => {
      el.addEventListener('change', () => {
        formChanged = true;
      });
    });

  function confirmBack() {
    if (!formChanged) {
      // ยังไม่ได้แก้ไขอะไร
      window.location.href = 'index.php'; // 👈 หน้า list / admin
      return;
    }

    Swal.fire({
      title: 'คุณยังไม่ได้บันทึกข้อมูล',
      text: 'หากย้อนกลับ ข้อมูลที่กรอกจะหายทั้งหมด',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'ย้อนกลับ',
      cancelButtonText: 'อยู่หน้านี้'
    }).then(result => {
      if (result.isConfirmed) {
        window.location.href = 'index.php'; // 👈 หน้า list / admin
      }
    });
  }
</script>