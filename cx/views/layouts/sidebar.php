<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/_env.php';
$activeNav = $activeNav ?? '';
$items = [
    ['k' => 'inicio',      'href' => '/index',                          'icon' => 'fa-house',              'label' => 'Inicio'],
    ['k' => 'perfil',      'href' => '/modules/perfil/pages/index',     'icon' => 'fa-user',              'label' => 'Mi información'],
    ['k' => 'aportes',     'href' => '/modules/aportes/pages/index',    'icon' => 'fa-chart-column',      'label' => 'Aportes'],
    ['k' => 'extractos',   'href' => '/modules/aportes/pages/extractos','icon' => 'fa-receipt',           'label' => 'Extractos'],
    ['k' => 'mov',         'href' => '/modules/aportes/pages/movimientos','icon' => 'fa-list',           'label' => 'Movimientos'],
    ['k' => 'cred',        'href' => '/modules/creditos/pages/index',   'icon' => 'fa-file-invoice-dollar','label' => 'Mis créditos'],
    ['k' => 'solicitudes', 'href' => '/modules/solicitudes/pages/index','icon' => 'fa-list-check',        'label' => 'Solicitudes'],
    ['k' => 'password',    'href' => '/cambiar_password',               'icon' => 'fa-key',               'label' => 'Cambiar contraseña'],
];
?>
<aside class="portal-sidebar" id="portal-sidebar">
  <nav class="portal-dock-nav nav flex-column">
    <?php foreach ($items as $it): ?>
    <a class="portal-dock-item nav-link <?= $activeNav === $it['k'] ? 'active' : '' ?>" href="<?= $CX . $it['href'] ?>">
      <span class="portal-dock-icon"><i class="fa-solid <?= $it['icon'] ?>"></i></span>
      <span class="portal-dock-label"><?= $it['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
</aside>