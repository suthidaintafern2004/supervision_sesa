<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once '../config/db_connect.php';

// 1. รับค่าจาก URL
$supervisor_id = $_SESSION['user_id'] ?? '';
$t_pid = $_GET['t_pid'] ?? '';
$subject_code = $_GET['subject_code'] ?? '';
$inspection_time = $_GET['inspection_time'] ?? '';

if (!$t_pid || !$subject_code || !$inspection_time) {
  die('<div class="alert alert-danger text-center">ข้อมูลไม่ครบถ้วน</div>');
}

// 2. ดึงข้อมูลหลักของการนิเทศ (Session)
$stmt_session = $conn->prepare("SELECT * FROM supervision_sessions WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ?");
$stmt_session->execute([$supervisor_id, $t_pid, $subject_code, $inspection_time]);
$session_data = $stmt_session->fetch(PDO::FETCH_ASSOC);

if (!$session_data) {
  die('<div class="alert alert-warning text-center">ไม่พบข้อมูลการนิเทศ</div>');
}

// 3. ดึงคะแนนเดิม (Answers)
$rating_stmt = $conn->prepare("SELECT question_id, rating_score FROM kpi_answers WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ?");
$rating_stmt->execute([$supervisor_id, $t_pid, $subject_code, $inspection_time]);
$ratings = $rating_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// 4. ดึงหัวข้อ KPI และคำถาม
$sql_kpi = "SELECT ind.id AS indicator_id, ind.title AS indicator_title, q.id AS question_id, q.question_text 
            FROM kpi_indicators ind LEFT JOIN kpi_questions q ON ind.id = q.indicator_id 
            ORDER BY ind.display_order, q.display_order";
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
?>

<!DOCTYPE html>
<html lang="th">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>แก้ไขข้อมูลการนิเทศ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">
  <link rel="stylesheet" href="css/kpi_form.css">
</head>

<body class="bg-light">

  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        <div class="card shadow-sm border-0 mb-4 rounded-4">
          <div class="card-body p-4 bg-white">
            <h2 class="text-primary fw-bold mb-3">แก้ไขข้อมูลการนิเทศ</h2>
            <p class="text-muted">ผู้นิเทศ: <strong><?= htmlspecialchars($_SESSION['user_name'] ?? '-') ?></strong></p>
          </div>
        </div>

        <form id="evaluationForm" method="POST" action="update_kpi_data.php" onsubmit="return validateKpiForm()">
          <input type="hidden" name="old_t_pid" value="<?= htmlspecialchars($t_pid) ?>">
          <input type="hidden" name="old_subject_code" value="<?= htmlspecialchars($subject_code) ?>">
          <input type="hidden" name="old_inspection_time" value="<?= htmlspecialchars($inspection_time) ?>">

          <div class="card shadow-sm border-0 mb-4 rounded-4">
            <div class="card-body p-4">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">รหัสวิชา</label>
                  <input type="text" name="subject_code" class="form-control" value="<?= htmlspecialchars($session_data['subject_code']) ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">ชื่อวิชา</label>
                  <input type="text" name="subject_name" class="form-control" value="<?= htmlspecialchars($session_data['subject_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">ครั้งที่นิเทศ</label>
                  <select name="inspection_time" class="form-select">
                    <?php for ($i = 1; $i <= 9; $i++): ?>
                      <option value="<?= $i ?>" <?= ($session_data['inspection_time'] == $i) ? 'selected' : '' ?>>ครั้งที่ <?= $i ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-bold">วันที่นิเทศ</label>
                  <div class="form-control bg-light"><?= date('d/m/Y', strtotime($session_data['supervision_date'])) ?></div>
                  <input type="hidden" name="supervision_date" value="<?= $session_data['supervision_date'] ?>">
                </div>
              </div>
            </div>
          </div>

          <?php foreach ($indicators as $iid => $data): ?>
            <div class="section-header mb-3 mt-5">
              <h4 class="fw-bold text-dark border-start border-4 border-primary ps-3"><?= htmlspecialchars($data['title']) ?></h4>
            </div>

            <?php foreach ($data['questions'] as $q): $qid = $q['question_id']; ?>
              <div class="card mb-3 border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                  <label class="form-label-question d-block mb-3 fw-bold text-secondary"><?= htmlspecialchars($q['question_text']) ?></label>

                  <div class="d-flex justify-content-start gap-3 flex-wrap">
                    <?php for ($i = 3; $i >= 0; $i--):
                      $checked = (isset($ratings[$qid]) && $ratings[$qid] == $i) ? 'checked' : '';
                    ?>
                      <div class="rating-radio-item">
                        <input type="radio"
                          name="ratings[<?= $qid ?>]"
                          id="q<?= $qid ?>_<?= $i ?>"
                          value="<?= $i ?>"
                          class="form-check-input"
                          <?= $checked ?> required>
                        <label class="form-check-label" for="q<?= $qid ?>_<?= $i ?>"><?= $i ?></label>
                      </div>
                    <?php endfor; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>

          <div class="card shadow-sm border-0 mb-5 rounded-4 overflow-hidden">
            <div class="card-header bg-white fw-bold py-3">📝 ข้อเสนอแนะในภาพรวม</div>
            <div class="card-body p-0">
              <textarea class="form-control border-0" name="overall_suggestion" rows="4" style="resize: none;" placeholder="ระบุข้อเสนอแนะเพิ่มเติม..."><?= htmlspecialchars($session_data['overall_suggestion']) ?></textarea>
            </div>
          </div>

          <div class="text-center mb-5">
            <button type="submit" class="btn btn-primary btn-lg px-5 shadow rounded-pill">บันทึกการแก้ไข</button>
            <a href="supervision_list.php" class="btn btn-light btn-lg px-5 ms-2 rounded-pill shadow-sm">ยกเลิก</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    const totalQuestions = <?= $total_questions_count ?>;

    function validateKpiForm() {
      const checked = document.querySelectorAll('input[type="radio"]:checked');
      if (checked.length < totalQuestions) {
        alert('❌ กรุณาตอบคำถามให้ครบทุกข้อ (ตอบแล้ว ' + checked.length + '/' + totalQuestions + ' ข้อ)');
        return false;
      }
      return confirm('⚠️ ยืนยันการบันทึกการแก้ไขใช่หรือไม่?');
    }
  </script>
</body>

</html>