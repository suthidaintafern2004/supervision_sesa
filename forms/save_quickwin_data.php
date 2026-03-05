<?php
/* ==================================================
   save_quickwin_data.php (ฉบับแก้ไขชื่อฟิลด์ตามฐานข้อมูลใหม่)
   ================================================== */

require_once __DIR__ . '/../config/session_config.php';

if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

$formType = 'qw';
$uploadDir = __DIR__ . '/../uploads/quickwin/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

function redirect_with_flash_message($message, $location, $type = 'warning')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type;
    header("Location: {$location}");
    exit();
}

function getAcademicYear($date)
{
    $year  = (int)date('Y', strtotime($date));
    $month = (int)date('n', strtotime($date));
    return ($month >= 5) ? $year + 543 : $year + 542;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

if (!isset($_SESSION['inspection_data'])) {
    redirect_with_flash_message('Session หมดอายุ กรุณาทำรายการใหม่', '../index.php', 'warning');
}

/* =========================
   รับค่าจากฟอร์ม
========================= */
$p_id = trim($_POST['supervisor_p_id'] ?? ''); // ผู้นิเทศ
$t_pid = trim($_POST['teacher_t_pid'] ?? ''); // ครู
$option_ids = $_POST['option_ids'] ?? [];
$option_other = trim($_POST['option_other'] ?? '');
$semester = (int)($_POST['semester'] ?? 0);
$supervision_date = date('Y-m-d H:i:s');
$academic_year = (int)($_POST['academic_year'] ?? 0);

if ($academic_year < 2500) {
    $academic_year = getAcademicYear($supervision_date);
}

if ($p_id === '' || $t_pid === '' || $semester === 0 || (empty($option_ids) && $option_other === '')) {
    redirect_with_flash_message('กรุณาเลือกหัวข้อ Quick Win หรือระบุหัวข้ออื่น ๆ', 'quickwin_form.php', 'warning');
}

/* =========================
   ตรวจสอบ Quick Win ซ้ำ
========================= */
$check_stmt = $conn->prepare("SELECT COUNT(*) FROM quick_win WHERE t_pid = :t_pid AND academic_year = :academic_year");
$check_stmt->execute([':t_pid' => $t_pid, ':academic_year' => $academic_year]);

if ($check_stmt->fetchColumn() > 0) {
    redirect_with_flash_message("ครูท่านนี้ได้รับการนิเทศ Quick Win ปีการศึกษา {$academic_year} แล้ว", '../index.php', 'warning');
}

$options_str = !empty($option_ids) ? implode('/', $option_ids) : '';

try {
    $conn->beginTransaction();

    /* ---------- 1) บันทึก quick_win (ใช้ p_id, t_pid) ---------- */
    $sql = "INSERT INTO quick_win (p_id, t_pid, options, option_other, supervision_date, academic_year, semester) 
            VALUES (:pid, :tid, :opt, :other, :sdate, :ay, :semester)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':pid'      => $p_id,
        ':tid'      => $t_pid,
        ':opt'      => $options_str,
        ':other'    => $option_other,
        ':sdate'    => $supervision_date,
        ':ay'       => $academic_year,
        ':semester' => $semester
    ]);

    /* ---------- 2) อัปโหลดรูปภาพ ---------- */
    if (!empty($_FILES['quickwin_images']['name'][0])) {
        $allowedExt = ['jpg', 'jpeg', 'png'];

        foreach ($_FILES['quickwin_images']['name'] as $index => $name) {
            if ($_FILES['quickwin_images']['error'][$index] !== UPLOAD_ERR_OK) continue;

            $tmpName = $_FILES['quickwin_images']['tmp_name'][$index];
            $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt)) continue;

            $newFileName = 'qw_' . $t_pid . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;

            if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                /* แก้ไขชื่อฟิลด์จาก supervisor_p_id -> p_id และ teacher_t_pid -> t_pid */
                $imgSql = "INSERT INTO images (p_id, t_pid, subject_code, inspection_time, file_name, academic_year, form_type)
                           VALUES (:pid, :tid, '', 0, :fname, :ay, :form_type)";

                $imgStmt = $conn->prepare($imgSql);
                $imgStmt->execute([
                    ':pid'       => $p_id,
                    ':tid'       => $t_pid,
                    ':fname'     => $newFileName,
                    ':ay'        => $academic_year,
                    ':form_type' => $formType
                ]);
            }
        }
    }

    $conn->commit();
    unset($_SESSION['inspection_data']);
    $_SESSION['flash_from'] = 'quickwin_save';

    redirect_with_flash_message("บันทึก Quick Win สำเร็จ", '../index.php', 'success');
} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo "พบปัญหา SQL: " . $e->getMessage();
    exit;
}
