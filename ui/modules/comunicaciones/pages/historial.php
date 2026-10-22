<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('comunicaciones');
$pageTitle = 'Historial de comunicaciones';
$currentPage = 'comunicaciones_historial';
$acc = ui_acciones('comunicaciones.historial', $staff['rol'] ?? '');
$accPlan = ui_acciones('comunicaciones.planes', $staff['rol'] ?? '');
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-clock-rotate-left me-2"></i>Historial y costos</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
      <?php if (($acc['comunicaciones.historial.exportar'] ?? false) === true): ?>
      <button class="btn btn-light btn-sm" onclick="exportar()"><i class="fas fa-file-excel me-1"></i>Exportar</button>
      <?php endif; ?>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:320px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Destino, nombre, cédula o plan…"></div>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Canal</label>
        <select class="form-select" id="fCanal" data-searchable style="width:140px"><option value="">Todos</option><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="email">Email</option></select>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="fEstado" data-searchable style="width:150px"><option value="">Todos</option><option value="pendiente">Pendiente</option><option value="enviado">Enviado</option><option value="entregado">Entregado</option><option value="leido">Leído</option><option value="fallido">Fallido</option><option value="omitido">Omitido</option><option value="cancelado">Cancelado</option></select>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label><input type="date" class="form-control" id="fDesde" style="width:145px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label><input type="date" class="form-control" id="fHasta" style="width:145px"></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="fecha_creacion">Fecha</th>
          <th>Plan</th>
          <th class="sortable" data-col="destino">Destino</th>
          <th class="sortable" data-col="nombre">Nombre</th>
          <th class="sortable" data-col="canal">Canal</th>
          <th class="sortable text-center" data-col="estado">Estado</th>
          <th class="sortable text-end" data-col="precio_usd">Costo</th>
          <th>Error</th>
          <th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Modal detalle -->
<div class="modal fade" id="mDetalle" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-message me-2"></i>Detalle del mensaje</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="detalleBody"><div class="text-muted">Seleccione un mensaje.</div></div>
</div></div></div>

