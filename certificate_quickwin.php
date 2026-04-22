<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
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
$academic_year_req = $_REQUEST['academic_year'] ?? null;

if (!$p_id || !$t_id || !$date || !$academic_year_req) {
    die('ข้อมูลไม่ครบถ้วน (Quick Win)');
}

/* ===============================
   1. ดึงข้อมูล Quick Win
================================ */
$stmt = $conn->prepare("
    SELECT 
        CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_name,
        sc.school_name,
        qw.supervision_date,
        qw.academic_year,
        qw.semester
    FROM quick_win qw
    LEFT JOIN teacher t ON qw.t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    LEFT JOIN school sc ON t.school_id = sc.school_id
    WHERE qw.p_id = ?
      AND qw.t_pid = ?
      AND qw.supervision_date = ?
      AND qw.academic_year = ?
    LIMIT 1
");
$stmt->execute([$p_id, $t_id, $date, $academic_year_req]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    die('ไม่พบข้อมูล Quick Win');
}

$academic_year = $session['academic_year'] ?? getAcademicYear($session['supervision_date']);

/* ===============================
   2. เช็ก certificate_log ก่อน
================================ */
$stmt = $conn->prepare("
    SELECT id
    FROM certificate_log
    WHERE p_id = ?
      AND t_pid   = ?
      AND inspection_time = ?
      AND form_type       = 'quickwin'
      AND academic_year   = ?
    LIMIT 1
");
$stmt->execute([$p_id, $t_id, $date, $academic_year_req]);
$certificate_id = $stmt->fetchColumn();

/* ===============================
   3. ถ้าไม่มี → INSERT (AUTO_INCREMENT)
================================ */
if (!$certificate_id) {
    $stmt = $conn->prepare("
        INSERT INTO certificate_log
        (p_id, t_pid, inspection_time,
         academic_year, form_type, generated_at)
        VALUES (?, ?, ?, ?, 'quickwin', ?)
    ");
    $stmt->execute([
        $p_id,
        $t_id,
        $date,
        $academic_year,
        date('Y-m-d H:i:s')
    ]);

    $certificate_id = $conn->lastInsertId();
}

/* ===============================
   4. คำนวณเลขอ้างอิง (เริ่ม 1 ใหม่ทุกปีการศึกษา)
================================ */
$stmtRank = $conn->prepare("
    SELECT COUNT(*)
    FROM certificate_log
    WHERE academic_year = ? AND id <= ?
");
$stmtRank->execute([$academic_year, $certificate_id]);
$cert_no = $stmtRank->fetchColumn();

/* ===============================
   เตรียมข้อมูลแสดงผล
================================ */
$teacher_name = $session['teacher_name'];
$school_name  = $session['school_name'];
$date_parts   = toThaiDate($session['supervision_date']);

$reference_number =
    'ศน.' .
    toThaiNumber(str_pad($cert_no, 4, '0', STR_PAD_LEFT)) .
    '/' .
    toThaiNumber($academic_year);

$date_text =
    "ให้ไว้ ณ วันที่ {$date_parts['day']} เดือน {$date_parts['month']} พ.ศ. {$date_parts['year']}";

$semester_thai = toThaiNumber($session['semester'] ?? '-');
$academic_year_thai = toThaiNumber($academic_year);
$semester_year_text = "ภาคเรียนที่ {$semester_thai} ปีการศึกษา {$academic_year_thai} ประจำปีงบประมาณ {$academic_year_thai}";

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
$bg = __DIR__ . '/images/qw_cer.png';
try {
        $stmt_bg = $conn->prepare("SELECT bg_image FROM cert_settings WHERE form_type = 'quickwin' AND academic_year = ?");
    $stmt_bg->execute([$academic_year]);
    $bg_file = $stmt_bg->fetchColumn();
    if ($bg_file && file_exists(__DIR__ . '/uploads/certificates/' . $bg_file)) {
        $bg = __DIR__ . '/uploads/certificates/' . $bg_file;
    }
} catch (PDOException $e) {}

if (file_exists($bg)) {
    $pdf->Image($bg, 0, 0, 297, 210);
}

/* Fonts */
$fontPath = __DIR__ . '/fonts/';
$fontBold = TCPDF_FONTS::addTTFfont($fontPath . 'eak_chodok.ttf', 'TrueTypeUnicode', '', 96);

$pdf->SetTextColor(8, 13, 86);

/* Elements */
$elements = [];
try {
    $stmt_el = $conn->prepare("SELECT * FROM cert_elements WHERE form_type = 'quickwin' AND academic_year = ?");
    $stmt_el->execute([$academic_year]);
    $elements = $stmt_el->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (empty($elements)) {
    // Fallback หากระบบยังไม่ถูกตั้งค่าครั้งแรก
    $pdf->SetFont($fontBold, '', 20); $pdf->SetXY(243, 10); $pdf->Cell(0, 0, $reference_number);
    $pdf->SetFont($fontBold, '', 35); $pdf->SetY(68); $pdf->Cell(0, 0, $teacher_name, 0, 1, 'C');
    $pdf->SetY(83); $pdf->Cell(0, 0, "โรงเรียน {$school_name}", 0, 1, 'C');
    $pdf->SetFont($fontBold, '', 23); $pdf->SetXY(90, 145); $pdf->Cell(0, 0, $date_text);
} else {
    // วาดตามฐานข้อมูล
    foreach ($elements as $el) {
        $pdf->SetFont($fontBold, '', $el['font_size']);
        
        // ดึงสีและแปลง HEX เป็น RGB
        $color = $el['color'] ?? '#080d56';
        if (preg_match('/^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $color, $matches)) {
            $pdf->SetTextColor(hexdec($matches[1]), hexdec($matches[2]), hexdec($matches[3]));
        } else {
            $pdf->SetTextColor(8, 13, 86);
        }
        
        $text = '';
        if ($el['element_type'] == 'dynamic') {
            if ($el['element_key'] == 'ref') $text = $reference_number;
            elseif ($el['element_key'] == 'teacher') $text = $teacher_name;
            elseif ($el['element_key'] == 'school') $text = "โรงเรียน {$school_name}";
            elseif ($el['element_key'] == 'date') $text = $date_text;
            elseif ($el['element_key'] == 'semester_year') $text = $semester_year_text;
        } else {
            $text = $el['text_value'];
        }
        $pdf->MultiCell(0, 0, $text, 0, $el['font_align'], false, 1, $el['pos_x'], $el['pos_y']);
    }
}

$pdf->Output('certificate_quickwin.pdf', 'I');
