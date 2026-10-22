<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('comunicaciones');
$pageTitle = 'Planes de comunicación';
$currentPage = 'comunicaciones_planes';
$acc = ui_acciones('comunicaciones.planes', $staff['rol'] ?? '');
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-calendar-check me-2"></i>Planes de comunicación</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
      <a class="btn btn-light btn-sm" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/nueva"><i class="fas fa-plus me-1"></i>Nueva</a>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:300px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Nombre o descripción…"></div>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Canal</label>
        <select class="form-select" id="fCanal" data-searchable style="width:140px"><option value="">Todos</option><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="email">Email</option></select>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="fEstado" data-searchable style="width:160px"><option value="">Todos</option><option value="borrador">Borrador</option><option value="programado">Programado</option><option value="en_proceso">En proceso</option><option value="completado">Completado</option><option value="parcial">Parcial</option><option value="fallido">Fallido</option><option value="cancelado">Cancelado</option></select>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label><input type="date" class="form-control" id="fDesde" style="width:145px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label><input type="date" class="form-control" id="fHasta" style="width:145px"></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="nombre">Plan</th>
          <th class="sortable" data-col="canal">Canal</th>
          <th class="sortable" data-col="programado_para">Programado</th>
          <th class="sortable text-center" data-col="estado">Estado</th>
          <th class="sortable text-end" data-col="total_destinatarios">Dest.</th>
          <th class="text-end">Env.</th>
          <th class="text-end">Fall.</th>
          <th class="sortable text-end" data-col="costo_total_usd">Costo</th>
          <th class="text-end">Acciones</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<script>
window.API=<?= json_encode(apiUrl()) ?>;
const U=<?= json_encode(uiBaseUrl()) ?>;
const L=window.Listado, esc=L.esc;
const SEC=<?= json_encode($acc, JSON_UNESCAPED_UNICODE) ?>;
let state={q:'',canal:'',estado:'',desde:'',hasta:'',page:0,size:20,order:'id',dir:'DESC'};
let t;
const EST={borrador:['Borrador','secondary'],programado:['Programado','primary'],en_proceso:['En proceso','warning'],completado:['Completado','success'],parcial:['Parcial','warning'],cancelado:['Cancelado','secondary'],fallido:['Fallido','danger']};
function badge(e){const x=EST[e]||[e||'—','secondary'];return `<span class="badge text-bg-${x[1]}">${esc(x[0])}</span>`;}
function usd(v){return '$'+(Number(v||0)).toLocaleString('en-US',{minimumFractionDigits:4,maximumFractionDigits:4})+' USD';}
function showAlert(msg,ok){const a=document.getElementById('alert');a.className='alert '+(ok?'alert-success':'alert-danger');a.textContent=msg;a.classList.remove('d-none');}
async function api(p,o){return L.api(p,o);}
function post(path,data){return api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}
L.bindSort(document,state,cargar);
function renderSortIcons(){L.renderSortIcons(document,state);}

document.getElementById('q').addEventListener('input',()=>{clearTimeout(t);t=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
[['fCanal','canal'],['fEstado','estado'],['fDesde','desde'],['fHasta','hasta']].forEach(([id,k])=>{
  document.getElementById(id).addEventListener('change',function(){state[k]=this.value;state.page=0;cargar();});
});

async function cargar(){
  const p=new URLSearchParams({q:state.q,canal:state.canal,estado:state.estado,desde:state.desde,hasta:state.hasta,page:state.page,size:state.size,order:state.order,dir:state.dir});
  try{
    const d=await api('comunicaciones/planes?'+p.toString());
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total?`Mostrando ${d.offset+1}-${Math.min(d.offset+state.size,total)} de ${total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(x=>`<tr>
      <td><a href="${U}modules/comunicaciones/pages/plan_detalle?id=${x.id}" class="fw-semibold">${esc(x.nombre)}</a><div class="small text-muted">${esc(x.tipo||'')} · ${esc(x.creado_por_nombre||'')}</div></td>
      <td>${esc(x.canal)}</td>
      <td>${esc(x.programado_para_local||'—')}</td>
      <td class="text-center">${badge(x.estado)}</td>
      <td class="text-end">${x.total_destinatarios||0}</td>
      <td class="text-end">${x.total_enviados||0}</td>
      <td class="text-end ${x.total_fallidos>0?'text-danger':''}">${x.total_fallidos||0}</td>
      <td class="text-end">${usd(x.costo_total_usd||x.costo_estimado_usd)}${Number(x.ia_costo_usd||0)>0?`<div class="small text-muted">IA ${usd(x.ia_costo_usd)}</div>`:''}</td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-outline-primary" href="${U}modules/comunicaciones/pages/plan_detalle?id=${x.id}" title="Ver"><i class="fas fa-eye"></i></a>
        ${SEC['comunicaciones.planes.duplicar']&&['borrador','programado','completado','parcial','cancelado'].includes(x.estado)?`<button class="btn btn-sm btn-outline-secondary" onclick="duplicar(${x.id})" title="Duplicar"><i class="fas fa-copy"></i></button>`:''}
        ${SEC['comunicaciones.planes.cancelar']&&['borrador','programado','en_proceso'].includes(x.estado)?`<button class="btn btn-sm btn-outline-warning" onclick="cancelar(${x.id})" title="Cancelar"><i class="fas fa-ban"></i></button>`:''}
        ${SEC['comunicaciones.planes.eliminar']&&['borrador','cancelado'].includes(x.estado)?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar(${x.id})" title="Eliminar"><i class="fas fa-trash"></i></button>`:''}
      </td>
    </tr>`).join('') || '<tr><td colspan="9" class="text-center text-muted">Sin planes</td></tr>';
    document.getElementById('paginacion').innerHTML=L.paginacion(total,state.page,state.size);
  }catch(e){showAlert(e.message,false);}
}
function goPage(p){state.page=p;cargar();}
async function cancelar(id){if(!confirm('¿Cancelar el plan? Los mensajes pendientes no se enviarán.'))return;try{await post('comunicaciones/plan',{id,accion:'cancelar'});showAlert('Plan cancelado.',true);cargar();}catch(e){showAlert(e.message,false);}}
async function duplicar(id){try{const d=await post('comunicaciones/plan',{id,accion:'duplicar'});showAlert('Plan duplicado.',true);location.href=U+'modules/comunicaciones/pages/plan_detalle?id='+d.id;}catch(e){showAlert(e.message,false);}}
async function eliminar(id){if(!confirm('¿Eliminar el plan definitivamente?'))return;try{await api('comunicaciones/planes',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});showAlert('Plan eliminado.',true);cargar();}catch(e){showAlert(e.message,false);}}
L.mountSize('fSizeBox',state,cargar);
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
