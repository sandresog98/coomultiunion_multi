<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina');
$acc = ui_acciones('config', $staff['rol'] ?? '');
$canCrear = ui_accion('config', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('config', 'editar',   $staff['rol'] ?? '');
$canElim  = ui_accion('config', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Configuración';
$currentPage = 'config';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-sliders me-2"></i>Productos y cláusulas</span>
    <span class="small text-white-50" id="infoTotal"></span>
    <?php if ($canCrear): ?>
    <button class="btn btn-light btn-sm" onclick="abrir()"><i class="fas fa-plus me-1"></i>Nuevo</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:280px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="q" placeholder="Nombre o descripción…">
        </div>
      </div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="nombre">Nombre</th>
          <th class="sortable" data-col="tipo">Tipo</th>
          <th class="text-end">Rango</th>
          <th class="sortable text-center" data-col="prioridad">Prioridad</th>
          <th class="text-center">Archivo</th>
          <th class="sortable text-center" data-col="estado_activo">Activo</th>
          <th class="text-end">Acciones</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="7" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3" aria-label="Paginación"></nav>
  </div>
</div>

<div class="modal fade" id="m" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mt">Nuevo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label><input class="form-control" id="f_nombre" required></div>
    <div class="mb-2"><label class="form-label">Tipo</label><select class="form-select" id="f_tipo"><option value="producto">Producto</option><option value="clausula">Cláusula</option></select></div>
    <div class="mb-2"><label class="form-label">Descripción</label><textarea class="form-control" id="f_descripcion" rows="2"></textarea></div>
    <div class="mb-2"><label class="form-label">Parámetros</label><textarea class="form-control" id="f_parametros" rows="2"></textarea></div>
    <div class="row g-2">
      <div class="col"><label class="form-label">Valor mínimo</label><input type="number" class="form-control" id="f_valor_minimo" step="0.01" value="0"></div>
      <div class="col"><label class="form-label">Valor máximo</label><input type="number" class="form-control" id="f_valor_maximo" step="0.01" value="0"></div>
    </div>
    <div class="row g-2 mt-2">
      <div class="col"><label class="form-label">Prioridad</label><input type="number" class="form-control" id="f_prioridad" value="100"></div>
      <div class="col d-flex align-items-end gap-3 pb-1">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="f_requiere_archivo"><label class="form-check-label" for="f_requiere_archivo">Requiere archivo</label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="f_estado_activo" checked><label class="form-check-label" for="f_estado_activo">Activo</label></div>
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-brand" onclick="guardar()">Guardar</button>
  </div>
</div></div></div>



<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const ACC = <?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['config.editar']===true,CAN_ELIM=ACC['config.eliminar']===true;
const PAGE_SIZE = 20;
const alertBox=document.getElementById('alert');
let state = { q: '', page: 0, order: 'tipo', dir: 'ASC' };
let editId = null, modal, debounceTimer;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

// ── Ordenamiento ──
document.querySelectorAll('.sortable').forEach(th=>{
  th.addEventListener('click',()=>{
    const col = th.dataset.col;
    if(state.order === col) state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
    else { state.order = col; state.dir = 'ASC'; }
    state.page = 0; cargar();
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

// ── Búsqueda ──
document.getElementById('q').addEventListener('input',()=>{
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(()=>{ state.q = document.getElementById('q').value.trim(); state.page = 0; cargar(); }, 300);
});

// ── Datos ──
async function cargar(){
  const offset = state.page * PAGE_SIZE;
  const params = `q=${encodeURIComponent(state.q)}&limit=${PAGE_SIZE}&offset=${offset}&order=${state.order}&dir=${state.dir}`;
  try{
    const d = await api('productos/catalogo?'+params);
    const total = d.total||0;
    document.getElementById('infoTotal').textContent = total > 0 ? `Mostrando ${offset+1}-${Math.min(offset+PAGE_SIZE, total)} de ${total}` : '';
    renderSortIcons();
    document.getElementById('tbody').innerHTML = (d.rows||[]).map(r=>`<tr>
      <td>${esc(r.nombre)}<br><small class="text-muted">${esc(r.descripcion||'')}</small></td>
      <td><span class="badge text-bg-light">${esc(r.tipo)}</span></td>
      <td class="text-end"><small>${money(r.valor_minimo)} – ${money(r.valor_maximo)}</small></td>
      <td class="text-center">${r.prioridad}</td>
      <td class="text-center">${r.requiere_archivo==1?'<span class="badge text-bg-info">Sí</span>':'—'}</td>
      <td class="text-center">${r.estado_activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-secondary">Inactivo</span>'}</td>
      <td class="text-end">
        ${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary me-1" onclick='abrir(${JSON.stringify(r)})'><i class="fas fa-edit"></i></button>`:''}
        ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="elim(${r.id},'${esc(r.nombre)}')"><i class="fas fa-ban"></i></button>`:''}
      </td></tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">Sin registros</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

// ── Paginación ──
function renderPagination(total){
  const N = Math.ceil(total / PAGE_SIZE);
  if (N <= 1) { document.getElementById('paginacion').innerHTML = ''; return; }
  const C = state.page; let pages = [];
  if (N <= 10) { for (let i = 0; i < N; i++) pages.push(i); }
  else {
    pages.push(0); if (C > 3) pages.push('...');
    for (let i = Math.max(1, C-2); i <= Math.min(N-2, C+2); i++) pages.push(i);
    if (C < N-4) pages.push('...'); pages.push(N-1);
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

// ── Modal CRUD ──
function abrir(row){
  editId = row ? row.id : null;
  document.getElementById('mt').textContent = editId ? 'Editar' : 'Nuevo';
  document.getElementById('f_nombre').value = row ? row.nombre : '';
  document.getElementById('f_tipo').value = row ? row.tipo : 'producto';
  document.getElementById('f_descripcion').value = row ? (row.descripcion||'') : '';
  document.getElementById('f_parametros').value = row ? (row.parametros||'') : '';
  document.getElementById('f_valor_minimo').value = row ? row.valor_minimo : 0;
  document.getElementById('f_valor_maximo').value = row ? row.valor_maximo : 0;
  document.getElementById('f_prioridad').value = row ? row.prioridad : 100;
  document.getElementById('f_requiere_archivo').checked = row ? row.requiere_archivo == 1 : false;
  document.getElementById('f_estado_activo').checked = row ? row.estado_activo == 1 : true;
  modal.show();
}

async function guardar(){
  const data = {
    nombre: document.getElementById('f_nombre').value.trim(),
    tipo: document.getElementById('f_tipo').value,
    descripcion: document.getElementById('f_descripcion').value.trim(),
    parametros: document.getElementById('f_parametros').value.trim(),
    valor_minimo: parseFloat(document.getElementById('f_valor_minimo').value)||0,
    valor_maximo: parseFloat(document.getElementById('f_valor_maximo').value)||0,
    prioridad: parseInt(document.getElementById('f_prioridad').value)||100,
    requiere_archivo: document.getElementById('f_requiere_archivo').checked,
    estado_activo: document.getElementById('f_estado_activo').checked,
  };
  if(!data.nombre){ alertBox.className='alert alert-danger';alertBox.textContent='El nombre es obligatorio.';alertBox.classList.remove('d-none'); return; }
  if(editId) data.id = editId;
  try{
    await api('productos/catalogo', {method: editId?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    modal.hide(); cargar();
  }catch(e){alert(e.message);}
}

async function elim(id, nombre){
  if(!confirm(`¿Inactivar "${nombre}"?`)) return;
  try{ await api('productos/catalogo', {method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})}); cargar(); }
  catch(e){alert(e.message);}
}

document.addEventListener('DOMContentLoaded',()=>{ modal=new bootstrap.Modal('#m'); cargar(); });
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
