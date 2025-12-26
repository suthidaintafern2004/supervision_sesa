<?php
// forms/save_satisfaction.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// เชื่อมต่อฐานข้อมูล (PDO)
if (file_exists('../config/db_connect.php')) {
    require_once '../config/db_connect.php';
} elseif (file_exists('config/db_connect.php')) {
    require_once 'config/db_connect.php';
}

function redirect_with_flash_message($message, $type = 'danger', $location = '../history.php')
{
    $_SESSION['flash_message']      = $message;
    $_SESSION['flash_message_type'] = $type;
    echo "<script>window.location.href='$location';</script>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_with_flash_message("Invalid request method.");
}

// โหมดการประเมิน
$mode = $_POST['mode'] ?? 'normal';

// ข้อมูลร่วม
$ratings            = $_POST['ratings'] ?? [];
$overall_suggestion = trim($_POST['overall_suggestion'] ?? '');

if (empty($ratings)) {
    redirect_with_flash_message("กรุณาให้คะแนนประเมินอย่างน้อย 1 ข้อ");
}

try {
    $conn->beginTransaction();

    // ---------------------------------------------------------
    // [NORMAL] นิเทศปกติ
    // ---------------------------------------------------------
    if ($mode === 'normal') {

        $s_pid    = $_POST['s_pid']    ?? null;
        $t_pid    = $_POST['t_pid']    ?? null;
        $sub_code = $_POST['sub_code'] ?? null;
        $time     = $_POST['time']     ?? null;

        if (!$s_pid || !$t_pid || !$sub_code || !$time) {
            redirect_with_flash_message("ข้อมูลที่จำเป็นสำหรับการบันทึกไม่ครบถ้วน (normal)");
        }

        // ลบคะแนนเก่า (PDO)
        $sql_del = "DELETE FROM satisfaction_answers 
                    WHERE supervisor_p_id = :sid 
                      AND teacher_t_pid   = :tid 
                      AND subject_code    = :scode 
                      AND inspection_time = :time";

        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->execute([
            ':sid'   => $s_pid,
            ':tid'   => $t_pid,
            ':scode' => $sub_code,
            ':time'  => $time
        ]);

        // Insert คะแนนใหม่
        $sql_answer = "INSERT INTO satisfaction_answers 
                       (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, question_id, rating) 
                       VALUES (:sid, :tid, :scode, :time, :qid, :rate)";
        $stmt_answer = $conn->prepare($sql_answer);

        foreach ($ratings as $question_id => $rating) {
            $stmt_answer->execute([
                ':sid'   => $s_pid,
                ':tid'   => $t_pid,
                ':scode' => $sub_code,
                ':time'  => $time,
                ':qid'   => (int)$question_id,
                ':rate'  => (int)$rating
            ]);
        }

        // อัปเดตสถานะใน session
        $sql_update = "UPDATE supervision_sessions 
                       SET satisfaction_suggestion = :sugg, 
                           satisfaction_date       = NOW(), 
                           satisfaction_submitted  = 1 
                       WHERE supervisor_p_id = :sid 
                         AND teacher_t_pid   = :tid 
                         AND subject_code    = :scode 
                         AND inspection_time = :time";

        $stmt_upd = $conn->prepare($sql_update);
        $stmt_upd->execute([
            ':sugg'  => $overall_suggestion,
            ':sid'   => $s_pid,
            ':tid'   => $t_pid,
            ':scode' => $sub_code,
            ':time'  => $time
        ]);

        // เตรียมข้อมูล Redirect
        $redirect_target = 'satisfaction_form.php';
        $redirect_params = [
            'mode'     => 'normal',
            's_pid'    => $s_pid,
            't_pid'    => $t_pid,
            'sub_code' => $sub_code,
            'time'     => $time
        ];

        // ---------------------------------------------------------
        // [QUICK WIN] จุดเน้น (quickwin)
        // ---------------------------------------------------------
    } elseif ($mode === 'quickwin') {

        $t_id             = $_POST['t_id']             ?? null; // ในฟอร์มใช้ t_id แต่ค่าคือ pid
        $p_id             = $_POST['p_id']             ?? null;
        $supervision_date = $_POST['supervision_date'] ?? null;

        if (!$t_id || !$p_id || !$supervision_date) {
            redirect_with_flash_message("ข้อมูลที่จำเป็นสำหรับการบันทึกไม่ครบถ้วน (quickwin)");
        }

        // ลบคะแนนเก่า (แก้ไข t_id -> t_pid)
        $sql_del = "DELETE FROM quickwin_satisfaction_answers 
                    WHERE t_pid            = :tid 
                      AND p_id             = :pid 
                      AND supervision_date = :sdate";

        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->execute([
            ':tid'   => $t_id,
            ':pid'   => $p_id,
            ':sdate' => $supervision_date
        ]);

        // Insert คะแนนใหม่ (แก้ไข t_id -> t_pid)
        $sql_answer = "INSERT INTO quickwin_satisfaction_answers 
                       (t_pid, p_id, supervision_date, question_id, rating) 
                       VALUES (:tid, :pid, :sdate, :qid, :rate)";
        $stmt_answer = $conn->prepare($sql_answer);

        foreach ($ratings as $question_id => $rating) {
            $stmt_answer->execute([
                ':tid'   => $t_id,
                ':pid'   => $p_id,
                ':sdate' => $supervision_date,
                ':qid'   => (int)$question_id,
                ':rate'  => (int)$rating
            ]);
        }

        // อัปเดตสถานะใน quick_win (แก้ไข t_id -> t_pid)
        $sql_update = "UPDATE quick_win 
                       SET satisfaction_suggestion = :sugg, 
                           satisfaction_date       = NOW(), 
                           satisfaction_submitted  = 1 
                       WHERE t_pid            = :tid 
                         AND p_id             = :pid 
                         AND supervision_date = :sdate";

        $stmt_upd = $conn->prepare($sql_update);
        $stmt_upd->execute([
            ':sugg'  => $overall_suggestion,
            ':tid'   => $t_id,
            ':pid'   => $p_id,
            ':sdate' => $supervision_date
        ]);

        // เตรียมข้อมูล Redirect
        $redirect_target = 'satisfaction_form.php';
        $redirect_params = [
            'mode' => 'quickwin',
            't_id' => $t_id,
            'p_id' => $p_id,
            'date' => $supervision_date
        ];
    } else {
        throw new Exception("Unknown mode: " . $mode);
    }

    // ---------------------------------------------------------
    // Commit & Redirect
    // ---------------------------------------------------------
    $conn->commit();

    $_SESSION['flash_message']      = "บันทึกการประเมินเรียบร้อยแล้ว";
    $_SESSION['flash_message_type'] = "success";

    // Auto-submit Form เพื่อ Redirect แบบ POST (ปลอดภัยกว่า GET)
    echo '<!DOCTYPE html>
    <html>
    <head><title>Redirecting...</title></head>
    <body>
        <form id="redirectForm" action="' . htmlspecialchars($redirect_target) . '" method="post">';

    foreach ($redirect_params as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '">';
    }

    echo '  </form>
        <script type="text/javascript">
            document.getElementById("redirectForm").submit();
        </script>
    </body>
    </html>';
    exit;
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Save Satisfaction DB Error: " . $e->getMessage());
    redirect_with_flash_message("เกิดข้อผิดพลาดฐานข้อมูล: " . $e->getMessage());
} catch (Exception $e) {
    $conn->rollBack();
    error_log("Save Satisfaction Error: " . $e->getMessage());
    redirect_with_flash_message("เกิดข้อผิดพลาด: " . $e->getMessage());
}
