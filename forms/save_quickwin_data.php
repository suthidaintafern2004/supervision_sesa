<?php
// forms/save_quickwin_data.php

/* =========================
   1. ตรวจสอบ Session
   ========================= */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   2. เชื่อมต่อฐานข้อมูล
   ========================= */
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

/* =========================
   3. ฟังก์ชัน redirect + flash message
   ========================= */
function redirect_with_flash_message($message, $location = '../index.php', $type = 'danger')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type; // success | warning | danger | info
    header("Location: $location");
    exit();
}

/* =========================
   4. ฟังก์ชันคำนวณปีการศึกษา (คิดจากเดือน)
   =========================
   - ม.ค.–เม.ย.  => ปีการศึกษาก่อนหน้า
   - พ.ค.–ธ.ค.  => ปีการศึกษาปัจจุบัน
*/
function getAcademicYear($date)
{
    $year  = (int)date('Y', strtotime($date));
    $month = (int)date('m', strtotime($date));

    return ($month < 5) ? $year - 1 : $year;
}

/* =========================
   5. ตรวจสอบ Method
   ========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

if (!isset($_SESSION['inspection_data'])) {
    redirect_with_flash_message(
        "Session หมดอายุ กรุณาทำรายการใหม่",
        "../index.php",
        "warning"
    );
}

/* =========================
   6. รับค่าจากฟอร์ม
   ========================= */
$p_id         = trim($_POST['supervisor_p_id'] ?? '');
$t_id         = trim($_POST['teacher_t_pid'] ?? '');
$option_ids   = $_POST['option_ids'] ?? [];
$option_other = trim($_POST['option_other'] ?? '');

$supervision_date =
    ($_POST['supervision_date'] ?? date('Y-m-d')) . ' ' . date('H:i:s');

/* =========================
   7. Validation
   ========================= */
if (
    $p_id === '' ||
    $t_id === '' ||
    (empty($option_ids) && $option_other === '')
) {
    redirect_with_flash_message(
        "กรุณาเลือกหัวข้อ Quick Win อย่างน้อย 1 ข้อ หรือระบุหัวข้ออื่นๆ",
        "../index.php",
        "warning"
    );
}

/* =========================
   8. คำนวณปีการศึกษา
   ========================= */
$academic_year = getAcademicYear($supervision_date);

/* =========================
   9. ตรวจสอบ Quick Win ซ้ำในปีการศึกษาเดียวกัน
   ========================= */
$check_sql = "
    SELECT COUNT(*)
    FROM quick_win
    WHERE t_pid = :t_pid
      AND (
            CASE
                WHEN MONTH(supervision_date) < 5
                THEN YEAR(supervision_date) - 1
                ELSE YEAR(supervision_date)
            END
          ) = :academic_year
";

$check_stmt = $conn->prepare($check_sql);
$check_stmt->execute([
    ':t_pid'         => $t_id,
    ':academic_year' => $academic_year
]);

if ($check_stmt->fetchColumn() > 0) {
    redirect_with_flash_message(
        "ครูท่านนี้ได้รับการนิเทศ Quick Win ปีการศึกษา {$academic_year} แล้ว",
        "../index.php",
        "warning"
    );
}

/* =========================
   10. เตรียมข้อมูลบันทึก
   ========================= */
$options_str = !empty($option_ids)
    ? implode('/', $option_ids)
    : '';

/* =========================
   11. บันทึกข้อมูล
   ========================= */
try {
    $conn->beginTransaction();

    $sql = "
        INSERT INTO quick_win
            (p_id, t_pid, options, option_other, supervision_date)
        VALUES
            (:pid, :tid, :opt, :other, :sdate)
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':pid'   => $p_id,
        ':tid'   => $t_id,
        ':opt'   => $options_str,
        ':other' => $option_other,
        ':sdate' => $supervision_date
    ]);

    $conn->commit();

    unset($_SESSION['inspection_data']);

    redirect_with_flash_message(
        "บันทึกข้อมูล Quick Win ปีการศึกษา {$academic_year} เรียบร้อยแล้ว",
        "../index.php",
        "success"
    );

} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Save QuickWin Error: " . $e->getMessage());

    redirect_with_flash_message(
        "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาติดต่อผู้ดูแลระบบ",
        "../index.php",
        "danger"
    );
}
