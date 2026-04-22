<?php
session_start();
require_once 'config/db_connect.php';

/* =======================
   ตรวจสิทธิ์ Admin
======================= */
if (!isset($_SESSION['is_logged_in']) || ($_SESSION['role'] ?? '') !== 'admin') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script><script>Swal.fire({icon: 'warning', title: 'ไม่มีสิทธิ์เข้าใช้งาน', text: 'ระบบนี้เป็นสิทธิ์ของแอดมินเท่านั้น', confirmButtonText: 'ตกลง'}).then(() => { window.location.href = 'index.php'; });</script>";
    exit;
}

// ตรวจสอบและสร้างโฟลเดอร์อัปโหลดภาพพื้นหลัง
$upload_dir = __DIR__ . '/uploads/certificates/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// =======================
// DB Migration & Table Setup
// =======================
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `cert_settings` (
        `form_type` varchar(20) NOT NULL,
        `academic_year` int(11) NOT NULL,
        `bg_image` varchar(255) NOT NULL,
        PRIMARY KEY (`form_type`, `academic_year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    $conn->exec("CREATE TABLE IF NOT EXISTS `cert_elements` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `form_type` varchar(20) NOT NULL,
        `academic_year` int(11) NOT NULL,
        `element_type` varchar(50) NOT NULL,
        `element_key` varchar(50) NOT NULL,
        `text_value` text,
        `pos_x` float NOT NULL DEFAULT '0',
        `pos_y` float NOT NULL DEFAULT '0',
        `font_size` int(11) NOT NULL DEFAULT '20',
        `font_align` varchar(1) NOT NULL DEFAULT 'L',
        `color` varchar(20) NOT NULL DEFAULT '#080d56',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // เพิ่มคอลัมน์ color ลงในตารางเก่าแบบอัตโนมัติ (หากยังไม่มี)
    try {
        $conn->exec("ALTER TABLE `cert_elements` ADD COLUMN `color` varchar(20) NOT NULL DEFAULT '#080d56'");
    } catch (PDOException $e) {}

    // อัปเดตข้อมูลเก่าให้มีคำว่า "เดือน" หากยังไม่มี (สำหรับข้อมูลที่สร้างก่อนหน้านี้)
    try {
        $conn->exec("UPDATE cert_elements SET text_value = REPLACE(text_value, 'วันที่ ๑ มกราคม', 'วันที่ ๑ เดือน มกราคม') WHERE text_value LIKE '%วันที่ ๑ มกราคม%' AND text_value NOT LIKE '%เดือน%'");
        $conn->exec("UPDATE cert_elements SET text_value = REPLACE(text_value, 'วันที่ ๑๔ กุมภาพันธ์', 'วันที่ ๑๔ เดือน กุมภาพันธ์') WHERE text_value LIKE '%วันที่ ๑๔ กุมภาพันธ์%' AND text_value NOT LIKE '%เดือน%'");
    } catch (PDOException $e) {}
} catch (PDOException $e) {}

// =======================
// Handle POST Actions
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_new_year') {
        $from_year = (int)$_POST['from_year'];
        $to_year = (int)$_POST['to_year'];
        
        if ($from_year && $to_year) {
            $stmt = $conn->prepare("SELECT * FROM cert_settings WHERE academic_year = ?");
            $stmt->execute([$from_year]);
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmtIns = $conn->prepare("REPLACE INTO cert_settings (form_type, academic_year, bg_image) VALUES (?, ?, ?)");
            foreach($settings as $row) { $stmtIns->execute([$row['form_type'], $to_year, $row['bg_image']]); }
            
            $stmt = $conn->prepare("SELECT * FROM cert_elements WHERE academic_year = ?");
            $stmt->execute([$from_year]);
            $elems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmtIns = $conn->prepare("INSERT INTO cert_elements (form_type, academic_year, element_type, element_key, text_value, pos_x, pos_y, font_size, font_align, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach($elems as $row) { $stmtIns->execute([$row['form_type'], $to_year, $row['element_type'], $row['element_key'], $row['text_value'], $row['pos_x'], $row['pos_y'], $row['font_size'], $row['font_align'], $row['color'] ?? '#080d56']); }
        }
        header("Location: edit_certificate_template.php?academic_year=" . $to_year);
        exit;
    }
    
    $academic_year = (int)($_POST['academic_year'] ?? 0);
    
    if ($action === 'upload_bg' && $academic_year) {
        $form_type = $_POST['form_type'];
        if (isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] == 0) {
            $ext = pathinfo($_FILES['bg_image']['name'], PATHINFO_EXTENSION);
            $filename = "bg_{$form_type}_{$academic_year}_" . time() . ".$ext";
            move_uploaded_file($_FILES['bg_image']['tmp_name'], $upload_dir . $filename);
            $stmt = $conn->prepare("REPLACE INTO cert_settings (form_type, academic_year, bg_image) VALUES (?, ?, ?)");
            $stmt->execute([$form_type, $academic_year, $filename]);
        }
        header("Location: edit_certificate_template.php?academic_year=" . $academic_year . "&tab=" . $form_type);
        exit;
    } elseif ($action === 'edit_element' && $academic_year) {
        $elem_type = $_POST['element_type'] ?? 'static';
        $elem_key = ($elem_type === 'dynamic') ? ($_POST['element_key'] ?? 'custom') : 'custom';
        $form_type = !empty($_POST['form_type']) ? $_POST['form_type'] : 'classroom';
        $stmt = $conn->prepare("UPDATE cert_elements SET text_value = ?, pos_x = ?, pos_y = ?, font_size = ?, font_align = ?, element_type = ?, element_key = ?, color = ? WHERE id = ?");
        $stmt->execute([$_POST['text_value'], $_POST['pos_x'], $_POST['pos_y'], $_POST['font_size'], $_POST['font_align'], $elem_type, $elem_key, $_POST['color'], $_POST['id']]);
        header("Location: edit_certificate_template.php?academic_year=" . $academic_year . "&tab=" . $form_type);
        exit;
    } elseif ($action === 'add_element' && $academic_year) {
        $elem_type = $_POST['element_type'] ?? 'static';
        $elem_key = ($elem_type === 'dynamic') ? ($_POST['element_key'] ?? 'custom') : 'custom';
        $form_type = !empty($_POST['form_type']) ? $_POST['form_type'] : 'classroom';
        $stmt = $conn->prepare("INSERT INTO cert_elements (form_type, academic_year, element_type, element_key, text_value, pos_x, pos_y, font_size, font_align, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$form_type, $academic_year, $elem_type, $elem_key, $_POST['text_value'], $_POST['pos_x'], $_POST['pos_y'], $_POST['font_size'], $_POST['font_align'], $_POST['color']]);
        header("Location: edit_certificate_template.php?academic_year=" . $academic_year . "&tab=" . $form_type);
        exit;
    } elseif ($action === 'delete_element') {
        $stmt = $conn->prepare("DELETE FROM cert_elements WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'update_position') {
        $stmt = $conn->prepare("UPDATE cert_elements SET pos_x = ?, pos_y = ? WHERE id = ?");
        $stmt->execute([$_POST['pos_x'], $_POST['pos_y'], $_POST['id']]);
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'delete_year') {
        $del_year = (int)$_POST['academic_year'];
        if ($del_year) {
            // ลบรูปภาพพื้นหลังทั้งหมดของปีนี้ออกจาก Server
            $stmt = $conn->prepare("SELECT bg_image FROM cert_settings WHERE academic_year = ?");
            $stmt->execute([$del_year]);
            $bgs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($bgs as $bg) {
                if ($bg && file_exists($upload_dir . $bg)) {
                    @unlink($upload_dir . $bg);
                }
            }
            $conn->prepare("DELETE FROM cert_settings WHERE academic_year = ?")->execute([$del_year]);
            $conn->prepare("DELETE FROM cert_elements WHERE academic_year = ?")->execute([$del_year]);
        }
        header("Location: edit_certificate_template.php?academic_year=" . $del_year);
        exit;
    }
}

// =======================
// Display Logic
// =======================
$selected_year = isset($_GET['academic_year']) ? (int)$_GET['academic_year'] : null;
$stmt = $conn->query("SELECT DISTINCT academic_year FROM cert_elements ORDER BY academic_year DESC");
$available_years = $stmt->fetchAll(PDO::FETCH_COLUMN);
$latest_year = !empty($available_years) ? max($available_years) : ((int)date('Y') + 543);

if ($selected_year) {
    // Seed default elements with Realistic Data (ข้อมูลจำลองเหมือนจริง)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM cert_elements WHERE academic_year = ?");
    $stmt->execute([$selected_year]);
    if ($stmt->fetchColumn() == 0) {
        $th_year = str_replace(['0','1','2','3','4','5','6','7','8','9'], ['๐','๑','๒','๓','๔','๕','๖','๗','๘','๙'], (string)$selected_year);
        $seeds = [
            // ข้อมูลจำลองสำหรับ Classroom
            ['classroom', $selected_year, 'dynamic', 'ref', 'ศธ ๐๔๒๔๔/ว ' . $th_year . '-๐๐๑', 235, 12, 16, 'L'],
            ['classroom', $selected_year, 'dynamic', 'teacher', 'นายสมชาย ใจดี', 0, 72, 32, 'C'],
            ['classroom', $selected_year, 'dynamic', 'school', 'โรงเรียนบุญวาทย์วิทยาลัย', 0, 88, 28, 'C'],
            ['classroom', $selected_year, 'dynamic', 'date', 'ให้ไว้ ณ วันที่ ๑ เดือน มกราคม พ.ศ. ' . $th_year, 0, 148, 20, 'C'],
            
            // ข้อมูลจำลองสำหรับ Quickwin
            ['quickwin', $selected_year, 'dynamic', 'ref', 'QW-' . $th_year . '/๐๘๕', 235, 12, 16, 'L'],
            ['quickwin', $selected_year, 'dynamic', 'teacher', 'นางสาวสมศรี เรียนดี', 0, 72, 32, 'C'],
            ['quickwin', $selected_year, 'dynamic', 'school', 'โรงเรียนลำปางกัลยาณี', 0, 88, 28, 'C'],
            ['quickwin', $selected_year, 'dynamic', 'date', 'ให้ไว้ ณ วันที่ ๑๔ เดือน กุมภาพันธ์ พ.ศ. ' . $th_year, 0, 148, 20, 'C'],
        ];
        $stmtIns = $conn->prepare("INSERT INTO cert_elements (form_type, academic_year, element_type, element_key, text_value, pos_x, pos_y, font_size, font_align, color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($seeds as $s) {
            $s[] = '#080d56'; // Default color
            $stmtIns->execute($s);
        }
    }

    $bg_settings = [];
    $stmt = $conn->prepare("SELECT form_type, bg_image FROM cert_settings WHERE academic_year = ?");
    $stmt->execute([$selected_year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $bg_settings[$row['form_type']] = $row['bg_image']; }

    $elements = ['classroom' => [], 'quickwin' => []];
    $stmt = $conn->prepare("SELECT * FROM cert_elements WHERE academic_year = ? ORDER BY form_type, id ASC");
    $stmt->execute([$selected_year]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $elements[$row['form_type']][] = $row; }
}

$types = ['classroom' => 'Classroom', 'quickwin' => 'Quick Win'];
$active_tab = $_GET['tab'] ?? 'classroom';
if (!array_key_exists($active_tab, $types)) $active_tab = 'classroom';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>จัดการแม่แบบเกียรติบัตร | LPRU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'EakChodok';
            src: url('fonts/eak_chodok.ttf') format('truetype');
        }
        body { font-family: 'Sarabun', sans-serif; background-color: #f8fafb; }
        .year-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: none; border-radius: 20px; overflow: hidden; background: #fff; height: 100%; box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .year-card:hover { transform: translateY(-8px); box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        .year-thumb-container { position: relative; height: 180px; background: #f0f0f0; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .year-thumb-container img { width: 100%; height: 100%; object-fit: cover; opacity: 0.9; transition: 0.5s; }
        .year-card:hover .year-thumb-container img { opacity: 1; transform: scale(1.08); }
        .year-badge { position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.95); backdrop-filter: blur(8px); padding: 6px 15px; border-radius: 30px; font-weight: 800; font-size: 0.85rem; color: #d9a406; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid rgba(255,193,7,0.2); }
        .preview-box { position: relative; width: 100%; max-width: 850px; margin: 0 auto; aspect-ratio: 297/210; background: #fff; box-shadow: 0 15px 50px rgba(0,0,0,0.15); border-radius: 4px; overflow: hidden; }
        .preview-el { position: absolute; color: #1a237e; font-family: 'EakChodok', 'Sarabun', sans-serif; font-weight: normal; line-height: 1.25; white-space: nowrap; cursor: move; user-select: none; outline: 1px dashed transparent; transition: outline 0.2s, background 0.2s; }
        .preview-el:hover { outline: 1px dashed #ffc107; background: rgba(255, 193, 7, 0.2); z-index: 10; }
        .nav-pills .nav-link { border-radius: 30px; padding: 10px 25px; font-weight: 600; color: #6c757d; transition: 0.3s; }
        .nav-pills .nav-link.active { background-color: #ffc107; color: #000; box-shadow: 0 4px 12px rgba(255,193,7,0.3); }
    </style>
</head>
<body>

    <?php if (file_exists('navbar.php')) include 'navbar.php'; ?>

    <div class="container py-5">
        <?php if (!$selected_year): ?>
            <div class="text-center mb-5 mt-4">
                <h1 class="fw-extrabold text-dark mb-2">ออกแบบเกียรติบัตร</h1>
                <p class="text-muted fs-5">เลือกปีการศึกษาที่ต้องการปรับแต่งข้อมูลจำลองและภาพพื้นหลัง</p>
            </div>

            <div class="row g-4 justify-content-center">
                <?php foreach ($available_years as $y): 
                    $stmtThumb = $conn->prepare("SELECT bg_image FROM cert_settings WHERE academic_year = ? AND form_type = 'classroom' LIMIT 1");
                    $stmtThumb->execute([$y]);
                    $thumb = $stmtThumb->fetchColumn();
                    $thumbPath = ($thumb && file_exists('uploads/certificates/'.$thumb)) ? 'uploads/certificates/'.$thumb : 'images/ctest.png';
                ?>
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card year-card">
                        <div class="year-thumb-container">
                            <img src="<?= $thumbPath ?>" alt="Year <?= $y ?>">
                            <div class="year-badge">พ.ศ. <?= $y ?></div>
                        </div>
                        <div class="card-body text-center p-4">
                            <h5 class="fw-bold mb-3">ปีการศึกษา <?= $y ?></h5>
                            <a href="?academic_year=<?= $y ?>" class="btn btn-warning w-100 fw-bold rounded-pill stretched-link">จัดการแม่แบบ</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="col-sm-6 col-md-4 col-lg-3">
                    <div class="card year-card border-warning" style="border: 2px dashed #ffc107 !important; background: #fffdf7;">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle mb-3">
                                <i class="fas fa-plus fa-2x text-warning"></i>
                            </div>
                            <h5 class="fw-bold">เพิ่มปีใหม่</h5>
                            <button class="btn btn-dark btn-sm rounded-pill px-4 fw-bold mt-2" data-bs-toggle="modal" data-bs-target="#newYearModal">สร้างทันที</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="newYearModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-4 shadow">
                        <div class="modal-header border-0 pb-0">
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body p-4 pt-0 text-center">
                                <i class="fas fa-copy fa-3x text-warning mb-3"></i>
                                <h4 class="fw-bold mb-4">คัดลอกข้อมูลไปปีการศึกษาใหม่</h4>
                                <input type="hidden" name="action" value="create_new_year">
                                <div class="text-start mb-3">
                                    <label class="form-label small fw-bold text-muted">ต้นฉบับจากปี:</label>
                                    <select name="from_year" class="form-select rounded-3">
                                        <?php foreach ($available_years as $y): ?>
                                            <option value="<?= $y ?>">ปีการศึกษา <?= $y ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="text-start mb-4">
                                    <label class="form-label small fw-bold text-muted">ปีการศึกษาใหม่:</label>
                                    <input type="number" name="to_year" class="form-control rounded-3 fw-bold" value="<?= $latest_year + 1 ?>">
                                </div>
                                <button type="submit" class="btn btn-warning w-100 fw-bold py-2 rounded-3">สร้างข้อมูลปีใหม่</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-0">ปีการศึกษา <?= $selected_year ?></h2>
                    <nav aria-label="breadcrumb">
                      <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="edit_certificate_template.php" class="text-decoration-none">เลือกปี</a></li>
                        <li class="breadcrumb-item active">ตั้งค่าแม่แบบ</li>
                      </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" id="deleteYearForm" class="m-0">
                        <input type="hidden" name="action" value="delete_year">
                        <input type="hidden" name="academic_year" value="<?= $selected_year ?>">
                        <button type="button" class="btn btn-danger rounded-pill border shadow-sm px-4 fw-bold" onclick="confirmDeleteYear()">
                            <i class="fas fa-undo-alt me-2"></i> คืนค่าเริ่มต้น
                        </button>
                    </form>
                    <a href="edit_certificate_template.php" class="btn btn-light rounded-pill border shadow-sm px-4 fw-bold">
                        <i class="fas fa-chevron-left me-2"></i> กลับ
                    </a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white border-0 py-3">
                            <h5 class="fw-bold mb-0"><i class="fas fa-desktop me-2 text-warning"></i> แสดงผลแบบเรียลไทม์</h5>
                        </div>
                        <div class="card-body bg-secondary bg-opacity-10 p-4">
                            <div id="live-preview-container" class="preview-box">
                                <img id="preview-bg" src="" style="width: 100%; height: 100%; position: absolute; z-index: 1;">
                                <div id="preview-elements" style="position: absolute; width: 100%; height: 100%; z-index: 2;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-pill shadow-sm justify-content-center" id="certTab">
                        <?php foreach ($types as $key => $name): ?>
                            <li class="nav-item px-1">
                                <button class="nav-link <?= ($active_tab === $key) ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-<?= $key ?>"><?= $name ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="tab-content">
                        <?php foreach ($types as $key => $name): ?>
                            <div class="tab-pane fade <?= ($active_tab === $key) ? 'show active' : '' ?>" id="tab-<?= $key ?>">
                                <div class="card border-0 shadow-sm rounded-4 mb-4">
                                    <div class="card-body p-4 text-center">
                                        <h6 class="fw-bold mb-3 text-start"><i class="fas fa-image me-2 text-primary"></i> พื้นหลัง <?= $name ?></h6>
                                        <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                                            <input type="hidden" name="action" value="upload_bg">
                                            <input type="hidden" name="academic_year" value="<?= $selected_year ?>">
                                            <input type="hidden" name="form_type" value="<?= $key ?>">
                                            <input type="file" name="bg_image" class="form-control form-control-sm rounded-3" required>
                                            <button type="submit" class="btn btn-primary btn-sm rounded-3 px-3">อัปโหลด</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm rounded-4">
                                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold mb-0">รายการพิกัดข้อความ</h6>
                                        <button type="button" class="btn btn-success btn-sm rounded-pill px-3" onclick="openAddModal('<?= $key ?>')">เพิ่ม</button>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="list-group list-group-flush overflow-auto" style="max-height: 400px;">
                                            <?php foreach ($elements[$key] as $el): ?>
                                                <div class="list-group-item p-3 border-0 border-bottom" id="row-el-<?= $el['id'] ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <div>
                                                            <strong class="d-block text-primary"><?= htmlspecialchars($el['text_value']) ?></strong>
                                                            <span class="badge bg-light text-dark border small"><?= $el['element_key'] ?></span>
                                                        </div>
                                                        <div class="btn-group">
                                                            <button class="btn btn-outline-warning btn-sm border-0" onclick="openEditModal(<?= $el['id'] ?>)"><i class="fas fa-edit"></i></button>
                                                            <button class="btn btn-outline-danger btn-sm border-0" onclick="deleteElement(<?= $el['id'] ?>)"><i class="fas fa-trash-alt"></i></button>
                                                        </div>
                                                    </div>
                                                    <div class="row g-2 text-center small text-muted">
                                                        <div class="col-3 border-end">X: <span class="val-x"><?= $el['pos_x'] ?></span></div>
                                                        <div class="col-3 border-end">Y: <span class="val-y"><?= $el['pos_y'] ?></span></div>
                                                        <div class="col-3 border-end">S: <span class="val-s"><?= $el['font_size'] ?></span></div>
                                                        <div class="col-3">A: <span class="val-a"><?= $el['font_align'] ?></span></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="elementModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" class="modal-content border-0 shadow rounded-4">
                        <div class="modal-header bg-dark text-white rounded-top-4 py-3">
                            <h5 class="modal-title fw-bold" id="mTitle">พิกัดข้อความ</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <input type="hidden" name="action" id="mAction">
                            <input type="hidden" name="id" id="mId">
                            <input type="hidden" name="academic_year" value="<?= $selected_year ?>">
                            <input type="hidden" name="form_type" id="mType">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-12">
                                    <label class="form-label small fw-bold">ประเภทข้อความ</label>
                                    <select name="element_type" id="mElementType" class="form-select rounded-3" onchange="toggleElementType()">
                                        <option value="static">ข้อความกำหนดเอง (พิมพ์เอง)</option>
                                        <option value="dynamic">ดึงจากระบบอัตโนมัติ (Dynamic)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 mb-3" id="dynamicKeyContainer" style="display:none;">
                                <div class="col-12">
                                    <label class="form-label small fw-bold">ข้อมูลที่ต้องการดึงมาแสดง</label>
                                    <select name="element_key" id="mElementKey" class="form-select rounded-3" onchange="updateMockupText()">
                                        <option value="ref">เลขที่อ้างอิงเกียรติบัตร</option>
                                        <option value="teacher">ชื่อ-นามสกุล ผู้รับนิเทศ</option>
                                        <option value="school">ชื่อโรงเรียน</option>
                                        <option value="date">วันที่ออกเกียรติบัตร</option>
                                        <option value="semester_year">ภาคเรียน / ปีการศึกษา</option>
                                        <option value="subject">รายวิชา (สำหรับ Classroom)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label small fw-bold" id="mTextLabel">ข้อความที่แสดง / ข้อความตัวอย่าง (Mockup Text):</label>
                                <textarea name="text_value" id="mText" class="form-control rounded-3" rows="2" required></textarea>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label small fw-bold">แกน X (มิลลิเมตร)</label>
                                    <div class="input-group"><span class="input-group-text small bg-light">mm</span><input type="number" step="0.1" name="pos_x" id="mX" class="form-control" required></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">แกน Y (มิลลิเมตร)</label>
                                    <div class="input-group"><span class="input-group-text small bg-light">mm</span><input type="number" step="0.1" name="pos_y" id="mY" class="form-control" required></div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">ขนาดฟอนต์ (pt)</label>
                                    <input type="number" name="font_size" id="mSize" class="form-control" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">การจัดวางแนว</label>
                                    <select name="font_align" id="mAlign" class="form-select">
                                        <option value="L">ชิดซ้าย (Left)</option>
                                        <option value="C">กึ่งกลาง (Center)</option>
                                        <option value="R">ชิดขวา (Right)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-bold">สีข้อความ</label>
                                    <input type="color" name="color" id="mColor" class="form-control form-control-color w-100" value="#080d56" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="submit" class="btn btn-warning w-100 fw-bold py-2 rounded-3">บันทึกตำแหน่ง</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php if ($selected_year): ?>
    <script>
        const elModal = new bootstrap.Modal(document.getElementById('elementModal'));
        const elementsData = <?= json_encode($elements); ?>;
        const bgSettings = <?= json_encode($bg_settings); ?>;

        function confirmDeleteYear() {
            Swal.fire({
                title: 'ยืนยันการคืนค่าเริ่มต้น?',
                text: 'พิกัดและภาพพื้นหลังของปีการศึกษานี้จะถูกลบ และกลับไปเป็นค่าเริ่มต้น',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'ใช่, คืนค่าเลย',
                cancelButtonText: 'ยกเลิก'
            }).then(r => {
                if(r.isConfirmed) {
                    document.getElementById('deleteYearForm').submit();
                }
            });
        }

        function toggleElementType() {
            const type = document.getElementById('mElementType').value;
            const dynamicContainer = document.getElementById('dynamicKeyContainer');
            if (type === 'dynamic') {
                dynamicContainer.style.display = 'block';
                updateMockupText();
            } else {
                dynamicContainer.style.display = 'none';
            }
        }

        function updateMockupText() {
            if (document.getElementById('mElementType').value !== 'dynamic') return;
            const key = document.getElementById('mElementKey').value;
            const mText = document.getElementById('mText');
            const selectedYear = document.querySelector('input[name="academic_year"]').value;
            const type = document.getElementById('mType').value;
            
            const toThaiNum = (str) => str.toString().replace(/\d/g, d => '๐๑๒๓๔๕๖๗๘๙'[d]);
            const thYear = toThaiNum(selectedYear);
            
            switch(key) {
                case 'ref': 
                    mText.value = (type === 'quickwin') ? 'QW-' + thYear + '/๐๘๕' : 'ศธ ๐๔๒๔๔/ว ' + thYear + '-๐๐๑'; 
                    break;
                case 'teacher': 
                    mText.value = (type === 'quickwin') ? 'นางสาวสมศรี เรียนดี' : 'นายสมชาย ใจดี'; 
                    break;
                case 'school': 
                    mText.value = (type === 'quickwin') ? 'โรงเรียนลำปางกัลยาณี' : 'โรงเรียนบุญวาทย์วิทยาลัย'; 
                    break;
                case 'date': 
                    mText.value = (type === 'quickwin') ? 'ให้ไว้ ณ วันที่ ๑๔ เดือน กุมภาพันธ์ พ.ศ. ' + thYear : 'ให้ไว้ ณ วันที่ ๑ เดือน มกราคม พ.ศ. ' + thYear; 
                    break;
                case 'semester_year': mText.value = 'ภาคเรียนที่ ๑ ปีการศึกษา ' + thYear + ' ประจำปีงบประมาณ ' + thYear; break;
                case 'subject': mText.value = 'รายวิชาคณิตศาสตร์'; break;
            }
        }

        function openAddModal(type) {
            const targetType = type || window.currentFormType || 'classroom';
            document.getElementById('mTitle').innerText = 'เพิ่มข้อความใหม่';
            document.getElementById('mAction').value = 'add_element';
            document.getElementById('mType').value = targetType;
            document.getElementById('mId').value = '';
            
            document.getElementById('mElementType').value = 'static';
            document.getElementById('dynamicKeyContainer').style.display = 'none';
            document.getElementById('mElementKey').value = 'ref';
            
            document.getElementById('mText').value = 'ตัวอย่างข้อความใหม่';
            document.getElementById('mX').value = 50;
            document.getElementById('mY').value = 50;
            document.getElementById('mSize').value = 24;
            document.getElementById('mAlign').value = 'C';
            document.getElementById('mColor').value = '#080d56';
            elModal.show();
        }

        function openEditModal(id) {
            const type = window.currentFormType || 'classroom';
            let el = elementsData['classroom']?.find(e => e.id == id);
            if (!el) el = elementsData['quickwin']?.find(e => e.id == id);
            if (!el) return;
            
            document.getElementById('mTitle').innerText = 'แก้ไขตำแหน่ง: ' + el.element_key;
            document.getElementById('mAction').value = 'edit_element';
            document.getElementById('mId').value = el.id;
            document.getElementById('mType').value = el.form_type || type;
            
            document.getElementById('mElementType').value = el.element_type || 'static';
            if (el.element_type === 'dynamic') {
                document.getElementById('dynamicKeyContainer').style.display = 'block';
                document.getElementById('mElementKey').value = el.element_key;
            } else {
                document.getElementById('dynamicKeyContainer').style.display = 'none';
            }
            
            document.getElementById('mText').value = el.text_value;
            document.getElementById('mX').value = el.pos_x;
            document.getElementById('mY').value = el.pos_y;
            document.getElementById('mSize').value = el.font_size;
            document.getElementById('mAlign').value = el.font_align;
            document.getElementById('mColor').value = el.color || '#080d56';
            elModal.show();
        }

        function deleteElement(id) {
            Swal.fire({ title: 'ยืนยันการลบ?', text: 'คุณจะไม่สามารถกู้คืนข้อมูลพิกัดนี้ได้', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'ลบทิ้ง' }).then(r => {
                if(r.isConfirmed) {
                    const fd = new FormData(); fd.append('action', 'delete_element'); fd.append('id', id);
                    fetch(window.location.href, { method: 'POST', body: fd }).then(res => res.json()).then(d => { if(d.success) location.reload(); });
                }
            });
        }

        window.currentFormType = 'classroom';

        function updatePreview(type) {
            window.currentFormType = type;
            const bg = bgSettings[type] ? 'uploads/certificates/' + bgSettings[type] : 'images/' + (type === 'classroom' ? 'ctest.png' : 'qw_cer.png');
            document.getElementById('preview-bg').src = bg;
            const container = document.getElementById('preview-elements');
            container.innerHTML = '';
            if(elementsData[type]) {
                elementsData[type].forEach(el => {
                    const div = document.createElement('div');
                    div.className = 'preview-el';
                    div.dataset.id = el.id;
                    div.dataset.align = el.font_align;
                    div.title = 'คลิกค้างเพื่อลาก หรือดับเบิ้ลคลิกเพื่อแก้ไข';
                    
                    div.style.top = (el.pos_y / 210 * 100) + '%';
                    
                    // สำหรับการจัดกึ่งกลางหรือชิดขวา
                    if(el.font_align === 'C') {
                        const center_x = parseFloat(el.pos_x) + (297 - parseFloat(el.pos_x)) / 2;
                        div.style.left = (center_x / 297 * 100) + '%';
                        div.style.transform = 'translateX(-50%)';
                        div.style.textAlign = 'center';
                    } else if(el.font_align === 'R') {
                        div.style.right = '0';
                        div.style.textAlign = 'right';
                    } else {
                        div.style.textAlign = 'left';
                        div.style.left = (el.pos_x / 297 * 100) + '%';
                    }

                    // คำนวณขนาดตัวอักษรตามสัดส่วนหน้าจอจริง
                    const fs = (el.font_size * 0.352778 / 210 * 100);
                    div.style.fontSize = `calc(var(--p-h, 210px) * ${fs/100})`;
                    div.style.color = el.color || '#080d56';
                    div.innerText = el.text_value;
                    
                    div.addEventListener('mousedown', startDrag);
                    div.addEventListener('dblclick', () => openEditModal(el.id));

                    container.appendChild(div);
                });
            }
        }

        let activeDragEl = null;
        let startX, startY;
        let initialPosX, initialPosY;

        function startDrag(e) {
            if (e.target.classList.contains('preview-el')) {
                activeDragEl = e.target;
                
                const id = activeDragEl.dataset.id;
                const type = window.currentFormType;
                const elData = elementsData[type].find(item => item.id == id);
                
                initialPosX = parseFloat(elData.pos_x);
                initialPosY = parseFloat(elData.pos_y);
                startX = e.clientX;
                startY = e.clientY;
                
                document.addEventListener('mousemove', drag);
                document.addEventListener('mouseup', stopDrag);
                activeDragEl.style.outline = '2px solid #dc3545';
                activeDragEl.style.background = 'rgba(220, 53, 69, 0.15)';
                activeDragEl.style.zIndex = '20';
            }
        }

        function drag(e) {
            if (!activeDragEl) return;
            e.preventDefault();
            
            const container = document.getElementById('live-preview-container');
            const rect = container.getBoundingClientRect();
            
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            
            const dx_mm = (dx / rect.width) * 297;
            const dy_mm = (dy / rect.height) * 210;
            
            let newX = initialPosX + dx_mm;
            let newY = initialPosY + dy_mm;
            
            if (newX < 0) newX = 0;
            if (newX > 297) newX = 297;
            if (newY < 0) newY = 0;
            if (newY > 210) newY = 210;
            
            activeDragEl.dataset.newX = newX.toFixed(1);
            activeDragEl.dataset.newY = newY.toFixed(1);
            
            const align = activeDragEl.dataset.align;
            activeDragEl.style.top = (newY / 210 * 100) + '%';
            
            if(align === 'C') {
                const center_x = newX + (297 - newX) / 2;
                activeDragEl.style.left = (center_x / 297 * 100) + '%';
            } else if(align === 'R') {
                // Keep visually right aligned
            } else {
                activeDragEl.style.left = (newX / 297 * 100) + '%';
            }
        }

        function stopDrag(e) {
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('mouseup', stopDrag);
            
            if (activeDragEl) {
                activeDragEl.style.outline = '';
                activeDragEl.style.background = '';
                activeDragEl.style.zIndex = '';
                
                const id = activeDragEl.dataset.id;
                const newX = activeDragEl.dataset.newX;
                const newY = activeDragEl.dataset.newY;
                
                if (newX !== undefined && newY !== undefined) {
                    const type = window.currentFormType;
                    const elData = elementsData[type].find(item => item.id == id);
                    
                    if (elData.pos_x != newX || elData.pos_y != newY) {
                        elData.pos_x = newX;
                        elData.pos_y = newY;
                        
                        const row = document.getElementById('row-el-' + id);
                        if (row) {
                            row.querySelector('.val-x').innerText = newX;
                            row.querySelector('.val-y').innerText = newY;
                        }
                        
                        const fd = new FormData();
                        fd.append('action', 'update_position');
                        fd.append('id', id);
                        fd.append('pos_x', newX);
                        fd.append('pos_y', newY);
                        
                        fetch(window.location.href, {
                            method: 'POST',
                            body: fd
                        });
                    }
                }
                
                activeDragEl = null;
            }
        }

        const pBox = document.getElementById('live-preview-container');
        new ResizeObserver(e => pBox.style.setProperty('--p-h', e[0].contentRect.height + 'px')).observe(pBox);

        document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(t => {
            t.addEventListener('shown.bs.tab', e => {
                const tab = e.target.getAttribute('data-bs-target').split('-')[1];
                updatePreview(tab);
                const url = new URL(window.location);
                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url);
            });
        });
        window.onload = () => updatePreview('<?= $active_tab ?>');
    </script>
    <?php endif; ?>
</body>
</html>