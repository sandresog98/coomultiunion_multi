<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.transacciones');
$acc = ui_acciones('movimientos', $staff['rol'] ?? '');
$canCrear = ui_accion('movimientos', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('movimientos', 'editar',   $staff['rol'] ?? '');
$canElim  = ui_accion('movimientos', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Movimientos';
$currentPage = 'movimientos';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-coins me-2"></i>Movimientos (ingresos y egresos)</span>
    <span class="small text-white-50" id="infoTotal"></span>
    <?php if ($canCrear): ?>
    <button class="btn btn-light btn-sm" onclick="abrir()"><i class="fas fa-plus me-1"></i>Nuevo</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:300px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Nombre, cédula, referido…"></div>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Tipo</label>
        <select class="form-select" id="fTipo" style="width:200px"><option value="">Todos</option></select>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Desde</label><input type="date" class="form-control" id="fDesde" style="width:140px"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Hasta</label><input type="date" class="form-control" id="fHasta" style="width:140px"></div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="fecha">Fecha</th>
          <th>Asociado</th>
          <th class="sortable" data-col="tipo">Tipo</th>
          <th class="sortable text-end" data-col="valor">Valor</th>
          <th>Detalle</th>
          <th>Creado por</th>
          <th class="text-end">Acciones</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="7" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<div class="modal fade" id="m" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mt">Nuevo movimiento</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Tipo <span class="text-danger">*</span></label>
        <select class="form-select" id="f_tipo" onchange="renderTipo()"><option value="">Cargando…</option></select>
      </div>
      <div class="col-md-6">
        <label class="form-label">Fecha <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="f_fecha">
      </div>
      <div class="col-md-6 position-relative">
        <label class="form-label">Asociado <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="f_cedula_input" placeholder="Cédula o nombre" autocomplete="off">
        <input type="hidden" id="f_cedula">
        <div id="resIni" class="list-group position-absolute w-100" style="top:100%; left:0; z-index:1070; max-height:200px; overflow:auto; background:#fff; border:1px solid #dee2e6; border-top:none; box-shadow:0 4px 10px rgba(0,0,0,0.1);"></div>
      </div>
      <div class="col-md-6 position-relative" id="wrapReferido">
        <label class="form-label">Asociado referido <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="f_referido_input" placeholder="Cédula o nombre" autocomplete="off">
        <input type="hidden" id="f_referido">
        <div id="resRef" class="list-group position-absolute w-100" style="top:100%; left:0; z-index:1070; max-height:200px; overflow:auto; background:#fff; border:1px solid #dee2e6; border-top:none; box-shadow:0 4px 10px rgba(0,0,0,0.1);"></div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Valor <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" class="form-control" id="f_valor" placeholder="0">
      </div>
      <div class="col-12">
        <label class="form-label">Observaciones</label>
        <input type="text" class="form-control" id="f_obs" placeholder="Opcional">
      </div>
      <div class="col-12 d-none" id="dispInfo">
        <div class="bg-light rounded-3 p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
          <div>
            <small class="text-muted d-block">Disponible para redención</small>
            <strong id="dispValor" class="text-success">$0</strong>
            <small class="text-muted d-block">Se registrará como negativo (reduce el saldo)</small>
          </div>
          <button class="btn btn-sm btn-outline-success" type="button" onclick="usarDisponible()"><i class="fas fa-magic me-1"></i>Usar</button>
        </div>
      </div>
    </div>
  </div>
  <div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-brand" onclick="guardar()"><i class="fas fa-save me-1"></i>Guardar</button>
  </div>
</div></div></div>

<input type="hidden" id="editId">



<script>
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric'});}
const API=<?php echo json_encode(apiUrl()); ?>, PAGE_SIZE=20, alertBox=document.getElementById('alert');
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['movimientos.editar']===true,CAN_ELIM=ACC['movimientos.eliminar']===true;
let state={q:'',tipo:'',desde:'',hasta:'',page:0,order:'fecha',dir:'DESC'};
let editId=null, debounceTimer, modal, TIPOS={};
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

document.querySelectorAll('.sortable').forEach(th=>{
  th.addEventListener('click',()=>{
    const col=th.dataset.col;
    if(state.order===col) state.dir=state.dir==='ASC'?'DESC':'ASC';
    else{state.order=col;state.dir='DESC';}
    state.page=0;cargar();
  });
});
function renderSortIcons(){
  document.querySelectorAll('.sortable').forEach(th=>{
    const col=th.dataset.col, active=state.order===col;
    th.classList.toggle('active',active);
    const txt=th.textContent.trim().replace(/[▲▼]/g,'');
    const arrow=active?(state.dir==='ASC'?'▲':'▼'):'';
    th.innerHTML=txt+(arrow?` <span class="sort-icon">${arrow}</span>`:'');
  });
}
document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
['fTipo','fDesde','fHasta'].forEach(id=>document.getElementById(id).addEventListener('change',function(){state[id.substring(1)]=this.value;state.page=0;cargar();}));

function badgeTipo(r){
  const m={comision:'info',redencion_comisiones:'warning',ingreso_extra:'success',descuento_egreso:'danger',interes:'primary',aporte_extra:'info',ajuste:'secondary'};
  const c=r.clasificacion==='egreso'?'<span class="badge text-bg-danger">egreso</span>':'<span class="badge text-bg-success">ingreso</span>';
  return `<span class="badge text-bg-${m[r.tipo]||'secondary'}">${esc(r.label||r.tipo)}</span> ${c}`;
}

async function cargar(){
  const off=state.page*PAGE_SIZE;
  const bs=`q=${encodeURIComponent(state.q)}&tipo=${state.tipo}&fecha_desde=${state.desde}&fecha_hasta=${state.hasta}&order=${state.order}&dir=${state.dir}&limit=${PAGE_SIZE}&offset=${off}`;
  try{
    const d=await api('movimientos/listar?'+bs);
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,total)} de ${total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>{
      const det=r.detalle||{};
      let detHtml='';
      if(r.tipo==='comision') detHtml+=`Ref: <b>${esc(det.asociado_referido||'—')}</b>`;
      if(det.observaciones) detHtml+=(detHtml?'<br>':'')+`<small class="text-muted">${esc(det.observaciones)}</small>`;
      if(!detHtml) detHtml=det.texto?esc(det.texto):'—';
      return `<tr>
        <td><small>${fmtDt(r.fecha)}</small></td>
        <td><span class="fw-semibold">${esc(r.nombre_asociado||r.cedula)}</span><br><small class="text-muted">CC ${esc(r.cedula)}</small></td>
        <td>${badgeTipo(r)}</td>
        <td class="text-end fw-semibold">${money(r.valor)}</td>
        <td><small>${detHtml}</small></td>
        <td><small>${esc(r.creado_por_nombre||'—')}</small></td>
        <td class="text-end">
          ${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary me-1" onclick='abrir(${JSON.stringify(r)})'><i class="fas fa-edit"></i></button>`:''}
          ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="elim(${r.id})"><i class="fas fa-trash"></i></button>`:''}
        </td></tr>`;
    }).join('')||'<tr><td colspan="7" class="text-center text-muted">Sin registros</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

function renderPagination(t){
  const N=Math.ceil(t/PAGE_SIZE);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}
  const C=state.page;let pages=[];
  if(N<=10){for(let i=0;i<N;i++)pages.push(i);}
  else{pages.push(0);if(C>3)pages.push('...');for(let i=Math.max(1,C-2);i<=Math.min(N-2,C+2);i++)pages.push(i);if(C<N-4)pages.push('...');pages.push(N-1);}
  let h=[];
  if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);
  pages.forEach(p=>{if(p==='...')h.push('<li class="page-item disabled"><span class="page-link">…</span></li>');else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);});
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);
  document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}
