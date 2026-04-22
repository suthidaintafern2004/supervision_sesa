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

// ตรวจสอบและเพิ่มคอลัมน์ display_order หากยังไม่มี
try {
    $conn->query("SELECT display_order FROM quickwin_options LIMIT 1");
} catch (PDOException $e) {
    // ถ้าไม่มีคอลัมน์ ให้สร้างใหม่และรันตัวเลขเริ่มต้น
    $conn->exec("ALTER TABLE quickwin_options ADD COLUMN display_order INT NOT NULL DEFAULT 0");
    $conn->exec("SET @order = 0");
    $conn->exec("UPDATE quickwin_options SET display_order = (@order := @order + 1) ORDER BY OptionID ASC");
}

// Handle CRUD operations (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    try {
        if ($action === 'add') {
            $text = trim($_POST['option_text'] ?? '');
            if ($text === '') throw new Exception('กรุณากรอกหัวข้อ');
            
            // รันตัวเลขข้ออัตโนมัติต่อจากข้อล่าสุด
            $stmt = $conn->query("SELECT MAX(OptionID) FROM quickwin_options");
            $maxId = (int)$stmt->fetchColumn();
            $nextId = $maxId + 1;
            
            $stmt = $conn->query("SELECT MAX(display_order) FROM quickwin_options");
            $maxOrder = (int)$stmt->fetchColumn();
            $nextOrder = $maxOrder + 1;
            
            $stmt = $conn->prepare("INSERT INTO quickwin_options (OptionID, OptionText, display_order) VALUES (?, ?, ?)");
            $stmt->execute([$nextId, $text, $nextOrder]);
            
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'edit') {
            $id = (int)$_POST['option_id'];
            $text = trim($_POST['option_text'] ?? '');
            if ($text === '') throw new Exception('กรุณากรอกหัวข้อ');
            
            $stmt = $conn->prepare("UPDATE quickwin_options SET OptionText = ? WHERE OptionID = ?");
            $stmt->execute([$text, $id]);
            
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'delete') {
            $id = (int)$_POST['option_id'];
            
            $stmt = $conn->prepare("DELETE FROM quickwin_options WHERE OptionID = ?");
            $stmt->execute([$id]);
            
            // รันลำดับ display_order ใหม่หลังจากลบ เพื่อไม่ให้ตัวเลขฟันหลอ
            $conn->exec("SET @order = 0");
            $conn->exec("UPDATE quickwin_options SET display_order = (@order := @order + 1) ORDER BY display_order ASC");
            
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'reorder') {
            $order = $_POST['order'] ?? [];
            foreach ($order as $index => $id) {
                $stmt = $conn->prepare("UPDATE quickwin_options SET display_order = ? WHERE OptionID = ?");
                $stmt->execute([$index + 1, (int)$id]);
            }
            echo json_encode(['success' => true]);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Fetch Options
$options = $conn->query("SELECT * FROM quickwin_options ORDER BY display_order ASC, OptionID ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>จัดการหัวข้อ Quick Win</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <style>
        .handle {
            cursor: grab;
        }
        .handle:active {
            cursor: grabbing;
        }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-0 fw-bold text-success">
                        <i class="fas fa-list-check me-2"></i> จัดการหัวข้อการประเมินจุดเน้น (Quick Win)
                    </h5>
                    <small class="text-muted mt-1 d-block">💡 ลากที่ไอคอน <i class="fas fa-grip-vertical mx-1"></i> เพื่อจัดเรียงลำดับใหม่</small>
                </div>
                <button class="btn btn-success" onclick="openAddModal()">
                    <i class="fas fa-plus-circle me-1"></i> เพิ่มหัวข้อ
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 5%;"></th>
                                <th class="text-center" style="width: 10%;">ลำดับที่</th>
                                <th style="width: 70%;">หัวข้อการนิเทศ</th>
                                <th class="text-center" style="width: 15%;">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="sortableTable">
                            <?php if (empty($options)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-danger py-4">ไม่มีข้อมูลหัวข้อ</td>
                                </tr>
                            <?php else: ?>
                                <?php $i = 1; foreach ($options as $opt): ?>
                                    <tr data-id="<?= $opt['OptionID'] ?>">
                                        <td class="text-center text-muted handle"><i class="fas fa-grip-vertical fs-5"></i></td>
                                        <td class="text-center fw-bold text-secondary row-number"><?= $i++ ?></td>
                                        <td><?= htmlspecialchars($opt['OptionText']) ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-warning btn-sm text-white" onclick="openEditModal(<?= $opt['OptionID'] ?>, '<?= htmlspecialchars(addslashes($opt['OptionText'])) ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteOption(<?= $opt['OptionID'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal เพิ่ม/แก้ไข -->
    <div class="modal fade" id="optionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalTitle">เพิ่มหัวข้อ Quick Win</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="optionForm" onsubmit="return false;">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="add">
                        <input type="hidden" name="option_id" id="optionId">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">หัวข้อการนิเทศ <span class="text-danger">*</span></label>
                            <textarea name="option_text" id="optionText" class="form-control" rows="4" required placeholder="ระบุหัวข้อการนิเทศ..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="button" class="btn btn-success" onclick="saveOption()">บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const optionModal = new bootstrap.Modal(document.getElementById('optionModal'));
        const form = document.getElementById('optionForm');

        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'เพิ่มหัวข้อ Quick Win';
            document.getElementById('formAction').value = 'add';
            document.getElementById('optionId').value = '';
            document.getElementById('optionText').value = '';
            
            document.querySelector('#optionModal .modal-header').className = 'modal-header bg-success text-white';
            document.querySelector('#optionModal .modal-footer .btn-success').className = 'btn btn-success';
            
            optionModal.show();
        }

        function openEditModal(id, text) {
            document.getElementById('modalTitle').innerText = 'แก้ไขหัวข้อ Quick Win';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('optionId').value = id;
            document.getElementById('optionText').value = text;
            
            document.querySelector('#optionModal .modal-header').className = 'modal-header bg-warning text-dark';
            document.querySelector('#optionModal .modal-footer .btn-success').className = 'btn btn-warning text-dark';
            
            optionModal.show();
        }

        function saveOption() {
            const text = document.getElementById('optionText').value.trim();
            if (!text) {
                Swal.fire('แจ้งเตือน', 'กรุณากรอกหัวข้อการนิเทศ', 'warning');
                return;
            }

            const formData = new FormData(form);

            fetch('edit_quickwin_options.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: 'บันทึกข้อมูลเรียบร้อยแล้ว',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('ผิดพลาด', data.message || 'เกิดข้อผิดพลาด', 'error');
                }
            })
            .catch(err => {
                Swal.fire('ผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            });
        }

        function deleteOption(id) {
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "คุณต้องการลบหัวข้อนี้ใช่หรือไม่?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ใช่, ลบเลย!',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#dc3545'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('option_id', id);

                    fetch('edit_quickwin_options.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'ลบสำเร็จ',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('ผิดพลาด', data.message || 'เกิดข้อผิดพลาด', 'error');
                        }
                    });
                }
            });
        }

        // เปิดใช้งาน Drag & Drop สำหรับจัดเรียงลำดับ
        const tbody = document.getElementById('sortableTable');
        if (tbody) {
            new Sortable(tbody, {
                handle: '.handle',
                animation: 150,
                onEnd: function () {
                    const rows = document.querySelectorAll('#sortableTable tr');
                    const order = [];
                    rows.forEach((row, index) => {
                        if (row.dataset.id) {
                            order.push(row.dataset.id);
                            // อัปเดตตัวเลขลำดับที่หน้าเว็บทันที
                            row.querySelector('.row-number').innerText = index + 1;
                        }
                    });

                    const formData = new FormData();
                    formData.append('action', 'reorder');
                    order.forEach(id => formData.append('order[]', id));

                    fetch('edit_quickwin_options.php', {
                        method: 'POST',
                        body: formData
                    }).catch(err => console.error('Reorder error:', err));
                }
            });
        }
    </script>
</body>
</html>