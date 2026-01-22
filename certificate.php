<?php
session_start();

/* ===============================
   ปิด warning / notice (กัน PDF พัง)
================================ */
error_reporting(0);
ini_set('display_errors', 0);

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
$s_pid    = $_REQUEST['s_pid']    ?? null;
$t_pid    = $_REQUEST['t_pid']    ?? null;
$sub_code = $_REQUEST['sub_code'] ?? null;
$time     = $_REQUEST['time']     ?? null;
$form_type = $_REQUEST['form_type'] ?? 'classroom';

if (!$s_pid || !$t_pid || !$sub_code || !$time) {
    die('ข้อมูลไม่ครบถ้วน');
}

try {

    /* ===============================
       1. ดึงข้อมูลการนิเทศ (ตัวจริง)
       ❗ ไม่กรอง academic_year ก่อน
    ================================ */
    $sql = "
    SELECT s.*,
           CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS teacher_full_name,
           sc.school_name AS school_name
    FROM supervision_sessions s
    LEFT JOIN teacher t ON s.teacher_t_pid = t.t_pid
    LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
    LEFT JOIN school sc ON t.school_id = sc.school_id
    WHERE s.supervisor_p_id = :sid
      AND s.teacher_t_pid   = :tid
      AND s.subject_code    = :scode
      AND s.inspection_time = :time
    LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':sid'   => $s_pid,
        ':tid'   => $t_pid,
        ':scode' => $sub_code,
        ':time'  => $time
    ]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        die('ไม่พบข้อมูลการนิเทศ');
    }

    /* ===============================
       2. ใช้ปีการศึกษาจากข้อมูลจริง
    ================================ */
    $academic_year = $session['academic_year'];

    /* ===============================
       3. บันทึก log ใบเกียรติบัตร
    ================================ */
    $sql_log = "
    INSERT IGNORE INTO certificate_log
    (
        supervisor_p_id,
        teacher_t_pid,
        subject_code,
        inspection_time,
        academic_year,
        form_type,
        generated_at
    )
    VALUES
    (
        :sid,
        :tid,
        :scode,
        :time,
        :ay,
        :form_type,
        NOW()
    )
    ";

    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->execute([
        ':sid'       => $s_pid,
        ':tid'       => $t_pid,
        ':scode'     => $sub_code,
        ':time'      => $time,
        ':ay'        => $academic_year,
        ':form_type' => $form_type
    ]);

    /* ===============================
       4. คำนวณ Running No (ต่อปี + ประเภท)
    ================================ */
    $sql_rank = "
    SELECT COUNT(*) AS cert_no
    FROM certificate_log
    WHERE academic_year = :ay
      AND form_type = :form_type
      AND generated_at <= (
          SELECT generated_at
          FROM certificate_log
          WHERE supervisor_p_id = :sid
            AND teacher_t_pid = :tid
            AND subject_code = :scode
            AND inspection_time = :time
            AND academic_year = :ay
            AND form_type = :form_type
          ORDER BY generated_at DESC
          LIMIT 1
      )
    ";

    $stmt_rank = $conn->prepare($sql_rank);
    $stmt_rank->execute([
        ':sid'       => $s_pid,
        ':tid'       => $t_pid,
        ':scode'     => $sub_code,
        ':time'      => $time,
        ':ay'        => $academic_year,
        ':form_type' => $form_type
    ]);

    $certificate_running_no = $stmt_rank->fetchColumn() ?: 1;
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
    toThaiNumber(str_pad($certificate_running_no, 4, '0', STR_PAD_LEFT)) .
    '/' .
    toThaiNumber($academic_year);

$date_text =
    "ให้ไว้ ณ วันที่ {$issue_date_parts['day']} เดือน {$issue_date_parts['month']} พ.ศ. {$issue_date_parts['year']}";

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
$bg = 'images/ctest.png';
if (file_exists($bg)) {
    $pdf->Image($bg, 0, 0, 297, 210);
}

/* Fonts */
$fontPath = __DIR__ . '/fonts/';
$fontRegular = TCPDF_FONTS::addTTFfont($fontPath . 'eak_jindara.ttf', 'TrueTypeUnicode', '', 96);
$fontBold    = TCPDF_FONTS::addTTFfont($fontPath . 'eak_chodok.ttf', 'TrueTypeUnicode', '', 96);

$pdf->SetTextColor(8, 13, 86);

/* Reference */
$pdf->SetFont($fontBold, '', 20);
$pdf->SetXY(243, 15);
$pdf->Cell(0, 0, $reference_number);

/* Teacher */
$pdf->SetFont($fontBold, '', 33);
$pdf->writeHTMLCell(0, 0, 0, 68, $teacher_name, 0, 1, false, true, 'C');

/* School */
$pdf->writeHTMLCell(0, 0, 0, 83, "โรงเรียน {$school_name}", 0, 1, false, true, 'C');

/* Date */
$pdf->SetFont($fontBold, '', 23);
$pdf->SetXY(95, 150);
$pdf->Cell(0, 0, $date_text);

if (ob_get_contents()) ob_end_clean();
$pdf->Output('certificate.pdf', 'I');
