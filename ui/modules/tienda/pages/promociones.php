<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.promociones');
$acc = ui_acciones('tienda.promociones', $staff['rol'] ?? '');
$canCrear = ui_accion('tienda.promociones', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('tienda.promociones', 'editar',   $staff['rol'] ?? '');
$canToggle= ui_accion('tienda.promociones', 'toggle',   $staff['rol'] ?? '');
$canElim  = ui_accion('tienda.promociones', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Promociones';
$currentPage = 'tienda_promociones';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-tags me-2"></i>Promociones</span>
    <span class="d-flex gap-2 align-items-center">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
      <?php if ($canCrear): ?>
      <button class="btn btn-light btn-sm" onclick="abrirModal()"><i class="fas fa-plus me-1"></i>Nueva promoción</button>
      <?php endif; ?>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:300px"><label class="form-label small text-muted mb-0">Buscar</label>
        <input class="form-control" id="q" placeholder="Producto, categoría o marca…"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="estF" data-searchable style="width:130px"><option value="">Todos</option><option value="1">Activa</option><option value="0">Inactiva</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Otros</label>
        <select class="form-select" id="vigF" data-searchable style="width:150px"><option value="">Todas</option><option value="1">Solo vigentes</option></select></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th>Producto</th><th>Categoría / Marca</th>
          <th class="sortable text-end" data-col="precio_descuento">P. descuento</th>
          <th class="sortable" data-col="fecha_inicio">Inicio</th><th class="sortable" data-col="fecha_fin">Fin</th>
          <th class="text-center">Vigente</th>
          <th class="sortable text-center" data-col="estado_activo">Estado</th><th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalP" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="formP" onsubmit="return guardar(event)">
    <input type="hidden" name="id" id="pId">
    <div class="modal-header"><h5 class="modal-title">Promoción</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2">
        <div class="col-12"><label class="form-label small text-muted mb-0">Producto *</label>
          <input class="form-control" id="buscarP" placeholder="Buscar producto…" autocomplete="off">
          <input type="hidden" id="pProd">
          <div id="resP" class="list-group position-absolute" style="z-index:1050;max-width:440px"></div>
        </div>
        <div class="col-md-4"><label class="form-label small text-muted mb-0">Precio descuento *</label>
          <input type="number" class="form-control" id="pPrecio" min="0" step="1" required></div>
        <div class="col-md-4"><label class="form-label small text-muted mb-0">Inicio *</label>
          <input type="date" class="form-control" id="pIni" required></div>
        <div class="col-md-4"><label class="form-label small text-muted mb-0">Fin *</label>
          <input type="date" class="form-control" id="pFin" required></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="pEstado" checked><label class="form-check-label">Activa</label></div></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand">Guardar</button></div>
  </form>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['tienda.promociones.editar']===true,CAN_TOGGLE=ACC['tienda.promociones.toggle']===true,CAN_ELIM=ACC['tienda.promociones.eliminar']===true;
const alertBox=document.getElementById('alert');
let state={q:'',est:'',vig:'',page:0,size:20,order:'id',dir:'DESC'};
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showA(m,ok){alertBox.className='alert '+(ok?'alert-success':'alert-danger');alertBox.textContent=m;alertBox.classList.remove('d-none');}

document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',function(){
  const col=th.dataset.col;if(state.order===col)state.dir=state.dir==='ASC'?'DESC':'ASC';else{state.order=col;state.dir='ASC';}
  state.page=0;cargar();
}));
function renderSortIcons(){document.querySelectorAll('.sortable').forEach(th=>{const col=th.dataset.col,a=state.order===col;th.classList.toggle('active',a);
  const txt=th.textContent.trim().replace(/[▲▼]/g,'');th.innerHTML=txt+(a?` <span class="sort-icon">${state.dir==='ASC'?'▲':'▼'}</span>`:'');});}
let t;
for(const id of ['q','estF','vigF'])document.getElementById(id).addEventListener('input',function(){clearTimeout(t);t=setTimeout(()=>{
  state.q=document.getElementById('q').value.trim();state.est=document.getElementById('estF').value;state.vig=document.getElementById('vigF').value;state.page=0;cargar();},300);});
