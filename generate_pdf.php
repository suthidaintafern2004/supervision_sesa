<?php
$params = http_build_query($_GET);

$url = "http://localhost/sesalpglpn/certificate.php?$params";
$output = __DIR__ . "/certificate.pdf";

$wkhtml = '"C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe"';

$cmd = "$wkhtml --encoding UTF-8 \"$url\" \"$output\"";
exec($cmd, $out, $ret);

if ($ret !== 0 || !file_exists($output)) {
    echo "<h3>สร้าง PDF ไม่สำเร็จ</h3>";
    echo "<pre>";
    echo $cmd . "\n";
    print_r($out);
    exit;
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="certificate.pdf"');
readfile($output);
