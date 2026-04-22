<?php
// File: api/delete_supervisor.php
header('Content-Type: application/json; charset=utf-8');
session_start(); // ⭐ เพิ่ม
require_once '../config/db_connect.php';

// ⭐ เพิ่มส่วนนี้
if (
    !isset($_SESSION['is_logged_in']) ||
    $_SESSION['role'] !== 'admin'
) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p_id = $_POST['p_id'] ?? '';

    if (empty($p_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing p_id']);
        exit;
    }

    try {
        // เช็คประวัติการนิเทศ
        $chk = $conn->prepare("SELECT COUNT(*) FROM supervision_sessions WHERE p_id = :pid");
        $chk->execute([':pid' => $p_id]);
        if ($chk->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'ลบไม่ได้: ผู้นิเทศท่านนี้มีประวัติการนิเทศแล้ว']);
            exit;
        }

        // เช็คประวัติ Quick Win
        $chkQW = $conn->prepare("SELECT COUNT(*) FROM quick_win WHERE p_id = :pid");
        $chkQW->execute([':pid' => $p_id]);
        if ($chkQW->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'ลบไม่ได้: ผู้นิเทศท่านนี้มีประวัติการนิเทศ Quick Win แล้ว']);
            exit;
        }

        $sql = $conn->prepare("DELETE FROM supervisor WHERE p_id = :p_id");
        $sql->execute([':p_id' => $p_id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
