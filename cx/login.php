<?php
declare(strict_types=1);
require_once __DIR__ . '/_env.php';
$__envClass = 'env-' . preg_replace('/[^a-z0-9_-]/i', '', app_env());
$__envLabel = env_label();
// Si ya está autenticado y entró aquí, va al inicio.
if (PortalAuth::actor() !== null) {
    header('Location: ' . cx_url('inicio'));
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ingreso · Portal de asociados</title>
<link rel="icon" href="<?= $APP ?>/assets/favicons/favicon.ico" sizes="any">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $APP ?>/assets/css/front.css">
<link rel="stylesheet" href="<?= $CX ?>/assets/cx.css">
</head>
<body class="portal <?php echo $__envClass; ?>">
<div class="portal-auth-screen">
  <div class="portal-shape s1"></div><div class="portal-shape s2"></div>
  <div class="card card-mobile">
    <div class="card-header">
      <div class="card-header-brand">
        <img src="<?= $APP ?>/assets/img/logo.png" alt="Multi Asociados">
        <div class="fw-semibold fs-5">Portal de asociados</div>
        <?php if ($__envLabel !== ''): ?><span class="env-badge ms-auto"><?php echo $__envLabel; ?></span><?php endif; ?>
      </div>
    </div>
    <div class="form-section">
      <div id="msg" class="d-none alert alert-danger py-2 small"></div>
      <form id="loginForm" autocomplete="off">
        <div class="mb-3">
          <label class="form-label text-white">Número de cédula</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
            <input class="form-control" id="cedula" name="cedula" inputmode="numeric" autocomplete="off" required placeholder="Ej. 1010101010">
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label text-white">Contraseña</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required placeholder="••••••••">
            <button type="button" class="btn-eye" id="togglePwd" tabindex="-1" aria-label="Ver contraseña"><i class="fa-solid fa-eye" id="togglePwdIcon"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-portal-gradient" id="btnLogin"><i class="fa-solid fa-right-to-bracket me-1"></i> Ingresar</button>
      </form>
      <div class="auth-links text-center">
        <a href="<?= cx_url('clave/crear') ?>"><i class="fa-solid fa-user-plus me-1"></i>¿No tienes contraseña? Créala</a>
      </div>
      <p class="text-center text-white-50 small mt-3 mb-0">Si es tu primer acceso, se te pedirá crear tu contraseña.</p>
      <div class="mt-3 d-flex justify-content-center">
        <span class="auth-bottom-logo"><img src="<?= $APP ?>/assets/img/logo_coomultiunion.png" alt="Coomultiunion"></span>
      </div>
    </div>
  </div>
</div>
<script>
var API=<?= json_encode($API) ?>;
document.getElementById('togglePwd').addEventListener('click',function(){
  var p=document.getElementById('password'), ic=document.getElementById('togglePwdIcon');
  var show=p.type==='password';
  p.type=show?'text':'password';
  ic.className='fa-solid '+(show?'fa-eye-slash':'fa-eye');
});
document.getElementById('loginForm').addEventListener('submit',async function(e){
  e.preventDefault();
  var msg=document.getElementById('msg'); msg.classList.add('d-none');
  var btn=document.getElementById('btnLogin'); btn.disabled=true;
  try{
    var r=await fetch(API+'/auth_login.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({cedula:document.getElementById('cedula').value.trim(),password:document.getElementById('password').value})});
    var j=await r.json();
    if(!j.ok){ msg.textContent=j.error||'Error'; msg.classList.remove('d-none'); btn.disabled=false; return; }
    if(j.data && j.data.pending){ window.location=<?= json_encode(cx_url('clave/crear')) ?>; return; }
    window.location=<?= json_encode(cx_url('inicio')) ?>;
  }catch(err){ msg.textContent='Error de conexión'; msg.classList.remove('d-none'); btn.disabled=false; }
});
</script>
</body>
</html>