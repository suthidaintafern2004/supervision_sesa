<h5 class="card-title fw-bold text-success mt-4">โปรดเลือกรูปแบบการนิเทศ</h5>

<style>
/* ==== STYLE สำหรับแบบ Tile Card ==== */
.tile-radio {
    padding: 22px;
    border-radius: 16px;
    text-align: center;
    cursor: pointer;
    border: 2px solid #e0e0e0;
    transition: all 0.25s;
    background: white;
}

.tile-radio:hover {
    background: #f8f9fa;
    border-color: #15a362;
}

.tile-radio input {
    display: none;
}

.tile-radio.active {
    border-color: #0d6efd;
    background: #e9f2ff;
    color: #0d6efd;
    box-shadow: 0 0 10px rgba(13,110,253,0.2);
}

.tile-radio i {
    font-size: 40px;
    margin-bottom: 10px;
    transition: 0.2s;
}

.tile-radio.active i {
    transform: scale(1.1);
}
</style>

<div class="row text-center mt-3">

    <!-- KPI Form -->
    <div class="col-md-6 mb-3">
        <label class="tile-radio w-100" onclick="activateTile(this)">
            <input type="radio" name="form_type" value="kpi_form" required>
            <i class="fas fa-file-alt text-primary"></i>
            <h5 class="fw-bold mt-2">ฟอร์ม Class</h5>
            <p class="text-muted small">แบบบันทึกการสอนและการจัดการชั้นเรียน</p>
        </label>
    </div>

    <!-- Quick Win -->
    <div class="col-md-6 mb-3">
        <label class="tile-radio w-100" onclick="activateTile(this)">
            <input type="radio" name="form_type" value="quickwin_form">
            <i class="fas fa-bullseye text-success"></i>
            <h5 class="fw-bold mt-2">ฟอร์ม QuickWin</h5>
            <p class="text-muted small">แบบกรอกข้อมูลนโยบายและจุดเน้น</p>
        </label>
    </div>

</div>

<script>
function activateTile(tile) {
    document.querySelectorAll('.tile-radio').forEach(t => t.classList.remove('active'));
    tile.classList.add('active');
    tile.querySelector('input[type="radio"]').checked = true;
}
</script>