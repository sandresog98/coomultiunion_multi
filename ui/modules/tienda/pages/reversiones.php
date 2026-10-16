<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.reversiones');
$canRevertir = ui_accion('tienda.reversiones', 'revertir', $staff['rol'] ?? '');
$canBonos    = ui_accion('tienda.reversiones', 'bonos',    $staff['rol'] ?? '');
$pageTitle = 'Reversiones y Bonos';
$currentPage = 'tienda_reversiones';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#paneRev">Reversiones</button></li>
  <?php if ($canBonos): ?>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#paneBonos">Bonos</button></li>
  <?php endif; ?>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="paneRev">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-undo me-2"></i>Reversiones</span>
        <span class="d-flex gap-2 align-items-center">
          <span class="small text-white-50" id="infoR"></span>
          <span id="fSizeR"></span>
          <?php if ($canRevertir): ?>
          <button class="btn btn-light btn-sm" onclick="abrirWizard()"><i class="fas fa-undo me-1"></i>Registrar reversión</button>
          <?php endif; ?>
        </span>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-auto" style="max-width:240px"><label class="form-label small text-muted mb-0">Buscar</label>
            <input class="form-control" id="qR" placeholder="Producto, venta o motivo…"></div>
          <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label>
            <input class="form-control" type="date" id="dR" style="width:160px"></div>
        </div>
        <div id="alertR" class="alert d-none"></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead><tr><th class="sortable" data-col="id">#</th><th>Fecha</th><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Valor</th><th class="text-end">Bono</th><th>Motivo</th><th>Usuario</th></tr></thead>
            <tbody id="tbodyR"></tbody>
          </table>
        </div>
        <nav id="pagR" class="d-flex justify-content-center mt-3"></nav>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="paneBonos">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-ticket-alt me-2"></i>Bonos</span>
        <span class="d-flex align-items-center gap-2">
          <span class="small text-white-50" id="infoB"></span>
          <span id="fSizeB"></span>
        </span>
      </div>
      <div class="card-body">
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-auto" style="max-width:260px"><label class="form-label small text-muted mb-0">Buscar</label>
            <input class="form-control" id="qB" placeholder="Cédula o nombre…"></div>
          <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label>
            <select class="form-select" id="estB" data-searchable style="width:130px"><option value="">Todos</option><option value="activo">Activo</option><option value="usado">Usado</option><option value="expirado">Expirado</option></select></div>
        </div>
        <div id="alertB" class="alert d-none"></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead><tr><th class="sortable" data-col="id">#</th><th>Acreedor</th><th class="text-end">Original</th><th class="text-end">Saldo</th><th class="sortable text-center" data-col="estado">Estado</th><th>Origen</th></tr></thead>
            <tbody id="tbodyB"></tbody>
          </table>
        </div>
        <nav id="pagB" class="d-flex justify-content-center mt-3"></nav>
      </div>
    </div>
  </div>
</div>

<!-- Wizard reversión -->
<div class="modal fade" id="modalWiz" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Registrar reversión</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label small text-muted mb-0">Venta reciente (sin líneas revertidas) *</label>
    <select class="form-select mb-2" id="wizVenta" onchange="wizLineas()"><option value="">Cargando…</option></select>
    <div id="wizLineasWrap" style="display:none">
      <label class="form-label small text-muted mb-0">Línea a revertir *</label>
      <select class="form-select mb-2" id="wizLinea"></select>
      <label class="form-label small text-muted mb-0">Motivo *</label>
      <textarea class="form-control" id="wizMotivo" rows="2"></textarea>
      <div class="small text-muted mt-2">Al revertir las unidades vuelven a inventario y se crea un bono por el valor.</div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-warning" onclick="wizGuardar()"><i class="fas fa-undo me-1"></i>Revertir línea</button></div>
</div></div></div>


<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const UI=<?php echo json_encode(uiBaseUrl()); ?>;
let stR={q:'',d1:'',page:0,size:20,order:'id',dir:'DESC'};
let stB={q:'',est:'',page:0,size:20,order:'id',dir:'DESC'};
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showA(id,m,ok){const e=document.getElementById(id);e.className='alert '+(ok?'alert-success':'alert-danger');e.textContent=m;e.classList.remove('d-none');}

document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',function(){
  const tp=this.closest('.tab-pane');if(!tp)return;
  const tab=tp.id==='paneRev';
  const s=tab?stR:stB;const col=th.dataset.col;
  if(s.order===col)s.dir=s.dir==='ASC'?'DESC':'ASC';else{s.order=col;s.dir='ASC';}
  s.page=0;tab?cargarR():cargarB();
}));
function renderSortIcons(tab){const s=tab==='R'?stR:stB;const sel=tab==='R'?'#paneRev .sortable':'#paneBonos .sortable';
  document.querySelectorAll(sel).forEach(th=>{
  const col=th.dataset.col,a=s.order===col;th.classList.toggle('active',a);
  const txt=th.textContent.trim().replace(/[▲▼]/g,'');th.innerHTML=txt+(a?` <span class="sort-icon">${s.dir==='ASC'?'▲':'▼'}</span>`:'');});}
