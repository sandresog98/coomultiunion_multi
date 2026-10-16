<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.compras');
$pageTitle = 'Compras';
$currentPage = 'tienda_compras';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-shopping-cart me-2"></i>Compras (historial)</span>
    <span class="d-flex gap-2 align-items-center">
      <span class="small text-white-50" id="infoTotal"></span>
      <a class="btn btn-light btn-sm" href="<?php echo uiBaseUrl(); ?>modules/tienda/pages/nueva_compra"><i class="fas fa-plus me-1"></i>Nueva compra</a>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:200px"><label class="form-label small text-muted mb-0">Nº compra</label>
        <input class="form-control" id="q" placeholder="ID…"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label>
        <input class="form-control" type="date" id="d1" style="width:160px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label>
        <input class="form-control" type="date" id="d2" style="width:160px"></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="id">#</th>
          <th class="sortable" data-col="fecha_compra">Fecha</th>
          <th class="sortable text-center" data-col="total_items">Ítems</th>
          <th class="sortable text-end" data-col="total_cantidad">Unidades</th>
          <th class="sortable text-end" data-col="total_valor">Valor</th>
          <th class="sortable" data-col="fecha_creacion">Registrada</th>
          <th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="7" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const PAGE_SIZE=20, alertBox=document.getElementById('alert');
let state={q:'',d1:'',d2:'',page:0,order:'id',dir:'DESC'};
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showA(m,ok){alertBox.className='alert '+(ok?'alert-success':'alert-danger');alertBox.textContent=m;alertBox.classList.remove('d-none');}

document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',function(){
  const col=th.dataset.col; if(state.order===col)state.dir=state.dir==='ASC'?'DESC':'ASC';else{state.order=col;state.dir='ASC';}
  state.page=0;cargar();
}));
function renderSortIcons(){
  document.querySelectorAll('.sortable').forEach(th=>{const col=th.dataset.col,a=state.order===col;th.classList.toggle('active',a);
    const txt=th.textContent.trim().replace(/[▲▼]/g,'');th.innerHTML=txt+(a?` <span class="sort-icon">${state.dir==='ASC'?'▲':'▼'}</span>`:'');});
}
let t;
for(const id of ['q','d1','d2'])document.getElementById(id).addEventListener('input',function(){clearTimeout(t);t=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.d1=document.getElementById('d1').value;state.d2=document.getElementById('d2').value;state.page=0;cargar();},300);});
async function cargar(){
  const off=state.page*PAGE_SIZE;
  const p=`q=${encodeURIComponent(state.q)}&fecha_desde=${state.d1}&fecha_hasta=${state.d2}&limit=${PAGE_SIZE}&offset=${off}&order=${state.order}&dir=${state.dir}`;
  try{
    const d=await api('tienda/compras.php?action=listar&'+p);
    document.getElementById('infoTotal').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,d.total)} de ${d.total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td><a href="${UI}modules/tienda/pages/detalle_compra?id=${a.id}" class="fw-semibold">#${a.id}</a></td>
      <td>${esc(a.fecha_compra||'')}</td>
      <td class="text-center">${a.total_items}</td><td class="text-end">${a.total_cantidad}</td><td class="text-end">${money(a.total_valor)}</td>
      <td>${esc(String(a.fecha_creacion||'').replace('T',' '))}</td>
      <td class="text-end">
        <a class="btn btn-sm btn-outline-primary" href="${UI}modules/tienda/pages/detalle_compra?id=${a.id}"><i class="fas fa-eye"></i></a>
        <button class="btn btn-sm btn-outline-danger" onclick="eliminar(${a.id})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join('')||'<tr><td colspan="7" class="text-center text-muted">Sin resultados</td></tr>';
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
function go(p){state.page=p;document.querySelector('.card-body').scrollIntoView({behavior:'smooth'});cargar();}
async function eliminar(id){
  if(!confirm('¿Eliminar la compra? Solo se eliminan compras sin unidades vendidas.'))return;
  try{await post('tienda/compras.php?action=eliminar&id='+id,{});cargar();showA('Compra eliminada.',true);}catch(e){showA(e.message,false);}
}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>