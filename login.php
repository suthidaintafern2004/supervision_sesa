<?php
// login.php
session_start();

// ถ้าล็อกอินอยู่แล้ว ให้ไปหน้า index.php
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

// ดึงข้อความ error มาแสดง (ถ้ามี)
$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>เข้าสู่ระบบสำหรับผู้นิเทศ</title>

    <!-- Bootstrap + Icon -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CSS -->
    <link rel="stylesheet" href="css/login.css">

</head>

<body>
    <div class="login-card">

        <h3 class="text-center title-modern mb-3">
            <i class="fa-solid fa-user-shield me-2"></i>เข้าสู่ระบบผู้นิเทศ
        </h3>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-modern" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form action="login_process.php" method="POST" autocomplete="off">
            <div class="mb-3">
                <label for="username" class="form-label">เลขบัตรประชาชน</label>
                <input type="text" class="form-control" id="username" name="username" maxlength="13" required autofocus>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">รหัสผ่าน (4 ตัวท้ายของเลขบัตร)</label>
                <input type="password" class="form-control" id="password" name="password" maxlength="16" required>
            </div>

            <button type="submit" class="btn btn-modern w-100 mt-3">
                <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>