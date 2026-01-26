<?php
session_start();
require_once 'config/db_connect.php';
require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';

/* ===============================
   Helper Functions
================================ */
function toThaiNumber($number)
{
    return str_replace(
        ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'],
        (string)$number
    );
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

    $date = new DateTime($dateStr);
    return [
        'day'   => toThaiNumber($date->format('j')),
        'month' => $thai_months[$date->format('F')],
        'year'  => toThaiNumber($date->format('Y') + 543)
    ];
}

function getAcademicYear($date)
{
    $y = (int)date('Y', strtotime($date));
    $m = (int)date('n', strtotime($date));
    return ($m >= 5) ? $y + 543 : $y + 542;
}

/* ===============================
   รับค่า
================================ */
$p_id = $_REQUEST['p_id'] ?? null;
$t_id = $_REQUEST['t_id'] ?? null;
$date = $_REQUEST['date'] ?? null;

if (!$p_id || !$t_id || !$date) {
    die('ข้อมูลไม่ครบถ้วน (Quick Win)');
}

/* ===============================
   1. ดึงข้อมูล Quick Win
================================ */
$stmt = $conn->prepare("
    SELECT 
        CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_name,
        sc.school_name,
        qw.supervision_date
    FROM quick_win qw
    LEFT JOIN teacher t ON qw.t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    LEFT JOIN school sc ON t.school_id = sc.school_id
    WHERE qw.p_id = ?
      AND qw.t_pid = ?
      AND qw.supervision_date = ?
    LIMIT 1
");
$stmt->execute([$p_id, $t_id, $date]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die('ไม่พบข้อมูล Quick Win');
}

$academic_year = getAcademicYear($session['supervision_date']);

/* ===============================
   2. เช็ก certificate_log ก่อน
================================ */
$stmt = $conn->prepare("
    SELECT id
    FROM certificate_log
    WHERE supervisor_p_id = ?
      AND teacher_t_pid   = ?
      AND inspection_time = ?
      AND form_type       = 'quickwin'
    LIMIT 1
");
$stmt->execute([$p_id, $t_id, $date]);
$certificate_id = $stmt->fetchColumn();

/* ===============================
   3. ถ้าไม่มี → INSERT (AUTO_INCREMENT)
================================ */
if (!$certificate_id) {
    $stmt = $conn->prepare("
        INSERT INTO certificate_log
        (supervisor_p_id, teacher_t_pid, inspection_time,
         academic_year, form_type, generated_at)
        VALUES (?, ?, ?, ?, 'quickwin', NOW())
    ");
    $stmt->execute([
        $p_id,
        $t_id,
        $date,
        $academic_year
    ]);

    $certificate_id = $conn->lastInsertId();
}

/* ===============================
   เตรียมข้อมูลแสดงผล
================================ */
$teacher_name = $session['teacher_name'];
$school_name  = $session['school_name'];
$date_parts   = toThaiDate($session['supervision_date']);

$reference_number =
    'ศน.' .
    toThaiNumber(str_pad($certificate_id, 5, '0', STR_PAD_LEFT)) .
    '/' .
    toThaiNumber($academic_year);

$date_text =
    "ให้ไว้ ณ วันที่ {$date_parts['day']} เดือน {$date_parts['month']} พ.ศ. {$date_parts['year']}";

/* ===============================
   สร้าง PDF
================================ */
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

/* Background */
$bg = 'images/qw_cer.png';
if (file_exists($bg)) {
    $pdf->Image($bg, 0, 0, 297, 210);
}

/* Fonts */
$fontPath = __DIR__ . '/fonts/';
$fontBold = TCPDF_FONTS::addTTFfont($fontPath . 'eak_chodok.ttf', 'TrueTypeUnicode', '', 96);

$pdf->SetTextColor(8, 13, 86);

/* Reference */
$pdf->SetFont($fontBold, '', 20);
$pdf->SetXY(243, 10);
$pdf->Cell(0, 0, $reference_number);

/* Teacher */
$pdf->SetFont($fontBold, '', 35);
$pdf->SetY(68);
$pdf->Cell(0, 0, $teacher_name, 0, 1, 'C');

/* School */
$pdf->SetY(83);
$pdf->Cell(0, 0, "โรงเรียน {$school_name}", 0, 1, 'C');

/* Date */
$pdf->SetFont($fontBold, '', 23);
$pdf->SetXY(90, 145);
$pdf->Cell(0, 0, $date_text);

$pdf->Output('certificate_quickwin.pdf', 'I');
