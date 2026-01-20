<?php
// certificate.php
session_start();

// 1. เพิ่มบรรทัดนี้เพื่อปิดการแสดง Warning ไม่ให้รบกวนการสร้าง PDF
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config/db_connect.php';
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

// ==========================================
// Helper Functions
// ==========================================
function toThaiNumber($number)
{
    $arabic_numerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $thai_numerals = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    return str_replace($arabic_numerals, $thai_numerals, (string)$number);
}

function toThaiDate($dateStr)
{
    if (empty($dateStr)) $dateStr = date('Y-m-d');
    $thai_months = [
        'January' => 'มกราคม',
        'February' => 'กุมภาพันธ์',
        'March' => 'มีนาคม',
        'April' => 'เมษายน',
        'May' => 'พฤษภาคม',
        'June' => 'มิถุนายน',
        'July' => 'กรกฎาคม',
        'August' => 'สิงหาคม',
        'September' => 'กันยายน',
        'October' => 'ตุลาคม',
        'November' => 'พฤศจิกายน',
        'December' => 'ธันวาคม'
    ];
    try {
        $date = new DateTime($dateStr);
    } catch (Exception $e) {
        $date = new DateTime();
    }
    $day = toThaiNumber($date->format('j'));
    $month = $thai_months[$date->format('F')];
    $year = toThaiNumber((int)$date->format('Y') + 543);
    return ['day' => $day, 'month' => $month, 'year' => $year];
}

function getAcademicYear($date)
{
    $year  = (int)date('Y', strtotime($date));
    $month = (int)date('n', strtotime($date));

    return ($month >= 5)
        ? $year + 543
        : $year + 542;
}


// ==========================================
// Data Processing
// ==========================================
$s_pid    = $_REQUEST['s_pid']    ?? null;
$t_pid    = $_REQUEST['t_pid']    ?? null;
$sub_code = $_REQUEST['sub_code'] ?? null;
$time     = $_REQUEST['time']     ?? null;

if (empty($s_pid) || empty($t_pid) || empty($sub_code) || empty($time)) {
    die("ข้อมูลไม่ครบถ้วน");
}

try {
    // บันทึก Log
    $sql_log = "INSERT IGNORE INTO certificate_log
                (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, generated_at, academic_year)
                VALUES (:sid, :tid, :scode, :time, NOW(), :ay)";
    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->execute([':sid' => $s_pid, ':tid' => $t_pid, ':scode' => $sub_code, ':time' => $time, ':ay' => $academic_year]);

    // ลำดับที่
    $sql_rank = "SELECT COUNT(*) as cert_no
                    FROM certificate_log
                    WHERE academic_year = :ay
                    AND generated_at <= (
                        SELECT generated_at
                        FROM certificate_log
                        WHERE supervisor_p_id = :sid
                            AND teacher_t_pid = :tid
                            AND subject_code = :scode
                            AND inspection_time = :time
                            AND academic_year = :ay
                    )";
    $stmt_rank = $conn->prepare($sql_rank);
    $stmt_rank->execute([':sid' => $s_pid, ':tid' => $t_pid, ':scode' => $sub_code, ':time' => $time, ':ay' => $academic_year]);
    $certificate_running_no = $stmt_rank->fetch(PDO::FETCH_ASSOC)['cert_no'] ?? 1;

    // ข้อมูลการนิเทศ
    $sql = "SELECT s.*, CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_full_name, sc.school_name AS SchoolName FROM supervision_sessions s LEFT JOIN teacher t ON s.teacher_t_pid = t.t_pid LEFT JOIN prefix p ON t.prefix_id = p.prefix_id LEFT JOIN school sc ON t.school_id = sc.school_id WHERE s.supervisor_p_id = :sid AND s.teacher_t_pid = :tid AND s.subject_code = :scode AND s.inspection_time = :time AND s.academic_year = :ay";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':sid' => $s_pid, ':tid' => $t_pid, ':scode' => $sub_code, ':time' => $time, ':ay' => $academic_year]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) die("ไม่พบข้อมูลการนิเทศ");
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// เตรียมตัวแปร
$teacher_name = $session['teacher_full_name'];
$school_name  = $session['SchoolName'];
$issue_date_parts = toThaiDate($session['satisfaction_date'] ?? date('Y-m-d'));
$academic_year = getAcademicYear($session['inspection_date'] ?? date('Y-m-d'));

$ref_prefix = 'ศน.';
$ref_running_no = toThaiNumber(str_pad($certificate_running_no, 4, '0', STR_PAD_LEFT));
$ref_year = toThaiNumber($academic_year);
$reference_number = "{$ref_prefix}{$ref_running_no}/{$ref_year}";

$date_text = "ให้ไว้ ณ วันที่   " . $issue_date_parts['day'] . "   เดือน   " . $issue_date_parts['month'] . "   พ.ศ.   " . $issue_date_parts['year'];

// ==========================================
// PDF Generation
// ==========================================
// ล้าง Output Buffer เพื่อป้องกัน Error: Some data has already been output
if (ob_get_contents()) ob_end_clean();

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('เกียรติบัตรการนิเทศ');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

// Background
$img_file = 'images/ctest.png';
if (file_exists($img_file)) {
    $pdf->Image($img_file, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
}

// การตั้งค่า Font
$fontPath = __DIR__ . '/fonts/';
$fontRegular = $fontPath . 'eak_jindara.ttf';
$fontBold    = $fontPath . 'eak_chodok.ttf';

$currentFont = 'helvetica';
if (file_exists($fontRegular)) {
    // เพิ่มฟอนต์และใช้การจัดการรหัสอักขระไทยภายในตัว
    $currentFont = TCPDF_FONTS::addTTFfont($fontRegular, 'TrueTypeUnicode', '', 96);
}
if (file_exists($fontBold)) {
    $currentFontBold = TCPDF_FONTS::addTTFfont($fontBold, 'TrueTypeUnicode', '', 96);
} else {
    $currentFontBold = $currentFont;
}

$pdf->SetTextColor(8, 13, 86);

// --- แสดงผลเนื้อหา ---

// 1. เลขที่อ้างอิง
$pdf->SetFont($currentFontBold, '', 20);
$pdf->SetXY(243, 15);
$pdf->Cell(0, 0, $reference_number, 0, 1, 'L');

// 2. ชื่อครู
$pdf->SetFont($currentFontBold, '', 33);
$pdf->writeHTMLCell(0, 0, 0, 68, $teacher_name, 0, 1, 0, true, 'C', true);

// 3. โรงเรียน
$pdf->SetFont($currentFontBold, '', 33);
$html_school = "โรงเรียน " . $school_name;
$pdf->writeHTMLCell(0, 0, 0, 83, $html_school, 0, 1, 0, true, 'C', true);

// 4. วันที่
$pdf->SetFont($currentFontBold, '', 23);
$pdf->SetXY(95, 150);
$pdf->Cell(0, 0, $date_text, 0, 1, 'L');

// เคลียร์ Buffer อีกครั้งก่อน Output
if (ob_get_contents()) ob_end_clean();
$pdf->Output('certificate.pdf', 'I');
