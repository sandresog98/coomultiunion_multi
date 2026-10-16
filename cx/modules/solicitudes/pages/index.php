<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Solicitudes';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
$hub = [
    ['icon' => 'fa-list-check',          'title' => 'Mis solicitudes',    'sub' => 'Gestiona y consulta tus solicitudes', 'href' => 'modules/solicitudes/pages/mis_solicitudes'],
    ['icon' => 'fa-headset',             'title' => 'PQRS',               'sub' => 'Peticiones, quejas, reclamos',        'href' => 'modules/solicitudes/pages/pqrs'],
    ['icon' => 'fa-user-pen',            'title' => 'Actualizar info',    'sub' => 'Actualiza tus datos',                'href' => 'modules/solicitudes/pages/actualizar_info'],
    ['icon' => 'fa-ticket',              'title' => 'Boletas',            'sub' => 'Solicita boletas',                   'href' => 'modules/solicitudes/pages/boletas'],
    ['icon' => 'fa-hand-holding-dollar', 'title' => 'Retiros',            'sub' => 'Redención bolsillos / incentivos',   'href' => 'modules/solicitudes/pages/retirar'],
    ['icon' => 'fa-file-pdf',            'title' => 'Certificados',       'sub' => 'Declaración renta / estado de cuenta', 'href' => 'modules/solicitudes/pages/certificados'],
    ['icon' => 'fa-gift',                'title' => 'Fondo de bienestar', 'sub' => 'Solicita el fondo de bienestar',     'href' => 'modules/fondo_bienestar/pages/index'],
    ['icon' => 'fa-handshake',           'title' => 'Convenios',          'sub' => 'Explora los convenios disponibles',  'href' => 'modules/convenios/pages/index'],
];
?>
<div class="row g-3">
  <?php foreach ($hub as $it): ?>
  <div class="col-12 col-sm-6 col-md-3">
    <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= $CX ?>/<?= $it['href'] ?>">
      <div class="mini-icon"><i class="fa-solid <?= $it['icon'] ?>"></i></div>
      <h6><?= $it['title'] ?></h6>
      <div class="small text-muted"><?= $it['sub'] ?></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>