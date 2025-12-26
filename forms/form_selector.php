<h5 class="card-title fw-bold text-success mt-4">โปรดเลือกรูปแบบการนิเทศ</h5>
<link rel="stylesheet" href="css/form_selector.css">

<div class="row text-center mt-3">
    

    <!-- Classroom Form -->
    <div class="col-md-6 mb-3">
        <label class="tile-radio w-100" id="classroom_tile" onclick="activateTile(this)">
            <input type="radio" name="form_type" id="classroom_radio" value="kpi_form" required>
            <i class="fas fa-file-alt text-primary"></i>
            <h5 class="fw-bold mt-2">ฟอร์ม Classroom</h5>
            <p class="text-muted small">แบบบันทึกการสอนและการจัดการชั้นเรียน</p>
        </label>
    </div>

    <!-- Quick Win -->
    <div class="col-md-6 mb-3">
        <label class="tile-radio w-100" id="quickwin_tile" onclick="activateTile(this)">
            <input type="radio" name="form_type" id="quickwin_radio" value="quickwin_form">
            <i class="fas fa-bullseye text-success"></i>
            <h5 class="fw-bold mt-2">ฟอร์ม QuickWin</h5>
            <p class="text-muted small">แบบกรอกข้อมูลนโยบายและจุดเน้น</p>
        </label>
    </div>

</div>

<script>
function activateTile(tile) {

    // ❌ ถ้า tile ถูกล็อก ห้ามเลือก
    if (tile.classList.contains('locked')) {
        return;
    }

    document.querySelectorAll('.tile-radio').forEach(t => t.classList.remove('active'));
    tile.classList.add('active');

    const radio = tile.querySelector('input[type="radio"]');
    if (radio && !radio.disabled) {
        radio.checked = true;
    }
}
</script>