async function cargar(){
  const off=state.page*state.size;
  const p=`q=${encodeURIComponent(state.q)}&estado=${state.est}&solo_vigentes=${state.vig}&limit=${state.size}&offset=${off}&order=${state.order}&dir=${state.dir}`;
  try{
    const d=await api('tienda/promociones.php?action=listar&'+p);
    document.getElementById('infoTotal').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+state.size,d.total)} de ${d.total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td class="fw-semibold">${esc(a.producto_nombre)}</td>
      <td class="small">${esc(a.categoria_nombre)} / ${esc(a.marca_nombre)}</td>
      <td class="text-end">${money(a.precio_descuento)}</td>
      <td>${esc(a.fecha_inicio)}</td><td>${esc(a.fecha_fin)}</td>
      <td class="text-center">${a.vigente==1?'<span class="badge text-bg-warning">Vigente</span>':'—'}</td>
      <td class="text-center">${a.estado_activo==1?'<span class="badge text-bg-success">Activa</span>':'<span class="badge text-bg-danger">Inactiva</span>'}</td>
      <td class="text-end">${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary" onclick="abrirModal(${a.id})"><i class="fas fa-edit"></i></button>`:''}
        ${CAN_TOGGLE?`<button class="btn btn-sm btn-outline-${a.estado_activo==1?'danger':'success'}" onclick="toggle(${a.id})"><i class="fas fa-${a.estado_activo==1?'ban':'check'}"></i></button>`:''}
        ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar(${a.id})"><i class="fas fa-trash"></i></button>`:''}</td>
    </tr>`).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(d.total);
  }catch(e){showA(e.message,false);}
}
function renderPagination(total){
  const N=Math.ceil(total/state.size),el=document.getElementById('paginacion'),C=state.page;
  if(N<=1){el.innerHTML='';return;}
  let pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h='';if(C>0)h+=`<li class="page-item"><a class="page-link" href="#" onclick="go(${C-1})">«</a></li>`;
  pages.forEach(p=>{if(p==='...')h+='<li class="page-item disabled"><span class="page-link">…</span></li>';else h+=`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="go(${p})">${p+1}</a></li>`;});
  if(C<N-1)h+=`<li class="page-item"><a class="page-link" href="#" onclick="go(${C+1})">»</a></li>`;
  el.innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h}</ul>`;
}
function go(p){state.page=p;cargar();}

let tP;
document.getElementById('buscarP').addEventListener('input',function(){
  const q=this.value.trim();if(q.length<1){document.getElementById('resP').innerHTML='';document.getElementById('pProd').value='';return;}
  clearTimeout(tP);tP=setTimeout(async()=>{
    try{
      const d=await api('tienda/catalogo.php?ent=productos&action=listar&q='+encodeURIComponent(q)+'&limit=20');
      document.getElementById('resP').innerHTML=d.rows.map(p=>`<button class="list-group-item list-group-item-action res-item" onclick="pickProd(${p.id},'${esc(p.nombre)}')">${esc(p.nombre)} — ${money(p.precio_venta_sugerido)}</button>`).join('')||'<div class="list-group-item text-muted">Sin resultados</div>';
    }catch(e){}
  },300);
});
function pickProd(id,n){document.getElementById('pProd').value=id;document.getElementById('buscarP').value=n;document.getElementById('resP').innerHTML='';}
async function abrirModal(id){
  document.getElementById('formP').reset();document.getElementById('pId').value=id||'';
  document.getElementById('pEstado').checked=true;
  document.getElementById('pIni').value=new Date().toISOString().slice(0,10);
  if(id){
    const a=await api('tienda/promociones.php?action=obtener&id='+id);
    document.getElementById('pProd').value=a.producto_id;document.getElementById('buscarP').value=a.producto_nombre;
    document.getElementById('pPrecio').value=Number(a.precio_descuento);
    document.getElementById('pIni').value=a.fecha_inicio;document.getElementById('pFin').value=a.fecha_fin;
    document.getElementById('pEstado').checked=a.estado_activo==1;
  }
  new bootstrap.Modal(document.getElementById('modalP')).show();
}
async function guardar(e){
  e.preventDefault();
  const prod=parseInt(document.getElementById('pProd').value);
  if(!prod){showA('Seleccione el producto.',false);return false;}
  const body={id:document.getElementById('pId').value,producto_id:prod,precio_descuento:document.getElementById('pPrecio').value,
    fecha_inicio:document.getElementById('pIni').value,fecha_fin:document.getElementById('pFin').value,
    estado_activo:document.getElementById('pEstado').checked?'1':'0'};
  try{await post('tienda/promociones.php?action=guardar',body);
    bootstrap.Modal.getInstance(document.getElementById('modalP')).hide();cargar();showA('Promoción guardada.',true);}catch(err){showA(err.message,false);}return false;
}
async function toggle(id){try{await post('tienda/promociones.php?action=toggle&id='+id,{});cargar();}catch(e){showA(e.message,false);}}
async function eliminar(id){if(!confirm('¿Eliminar promoción?'))return;try{await post('tienda/promociones.php?action=eliminar&id='+id,{});cargar();}catch(e){showA(e.message,false);}}

window.Listado.mountSize('fSizeBox', state, cargar);
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>