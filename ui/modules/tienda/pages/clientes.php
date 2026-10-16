<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.clientes');
$acc = ui_acciones('tienda.clientes', $staff['rol'] ?? '');
$canCrear = ui_accion('tienda.clientes', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('tienda.clientes', 'editar',   $staff['rol'] ?? '');
$canToggle= ui_accion('tienda.clientes', 'toggle',   $staff['rol'] ?? '');
$canElim  = ui_accion('tienda.clientes', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Clientes';
$currentPage = 'tienda_clientes';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-users me-2"></i>Clientes</span>
    <span class="d-flex gap-2 align-items-center">
      <span class="small text-white-50" id="infoTotal"></span>
      <?php if ($canCrear): ?>
      <button class="btn btn-light btn-sm" onclick="abrirModal()"><i class="fas fa-plus me-1"></i>Nuevo cliente</button>
      <?php endif; ?>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:300px"><label class="form-label small text-muted mb-0">Buscar</label>
        <input class="form-control" id="q" placeholder="Nombre, documento, teléfono…"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Tipo</label>
        <select class="form-select" id="tipoF" style="width:140px"><option value="">Todos</option><option value="externo">Externo</option><option value="asociado">Asociado</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="estF" style="width:120px"><option value="">Todos</option><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="nombre">Nombre</th>
          <th class="sortable" data-col="nit_cedula">NIT/Cédula</th>
          <th class="sortable" data-col="cedula">Cédula Sifone</th>
          <th class="text-center">Tipo</th>
          <th class="sortable" data-col="telefono">Teléfono</th>
          <th class="sortable" data-col="email">Email</th>
          <th class="text-center">Ventas</th>
          <th class="sortable text-center" data-col="estado_activo">Estado</th>
          <th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalC" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="formC" onsubmit="return guardar(event)">
    <input type="hidden" name="id" id="cId">
    <div class="modal-header"><h5 class="modal-title">Cliente</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2">
        <div class="col-12"><label class="form-label small text-muted mb-0">Tipo *</label>
          <select class="form-select" id="cTipo" onchange="tipoChange()"><option value="externo">Externo</option><option value="asociado">Asociado</option></select></div>
        <div class="col-12" id="campoAsoc" style="display:none">
          <label class="form-label small text-muted mb-0">Asociado *</label>
          <input class="form-control" id="asocBuscar" placeholder="Cédula o nombre del asociado…" autocomplete="off">
          <input type="hidden" id="asocCedula">
          <div id="resAsoc" class="list-group position-absolute" style="z-index:1050;max-width:320px"></div>
        </div>
        <div class="col-12" id="campoDoc">
          <label class="form-label small text-muted mb-0">NIT/Cédula *</label>
          <input class="form-control" name="nit_cedula" id="cDoc"></div>
        <div class="col-12"><label class="form-label small text-muted mb-0">Nombre *</label><input class="form-control" name="nombre" id="cNombre" required maxlength="200"></div>
        <div class="col-md-6"><label class="form-label small text-muted mb-0">Teléfono</label><input class="form-control" name="telefono" id="cTel"></div>
        <div class="col-md-6"><label class="form-label small text-muted mb-0">Email</label><input class="form-control" name="email" id="cEmail"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="cEstado" checked><label class="form-check-label">Activo</label></div></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand">Guardar</button></div>
  </form>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['tienda.clientes.editar']===true,CAN_TOGGLE=ACC['tienda.clientes.toggle']===true,CAN_ELIM=ACC['tienda.clientes.eliminar']===true;
const PAGE_SIZE=20, alertBox=document.getElementById('alert');
let state={q:'',tipo:'',estado:'',page:0,order:'nombre',dir:'ASC'};
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showA(m,ok){alertBox.className='alert '+(ok?'alert-success':'alert-danger');alertBox.textContent=m;alertBox.classList.remove('d-none');}

document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',function(){
  const col=th.dataset.col; if(state.order===col)state.dir=state.dir==='ASC'?'DESC':'ASC';else{state.order=col;state.dir='ASC';}
  state.page=0;cargar();
}));
function renderSortIcons(){
  document.querySelectorAll('.sortable').forEach(th=>{const col=th.dataset.col,a=state.order===col;th.classList.toggle('active',a);
    const txt=th.textContent.trim().replace(/[▲▼]/g,'');th.innerHTML=txt+(a?` <span class="sort-icon">${a?(state.dir==='ASC'?'▲':'▼'):''}</span>`:'');});
}
let t;
for(const id of ['q','tipoF','estF']){
  const el=document.getElementById(id);
  el.addEventListener('input',function(){clearTimeout(t);t=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.tipo=document.getElementById('tipoF').value;state.estado=document.getElementById('estF').value;state.page=0;cargar();},300);});
}
async function cargar(){
  const off=state.page*PAGE_SIZE;
  const p=`q=${encodeURIComponent(state.q)}&tipo=${state.tipo}&estado=${state.estado}&limit=${PAGE_SIZE}&offset=${off}&order=${state.order}&dir=${state.dir}`;
  try{
    const d=await api('tienda/clientes.php?action=listar&'+p);
    document.getElementById('infoTotal').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,d.total)} de ${d.total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td class="fw-semibold">${esc(a.nombre)}</td><td>${esc(a.nit_cedula||'')}</td><td>${esc(a.cedula||'')}</td>
      <td class="text-center">${a.tipo==='asociado'?'<span class="badge text-bg-info">Asociado</span>':'<span class="badge text-bg-secondary">Externo</span>'}</td>
      <td>${esc(a.telefono||'')}</td><td>${esc(a.email||'')}</td>
      <td class="text-center">${a.usado_ventas>0?`<span class="badge text-bg-primary">${a.usado_ventas}</span>`:'—'}</td>
      <td class="text-center">${a.estado_activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-danger">Inactivo</span>'}</td>
      <td class="text-end">${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary" onclick="abrirModal(${a.id})"><i class="fas fa-edit"></i></button>`:''}
        ${CAN_TOGGLE?`<button class="btn btn-sm btn-outline-${a.estado_activo==1?'danger':'success'}" onclick="toggle(${a.id})"><i class="fas fa-${a.estado_activo==1?'ban':'check'}"></i></button>`:''}
        ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar(${a.id})"><i class="fas fa-trash"></i></button>`:''}</td>
    </tr>`).join('')||'<tr><td colspan="9" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(d.total);
  }catch(e){showA(e.message,false);}
}
function renderPagination(total){
  const N=Math.ceil(total/PAGE_SIZE), el=document.getElementById('paginacion'), C=state.page;
  if(N<=1){el.innerHTML='';return;}
  let pages=[]; if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h=''; if(C>0)h+=`<li class="page-item"><a class="page-link" href="#" onclick="go(${C-1})">«</a></li>`;
  pages.forEach(p=>{if(p==='...')h+='<li class="page-item disabled"><span class="page-link">…</span></li>';else h+=`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="go(${p})">${p+1}</a></li>`;});
  if(C<N-1)h+=`<li class="page-item"><a class="page-link" href="#" onclick="go(${C+1})">»</a></li>`;
  el.innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h}</ul>`;
}
function go(p){state.page=p;cargar();}

// ── modal ──
function tipoChange(){
  const t=document.getElementById('cTipo').value;
  document.getElementById('campoAsoc').style.display=t==='asociado'?'':'none';
  document.getElementById('campoDoc').style.display=t==='externo'?'':'none';
  document.getElementById('cDoc').required=t==='externo';
}
async function abrirModal(id){
  document.getElementById('formC').reset(); document.getElementById('cId').value=id||'';
  document.getElementById('cTipo').value='externo';tipoChange();document.getElementById('cEstado').checked=true;
  if(id){
    const a=await api('tienda/clientes.php?action=obtener&id='+id);
    document.getElementById('cTipo').value=a.tipo;tipoChange();
    document.getElementById('cDoc').value=a.nit_cedula||'';
    document.getElementById('asocCedula').value=a.cedula||'';document.getElementById('asocBuscar').value=a.cedula||'';
    document.getElementById('cNombre').value=a.nombre;
    document.getElementById('cTel').value=a.telefono||'';document.getElementById('cEmail').value=a.email||'';
    document.getElementById('cEstado').checked=a.estado_activo==1;
  }
  new bootstrap.Modal(document.getElementById('modalC')).show();
}
async function guardar(e){
  e.preventDefault();
  const t=document.getElementById('cTipo').value;
  const body={
    id:document.getElementById('cId').value,
    tipo:t,
    cedula:t==='asociado'?document.getElementById('asocCedula').value:null,
    nit_cedula:t==='externo'?document.getElementById('cDoc').value:null,
    nombre:document.getElementById('cNombre').value,
    telefono:document.getElementById('cTel').value,
    email:document.getElementById('cEmail').value,
    estado_activo:document.getElementById('cEstado').checked?'1':'0'
  };
  try{await post('tienda/clientes.php?action=guardar',body);
    bootstrap.Modal.getInstance(document.getElementById('modalC')).hide();cargar();showA('Cliente guardado.',true);}catch(err){showA(err.message,false);} return false;
}
async function toggle(id){try{await post('tienda/clientes.php?action=toggle&id='+id,{});cargar();}catch(e){showA(e.message,false);}}
async function eliminar(id){
  if(!confirm('¿Eliminar cliente?'))return;
  try{await post('tienda/clientes.php?action=eliminar&id='+id,{});cargar();}catch(e){showA(e.message,false);}
}

// autocomplete asociado
let tA;
document.getElementById('asocBuscar').addEventListener('input',function(){
  clearTimeout(tA); const q=this.value.trim();
  if(q.length<1){document.getElementById('resAsoc').innerHTML='';document.getElementById('asocCedula').value='';return;}
  tA=setTimeout(async()=>{
    try{
      const d=await api('tienda/clientes.php?action=buscar_asociados&q='+encodeURIComponent(q)+'&limit=20');
      document.getElementById('resAsoc').innerHTML=d.map(a=>`<button class="list-group-item list-group-item-action" onclick="pickAsoc('${esc(a.cedula)}','${esc(a.nombre)}')">${esc(a.cedula)} — ${esc(a.nombre)}</button>`).join('')||'<div class="list-group-item text-muted">Sin resultados</div>';
    }catch(e){}
  },300);
});
function pickAsoc(c,n){document.getElementById('asocCedula').value=c;document.getElementById('asocBuscar').value=n;document.getElementById('resAsoc').innerHTML='';}

tipoChange(); cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>