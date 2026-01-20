<?php
/**
 * app.php
 * กำหนด BASE_URL แบบถูกต้อง (รวม domain)
 * ใช้ได้กับ PHP 7.x / 8.x
 */

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';

$host = $_SERVER['HTTP_HOST'];

$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath   = dirname($scriptName);

// โฟลเดอร์ที่ไม่ใช่ root ของโปรเจกต์
$removeFolders = ['admin', 'classroom', 'quickwin', 'forms'];

foreach ($removeFolders as $folder) {
    $len = strlen('/' . $folder);
    if (substr($basePath, -$len) === '/' . $folder) {
        $basePath = substr($basePath, 0, -$len);
    }
}

if ($basePath === '/') {
    $basePath = '';
}

define('BASE_URL', $scheme . '://' . $host . $basePath);
