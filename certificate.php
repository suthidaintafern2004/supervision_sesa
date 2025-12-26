<?php
// certificate.php
session_start();
require_once 'config/db_connect.php';
require_once('vendor/tecnickcom/tcpdf/tcpdf.php');

// ==========================================
// 1. Helper Functions
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

// ==========================================
// 2. Data Processing (ทำส่วนนี้ให้เสร็จก่อนสร้าง PDF)
// ==========================================
$s_pid    = $_REQUEST['s_pid']    ?? null;
$t_pid    = $_REQUEST['t_pid']    ?? null;
$sub_code = $_REQUEST['sub_code'] ?? null;
$time     = $_REQUEST['time']     ?? null;

if (empty($s_pid) || empty($t_pid) || empty($sub_code) || empty($time)) {
    die("ข้อมูลไม่ครบถ้วน (Missing parameters)");
}

try {
    // 2.1 Log
    $sql_log = "INSERT IGNORE INTO certificate_log (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, generated_at) VALUES (:sid, :tid, :scode, :time, NOW())";
    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->execute([':sid' => $s_pid, ':tid' => $t_pid, ':scode' => $sub_code, ':time' => $time]);

    // 2.2 Rank (Running Number)
    $sql_rank = "SELECT COUNT(*) as cert_no FROM certificate_log WHERE generated_at <= (SELECT generated_at FROM certificate_log WHERE supervisor_p_id = :sid AND teacher_t_pid = :tid AND subject_code = :scode AND inspection_time = :time)";
    $stmt_rank = $conn->prepare($sql_rank);
    $stmt_rank->execute([':sid' => $s_pid, ':tid' => $t_pid, ':scode' => $sub_code, ':time' => $time]);
    $certificate_running_no = $stmt_rank->fetch(PDO::FETCH_ASSOC)['cert_no'] ?? 1;

    // 2.3 Query Details
    $sql = "SELECT s.*, CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_full_name, sc.school_name AS SchoolName FROM supervision_sessions s LEFT JOIN teacher t ON s.teacher_t_pid = t.t_pid LEFT JOIN prefix p ON t.prefix_id = p.prefix_id LEFT JOIN school sc ON t.school_id = sc.school_id WHERE s.supervisor_p_id = :sid AND s.teacher_t_pid = :tid AND s.subject_code = :scode AND s.inspection_time = :time";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':sid' => $s_pid, ':tid' => $t_pid, ':scode' => $sub_code, ':time' => $time]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) die("ไม่พบข้อมูลการนิเทศ");
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// ==========================================
// 3. Prepare Variables (เตรียมตัวแปรให้ครบตรงนี้)
// ==========================================
$teacher_name = $session['teacher_full_name'];
$school_name  = $session['SchoolName'];
$issue_date_parts = toThaiDate($session['satisfaction_date'] ?? date('Y-m-d'));

// สร้าง $reference_number (เลขที่อ้างอิง)
$ref_prefix = 'ศน.';
$ref_running_no = toThaiNumber(str_pad($certificate_running_no, 4, '0', STR_PAD_LEFT));
$ref_year = toThaiNumber((int)date('Y') + 543);
$reference_number = "{$ref_prefix}{$ref_running_no}/{$ref_year}";

// สร้าง $date_text (ข้อความวันที่ด้านล่าง)
$date_text = "ให้ไว้ ณ วันที่   " . $issue_date_parts['day'] . "   เดือน   " . $issue_date_parts['month'] . "   พ.ศ.   " . $issue_date_parts['year'];


// ==========================================
// 4. PDF Generation (เริ่มสร้าง PDF)
// ==========================================
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

// Font Setup
$fontName = 'helvetica';
$fontPath = __DIR__ . '/fonts/';
$fontRegular = $fontPath . 'Sarabun-Medium.ttf';
$fontBold    = $fontPath . 'Sarabun-Bold.ttf';

if (file_exists($fontRegular)) {
    try {
        $fontName = TCPDF_FONTS::addTTFfont($fontRegular, 'TrueTypeUnicode', '', 96);
    } catch (Exception $e) {
    }
}
if (file_exists($fontBold)) {
    try {
        $fontNameBold = TCPDF_FONTS::addTTFfont($fontBold, 'TrueTypeUnicode', '', 96);
    } catch (Exception $e) {
    }
}

$pdf->SetTextColor(8, 13, 86);
$currentFont = isset($fontNameBold) ? $fontNameBold : $fontName;

// --- Print Content ---

// 1. เลขที่อ้างอิง
$pdf->SetFont($currentFont, '', 16);
$pdf->SetXY(235, 15);
$pdf->Cell(0, 0, $reference_number, 0, 1, 'L');

// 2. ชื่อครู
$pdf->SetFont($currentFont, '', 27);
$pdf->SetY(70);
$pdf->Cell(0, 0, $teacher_name, 0, 1, 'C', 0, '', 0);

// 3. โรงเรียน
$pdf->SetFont($currentFont, '', 27);
$pdf->SetY(85);
$pdf->Cell(0, 0, "ครู โรงเรียน {$school_name}", 0, 1, 'C', 0, '', 0);

// 4. วันที่
$pdf->SetFont($currentFont, '', 20);
$pdf->SetXY(76, 155);
$pdf->Cell(0, 0, $date_text, 0, 1, 'L');

$pdf->Output('certificate.pdf', 'I');
