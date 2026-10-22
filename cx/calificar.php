<?php
declare(strict_types=1);
require_once __DIR__ . '/_env.php';
$__envClass = 'env-' . preg_replace('/[^a-z0-9_-]/i', '', app_env());
$__envLabel = env_label();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Califica tu atención · Portal de asociados</title>
<link rel="icon" href="<?= $APP ?>/assets/favicons/favicon.ico" sizes="any">
<link href="<?= $APP ?>/assets/vendor/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link href="<?= $APP ?>/assets/vendor/fontawesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="<?= $APP ?>/assets/css/fonts.css" rel="stylesheet">
<link rel="stylesheet" href="<?= $APP ?>/assets/css/front.css">
<link rel="stylesheet" href="<?= $CX ?>/assets/cx.css">
<script src="<?= $APP ?>/assets/js/solicitud_detalle.js"></script>
</head>
<body class="portal <?php echo $__envClass; ?>">
<div class="portal-auth-screen">
  <div class="portal-shape s1"></div><div class="portal-shape s2"></div>
  <div class="card card-mobile">
    <div class="card-header">
      <div class="card-header-brand">
        <img src="<?= $APP ?>/assets/img/logo.png" alt="Multi Asociados">
        <div class="fw-semibold fs-5">Califica tu atención</div>
        <?php if ($__envLabel !== ''): ?><span class="env-badge ms-auto"><?php echo $__envLabel; ?></span><?php endif; ?>
      </div>
    </div>
    <div class="form-section">
      <div id="box" class="text-center text-white-50 py-3">Cargando…</div>
    </div>
  </div>
</div>
<script>
const CXAPI=<?= json_encode($API) ?>; // $APP . '/api/portal'
const SD=window.SolDetalle;
const TOKEN=new URLSearchParams(location.search).get('t')||'';
let STAR=0;
const box=document.getElementById('box');

function starsPick(){
  var s='<div id="starPick" class="mb-3 d-flex justify-content-center" style="font-size:2.4rem;letter-spacing:6px">';
  for(var n=1;n<=5;n++){ s+='<i class="fa-regular fa-star" style="cursor:pointer;color:#FCD329" onclick="setStar('+n+')"></i>'; }
  return s+'</div>';
}
function setStar(n){STAR=n;var el=document.getElementById('starPick').querySelectorAll('i');for(var i=0;i<el.length;i++){el[i].className=(i<n)?'fa-solid fa-star':'fa-regular fa-star';}}

function renderForm(d){
  box.classList.remove('text-center');
  box.innerHTML='<p class="text-white mb-1">Solicitud <b>'+SD.esc(d.numero_radicado)+'</b></p>'
    +'<p class="text-white-50 small mb-3">¿Cómo calificas la atención recibida?</p>'
    +starsPick()
    +'<textarea id="calCom" rows="3" class="form-control mb-3" placeholder="Comentario (opcional)"></textarea>'
    +'<button class="btn btn-portal-gradient w-100" id="btnCal" onclick="enviar()"><i class="fa-solid fa-star me-1"></i> Enviar calificación</button>'
    +'<div id="msg" class="text-center text-white-50 small mt-3"></div>';
}

function enviar(){
  if(!STAR){document.getElementById('msg').textContent='Selecciona una puntuación de 1 a 5 estrellas.';return;}
  var btn=document.getElementById('btnCal');btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin me-1"></i> Enviando…';
  fetch(CXAPI+'/solicitud_calificar_token.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({token:TOKEN,puntuacion:STAR,comentarios:(document.getElementById('calCom').value||'')})})
    .then(r=>r.json()).then(function(j){
      if(!j.ok){btn.disabled=false;btn.innerHTML='Enviar calificación';document.getElementById('msg').textContent=j.error||'Error';return;}
      box.classList.add('text-center');
      box.innerHTML='<div class="mb-2" style="font-size:2.6rem;color:#10b981"><i class="fa-solid fa-circle-check"></i></div>'
        +'<p class="text-white fw-semibold">¡Gracias por tu calificación!</p>'
        +'<div>'+SD.estrellasHtml(STAR)+'</div>';
    }).catch(function(){btn.disabled=false;btn.innerHTML='Enviar calificación';document.getElementById('msg').textContent='Error de conexión.';});
}

(function init(){
  if(!TOKEN){box.innerHTML='<div class="alert alert-danger">Enlace de calificación inválido o incompleto.</div>';return;}
  fetch(CXAPI+'/solicitud_calificar_token.php?token='+encodeURIComponent(TOKEN))
    .then(r=>r.json()).then(function(j){
      if(!j.ok){box.innerHTML='<div class="alert alert-warning">'+SD.esc(j.error||'Enlace inválido')+'</div>';return;}
      var d=j.data;
      if(d.puntuacion!=null){
        box.innerHTML='<p class="text-white fw-semibold">Esta solicitud ya fue calificada.</p>'
          +'<div>'+SD.estrellasHtml(d.puntuacion)+'</div>'
          +(d.comentarios?'<p class="text-white-50 small mt-2">'+SD.esc(d.comentarios)+'</p>':'');
        return;
      }
      renderForm(d);
    }).catch(function(){box.innerHTML='<div class="alert alert-danger">Error de conexión.</div>';});
})();
</script>
</body>
</html>
