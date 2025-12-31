<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (file_exists('config/db_connect.php')) {
  require_once 'config/db_connect.php';
} elseif (file_exists('../config/db_connect.php')) {
  require_once '../config/db_connect.php';
}

$inspection_data = $_SESSION['inspection_data'] ?? [];
$supervisor_id = $_SESSION['user_id'] ?? '';
$teacher_id    = $inspection_data['t_pid'] ?? ''; // <<< สำคัญ

// --------------------
// ดึง KPI + คำถาม
// --------------------
$sql = "SELECT 
            ind.id AS indicator_id,
            ind.title AS indicator_title,
            q.id AS question_id,
            q.question_text
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
  $indicators[$iid]['title'] = $row['indicator_title'];

  if ($row['question_id']) {
    $indicators[$iid]['questions'][] = $row;
    $total_questions_count++;
  }
}
?>

<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/kpi_form.css">

<form method="POST"
  action="save_kpi_data.php"
  enctype="multipart/form-data"
  onsubmit="return validateKpiForm()">

  <!-- 🔴 ส่ง t_pid ไปกับฟอร์ม -->
  <input type="hidden" name="t_pid" value="<?php echo htmlspecialchars($teacher_id); ?>">

  <form id="evaluationForm" method="POST" action="save_kpi_data.php" enctype="multipart/form-data" onsubmit="return validateKpiForm()">

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

    <div class="row g-3 mt-2 mb-4">
      <div class="col-md-6">
        <label for="subject_code" class="form-label fw-bold">รหัสวิชา</label>
        <input type="text" id="subject_code" name="subject_code" class="form-control" placeholder="เช่น ท0001" required>
      </div>
      <div class="col-md-6">
        <label for="subject_name" class="form-label fw-bold">ชื่อวิชา</label>
        <input type="text" id="subject_name" name="subject_name" class="form-control" placeholder="เช่น ภาษาไทย" required>
      </div>
      <div class="col-md-6">
        <label for="inspection_time" class="form-label fw-bold">ครั้งที่นิเทศ</label>
        <select id="inspection_time" name="inspection_time" class="form-select" required>
          <option value="" disabled selected>-- เลือกครั้งที่นิเทศ --</option>
          <?php for ($i = 1; $i <= 9; $i++):
            $history_text = "";
            if (isset($history_info[$i])) {
              $subjects = implode(', ', array_unique($history_info[$i]));
              $history_text = " (เคยนิเทศ: $subjects)";
            }
          ?>
            <option value="<?php echo $i; ?>">
              <?php echo $i . $history_text; ?>
            </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label for="supervision_date" class="form-label fw-bold">วันที่การนิเทศ</label>
        <input type="date" id="supervision_date" name="supervision_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
      </div>
    </div>

    <hr class="my-5">

    <?php foreach ($indicators as $indicator_id => $indicator_data) : ?>
      <div class="section-header mb-3">
        <h2 class="h5"><?php echo htmlspecialchars($indicator_data['title']); ?></h2>
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
      <button type="submit" class="btn btn-success fs-5 btn-hover-blue px-4 py-2">
        บันทึกข้อมูล
      </button>
    </div>
  </form>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    function showPopup(icon, title, text, timer = 3000) {
      Swal.fire({
        icon: icon, // success | error | warning | info | question
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
    let selectedFiles = [];

    function handleFiles(input) {
      const files = Array.from(input.files);

      if (selectedFiles.length + files.length > 2) {
        alert('อัปโหลดได้สูงสุดแค่ 2 รูปเท่านั้น');
        return;
      }

      files.forEach(file => {
        if (selectedFiles.length < 2) {
          selectedFiles.push(file);
        }
      });

      renderPreview();
      updateInputFiles();
    }

    function renderPreview() {
      const container = document.getElementById('previewContainer');
      container.innerHTML = '';

      selectedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
          const wrapper = document.createElement('div');
          wrapper.className = 'img-preview-wrapper shadow-sm';
          wrapper.innerHTML = `
            <img src="${e.target.result}">
            <button type="button" class="remove-btn" onclick="removeImage(${index})">
                <i class="fas fa-times"></i>
            </button>
        `;
          container.appendChild(wrapper);
        }
        reader.readAsDataURL(file);
      });
    }

    function removeImage(index) {
      selectedFiles.splice(index, 1);
      renderPreview();
      updateInputFiles();
    }

    function updateInputFiles() {
      const dataTransfer = new DataTransfer();
      selectedFiles.forEach(file => dataTransfer.items.add(file));
      document.getElementById('imageInput').files = dataTransfer.files;
    }
  </script>

  <button onclick="scrollToBottom()" class="btn btn-primary rounded-pill position-fixed bottom-0 end-0 m-3 shadow" title="เลื่อนลงล่างสุด" style="z-index: 99;">
    <i class="fas fa-arrow-down"></i>
  </button>

  <button onclick="scrollToTop()" id="scrollToTopBtn" class="btn btn-secondary rounded-pill position-fixed bottom-0 end-0 m-3 shadow" title="เลื่อนขึ้นบนสุด" style="z-index: 99; margin-bottom: 80px !important; display: none;">
    <i class="fas fa-arrow-up"></i>
  </button>

  <script>
    const scrollToTopBtn = document.getElementById("scrollToTopBtn");
    const totalQuestions = <?php echo $total_questions_count; ?>;

    function validateKpiForm() {
      const subjectCode = document.getElementById('subject_code').value;
      const subjectName = document.getElementById('subject_name').value;
      const inspectionTime = document.getElementById('inspection_time').value;
      const supervisionDate = document.getElementById('supervision_date').value;

      if (!subjectCode || !subjectName || !inspectionTime || !supervisionDate) {
        showPopup(
          'warning',
          '⚠️ ข้อมูลไม่ครบ',
          'กรุณากรอกรหัสวิชา ชื่อวิชา ครั้งที่ และวันที่ให้ครบถ้วน'
        );
        return false;
      }

      const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
      if (checkedRadios.length < totalQuestions) {
        showPopup(
          'error',
          '❌ ตอบคำถามไม่ครบ',
          `คุณตอบไปแล้ว ${checkedRadios.length} / ${totalQuestions} ข้อ`
        );
        return false;
      }

      // Confirm ก่อนบันทึก
      Swal.fire({
        icon: 'question',
        title: '📌 ยืนยันการบันทึก',
        text: 'ต้องการบันทึกข้อมูลการนิเทศใช่หรือไม่?',
        showCancelButton: true,
        confirmButtonText: '✅ บันทึก',
        cancelButtonText: '❌ ยกเลิก'
      }).then((result) => {
        if (result.isConfirmed) {
          document.querySelector('form').submit();
        }
      });

      return false;
    }

    function scrollToBottom() {
      window.scrollTo(0, document.body.scrollHeight);
    }

    function scrollToTop() {
      window.scrollTo(0, 0);
    }

    window.onscroll = function() {
      if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        scrollToTopBtn.style.display = "block";
      } else {
        scrollToTopBtn.style.display = "none";
      }
    };

    /* =========================
   AUTO RANDOM SCORE (ไม่ใช้ปุ่ม)
   ========================= */
    window.addEventListener('load', () => {
      const names = new Set(
        Array.from(document.querySelectorAll('input[type="radio"]'))
        .map(r => r.name)
      );

      names.forEach(name => {
        const group = document.querySelectorAll(`input[name="${name}"]`);
        if (group.length > 0) {
          const randomIndex = Math.floor(Math.random() * group.length);
          group[randomIndex].checked = true;
        }
      });
    });
  </script>