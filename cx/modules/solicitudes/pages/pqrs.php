<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/_env.php';
$pageTitle = 'PQRS';
$activeNav = 'solicitudes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="portal-card">
  <div class="card-header-brand"><i class="fa-solid fa-headset"></i><div class="fw-semibold">Radicar una PQRS</div></div>
  <div class="p-3">
    <div class="row g-2 mb-2">
      <div class="col-md-4"><label class="form-label small text-muted mb-0">Tipo</label>
        <select class="portal-select form-select" id="pTipo"><option>Peticiones</option><option>Quejas</option><option>Reclamos</option><option>Sugerencias</option></select></div>
    </div>
    <div class="mb-2"><label class="form-label small text-muted mb-0">Resumen</label><input class="portal-input form-control" id="pRes" maxlength="200" placeholder="Asunto breve"></div>
    <div class="mb-2"><label class="form-label small text-muted mb-0">Descripción</label><textarea class="portal-input form-control" id="pDesc" rows="4" placeholder="Cuéntenos con detalle"></textarea></div>
    <div class="mb-2"><label class="form-label small text-muted mb-0">Adjuntos</label><input type="file" class="form-control" id="pFile" multiple></div>
    <button class="btn btn-portal-gradient" onclick="enviar()"><i class="fa-solid fa-paper-plane me-1"></i>Enviar PQRS</button>
    <div id="msg" class="mt-2 small"></div>
  </div>
</div>
<div class="portal-card mt-3">
  <div class="card-header-brand"><i class="fa-solid fa-list"></i><div class="fw-semibold">Mis PQRS</div></div>
  <div id="box" class="text-center text-muted py-4">Cargando…</div><div id="body"></div>
</div>
<script>
const PAPI=<?php echo json_encode($APP . '/api/solicitudes'); ?>;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function est(s){var m={pendiente:['Pendiente','bg-secondary'],recibido:['Recibido','bg-info'],asignado:['Asignado','bg-info'],aprobada:['Aprobada','bg-success'],rechazada:['Rechazada','bg-danger']};var e=m[s]||[s,'bg-secondary'];return '<span class="badge-etapa '+e[1]+'">'+e[0]+'</span>';}
function enviar(){
  var tipo=document.getElementById('pTipo').value,res=document.getElementById('pRes').value.trim(),desc=document.getElementById('pDesc').value.trim(),msg=document.getElementById('msg');
  if(!res||desc.length<20){msg.className='text-danger small';msg.textContent='Resumen obligatorio y descripción de al menos 20 caracteres.';return;}
  msg.className='text-muted small';msg.textContent='Enviando…';
  fetch(PAPI+'/crear.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({tipo:'pqrs',detalle:{tipo_pqrs:tipo,resumen:res,descripcion:desc}})}).then(r=>r.json()).then(function(j){
    if(!j.ok){msg.className='text-danger small';msg.textContent=j.error;return;}
    var files=document.getElementById('pFile').files;
    function upAdjs(i){
      if(i>=files.length){msg.className='text-success small';msg.textContent='Solicitud '+j.data.solicitud.numero_radicado+' radicada correctamente.';listar();location.reload();}
      else{var fd=new FormData();fd.append('id',j.data.solicitud.id);fd.append('file',files[i]);fd.append('contexto','solicitud');fetch(PAPI+'/adjuntar.php',{method:'POST',body:fd}).then(()=>upAdjs(i+1)).catch(()=>upAdjs(i+1));}
    }
    upAdjs(0);
  });
}
function listar(){
  fetch(PAPI+'/listar.php?tipo=pqrs&limit=50').then(r=>r.json()).then(j=>{
    var box=document.getElementById('box'),body=document.getElementById('body');
    if(!j.ok){box.textContent=j.error;return;}box.classList.add('d-none');
    var rows=j.data.rows||[];
    if(!rows.length){body.innerHTML='<div class="text-center text-muted py-3">Sin PQRS radicadas</div>';return;}
    window.cxTabla({el:'#body',porPagina:function(){return 5;},cols:[
      {label:'Radicado',val:function(s){return s.numero_radicado;}},
      {label:'Estado',val:function(s){return s.estado_codigo;},html:function(s){return est(s.estado_codigo);}},
      {label:'Fecha',val:function(s){return cxFecha(s.fecha_creacion);}},
      {label:'',sort:false,val:function(){return'';},html:function(s){return '<a class="btn btn-sm btn-portal-outline" href="'+CX_BASE+'/modules/solicitudes/pages/detalle?id='+s.id+'">Ver</a>';}},
    ],filas:rows});
  });
}
listar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>