<script>
window.API=<?= json_encode(apiUrl()) ?>;
const U=<?= json_encode(uiBaseUrl()) ?>;
const L=window.Listado, esc=L.esc;
const ACC=<?= json_encode($acc, JSON_UNESCAPED_UNICODE) ?>;
const ACC_PLAN=<?= json_encode($accPlan, JSON_UNESCAPED_UNICODE) ?>;
let state={q:'',canal:'',estado:'',desde:'',hasta:'',page:0,size:20,order:'fecha_creacion',dir:'DESC'};
let t, modalDetalle;
const EST={pendiente:['Pendiente','secondary'],enviando:['Enviando','warning'],enviado:['Enviado','primary'],entregado:['Entregado','success'],leido:['Leído','success'],fallido:['Fallido','danger'],omitido:['Omitido','secondary'],cancelado:['Cancelado','secondary']};
function badge(e){const x=EST[e]||[e||'—','secondary'];return `<span class="badge text-bg-${x[1]}">${esc(x[0])}</span>`;}
function usd(v){return v==null?'—':'$'+(Number(v||0)).toLocaleString('en-US',{minimumFractionDigits:4,maximumFractionDigits:4})+' USD';}
function fmt(v){return v?new Date(String(v).replace(' ','T')+'Z').toLocaleString('es-CO',{dateStyle:'short',timeStyle:'short'}):'—';}
function showAlert(msg,ok){const a=document.getElementById('alert');a.className='alert '+(ok?'alert-success':'alert-danger');a.textContent=msg;a.classList.remove('d-none');}
async function api(p,o){return L.api(p,o);}
L.bindSort(document,state,cargar);
function renderSortIcons(){L.renderSortIcons(document,state);}
document.getElementById('q').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
[['fCanal','canal'],['fEstado','estado'],['fDesde','desde'],['fHasta','hasta']].forEach(([id,k])=>{
  document.getElementById(id).addEventListener('change',function(){state[k]=this.value;state.page=0;cargar();});
});
async function cargar(){
  const p=new URLSearchParams({q:state.q,canal:state.canal,estado:state.estado,desde:state.desde,hasta:state.hasta,page:state.page,size:state.size,order:state.order,dir:state.dir});
  try{
    const d=await api('comunicaciones/historial?'+p.toString());
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total?`Mostrando ${d.offset+1}-${Math.min(d.offset+state.size,total)} de ${total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(x=>`<tr>
      <td>${fmt(x.fecha_creacion)}</td>
      <td><a href="${U}modules/comunicaciones/pages/plan_detalle?id=${x.plan_id}">${esc(x.plan_nombre||'')}</a></td>
      <td>${esc(x.destino)}</td><td>${esc(x.nombre||'')}</td><td>${esc(x.canal)}</td>
      <td class="text-center">${badge(x.estado)}</td><td class="text-end">${usd(x.precio_usd)}</td>
      <td class="small text-danger">${esc(x.error_message||'')}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="ver(${x.id})" title="Detalle"><i class="fas fa-eye"></i></button></td>
    </tr>`).join('') || '<tr><td colspan="9" class="text-center text-muted">Sin mensajes</td></tr>';
    document.getElementById('paginacion').innerHTML=L.paginacion(total,state.page,state.size);
  }catch(e){showAlert(e.message,false);}
}
function goPage(p){state.page=p;cargar();}
function exportar(){
  const p=new URLSearchParams({q:state.q,canal:state.canal,estado:state.estado,desde:state.desde,hasta:state.hasta});
  location.href=window.API+'comunicaciones/historial_export?'+p.toString();
}
async function ver(id){
  modalDetalle=modalDetalle||new bootstrap.Modal(document.getElementById('mDetalle'));
  document.getElementById('detalleBody').innerHTML='<div class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Cargando…</div>';
  modalDetalle.show();
  try{
    const d=await api('comunicaciones/mensaje?id='+id);
    const m=d.mensaje;
    document.getElementById('detalleBody').innerHTML=`
      <div class="row g-3 small">
        <div class="col-md-5">
          <div class="text-muted">Destino</div><div class="fw-semibold">${esc(m.destino)}</div>
          <div class="text-muted mt-2">Nombre / cédula</div><div>${esc(m.nombre||'')} · ${esc(m.asociado_cedula||'')}</div>
          <div class="text-muted mt-2">Plan</div><div><a href="${U}modules/comunicaciones/pages/plan_detalle?id=${m.plan_id}">${esc(m.plan_nombre||'')}</a></div>
          <div class="text-muted mt-2">Estado</div><div>${badge(m.estado)} ${m.motivo_omitido?'<span class="text-muted">· '+esc(m.motivo_omitido)+'</span>':''}</div>
          <div class="text-muted mt-2">SID / ID proveedor</div><div class="font-monospace">${esc(m.proveedor_message_id||'—')}</div>
          <div class="text-muted mt-2">Costo</div><div>${usd(m.precio_usd)} ${m.precio_moneda?'('+esc(m.precio_moneda)+')':''}</div>
          <div class="text-muted mt-2">Error</div><div class="text-danger">${esc(m.error_message||'')} ${m.error_code?'('+m.error_code+')':''}</div>
        </div>
        <div class="col-md-7">
          <div class="text-muted">Contenido del plan</div>
          <div class="border rounded p-2 mb-2" style="white-space:pre-wrap">${esc(m.plan_cuerpo||'—')}</div>
          ${m.plan_cuerpo_html?`<div class="text-muted">Vista previa HTML</div><iframe id="msgHtmlPreview" sandbox="allow-same-origin" title="Vista previa HTML" style="width:100%;height:320px;border:1px solid #dee2e6;border-radius:8px;background:#fff;color-scheme:light" class="mb-2"></iframe>`:''}
          <div class="text-muted">Eventos</div>
          <div class="table-responsive" style="max-height:220px;overflow:auto"><table class="table table-sm mb-2"><thead><tr><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Mensaje</th></tr></thead><tbody>
            ${(d.eventos||[]).map(e=>`<tr><td>${fmt(e.fecha_creacion)}</td><td>${esc(e.tipo)}</td><td>${esc(e.estado||'')}</td><td class="text-danger">${esc(e.error_message||'')}</td></tr>`).join('')||'<tr><td colspan="4" class="text-muted">Sin eventos</td></tr>'}
          </tbody></table></div>
          <div class="text-muted">Respuestas entrantes</div>
          <div class="table-responsive" style="max-height:180px;overflow:auto"><table class="table table-sm mb-0"><thead><tr><th>Fecha</th><th>De</th><th>Cuerpo</th></tr></thead><tbody>
            ${(d.entrantes||[]).map(e=>`<tr><td>${fmt(e.fecha_creacion)}</td><td>${esc(e.de)}</td><td>${esc(e.cuerpo||'')}</td></tr>`).join('')||'<tr><td colspan="3" class="text-muted">Sin respuestas</td></tr>'}
          </tbody></table></div>
          ${['fallido','omitido'].includes(m.estado)&&ACC_PLAN['comunicaciones.planes.reintentar']?`<button class="btn btn-sm btn-outline-primary mt-2" onclick="reintentar(${m.id})"><i class="fas fa-rotate-right me-1"></i>Reintentar</button>`:''}
        </div>
      </div>`;
    const hp=document.getElementById('msgHtmlPreview');
    if(hp && m.plan_cuerpo_html){
      let h=String(m.plan_cuerpo_html);
      h=h.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'')
         .replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi,'')
         .replace(/javascript\s*:/gi,'');
      const abre=(h.match(/</g)||[]).length, cierra=(h.match(/>/g)||[]).length;
      hp.srcdoc=abre!==cierra
        ? '<!doctype html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;padding:16px;color:#b02a37"><h4>HTML incompleto</h4><p>El HTML guardado tiene etiquetas sin cerrar.</p></body></html>'
        : (/<html|<body/i.test(h) ? h : '<!doctype html><html><head><meta charset="utf-8"><style>html,body{color-scheme:light}body{font-family:Arial,Helvetica,sans-serif;padding:16px;background:#fff;color:#333;font-size:14px}</style></head><body>'+h+'</body></html>');
    }
  }catch(e){document.getElementById('detalleBody').innerHTML='<div class="alert alert-danger">'+esc(e.message)+'</div>';}
}
async function reintentar(id){
  try{await api('comunicaciones/mensaje',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});showAlert('Mensaje reencolado.',true);cargar();document.getElementById('mDetalle').querySelector('.btn-close').click();}catch(e){showAlert(e.message,false);}
}
L.mountSize('fSizeBox',state,cargar);
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
