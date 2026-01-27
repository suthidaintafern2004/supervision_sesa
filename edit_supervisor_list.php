<?php
session_start();
require_once 'config/db_connect.php';

/* =======================
   ตรวจสิทธิ์ Admin
======================= */
if (
    !isset($_SESSION['is_logged_in']) ||
    $_SESSION['role'] !== 'admin'
) {
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
    Swal.fire({
        icon: 'warning',
        title: 'ไม่มีสิทธิ์เข้าใช้งาน',
        html: 'ระบบนี้เป็นสิทธิ์ของแอดมินเท่านั้น<br>กรุณาติดต่อผู้ดูแลระบบ',
        confirmButtonText: 'ตกลง',
        allowOutsideClick: false
    }).then(() => {
        window.location.href = 'index.php';
    });
    </script>";
    exit;
}

/* =======================
   โหลด dropdown
======================= */
try {
    $prefixes  = $conn->query("SELECT prefix_id, prefix_name FROM prefix ORDER BY prefix_name")->fetchAll(PDO::FETCH_ASSOC);
    $offices   = $conn->query("SELECT office_id, office_name FROM office ORDER BY office_name")->fetchAll(PDO::FETCH_ASSOC);
    $positions = $conn->query("SELECT position_id, position_name FROM position ORDER BY position_name")->fetchAll(PDO::FETCH_ASSOC);
    $ranks     = $conn->query("SELECT rank_id, rank_name FROM ranks ORDER BY rank_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>จัดการข้อมูลผู้นิเทศ</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/supervisor.css" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

    <div class="container py-4">
        <div class="card shadow-sm supervisor-card">

            <!-- HEADER -->
            <div class="card-header supervisor-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold page-title-lg">
                        <i class="fas fa-user-tie me-2"></i>
                        จัดการข้อมูลผู้นิเทศก์
                    </h5>

                    <div class="d-flex gap-2">
                        <button class="btn btn-add-lg" onclick="openAddModal()">
                            <i class="fas fa-plus-circle me-2"></i> เพิ่มข้อมูล
                        </button>

                        <a href="index.php" class="btn btn-danger btn-lg fs-6">
                            <i class="fas fa-arrow-left me-2"></i> ย้อนกลับ
                        </a>
                    </div>
                </div>
            </div>

            <!-- BODY -->
            <div class="card-body">

                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ชื่อ - สกุล</th>
                            <th>สำนักงาน</th>
                            <th>ตำแหน่ง</th>
                            <th>วิทยฐานะ</th>
                            <th class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>

            </div>
        </div>
    </div>

    <!-- ================= MODAL ADD ================= -->
    <div class="modal fade" id="addModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">เพิ่มข้อมูลผู้นิเทศก์</h5>
                    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form id="addForm" onsubmit="return false;">
                    <div class="modal-body row g-3">

                        <div class="col-md-12">
                            <label>รหัสบัตรประชาชน</label>
                            <input type="text" name="p_id" class="form-control" maxlength="13" required>
                        </div>

                        <div class="col-md-3">
                            <label>คำนำหน้า</label>
                            <select name="prefix_id" class="form-select" required>
                                <option value="">-- เลือก --</option>
                                <?php foreach ($prefixes as $r): ?>
                                    <option value="<?= $r['prefix_id'] ?>"><?= htmlspecialchars($r['prefix_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>ชื่อ</label>
                            <input type="text" name="fname" class="form-control" required>
                        </div>

                        <div class="col-md-5">
                            <label>นามสกุล</label>
                            <input type="text" name="lname" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label>สำนักงาน</label>
                            <select name="office_id" class="form-select" required>
                                <?php foreach ($offices as $o): ?>
                                    <option value="<?= $o['office_id'] ?>"><?= htmlspecialchars($o['office_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label>ตำแหน่ง</label>
                            <select name="position_id" class="form-select" required>
                                <option value="position_id" selected disabled hidden>
                                    กรุณาเลือกตำแหน่ง
                                </option>
                                <?php foreach ($positions as $p): ?>
                                    <option value="<?= $p['position_id'] ?>"><?= htmlspecialchars($p['position_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label>วิทยฐานะ</label>
                            <select name="rank_id" class="form-select">
                                <option value="" selected disabled hidden>
                                    กรุณาเลือกวิทยฐานะ
                                </option>
                                <?php foreach ($ranks as $r): ?>
                                    <option value="<?= $r['rank_id'] ?>"><?= htmlspecialchars($r['rank_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label>สิทธิ์ผู้ใช้งาน</label>
                            <select name="role" class="form-select" required>
                                <option value="role" selected disabled hidden>
                                    กรุณาเลือกสิทธิ์ผู้ใช้งาน
                                </option>
                                <option value="supervisor">ผู้นิเทศก์</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" onclick="submitAdd()">บันทึก</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- ================= MODAL EDIT ================= -->
    <div class="modal fade" id="editModal">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-1"></i> แก้ไขข้อมูลผู้นิเทศก์
                    </h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form id="editForm" onsubmit="return false;">
                    <div class="modal-body row g-3">

                        <input type="hidden" name="p_id" id="edit_p_id">

                        <div class="col-md-3">
                            <label>คำนำหน้า</label>
                            <select name="prefix_id" id="edit_prefix_id" class="form-select" required>
                                <?php foreach ($prefixes as $r): ?>
                                    <option value="<?= $r['prefix_id'] ?>">
                                        <?= htmlspecialchars($r['prefix_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>ชื่อ</label>
                            <input type="text" name="fname" id="edit_fname" class="form-control" required>
                        </div>

                        <div class="col-md-5">
                            <label>นามสกุล</label>
                            <input type="text" name="lname" id="edit_lname" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label>สำนักงาน</label>
                            <select name="office_id" id="edit_office_id" class="form-select" required>
                                <?php foreach ($offices as $o): ?>
                                    <option value="<?= $o['office_id'] ?>">
                                        <?= htmlspecialchars($o['office_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label>ตำแหน่ง</label>
                            <select name="position_id" id="edit_position_id" class="form-select" required>
                                <?php foreach ($positions as $p): ?>
                                    <option value="<?= $p['position_id'] ?>">
                                        <?= htmlspecialchars($p['position_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label>วิทยฐานะ</label>
                            <select name="rank_id" id="edit_rank_id" class="form-select">
                                <option value="">กรุณาเลือกวิทยฐานะ</option>
                                <?php foreach ($ranks as $r): ?>
                                    <option value="<?= $r['rank_id'] ?>">
                                        <?= htmlspecialchars($r['rank_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label>สิทธิ์ผู้ใช้งาน</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <option value="supervisor">ผู้นิเทศก์</option>
                                <option value="admin">ผู้ดูแลระบบ</option>
                            </select>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-warning" onclick="submitEdit()">
                            บันทึกการแก้ไข
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>


    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let addModalEl = document.getElementById('addModal');

        function loadData() {
            fetch(`api/get_supervisors.php`)
                .then(r => r.json())
                .then(res => {
                    tableBody.innerHTML = '';

                    res.data.forEach(s => {
                        tableBody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td>${s.prefix_name || ''} ${s.fname} ${s.lname}</td>
                    <td>${s.office_name || '-'}</td>
                    <td>${s.position_name || '-'}</td>
                    <td>${s.rank_name || '-'}</td>
                    <td class="text-center">
                        <button class="btn btn-warning btn-sm" onclick="openEdit('${s.p_id}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="del('${s.p_id}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                `);
                    });

                    pageNum.innerText = page;
                    prevBtn.disabled = page === 1;
                    nextBtn.disabled = res.data.length < limit;
                });
        }

        loadData();

        function nextPage() {
            page++;
            loadData();
        }

        function prevPage() {
            if (page > 1) {
                page--;
                loadData();
            }
        }

        function openAddModal() {
            new bootstrap.Modal(addModalEl).show();
        }

        addModalEl.addEventListener('hidden.bs.modal', () => {
            addForm.reset();
        });

        function submitAdd() {

            const prefix = addForm.prefix_id.value;
            const fname = addForm.fname.value.trim();
            const lname = addForm.lname.value.trim();
            const office = addForm.office_id.value;
            const position = addForm.position_id.value;
            const rank = addForm.rank_id.value;
            const role = addForm.role.value;

            if (
                prefix === '' ||
                fname === '' ||
                lname === '' ||
                office === '' ||
                position === '' ||
                rank === '' ||
                role === ''
            ) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบ',
                    text: 'กรุณาเลือกและกรอกข้อมูลให้ครบถ้วน',
                    confirmButtonText: 'ตกลง'
                });
                return; // ❌ หยุด ไม่ส่ง fetch
            }

            fetch('api/add_supervisor.php', {
                    method: 'POST',
                    body: new FormData(addForm)
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire('สำเร็จ', 'เพิ่มข้อมูลแล้ว', 'success');
                        bootstrap.Modal.getInstance(addModalEl).hide();
                        loadData();
                    } else {
                        Swal.fire('ผิดพลาด', d.message, 'error');
                    }
                });
        }
        const editModalEl = document.getElementById('editModal');
        let editModalInstance = null;

        function openEdit(id) {
            fetch(`api/get_supervisor_details.php?p_id=${id}`)
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        Swal.fire('ผิดพลาด', d.message, 'error');
                        return;
                    }

                    const s = d.data;

                    edit_p_id.value = s.p_id;
                    edit_prefix_id.value = s.prefix_id;
                    edit_fname.value = s.fname;
                    edit_lname.value = s.lname;
                    edit_office_id.value = s.office_id;
                    edit_position_id.value = s.position_id;
                    edit_rank_id.value = s.rank_id ?? '';
                    edit_role.value = s.role;

                    editModalInstance = new bootstrap.Modal(editModalEl);
                    editModalInstance.show();
                });
        }

        function submitEdit() {

            const prefix = editForm.prefix_id.value;
            const fname = editForm.fname.value.trim();
            const lname = editForm.lname.value.trim();
            const office = editForm.office_id.value;
            const position = editForm.position_id.value;
            const rank = editForm.rank_id.value;
            const role = editForm.role.value;

            if (
                prefix === '' ||
                fname === '' ||
                lname === '' ||
                office === '' ||
                position === '' ||
                rank === '' ||
                role === ''
            ) {
                Swal.fire({
                    icon: 'warning',
                    title: 'ข้อมูลไม่ครบ',
                    text: 'กรุณาเลือกและกรอกข้อมูลให้ครบถ้วน',
                    confirmButtonText: 'ตกลง'
                });
                return;
            }

            fetch('api/update_supervisor.php', {
                    method: 'POST',
                    body: new FormData(editForm)
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        Swal.fire('สำเร็จ', 'บันทึกข้อมูลแล้ว', 'success');
                        editModalInstance.hide();
                        loadData();
                    } else {
                        Swal.fire('ผิดพลาด', d.message, 'error');
                    }
                });
        }

        function del(id) {
            Swal.fire({
                title: 'ยืนยันการลบ',
                text: 'ต้องการลบผู้นิเทศรายนี้หรือไม่',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ลบ',
                cancelButtonText: 'ยกเลิก'
            }).then(res => {
                if (!res.isConfirmed) return;

                const fd = new FormData();
                fd.append('p_id', id);

                fetch('api/delete_supervisor.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            Swal.fire('ลบสำเร็จ', '', 'success');
                            loadData();
                        } else {
                            Swal.fire('ผิดพลาด', d.message, 'error');
                        }
                    });
            });
        }
    </script>


</body>

</html>