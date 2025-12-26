<?php
// supervisor.php (include ใน supervision_start.php)
// คาดว่า session ถูก start แล้ว และมีค่าดังนี้:
// $_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['office_name'], $_SESSION['position_name'], $_SESSION['rank_name']

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    echo '<div class="alert alert-danger">กรุณาเข้าสู่ระบบก่อน</div>';
    return;
}

$supervisor_pid      = $_SESSION['user_id'];
$supervisor_name     = $_SESSION['user_name'] ?? '-';
$supervisor_office   = $_SESSION['office_name'] ?? '-';
$supervisor_position = $_SESSION['position_name'] ?? '-';
$supervisor_rank     = $_SESSION['rank_name'] ?? '-';
?>
<div class="card-body">
    <h5 class="card-title fw-bold text-primary"><i class="fas fa-user-tie"></i> ข้อมูลผู้นิเทศ</h5>
    <hr>

    <input type="hidden" id="supervisor_id" name="s_p_id" value="<?php echo htmlspecialchars($supervisor_pid); ?>">

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-bold">ชื่อผู้นิเทศ</label>
            <input type="text" class="form-control display-field" value="<?php echo htmlspecialchars($supervisor_name); ?>" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-bold">เลขประจำตัวประชาชน</label>
            <input type="text" class="form-control display-field" value="<?php echo htmlspecialchars($supervisor_pid); ?>" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-bold">สังกัด</label>
            <input type="text" class="form-control display-field" value="<?php echo htmlspecialchars($supervisor_office); ?>" readonly>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-bold">ตำแหน่ง / วิทยฐานะ</label>
            <input type="text" class="form-control display-field" value="<?php echo htmlspecialchars($supervisor_position . ' / ' . $supervisor_rank); ?>" readonly>
        </div>
    </div>
</div>
