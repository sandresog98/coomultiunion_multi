<?php
/** Encabezado común de la interfaz ui. Requiere: $staff, $pageTitle, $currentPage. */
require_once __DIR__ . '/../../config/paths.php';
require_once dirname(__DIR__, 3) . '/core/env.php';
$staff = $staff ?? [];
$pageTitle = $pageTitle ?? 'Multi App';

// Señal visual de entorno: 'local' (negro) · 'test' (rojo) · 'production' (azul, sin señal).
$__env      = app_env();
$__envClass = 'env-' . preg_replace('/[^a-z0-9_-]/i', '', $__env);
$__envLabel = env_label();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(\Csrf::token()); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> — Multi App</title>
    <link rel="icon" href="<?php echo assetUrl('favicons/favicon.ico'); ?>" sizes="any">
    <!-- Tema: fija data-bs-theme ANTES de pintar (evita parpadeo). Por defecto: claro. -->
    <script>(function(){try{var t=localStorage.getItem('multi-theme');document.documentElement.setAttribute('data-bs-theme',t==='dark'?'dark':'light');}catch(e){document.documentElement.setAttribute('data-bs-theme','light');}})();</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo assetUrl('css/common.css'); ?>" rel="stylesheet">
    <link href="<?php echo assetUrl('css/theme-dark.css'); ?>" rel="stylesheet">
    <script src="<?php echo assetUrl('js/ui_helpers.js'); ?>"></script>
    <script src="<?php echo assetUrl('js/solicitud_detalle.js'); ?>"></script>
</head>
<body class="<?php echo $__envClass; ?>">
<!-- Top bar mobile -->
<nav class="navbar d-md-none" style="background:var(--sidebar-bg)">
  <div class="container-fluid">
    <button class="btn text-white p-0" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas"><i class="fas fa-bars fs-5"></i></button>
    <span class="fw-brand text-white">Multi App<?php if ($__envLabel !== ''): ?> <span class="env-badge ms-1"><?php echo $__envLabel; ?></span><?php endif; ?></span>
    <div class="dropdown">
      <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
        <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($staff['nombre_completo'] ?? 'Usuario'); ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?php echo uiBaseUrl(); ?>cambiar_password">Cambiar contraseña</a></li>
        <li><a class="dropdown-item text-danger" href="<?php echo uiBaseUrl(); ?>logout">Cerrar sesión</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="col-md-9 col-lg-10 ms-sm-auto px-md-4 py-4">
      <div class="d-none d-md-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-brand mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($staff['nombre_completo'] ?? 'Usuario'); ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?php echo uiBaseUrl(); ?>cambiar_password">Cambiar contraseña</a></li>
            <li><a class="dropdown-item text-danger" href="<?php echo uiBaseUrl(); ?>logout">Cerrar sesión</a></li>
          </ul>
        </div>
      </div>
      <div class="brand-accent mb-4"></div>
