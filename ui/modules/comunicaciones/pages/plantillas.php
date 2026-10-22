<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('comunicaciones');
$pageTitle = 'Plantillas de comunicación';
$currentPage = 'comunicaciones_plantillas';
$acc = ui_acciones('comunicaciones.plantillas', $staff['rol'] ?? '');
$sec = ui_secciones('comunicaciones.plantillas', $staff['rol'] ?? '');
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-file-lines me-2"></i>Plantillas</span>
    <span class="d-flex gap-2">
      <select class="form-select form-select-sm" id="fCanal" style="width:auto" onchange="cargar()">
        <option value="">Todas</option><option value="whatsapp">WhatsApp</option><option value="email">Email</option>
      </select>
      <?php if (($sec['comunicaciones.plantillas.sincronizar'] ?? false) && ($acc['comunicaciones.plantillas.sincronizar'] ?? false)): ?>
      <button class="btn btn-light btn-sm" id="btnSync" onclick="sincronizar()"><i class="fas fa-rotate me-1"></i>Sincronizar Twilio</button>
      <?php endif; ?>
      <?php if (($acc['comunicaciones.plantillas.crear'] ?? false)): ?>
      <button class="btn btn-light btn-sm" onclick="abrirLocal()"><i class="fas fa-plus me-1"></i>Plantilla email</button>
      <?php endif; ?>
    </span>
  </div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>
    <p class="small text-muted">Las plantillas de WhatsApp se sincronizan desde Twilio Content y deben estar aprobadas por Meta. Las plantillas de email son locales y reutilizables.</p>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Nombre</th><th>Canal</th><th>Categoría</th><th>Estado</th><th>Variables</th><th>Actualizada</th><th></th></tr></thead>
        <tbody id="tbody"><tr><td colspan="7" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal local -->
<div class="modal fade" id="mLocal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Plantilla de email</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" id="tNombre"></div>
      <div class="col-md-6"><label class="form-label">Asunto</label><input class="form-control" id="tAsunto"></div>
      <div class="col-12"><label class="form-label">Texto plano</label><textarea class="form-control" id="tCuerpo" rows="4"></textarea></div>
      <div class="col-12"><label class="form-label">HTML (opcional)</label><textarea class="form-control font-monospace" id="tHtml" rows="6"></textarea></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarLocal()">Guardar</button></div>
</div></div></div>

<!-- Modal detalle -->
<div class="modal fade" id="mDetalle" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="dNombre">Plantilla</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="dBody"></div>
</div></div></div>

<script>
window.API=<?= json_encode(apiUrl()) ?>;
const U=<?= json_encode(uiBaseUrl()) ?>;
const L=window.Listado, esc=L.esc;
const ACC=<?= json_encode($acc, JSON_UNESCAPED_UNICODE) ?>;
let datos=[], mLocal, mDetalle;
const APROB={approved:['Aprobada','success'],pending:['Pendiente','warning'],rejected:['Rechazada','danger'],paused:['Pausada','warning'],disabled:['Deshabilitada','secondary'],unsubmitted:['Sin enviar','secondary']};
function badge(e){const x=APROB[e]||[e||'—','secondary'];return `<span class="badge text-bg-${x[1]}">${esc(x[0])}</span>`;}
function showAlert(msg,ok){const a=document.getElementById('alert');a.className='alert '+(ok?'alert-success':'alert-danger');a.textContent=msg;a.classList.remove('d-none');}
async function api(p,o){return L.api(p,o);}
function post(path,data){return api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}
async function cargar(){
  try{
    const canal=document.getElementById('fCanal').value;
    datos=await api('comunicaciones/plantillas'+(canal?'?canal='+encodeURIComponent(canal):''));
    document.getElementById('tbody').innerHTML=(datos||[]).map(p=>`<tr>
      <td><button class="btn btn-link p-0 text-start" onclick="ver(${p.id})">${esc(p.nombre)}</button><div class="small text-muted">${esc(p.proveedor)}</div></td>
      <td>${esc(p.canal)}</td><td>${esc(p.categoria||'—')}</td><td>${badge(p.estado_aprobacion)}</td>
      <td class="small">${(p.variables||[]).length?esc((p.variables||[]).join(', ')):'—'}</td>
      <td class="small text-muted">${esc(p.fecha_sincronizacion||p.fecha_creacion||'—')}</td>
      <td class="text-end">${p.proveedor==='local'&&ACC['comunicaciones.plantillas.eliminar']?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar(${p.id})"><i class="fas fa-trash"></i></button>`:''}</td>
    </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted py-4">Sin plantillas</td></tr>';
  }catch(e){showAlert(e.message,false);}
}
async function sincronizar(){
  const b=document.getElementById('btnSync');b.disabled=true;
  try{const d=await post('comunicaciones/plantillas',{accion:'sincronizar'});showAlert('Sincronizadas '+(d.resultado.total||0)+' plantillas.',true);cargar();}catch(e){showAlert(e.message,false);}finally{b.disabled=false;}
}
function abrirLocal(){mLocal=mLocal||new bootstrap.Modal(document.getElementById('mLocal'));document.getElementById('tNombre').value='';document.getElementById('tAsunto').value='';document.getElementById('tCuerpo').value='';document.getElementById('tHtml').value='';mLocal.show();}
async function guardarLocal(){
  try{await post('comunicaciones/plantillas',{accion:'guardar_local',nombre:document.getElementById('tNombre').value,asunto:document.getElementById('tAsunto').value,cuerpo:document.getElementById('tCuerpo').value,cuerpo_html:document.getElementById('tHtml').value});mLocal.hide();showAlert('Plantilla guardada.',true);cargar();}catch(e){showAlert(e.message,false);}
}
async function eliminar(id){if(!confirm('¿Eliminar la plantilla?'))return;try{await api('comunicaciones/plantillas',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({accion:'eliminar',id})});showAlert('Plantilla eliminada.',true);cargar();}catch(e){showAlert(e.message,false);}}
function ver(id){
  const p=datos.find(x=>parseInt(x.id,10)===id);if(!p)return;
  mDetalle=mDetalle||new bootstrap.Modal(document.getElementById('mDetalle'));
  document.getElementById('dNombre').textContent=p.nombre;
  document.getElementById('dBody').innerHTML=`
    <div class="small text-muted mb-1">${esc(p.proveedor)} · ${esc(p.canal)} · ${badge(p.estado_aprobacion)}</div>
    ${p.asunto?`<div class="mb-2"><strong>Asunto:</strong> ${esc(p.asunto)}</div>`:''}
    ${p.cuerpo?`<div class="border rounded p-2 mb-2" style="white-space:pre-wrap">${esc(p.cuerpo)}</div>`:''}
    ${p.cuerpo_html?`<div class="text-muted mt-2">HTML</div><pre class="border rounded p-2 mb-2" style="max-height:300px;overflow:auto;white-space:pre-wrap">${esc(p.cuerpo_html)}</pre>`:''}
    ${(p.media||[]).length?`<div class="small text-muted">Media: ${esc((p.media||[]).join(', '))}</div>`:''}
    ${(p.variables||[]).length?`<div class="small text-muted">Variables: ${esc((p.variables||[]).join(', '))}</div>`:''}
    ${p.motivo_rechazo?`<div class="alert alert-danger mt-2 mb-0 small">${esc(p.motivo_rechazo)}</div>`:''}`;
  mDetalle.show();
}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
