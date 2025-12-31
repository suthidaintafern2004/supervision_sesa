<?php
// ไฟล์: summary.php
session_start();
require_once 'config/db_connect.php'; // ⭐️ เชื่อมต่อฐานข้อมูล (PDO)

// ----------------------------------------------------------------
// A) ตรวจสอบข้อมูลที่ถูกส่งมาจากหน้า supervision_start.php
// ----------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // รับค่าฟอร์มที่เลือก (มีแค่ form_type)
    $selected_form = $_POST['form_type'] ?? null;

    if ($selected_form) {

        // บันทึกข้อมูลฟอร์มทั้งหมดลง Session
        $_SESSION['inspection_data'] = $_POST;

        // ⭐ Router ส่งผู้ใช้ไปยังฟอร์มที่เลือก
        if ($selected_form === 'quickwin_form') {
            header("Location: forms/quickwin_form.php");
            exit();
        }

        if ($selected_form === 'kpi_form') {
            // ให้โหลดหน้าปัจจุบันต่อไปเพื่อแสดง KPI Form
        }

    } else {
        $error_message = 'กรุณาเลือกรูปแบบการนิเทศก่อน';
    }
}

// ----------------------------------------------------------------
// B) ตรวจสอบข้อมูลใน Session ก่อนโหลดฟอร์ม KPI
// ----------------------------------------------------------------

$inspection_data = $_SESSION['inspection_data'] ?? null;

// ⭐ แก้สำคัญ: ไม่ต้องเช็ค s_p_id เพราะ supervisor login แล้ว ไม่ต้องเลือกเอง
if (!$inspection_data || empty($inspection_data['t_pid'])) {
    $error_message = 'ไม่พบข้อมูลการนิเทศ กรุณาเริ่มจากหน้าแรก';
}

$error_message = $error_message ?? '';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>บันทึกการนิเทศการสอน (KPI)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body class="bg-light">

    <?php if (empty($error_message)): ?>
        <div class="container mt-4">
            <a href="supervision_start.php?edit=true" class="btn btn-danger">
                <i class="fas fa-arrow-left"></i> ย้อนกลับไปแก้ไขข้อมูล
            </a>
        </div>
    <?php endif; ?>

    <div class="container my-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white text-center py-3">
                <h4 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>
                    แบบบันทึกการนิเทศการสอน (KPI)
                </h4>
            </div>

            <div class="card-body p-4">
                <?php if (!empty($error_message)): ?>

                    <div class="alert alert-danger text-center shadow-sm" role="alert">
                        <h4 class="alert-heading">
                            <i class="fas fa-exclamation-triangle"></i> พบข้อผิดพลาด
                        </h4>
                        <p><?= $error_message ?></p>
                        <hr>
                        <a href="supervision_start.php" class="btn btn-danger px-4">กลับสู่หน้าเริ่มต้น</a>
                    </div>

                <?php else: ?>

                    <?php
                    // ⭐ โหลด KPI Form
                    include 'forms/kpi_form.php';
                    ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