function renderPagination(elId,total,s){
  const el=document.getElementById(elId),N=Math.ceil(total/s.size),C=s.page;
  if(N<=1){el.innerHTML='';return;}
  let pages=[];if(N<=10){for(let i=0;i<N;i++)pages.push(i);}else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h='';if(C>0)h+=`<li class="page-item"><a class="page-link" href="#" onclick="go('${elId}',${C-1})">«</a></li>`;
  pages.forEach(p=>{if(p==='...')h+='<li class="page-item disabled"><span class="page-link">…</span></li>';else h+=`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="go('${elId}',${p})">${p+1}</a></li>`;});
  if(C<N-1)h+=`<li class="page-item"><a class="page-link" href="#" onclick="go('${elId}',${C+1})">»</a></li>`;
  el.innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h}</ul>`;
}
function go(elId,p){const tab=elId==='pagR';const s=tab?stR:stB;s.page=p;tab?cargarR():cargarB();}
let t;
document.getElementById('qR').addEventListener('input',function(){clearTimeout(t);t=setTimeout(()=>{stR.q=this.value.trim();stR.page=0;cargarR();},300);});
document.getElementById('dR').addEventListener('input',function(){clearTimeout(t);t=setTimeout(()=>{stR.d1=this.value;stR.page=0;cargarR();},300);});
document.getElementById('qB').addEventListener('input',function(){clearTimeout(t);t=setTimeout(()=>{stB.q=this.value.trim();stB.page=0;cargarB();},300);});
document.getElementById('estB').addEventListener('change',function(){stB.est=this.value;stB.page=0;cargarB();});

async function cargarR(){
  const off=stR.page*stR.size;
  const p=`q=${encodeURIComponent(stR.q)}&fecha_desde=${stR.d1}&limit=${stR.size}&offset=${off}&order=${stR.order}&dir=${stR.dir}`;
  try{
    const d=await api('tienda/reversiones.php?action=listar&'+p);
    document.getElementById('infoR').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+stR.size,d.total)} de ${d.total}`:'';
    renderSortIcons('R');
    document.getElementById('tbodyR').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td>${a.id}</td><td>${esc(String(a.fecha_creacion||'').replace('T',' ').slice(0,16))}</td>
      <td class="fw-semibold">${esc(a.producto_nombre)} <span class="text-muted small">(venta #${a.venta_id})</span></td>
      <td class="text-center">${a.cantidad}</td><td class="text-end">${money(a.subtotal)}</td>
      <td class="text-end">${a.bono_saldo!=null?money(a.bono_saldo):'—'}</td>
      <td class="small">${esc(a.motivo||'')}</td><td>${esc(a.usuario_nombre||'')}</td>
    </tr>`).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination('pagR',d.total,stR);
  }catch(e){showA('alertR',e.message,false);}
}
async function cargarB(){
  const off=stB.page*stB.size;
  const p=`q=${encodeURIComponent(stB.q)}&estado=${stB.est}&limit=${stB.size}&offset=${off}&order=${stB.order}&dir=${stB.dir}`;
  try{
    const d=await api('tienda/bonos.php?action=listar&'+p);
    document.getElementById('infoB').textContent=d.total>0?`Mostrando ${off+1}-${Math.min(off+stB.size,d.total)} de ${d.total}`:'';
    renderSortIcons('B');
    document.getElementById('tbodyB').innerHTML=(d.rows||[]).map(a=>`<tr>
      <td>${a.id}</td><td>${esc(a.acreedor_nombre||a.cedula||a.cliente_id||'—')}</td>
      <td class="text-end">${money(a.monto_original)}</td><td class="text-end">${money(a.saldo)}</td>
      <td class="text-center">${a.estado==='activo'?'<span class="badge text-bg-success">activo</span>':(a.estado==='usado'?'<span class="badge text-bg-secondary">usado</span>':'<span class="badge text-bg-danger">expirado</span>')}</td>
      <td class="small">${a.origen_reversion_id?'reversión #'+a.origen_reversion_id:'—'}</td>
    </tr>`).join('')||'<tr><td colspan="6" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination('pagB',d.total,stB);
  }catch(e){showA('alertB',e.message,false);}
}

// wizard
let wizLineasCache=[];
async function abrirWizard(){
  document.getElementById('wizMotivo').value='';document.getElementById('wizLineasWrap').style.display='none';
  new bootstrap.Modal(document.getElementById('modalWiz')).show();
  try{
    const d=await api('tienda/ventas.php?action=listar&limit=50');
    document.getElementById('wizVenta').innerHTML='<option value="">Seleccione…</option>'+d.rows.filter(v=>v.reversible).map(v=>`<option value="${v.id}">#${v.id} — ${esc(v.cliente_nombre)} — ${money(v.total)}</option>`).join('');
  }catch(e){}
}
async function wizLineas(){
  const vid=document.getElementById('wizVenta').value;
  if(!vid){document.getElementById('wizLineasWrap').style.display='none';return;}
  try{
    const v=await api('tienda/ventas.php?action=detalle&id='+vid);
    wizLineasCache=v.detalles.filter(x=>!x.revertida);
    document.getElementById('wizLinea').innerHTML=wizLineasCache.map((l,i)=>`<option value="${l.id}">${esc(l.producto_nombre)} x${l.cantidad} — ${money(l.subtotal)}</option>`).join('')||'<option value="">Sin líneas revertibles</option>';
    document.getElementById('wizLineasWrap').style.display='';
  }catch(e){}
}
async function wizGuardar(){
  const detId=document.getElementById('wizLinea').value;
  const motivo=document.getElementById('wizMotivo').value.trim();
  if(!detId){alert('Seleccione la línea a revertir.');return;}
  if(!motivo){alert('Indique el motivo.');return;}
  try{
    const r=await post('tienda/reversiones.php?action=registrar',{venta_detalle_id:detId,motivo});
    bootstrap.Modal.getInstance(document.getElementById('modalWiz')).hide();
    showA('alertR','Reversión #'+r.id+' registrada. Bono por '+money(r.monto)+' creado.',true);
    cargarR();
  }catch(e){showA('alertR',e.message,false);}
}

window.Listado.mountSize('fSizeR', stR, cargarR, {id:'fSizeR'});
window.Listado.mountSize('fSizeB', stB, cargarB, {id:'fSizeB'});
cargarR(); cargarB();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>