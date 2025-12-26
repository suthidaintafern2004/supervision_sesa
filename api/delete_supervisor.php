<?php
// File: api/delete_supervisor.php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p_id = $_POST['p_id'] ?? '';

    if (empty($p_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing p_id']);
        exit;
    }

    try {
        // เช็คประวัติการนิเทศ
        $chk = $conn->prepare("SELECT COUNT(*) FROM supervision_sessions WHERE supervisor_p_id = :pid");
        $chk->execute([':pid' => $p_id]);
        if ($chk->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'ลบไม่ได้: ผู้นิเทศท่านนี้มีประวัติการนิเทศแล้ว']);
            exit;
        }

        $sql = $conn->prepare("DELETE FROM supervisor WHERE p_id = :p_id");
        $sql->execute([':p_id' => $p_id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
