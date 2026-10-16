<?php
declare(strict_types=1);
require_once __DIR__ . '/guard.php';
$staff = ui_guard();
$pageTitle = 'Inicio';
$currentPage = 'dashboard';
require __DIR__ . '/views/layouts/header.php';
?>
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <h2 class="h4 text-brand">Bienvenido, <?php echo htmlspecialchars($staff['nombre_completo']); ?></h2>
        <p class="text-muted mb-0">Rol: <span class="badge text-bg-primary"><?php echo htmlspecialchars($staff['rol']); ?></span></p>
        <p class="text-muted small mt-3 mb-0">Utiliza el menú lateral para ir a cada sección.</p>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/views/layouts/footer.php'; ?>
