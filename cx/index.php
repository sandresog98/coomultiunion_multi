<?php
declare(strict_types=1);
require_once __DIR__ . '/_env.php';
$actor = PortalAuth::actor();
if ($actor === null) { header('Location: ' . $CX . '/login'); exit; }
$pageTitle = 'Inicio';
$activeNav = 'inicio';
require __DIR__ . '/views/layouts/header.php';
$nombre = htmlspecialchars($actor['nombre'] ?? '');
?>
<div class="portal-card portal-card-glowing-border mb-3">
  <div class="card-header-brand">
    <i class="fa-solid fa-hand-sparkles fa-xl" style="color:var(--cx-dark)"></i>
    <div>
      <div class="fs-5 fw-bold">Bienvenido, <?= $nombre ?></div>
      <div class="crumb">Cédula <?= htmlspecialchars($actor['cedula'] ?? '') ?> · explora tus servicios desde el menú</div>
    </div>
  </div>
</div>
<div class="row g-3">
  <div class="col-12 col-sm-6 col-md-3">
    <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= $CX ?>/modules/aportes/pages/index">
      <div class="mini-icon"><i class="fa-solid fa-chart-column"></i></div>
      <h6>Aportes</h6><div class="small text-muted">Saldos y extractos</div>
    </a>
  </div>
  <div class="col-12 col-sm-6 col-md-3">
    <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= $CX ?>/modules/aportes/pages/movimientos">
      <div class="mini-icon"><i class="fa-solid fa-list"></i></div>
      <h6>Movimientos</h6><div class="small text-muted">Pagos y transacciones</div>
    </a>
  </div>
  <div class="col-12 col-sm-6 col-md-3">
    <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= $CX ?>/modules/creditos/pages/index">
      <div class="mini-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
      <h6>Mis créditos</h6><div class="small text-muted">Créditos y solicitudes</div>
    </a>
  </div>
  <div class="col-12 col-sm-6 col-md-3">
    <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= $CX ?>/modules/solicitud_credito/pages/index">
      <div class="mini-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
      <h6>Solicitar crédito</h6><div class="small text-muted">Nueva solicitud</div>
    </a>
  </div>
</div>
<?php require __DIR__ . '/views/layouts/footer.php'; ?>