function goPage(p){state.page=p;cargar();}

function setupAutocomplete(inputId,hiddenId,resId,onPick){
  const input=document.getElementById(inputId),hidden=document.getElementById(hiddenId),cont=document.getElementById(resId);
  let items=[],selIdx=-1;
  function pick(btn){
    hidden.dataset.nombre=btn.dataset.nm;
    hidden.value=btn.dataset.ce;
    input.value=btn.dataset.nm+' ('+btn.dataset.ce+')';
    cont.innerHTML='';selIdx=-1;
    if(onPick)onPick();
  }
  function render(){
    cont.innerHTML=items.map((it,i)=>`<button type="button" class="list-group-item list-group-item-action${i===selIdx?' active':''}" data-ce="${esc(it.cedula)}" data-nm="${esc(it.nombre)}">${esc(it.cedula)} — ${esc(it.nombre)}</button>`).join('');
    cont.querySelectorAll('button').forEach((b,i)=>b.addEventListener('mousedown',e=>{e.preventDefault();pick(b);}));
  }
  input.addEventListener('input',function(){
    const q=(input.value||'').trim();selIdx=-1;
    clearTimeout(debounceTimer);
    if(q.length<1){items=[];cont.innerHTML='';return;}
    cont.innerHTML='<div class="list-group-item text-muted">Buscando…</div>';
    debounceTimer=setTimeout(async()=>{
      try{
        const d=await api('asociados/listar?q='+encodeURIComponent(q)+'&limit=20');
        items=d.rows||[];
        if(!items.length){items=[];cont.innerHTML='<div class="list-group-item text-muted">Sin resultados</div>';return;}
        selIdx=(selIdx<0?0:Math.min(selIdx,items.length-1));
        render();
      }catch(_){items=[];cont.innerHTML='<div class="list-group-item text-danger">Error de búsqueda</div>';}
    },300);
  });
  input.addEventListener('keydown',function(e){
    if(!items.length)return;
    if(e.key==='ArrowDown'){e.preventDefault();selIdx=(selIdx+1)%items.length;highlight();}
    else if(e.key==='ArrowUp'){e.preventDefault();selIdx=(selIdx-1+items.length)%items.length;highlight();}
    else if(e.key==='Enter'){e.preventDefault();const b=cont.querySelectorAll('button')[Math.max(0,selIdx)];if(b)pick(b);}
    else if(e.key==='Escape'){items=[];cont.innerHTML='';}
  });
  function highlight(){cont.querySelectorAll('button').forEach((b,i)=>b.classList.toggle('active',i===selIdx));}
  document.addEventListener('mousedown',e=>{if(!cont.contains(e.target)&&e.target!==input){items=[];cont.innerHTML='';}});
}

