<?php
declare(strict_types=1);
require_once __DIR__ . '/config/paths.php';
require_once dirname(__DIR__) . '/core/Auth.php';
// Debe haber sesión (aunque sea con must_change).
$staff = Auth::staff();
if ($staff === null) {
    header('Location: ' . uiBaseUrl() . 'login');
    exit;
}
$forzado = !empty($staff['must_change']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(\Csrf::token()); ?>">
    <title>Cambiar contraseña — Multi App</title>
    <link href="<?php echo assetUrl('vendor/bootstrap/5.3.3/css/bootstrap.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo assetUrl('vendor/fontawesome/6.5.2/css/all.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo assetUrl('css/fonts.css'); ?>" rel="stylesheet">
    <link href="<?php echo assetUrl('css/common.css'); ?>" rel="stylesheet">
</head>
<body class="brand-gradient">
<div class="login-wrap">
  <div class="card login-card border-0 shadow">
    <div class="brand-accent"></div>
    <div class="card-body p-4 text-center">
      <img src="<?php echo assetUrl('img/logo.png'); ?>" alt="Multi App" class="mb-3" style="height:92px;width:92px;object-fit:contain;">
      <h2 class="h5 text-brand mb-3">Cambiar contraseña</h2>
      <?php if ($forzado): ?>
        <div class="alert alert-warning py-2 text-start">Debes cambiar tu contraseña para continuar.</div>
      <?php endif; ?>
      <div id="alert" class="alert alert-danger d-none"></div>
      <div id="okAlert" class="alert alert-success d-none"></div>
      <form id="form" class="text-start">
        <div class="mb-3">
          <label class="form-label fw-semibold">Contraseña actual</label>
          <div class="input-group">
            <input type="password" class="form-control" id="actual" autocomplete="current-password" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('actual', this)" title="Mostrar/ocultar contraseña" tabindex="-1"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold">Nueva contraseña</label>
          <div class="input-group">
            <input type="password" class="form-control" id="nueva" autocomplete="new-password" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('nueva', this)" title="Mostrar/ocultar contraseña" tabindex="-1"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label fw-semibold">Confirmar nueva contraseña</label>
          <div class="input-group">
            <input type="password" class="form-control" id="confirmar" autocomplete="new-password" required>
            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('confirmar', this)" title="Mostrar/ocultar contraseña" tabindex="-1"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <small class="text-muted d-block mb-3">Mínimo 8 caracteres, con mayúscula, minúscula y número.</small>
        <button type="submit" class="btn btn-brand w-100" id="btn">Actualizar</button>
      </form>
    </div>
  </div>
</div>
<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const UI  = <?php echo json_encode(uiBaseUrl()); ?>;
const alertBox = document.getElementById('alert');
const okBox = document.getElementById('okAlert');

function togglePwd(id, btnEl){
  const input = document.getElementById(id);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btnEl.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

document.getElementById('form').addEventListener('submit', async (e) => {
  e.preventDefault();
  alertBox.classList.add('d-none'); okBox.classList.add('d-none');
  const nueva = document.getElementById('nueva').value;
  const confirmar = document.getElementById('confirmar').value;
  if (nueva !== confirmar) {
    alertBox.textContent = 'La nueva contraseña y su confirmación no coinciden.';
    alertBox.classList.remove('d-none');
    return;
  }
  document.getElementById('btn').disabled = true;
  try {
    const r = await fetch(API + 'auth/change_password', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
      body: JSON.stringify({ actual: document.getElementById('actual').value, nueva, confirmar })
    });
    const j = await r.json();
    if (!j.ok) throw new Error(j.error || 'Error');
    okBox.textContent = 'Contraseña actualizada. Vuelve a iniciar sesión.';
    okBox.classList.remove('d-none');
    setTimeout(() => location.href = UI + 'login', 1500);
  } catch (err) {
    alertBox.textContent = err.message; alertBox.classList.remove('d-none');
    document.getElementById('btn').disabled = false;
  }
});
</script>
</body>
</html>
