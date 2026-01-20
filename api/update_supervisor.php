<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $p_id        = $_POST['p_id'];
    $prefix_id   = $_POST['prefix_id'];
    $fname       = $_POST['fname'];
    $lname       = $_POST['lname'];
    $office_id   = $_POST['office_id'];
    $position_id = $_POST['position_id'];
    $rank_id     = $_POST['rank_id'] ?: null;
    $role        = $_POST['role'];   // 🔴 จุดสำคัญ

    $sql = "UPDATE supervisor SET
        prefix_id = :prefix_id,
        fname = :fname,
        lname = :lname,
        office_id = :office_id,
        position_id = :position_id,
        rank_id = :rank_id,
        role = :role
    WHERE p_id = :p_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':prefix_id', $prefix_id);
    $stmt->bindParam(':fname', $fname);
    $stmt->bindParam(':lname', $lname);
    $stmt->bindParam(':office_id', $office_id);
    $stmt->bindParam(':position_id', $position_id);
    $stmt->bindParam(':rank_id', $rank_id);
    $stmt->bindParam(':role', $role); // 🔴 ต้องมี
    $stmt->bindParam(':p_id', $p_id);

    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
