<?php
session_start();
require_once 'config/db_connect.php';

// โหลดตัวเลือกสำหรับ Modal
try {
    $prefixes  = $conn->query("SELECT prefix_id, prefix_name FROM prefix ORDER BY prefix_name")->fetchAll(PDO::FETCH_ASSOC);
    $positions = $conn->query("SELECT position_id, position_name FROM position ORDER BY position_name")->fetchAll(PDO::FETCH_ASSOC);
    $ranks     = $conn->query("SELECT rank_id, rank_name FROM ranks ORDER BY rank_name")->fetchAll(PDO::FETCH_ASSOC);
    $schools   = $conn->query("SELECT school_id, school_name FROM school ORDER BY school_name")->fetchAll(PDO::FETCH_ASSOC);
    $subjectgroups = $conn->query("SELECT subjectgroup_id, subjectgroup_name FROM subject_group ORDER BY subjectgroup_name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <title>จัดการข้อมูลครู — Modern Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/teacher.css">
</head>

<body>

    <div class="container-custom py-4">
        <div class="card card-custom p-4">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div class="page-title">
                    <i class="fas fa-user-graduate"></i>
                    <div>
                        <div style="font-size:18px;">จัดการข้อมูลครู</div>
                        <div style="font-size:13px; color:var(--muted);">รายการ และจัดการข้อมูลครูในระบบ</div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-user-plus me-2"></i> เพิ่มข้อมูลครู
                    </button>
                    <a href="index.php" class="btn btn-danger">
                        <i class="fas fa-arrow-left me-1"></i> กลับหน้าหลัก
                    </a>
                </div>
            </div>

            <div class="row mb-3 align-items-center">
                <div class="col-12 col-md-6 col-lg-5">
                    <div class="position-relative search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchTeacher" class="form-control search-input" placeholder="ค้นหาชื่อ, นามสกุล หรือโรงเรียน...">
                        <button id="clearSearch" class="btn btn-light position-absolute border-0" style="right:6px; top:50%; transform:translateY(-50%); border-radius:50%; width:35px; height:35px; display:flex; align-items:center; justify-content:center; background: transparent;">
                            <i class="fas fa-times text-muted"></i>
                        </button>
                    </div>
                    <div class="mt-2 text-muted small ps-2">
                        <i class="fas fa-info-circle"></i> พิมพ์เพื่อค้นหาทันที | เลื่อนลงเพื่อโหลดเพิ่ม (Infinite Scroll)
                    </div>
                </div>
            </div>

            <div class="table-responsive rounded shadow-sm border" style="max-height: 650px; overflow-y: auto;" id="scrollContainer">
                <table class="table table-hover align-middle mb-0 w-100">
                    <thead style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th style="width: 25%;">ชื่อ - สกุล</th>
                            <th style="width: 20%;">ตำแหน่ง</th>
                            <th style="width: 15%;">วิทยฐานะ</th>
                            <th style="width: 25%;">โรงเรียน</th>
                            <th class="text-center" style="width: 15%;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="teacherTableBody">
                    </tbody>
                </table>
                <div id="loading"><i class="fas fa-spinner fa-spin"></i> กำลังโหลดข้อมูล...</div>
                <div id="noMoreData" class="text-center p-3 text-muted" style="display:none;">-- สิ้นสุดข้อมูล --</div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="editTeacherModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-dark bg-opacity-25">
                    <h5 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>แก้ไขข้อมูลครู</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="teacherEditForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="t_pid" id="edit_t_pid">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">คำนำหน้า</label>
                                <select class="form-select" name="prefix_id" id="edit_prefix_id">
                                    <option value="">--</option><?php foreach ($prefixes as $pf): ?><option value="<?= $pf['prefix_id'] ?>"><?= $pf['prefix_name'] ?></option><?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">ชื่อ</label>
                                <input type="text" class="form-control" name="f_name" id="edit_f_name">
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-muted">นามสกุล</label>
                                <input type="text" class="form-control" name="l_name" id="edit_l_name">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">ตำแหน่ง</label>
                                <select class="form-select" name="position_id" id="edit_position_id"><?php foreach ($positions as $p): ?><option value="<?= $p['position_id'] ?>"><?= $p['position_name'] ?></option><?php endforeach; ?></select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">วิทยฐานะ</label>
                                <select class="form-select" name="rank_id" id="edit_rank_id">
                                    <option value="">--</option><?php foreach ($ranks as $r): ?><option value="<?= $r['rank_id'] ?>"><?= $r['rank_name'] ?></option><?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">กลุ่มสาระการเรียนรู้</label>
                                <select class="form-select" name="subjectgroup_id" id="edit_subjectgroup_id">
                                    <option value="">--</option><?php foreach ($subjectgroups as $sg): ?><option value="<?= $sg['subjectgroup_id'] ?>"><?= $sg['subjectgroup_name'] ?></option><?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted">โรงเรียน</label>
                                <select class="form-select" name="school_id" id="edit_school_id"><?php foreach ($schools as $s): ?><option value="<?= $s['school_id'] ?>"><?= $s['school_name'] ?></option><?php endforeach; ?></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="button" class="btn btn-warning text-white" onclick="saveTeacherEdit()"><i class="fas fa-save me-1"></i> บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addTeacherModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header btn-gradient">
                    <h5 class="modal-title text-white fw-bold"><i class="fas fa-user-plus me-2"></i>เพิ่มครูใหม่</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="addTeacherForm">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted">รหัสบัตรประชาชน (Username)</label>
                                <input type="text" class="form-control" name="t_pid" maxlength="13" required placeholder="ระบุเลข 13 หลัก">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted">คำนำหน้า</label>
                                <select class="form-select" name="prefix_id">
                                    <option value="">--</option><?php foreach ($prefixes as $pf): ?><option value="<?= $pf['prefix_id'] ?>"><?= $pf['prefix_name'] ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">ชื่อ</label>
                                <input type="text" class="form-control" name="f_name" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-bold small text-muted">นามสกุล</label>
                                <input type="text" class="form-control" name="l_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">ตำแหน่ง</label>
                                <select class="form-select" name="position_id"><?php foreach ($positions as $p): ?><option value="<?= $p['position_id'] ?>"><?= $p['position_name'] ?></option><?php endforeach; ?></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">วิทยฐานะ</label>
                                <select class="form-select" name="rank_id">
                                    <option value="">--</option><?php foreach ($ranks as $r): ?><option value="<?= $r['rank_id'] ?>"><?= $r['rank_name'] ?></option><?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">รายวิชา</label>
                                <select class="form-select" name="subject_id" id="edit_subject_id">
                                    <option value="">-- เลือกรายวิชา --</option>
                                    <?php
                                    $subjects = $conn->query("SELECT subject_id, subject_name FROM subject ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);

                                    foreach ($subjects as $s):
                                    ?>
                                        <option value="<?= $s['subject_id'] ?>">
                                            <?= $s['subject_name'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted">โรงเรียน</label>
                                <select class="form-select" name="school_id"><?php foreach ($schools as $s): ?><option value="<?= $s['school_id'] ?>"><?= $s['school_name'] ?></option><?php endforeach; ?></select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="button" class="btn btn-success btn-gradient" onclick="saveNewTeacher()"><i class="fas fa-save me-1"></i> บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // --- Variables ---
        let offset = 0;
        let isLoading = false;
        let hasMore = true;
        let searchTimeout = null;

        const tbody = document.getElementById("teacherTableBody");
        const searchInput = document.getElementById("searchTeacher");
        const loadingEl = document.getElementById("loading");
        const noMoreEl = document.getElementById("noMoreData");
        const scrollContainer = document.getElementById("scrollContainer");

        // --- Functions ---

        // 1. โหลดข้อมูล (AJAX)
        function loadData(reset = false) {
            if (isLoading) return;
            if (reset) {
                offset = 0;
                hasMore = true;
                tbody.innerHTML = "";
                noMoreEl.style.display = "none";
            }
            if (!hasMore) return;

            isLoading = true;
            loadingEl.style.display = "block";

            const searchTerm = searchInput.value.trim();

            fetch(`api/get_teachers.php?search=${encodeURIComponent(searchTerm)}&offset=${offset}`)
                .then(response => response.json())
                .then(res => {
                    if (res.success) {
                        const data = res.data;
                        if (data.length > 0) {
                            renderRows(data);
                            offset += data.length;
                        } else {
                            hasMore = false;
                            if (offset > 0 || searchTerm !== "") {
                                noMoreEl.style.display = "block";
                            } else {
                                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-5"><i class="fas fa-search fa-2x mb-3"></i><br>ไม่พบข้อมูล</td></tr>';
                            }
                        }
                    } else {
                        console.error(res.message);
                    }
                })
                .catch(err => console.error("Error:", err))
                .finally(() => {
                    isLoading = false;
                    loadingEl.style.display = "none";
                });
        }

        // 2. สร้างแถวตาราง HTML
        function renderRows(teachers) {
            let html = "";
            teachers.forEach(t => {
                html += `
            <tr>
                <td class="fw-bold text-dark">${(t.prefix_name || '')} ${t.f_name} ${t.l_name}</td>
                <td><span class="badge bg-secondary bg-opacity-10 text-dark border">${t.position_name || '-'}</span></td>
                <td class="small text-muted">${t.rank_name || '-'}</td>
                <td>${t.school_name || '-'}</td>
                <td class="text-center">
                    <div class="btn-group">
                        <button class="btn btn-warning btn-sm text-white shadow-sm" onclick="openTeacherEditModal('${t.t_pid}')" title="แก้ไข">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm shadow-sm" onclick="deleteTeacher('${t.t_pid}')" title="ลบ">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
            });
            tbody.insertAdjacentHTML('beforeend', html);
        }

        // --- Event Listeners ---

        // 1. ค้นหา (Debounce)
        searchInput.addEventListener("input", () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                loadData(true);
            }, 500);
        });

        // 2. ปุ่มเคลียร์ค้นหา
        document.getElementById("clearSearch").addEventListener("click", () => {
            searchInput.value = "";
            loadData(true);
        });

        // 3. Infinite Scroll
        scrollContainer.addEventListener("scroll", () => {
            const {
                scrollTop,
                scrollHeight,
                clientHeight
            } = scrollContainer;
            if (scrollTop + clientHeight >= scrollHeight - 50) {
                loadData();
            }
        });

        // --- Init ---
        loadData(true);

        // --- Action Functions ---

        function openTeacherEditModal(t_pid) {
            // ใช้ api/get_teachers.php?t_pid=xxx แทน fetch_teacher.php เดิม เพื่อให้สอดคล้องกับโครงสร้าง API
            // แต่เนื่องจากคุณมี fetch_teacher.php อยู่แล้ว ผมจะใช้ไฟล์เดิมเพื่อความชัวร์
            fetch('fetch_teacher.php?t_pid=' + encodeURIComponent(t_pid))
                .then(r => r.json())
                .then(d => {
                    if (!d.success) {
                        alert("Error โหลดข้อมูล: " + (d.message || 'Unknown error'));
                        return;
                    }
                    const t = d.data;
                    document.getElementById("edit_t_pid").value = t.t_pid;
                    document.getElementById("edit_prefix_id").value = t.prefix_id || '';
                    document.getElementById("edit_f_name").value = t.f_name;
                    document.getElementById("edit_l_name").value = t.l_name;
                    document.getElementById("edit_position_id").value = t.position_id || '';
                    document.getElementById("edit_rank_id").value = t.rank_id || '';
                    document.getElementById("edit_subject_id").value = t.subject_id || '';
                    document.getElementById("edit_school_id").value = t.school_id || '';
                    new bootstrap.Modal(document.getElementById('editTeacherModal')).show();
                })
                .catch(e => {
                    console.error(e);
                    alert("เกิดข้อผิดพลาดในการโหลดข้อมูล");
                });
        }

        function saveTeacherEdit() {
            const form = document.getElementById("teacherEditForm");
            if (!form.checkValidity()) {
                alert("กรุณากรอกข้อมูลให้ครบถ้วน");
                return;
            }

            const data = new FormData(form);
            const btnSave = document.querySelector('#editTeacherModal .btn-warning');
            const originalText = btnSave.innerHTML;
            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

            fetch("api/update_teacher.php", {
                    method: "POST",
                    body: data
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        alert("บันทึกข้อมูลสำเร็จ");
                        var myModalEl = document.getElementById('editTeacherModal');
                        var modal = bootstrap.Modal.getInstance(myModalEl);
                        modal.hide();
                        loadData(true);
                    } else {
                        alert("เกิดข้อผิดพลาด: " + d.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
                })
                .finally(() => {
                    btnSave.disabled = false;
                    btnSave.innerHTML = originalText;
                });
        }

        function deleteTeacher(pid) {
            if (!confirm("ยืนยันการลบข้อมูลครูท่านนี้? \n(ข้อมูลการนิเทศที่เกี่ยวข้องอาจได้รับผลกระทบ)")) return;

            const data = new FormData();
            data.append("t_pid", pid);

            fetch("api/delete_teacher.php", {
                    method: "POST",
                    body: data
                })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        alert("ลบข้อมูลสำเร็จ");
                        loadData(true);
                    } else {
                        alert("Error: " + d.message);
                    }
                })
                .catch(e => {
                    alert("เกิดข้อผิดพลาดในการเชื่อมต่อ");
                });
        }

        function saveNewTeacher() {
            const form = document.getElementById("addTeacherForm");
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const data = new FormData(form);
            const btnSave = document.querySelector('#addTeacherModal .btn-success');
            const originalText = btnSave.innerHTML;
            btnSave.disabled = true;
            btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังบันทึก...';

            fetch("api/add_teacher.php", {
                    method: "POST",
                    body: data
                })
                .then(r => r.json()).then(d => {
                    if (d.success) {
                        alert("เพิ่มข้อมูลสำเร็จ");
                        form.reset();
                        var myModalEl = document.getElementById('addTeacherModal');
                        var modal = bootstrap.Modal.getInstance(myModalEl);
                        modal.hide();
                        loadData(true);
                    } else {
                        alert("Error: " + d.message);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("เกิดข้อผิดพลาดในการบันทึก");
                })
                .finally(() => {
                    btnSave.disabled = false;
                    btnSave.innerHTML = originalText;
                });
        }
    </script>

</body>

</html>