<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.ventas');
$pageTitle = 'Facturación';
$currentPage = 'tienda_facturacion';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-file-invoice-dollar me-2"></i>Facturación</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:240px"><label class="form-label small text-muted mb-0">Buscar</label>
        <input class="form-control" id="q" placeholder="Nº venta, cédula, nombre…"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Tipo</label>
        <select class="form-select" id="tipoF" data-searchable style="width:130px"><option value="">Todos</option><option value="asociado">Asociado</option><option value="externo">Externo</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label>
        <input class="form-control" type="date" id="d1" style="width:160px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label>
        <input class="form-control" type="date" id="d2" style="width:160px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">CECO</label>
        <select class="form-select" id="cecoF" data-searchable style="width:190px"><option value="">Todos</option><option>TIENDA GALERIAS</option><option>COOPERATIVA OFICINA</option><option>REDES - WEB</option></select></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="id">#</th>
          <th class="sortable" data-col="fecha_venta">Fecha</th>
          <th class="text-center">Tipo</th>
          <th class="sortable" data-col="total">Total</th>
          <th class="sortable" data-col="ceco">CECO</th>
          <th class="text-center">Ítems</th>
          <th class="text-center">Pago</th>
          <th class="text-center">Rev.</th>
          <th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="9" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<!-- Detalle venta -->
<div class="modal fade" id="modalV" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="vTitulo"></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2 mb-2 small">
      <div class="col-md-4">Cliente: <strong id="vCliente"></strong></div>
      <div class="col-md-3">CECO: <strong id="vCeco"></strong></div>
      <div class="col-md-3">Fecha: <strong id="vFecha"></strong></div>
      <div class="col-md-2 text-end">Total: <strong id="vTotal"></strong></div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle" id="vDetT">
        <thead class="table-light"><tr><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">P. unit</th><th class="text-end">Subtotal</th><th class="text-end">Costo</th><th class="text-end">Ganancia</th><th>Series</th><th></th></tr></thead>
        <tbody></tbody>
      </table>
    </div>
    <div class="row g-2">
      <div class="col-md-8 table-responsive"><table class="table table-sm table-borderless" id="vPagT"></table></div>
      <div class="col-md-4 text-end">
        <div>Costo total: <strong id="vCosto"></strong></div>
        <div class="text-success fw-semibold fs-5">Ganancia: <span id="vGan"></span></div>
      </div>
    </div>
  </div>
</div></div></div>

<!-- Modal motivo reversión -->
<div class="modal fade" id="modalRev" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Registrar reversión</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="small text-muted mb-2" id="revInfo"></div>
    <label class="form-label small text-muted mb-0">Motivo *</label>
    <textarea class="form-control" id="revMotivo" rows="2" required></textarea>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-warning" onclick="confirmarRev()"><i class="fas fa-undo me-1"></i>Revertir (genera bono)</button></div>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const alertBox=document.getElementById('alert');
let state={q:'',tipo:'',d1:'',d2:'',ceco:'',page:0,size:20,order:'id',dir:'DESC'};
let revDetId=null;
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
for(const id of ['q','tipoF','d1','d2','cecoF'])document.getElementById(id).addEventListener('input',function(){clearTimeout(t);t=setTimeout(()=>{
  state.q=document.getElementById('q').value.trim();state.tipo=document.getElementById('tipoF').value;state.d1=document.getElementById('d1').value;
  state.d2=document.getElementById('d2').value;state.ceco=document.getElementById('cecoF').value;state.page=0;cargar();},300);});
