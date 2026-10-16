<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Certificados';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="portal-card">
  <div class="card-header-brand"><i class="fa-solid fa-file-circle-check"></i><div class="fw-semibold">Solicitar certificado</div></div>
  <div class="p-3">
    <div class="mb-2"><label class="form-label small text-muted mb-0">Tipo de certificado</label>
      <select class="portal-select form-select" id="cEsp"><option value="declaracion_renta">Declaración de renta</option><option value="estado_cuenta">Estado de cuenta</option></select></div>
    <div class="mb-2"><label class="form-label small text-muted mb-0">Año / período</label>
      <input type="number" class="portal-input form-control" id="cAnio" value="<?php echo date('Y'); ?>"></div>
    <button class="btn btn-portal-gradient" onclick="enviar()"><i class="fa-solid fa-paper-plane me-1"></i>Solicitar</button>
    <div id="msg" class="mt-2 small"></div>
    <hr><h6 class="fw-semibold">Mis certificados</h6><div id="list" class="text-center text-muted py-2">Cargando…</div>
  </div>
</div>
<script>
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function enviar(){
  var m=document.getElementById('msg');m.className='text-muted small';m.textContent='Enviando…';
  fetch(PAPI+'/crear.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tipo:'certificado',detalle:{especie:document.getElementById('cEsp').value,anio:document.getElementById('cAnio').value}})}).then(r=>r.json()).then(j=>{
    if(!j.ok){m.className='text-danger small';m.textContent=j.error;return;}m.className='text-success small';m.textContent='Solicitud '+j.data.solicitud.numero_radicado+' creada. El documento se generará y adjuntará al aprobar.';
  });
}
function listar(){
  fetch(PAPI+'/listar.php?tipo=certificado&limit=50').then(r=>r.json()).then(j=>{
    var rows=(j.data&&j.data.rows)||[];
    document.getElementById('list').innerHTML=rows.length?rows.map(s=>'<div class="d-flex justify-content-between border-bottom py-1"><span>'+esc(s.numero_radicado)+' · '+esc(s.estado_codigo)+'</span><a class="btn btn-sm btn-portal-outline" href="'+CX_BASE+'/modules/solicitudes/pages/detalle?id='+s.id+'">Ver</a></div>').join(''):'<span class="text-muted">Sin solicitudes</span>';
  }).catch(function(){document.getElementById('list').textContent='—';});
}
listar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>