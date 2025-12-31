<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db_connect.php';

// รับค่าจาก URL
$supervisor_id = $_SESSION['user_id'] ?? '';
$t_pid         = $_GET['t_pid'] ?? '';
$subject_code  = $_GET['subject_code'] ?? '';
$inspection_time = $_GET['inspection_time'] ?? '';

if (!$t_pid || !$subject_code || !$inspection_time) {
    die('<div class="alert alert-danger text-center">ข้อมูลไม่ครบถ้วน</div>');
}

// ดึงข้อมูล session + ชื่อครู + วันที่นิเทศ
$stmt_session = $conn->prepare("
    SELECT ss.*, 
           t.f_name, t.m_name, t.l_name,
           p.prefix_name,
           CONCAT(p.prefix_name,' ',t.f_name,' ',t.l_name) AS teacher_name
    FROM supervision_sessions ss
    LEFT JOIN teacher t ON ss.teacher_t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    WHERE ss.supervisor_p_id = ? 
      AND ss.teacher_t_pid = ? 
      AND ss.subject_code = ? 
      AND ss.inspection_time = ?
");
$stmt_session->execute([$supervisor_id, $t_pid, $subject_code, $inspection_time]);
$session_data = $stmt_session->fetch(PDO::FETCH_ASSOC);

if (!$session_data) {
    die('<div class="alert alert-warning text-center">ไม่พบข้อมูลการนิเทศ</div>');
}

// ดึงคะแนนเดิม
$rating_stmt = $conn->prepare("SELECT question_id, rating_score FROM kpi_answers WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ?");
$rating_stmt->execute([$supervisor_id, $t_pid, $subject_code, $inspection_time]);
$ratings = $rating_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// ดึง KPI + คำถาม
$sql_kpi = "SELECT ind.id AS indicator_id, ind.title AS indicator_title, q.id AS question_id, q.question_text FROM kpi_indicators ind LEFT JOIN kpi_questions q ON ind.id = q.indicator_id ORDER BY ind.display_order, q.display_order";
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

// ดึงข้อเสนอแนะรายตัวบ่งชี้
$notes_stmt = $conn->prepare("SELECT indicator_id, suggestion_text FROM kpi_indicator_suggestions WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ?");
$notes_stmt->execute([$supervisor_id, $t_pid, $subject_code, $inspection_time]);
$indicator_suggestions = $notes_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// ดึงรูปภาพเดิม
$stmtImg = $conn->prepare("SELECT file_name FROM images WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ?");
$stmtImg->execute([$supervisor_id, $t_pid, $subject_code, $inspection_time]);
$existing_images_db = $stmtImg->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>แก้ไขข้อมูลการนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-sm border-0 mb-4 rounded-4">
            <div class="card-body p-4 bg-white">
                <h2 class="text-primary fw-bold mb-3">แก้ไขข้อมูลการนิเทศ</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <p class="mb-0">
                            ผู้นิเทศ: <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
                            <span class="mx-3">|</span>
                            ผู้รับนิเทศ: <strong><?= htmlspecialchars($session_data['teacher_name']) ?></strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <form id="evaluationForm" method="POST" action="update_kpi_data.php" enctype="multipart/form-data" onsubmit="return validateKpiForm()">
            <input type="hidden" name="old_t_pid" value="<?= $t_pid ?>">
            <input type="hidden" name="old_subject_code" value="<?= $subject_code ?>">
            <input type="hidden" name="old_inspection_time" value="<?= $inspection_time ?>">

            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="fw-bold">รหัสวิชา</label>
                            <input type="text" name="subject_code" class="form-control" value="<?= htmlspecialchars($session_data['subject_code']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold">ชื่อวิชา</label>
                            <input type="text" name="subject_name" class="form-control" value="<?= htmlspecialchars($session_data['subject_name']) ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold">ครั้งที่นิเทศ</label>
                            <select name="inspection_time" class="form-select">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($session_data['inspection_time'] == $i) ? 'selected' : '' ?>>ครั้งที่ <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="fw-bold">วันที่นิเทศ</label>
                            <input type="text" class="form-control bg-light" value="<?= date('d/m/Y', strtotime($session_data['supervision_date'])) ?>" readonly>
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
                    <textarea class="form-control" name="overall_suggestion" rows="3"><?= htmlspecialchars($session_data['overall_suggestion']) ?></textarea>
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
                <button type="button"
                    class="btn btn-modern btn-save shadow"
                    onclick="confirmSave()">
                    <i class="fas fa-save"></i> บันทึกการแก้ไข
                </button>

                <!-- ยกเลิก -->
                <button type="button"
                    class="btn btn-modern btn-cancel shadow"
                    onclick="window.location.href='../index.php'">
                    <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                </button>

                <!-- ลบ -->
                <button type="button"
                    class="btn btn-modern btn-delete shadow"
                    onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> ลบแบบบันทึก
                </button>

            </div>

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
                const totalQuestions = <?= $total_questions_count ?>;

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
                        text: 'การลบจะไม่สามารถกู้คืนได้ ต้องการดำเนินการต่อหรือไม่?',
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


</body>

</html>