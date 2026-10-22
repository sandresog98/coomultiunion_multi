<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Actualizar mi información';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="portal-card">
  <div class="card-header-brand"><i class="fa-solid fa-user-pen"></i><div class="fw-semibold">Solicitar actualización de datos</div></div>
  <div class="p-3">
    <div class="text-muted small mb-3">Solo se pueden actualizar algunos campos. El cambio se aplica tras la aprobación del área.</div>
    <div class="row g-2 mb-2">
      <div class="col-md-6"><label class="form-label small text-muted mb-0">Nombre</label><input class="portal-input form-control" id="fN" placeholder="Solo informativo (no editable aquí)"></div>
      <div class="col-md-6"><label class="form-label small text-muted mb-0">Celular</label><input class="portal-input form-control" id="fC"></div>
      <div class="col-md-6"><label class="form-label small text-muted mb-0">Email</label><input class="portal-input form-control" id="fE" type="email"></div>
      <div class="col-md-6"><label class="form-label small text-muted mb-0">Ciudad</label><input class="portal-input form-control" id="fCi"></div>
      <div class="col-md-12"><label class="form-label small text-muted mb-0">Dirección</label><input class="portal-input form-control" id="fD"></div>
      <div class="col-md-12"><label class="form-label small text-muted mb-0">Observaciones</label><textarea class="portal-input form-control" id="fO" rows="2"></textarea></div>
    </div>
    <button class="btn btn-portal-gradient" onclick="enviar()"><i class="fa-solid fa-paper-plane me-1"></i>Enviar solicitud</button>
    <div class="form-text small text-muted mt-2">Al enviar aceptas nuestra <a href="<?= $APP ?>/privacidad" target="_blank" rel="noopener">Política de Tratamiento de Datos Personales</a>.</div>
    <div id="msg" class="mt-2 small"></div>
  </div>
</div>
<script>
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function enviar(){
  var d={nombre:document.getElementById('fN').value.trim(),celular:document.getElementById('fC').value.trim(),email:document.getElementById('fE').value.trim(),ciudad:document.getElementById('fCi').value.trim(),direccion:document.getElementById('fD').value.trim(),observaciones:document.getElementById('fO').value.trim()};
  var msg=document.getElementById('msg');
  if(!(d.celular||d.email||d.ciudad||d.direccion||d.observaciones)){msg.className='text-danger small';msg.textContent='Ingrese al menos un dato a actualizar.';return;}
  fetch(PAPI+'/crear.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tipo:'actualizar_info',detalle:d})}).then(r=>r.json()).then(j=>{
    if(!j.ok){msg.className='text-danger small';msg.textContent=j.error;return;}
    msg.className='text-success small';msg.textContent='Solicitud '+j.data.solicitud.numero_radicado+' enviada. Será revisada por el área.';
  });
}
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>