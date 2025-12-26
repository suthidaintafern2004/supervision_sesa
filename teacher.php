<hr>
<div class="card-body">
    <h5 class="card-title fw-bold text-success"><i class="fas fa-user-graduate"></i> ข้อมูลผู้รับนิเทศ</h5>
    <hr>
    <div class="row g-3">

        <div class="col-md-6">
            <label for="teacher_name_input" class="form-label fw-bold">ชื่อผู้รับนิเทศ</label>

            <div style="position: relative;">
                <div class="input-group">
                    <input id="teacher_name_input" name="teacher_name"
                        class="form-control"
                        value="<?php echo htmlspecialchars($inspection_data['teacher_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="-- พิมพ์ชื่อ-สกุล แล้วกดค้นหา --"
                        autocomplete="off">

                    <button class="btn btn-primary" type="button" id="search_teacher_button">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                </div>

                <div id="teacher_results"
                    style="border:1px solid #ccc; background:#fff; width:100%;
                            display:none; position:absolute; z-index:999;
                            max-height:180px; overflow-y:auto; border-radius:4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <label for="t_pid" class="form-label fw-bold">เลขบัตรประจำตัวประชาชน</label>
            <input type="text" id="t_pid" name="t_pid"
                class="form-control display-field bg-light" placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="position_name" class="form-label fw-bold">ตำแหน่ง</label>
            <input type="text" id="position_name" name="position_name"
                class="form-control display-field bg-light" placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="rank_name" class="form-label fw-bold">วิทยฐานะ</label>
            <input type="text" id="rank_name" name="rank_name"
                class="form-control display-field bg-light" placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="subject_name" class="form-label fw-bold">กลุ่มสาระการเรียนรู้</label>
            <input type="text" id="subject_name" name="subject_name"
                class="form-control display-field bg-light" placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="school_name" class="form-label fw-bold">โรงเรียน</label>
            <input type="text" id="school_name" name="school_name"
                class="form-control display-field bg-light" placeholder="--" readonly>
        </div>
    </div>
</div>

