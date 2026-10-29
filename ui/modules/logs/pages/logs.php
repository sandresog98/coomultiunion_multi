<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('logs.gestion');
$pageTitle = 'Logs del Sistema';
$currentPage = 'logs';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-clipboard-list me-2"></i>Logs del Sistema</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:300px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="q" placeholder="Buscar en detalle…">
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Usuario</label>
        <select class="form-select" id="fUsuario" data-searchable style="width:150px"><option value="">Todos</option></select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Módulo</label>
        <select class="form-select" id="fModulo" data-searchable style="width:150px"><option value="">Todos</option></select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Acción</label>
        <select class="form-select" id="fAccion" data-searchable style="width:140px"><option value="">Todas</option></select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Nivel</label>
        <select class="form-select" id="fNivel" data-searchable style="width:130px"><option value="">Todos</option></select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Desde</label>
        <input type="date" class="form-control" id="fDesde" style="width:140px">
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Hasta</label>
        <input type="date" class="form-control" id="fHasta" style="width:140px">
      </div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="id">ID</th>
          <th class="sortable" data-col="id_usuario">Usuario</th>
          <th class="sortable" data-col="accion">Acción</th>
          <th class="sortable" data-col="modulo">Módulo</th>
          <th>Detalle</th>
          <th>IP</th>
          <th class="sortable" data-col="timestamp" style="min-width:130px">Fecha</th>
          <th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3" aria-label="Paginación"></nav>
  </div>
</div>

<!-- Modal Detalle -->
<div class="modal fade" id="logDetailModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Detalle del Log</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="logDetailContent"><div class="text-center text-muted p-3">Seleccione un log para ver detalles</div></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
</div></div></div>



<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const alertBox=document.getElementById('alert');
let state = { q: '', usuario: '', modulo: '', accion: '', nivel: '', desde: '', hasta: '', page: 0, size: 20, order: 'id', dir: 'DESC' };
let debounceTimer, detailModal;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function intv(v){v=parseInt(v);return isNaN(v)?'—':v.toLocaleString('es-CO');}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
// ── Ordenamiento ──
document.querySelectorAll('.sortable').forEach(th=>{
  th.addEventListener('click',()=>{
    const col = th.dataset.col;
    if(state.order === col) state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
    else { state.order = col; state.dir = 'ASC'; }
    state.page = 0;
    cargar();
  });
});
function renderSortIcons(){
  document.querySelectorAll('.sortable').forEach(th=>{
    const col = th.dataset.col;
    const active = state.order === col;
    th.classList.toggle('active', active);
    const txt = th.textContent.trim().replace(/[▲▼]/g,'');
    const arrow = active ? (state.dir === 'ASC' ? '▲' : '▼') : '';
    th.innerHTML = txt + (arrow ? ` <span class="sort-icon">${arrow}</span>` : '');
  });
}

// ── Filtros ──
function buildParams(){
  const p = [];
  {const v=state.q.trim(); if(v) p.push('q='+encodeURIComponent(v));}
  {const v=document.getElementById('fUsuario').value; if(v) p.push('usuario='+v);}
  {const v=document.getElementById('fModulo').value; if(v) p.push('modulo='+v);}
  {const v=document.getElementById('fAccion').value; if(v) p.push('accion='+v);}
  {const v=document.getElementById('fNivel').value; if(v) p.push('nivel='+v);}
  {const v=document.getElementById('fDesde').value; if(v) p.push('fecha_desde='+v);}
  {const v=document.getElementById('fHasta').value; if(v) p.push('fecha_hasta='+v);}
  p.push('limit='+state.size+'&offset='+(state.page*state.size)+'&order='+state.order+'&dir='+state.dir);
  return p.join('&');
}

document.getElementById('q').addEventListener('input',()=>{
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(()=>{ state.q = document.getElementById('q').value.trim(); state.page = 0; cargar(); }, 300);
});
document.querySelectorAll('#fUsuario,#fModulo,#fAccion,#fNivel,#fDesde,#fHasta').forEach(el=>{
  el.addEventListener('change',()=>{ state.page = 0; cargar(); });
});

// ── Datos ──
async function cargar(){
  try{
    const d = await api('logs/listar?'+buildParams());
    const total = d.total || 0, offset = d.offset || 0;
    document.getElementById('infoTotal').textContent = total > 0 ? `Mostrando ${offset+1}-${Math.min(offset+state.size, total)} de ${intv(total)}` : '';
    renderSortIcons();
    const badges = {crear:'success', editar:'warning', eliminar:'danger', login:'info', descargar:'secondary'};
    document.getElementById('tbody').innerHTML = (d.rows||[]).map(l=>`<tr>
      <td><small>#${l.id}</small></td>
      <td>${esc(l.nombre_usuario||l.id_usuario||'Sistema')}</td>
      <td><span class="badge text-bg-${badges[l.accion]||'secondary'}">${esc(l.accion)}</span></td>
      <td><span class="badge text-bg-light">${esc(l.modulo)}</span></td>
      <td class="text-truncate" style="max-width:260px">${esc(l.detalle||'—')}</td>
      <td><small>${esc(l.ip_address||'—')}</small></td>
      <td><small>${l.timestamp?new Date(l.timestamp).toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—'}</small></td>
      <td><button class="btn btn-sm btn-outline-info" onclick="verDetalle(${l.id})"><i class="fas fa-eye"></i></button></td>
    </tr>`).join('') || '<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

// ── Paginación ──
function renderPagination(total){
  const N = Math.ceil(total / state.size);
  if (N <= 1) { document.getElementById('paginacion').innerHTML = ''; return; }
  const C = state.page;
  let pages = [];
  if (N <= 10) { for (let i = 0; i < N; i++) pages.push(i); }
  else {
    pages.push(0);
    if (C > 3) pages.push('...');
    for (let i = Math.max(1, C-2); i <= Math.min(N-2, C+2); i++) pages.push(i);
    if (C < N-4) pages.push('...');
    pages.push(N-1);
  }
  let html = [];
  if (C > 0) html.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);
  pages.forEach(p => {
    if (p === '...') html.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
    else html.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);
  });
  if (C < N-1) html.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);
  document.getElementById('paginacion').innerHTML = `<ul class="pagination pagination-sm justify-content-center mb-0">${html.join('')}</ul>`;
}
function goPage(p){state.page = p; document.querySelector('.card-body').scrollIntoView({behavior:'smooth'}); cargar();}

