<?php
include_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../auth.php';
checkAuth();

$base_url = ticketsRelativeBaseUrl();
?>

<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6" href="<?php echo $base_url; ?>inicio.php">
        <i class="fas fa-ticket-alt me-2"></i>Ticket GK
    </a>
    <button class="navbar-toggler d-lg-none collapsed ms-3" type="button" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Alternar menú de navegación">
        <span class="navbar-toggler-icon"></span>
    </button>
    <input class="form-control form-control-dark w-100 d-none d-md-block" type="text" placeholder="Buscar tickets..." aria-label="Buscar tickets" id="searchInput">
    <div class="navbar-nav">
        <div class="nav-item text-nowrap">
            <a class="nav-link px-3" href="<?php echo $base_url; ?>logout.php" title="Cerrar sesión">
                <i class="fas fa-sign-out-alt me-1"></i><span class="d-none d-md-inline">Cerrar sesión</span>
            </a>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                const baseUrl = '<?php echo $base_url; ?>';
                window.location.href = baseUrl + 'modules/tickets/tickets.php?q=' + encodeURIComponent(this.value);
            }
        });
    }
});
</script>

<!-- Replace this line: -->
<!-- <script src="https://kit.fontawesome.com/a076d05399.js"></script> -->

<!-- With this: -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />