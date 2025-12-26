<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/db_connect.php';

$action = $_GET['action'] ?? '';
$t_pid  = $_GET['t_pid'] ?? '';

try {

    /* =====================================================
       1) ดึงรายชื่อครูทั้งหมด (ใช้สำหรับ autocomplete)
       ===================================================== */
    if ($action === 'get_all') {

        $sql = "SELECT
                    t.t_pid,
                    CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS full_name,
                    s.school_name
                FROM teacher t
                LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
                LEFT JOIN school s ON t.school_id = s.school_id
                ORDER BY t.f_name ASC";

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data'    => $rows
        ]);
        exit;
    }

    /* =====================================================
       2) ดึงข้อมูลครูรายบุคคล (แสดงรายละเอียด)
       ===================================================== */
    if (!empty($t_pid)) {

        $sql = "SELECT
                t.t_pid,
                t.prefix_id,
                t.f_name,
                t.l_name,
                t.school_id,
                t.position_id,
                t.rank_id,
                t.subject_id,

                 CONCAT(IFNULL(p.prefix_name,''), t.f_name, ' ', t.l_name) AS full_name,

                s.school_name,
                pos.position_name,
                r.rank_name,

                sub.subject_name,
                sg.subjectgroup_name    
                FROM teacher t
                LEFT JOIN prefix p ON t.prefix_id = p.prefix_id
                LEFT JOIN school s ON t.school_id = s.school_id
                LEFT JOIN position pos ON t.position_id = pos.position_id
                LEFT JOIN ranks r ON t.rank_id = r.rank_id
                LEFT JOIN subject sub ON t.subject_id = sub.subject_id
                LEFT JOIN subject_group sg ON sub.subjectgroup_id = sg.subjectgroup_id
                WHERE t.t_pid = :t_pid
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':t_pid' => $t_pid
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'success' => true,
                'data'    => $row
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'ไม่พบข้อมูลครู'
            ]);
        }
        exit;
    }

    /* =====================================================
       3) กรณีไม่ส่ง action และไม่ส่ง t_pid
       ===================================================== */
    echo json_encode([
        'success' => false,
        'message' => 'No action specified'
    ]);
} catch (PDOException $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'DB Error: ' . $e->getMessage()
    ]);
}