// ── Modal detalle ──
async function verDetalle(id){
  detailModal.show();
  document.getElementById('logDetailContent').innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div> Cargando...</div>';
  try{
    const l = await api('logs/detalle?id='+id);
    if(!l){ document.getElementById('logDetailContent').innerHTML='<div class="alert alert-warning mb-0">Log no encontrado.</div>'; return; }
    const niveles = {error:'danger',warning:'warning',critical:'danger',info:'info'};
    let html=`<div class="row"><div class="col-md-6">
      <table class="table table-sm"><tbody>
        <tr><td><strong>ID</strong></td><td>#${l.id}</td></tr>
        <tr><td><strong>Usuario</strong></td><td>${esc(l.nombre_usuario||l.login_usuario||l.id_usuario||'Sistema')}</td></tr>
        <tr><td><strong>Módulo</strong></td><td><span class="badge text-bg-light">${esc(l.modulo)}</span></td></tr>
        <tr><td><strong>Acción</strong></td><td><span class="badge text-bg-info">${esc(l.accion)}</span></td></tr>
        <tr><td><strong>Nivel</strong></td><td><span class="badge text-bg-${niveles[l.nivel]||'secondary'}">${esc(l.nivel)}</span></td></tr>
        <tr><td><strong>Fecha</strong></td><td>${new Date(l.timestamp).toLocaleString('es-CO')}</td></tr>
        <tr><td><strong>IP</strong></td><td><code>${esc(l.ip_address||'—')}</code></td></tr>
      </tbody></table></div>
      <div class="col-md-6"><h6 class="fw-bold">Detalle</h6><div class="border rounded p-2 bg-light">${esc(l.detalle||'Sin descripción')}</div></div></div>`;
    if(l.datos_anteriores_parsed){
      html+='<hr><h6 class="text-warning fw-bold"><i class="fas fa-arrow-left me-1"></i>Datos anteriores</h6>';
      html+='<div class="table-responsive"><table class="table table-sm table-warning"><tbody>';
      Object.entries(l.datos_anteriores_parsed).forEach(([k,v])=>{
        html+=`<tr><td><strong>${esc(k)}</strong></td><td>${typeof v==='object'?JSON.stringify(v):esc(String(v))}</td></tr>`;
      });
      html+='</tbody></table></div>';
    }
    if(l.datos_nuevos_parsed){
      html+='<hr><h6 class="text-success fw-bold"><i class="fas fa-arrow-right me-1"></i>Datos nuevos</h6>';
      html+='<div class="table-responsive"><table class="table table-sm table-success"><tbody>';
      Object.entries(l.datos_nuevos_parsed).forEach(([k,v])=>{
        html+=`<tr><td><strong>${esc(k)}</strong></td><td>${typeof v==='object'?JSON.stringify(v):esc(String(v))}</td></tr>`;
      });
      html+='</tbody></table></div>';
    }
    document.getElementById('logDetailContent').innerHTML = html;
  }catch(e){document.getElementById('logDetailContent').innerHTML=`<div class="alert alert-danger">${e.message}</div>`;}
}

// ── Inicializar ──
async function initOpciones(){
  try{
    const d = await api('logs/estadisticas');
    const op = d.opciones||{};
    populateSelect('fUsuario', (op.usuarios||[]).map(u=>`<option value="${u.id}">${esc(u.nombre_completo||u.usuario)}</option>`));
    populateSelect('fModulo', (op.modulos||[]).map(m=>`<option value="${m}">${esc(m)}</option>`));
    populateSelect('fAccion', (op.acciones||[]).map(a=>`<option value="${a}">${esc(a)}</option>`));
    populateSelect('fNivel', (op.niveles||[]).map(n=>`<option value="${n}">${esc(n)}</option>`));
  }catch(e){alertBox.className='alert alert-warning';alertBox.textContent='No se pudieron cargar los filtros.';alertBox.classList.remove('d-none');}
}
function populateSelect(id, options){
  const sel = document.getElementById(id);
  sel.innerHTML = sel.options[0]?.outerHTML + options.join('');
}

document.addEventListener('DOMContentLoaded',()=>{
  detailModal = new bootstrap.Modal('#logDetailModal');
  window.Listado.mountSize('fSizeBox', state, cargar);
  initOpciones();
  cargar();
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
