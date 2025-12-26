<?php
// forms/quickwin_form.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. เชื่อมต่อฐานข้อมูล (PDO)
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
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
$supervisor_pid  = $inspection_data['s_p_id']          ?? '';
$teacher_pid     = $inspection_data['t_pid']           ?? '';
$school_name     = $inspection_data['school_name']     ?? '';
$subject_name    = $inspection_data['subject_name']    ?? '';

// 4. ดึงข้อมูลตัวเลือก (Options)
$options = [];
try {
    $sql = "SELECT OptionID, OptionText FROM quickwin_options ORDER BY OptionID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $seenTexts = [];
    foreach ($result as $row) {
        $key = trim($row['OptionText']);
        if (isset($seenTexts[$key])) {
            continue;
        }
        $seenTexts[$key] = true;
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
</head>

<body>

    <a href="../supervision_start.php?edit=true" class="btn btn-outline-danger fixed-back-btn shadow-sm bg-white">
        <i class="fas fa-arrow-left"></i> ย้อนกลับ
    </a>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-10">
                <div class="card shadow-lg">
                    <div class="card-header bg-danger text-white text-center py-3">
                        <h4 class="mb-0 fw-bold">
                            <i class="fas fa-bullseye me-2"></i> แบบบันทึกจุดเน้น (Quick Win)
                        </h4>
                    </div>

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
                            </div>
                        </div>

                        <form action="save_quickwin_data.php" method="POST" id="quickwinForm">

                            <input type="hidden" name="supervisor_p_id" value="<?php echo htmlspecialchars($supervisor_pid); ?>">
                            <input type="hidden" name="teacher_t_pid" value="<?php echo htmlspecialchars($teacher_pid); ?>">

                            <div class="mb-4">
                                <label class="form-label form-label-bold text-danger fs-5 mb-3">
                                    <i class="fas fa-list-check"></i> เลือกหัวข้อจุดเน้น (เลือกได้สูงสุด 10 ข้อ)
                                </label>

                                <div class="row">
                                    <div class="col-md-6 border-end">
                                        <?php foreach ($col1_options as $opt): ?>
                                            <div class="form-check qw-select">
                                                <input class="form-check-input qw-checkbox" type="checkbox"
                                                    name="option_ids[]"
                                                    value="<?php echo $opt['OptionID']; ?>"
                                                    id="opt_<?php echo $opt['OptionID']; ?>">
                                                <label class="form-check-label option-text" for="opt_<?php echo $opt['OptionID']; ?>">
                                                    <?php echo htmlspecialchars($opt['OptionID'] . '. ' . $opt['OptionText']); ?>
                                                </label>
                                    </div>
                                <?php endforeach; ?>
                                </div>

                                <div class="col-md-6">
                                    <?php foreach ($col2_options as $opt): ?>
                                        <div class="form-check qw-select">
                                            <input class="form-check-input qw-checkbox" type="checkbox"
                                                name="option_ids[]"
                                                value="<?php echo $opt['OptionID']; ?>"
                                                id="opt_<?php echo $opt['OptionID']; ?>">
                                            <label class="form-check-label option-text" for="opt_<?php echo $opt['OptionID']; ?>">
                                                <?php echo htmlspecialchars($opt['OptionID'] . '. ' . $opt['OptionText']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-text text-danger mt-2" id="limit-warning" style="display:none;">
                                * คุณเลือกครบ 10 ข้อแล้ว
                            </div>
                    </div>

                    <div class="mb-4">
                        <label for="option_other" class="form-label form-label-bold">
                            หรือ อื่นๆ ( กรณีหัวข้อที่ต้องการนิเทศไม่ได้อยู่ในรายการด้านบน )
                        </label>
                        <textarea class="form-control" name="option_other" id="option_other" rows="4"
                            placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
                    </div>

                    <div class="d-grid gap-2 col-md-8 mx-auto mt-5">
                        <button type="submit" class="btn btn-success btn-lg shadow">
                            <i class="fas fa-save me-2"></i> บันทึกข้อมูล Quick Win
                        </button>
                    </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Logic จำกัดการเลือก Checkbox สูงสุด 10 อัน
            const maxAllowed = 10;
            const checkboxes = document.querySelectorAll('.qw-checkbox');
            const warningMsg = document.getElementById('limit-warning');

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const checkedCount = document.querySelectorAll('.qw-checkbox:checked').length;

                    if (checkedCount > maxAllowed) {
                        this.checked = false; // ยกเลิกการเลือกอันล่าสุด
                        alert('คุณสามารถเลือกหัวข้อจุดเน้นได้สูงสุด ' + maxAllowed + ' ข้อ');
                    }

                    // แสดงข้อความเตือนเมื่อครบ
                    if (checkedCount >= maxAllowed) {
                        warningMsg.style.display = 'block';
                    } else {
                        warningMsg.style.display = 'none';
                    }
                });
            });

            // แจ้งบันทึกข้อมูลไม่สำเร็จ
            // <?php
                // if (isset($_SESSION['flash_message'])) {
                //     $msg = $_SESSION['flash_message'];
                //     echo "alert('" . addslashes($msg) . "');";
                //     unset($_SESSION['flash_message']);
                // }
                // 
                ?>
        });
    </script>
</body>

</html>