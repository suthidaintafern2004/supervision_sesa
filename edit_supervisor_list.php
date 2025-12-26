<?php
session_start();
require_once 'config/db_connect.php';

// โหลดตัวเลือกสำหรับ Modal (PHP)
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/supervisor.css">
</head>

<body>

    <div class="container py-4">
        <div class="card card-custom p-4">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="page-title">
                    <i class="fas fa-user-tie"></i>
                    <div>
                        <div style="font-size:18px;">จัดการข้อมูลผู้นิเทศ</div>
                        <div style="font-size:13px; color:#6c757d;">รายการผู้นิเทศในระบบ</div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-gradient" onclick="openAddModal()">
                        <i class="fas fa-plus-circle me-2"></i> เพิ่มข้อมูล
                    </button>
                    <a href="index.php" class="btn btn-danger">
                        <i class="fas fa-arrow-left me-1"></i> กลับ
                    </a>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-8 position-relative">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="ค้นหาชื่อ, นามสกุล หรือหน่วยงาน...">
                </div>
            </div>

            <div class="table-container" id="scrollContainer">
                <table class="table table-hover align-middle mb-0">
                    <thead>
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
                <div id="loading"><i class="fas fa-spinner fa-spin"></i> กำลังโหลด...</div>
                <div id="noMoreData" class="text-center p-3 text-muted" style="display:none;">-- สิ้นสุดข้อมูล --</div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">เพิ่มผู้นิเทศ</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addForm">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12"><label>รหัสบัตรประชาชน (13 หลัก)</label><input type="text" name="p_id" class="form-control" maxlength="13" required></div>
                            <div class="col-md-3"><label>คำนำหน้า</label><select name="prefix_id" class="form-select">
                                    <option value="">--</option><?php foreach ($prefixes as $r) echo "<option value='{$r['prefix_id']}'>{$r['prefix_name']}</option>"; ?>
                                </select></div>
                            <div class="col-md-4"><label>ชื่อ</label><input type="text" name="fname" class="form-control" required></div>
                            <div class="col-md-5"><label>นามสกุล</label><input type="text" name="lname" class="form-control" required></div>
                            <div class="col-md-12"><label>สำนักงาน</label><select name="office_id" class="form-select">
                                    <option value="">--</option><?php foreach ($offices as $r) echo "<option value='{$r['office_id']}'>{$r['office_name']}</option>"; ?>
                                </select></div>
                            <div class="col-md-6"><label>ตำแหน่ง</label><select name="position_id" class="form-select">
                                    <option value="">--</option><?php foreach ($positions as $r) echo "<option value='{$r['position_id']}'>{$r['position_name']}</option>"; ?>
                                </select></div>
                            <div class="col-md-6"><label>วิทยฐานะ</label><select name="rank_id" class="form-select">
                                    <option value="">--</option><?php foreach ($ranks as $r) echo "<option value='{$r['rank_id']}'>{$r['rank_name']}</option>"; ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-success" onclick="submitAdd()">บันทึก</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">แก้ไขข้อมูลผู้นิเทศ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="p_id" id="edit_p_id">
                        <div class="row g-3">
                            <div class="col-md-3"><label>คำนำหน้า</label><select name="prefix_id" id="edit_prefix_id" class="form-select">
                                    <option value="">--</option><?php foreach ($prefixes as $r) echo "<option value='{$r['prefix_id']}'>{$r['prefix_name']}</option>"; ?>
                                </select></div>
                            <div class="col-md-4"><label>ชื่อ</label><input type="text" name="fname" id="edit_fname" class="form-control"></div>
                            <div class="col-md-5"><label>นามสกุล</label><input type="text" name="lname" id="edit_lname" class="form-control"></div>
                            <div class="col-md-12"><label>สำนักงาน</label><select name="office_id" id="edit_office_id" class="form-select"><?php foreach ($offices as $r) echo "<option value='{$r['office_id']}'>{$r['office_name']}</option>"; ?></select></div>
                            <div class="col-md-6"><label>ตำแหน่ง</label><select name="position_id" id="edit_position_id" class="form-select"><?php foreach ($positions as $r) echo "<option value='{$r['position_id']}'>{$r['position_name']}</option>"; ?></select></div>
                            <div class="col-md-6"><label>วิทยฐานะ</label><select name="rank_id" id="edit_rank_id" class="form-select">
                                    <option value="">--</option><?php foreach ($ranks as $r) echo "<option value='{$r['rank_id']}'>{$r['rank_name']}</option>"; ?>
                                </select></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-success" onclick="submitEdit()">บันทึกการแก้ไข</button></div>
                </form>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let offset = 0;
        let isLoading = false;
        let hasMore = true;
        let searchTimeout;

        // 1. Load Data (Search & Infinite Scroll)
        function loadData(reset = false) {
            if (isLoading) return;
            if (reset) {
                offset = 0;
                hasMore = true;
                document.getElementById("tableBody").innerHTML = "";
                document.getElementById("noMoreData").style.display = "none";
            }
            if (!hasMore) return;

            isLoading = true;
            document.getElementById("loading").style.display = "block";
            const search = document.getElementById("searchInput").value;

            // ตรวจสอบว่าไฟล์ api/get_supervisors.php มีอยู่จริง
            fetch(`api/get_supervisors.php?search=${encodeURIComponent(search)}&offset=${offset}`)
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        if (res.data.length > 0) {
                            let html = "";
                            res.data.forEach(s => {
                                html += `<tr>
                            <td>${s.prefix_name||''} ${s.fname} ${s.lname}</td>
                            <td>${s.office_name||'-'}</td>
                            <td>${s.position_name||'-'}</td>
                            <td>${s.rank_name||'-'}</td>
                            <td class="text-center">
                                <button class="btn btn-edit btn-sm me-1" onclick="openEdit('${s.p_id}')"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="del('${s.p_id}')"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>`;
                            });
                            document.getElementById("tableBody").insertAdjacentHTML('beforeend', html);
                            offset += res.data.length;
                        } else {
                            hasMore = false;
                            if (offset > 0) document.getElementById("noMoreData").style.display = "block";
                            else document.getElementById("tableBody").innerHTML = "<tr><td colspan='5' class='text-center py-3 text-muted'>ไม่พบข้อมูล</td></tr>";
                        }
                    }
                })
                .catch(err => console.error("Load Data Error:", err))
                .finally(() => {
                    isLoading = false;
                    document.getElementById("loading").style.display = "none";
                });
        }

        // 2. Events
        document.getElementById("searchInput").addEventListener("input", () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadData(true), 500);
        });
        document.getElementById("scrollContainer").addEventListener("scroll", function() {
            if (this.scrollTop + this.clientHeight >= this.scrollHeight - 50) loadData();
        });
        loadData(true);

        // 3. Actions - Add
        function openAddModal() {
            new bootstrap.Modal(document.getElementById('addModal')).show();
        }

        function submitAdd() {
            const form = document.getElementById('addForm');
            if (!form.checkValidity()) {
                alert("กรุณากรอกข้อมูลให้ครบ");
                return;
            }

            fetch('api/add_supervisor.php', {
                    method: 'POST',
                    body: new FormData(form)
                })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        alert("เพิ่มสำเร็จ");
                        location.reload();
                    } else alert(d.message);
                });
        }

        // 4. Actions - Edit (Fixed & Robust)
        var editModalInstance = null;

        function openEdit(id) {
            if (!id) {
                alert("ไม่พบรหัสผู้นิเทศ");
                return;
            }

            // [จุดที่แก้] เปลี่ยนชื่อไฟล์เป็น get_supervisor_details.php
            fetch(`api/get_supervisor_details.php?p_id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("ติดต่อ Server ไม่ได้ (HTTP " + response.status + ")");
                    }
                    return response.json();
                })
                .then(d => {
                    if (d.success) {
                        const s = d.data;

                        document.getElementById('edit_p_id').value = s.p_id || '';
                        document.getElementById('edit_prefix_id').value = s.prefix_id || '';
                        document.getElementById('edit_fname').value = s.fname || '';
                        document.getElementById('edit_lname').value = s.lname || '';
                        document.getElementById('edit_office_id').value = s.office_id || '';
                        document.getElementById('edit_position_id').value = s.position_id || '';
                        document.getElementById('edit_rank_id').value = s.rank_id || '';

                        var modalEl = document.getElementById('editModal');
                        if (!editModalInstance) {
                            editModalInstance = new bootstrap.Modal(modalEl);
                        }
                        editModalInstance.show();

                    } else {
                        alert("ไม่พบข้อมูล: " + d.message);
                    }
                })
                .catch(err => {
                    console.error("Open Edit Error:", err);
                    alert("เกิดข้อผิดพลาด: " + err.message);
                });
        }

        function submitEdit() {
            const form = document.getElementById('editForm');
            if (!form.checkValidity()) {
                alert("กรุณากรอกข้อมูลให้ครบถ้วน");
                return;
            }

            fetch('api/update_supervisor.php', {
                    method: 'POST',
                    body: new FormData(form)
                })
                .then(response => response.json())
                .then(d => {
                    if (d.success) {
                        alert("บันทึกการแก้ไขสำเร็จ");
                        loadData(true);

                        if (editModalInstance) {
                            editModalInstance.hide();
                        } else {
                            var modalEl = document.getElementById('editModal');
                            var modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }
                    } else {
                        alert("บันทึกไม่สำเร็จ: " + d.message);
                    }
                })
                .catch(err => {
                    console.error("Submit Edit Error:", err);
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อ Server");
                });
        }

        // 5. Actions - Delete
        function del(id) {
            if (confirm("ยืนยันการลบ?")) {
                let fd = new FormData();
                fd.append('p_id', id);
                fetch('api/delete_supervisor.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json()).then(d => {
                        if (d.success) {
                            alert("ลบสำเร็จ");
                            loadData(true);
                        } else alert(d.message);
                    });
            }
        }
    </script>
</body>

</html>