<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['flash_message'])):
    $type = $_SESSION['flash_type'] ?? 'info';

    $iconMap = [
        'success' => 'success',
        'warning' => 'warning',
        'danger'  => 'error',
        'info'    => 'info'
    ];

    $icon = $iconMap[$type] ?? 'info';
    $message = $_SESSION['flash_message'];

    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Swal.fire({
        icon: '<?= $icon ?>',
        title: 'แจ้งเตือน',
        text: '<?= addslashes($message) ?>',
        timer: 3000,
        showConfirmButton: false,
        timerProgressBar: true,
        customClass: {
            popup: 'swal-theme'
        }
    });
});
</script>
<?php endif; ?>
