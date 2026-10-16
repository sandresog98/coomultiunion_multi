<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ' . $CX . '/modules/solicitud_credito/pages/mis_solicitudes'); exit; }
$pageTitle = 'Autorizar solicitud';
$activeNav = 'solic';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="row g-3" style="max-width:720px">
  <div class="col-12" id="box" class="text-center text-muted py-5">Cargando…</div>
</div>
<div class="portal-card portal-card-glowing-border d-none" id="card">
  <div class="card-header-brand mb-3"><i class="fa-solid fa-file-signature fa-lg"></i><div class="fw-semibold">Autorizar y aceptar gestión</div></div>
  <div id="sum"></div>
  <div id="msg" class="d-none alert py-2 small"></div>
  <form id="acForm">
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="habeas">
      <label class="form-check-label small" for="habeas">Autorizo el tratamiento de datos personales y consulta en centrales de riesgo (Ley 1581 de 2012) <span class="text-danger">*</span></label>
    </div>
    <div class="form-check mb-2">
      <input class="form-check-input" type="checkbox" id="ofertas">
      <label class="form-check-label small" for="ofertas">Acepto recibir ofertas comerciales</label>
    </div>
    <button type="submit" class="btn btn-portal-gradient mt-2"><i class="fa-solid fa-check me-1"></i> Autorizar y aceptar</button>
  </form>
</div>
<script>
var SOLID=<?= $id ?>;
(async function(){
  var box=document.getElementById('box'), card=document.getElementById('card');
  var r=await fetch(CX_API+'/documentos.php?id='+SOLID); var j=await r.json();
  if(!j.ok){ box.textContent=j.error||'Error'; return; }
  var s=j.data.solicitud;
  box.classList.add('d-none'); card.classList.remove('d-none');
  if(s.aceptado_por_asociado===1){ card.innerHTML='<div class="alert alert-success mb-0">Ya autorizaste esta solicitud.</div>'; return; }
  if(s.etapa_actual!=='creacion' && s.etapa_actual!=='revision'){ card.innerHTML='<div class="alert alert-info mb-0">Esta solicitud ya no está en etapa de autorización.</div>'; return; }
  document.getElementById('sum').innerHTML='<div class="portal-datos-grid mb-3">'
    +[['Solicitud',s.numero_solicitud],['Monto deseado',money(s.monto_deseado)],['Cuotas',s.numero_cuotas_deseadas],['Etapa',s.etapa_actual]].map(function(f){
      return '<div class="dato-item"><div class="dato-label">'+esc(f[0])+'</div><div class="dato-val">'+esc(f[1])+'</div></div>';
    }).join('')+'</div>';
  document.getElementById('acForm').addEventListener('submit',async function(e){
    e.preventDefault();
    var msg=document.getElementById('msg'); msg.classList.add('d-none');
    if(!document.getElementById('habeas').checked){ msg.className='alert py-2 small alert-danger'; msg.textContent='Debe autorizar el tratamiento de datos.'; msg.classList.remove('d-none'); return; }
    var items=[{config_aceptacion_id:11,aceptado:true}];
    if(document.getElementById('ofertas').checked)items.push({config_aceptacion_id:12,aceptado:true});
    try{
      var r=await fetch(CX_API+'/solicitudes_aceptar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:SOLID,items:items})});
      var j=await r.json();
      if(!j.ok){ msg.className='alert py-2 small alert-danger'; msg.textContent=j.error||'Error'; msg.classList.remove('d-none'); return; }
      msg.className='alert py-2 small alert-success'; msg.textContent='Solicitud autorizada correctamente.'; msg.classList.remove('d-none');
      setTimeout(function(){window.location=''+CX_BASE+'/modules/solicitud_credito/pages/mis_solicitudes';},900);
    }catch(err){ msg.className='alert py-2 small alert-danger'; msg.textContent='Error de conexión'; msg.classList.remove('d-none'); }
  });
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>