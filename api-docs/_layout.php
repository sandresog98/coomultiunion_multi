<?php
declare(strict_types=1);
/**
 * Layout compartido de la documentación pública de la API.
 * Uso:  require __DIR__ . '/_layout.php';  ad_head('Título','docs'); ...  ad_foot();
 *
 * Tema: reutiliza el sistema de `ui/` (assets/js/theme.js + assets/css/theme-dark.css).
 * El tema claro/oscuro se guarda en localStorage['multi-theme'] y se aplica con
 * el atributo data-bs-theme en <html>. Por defecto: claro.
 */

function ad_base(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return rtrim(dirname($dir), '/');
}

function ad_head(string $title, string $active, string $extraHead = ''): void
{
    $base  = ad_base();
    $logo  = $base . '/assets/img/logo_coomultiunion.png';
    $hub   = $base . '/';
    $links = [
        'docs'  => ['Documentación API', $base . '/api-docs/',          'fa-code'],
        'roles' => ['Roles y permisos',  $base . '/api-docs/roles', 'fa-user-shield'],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — Coomultiunion</title>
<link rel="icon" href="<?= $base ?>/assets/favicons/favicon.ico" sizes="any">
<!-- Tema: fija data-bs-theme ANTES de pintar (evita parpadeo). Por defecto: claro. -->
<script>(function(){try{var t=localStorage.getItem('multi-theme');document.documentElement.setAttribute('data-bs-theme',t==='dark'?'dark':'light');}catch(e){document.documentElement.setAttribute('data-bs-theme','light');}})();</script>
<link href="<?= $base ?>/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $base ?>/assets/vendor/fontawesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="<?= $base ?>/assets/css/fonts.css" rel="stylesheet">
<link href="<?= $base ?>/assets/css/front.css" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>/api-docs/assets/api-docs.css">
<link href="<?= $base ?>/assets/css/theme-dark.css" rel="stylesheet">
<?= $extraHead ?>
</head>
<body class="ad-body">
<div class="ad-app">
  <header class="ad-header">
    <a class="ad-brand" href="<?= $hub ?>" aria-label="Inicio">
      <img src="<?= $base ?>/assets/img/logo.png" alt="Multi App" class="ad-logo-multi">
      <img src="<?= $logo ?>" alt="Coomultiunion" class="ad-logo-coop">
    </a>
    <div class="ad-titles">
      <h1 class="ad-title">API para integraciones</h1>
      <p class="ad-sub">Documentación de los endpoints autorizados</p>
    </div>
    <nav class="ad-nav">
      <?php foreach ($links as $k => $l): ?>
      <a class="ad-navlink<?= $k === $active ? ' is-active' : '' ?>" href="<?= $l[1] ?>"><i class="fas <?= $l[2] ?>"></i><span><?= $l[0] ?></span></a>
      <?php endforeach; ?>
      <button type="button" class="ad-navlink ad-theme-toggle" data-theme-toggle aria-pressed="false" title="Cambiar a tema oscuro">
        <i class="fas fa-moon" data-theme-icon></i><span data-theme-label>Tema oscuro</span>
      </button>
    </nav>
  </header>
  <main class="ad-main">
<?php
}

function ad_foot(): void
{
    $base = ad_base();
    ?>
  </main>
  <footer class="ad-footer">
    <span><i class="fas fa-lock"></i> Autenticación por token Bearer. En producción se exige HTTPS.</span>
    <span><a href="<?= $base ?>/privacidad"><i class="fas fa-shield-halved"></i> Política de datos</a></span>
    <span><a href="<?= $base ?>/"><i class="fas fa-house"></i> Inicio</a></span>
  </footer>
</div>
<script src="<?= $base ?>/assets/js/theme.js"></script>
</body>
</html>
<?php
}
