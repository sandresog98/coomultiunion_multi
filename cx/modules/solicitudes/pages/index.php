<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Solicitudes';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
$hub = [
    ['icon' => 'fa-list-check',          'title' => 'Mis solicitudes',    'sub' => 'Gestiona y consulta tus solicitudes', 'href' => 'solicitudes/mis'],
    ['icon' => 'fa-headset',             'title' => 'PQRS',               'sub' => 'Peticiones, quejas, reclamos',        'href' => 'solicitudes/pqrs'],
    ['icon' => 'fa-user-pen',            'title' => 'Actualizar info',    'sub' => 'Actualiza tus datos',                'href' => 'solicitudes/actualizar-info'],
    ['icon' => 'fa-hand-holding-dollar', 'title' => 'Retiros',            'sub' => 'Redención bolsillos / incentivos',   'href' => 'solicitudes/retirar'],
    ['icon' => 'fa-file-pdf',            'title' => 'Certificados',       'sub' => 'Declaración renta / estado de cuenta', 'href' => 'solicitudes/certificados'],
    ['icon' => 'fa-gift',                'title' => 'Fondo de bienestar', 'sub' => 'Solicita el fondo de bienestar',     'href' => 'fondo-bienestar'],
    ['icon' => 'fa-handshake',           'title' => 'Convenios',          'sub' => 'Solicita productos de convenios',    'href' => 'solicitudes/convenios'],
];
?>
<div class="row g-3">
  <?php foreach ($hub as $it): ?>
  <div class="col-12 col-sm-6 col-md-3">
    <a class="portal-card portal-card-glowing-border text-decoration-none text-dark portal-mini d-block h-100" href="<?= cx_url($it['href']) ?>">
      <div class="mini-icon"><i class="fa-solid <?= $it['icon'] ?>"></i></div>
      <h6><?= $it['title'] ?></h6>
      <div class="small text-muted"><?= $it['sub'] ?></div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>