let dispDisponible=null;
function onAsociadoSeleccionado(){
  const t=document.getElementById('f_tipo').value, tp=TIPOS[t];
  if(tp&&tp.valida_disponible==1 && document.getElementById('f_cedula').value) cargarDisponible();
  else ocultarDisponible();
}
async function cargarDisponible(){
  const ced=document.getElementById('f_cedula').value.trim();if(!ced)return;
  try{
    const d=await api('movimientos/disponible?cedula='+encodeURIComponent(ced));
    dispDisponible=d.disponible||0;
    document.getElementById('dispValor').textContent=money(dispDisponible);
    document.getElementById('dispInfo').classList.remove('d-none');
  }catch(_){ocultarDisponible();}
}
function ocultarDisponible(){dispDisponible=null;document.getElementById('dispInfo').classList.add('d-none');}
function usarDisponible(){if(dispDisponible!==null)document.getElementById('f_valor').value=dispDisponible;}

function renderTipo(){
  const t=document.getElementById('f_tipo').value, tp=TIPOS[t];
  document.getElementById('wrapReferido').style.display=(tp&&tp.requiere_referido==1)?'':'none';
  if(tp&&tp.valida_disponible==1){if(document.getElementById('f_cedula').value)cargarDisponible();}
  else ocultarDisponible();
}

