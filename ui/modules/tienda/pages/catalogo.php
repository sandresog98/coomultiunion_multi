<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.catalogo');
$acc = ui_acciones('tienda.catalogo', $staff['rol'] ?? '');
$canCrear = ui_accion('tienda.catalogo', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('tienda.catalogo', 'editar',   $staff['rol'] ?? '');
$canElim  = ui_accion('tienda.catalogo', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Catálogo';
$currentPage = 'tienda_catalogo';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" id="tbProducts" data-bs-toggle="tab" data-bs-target="#paneProducts">Productos</button></li>
  <li class="nav-item"><button class="nav-link" id="tbCategorias" data-bs-toggle="tab" data-bs-target="#paneCategorias">Categorías</button></li>
  <li class="nav-item"><button class="nav-link" id="tbMarcas" data-bs-toggle="tab" data-bs-target="#paneMarcas">Marcas</button></li>
</ul>

<div class="tab-content">
  <!-- PRODUCTOS -->
  <div class="tab-pane fade show active" id="paneProducts">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-box me-2"></i>Productos</span>
        <span class="d-flex gap-2 align-items-center">
          <span class="small text-white-50" id="infoP"></span>
          <?php if ($canCrear): ?>
          <button class="btn btn-light btn-sm" onclick="abrirProducto()"><i class="fas fa-plus me-1"></i>Nuevo producto</button>
          <?php endif; ?>
        </span>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-auto" style="max-width:280px"><label class="form-label small text-muted mb-0">Buscar</label>
            <input class="form-control" id="qP" placeholder="Nombre o descripción…"></div>
          <div class="col-auto"><label class="form-label small text-muted mb-0">Categoría</label>
            <select class="form-select" id="catP" style="width:170px"></select></div>
          <div class="col-auto"><label class="form-label small text-muted mb-0">Marca</label>
            <select class="form-select" id="marP" style="width:170px"></select></div>
          <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
            <select class="form-select" id="estP" style="width:120px"><option value="">Todos</option><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
          <div class="col-auto"><label class="form-label small text-muted mb-0">Agotado</label>
            <select class="form-select" id="agotP" style="width:140px"><option value="">Todos</option><option value="1">Agotados</option><option value="0">No agotados</option></select></div>
        </div>
        <div id="alertP" class="alert d-none"></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead><tr>
              <th style="width:70px">Foto</th>
              <th class="sortable" data-col="nombre" data-tab="P">Producto</th>
              <th class="sortable" data-col="categoria" data-tab="P">Categoría</th>
              <th class="sortable" data-col="marca" data-tab="P">Marca</th>
              <th class="sortable text-end" data-col="precio_venta_sugerido" data-tab="P">P. venta</th>
              <th class="text-end">Multiunión</th>
              <th class="text-center">Stock</th>
              <th class="text-center">Promo</th>
              <th class="sortable text-center" data-col="estado_activo" data-tab="P">Estado</th>
              <th></th>
            </tr></thead>
            <tbody id="tbodyP"><tr><td colspan="10" class="text-center text-muted">Cargando…</td></tr></tbody>
          </table>
        </div>
        <nav id="pagP" class="d-flex justify-content-center mt-3"></nav>
      </div>
    </div>
  </div>

  <!-- CATEGORÍAS -->
  <div class="tab-pane fade" id="paneCategorias">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-tags me-2"></i>Categorías</span>
        <span class="d-flex gap-2 align-items-center">
          <span class="small text-white-50" id="infoC"></span>
          <?php if ($canCrear): ?>
          <button class="btn btn-light btn-sm" onclick="abrirCategoria()"><i class="fas fa-plus me-1"></i>Nueva</button>
          <?php endif; ?>
        </span>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-auto" style="max-width:280px"><label class="form-label small text-muted mb-0">Buscar</label>
            <input class="form-control" id="qC" placeholder="Nombre…"></div>
          <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
            <select class="form-select" id="estC" style="width:120px"><option value="">Todos</option><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        </div>
        <div id="alertC" class="alert d-none"></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead><tr><th class="sortable" data-col="nombre" data-tab="C">Nombre</th><th class="text-center">Productos</th><th class="sortable text-center" data-col="estado_activo" data-tab="C">Estado</th><th></th></tr></thead>
            <tbody id="tbodyC"></tbody>
          </table>
        </div>
        <nav id="pagC" class="d-flex justify-content-center mt-3"></nav>
      </div>
    </div>
  </div>

  <!-- MARCAS -->
  <div class="tab-pane fade" id="paneMarcas">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-registered me-2"></i>Marcas</span>
        <span class="d-flex gap-2 align-items-center">
          <span class="small text-white-50" id="infoM"></span>
          <?php if ($canCrear): ?>
          <button class="btn btn-light btn-sm" onclick="abrirMarca()"><i class="fas fa-plus me-1"></i>Nueva</button>
          <?php endif; ?>
        </span>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-auto" style="max-width:280px"><label class="form-label small text-muted mb-0">Buscar</label>
            <input class="form-control" id="qM" placeholder="Nombre…"></div>
          <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
            <select class="form-select" id="estM" style="width:120px"><option value="">Todos</option><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
        </div>
        <div id="alertM" class="alert d-none"></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead><tr><th class="sortable" data-col="nombre" data-tab="M">Nombre</th><th class="text-center">Productos</th><th class="sortable text-center" data-col="estado_activo" data-tab="M">Estado</th><th></th></tr></thead>
            <tbody id="tbodyM"></tbody>
          </table>
        </div>
        <nav id="pagM" class="d-flex justify-content-center mt-3"></nav>
      </div>
    </div>
  </div>
</div>

<!-- Modal producto -->
<div class="modal fade" id="modalP" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <form id="formP" onsubmit="return guardarProducto(event)">
    <input type="hidden" name="id" id="pId">
    <div class="modal-header"><h5 class="modal-title">Producto</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2">
        <div class="col-md-6"><label class="form-label small text-muted mb-0">Categoría *</label>
          <select class="form-select" name="categoria_id" id="pCat" required></select></div>
        <div class="col-md-6"><label class="form-label small text-muted mb-0">Marca *</label>
          <select class="form-select" name="marca_id" id="pMar" required></select></div>
        <div class="col-md-8"><label class="form-label small text-muted mb-0">Nombre *</label>
          <input class="form-control" name="nombre" id="pNombre" required maxlength="200"></div>
        <div class="col-md-4"><label class="form-label small text-muted mb-0">Foto</label>
          <input type="file" class="form-control" name="foto" id="pFoto" accept="image/*"></div>
        <div class="col-md-4"><label class="form-label small text-muted mb-0">Precio compra (sugerido)</label>
          <input type="number" class="form-control" name="precio_compra_sugerido" id="pPc" min="0" step="1"></div>
        <div class="col-md-4"><label class="form-label small text-muted mb-0">Precio venta (sugerido)</label>
          <input type="number" class="form-control" name="precio_venta_sugerido" id="pPv" min="0" step="1"></div>
        <div class="col-md-4 align-self-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="pEstado" checked>
          <label class="form-check-label">Activo</label></div>
          <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="pAgotado">
          <label class="form-check-label text-danger">Agotado</label></div></div>
        <div class="col-12"><label class="form-label small text-muted mb-0">Descripción</label>
          <textarea class="form-control" name="descripcion" id="pDesc" rows="2"></textarea></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-brand">Guardar</button></div>
  </form>
</div></div></div>

<!-- Modal categoría -->
<div class="modal fade" id="modalC" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="formC" onsubmit="return guardarCategoria(event)">
    <input type="hidden" name="id" id="cId">
    <div class="modal-header"><h5 class="modal-title">Categoría</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2">
        <div class="col-12"><label class="form-label small text-muted mb-0">Nombre *</label><input class="form-control" name="nombre" id="cNombre" required maxlength="120"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="cEstado" checked><label class="form-check-label">Activa</label></div></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand">Guardar</button></div>
  </form>
</div></div></div>

<!-- Modal marca -->
<div class="modal fade" id="modalM" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="formM" onsubmit="return guardarMarca(event)">
    <input type="hidden" name="id" id="mId">
    <div class="modal-header"><h5 class="modal-title">Marca</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-2">
        <div class="col-12"><label class="form-label small text-muted mb-0">Nombre *</label><input class="form-control" name="nombre" id="mNombre" required maxlength="120"></div>
        <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="mEstado" checked><label class="form-check-label">Activa</label></div></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand">Guardar</button></div>
  </form>
</div></div></div>

<style>.thumb{width:44px;height:44px;object-fit:cover;border-radius:8px}</style>
<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['tienda.catalogo.editar']===true,CAN_ELIM=ACC['tienda.catalogo.eliminar']===true;
const PAGE_SIZE=20, alertP=document.getElementById('alertP');
let stP={q:'',cat:'',mar:'',est:'',agotado:'',page:0,order:'nombre',dir:'ASC'};
let stC={q:'',est:'',page:0,order:'nombre',dir:'ASC'};
let stM={q:'',est:'',page:0,order:'nombre',dir:'ASC'};
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showA(id,msg,ok){const e=document.getElementById(id);e.className='alert '+(ok?'alert-success':'alert-danger');e.textContent=msg;e.classList.remove('d-none');}
function imgUrl(id,path){if(!path)return null;if(path.match(/^https?:/))return path;return API+'tienda/serve_imagen.php?id='+id;}
function estadoBadge(v){return v==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-danger">Inactivo</span>';}

// ── sort ──
document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',function(){
  const tab=th.dataset.tab, col=th.dataset.col;
  const s=tab==='P'?stP:(tab==='C'?stC:stM);
  if(s.order===col)s.dir=s.dir==='ASC'?'DESC':'ASC';else{s.order=col;s.dir='ASC';}
  s.page=0;cargarTab(tab);
}));
function renderSortIcons(tab){
  const s=tab==='P'?stP:(tab==='C'?stC:stM);
  document.querySelectorAll(`.sortable[data-tab="${tab}"]`).forEach(th=>{
    const col=th.dataset.col, active=s.order===col; th.classList.toggle('active',active);
    const txt=th.textContent.trim().replace(/[▲▼]/g,'');
    const arrow=active?(s.dir==='ASC'?'▲':'▼'):'';
    th.innerHTML=txt+(arrow?` <span class="sort-icon">${arrow}</span>`:'');
  });
}
function renderPagination(elId,total,s){
  const el=document.getElementById(elId); const N=Math.ceil(total/PAGE_SIZE);
  if(N<=1){el.innerHTML='';return;}
  let pages=[]; const C=s.page;
  if(N<=10){for(let i=0;i<N;i++)pages.push(i);}
  else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let html='';
  if(C>0)html+=`<li class="page-item"><a class="page-link" href="#" onclick="go('${elId}',${C-1})">«</a></li>`;
  pages.forEach(p=>{if(p==='...')html+='<li class="page-item disabled"><span class="page-link">…</span></li>';else html+=`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="go('${elId}',${p})">${p+1}</a></li>`;});
  if(C<N-1)html+=`<li class="page-item"><a class="page-link" href="#" onclick="go('${elId}',${C+1})">»</a></li>`;
  el.innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${html}</ul>`;
}
function go(elId,p){const tab=elId==='pagP'?'P':(elId==='pagC'?'C':'M');const s=tab==='P'?stP:(tab==='C'?stC:stM);s.page=p;cargarTab(tab);}

// ── filtros productos ──
cargarSelectsCat(); 
async function cargarSelectsCat(){
  try{
    const cat=await api('tienda/catalogo.php?ent=categorias&action=activas');
    const mar=await api('tienda/catalogo.php?ent=marcas&action=activas');
    const selC=document.getElementById('catP'), selM=document.getElementById('marP');
    const optsC=cat.map(c=>`<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    const optsM=mar.map(c=>`<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    selC.innerHTML='<option value="">Todas</option>'+optsC; selM.innerHTML='<option value="">Todas</option>'+optsM;
    document.getElementById('pCat').innerHTML='<option value="">Seleccione…</option>'+cat.map(c=>`<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    document.getElementById('pMar').innerHTML='<option value="">Seleccione…</option>'+mar.map(c=>`<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
  }catch(e){}
}
let tQ;
document.getElementById('qP').addEventListener('input',function(){clearTimeout(tQ);tQ=setTimeout(()=>{stP.q=this.value.trim();stP.page=0;cargarTab('P');},300);});
['catP','marP','estP','agotP'].forEach(id=>document.getElementById(id).addEventListener('change',function(){
  stP.cat=document.getElementById('catP').value;stP.mar=document.getElementById('marP').value;stP.est=document.getElementById('estP').value;stP.agotado=document.getElementById('agotP').value;stP.page=0;cargarTab('P');
}));
['qC','estC'].forEach(id=>document.getElementById(id).addEventListener('input',function(){
  stC.q=document.getElementById('qC').value.trim();stC.est=document.getElementById('estC').value;stC.page=0;cargarTab('C');
}));
document.getElementById('qC').addEventListener('input',function(){clearTimeout(tQ);tQ=setTimeout(()=>{stC.q=this.value.trim();stC.page=0;cargarTab('C');},300);});
document.getElementById('estC').addEventListener('change',function(){stC.est=this.value;stC.page=0;cargarTab('C');});
document.getElementById('qM').addEventListener('input',function(){clearTimeout(tQ);tQ=setTimeout(()=>{stM.q=this.value.trim();stM.page=0;cargarTab('M');},300);});
document.getElementById('estM').addEventListener('change',function(){stM.est=this.value;stM.page=0;cargarTab('M');});

// ── carga de tabs ──
function cargarTab(tab){
  if(tab==='P')cargarP();else if(tab==='C')cargarC();else cargarM();
}
async function cargarP(){
  const off=stP.page*PAGE_SIZE;
  const p=`q=${encodeURIComponent(stP.q)}&categoria_id=${stP.cat}&marca_id=${stP.mar}&estado=${stP.est}&agotado=${stP.agotado}&limit=${PAGE_SIZE}&offset=${off}&order=${stP.order}&dir=${stP.dir}`;
  try{
    const d=await api('tienda/catalogo.php?ent=productos&action=listar&'+p);
    document.getElementById('infoP').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,d.total)} de ${d.total}`:'';
    renderSortIcons('P');
    document.getElementById('tbodyP').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td>${a.foto_path?`<img src="${imgUrl(a.id,a.foto_path)}" class="thumb" onerror="this.style.visibility='hidden'">`:'<span class="text-muted">—</span>'}</td>
      <td class="fw-semibold">${esc(a.nombre)}</td><td>${esc(a.categoria_nombre)}</td><td>${esc(a.marca_nombre)}</td>
      <td class="text-end">${money(a.precio_venta_sugerido)}</td><td class="text-end">${a.precio_multiunion!=null?money(a.precio_multiunion):'—'}</td>
      <td class="text-center">${a.disponible>0?`<span class="badge text-bg-success">${a.disponible} disp</span>`:''} ${a.vendido>0?`<span class="badge text-bg-secondary">${a.vendido} ven</span>`:''}</td>
      <td class="text-center">${a.en_promocion==1?`<span class="badge text-bg-warning">PROMO ${money(a.precio_descuento)}</span>`:'—'}</td>
      <td class="text-center">${estadoBadge(a.estado_activo)}${(a.estado_activo==1 && a.agotado==1)?'<div class="mt-1"><span class="badge text-bg-danger">Agotado</span></div>':''}</td>
      <td class="text-end">${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary" onclick="abrirProducto(${a.id})"><i class="fas fa-edit"></i></button>`:''}
        ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar('productos',${a.id})"><i class="fas fa-trash"></i></button>`:''}</td>
    </tr>`).join('')||'<tr><td colspan="10" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination('pagP',d.total,stP);
  }catch(e){showA('alertP',e.message,false);}
}
async function cargarC(){
  const off=stC.page*PAGE_SIZE;
  const p=`q=${encodeURIComponent(stC.q)}&estado=${stC.est}&limit=${PAGE_SIZE}&offset=${off}&order=${stC.order}&dir=${stC.dir}`;
  try{
    const d=await api('tienda/catalogo.php?ent=categorias&action=listar&'+p);
    document.getElementById('infoC').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,d.total)} de ${d.total}`:'';
    renderSortIcons('C');
    document.getElementById('tbodyC').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td class="fw-semibold">${esc(a.nombre)}</td><td class="text-center">${a.total_productos}</td><td class="text-center">${estadoBadge(a.estado_activo)}</td>
      <td class="text-end">${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary" onclick="abrirCategoria(${a.id},'${esc(a.nombre)}',${a.estado_activo})"><i class="fas fa-edit"></i></button>`:''}
        ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar('categorias',${a.id})"><i class="fas fa-trash"></i></button>`:''}</td>
    </tr>`).join('')||'<tr><td colspan="4" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination('pagC',d.total,stC);
  }catch(e){showA('alertC',e.message,false);}
}
async function cargarM(){
  const off=stM.page*PAGE_SIZE;
  const p=`q=${encodeURIComponent(stM.q)}&estado=${stM.est}&limit=${PAGE_SIZE}&offset=${off}&order=${stM.order}&dir=${stM.dir}`;
  try{
    const d=await api('tienda/catalogo.php?ent=marcas&action=listar&'+p);
    document.getElementById('infoM').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,d.total)} de ${d.total}`:'';
    renderSortIcons('M');
    document.getElementById('tbodyM').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td class="fw-semibold">${esc(a.nombre)}</td><td class="text-center">${a.total_productos}</td><td class="text-center">${estadoBadge(a.estado_activo)}</td>
      <td class="text-end">${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary" onclick="abrirMarca(${a.id},'${esc(a.nombre)}',${a.estado_activo})"><i class="fas fa-edit"></i></button>`:''}
        ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar('marcas',${a.id})"><i class="fas fa-trash"></i></button>`:''}</td>
    </tr>`).join('')||'<tr><td colspan="4" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination('pagM',d.total,stM);
  }catch(e){showA('alertM',e.message,false);}
}

// ── producto form ──
function abrirProducto(id){
  document.getElementById('formP').reset(); document.getElementById('pId').value=id||''; document.getElementById('pEstado').checked=true; document.getElementById('pAgotado').checked=false;
  if(id){
    api('tienda/catalogo.php?ent=productos&action=obtener&id='+id).then(a=>{
      document.getElementById('pCat').value=a.categoria_id; document.getElementById('pMar').value=a.marca_id;
      document.getElementById('pNombre').value=a.nombre; document.getElementById('pDesc').value=a.descripcion||'';
      document.getElementById('pPc').value=Number(a.precio_compra_sugerido||0);
      document.getElementById('pPv').value=Number(a.precio_venta_sugerido||0);
      document.getElementById('pEstado').checked=a.estado_activo==1;
      document.getElementById('pAgotado').checked=a.agotado==1;
    });
  }
  const m=new bootstrap.Modal(document.getElementById('modalP')); m.show();
}
async function guardarProducto(e){
  e.preventDefault();
  const fd=new FormData(document.getElementById('formP'));
  fd.set('estado_activo',document.getElementById('pEstado').checked?'1':'0');
  fd.set('agotado',document.getElementById('pAgotado').checked?'1':'0');
  try{
    await api('tienda/catalogo.php?ent=productos&action=guardar',{method:'POST',body:fd});
    bootstrap.Modal.getInstance(document.getElementById('modalP')).hide();
    cargarTab('P'); showA('alertP','Producto guardado.',true);
  }catch(err){showA('alertP',err.message,false);} return false;
}
// ── categoria/marca form ──
function abrirCategoria(id,nombre,estado){
  document.getElementById('cId').value=id||''; document.getElementById('cNombre').value=nombre||''; document.getElementById('cEstado').checked=estado!==0;
  new bootstrap.Modal(document.getElementById('modalC')).show();
}
async function guardarCategoria(e){
  e.preventDefault();
  const fd=new FormData(document.getElementById('formC')); fd.set('estado_activo',document.getElementById('cEstado').checked?'1':'0');
  try{await api('tienda/catalogo.php?ent=categorias&action=guardar',{method:'POST',body:fd});
    bootstrap.Modal.getInstance(document.getElementById('modalC')).hide(); cargarTab('C'); cargarSelectsCat(); showA('alertC','Categoría guardada.',true);}catch(err){showA('alertC',err.message,false);} return false;
}
function abrirMarca(id,nombre,estado){
  document.getElementById('mId').value=id||''; document.getElementById('mNombre').value=nombre||''; document.getElementById('mEstado').checked=estado!==0;
  new bootstrap.Modal(document.getElementById('modalM')).show();
}
async function guardarMarca(e){
  e.preventDefault();
  const fd=new FormData(document.getElementById('formM')); fd.set('estado_activo',document.getElementById('mEstado').checked?'1':'0');
  try{await api('tienda/catalogo.php?ent=marcas&action=guardar',{method:'POST',body:fd});
    bootstrap.Modal.getInstance(document.getElementById('modalM')).hide(); cargarTab('M'); cargarSelectsCat(); showA('alertM','Marca guardada.',true);}catch(err){showA('alertM',err.message,false);} return false;
}
// ── eliminar ──
async function eliminar(ent,id){
  if(!confirm('¿Eliminar? Esta acción no se puede deshacer.'))return;
  try{await post(`tienda/catalogo.php?ent=${ent}&action=eliminar&id=${id}`,{}); cargarTab(ent==='productos'?'P':(ent==='categorias'?'C':'M'));}
  catch(e){showA('alertP',e.message,false);}
}

cargarTab('P');

// Cargar Categorías/Marcas al activar su tab
document.querySelectorAll('#tbCategorias,#tbMarcas').forEach(btn=>{
  btn.addEventListener('shown.bs.tab',function(){
    if(this.id==='tbCategorias') cargarTab('C');
    if(this.id==='tbMarcas') cargarTab('M');
  });
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>