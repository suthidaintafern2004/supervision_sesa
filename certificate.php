<?php
session_start();

/* ===============================
   ปิด warning / notice (กัน PDF พัง)
================================ */
error_reporting(0);
ini_set('display_errors', 0);

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

    try {
        $date = new DateTime($dateStr);
    } catch (Exception $e) {
        $date = new DateTime();
    }

    return [
        'day'   => toThaiNumber($date->format('j')),
        'month' => $thai_months[$date->format('F')],
        'year'  => toThaiNumber((int)$date->format('Y') + 543)
    ];
}

/* ===============================
   รับค่าจาก Request
================================ */
$s_pid     = $_REQUEST['s_pid']    ?? null;
$t_pid     = $_REQUEST['t_pid']    ?? null;
$sub_code  = $_REQUEST['sub_code'] ?? null;
$time      = $_REQUEST['time']     ?? null;
$academic_year_req = $_REQUEST['academic_year'] ?? null;
$form_type = $_REQUEST['form_type'] ?? 'classroom';

if (!$s_pid || !$t_pid || !$sub_code || !$time || !$academic_year_req) {
    die('ข้อมูลไม่ครบถ้วน');
}

try {

    /* ===============================
       1. ดึงข้อมูลการนิเทศ
    ================================ */
    $stmt = $conn->prepare("
        SELECT s.*,
               CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_full_name,
               sc.school_name
        FROM supervision_sessions s
        LEFT JOIN teacher t ON s.t_pid = t.t_pid
        LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
        LEFT JOIN school sc ON t.school_id = sc.school_id
        WHERE s.p_id = ?
          AND s.t_pid   = ?
          AND s.subject_code    = ?
          AND s.inspection_time = ?
          AND s.academic_year   = ?
        LIMIT 1
    ");
    $stmt->execute([$s_pid, $t_pid, $sub_code, $time, $academic_year_req]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        die('ไม่พบข้อมูลการนิเทศ');
    }

    $academic_year = $session['academic_year'];

    /* ===============================
       2. ตรวจว่ามีใบเกียรติบัตรแล้วหรือยัง
    ================================ */
    $stmt = $conn->prepare("
        SELECT id
        FROM certificate_log
        WHERE p_id = ?
          AND t_pid   = ?
          AND subject_code    = ?
          AND inspection_time = ?
          AND academic_year   = ?
        LIMIT 1
    ");
    $stmt->execute([$s_pid, $t_pid, $sub_code, $time, $academic_year_req]);
    $certificate_id = $stmt->fetchColumn();

    /* ===============================
       3. ถ้ายังไม่มี → INSERT (AUTO_INCREMENT)
    ================================ */
    if (!$certificate_id) {
        $stmt = $conn->prepare("
            INSERT INTO certificate_log
            (p_id, t_pid, subject_code, inspection_time,
             academic_year, form_type, generated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $s_pid,
            $t_pid,
            $sub_code,
            $time,
            $academic_year,
            $form_type,
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
} catch (PDOException $e) {
    die('DB Error: ' . $e->getMessage());
}

/* ===============================
   เตรียมข้อมูลแสดงผล
================================ */
$teacher_name = $session['teacher_full_name'];
$school_name  = $session['school_name'];

$issue_date_parts = toThaiDate($session['satisfaction_date'] ?? date('Y-m-d'));

$reference_number =
    'ศน.' .
    toThaiNumber(str_pad($cert_no, 4, '0', STR_PAD_LEFT)) .
    '/' .
    toThaiNumber($academic_year);

$date_text =
    "ให้ไว้ ณ วันที่ {$issue_date_parts['day']} เดือน {$issue_date_parts['month']} พ.ศ. {$issue_date_parts['year']}";

$semester_thai = toThaiNumber($session['semester'] ?? '-');
$academic_year_thai = toThaiNumber($academic_year);
$semester_year_text = "ภาคเรียนที่ {$semester_thai} ปีการศึกษา {$academic_year_thai} ประจำปีงบประมาณ {$academic_year_thai}";
$subject_text = "รายวิชา" . ($session['subject_name'] ?? '');

/* ===============================
   สร้าง PDF
================================ */
if (ob_get_contents()) ob_end_clean();

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

/* Background */
$bg = __DIR__ . '/images/ctest.png';
try {
    $stmt_bg = $conn->prepare("SELECT bg_image FROM cert_settings WHERE form_type = 'classroom' AND academic_year = ?");
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
    $stmt_el = $conn->prepare("SELECT * FROM cert_elements WHERE form_type = 'classroom' AND academic_year = ?");
    $stmt_el->execute([$academic_year]);
    $elements = $stmt_el->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

if (empty($elements)) {
    // Fallback หากระบบยังไม่ถูกตั้งค่าครั้งแรก
    $pdf->SetFont($fontBold, '', 20); $pdf->SetXY(243, 15); $pdf->Cell(0, 0, $reference_number);
    $pdf->SetFont($fontBold, '', 33); $pdf->writeHTMLCell(0, 0, 0, 68, $teacher_name, 0, 1, false, true, 'C');
    $pdf->writeHTMLCell(0, 0, 0, 83, "โรงเรียน {$school_name}", 0, 1, false, true, 'C');
    $pdf->SetFont($fontBold, '', 23); $pdf->SetXY(95, 150); $pdf->Cell(0, 0, $date_text);
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
            elseif ($el['element_key'] == 'subject') $text = $subject_text;
        } else {
            $text = $el['text_value'];
        }
        $pdf->MultiCell(0, 0, $text, 0, $el['font_align'], false, 1, $el['pos_x'], $el['pos_y']);
    }
}

if (ob_get_contents()) ob_end_clean();
$pdf->Output('certificate.pdf', 'I');
