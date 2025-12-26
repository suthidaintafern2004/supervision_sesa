<?php
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
// 2. Data Processing (Quick Win)
// ==========================================
$p_id = $_REQUEST['p_id'] ?? null;
$t_id = $_REQUEST['t_id'] ?? null;
$date = $_REQUEST['date'] ?? null;

if (empty($p_id) || empty($t_id) || empty($date)) {
    die("ข้อมูลไม่ครบถ้วน (Missing required parameters for Quick Win)");
}

try {
    // 2.1 บันทึก Log (ใช้ PDO)
    // หมายเหตุ: ใช้ตารางเดิม แต่ช่อง subject_code จะเป็น NULL หรืออาจใส่ค่าพิเศษถ้าต้องการ
    $sql_log = "INSERT IGNORE INTO certificate_log 
                (supervisor_p_id, teacher_t_pid, inspection_time, generated_at) 
                VALUES (:sid, :tid, :date_val, NOW())";
    $stmt_log = $conn->prepare($sql_log);
    // ในที่นี้ inspection_time ถูกประยุกต์ใช้เก็บ 'วันที่' แทน string
    $stmt_log->execute([':sid' => $p_id, ':tid' => $t_id, ':date_val' => $date]);

    // 2.2 คำนวณเลขที่เกียรติบัตร (Running Number)
    $sql_rank = "SELECT COUNT(*) as cert_no 
                 FROM certificate_log 
                 WHERE generated_at <= (
                     SELECT generated_at FROM certificate_log 
                     WHERE supervisor_p_id = :sid 
                       AND teacher_t_pid = :tid 
                       AND inspection_time = :date_val
                 )";
    $stmt_rank = $conn->prepare($sql_rank);
    $stmt_rank->execute([':sid' => $p_id, ':tid' => $t_id, ':date_val' => $date]);
    $certificate_running_no = $stmt_rank->fetch(PDO::FETCH_ASSOC)['cert_no'] ?? 1;

    // 2.3 ดึงข้อมูลรายละเอียด Quick Win (แก้ไข JOIN และ Column ให้ตรง DB ใหม่)
    // t_id ใน quick_win คือ t_pid ใน teacher
    $sql = "SELECT 
                CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_full_name, 
                sc.school_name AS SchoolName,
                qw.supervision_date
            FROM quick_win qw
            LEFT JOIN teacher t ON qw.t_pid = t.t_pid -- แก้ t_id เป็น t_pid
            LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
            LEFT JOIN school sc ON t.school_id = sc.school_id
            WHERE qw.p_id = :pid 
              AND qw.t_pid = :tid -- แก้ t_id เป็น t_pid
              AND qw.supervision_date = :sdate";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':pid' => $p_id, ':tid' => $t_id, ':sdate' => $date]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        die("ไม่พบข้อมูล Quick Win");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// เตรียมตัวแปร
$teacher_name = $session['teacher_full_name'];
$school_name  = $session['SchoolName'];
$issue_date_parts = toThaiDate($session['supervision_date']);

// สร้างตัวแปรสำหรับแสดงผล (ย้ายขึ้นมาข้างบน)
$ref_prefix = 'ศน.';
$ref_running_no = toThaiNumber(str_pad($certificate_running_no, 4, '0', STR_PAD_LEFT));
$ref_year = toThaiNumber((int)date('Y') + 543);
$reference_number = "{$ref_prefix}{$ref_running_no}/{$ref_year}";

$date_text = "ให้ไว้ ณ วันที่   " . $issue_date_parts['day'] . "   เดือน   " . $issue_date_parts['month'] . "   พ.ศ.   " . $issue_date_parts['year'];


// ==========================================
// 3. PDF Generation
// ==========================================
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('เกียรติบัตรการนิเทศ (จุดเน้น)');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);
$pdf->AddPage();

// Background
$img_file = 'images/qw_cer.png'; // รูปพื้นหลังสำหรับ Quick Win
if (file_exists($img_file)) {
    $pdf->Image($img_file, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
}

// --- Font Setup (ใช้ Logic เดียวกับ certificate.php) ---
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
$pdf->SetXY(243, 15);
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
$pdf->SetXY(78, 151);
$pdf->Cell(0, 0, $date_text, 0, 1, 'L');

$pdf->Output('certificate_quickwin.pdf', 'I');
