<?php
declare(strict_types=1);
require_once __DIR__ . '/config/paths.php';
require_once dirname(__DIR__) . '/core/Auth.php';
// Si ya hay sesión, al dashboard.
if (Auth::staff() !== null) {
    header('Location: ' . uiBaseUrl() . 'index');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingreso — Multi App</title>
    <link rel="icon" href="<?php echo assetUrl('favicons/favicon.ico'); ?>" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    <link href="<?php echo assetUrl('css/common.css'); ?>" rel="stylesheet">
</head>
<body class="brand-gradient">
<div class="login-wrap">
  <div class="card login-card border-0 shadow">
    <div class="brand-accent"></div>
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <img src="<?php echo assetUrl('img/logo.png'); ?>" alt="Multi App" class="mb-3" style="height:92px;width:92px;object-fit:contain;">
        <div class="fw-brand fs-3 text-brand">Multi App</div>
        <small class="text-muted">Interfaz administrativa</small>
      </div>
      <div id="alert" class="alert alert-danger d-none" role="alert"></div>
      <form id="loginForm">
        <div class="mb-3">
          <label class="form-label fw-semibold">Usuario</label>
          <input type="text" class="form-control" id="usuario" autocomplete="username" required autofocus>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Contraseña</label>
          <div class="input-group">
            <input type="password" class="form-control" id="password" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('password', this)" title="Mostrar/ocultar contraseña" tabindex="-1"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-brand w-100" id="btn">
          <i class="fas fa-right-to-bracket me-2"></i>Ingresar
        </button>
      </form>
    </div>
  </div>
</div>
<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const UI  = <?php echo json_encode(uiBaseUrl()); ?>;
const form = document.getElementById('loginForm');
const alertBox = document.getElementById('alert');
const btn = document.getElementById('btn');

function togglePwd(id, btnEl){
  const input = document.getElementById(id);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btnEl.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  alertBox.classList.add('d-none');
  btn.disabled = true;
  try {
    const r = await fetch(API + 'auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        usuario: document.getElementById('usuario').value.trim(),
        password: document.getElementById('password').value
      })
    });
    const j = await r.json();
    if (!j.ok) { throw new Error(j.error || 'Error'); }
    // Si requiere cambio de contraseña, va a esa pantalla; si no, al dashboard.
    location.href = UI + (j.data.must_change ? 'cambiar_password' : 'index');
  } catch (err) {
    alertBox.textContent = err.message;
    alertBox.classList.remove('d-none');
    btn.disabled = false;
  }
});
</script>
</body>
</html>
