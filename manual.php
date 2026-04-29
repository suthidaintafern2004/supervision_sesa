<?php
session_start();
$nav_prefix = '';
$is_supervisor = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คู่มือการใช้งานระบบสารสนเทศการนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .manual-header {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            padding: 40px 0;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .nav-pills .nav-link {
            border-radius: 30px;
            padding: 12px 30px;
            font-weight: 600;
            color: #555;
            background: #f8f9fa;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        .nav-pills .nav-link.active {
            background: #ff8c42;
            color: white;
            box-shadow: 0 4px 10px rgba(255, 140, 66, 0.4);
        }
        .step-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .step-card:hover {
            transform: translateY(-5px);
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #1a73e8;
            color: white;
            border-radius: 50%;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 15px;
        }
        .step-number-teacher {
            background: #28a745;
        }
        .step-title {
            display: flex;
            align-items: center;
            font-size: 1.25rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        .step-body {
            padding-left: 55px;
        }
        .step-body ul {
            padding-left: 20px;
            color: #555;
        }
        .step-body li {
            margin-bottom: 8px;
        }
        .highlight-text {
            color: #d32f2f;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <?php include 'navbar.php'; ?>

    <div class="container my-4">
        
        <div class="manual-header text-center shadow-sm">
            <h1 class="fw-bold"><i class="fas fa-book-reader me-3"></i>คู่มือการใช้งานระบบสารสนเทศการนิเทศ</h1>
            <p class="fs-5 mt-2 opacity-75">สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาลำปาง ลำพูน</p>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-pills justify-content-center mb-5" id="manualTabs" role="tablist">
            <?php if ($is_supervisor): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="supervisor-tab" data-bs-toggle="pill" data-bs-target="#supervisor" type="button" role="tab">
                    <i class="fas fa-user-tie me-2"></i>สำหรับศึกษานิเทศก์ (ผู้ประเมิน)
                </button>
            </li>
            <?php endif; ?>
            <?php if ($is_admin): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="admin-tab" data-bs-toggle="pill" data-bs-target="#admin" type="button" role="tab">
                    <i class="fas fa-user-shield me-2"></i>สำหรับผู้ดูแลระบบ (Admin)
                </button>
            </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= !$is_supervisor ? 'active' : '' ?>" id="teacher-tab" data-bs-toggle="pill" data-bs-target="#teacher" type="button" role="tab">
                    <i class="fas fa-chalkboard-teacher me-2"></i>สำหรับครู (ผู้รับการนิเทศ)
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="manualTabsContent">
            
            <?php if ($is_supervisor): ?>
            <!-- Supervisor Tab -->
            <div class="tab-pane fade show active" id="supervisor" role="tabpanel">
                
                <div class="card step-card border-primary">
                    <div class="card-body p-4">
                        <div class="step-title text-primary">
                            <span class="step-number bg-primary text-white"><i class="fas fa-sign-in-alt"></i></span>
                            1. เข้าสู่ระบบ (Login)
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>ไปที่มุมขวาบนของเว็บไซต์ คลิก <strong>"เข้าสู่ระบบ"</strong></li>
                                <li>กรอก <strong>"เลขประจำตัวประชาชน"</strong> (13 หลัก) ในช่อง Username</li>
                                <li>กรอก <strong>"รหัสผ่าน"</strong> (ค่าเริ่มต้นคือ 4 ตัวท้ายของเลขประจำตัวประชาชน)</li>
                                <li>คลิกปุ่ม <strong>"Login"</strong> เพื่อเข้าสู่ระบบ</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-primary">
                    <div class="card-body p-4">
                        <div class="step-title text-primary">
                            <span class="step-number bg-primary text-white"><i class="fas fa-search"></i></span>
                            2. การค้นหาและตรวจสอบประวัติครู (หน้าแรก)
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>หลังจากเข้าสู่ระบบ จะพบหน้าแรกที่แสดงรายชื่อครูที่เคยได้รับการนิเทศ</li>
                                <li>สามารถ <strong>ค้นหาชื่อครู หรือ โรงเรียน</strong> ได้จากช่องค้นหา</li>
                                <li>สามารถกรองข้อมูลตาม <strong>ปีการศึกษา</strong> ได้</li>
                                <li>คลิกปุ่ม <strong class="text-info"><i class="fas fa-eye"></i> ดูประวัติ</strong> เพื่อดูรายละเอียดการนิเทศของครูท่านนั้น ๆ ทั้งแบบ Classroom และ Quick Win</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-primary">
                    <div class="card-body p-4">
                        <div class="step-title text-primary">
                            <span class="step-number bg-primary text-white"><i class="fas fa-file-signature"></i></span>
                            3. การบันทึกข้อมูลการนิเทศใหม่
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>ไปที่เมนู <strong>"บันทึกการนิเทศ"</strong> ในแถบด้านซ้าย หรือหน้าแรก</li>
                                <li>ค้นหาชื่อ <strong>"ผู้รับนิเทศ"</strong> จากนั้นคลิกเลือกจากรายชื่อที่ระบบแนะนำ</li>
                                <li>เลือกรูปแบบการนิเทศ:
                                    <ul>
                                        <li><strong>ฟอร์ม Class (Classroom):</strong> สำหรับประเมินการจัดการเรียนการสอนในชั้นเรียน (มีตัวชี้วัด 8 ข้อ)</li>
                                        <li><strong>ฟอร์ม QuickWin:</strong> สำหรับประเมินจุดเน้นและนโยบาย</li>
                                    </ul>
                                </li>
                                <li>คลิก <strong>"CONTINUE"</strong> เพื่อไปยังหน้ากรอกคะแนน</li>
                                <li>กรอกข้อมูลให้ครบถ้วน (เช่น รหัสวิชา, ครั้งที่, ภาคเรียน, ปีการศึกษา, คะแนนในแต่ละข้อ, อัปโหลดรูปภาพ)</li>
                                <li>คลิก <strong>"Save Data"</strong> เพื่อบันทึกข้อมูลเข้าสู่ระบบ</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-primary">
                    <div class="card-body p-4">
                        <div class="step-title text-primary">
                            <span class="step-number bg-primary text-white"><i class="fas fa-user-edit"></i></span>
                            4. การจัดการข้อมูลครู
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>ไปที่เมนู <strong>"ข้อมูลครู"</strong> ในแถบด้านซ้าย</li>
                                <li>สามารถ <strong>ค้นหา</strong> รายชื่อครูได้จากช่องค้นหาด้านบน</li>
                                <li>คลิก <strong>"เพิ่มข้อมูลครู"</strong> เพื่อสร้างข้อมูลครูใหม่เข้าสู่ระบบ</li>
                                <li>คลิกปุ่ม <strong class="text-warning"><i class="fas fa-edit"></i></strong> เพื่อแก้ไขข้อมูล หรือปุ่ม <strong class="text-danger"><i class="fas fa-trash"></i></strong> เพื่อลบข้อมูลครู</li>
                                <li><span class="highlight-text">หมายเหตุ:</span> ศึกษานิเทศก์สามารถจัดการเพิ่ม ลบ แก้ไข ได้เฉพาะข้อมูลครูเท่านั้น (การจัดการข้อมูลศึกษานิเทศก์สงวนสิทธิ์ไว้สำหรับผู้ดูแลระบบ หรือ Admin)</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-primary">
                    <div class="card-body p-4">
                        <div class="step-title text-primary">
                            <span class="step-number bg-primary text-white"><i class="fas fa-print"></i></span>
                            5. การตั้งค่าการพิมพ์รายงาน (Print Settings)
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>เมื่ออยู่ในหน้ารายงานผลการนิเทศ (Classroom หรือ Quick Win) ให้คลิกปุ่ม <strong>"พิมพ์รายงาน"</strong></li>
                                <li>หากในหน้าตัวอย่างก่อนพิมพ์ (Print Preview) ปรากฏหัวกระดาษและท้ายกระดาษ (เช่น ลิงก์ URL, วันที่ หรือเลขหน้า) ที่ไม่ต้องการ</li>
                                <li>ให้คลิกไปที่ <strong>"การตั้งค่าเพิ่มเติม" (More options)</strong> ในเมนูด้านซ้ายหรือขวาของหน้าต่างการพิมพ์</li>
                                <li>ค้นหาหัวข้อ <strong>"ตัวเลือก" (Options)</strong></li>
                                <li><span class="highlight-text">คลิกเลือกเอาเครื่องหมายถูกออก</span> ที่เมนู <strong>"หัวกระดาษและท้ายกระดาษ" (Headers and footers)</strong></li>
                                <li>เมื่อหน้ากระดาษสวยงามแล้ว สามารถกด <strong>"พิมพ์" (Print)</strong> หรือเลือก <strong>"บันทึกเป็น PDF" (Save as PDF)</strong> ได้เลย</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
            <?php endif; ?>

            <?php if ($is_admin): ?>
            <!-- Admin Tab -->
            <div class="tab-pane fade" id="admin" role="tabpanel">
                
                <div class="card step-card border-danger">
                    <div class="card-body p-4">
                        <div class="step-title text-danger">
                            <span class="step-number bg-danger text-white"><i class="fas fa-users-cog"></i></span>
                            1. การจัดการข้อมูลผู้ใช้งาน (ครู และ ศึกษานิเทศก์)
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>ไปที่เมนู <strong>"ข้อมูลครู"</strong> หรือ <strong>"ข้อมูลศึกษานิเทศก์ (Admin)"</strong> ในแถบด้านซ้าย</li>
                                <li>สามารถ <strong>ค้นหา</strong> รายชื่อผู้ใช้งานได้จากช่องค้นหาด้านบน</li>
                                <li>คลิก <strong>"เพิ่มข้อมูล"</strong> เพื่อสร้างผู้ใช้งานใหม่ โดยกรอกรายละเอียดให้ครบถ้วน (รหัสบัตรประชาชน 13 หลักจะเป็น Username)</li>
                                <li>คลิกปุ่ม <strong class="text-warning"><i class="fas fa-edit"></i> แก้ไข</strong> เพื่อปรับปรุงข้อมูล หรือปุ่ม <strong class="text-danger"><i class="fas fa-trash"></i> ลบ</strong> เพื่อนำออกจากระบบ</li>
                                <li>สำหรับศึกษานิเทศก์ แอดมินสามารถกำหนดสิทธิ์ <strong>"สิทธิ์ผู้ใช้งาน"</strong> (ศึกษานิเทศก์ หรือ ผู้ดูแลระบบ) ได้</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-danger">
                    <div class="card-body p-4">
                        <div class="step-title text-danger">
                            <span class="step-number bg-danger text-white"><i class="fas fa-list-check"></i></span>
                            2. การจัดการหัวข้อการประเมินจุดเน้น (Quick Win)
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>ไปที่เมนู <strong>"จัดการหัวข้อ Quick Win (Admin)"</strong></li>
                                <li>คลิก <strong>"เพิ่มหัวข้อ"</strong> เพื่อสร้างนโยบายใหม่ หรือแก้ไขข้อความนโยบายเดิมได้ตามต้องการ</li>
                                <li>แอดมินสามารถ <strong>คลิกค้างที่ไอคอน <i class="fas fa-grip-vertical"></i> แล้วลากขึ้น-ลง (Drag & Drop)</strong> เพื่อจัดเรียงลำดับหัวข้อใหม่ได้ทันที</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-danger">
                    <div class="card-body p-4">
                        <div class="step-title text-danger">
                            <span class="step-number bg-danger text-white"><i class="fas fa-certificate"></i></span>
                            3. การจัดการแม่แบบเกียรติบัตร
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>ไปที่เมนู <strong>"จัดการแม่แบบเกียรติบัตร (Admin)"</strong></li>
                                <li>เลือก <strong>"ปีการศึกษา"</strong> ที่ต้องการจัดการ (หากเป็นปีใหม่ สามารถคลิกสร้างคัดลอกรูปแบบจากปีเก่ามาได้)</li>
                                <li><strong>อัปโหลดภาพพื้นหลัง:</strong> เลือกแท็บ Classroom หรือ Quick Win จากนั้นอัปโหลดไฟล์ภาพ .PNG หรือ .JPG</li>
                                <li><strong>ตั้งค่าพิกัดข้อความ:</strong> ในส่วนแสดงผลแบบเรียลไทม์ แอดมินสามารถ <strong>คลิกลากข้อความ</strong> ไปวางในตำแหน่งที่ต้องการบนเกียรติบัตรได้เลย</li>
                                <li>สามารถเพิ่มข้อความแบบดึงจากระบบอัตโนมัติ (Dynamic) เช่น ชื่อครู, ชื่อโรงเรียน หรือพิมพ์ข้อความเอง (Static) และสามารถเปลี่ยนสีและขนาดฟอนต์ได้</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-danger">
                    <div class="card-body p-4">
                        <div class="step-title text-danger">
                            <span class="step-number bg-danger text-white"><i class="fas fa-history"></i></span>
                            4. การจัดการรายการที่บันทึก และ ถังขยะ
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>ไปที่เมนู <strong>"รายการที่บันทึก (Admin)"</strong> เพื่อดูประวัติการนิเทศทั้งหมดในระบบ (สามารถกรองตามปีการศึกษาและรูปแบบการนิเทศได้)</li>
                                <li>แอดมินมีสิทธิ์แก้ไขข้อมูลการนิเทศของศน.ทุกคน และสามารถ <strong>ลบ (Soft Delete)</strong> รายการที่ไม่ต้องการไปยังถังขยะได้</li>
                                <li>หากลบผิด สามารถเข้าไปที่เมนู <strong>"ถังขยะ (Admin)"</strong> เพื่อ <strong>กู้คืน (Restore)</strong> ข้อมูลกลับมา หรือ <strong>ลบถาวร (Force Delete)</strong> ได้</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
            <?php endif; ?>

            <!-- Teacher Tab -->
            <div class="tab-pane fade <?= !$is_supervisor ? 'show active' : '' ?>" id="teacher" role="tabpanel">
                
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                    <i class="fas fa-info-circle fa-2x me-3"></i>
                    <div>
                        <strong>หมายเหตุ:</strong> คุณครูไม่ต้องเข้าสู่ระบบ (Login) เพื่อใช้งาน 
                        สามารถเข้าถึงหน้ารายละเอียดของตนเองได้ผ่าน <strong>ลิงก์ (URL) หรือ QR Code</strong> ที่ศึกษานิเทศก์ส่งให้
                    </div>
                </div>

                <div class="card step-card border-success">
                    <div class="card-body p-4">
                        <div class="step-title text-success">
                            <span class="step-number bg-success text-white"><i class="fas fa-link"></i></span>
                            1. เข้าสู่หน้าประวัติการนิเทศ
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>คลิกลิงก์ที่ศึกษานิเทศก์ส่งให้</li>
                                <li>ตรวจสอบ <strong>ชื่อ-นามสกุล และโรงเรียน</strong> ของท่านว่าถูกต้องหรือไม่</li>
                                <li>ในหน้าต่างนี้ จะแสดงตารางประวัติการได้รับการนิเทศทั้งหมดของท่าน ทั้งแบบชั้นเรียน (Classroom) และ Quick Win</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-success">
                    <div class="card-body p-4">
                        <div class="step-title text-success">
                            <span class="step-number bg-success text-white"><i class="fas fa-file-alt"></i></span>
                            2. ดูรายงานผลการนิเทศ
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>ในตารางประวัติ แต่ละรายการจะมีปุ่ม <strong class="text-white" style="background:#e76f51; padding:2px 8px; border-radius:4px;"><i class="fas fa-file-alt"></i> รายงาน</strong></li>
                                <li>คลิกที่ปุ่ม <strong>"รายงาน"</strong> เพื่อดูรายละเอียดผลคะแนน ข้อเสนอแนะจากผู้นิเทศ และรูปภาพประกอบ</li>
                                <li>ท่านสามารถสั่งพิมพ์ (Print) หน้ารายงานนี้เก็บไว้เป็นหลักฐานได้</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-success">
                    <div class="card-body p-4">
                        <div class="step-title text-success">
                            <span class="step-number bg-success text-white"><i class="fas fa-star"></i></span>
                            3. ทำแบบประเมินความพึงพอใจ
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>หากรายการนิเทศใดยังไม่ได้ทำแบบประเมิน จะปรากฏปุ่ม <strong class="text-dark bg-warning" style="padding:2px 8px; border-radius:4px;"><i class="fas fa-star"></i> ประเมิน</strong></li>
                                <li>คลิกที่ปุ่ม <strong>"ประเมิน"</strong> เพื่อเข้าสู่แบบฟอร์มประเมินความพึงพอใจที่มีต่อศึกษานิเทศก์</li>
                                <li>ให้คะแนนความพึงพอใจในแต่ละข้อ (1-5 คะแนน) และสามารถระบุข้อเสนอแนะเพิ่มเติมได้</li>
                                <li>คลิก <strong>"บันทึกการประเมิน"</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-success">
                    <div class="card-body p-4">
                        <div class="step-title text-success">
                            <span class="step-number bg-success text-white"><i class="fas fa-certificate"></i></span>
                            4. ดาวน์โหลดเกียรติบัตร
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>หลังจากทำแบบประเมินความพึงพอใจเสร็จสิ้น ปุ่มประเมินจะเปลี่ยนสถานะเป็นปุ่ม <strong class="text-white bg-success" style="padding:2px 8px; border-radius:4px;"><i class="fas fa-certificate"></i> เกียรติบัตร</strong> อัตโนมัติ</li>
                                <li>คลิกที่ปุ่ม <strong>"เกียรติบัตร"</strong> เพื่อสร้างและดาวน์โหลดเกียรติบัตรการรับการนิเทศในรูปแบบ PDF</li>
                                <li><span class="highlight-text">ข้อควรระวัง:</span> หากยังไม่ทำแบบประเมินความพึงพอใจ จะไม่สามารถดาวน์โหลดเกียรติบัตรได้</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card step-card border-success">
                    <div class="card-body p-4">
                        <div class="step-title text-success">
                            <span class="step-number bg-success text-white"><i class="fas fa-print"></i></span>
                            5. การตั้งค่าการพิมพ์รายงาน (Print Settings)
                        </div>
                        <div class="step-body">
                            <ul>
                                <li>เมื่ออยู่ในหน้ารายงานผลการนิเทศ ให้คลิกที่ปุ่ม <strong>"พิมพ์รายงาน"</strong> หรือกด (Ctrl + P)</li>
                                <li>หากในหน้าตัวอย่างก่อนพิมพ์ (Print Preview) ปรากฏหัวกระดาษและท้ายกระดาษ (เช่น ลิงก์ URL, วันที่ หรือเลขหน้า) ที่ไม่ต้องการ</li>
                                <li>ให้คลิกไปที่ <strong>"การตั้งค่าเพิ่มเติม" (More options)</strong> ในเมนูด้านซ้ายหรือขวาของหน้าต่างการพิมพ์</li>
                                <li>ค้นหาหัวข้อ <strong>"ตัวเลือก" (Options)</strong></li>
                                <li><span class="highlight-text">ติ๊กเอาเครื่องหมายถูกออก</span> ที่เมนู <strong>"หัวกระดาษและท้ายกระดาษ" (Headers and footers)</strong></li>
                                <li>เมื่อหน้ากระดาษสวยงามแล้ว สามารถกด <strong>"พิมพ์" (Print)</strong> หรือเลือก <strong>"บันทึกเป็น PDF" (Save as PDF)</strong> เพื่อเก็บไว้เป็นหลักฐานได้เลย</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>