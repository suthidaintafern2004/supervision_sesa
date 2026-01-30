<?php
// ตั้งค่า Session 8 ชั่วโมง (28,800 วินาที)
$session_timeout = 28800;

// 1. ตั้งค่า Lifetime ของ Garbage Collector (ฝั่ง Server)
ini_set('session.gc_maxlifetime', $session_timeout);

// 2. ตั้งค่า Lifetime ของ Cookie (ฝั่ง Browser)
session_set_cookie_params($session_timeout);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
