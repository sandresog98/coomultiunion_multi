<?php
declare(strict_types=1);
require_once __DIR__ . '/_env.php';
if (PortalAuth::actor() !== null) {
    header('Location: ' . cx_url('inicio'));
    exit;
}
$doc = trim((string) ($_GET['doc'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verificar código · Portal de asociados</title>
<link rel="icon" href="<?= $APP ?>/assets/favicons/favicon.ico" sizes="any">
<link href="<?= $APP ?>/assets/vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $APP ?>/assets/vendor/fontawesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="<?= $APP ?>/assets/css/fonts.css" rel="stylesheet">
<link rel="stylesheet" href="<?= $APP ?>/assets/css/front.css">
<link rel="stylesheet" href="<?= $CX ?>/assets/cx.css">
</head>
<body class="portal">
<div class="portal-auth-screen">
  <div class="portal-shape s1"></div><div class="portal-shape s2"></div>
  <div class="card card-mobile">
    <div class="card-header">
      <div class="card-header-brand">
        <img src="<?= $APP ?>/assets/img/logo.png" alt="Multi Asociados">
        <div class="fw-semibold fs-5">Verificar código</div>
        <div class="small text-white-50">Crea o restablece tu contraseña</div>
      </div>
    </div>
    <div class="form-section">
      <div id="msg" class="d-none alert py-2 small"></div>
      <form id="verForm" autocomplete="off">
        <div class="mb-3">
          <label class="form-label text-white">Número de cédula</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
            <input class="form-control" id="cedula" inputmode="numeric" required placeholder="Tu cédula" value="<?= htmlspecialchars($doc) ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label text-white">Código de verificación</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-shield"></i></span>
            <input class="form-control" id="codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required placeholder="Código de 6 dígitos">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label text-white">Nueva contraseña</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input class="form-control" id="password" type="password" minlength="6" autocomplete="new-password" required placeholder="Mínimo 6 caracteres">
            <button type="button" class="btn-eye" id="togglePwd1" tabindex="-1" aria-label="Ver contraseña"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label text-white">Confirmar contraseña</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input class="form-control" id="password2" type="password" minlength="6" autocomplete="new-password" required placeholder="Repite la contraseña">
            <button type="button" class="btn-eye" id="togglePwd2" tabindex="-1" aria-label="Ver contraseña"><i class="fa-solid fa-eye"></i></button>
          </div>
        </div>
        <button type="submit" class="btn btn-portal-gradient" id="btnVer"><i class="fa-solid fa-check me-1"></i> Guardar contraseña</button>
      </form>
      <div class="auth-links text-center">
        <a href="<?= cx_url('clave/crear') ?>"><i class="fa-solid fa-arrow-left me-1"></i>Volver y reenviar código</a>
      </div>
    </div>
  </div>
</div>
<script>
var API=<?= json_encode($API) ?>, CX=<?= json_encode($CX) ?>;
function bindEye(btnId,inpId){ document.getElementById(btnId).addEventListener('click',function(){ var p=document.getElementById(inpId), ic=document.querySelector('#'+btnId+' i'); var show=p.type==='password'; p.type=show?'text':'password'; ic.className='fa-solid '+(show?'fa-eye-slash':'fa-eye'); }); }
bindEye('togglePwd1','password'); bindEye('togglePwd2','password2');
document.getElementById('verForm').addEventListener('submit',async function(e){
  e.preventDefault();
  var msg=document.getElementById('msg'); msg.classList.add('d-none');
  var p1=document.getElementById('password').value, p2=document.getElementById('password2').value;
  if(p1!==p2){ msg.className='alert py-2 small alert-danger'; msg.textContent='Las contraseñas no coinciden.'; msg.classList.remove('d-none'); return; }
  var btn=document.getElementById('btnVer'); btn.disabled=true;
  try{
    var r=await fetch(API+'/auth_confirmar_codigo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      cedula:document.getElementById('cedula').value.trim(), codigo:document.getElementById('codigo').value.trim(), password:p1})});
    var j=await r.json();
    if(!j.ok){ msg.className='alert py-2 small alert-danger'; msg.textContent=j.error||'Error'; msg.classList.remove('d-none'); btn.disabled=false; return; }
    msg.className='alert py-2 small alert-success'; msg.textContent='Contraseña guardada correctamente.'; msg.classList.remove('d-none');
    setTimeout(function(){ window.location=<?= json_encode(cx_url('login')) ?>+'?success=1'; }, 900);
  }catch(err){ msg.className='alert py-2 small alert-danger'; msg.textContent='Error de conexión'; msg.classList.remove('d-none'); btn.disabled=false; }
});
</script>
</body>
</html>