<?php
if (!empty($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: <?= json_encode($_SESSION['success']) ?>,
            confirmButtonText: 'ตกลง'
        });
    </script>
<?php
    unset($_SESSION['success']); // ⭐ สำคัญมาก
endif;
?>

<?php
if (!empty($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด',
            text: <?= json_encode($_SESSION['error']) ?>,
            confirmButtonText: 'ตกลง'
        });
    </script>
<?php
    unset($_SESSION['error']);
endif;
?>

<?php
if (!empty($_SESSION['warning'])): ?>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'แจ้งเตือน',
            text: <?= json_encode($_SESSION['warning']) ?>,
            confirmButtonText: 'ตกลง'
        });
    </script>
<?php
    unset($_SESSION['warning']);
endif;
?>