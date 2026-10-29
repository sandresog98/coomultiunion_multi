<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.inventario');
$pageTitle = 'Inventario';
$currentPage = 'tienda_inventario';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-boxes me-2"></i>Inventario</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:280px"><label class="form-label small text-muted mb-0">Buscar</label>
        <input class="form-control" id="q" placeholder="Producto…"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Categoría</label>
        <select class="form-select" id="catF" data-searchable style="width:170px"></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Marca</label>
        <select class="form-select" id="marF" data-searchable style="width:170px"></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="estF" data-searchable style="width:150px"><option value="">Todos</option><option value="disponible">Con disponibilidad</option><option value="vendido">Con vendidos</option></select></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="nombre">Producto</th>
          <th class="sortable" data-col="categoria">Categoría</th>
          <th class="sortable" data-col="marca">Marca</th>
          <th class="sortable text-end" data-col="ingresado">Ingresado</th>
          <th class="sortable text-end" data-col="vendido">Vendido</th>
          <th class="sortable text-end" data-col="disponible">Disponible</th>
          <th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="7" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Detalle producto -->
<div class="modal fade" id="modalDet" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="detTitulo"></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <ul class="nav nav-tabs mb-2">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabLotes">Lotes</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVentas">Ventas</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRev">Reversiones</button></li>
    </ul>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="tabLotes">
        <input class="form-control form-control-sm mb-2" style="max-width:280px" id="serieQ" placeholder="Filtrar serie…" oninput="filtrarSeries()">
        <div class="table-responsive"><table class="table table-sm align-middle" id="tabLotesT"></table></div>
      </div>
      <div class="tab-pane fade" id="tabVentas"><div class="table-responsive"><table class="table table-sm align-middle" id="tabVentasT"></table></div></div>
      <div class="tab-pane fade" id="tabRev"><div class="table-responsive"><table class="table table-sm align-middle" id="tabRevT"></table></div></div>
    </div>
  </div>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const alertBox=document.getElementById('alert');
let state={q:'',cat:'',mar:'',est:'',page:0,size:20,order:'nombre',dir:'ASC'};
let det=null;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
function showA(m,ok){alertBox.className='alert '+(ok?'alert-success':'alert-danger');alertBox.textContent=m;alertBox.classList.remove('d-none');}
function badge(v,cls,txt){return v>0?`<span class="badge ${cls}">${txt}</span>`:'—';}

(async function(){
  try{
    const cat=await api('tienda/catalogo.php?ent=categorias&action=activas');
    const mar=await api('tienda/catalogo.php?ent=marcas&action=activas');
    document.getElementById('catF').innerHTML='<option value="">Todas</option>'+cat.map(c=>`<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    document.getElementById('marF').innerHTML='<option value="">Todas</option>'+mar.map(c=>`<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
  }catch(e){}
})();

document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',function(){
  const col=th.dataset.col;if(state.order===col)state.dir=state.dir==='ASC'?'DESC':'ASC';else{state.order=col;state.dir='ASC';}
  state.page=0;cargar();
}));
function renderSortIcons(){document.querySelectorAll('.sortable').forEach(th=>{const col=th.dataset.col,a=state.order===col;th.classList.toggle('active',a);
  const txt=th.textContent.trim().replace(/[▲▼]/g,'');th.innerHTML=txt+(a?` <span class="sort-icon">${state.dir==='ASC'?'▲':'▼'}</span>`:'');});}
let t;
for(const id of ['q','catF','marF','estF'])document.getElementById(id).addEventListener('input',function(){clearTimeout(t);t=setTimeout(()=>{
  state.q=document.getElementById('q').value.trim();state.cat=document.getElementById('catF').value;state.mar=document.getElementById('marF').value;state.est=document.getElementById('estF').value;state.page=0;cargar();},300);});
