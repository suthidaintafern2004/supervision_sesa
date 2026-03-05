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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Sarabun', sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background-color: #fdf8e6;
            /* ปรับเป็นสีครีมสว่างตามพื้นหลังหน้าเว็บ */
        }

        /* พื้นหลังรูปภาพสำนักงาน - ปรับความชัดและความจางตามที่คุณต้องการ */
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('images/office.jpg');
            background-size: cover;
            background-position: center;
            filter: blur(4px);
            /* ความชัดกำลังดี */
            opacity: 0.4;
            z-index: -1;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1000px;
            padding: 20px;
            z-index: 1;
        }

        .login-container {
            display: flex;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(184, 134, 11, 0.15);
            /* เงาสีทองจางๆ */
            min-height: 580px;
        }

        /* ฝั่งซ้าย: ปรับเป็น Gradient เหลืองทอง-ส้มมัสตาร์ด ตามหน้าเว็บ */
        .login-left {
            flex: 1.2;
            background: linear-gradient(135deg, #f1b31c 0%, #d49a10 100%);
            position: relative;
            display: flex;
            align-items: center;
            padding: 60px;
            color: #fff;
        }

        .login-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        /* โลโก้วงกลม: ขอบสีเหลืองทอง */
        .logo-container {
            width: 110px;
            height: 110px;
            border: 4px solid #f1b31c;
            border-radius: 50%;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #fff;
        }

        .logo-container img {
            width: 85%;
            height: auto;
        }

        .form-box {
            width: 100%;
            max-width: 320px;
        }

        /* หัวข้อเปลี่ยนเป็นสีน้ำตาลเหลืองแบบตัวหนังสือในหน้าเว็บ */
        .form-title {
            color: #b08210;
            text-align: center;
            font-weight: 700;
            margin-bottom: 30px;
            font-size: 1.3rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }

        /* ไอคอนสีเหลืองทองมัสตาร์ด */
        .input-group-custom i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #f1b31c;
            z-index: 10;
        }

        .input-group-custom input {
            width: 100%;
            padding: 12px 20px 12px 50px;
            background: #fffdf5;
            border: 1px solid #eee0b1;
            border-radius: 25px;
            outline: none;
            transition: 0.3s;
        }

        .input-group-custom input:focus {
            border-color: #f1b31c;
            background: #fff;
            box-shadow: 0 0 10px rgba(241, 179, 28, 0.15);
        }

        /* ปุ่ม Login: สีเหลืองทองแบบปุ่มค้นหาในหน้าเว็บ */
        .btn-login-gradient {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 25px;
            background: linear-gradient(to right, #f1b31c, #e0a510);
            color: #fff;
            font-weight: 700;
            margin-top: 10px;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 8px 15px rgba(241, 179, 28, 0.2);
        }

        .btn-login-gradient:hover {
            transform: translateY(-2px);
            background: linear-gradient(to right, #e0a510, #c9940e);
            box-shadow: 0 10px 20px rgba(241, 179, 28, 0.3);
        }

        /* กราฟิกตกแต่งฝั่งซ้าย */
        .shape-1 {
            width: 300px;
            height: 40px;
            top: 20%;
            left: -50px;
            transform: rotate(-45deg);
            position: absolute;
            background: linear-gradient(to right, rgba(255, 255, 255, 0.25), transparent);
            border-radius: 50px;
        }

        .circle-1 {
            width: 150px;
            height: 150px;
            top: -50px;
            right: -50px;
            border-radius: 50%;
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
        }

        @media (max-width: 850px) {
            .login-left {
                display: none;
            }

            .login-container {
                max-width: 400px;
            }
        }
    </style>
</head>

<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-left">
                <div class="welcome-content">
                    <h1>Welcome to<br>Website</h1>
                    <p>ระบบบันทึกข้อมูลและสารสนเทศการนิเทศการศึกษา สพม.ลำปาง ลำพูน</p>
                </div>
                <div class="shape-1"></div>
                <div class="circle-1"></div>
            </div>

            <div class="login-right">
                <div class="logo-container">
                    <img src="images/logo.png" alt="Logo">
                </div>

                <div class="form-box">
                    <h2 class="form-title">User Login</h2>

                    <form action="login_process.php" method="POST">
                        <div class="input-group-custom">
                            <i class="fa-regular fa-user"></i>
                            <input type="text" name="username" placeholder="เลขบัตรประชาชน" maxlength="13" required>
                        </div>

                        <div class="input-group-custom">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" placeholder="รหัสผ่าน" required>
                        </div>

                        <button type="submit" class="btn-login-gradient">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>