async function cargar(){
  const off=state.page*state.size;
  const p=`q=${encodeURIComponent(state.q)}&tipo_cliente=${state.tipo}&fecha_desde=${state.d1}&fecha_hasta=${state.d2}&ceco=${encodeURIComponent(state.ceco)}&limit=${state.size}&offset=${off}&order=${state.order}&dir=${state.dir}`;
  try{
    const d=await api('tienda/ventas.php?action=listar&'+p);
    document.getElementById('infoTotal').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+state.size,d.total)} de ${d.total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td class="fw-semibold">#${a.id}</td>
      <td>${esc(String(a.fecha_venta||a.fecha_creacion||'').replace('T',' ').slice(0,16))}</td>
      <td class="text-center">${a.tipo_cliente==='asociado'?'<span class="badge text-bg-info">Asoc</span>':'<span class="badge text-bg-secondary">Ext</span>'}</td>
      <td class="text-end">${money(a.total)}</td><td>${esc(a.ceco||'')}</td>
      <td class="text-center">${a.total_items||0}</td>
      <td class="text-center">${a.total_pagos||0}</td>
      <td class="text-center">${a.reversiones>0?'<span class="badge text-bg-warning">'+(a.reversiones)+'</span>':'—'}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="verVenta(${a.id})"><i class="fas fa-eye"></i></button></td>
    </tr>`).join('')||'<tr><td colspan="9" class="text-center text-muted">Sin resultados</td></tr>';
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
function go(p){state.page=p;document.querySelector('.card-body').scrollIntoView({behavior:'smooth'});cargar();}

async function verVenta(id){
  try{
    const v=await api('tienda/ventas.php?action=detalle&id='+id);
    document.getElementById('vTitulo').textContent='Venta #'+v.id;
    document.getElementById('vCliente').textContent=esc(v.cliente_nombre)+' '+(v.tipo_cliente==='asociado'?'('+esc(v.cedula)+')':'');
    document.getElementById('vCeco').textContent=esc(v.ceco||'');
    document.getElementById('vFecha').textContent=esc(v.fecha_venta||'');
    document.getElementById('vTotal').textContent=money(v.total);
    const detTable=document.querySelector('#vDetT tbody');
    detTable.innerHTML=v.detalles.map(d=>`<tr class="${d.revertida?'table-warning':''}">
      <td>${esc(d.producto_nombre)} ${d.revertida?'<span class="badge text-bg-warning">REVERTIDA</span>':''}</td>
      <td class="text-center">${d.cantidad}</td><td class="text-end">${money(d.precio_unitario)}</td><td class="text-end">${money(d.subtotal)}</td>
      <td class="text-end">${d.revertida?'—':money(d.costo_total)}</td><td class="text-end">${d.revertida?'—':money(d.ganancia)}</td>
      <td><span class="small">${d.series&&d.series.length?esc(d.series.join(', ')):'—'}</span></td>
      <td class="text-end">${(!d.revertida)?`<button class="btn btn-sm btn-outline-warning" onclick="abrirRev(${d.id},'${esc(d.producto_nombre)}',${d.cantidad})"><i class="fas fa-undo"></i></button>`:''}</td>
    </tr>`).join('');
    document.getElementById('vCosto').textContent=money(v.costo_total);
    document.getElementById('vGan').textContent=money(v.ganancia_total);
    document.getElementById('vPagT').innerHTML='<thead><tr><th>Tipo</th><th class="text-end">Monto</th><th>Nº</th><th>Bono</th></tr></thead><tbody>'+
      v.pagos.map(p=>`<tr><td>${esc(p.tipo)}</td><td class="text-end">${money(p.monto)}</td><td>${esc(p.numero_credito_sifone||'')}</td><td class="small">${p.bono_id?('#'+p.bono_id+' saldo '+money(p.bono_saldo_restante)):''}</td></tr>`).join('')+'</tbody>';
    new bootstrap.Modal(document.getElementById('modalV')).show();
  }catch(e){showA(e.message,false);}
}
function abrirRev(detId,nombre,cantidad){
  revDetId=detId; document.getElementById('revMotivo').value='';
  document.getElementById('revInfo').textContent=`Línea de ${nombre} (x${cantidad}). Al revertir, las unidades vuelven a inventario y se genera un BONO por el valor.`;
  new bootstrap.Modal(document.getElementById('modalRev')).show();
}
async function confirmarRev(){
  const motivo=document.getElementById('revMotivo').value.trim();
  if(!motivo){alert('Indique el motivo.');return;}
  try{
    const r=await post('tienda/reversiones.php?action=registrar',{venta_detalle_id:revDetId,motivo});
    bootstrap.Modal.getInstance(document.getElementById('modalRev')).hide();
    bootstrap.Modal.getInstance(document.getElementById('modalV')).hide();
    showA('Reversión #'+r.id+' registrada. Bono por '+money(r.monto)+' creado.',true);
    cargar();
  }catch(e){showA(e.message,false);}
}

window.Listado.mountSize('fSizeBox', state, cargar);
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>