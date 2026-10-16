<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('creditos');
$pageTitle = 'Solicitudes de crédito';
$currentPage = 'creditos_lista';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-industry me-2"></i>Solicitudes de crédito</span>
    <span class="small text-white-50" id="infoTotal"></span>
    <a class="btn btn-light btn-sm" href="nueva_solicitud"><i class="fas fa-plus me-1"></i>Nueva solicitud</a>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:300px"><label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Nº solicitud, cédula, nombre…"></div></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="fEstado" style="width:150px"><option value="">Todos</option><option>solicitado</option><option>revisado</option><option>con_estudio</option><option>desembolsado</option><option>rechazado</option><option>cancelado</option></select></div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th class="sortable" data-col="numero_solicitud">Nº</th><th class="sortable" data-col="cedula_asociado">Cédula</th><th class="sortable" data-col="nombre_solicitante">Solicitante</th><th class="text-end sortable" data-col="monto_deseado">Monto deseado</th><th>Etapa</th><th>Estado</th><th>Docs</th><th class="sortable" data-col="fecha_creacion">Fecha</th><th></th></tr></thead>
        <tbody id="tbody"><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>, U=<?php echo json_encode(uiBaseUrl()); ?>, PAGE_SIZE=20;
const ESTETA={creacion:['Creación','primary'],revision:['Revisión','info'],estudio:['Estudio','warning'],consideracion:['Consideración','warning'],estudio_aprobado:['Aprobado','success'],estudio_rechazado:['Rechazado','danger'],radicado_tesoreria:['Radicado · Tesorería','dark'],final:['Final','dark']};
let state={q:'',estado:'',page:0,order:'id',dir:'DESC'},debounceTimer;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){return '$'+Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleDateString('es-CO');}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
document.querySelectorAll('.sortable').forEach(th=>{th.addEventListener('click',()=>{
  const col=th.dataset.col;
  if(state.order===col)state.dir=state.dir==='ASC'?'DESC':'ASC';
  else{state.order=col;state.dir='ASC';}
  state.page=0;cargar();
});});
function renderSortIcons(){
  document.querySelectorAll('.sortable').forEach(th=>{
    const col=th.dataset.col,active=state.order===col;
    th.classList.toggle('active',active);
    const txt=th.textContent.trim().replace(/[▲▼]/g,'');
    const arrow=active?(state.dir==='ASC'?'▲':'▼'):'';
    th.innerHTML=txt+(arrow?` <span class="sort-icon">${arrow}</span>`:'');
  });
}
document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
document.getElementById('fEstado').addEventListener('change',function(){state.estado=this.value;state.page=0;cargar();});
const ESTBADGE={solicitado:['solicitado','secondary'],revisado:['revisado','info'],con_estudio:['con estudio','warning'],desembolsado:['desembolsado','success'],rechazado:['rechazado','danger'],cancelado:['cancelado','secondary']};
async function cargar(){
  const off=state.page*PAGE_SIZE;
  try{
    const d=await api('creditos/listar?q='+encodeURIComponent(state.q)+'&estado='+state.estado+'&order='+state.order+'&dir='+state.dir+'&limit='+PAGE_SIZE+'&offset='+off);
    const total=d.total||0,rows=d.rows||[];
    document.getElementById('infoTotal').textContent=total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,total)} de ${total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=rows.map(r=>{
      const et=ESTETA[r.etapa_actual]||[r.etapa_actual,'secondary'], eb=ESTBADGE[r.estado]||[r.estado,'secondary'];
      return `<tr>
        <td><span class="badge text-bg-light">${esc(r.numero_solicitud)}</span></td>
        <td>${esc(r.cedula_asociado)}</td>
        <td><span class="fw-semibold">${esc(r.nombre_solicitante||'—')}</span></td>
        <td class="text-end">${money(r.monto_deseado)}</td>
        <td><span class="badge text-bg-${et[1]}">${et[0]}</span></td>
        <td><span class="badge text-bg-${eb[1]}">${eb[0]}</span></td>
        <td class="text-center">${r.num_docs||0}</td>
        <td><small>${fmtDt(r.fecha_creacion)}</small></td>
        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="gestionar_solicitud?id=${r.id}"><i class="fas fa-edit"></i> Gestionar</a></td>
      </tr>`;
    }).join('')||'<tr><td colspan="9" class="text-center text-muted">Sin solicitudes</td></tr>';
    renderPagination(total);
  }catch(e){alert(e.message);}
}
function renderPagination(t){
  const N=Math.ceil(t/PAGE_SIZE);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}
  const C=state.page;let pages=[];
  if(N<=10){for(let i=0;i<N;i++)pages.push(i);}
  else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h=[];if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);
  pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);
  document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}
function goPage(p){state.page=p;cargar();}
document.addEventListener('DOMContentLoaded',cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>