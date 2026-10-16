<?php
/** Encabezado común de la interfaz ui. Requiere: $staff, $pageTitle, $currentPage. */
require_once __DIR__ . '/../../config/paths.php';
$staff = $staff ?? [];
$pageTitle = $pageTitle ?? 'Multi App';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(\Csrf::token()); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> — Multi App</title>
    <link rel="icon" href="<?php echo assetUrl('favicons/favicon.ico'); ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="<?php echo assetUrl('css/common.css'); ?>" rel="stylesheet">
    <script src="<?php echo assetUrl('js/ui_helpers.js'); ?>"></script>
</head>
<body>
<!-- Top bar mobile -->
<nav class="navbar d-md-none" style="background:var(--brand-blue-dark)">
  <div class="container-fluid">
    <button class="btn text-white p-0" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas"><i class="fas fa-bars fs-5"></i></button>
    <span class="fw-brand text-white">Multi App</span>
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
