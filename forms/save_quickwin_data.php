<?php
/* ==================================================
   save_quickwin_data.php
   รองรับ Quick Win + อัปโหลดรูป
   ================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   เชื่อมต่อฐานข้อมูล
========================= */
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

/* =========================
   FORM TYPE (ใช้ระบุประเภทรูป)
========================= */
$formType = 'qw';

/* =========================
   โฟลเดอร์อัปโหลดรูป
========================= */
$uploadDir = __DIR__ . '/../uploads/quickwin/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

/* =========================
   ฟังก์ชัน redirect + flash
========================= */
function redirect_with_flash_message($message, $location, $type = 'warning')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type;
    header("Location: {$location}");
    exit();
}

/* =========================
   ฟังก์ชันคำนวณปีการศึกษา
========================= */
function getAcademicYear($date)
{
    $year  = (int)date('Y', strtotime($date));
    $month = (int)date('n', strtotime($date));

    return ($month >= 5)
        ? $year + 543
        : $year + 542;
}

/* =========================
   ตรวจสอบ Method
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

if (!isset($_SESSION['inspection_data'])) {
    redirect_with_flash_message(
        'Session หมดอายุ กรุณาทำรายการใหม่',
        '../index.php',
        'warning'
    );
}

/* =========================
   รับค่าจากฟอร์ม
========================= */
$p_id         = trim($_POST['supervisor_p_id'] ?? '');
$t_id         = trim($_POST['teacher_t_pid'] ?? '');
$option_ids   = $_POST['option_ids'] ?? [];
$option_other = trim($_POST['option_other'] ?? '');
$academic_year_post = (int)($_POST['academic_year'] ?? 0);

$supervision_date = date('Y-m-d H:i:s');

/* =========================
   Validation
========================= */
if (
    $p_id === '' ||
    $t_id === '' ||
    $academic_year_post === 0 ||
    (empty($option_ids) && $option_other === '')
) {
    redirect_with_flash_message(
        'กรุณาเลือกหัวข้อ Quick Win อย่างน้อย 1 ข้อ หรือระบุหัวข้ออื่น ๆ',
        'quickwin_form.php',
        'warning'
    );
}

/* =========================
   คำนวณปีการศึกษา
========================= */
// ใช้ค่าจากฟอร์มเป็นหลัก (fallback กรณีผิดปกติ)
$academic_year = ($academic_year_post > 2500)
    ? $academic_year_post
    : getAcademicYear($supervision_date);

/* =========================
   ตรวจสอบ Quick Win ซ้ำ
========================= */
$check_sql = "
    SELECT COUNT(*)
    FROM quick_win
    WHERE t_pid = :t_pid
      AND academic_year = :academic_year
";

$check_stmt = $conn->prepare($check_sql);
$check_stmt->execute([
    ':t_pid'         => $t_id,
    ':academic_year' => $academic_year
]);


if ($check_stmt->fetchColumn() > 0) {
    redirect_with_flash_message(
        "ครูท่านนี้ได้รับการนิเทศ Quick Win ปีการศึกษา {$academic_year} แล้ว",
        '../index.php',
        'warning'
    );
}

/* =========================
   เตรียมข้อมูลบันทึก
========================= */
$options_str = !empty($option_ids)
    ? implode('/', $option_ids)
    : '';

/* =========================
   บันทึกข้อมูล + รูป
========================= */
try {
    $conn->beginTransaction();

    /* ---------- 1) บันทึก quick_win ---------- */
    $sql = "
        INSERT INTO quick_win
            (p_id, t_pid, options, option_other, supervision_date, academic_year)
            VALUES
            (:pid, :tid, :opt, :other, :sdate, :ay)
                ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':pid'   => $p_id,
        ':tid'   => $t_id,
        ':opt'   => $options_str,
        ':other' => $option_other,
        ':sdate' => $supervision_date,
        ':ay'    => $academic_year
    ]);

    $quickwin_id = $conn->lastInsertId();

    /* ---------- 2) อัปโหลดรูป (ถ้ามี) ---------- */
    if (!empty($_FILES['quickwin_images']['name'][0])) {

        $allowedExt = ['jpg', 'jpeg', 'png'];

        foreach ($_FILES['quickwin_images']['name'] as $index => $name) {

            if ($_FILES['quickwin_images']['error'][$index] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = $_FILES['quickwin_images']['tmp_name'][$index];
            $size    = $_FILES['quickwin_images']['size'][$index];
            $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt) || $size > 5 * 1024 * 1024) {
                continue;
            }

            $newFileName =
                'qw_' . $quickwin_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

            if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {

                $imgSql = "
                    INSERT INTO images
                        (
                            supervisor_p_id,
                            teacher_t_pid,
                            subject_code,
                            inspection_time,
                            file_name,
                            academic_year,
                            form_type
                        )
                    VALUES
                        (
                            :pid,
                            :tid,
                            NULL,
                            NULL,
                            :fname,
                            :ay,
                            :form_type
                        )
                ";


                $imgStmt = $conn->prepare($imgSql);
                $imgStmt->execute([
                    ':pid'       => $p_id,
                    ':tid'       => $t_id,
                    ':fname'     => $newFileName,
                    ':ay'        => $academic_year,
                    ':form_type' => $formType
                ]);
            }
        }
    }

    /* ---------- 3) commit ---------- */
    $conn->commit();

    unset($_SESSION['inspection_data']);

    redirect_with_flash_message(
        "บันทึก Quick Win ปีการศึกษา {$academic_year} สำเร็จ",
        '../index.php',
        'success'
    );
} catch (PDOException $e) {

    $conn->rollBack();
    error_log('QuickWin Save Error: ' . $e->getMessage());

    redirect_with_flash_message(
        'เกิดข้อผิดพลาดในการบันทึกข้อมูล',
        'quickwin_form.php',
        'danger'
    );
}