async function cargar(){
  const off=state.page*state.size;
  const p=`q=${encodeURIComponent(state.q)}&categoria_id=${state.cat}&marca_id=${state.mar}&estado=${state.est}&limit=${state.size}&offset=${off}&order=${state.order}&dir=${state.dir}`;
  try{
    const d=await api('tienda/stock.php?action=listar&'+p);
    document.getElementById('infoTotal').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+state.size,d.total)} de ${d.total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td class="fw-semibold">${esc(a.nombre)}</td><td>${esc(a.categoria_nombre)}</td><td>${esc(a.marca_nombre)}</td>
      <td class="text-end">${a.ingresado}</td><td class="text-end">${badge(a.vendido,'text-bg-secondary',a.vendido)}</td>
      <td class="text-end">${badge(a.disponible,'text-bg-success',a.disponible)}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="verDetalle(${a.id})"><i class="fas fa-search"></i></button></td>
    </tr>`).join('')||'<tr><td colspan="7" class="text-center text-muted">Sin resultados</td></tr>';
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

async function verDetalle(id){
  try{
    det=await api('tienda/stock.php?action=detalle_producto&id='+id);
    document.getElementById('detTitulo').textContent=`${det.nombre} · ${det.categoria_nombre} / ${det.marca_nombre} · Ingresado ${det.lotes.reduce((s,l)=>s+ (+l.vendido+ +l.disponible+ +l.anulado),0)}`;
    renderLotes(); renderVentas(); renderRev();
    new bootstrap.Modal(document.getElementById('modalDet')).show();
  }catch(e){showA(e.message,false);}
}
function renderLotes(){
  document.getElementById('tabLotesT').innerHTML=`<thead class="table-light"><tr><th>#Det</th><th>Fecha compra</th><th>P. compra</th><th>P. venta sug.</th><th>Vend</th><th>Disp</th><th>Anul</th><th>Series</th></tr></thead><tbody>`+
    det.lotes.map(l=>`<tr><td>${l.id}</td><td>${esc(l.fecha_compra||'')}</td><td>${money(l.precio_compra)}</td><td>${money(l.precio_venta_sugerido)}</td>
      <td class="text-center">${l.vendido||'—'}</td><td class="text-center">${l.disponible||'—'}</td><td class="text-center">${l.anulado||'—'}</td>
      <td style="max-width:260px"><span class="small">${l.series.map(s=>`<span class="badge text-bg-light border">${esc(s.numero_serie)} <span class="text-${s.estado==='vendido'?'danger':'success'}">(${s.estado})</span></span>`).join(' ')||'—'}</span></td></tr>`).join('')+'</tbody>';
}
function filtrarSeries(){
  const q=document.getElementById('serieQ').value.toLowerCase();
  document.querySelectorAll('#tabLotesT tbody tr').forEach(tr=>{tr.style.display=(q===''||tr.textContent.toLowerCase().includes(q))?'':'none';});
}
function renderVentas(){
  document.getElementById('tabVentasT').innerHTML=`<thead class="table-light"><tr><th>#Venta</th><th>Fecha</th><th>Tipo</th><th>Cédula/Cliente</th><th>Cant.</th><th>P. unit</th><th>Subtotal</th></tr></thead><tbody>`+
    (det.ventas||[]).map(v=>`<tr><td>${v.venta_id}</td><td>${esc(v.fecha_venta||'')}</td><td>${v.tipo_cliente}</td><td>${esc(v.cedula||v.cliente_id||'')}</td>
      <td class="text-center">${v.cantidad}</td><td class="text-end">${money(v.precio_unitario)}</td><td class="text-end">${money(v.subtotal)}</td></tr>`).join('')
    ||'<tr><td colspan="7" class="text-center text-muted">Sin ventas</td></tr>'+'</tbody>';
}
function renderRev(){
  document.getElementById('tabRevT').innerHTML=`<thead class="table-light"><tr><th>#Rev</th><th>Fecha</th><th>Motivo</th><th>Línea</th><th>Bono</th></tr></thead><tbody>`+
    (det.reversiones||[]).map(r=>`<tr><td>${r.id}</td><td>${esc(r.fecha_creacion||'')}</td><td>${esc(r.motivo||'')}</td>
      <td class="text-center">#${r.venta_detalle_id}</td><td>${r.bono_saldo!=null?money(r.bono_saldo):'—'}</td></tr>`).join('')
    ||'<tr><td colspan="5" class="text-center text-muted">Sin reversiones</td></tr>'+'</tbody>';
}

window.Listado.mountSize('fSizeBox', state, cargar);
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>