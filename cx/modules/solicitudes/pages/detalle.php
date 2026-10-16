<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'Detalle de solicitud';
$activeNav = 'solicitudes';
$detId = (int) ($_GET['id'] ?? 0);
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="mb-2"><a class="btn btn-sm btn-portal-outline" href="<?= $CX ?>/modules/solicitudes/pages/mis_solicitudes"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a></div>
<div class="portal-card"><div class="card-header-brand"><i class="fa-solid fa-file-lines"></i><div class="fw-semibold">Detalle de solicitud</div></div>
  <div id="box" class="text-center text-muted py-4">Cargando…</div>
</div>
<script>
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>,DID=<?php echo $detId; ?>;
const TIPOS={'pqrs':'PQRS','actualizar_info':'Actualización','redencion':'Redención','nuevo_producto':'Nuevo producto','boleta':'Boletas','retiro_incentivos':'Retiro incentivos','certificado':'Certificado','fondo':'Fondo de bienestar','ticket':'Ticket','retiro_aporte':'Retiro de aportes'};
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function lst(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
(function init(){
  var box=document.getElementById('box');
  fetch(PAPI+'/detalle.php?id='+DID).then(r=>r.json()).then(j=>{
    if(!j.ok){if(j.error==='No autorizado'){window.location=CX_BASE+'/login';return;}box.innerHTML='<div class="alert alert-warning">'+esc(j.error)+'</div>';return;}
    var d=j.data,s=d.solicitud,det=d.detalle||{};box.classList.remove('text-center');box.classList.add('p-3');
    var h='<h5 class="fw-semibold">'+esc(s.numero_radicado)+' <span class="badge text-bg-primary">'+(TIPOS[s.tipo_codigo]||s.tipo_codigo)+'</span></h5>';
    h+='<div class="text-muted small mb-2">Estado: <b>'+esc(s.estado_codigo)+'</b> · Creada: '+cxFecha(s.fecha_creacion)+'</div>';
    h+='<hr><div class="portal-datos-grid">';
    Object.entries(det).forEach(([k,v])=>{if(v==null||v===''||v===0)return;h+='<div class="dato-item"><div class="dato-label">'+esc(k)+'</div><div class="dato-val">'+esc(v)+'</div></div>';});
    h+='</div>';
    if(s.tipo_codigo==='fondo'){
      if(d.elegibilidad){
        var el=d.elegibilidad;
        h+='<div class="mt-3 p-2 rounded '+(el.apto?'alert-success':'alert-danger')+'"><i class="fa-solid '+(el.apto?'fa-circle-check':'fa-circle-xmark')+' me-1"></i><b>Elegibilidad:</b> '+esc(el.mensaje)+'</div>';
      }
      if(det.ruta_pdf_obligatorio){
        h+='<a class="btn btn-sm btn-portal-outline mt-2" target="_blank" href="'+CX_API+'/fondo/archivo.php?id='+DID+'"><i class="fa-solid fa-file-pdf me-1"></i> Ver PDF adjunto</a>';
      }
    }
    if(d.boletas&&d.boletas.length){h+='<h6 class="mt-3 fw-semibold">Boletas emitidas</h6>';d.boletas.forEach(b=>{h+='<div class="border rounded p-2 mb-1"><i class="fa-solid fa-ticket me-1"></i>'+esc(b.producto_nombre||'')+' · serial '+esc(b.serial)+' · <b>'+esc(b.estado)+'</b></div>';});}
    h+='<hr><h6 class="fw-semibold">Comentarios</h6><div id="cmts">'+((d.comentarios||[]).map(c=>'<div class="border rounded p-2 mb-2"><small class="text-muted">'+esc(c.tipo)+' · '+cxFecha(c.fecha_creacion)+'</small><div>'+esc(c.mensaje)+'</div></div>').join('')||'<span class="text-muted">Sin comentarios</span>')+'</div>';
    h+='<hr><div class="input-group input-group-sm"><input id="cmt" class="portal-input form-control" placeholder="Escribir comentario…"><button class="btn btn-portal-gradient" onclick="sendCmt()">Enviar</button></div>';
    if(d.adjuntos&&d.adjuntos.length){h+='<hr><h6 class="fw-semibold">Adjuntos</h6>'+(d.adjuntos.map(a=>'<a class="btn btn-sm btn-portal-outline me-1 mb-1" target="_blank" href="'+PAPI+'/serve_adjunto.php?id='+a.id+'"><i class="fa-solid fa-file"></i> '+esc(a.nombre_original||a.ruta_archivo)+'</a>').join(''));}
    box.innerHTML=h;
  }).catch(e=>{box.innerHTML='<div class="alert alert-danger">Error de conexión.</div>';});
  window.sendCmt=function(){var c=document.getElementById('cmt').value.trim();if(!c)return;
    fetch(PAPI+'/comentar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:DID,mensaje:c})}).then(r=>r.json()).then(j=>{if(!j.ok){alert(j.error);return;}location.reload();});};
})();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>