<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/_env.php';
$activeNav = $activeNav ?? '';
$items = [
    ['k' => 'inicio',      'href' => 'inicio',                 'icon' => 'fa-house',              'label' => 'Inicio'],
    ['k' => 'perfil',      'href' => 'mi-informacion',         'icon' => 'fa-user',               'label' => 'Mi información'],
    ['k' => 'aportes',     'href' => 'aportes',                'icon' => 'fa-chart-column',       'label' => 'Aportes'],
    ['k' => 'extractos',   'href' => 'extractos',             'icon' => 'fa-receipt',            'label' => 'Extractos'],
    ['k' => 'mov',         'href' => 'movimientos',           'icon' => 'fa-list',               'label' => 'Movimientos'],
    ['k' => 'cred',        'href' => 'creditos',               'icon' => 'fa-file-invoice-dollar','label' => 'Mis créditos'],
    ['k' => 'solicitudes', 'href' => 'solicitudes',            'icon' => 'fa-list-check',         'label' => 'Solicitudes'],
    ['k' => 'asistente',   'href' => 'asistente',               'icon' => 'fa-comments',           'label' => 'Soli IA'],
    ['k' => 'password',    'href' => 'mi-clave',               'icon' => 'fa-key',                'label' => 'Cambiar contraseña'],
];
?>
<aside class="portal-sidebar" id="portal-sidebar">
  <nav class="portal-dock-nav nav flex-column">
    <?php foreach ($items as $it): ?>
    <a class="portal-dock-item nav-link <?= $activeNav === $it['k'] ? 'active' : '' ?>" href="<?= cx_url($it['href']) ?>">
      <span class="portal-dock-icon"><i class="fa-solid <?= $it['icon'] ?>"></i></span>
      <span class="portal-dock-label"><?= $it['label'] ?></span>
    </a>
    <?php endforeach; ?>
  </nav>
  <div class="pt-3 mt-2" style="border-top:1px solid var(--front-line)">
    <button type="button" class="portal-theme-toggle" data-theme-toggle aria-pressed="false">
      <span class="portal-dock-icon"><i class="fa-solid fa-moon" data-theme-icon></i></span>
      <span class="portal-dock-label" data-theme-label>Tema oscuro</span>
    </button>
  </div>
</aside>