function abrir(r){
  editId=r?r.id:null;
  document.getElementById('mt').textContent=editId?'Editar movimiento':'Nuevo movimiento';
  document.getElementById('f_tipo').value=r?(r.tipo||''):'';
  document.getElementById('f_fecha').value=r?(r.fecha||''):'';
  document.getElementById('f_cedula').value=r?(r.cedula||''):'';
  const det=r?(r.detalle||{}):{};
  document.getElementById('f_cedula_input').value=r?((r.nombre_asociado||'')+' ('+(r.cedula||'')+')'):'';
  document.getElementById('f_referido').value=r?(det.asociado_referido||''):'';
  document.getElementById('f_referido_input').value=r?(det.asociado_referido||''):'';
  document.getElementById('f_valor').value=r?Math.abs(Number(r.valor||0)):''; // se muestra magnitud (redención negativa se guarda sola)
  document.getElementById('f_obs').value=r?(det.observaciones||''):'';
  renderTipo();
  modal.show();
}

async function guardar(){
  const data={
    tipo:document.getElementById('f_tipo').value,
    fecha:document.getElementById('f_fecha').value,
    cedula:document.getElementById('f_cedula').value.trim(),
    asociado_referido:document.getElementById('f_referido').value.trim(),
    valor:parseFloat(document.getElementById('f_valor').value)||0,
    observaciones:document.getElementById('f_obs').value.trim(),
  };
  if(!data.cedula){alert('Seleccione el asociado.');return;}
  if(!data.tipo){alert('Seleccione el tipo de movimiento.');return;}
  if(!data.fecha){alert('La fecha es obligatoria.');return;}
  if(!(data.valor>0)){alert('El valor debe ser mayor a cero.');return;}
  const tp=TIPOS[data.tipo];
  if(tp&&tp.requiere_referido==1&&!data.asociado_referido){alert('Para este tipo es obligatorio el asociado referido.');return;}
  if(tp&&tp.requiere_referido==1&&data.asociado_referido===data.cedula){alert('El asociado referido no puede ser el mismo.');return;}
  if(tp&&tp.valida_disponible==1&&dispDisponible!==null&&data.valor>dispDisponible){alert('La redención no puede superar el disponible ('+money(dispDisponible)+').');return;}
  try{
    if(editId){data.id=editId;await api('movimientos/editar',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}
    else{await api('movimientos/crear',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}
    modal.hide();cargar();
  }catch(e){alert(e.message);}
}

async function elim(id){
  if(!confirm('¿Eliminar el movimiento #'+id+'?'))return;
  try{await api('movimientos/eliminar',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});cargar();}
  catch(e){alert(e.message);}
}

async function cargarTipos(){
  try{
    const rows=await api('movimientos_tipos/activos');
    TIPOS={};
    rows.forEach(t=>{TIPOS[t.tipo]={nombre:t.nombre,clasificacion:t.clasificacion,requiere_referido:t.requiere_referido,valida_disponible:t.valida_disponible};});
    const selM=document.getElementById('f_tipo'), selF=document.getElementById('fTipo');
    selM.innerHTML=rows.map(t=>`<option value="${esc(t.tipo)}">${esc(t.nombre)}</option>`).join('')||'<option value="">Sin tipos</option>';
    selF.innerHTML='<option value="">Todos</option>'+rows.map(t=>`<option value="${esc(t.tipo)}">${esc(t.nombre)}</option>`).join('');
    renderTipo();
  }catch(e){alert(e.message);}
}

document.addEventListener('DOMContentLoaded',()=>{
  modal=new bootstrap.Modal('#m');
  setupAutocomplete('f_cedula_input','f_cedula','resIni',onAsociadoSeleccionado);
  setupAutocomplete('f_referido_input','f_referido','resRef');
  document.getElementById('m').addEventListener('hidden.bs.modal',()=>{document.getElementById('resIni').innerHTML='';document.getElementById('resRef').innerHTML='';ocultarDisponible();});
  cargarTipos().then(()=>cargar());
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>