<script>
    // เก็บ list ครูทั้งหมด
    let allTeachers = [];

    /**
     * เรียกจาก supervision_start.php หลัง DOM โหลด
     * เพื่อผูก event ให้ช่องค้นหาครู
     */
    function initTeacherSearch() {
        const teacherInput = document.getElementById('teacher_name_input');
        const resultBox = document.getElementById('teacher_results');
        const searchBtn = document.getElementById('search_teacher_button');

        if (!teacherInput || !resultBox || !searchBtn) return;

        // โหลดรายชื่อครูทั้งหมดรอไว้เลย
        populateTeacherList(teachers => allTeachers = teachers);

        // ฟังก์ชันค้นหา (ใช้ได้ทั้งปุ่มค้นหาและ Enter)
        function runTeacherSearch() {
            const searchTerm = teacherInput.value.trim().toLowerCase();

            if (!searchTerm) {
                alert("กรุณากรอกชื่อก่อนค้นหา");
                return;
            }

            const results = allTeachers
                .filter(t => t.full_name.toLowerCase().includes(searchTerm))
                .slice(0, 10); // แสดงสูงสุด 10 คน

            resultBox.innerHTML = "";

            if (results.length === 0) {
                resultBox.style.display = "none";
                alert("ไม่พบรายชื่อที่ค้นหา");
                return;
            }

            results.forEach(teacher => {
                const item = document.createElement('div');
                item.textContent = teacher.full_name + " (" + teacher.school_name + ")"; // เพิ่มชื่อโรงเรียนในวงเล็บ
                item.style.padding = "10px";
                item.style.cursor = "pointer";
                item.style.borderBottom = "1px solid #eee";

                item.addEventListener('mouseover', () => item.style.background = "#f0f0f0");
                item.addEventListener('mouseout', () => item.style.background = "white");

                item.addEventListener('click', () => {
                    teacherInput.value = teacher.full_name;
                    resultBox.style.display = "none";
                    fetchTeacherData(teacher.t_pid);
                });

                resultBox.appendChild(item);
            });

            resultBox.style.display = "block";
        }

        // คลิกปุ่มค้นหา
        searchBtn.addEventListener('click', runTeacherSearch);

        // กด Enter ในช่องชื่อ -> ค้นหา
        teacherInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                runTeacherSearch();
            }
        });

        // พิมพ์ใหม่ -> เคลียร์ข้อมูลด้านขวา
        teacherInput.addEventListener('input', () => {
            clearTeacherData();
            resultBox.style.display = "none";
        });

        // คลิกที่อื่นเพื่อปิดกล่องผลลัพธ์
        document.addEventListener('click', (e) => {
            if (!teacherInput.contains(e.target) && !resultBox.contains(e.target) && !searchBtn.contains(e.target)) {
                resultBox.style.display = "none";
            }
        });
    }

    // ดึง list ครูทั้งหมดจาก server
    function populateTeacherList(callback) {
        fetch("fetch_teacher.php?action=get_all")
            .then(res => res.json())
            .then(data => {
                if (data.success) callback(data.data);
            })
            .catch(err => console.error("Error loading teacher list:", err));
    }

    // ล้างช่องรายละเอียดครูด้านขวา
    function clearTeacherData() {
        document.getElementById('t_pid').value = "";
        document.getElementById('position_name').value = "";
        document.getElementById('rank_name').value = "";
        document.getElementById('subject_name').value = "";
        document.getElementById('school_name').value = "";
    }

    // ดึงข้อมูลครูจาก PID แล้วเติมลงช่องแสดงผล
    function fetchTeacherData(pid) {
        clearTeacherData();

        fetch("fetch_teacher.php?t_pid=" + encodeURIComponent(pid))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const info = data.data;
                    document.getElementById('t_pid').value = info.t_pid;
                    document.getElementById('position_name').value = info.position_name || '-';
                    document.getElementById('rank_name').value = info.rank_name || info.rank_name || '-';
                    document.getElementById('subject_name').value = info.subject_name || info.subjectgroup_name || '-';
                    document.getElementById('school_name').value = info.school_name || '-';

                    lockFormByPosition(info.position_name || '');
                }
            })
            .catch(err => console.error("Teacher fetch error:", err));
    }


    function lockFormByPosition(positionName) {

        const classroomTile = document.getElementById('classroom_tile');
        const classroomRadio = document.getElementById('classroom_radio');
        const quickwinRadio = document.getElementById('quickwin_radio');
        const quickwinTile = document.getElementById('quickwin_tile');

        if (!classroomTile || !classroomRadio || !quickwinRadio) return;

        const isDirector =
            positionName.includes('ผู้อำนวยการ') ||
            positionName.includes('รองผู้อำนวยการ');

        if (isDirector) {
            /* 🔒 ล็อก */
            classroomTile.classList.add('locked');

            classroomTile.style.backgroundColor = '#e9ecef';
            classroomTile.style.borderColor = '#ced4da';
            classroomTile.style.opacity = '0.6';
            classroomTile.style.pointerEvents = 'none';

            classroomRadio.checked = false;
            classroomRadio.disabled = true;

            // เลือก QuickWin อัตโนมัติ
            quickwinRadio.checked = true;
            quickwinTile.classList.add('active');

        } else {
            /* 🔓 ปลดล็อก (สำคัญมาก) */
            classroomTile.classList.remove('locked');

            // ⭐ คืนค่า style ทุกอย่าง
            classroomTile.style.backgroundColor = '';
            classroomTile.style.borderColor = '';
            classroomTile.style.opacity = '';
            classroomTile.style.pointerEvents = '';

            classroomRadio.disabled = false;

            // ไม่ auto-select อะไร ปล่อยให้ user เลือกเอง
        }
    }
</script>