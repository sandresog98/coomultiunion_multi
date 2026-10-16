<?php
declare(strict_types=1);
require_once __DIR__ . '/_env.php';
if (PortalAuth::actor() !== null) {
    header('Location: ' . cx_url('inicio'));
    exit;
}
$prefill = trim((string) ($_GET['doc'] ?? ($_SESSION['cx_pending'] ?? '')));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Crear o restablecer contraseña · Portal de asociados</title>
<link rel="icon" href="<?= $APP ?>/assets/favicons/favicon.ico" sizes="any">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
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
        <div class="fw-semibold fs-5">Crear o restablecer contraseña</div>
        <div class="small text-white-50">Te enviaremos un código a tu correo</div>
      </div>
    </div>
    <div class="form-section">
      <div id="msg" class="d-none alert py-2 small"></div>
      <form id="crearForm" autocomplete="off">
        <div class="mb-4">
          <label class="form-label text-white">Número de cédula</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
            <input class="form-control" id="cedula" inputmode="numeric" autocomplete="off" required placeholder="Tu cédula" value="<?= htmlspecialchars($prefill) ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-portal-gradient" id="btnCrear"><i class="fa-solid fa-paper-plane me-1"></i> Enviar código</button>
      </form>
      <div class="auth-links text-center">
        <a href="<?= cx_url('login') ?>"><i class="fa-solid fa-arrow-left me-1"></i>Volver al ingreso</a>
      </div>
    </div>
  </div>
</div>
<script>
var API=<?= json_encode($API) ?>, CX=<?= json_encode($CX) ?>;
document.getElementById('crearForm').addEventListener('submit',async function(e){
  e.preventDefault();
  var msg=document.getElementById('msg'); msg.classList.add('d-none');
  var cedula=document.getElementById('cedula').value.trim();
  if(!cedula){ msg.className='alert py-2 small alert-danger'; msg.textContent='Ingrese su cédula.'; msg.classList.remove('d-none'); return; }
  var btn=document.getElementById('btnCrear'); btn.disabled=true;
  try{
    var r=await fetch(API+'/auth_emitir_codigo.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cedula:cedula})});
    var j=await r.json();
    if(!j.ok){ msg.className='alert py-2 small alert-danger'; msg.textContent=j.error||'Error'; msg.classList.remove('d-none'); btn.disabled=false; return; }
    var d=j.data||{};
    msg.className='alert py-2 small alert-success';
    msg.textContent=(d.enviado===false?'No pudimos enviar el correo':'Enviamos un código de verificación a tu correo '+(d.correo||''))+'. Revisa tu bandeja e ingrésalo en el siguiente paso.';
    msg.classList.remove('d-none');
    setTimeout(function(){ window.location=<?= json_encode(cx_url('clave/verificar')) ?>+'?doc='+encodeURIComponent(cedula); }, 1200);
  }catch(err){ msg.className='alert py-2 small alert-danger'; msg.textContent='Error de conexión'; msg.classList.remove('d-none'); btn.disabled=false; }
});
</script>
</body>
</html>