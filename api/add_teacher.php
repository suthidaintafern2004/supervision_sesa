<?php
// File: api/add_teacher.php
header('Content-Type: application/json');
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. รับค่าจากฟอร์ม
    $t_pid       = trim($_POST['t_pid'] ?? '');
    $prefix_id   = $_POST['prefix_id'] ?? ''; // แก้ไข: รับค่าเป็น string ว่างถ้าไม่มี
    $f_name      = trim($_POST['f_name'] ?? '');
    $l_name      = trim($_POST['l_name'] ?? '');
    $position_id = $_POST['position_id'] ?? '';
    $rank_id     = $_POST['rank_id'] ?? null; // วิทยฐานะเป็น NULL ได้
    $school_id   = $_POST['school_id'] ?? '';

    // 2. ตรวจสอบข้อมูลจำเป็น (Validation)
    // [G-Refactor] เพิ่มการเช็ค prefix_id เพราะใน DB ห้ามเป็น NULL
    if (empty($t_pid) || empty($prefix_id) || empty($f_name) || empty($l_name) || empty($school_id) || empty($position_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน (รหัสบัตร, คำนำหน้า, ชื่อ-สกุล, ตำแหน่ง, โรงเรียน)'
        ]);
        exit;
    }

    // ตรวจสอบเลขบัตรประชาชน 13 หลัก
    if (strlen($t_pid) != 13 || !is_numeric($t_pid)) {
        echo json_encode([
            'success' => false,
            'message' => 'รหัสบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'
        ]);
        exit;
    }

    try {
        // 3. ตรวจสอบว่ามีครูคนนี้อยู่แล้วหรือไม่
        $check_sql = "SELECT COUNT(*) FROM teacher WHERE t_pid = :pid";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->execute([':pid' => $t_pid]);

        if ($check_stmt->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'รหัสบัตรประชาชนนี้มีอยู่ในระบบแล้ว'
            ]);
            exit;
        }

        // 4. หา office_id (เนื่องจากฟอร์มไม่มีให้เลือก ต้องดึงจากระบบ)
        // ดึง office_id แรกที่เจอในระบบ (ปกติจะมีค่าเดียวตาม SQL dump คือ '1000520001')
        $stmt_office = $conn->query("SELECT office_id FROM office LIMIT 1");
        $office_id = $stmt_office->fetchColumn();

        if (!$office_id) {
            $office_id = '1000520001'; // Fallback ค่า Default ถ้าหาไม่เจอ
        }

        // 5. กำหนดค่า Default อื่นๆ
        // subject_id ใน DB ห้ามว่าง แต่ฟอร์มเพิ่มครูทั่วไปอาจไม่ได้ระบุวิชาเอก
        // กำหนดเป็น '0' (ไม่ระบุ/อื่นๆ) ตามที่มีในตาราง subject
        $subject_id = '0';

        // 6. บันทึกข้อมูล (Insert)
        $sql = "INSERT INTO teacher 
                (office_id, school_id, t_pid, prefix_id, f_name, m_name, l_name, subject_id, subject, position_id, rank_id) 
                VALUES 
                (:office, :school, :pid, :prefix, :fname, NULL, :lname, :subj_id, NULL, :pos, :rank)";

        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([
            ':office'   => $office_id,
            ':school'   => $school_id,
            ':pid'      => $t_pid,
            ':prefix'   => $prefix_id, // ส่งค่า prefix_id ที่ผ่านการเช็คแล้ว
            ':fname'    => $f_name,
            ':lname'    => $l_name,
            ':subj_id'  => $subject_id,
            ':pos'      => $position_id,
            ':rank'     => empty($rank_id) ? NULL : $rank_id // ถ้า rank_id ว่าง ให้ส่ง NULL (DB ยอมรับได้)
        ]);

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'บันทึกข้อมูลไม่สำเร็จ']);
        }
    } catch (PDOException $e) {
        // ส่ง Error ของ Database กลับไปเพื่อดูสาเหตุ (เช่น Constraint Violation)
        echo json_encode([
            'success' => false,
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid Request Method'
    ]);
}
