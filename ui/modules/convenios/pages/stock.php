<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('convenios.boletas');
$acc = ui_acciones('convenios.stock', $staff['rol'] ?? '');
$canCrear   = ui_accion('convenios.stock', 'crear',         $staff['rol'] ?? '');
$canCargar  = ui_accion('convenios.stock', 'crear',         $staff['rol'] ?? '');
$canVender  = ui_accion('convenios.stock', 'vender',        $staff['rol'] ?? '');
$canCont    = ui_accion('convenios.stock', 'contabilizar',  $staff['rol'] ?? '');
$canDeshVar = ui_accion('convenios.stock', 'deshacer_venta',$staff['rol'] ?? '');
$canAnular  = ui_accion('convenios.stock', 'anular',        $staff['rol'] ?? '');
$canDesanul = ui_accion('convenios.stock', 'desanular',     $staff['rol'] ?? '');
$canElim    = ui_accion('convenios.stock', 'eliminar',      $staff['rol'] ?? '');
$pageTitle = 'Stock de boletas';
$currentPage = 'convenios_stock';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="fas fa-ticket-alt me-2"></i>Stock de boletas</span>
    <span>
      <button class="btn btn-light btn-sm me-1" onclick="exportar('boletas')" title="Descargar todas"><i class="fas fa-file-excel me-1"></i>Excel</button>
      <button class="btn btn-light btn-sm me-1" onclick="exportar('vendidas')" title="Descargar vendidas/contabilizadas"><i class="fas fa-file-excel me-1"></i>Vendidas</button>
      <?php if ($canCargar): ?>
      <button class="btn btn-light btn-sm me-1" onclick="abrirCarga()" title="Carga masiva desde Excel"><i class="fas fa-file-import me-1"></i>Carga masiva</button>
      <?php endif; ?>
      <?php if ($canCrear): ?>
      <button class="btn btn-light btn-sm" onclick="abrirNueva()"><i class="fas fa-plus me-1"></i>Nueva boleta</button>
      <?php endif; ?>
    </span>
  </div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>

    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <label class="form-label small text-muted mb-0">Producto (boleta)</label>
        <select class="form-select form-select-sm" id="fProducto" data-searchable><option value="0">Todos</option></select>
      </div>
    </div>

    <div class="row g-2 mb-3 text-center" id="kpis"></div>

    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Estados</label>
        <div class="d-flex gap-2 flex-wrap" id="fEstados">
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="est_disponible" value="disponible" checked><label class="form-check-label small" for="est_disponible">Disponible</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="est_vendida" value="vendida" checked><label class="form-check-label small" for="est_vendida">Vendida</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="est_contabilizada" value="contabilizada" checked><label class="form-check-label small" for="est_contabilizada">Contabilizada</label></div>
          <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="est_anulada" value="anulada"><label class="form-check-label small" for="est_anulada">Anulada</label></div>
        </div>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <input class="form-control form-control-sm" id="q" placeholder="Serial, factura, comprador">
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Cédula</label>
        <input class="form-control form-control-sm" id="fCedula" placeholder="Cédula comprador">
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Venta desde</label>
        <input class="form-control form-control-sm" type="date" id="fvDesde">
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Venta hasta</label>
        <input class="form-control form-control-sm" type="date" id="fvHasta">
      </div>
    </div>

    <div class="mb-2 d-flex align-items-center gap-2"><span class="badge text-bg-info" id="infoTotal">Mostrando 0-0 de 0</span><span id="fSizeBox"></span></div>
    <div class="table-responsive">
      <table class="table table-hover align-middle table-sm">
        <thead><tr>
          <th class="sortable" data-col="serial">Serial</th>
          <th class="sortable" data-col="producto">Producto</th>
          <th class="sortable text-end" data-col="precio_compra">Compra</th>
          <th class="sortable text-end" data-col="precio_venta">Venta</th>
          <th class="sortable" data-col="estado">Estado</th>
          <th class="sortable" data-col="vencimiento">Vence</th>
          <th>Comprador</th>
          <th>Pago</th>
          <th class="sortable" data-col="fecha_vendida">Vendida</th>
          <th class="text-end">Acciones</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="10" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion"></nav>
  </div>
</div>

<!-- Modal nueva boleta -->
<div class="modal fade" id="modalNueva" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Nueva boleta</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Producto *</label><select class="form-select" id="n_producto"></select></div>
        <div class="col-md-6"><label class="form-label">Serial *</label><input class="form-control" id="n_serial"></div>
        <div class="col-md-6"><label class="form-label">Nº factura *</label><input class="form-control" id="n_factura"></div>
        <div class="col-md-6"><label class="form-label">Fecha vencimiento</label><input class="form-control" type="date" id="n_venc"></div>
        <div class="col-md-6"><label class="form-label">Precio compra</label><input class="form-control" type="number" step="1" min="0" id="n_compra"></div>
        <div class="col-md-6"><label class="form-label">Precio venta</label><input class="form-control" type="number" step="1" min="0" id="n_venta"></div>
        <div class="col-12"><label class="form-label">Archivo (jpg/png/pdf)</label><input class="form-control" type="file" id="n_archivo" accept=".jpg,.jpeg,.png,.pdf"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-brand" onclick="guardarNueva()">Guardar</button></div>
  </div></div>
</div>

<!-- Modal carga masiva -->
<div class="modal fade" id="modalCarga" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-file-import me-2"></i>Carga masiva de boletas</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="alert alert-info small mb-3">
        <i class="fas fa-info-circle me-1"></i>Descarga la plantilla, llénala y súbela. Al analizar podrás <strong>validar y editar</strong> cada registro antes de cargar; <strong>si hay errores no se permitirá cargar</strong>.
      </div>
      <div class="row g-3 align-items-end">
        <div class="col-md-6">
          <label class="form-label small text-muted mb-0">1 · Plantilla</label>
          <a class="btn btn-outline-primary btn-sm w-100" id="cargaPlantilla" download><i class="fas fa-download me-1"></i>Descargar plantilla (.xlsx)</a>
        </div>
        <div class="col-md-6">
          <label class="form-label small text-muted mb-0">2 · Archivo a cargar (.xls/.xlsx)</label>
          <input class="form-control form-control-sm" type="file" id="cargaArchivo" accept=".xls,.xlsx">
        </div>
      </div>
      <details class="mt-3">
        <summary class="small text-muted"><i class="fas fa-list me-1"></i>Productos de stock (PRODUCTO_ID)</summary>
        <div class="table-responsive mt-2">
          <table class="table table-sm table-bordered small mb-0">
            <thead class="table-light"><tr><th>ID</th><th>Producto</th><th>Convenio</th></tr></thead>
            <tbody id="cargaProductos"><tr><td colspan="3" class="text-muted">Cargando…</td></tr></tbody>
          </table>
        </div>
      </details>
      <div class="d-none" id="cargaAlert"></div>
      <div class="d-grid mt-3">
        <button class="btn btn-brand" id="btnAnalizar" onclick="analizarCarga()"><i class="fas fa-search me-1"></i>Analizar archivo</button>
      </div>
    </div>
  </div></div>
</div>

<!-- Modal vender -->
<div class="modal fade" id="modalVender" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Vender boleta <span id="vSerial" class="text-muted"></span></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="v_id">
      <div class="row g-3">
        <div class="col-12 position-relative">
          <label class="form-label">Comprador (cédula — nombre) *</label>
          <input class="form-control" id="v_cedula_input" placeholder="Escriba cédula o nombre">
          <input type="hidden" id="v_cedula">
          <div class="list-group position-absolute w-100" id="v_res" style="z-index:1050;max-height:220px;overflow:auto"></div>
        </div>
        <div class="col-md-6"><label class="form-label">Método de venta</label>
          <select class="form-select" id="v_metodo">
            <option value="credito">Crédito</option>
            <option value="regalo_cooperativa">Regalo cooperativa</option>
          </select></div>
        <div class="col-12">
          <label class="form-label">Pago / vínculo financiero</label>
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="v_fin" id="fin_none" value="none" checked><label class="btn btn-outline-primary btn-sm" for="fin_none">Sin vínculo</label>
            <input type="radio" class="btn-check" name="v_fin" id="fin_trx" value="transaccion"><label class="btn btn-outline-primary btn-sm" for="fin_trx">Crear transacción</label>
            <input type="radio" class="btn-check" name="v_fin" id="fin_sif" value="sifone"><label class="btn btn-outline-primary btn-sm" for="fin_sif">Nº movimiento Sifone</label>
          </div>
        </div>
        <div class="col-md-6 d-none" id="wrapSif"><label class="form-label">Nº movimiento Sifone</label><input class="form-control" id="v_sifone" placeholder="Ej. 12345678"></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-brand" onclick="guardarVenta()">Vender</button></div>
  </div></div>
</div>

<!-- Modal contabilizar -->
<div class="modal fade" id="modalCont" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Contabilizar boleta</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="c_id">
      <label class="form-label">Comprobante *</label><input class="form-control" id="c_comprobante">
    </div>
    <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-brand" onclick="guardarCont()">Contabilizar</button></div>
  </div></div>
</div>

<!-- Modal ver archivo (re-auth por contraseña) -->
<div class="modal fade" id="modalArchivo" tabindex="-1">
  <div class="modal-dialog modal-sm"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Verificar identidad</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="a_id">
      <label class="form-label">Contraseña</label><input class="form-control" type="password" id="a_pass">
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-brand" onclick="verArchivo('view')"><i class="fas fa-eye me-1"></i>Ver</button>
      <button class="btn btn-outline-brand" onclick="verArchivo('download')"><i class="fas fa-download me-1"></i>Descargar</button>
    </div>
  </div></div>
</div>

<!-- Modal timeline -->
<div class="modal fade" id="modalTimeline" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="fas fa-history me-2"></i>Histórico de eventos</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><div id="timeline"></div></div>
  </div></div>
</div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>, U = <?php echo json_encode(uiBaseUrl()); ?>;
const ACC = <?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_VENDER=ACC['convenios.stock.vender']===true;
const CAN_CONT  =ACC['convenios.stock.contabilizar']===true;
const CAN_DESHVAR=ACC['convenios.stock.deshacer_venta']===true;
const CAN_ANULAR=ACC['convenios.stock.anular']===true;
const CAN_DESANULAR=ACC['convenios.stock.desanular']===true;
const CAN_ELIMINAR=ACC['convenios.stock.eliminar']===true;
const alertBox = document.getElementById('alert');
let modalNueva, modalVender, modalCont, modalArchivo, modalTimeline, modalCarga;
let debounceTimer, state = { prod:0, estados:['disponible','vendida','contabilizada'], q:'', cedula:'', fvDesde:'', fvHasta:'', page:0, size:20, order:'fecha_creacion', dir:'DESC' };
const ROW = {}; // filas por id para las acciones (evita JSON en atributos onclick)

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return'$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v.replace(' ','T'));return isNaN(d.getTime())?'—':d.toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric'});}
function showAlert(msg,ok=false){alertBox.className='alert alert-'+(ok?'success':'danger');alertBox.textContent=msg;alertBox.classList.remove('d-none');}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
function post(path,data){return api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}

const ESTADO_BADGE={disponible:'<span class="badge text-bg-success">Disponible</span>',vendida:'<span class="badge text-bg-primary">Vendida</span>',contabilizada:'<span class="badge text-bg-dark">Contabilizada</span>',anulada:'<span class="badge text-bg-danger">Anulada</span>'};

document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',()=>{
  const col=th.dataset.col;
  if(state.order===col)state.dir=state.dir==='ASC'?'DESC':'ASC';else{state.order=col;state.dir='DESC';}
  state.page=0;cargar();
}));
function renderSortIcons(){document.querySelectorAll('.sortable').forEach(th=>{
  const col=th.dataset.col,a=state.order===col;th.classList.toggle('active',a);
  const txt=th.textContent.trim().replace(/[▲▼]/g,'');
  th.innerHTML=txt+(a?` <span class="sort-icon">${state.dir==='ASC'?'▲':'▼'}</span>`:'');
});}

async function cargarKpis(){
  try{
    const d=await api('boletas/resumen?producto_id='+state.prod);
    const k=d.kpis||{};
    const cards=[
      ['Disponibles', k.disponibles||0, 'text-bg-success'],
      ['Vendidas', k.vendidas||0, 'text-bg-primary'],
      ['Contabilizadas', k.contabilizadas||0, 'text-bg-dark'],
      ['Anuladas', k.anuladas||0, 'text-bg-danger'],
      ['Ganancia', money(k.ganancia||0), 'text-bg-warning'],
    ];
    document.getElementById('kpis').innerHTML=cards.map(c=>`
      <div class="col"><div class="card mb-0 ${c[2]} text-white"><div class="card-body py-2 px-3">
        <div class="small opacity-75">${c[0]}</div><div class="fw-bold fs-5">${c[1]}</div>
      </div></div></div>`).join('');
  }catch(e){showAlert(e.message);}
}

async function cargarProductos(){
  try{
    const d=await api('boletas/resumen?producto_id=0');
    const opts=(d.productos||[]).map(p=>`<option value="${p.id}" data-c="${p.precio_compra||0}" data-v="${p.precio_venta||0}">${esc(p.convenio_nombre)} — ${esc(p.nombre)}</option>`).join('');
    document.getElementById('fProducto').innerHTML='<option value="0">Todos</option>'+opts;
    document.getElementById('n_producto').innerHTML='<option value="">Seleccione…</option>'+opts;
  }catch(e){showAlert(e.message);}
}

async function cargar(){
  const off=state.page*state.size;
  const est=state.estados.join(',');
  const bs=`producto_id=${state.prod}&estados[]=${est.split(',').join('&estados[]=')}&q=${encodeURIComponent(state.q)}&cedula=${encodeURIComponent(state.cedula)}&fecha_vendida_desde=${state.fvDesde}&fecha_vendida_hasta=${state.fvHasta}&order=${state.order}&dir=${state.dir}&limit=${state.size}&offset=${off}`;
  try{
    const d=await api('boletas/listar?'+bs);
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total?`Mostrando ${off+1}-${Math.min(off+state.size,total)} de ${total}`:'';
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>{
      ROW[r.id] = { serial: r.serial, precio: r.precio_venta_snapshot };
      return `
      <tr>
        <td><strong>${esc(r.serial)}</strong>${r.archivo_ruta?` <a href="#" class="text-primary" onclick="abrirArchivo(${r.id});return false;" title="Ver archivo"><i class="fas fa-paperclip"></i></a>`:''}<div class="small text-muted">#${r.id}</div></td>
        <td>${esc(r.producto_nombre||('#'+r.producto_id))}<div class="small text-muted">${esc(r.convenio_nombre||'')}</div></td>
        <td class="text-end">${money(r.precio_compra_snapshot)}</td>
        <td class="text-end">${money(r.precio_venta_snapshot)}</td>
        <td>${ESTADO_BADGE[r.estado]||r.estado}${r.estado==='disponible'?`<div class="small text-muted mt-1">${esc(r.vencimiento_flag==='VENDIDA/OTRO'?'':r.vencimiento_flag)}</div>`:''}</td>
        <td class="small">${r.fecha_vencimiento||'—'}</td>
        <td>${r.asociado_cedula?`${esc(r.asociado_nombre||'')}<div class="small text-muted">${esc(r.asociado_cedula)}</div>`:'—'}</td>
        <td class="small">${r.sifone_numero?`<span class="badge text-bg-secondary" title="Movimiento Sifone">S ${esc(r.sifone_numero)}</span>`:''}${r.transaccion_id?`<span class="badge text-bg-secondary" title="Transacción">T #${r.transaccion_id}</span>`:''}${r.metodo_venta&&r.metodo_venta!=='credito'?` <span class="badge text-bg-warning">${esc(r.metodo_venta)}</span>`:''}</td>
        <td class="small">${r.fecha_vendida?fmtDt(r.fecha_vendida):'—'}</td>
        <td class="text-end text-nowrap">
          <button class="btn btn-sm btn-outline-info" onclick="verTimeline(${r.id})" title="Histórico"><i class="fas fa-history"></i></button>
          ${CAN_VENDER&&r.estado==='disponible'?`<button class="btn btn-sm btn-outline-primary" onclick="abrirVenta(${r.id})" title="Vender"><i class="fas fa-cash-register"></i></button>`:''}
          ${CAN_CONT&&r.estado==='vendida'?`<button class="btn btn-sm btn-outline-secondary" onclick="contabilizar(${r.id})" title="Contabilizar"><i class="fas fa-check-double"></i></button>`:''}${CAN_DESHVAR&&r.estado==='vendida'?`<button class="btn btn-sm btn-outline-warning" onclick="deshacerVenta(${r.id})" title="Deshacer venta"><i class="fas fa-undo"></i></button>`:''}
          ${CAN_ANULAR&&r.estado!=='anulada'?`<button class="btn btn-sm btn-outline-danger" onclick="anular(${r.id})" title="Anular"><i class="fas fa-ban"></i></button>`:''}
          ${(CAN_DESANULAR&&r.estado==='anulada')?`<button class="btn btn-sm btn-outline-success" onclick="desanular(${r.id})" title="Desanular"><i class="fas fa-rotate-left"></i></button>`:''}${(CAN_ELIMINAR&&r.estado==='anulada')?`
          <button class="btn btn-sm btn-outline-danger" onclick="eliminar(${r.id})" title="Eliminar"><i class="fas fa-trash"></i></button>`:''}
        </td>
      </tr>`;
    }).join('')||'<tr><td colspan="10" class="text-center text-muted">Sin boletas</td></tr>';
    renderSortIcons();renderPagination(total);
  }catch(e){showAlert(e.message);}
}
function renderPagination(t){
  const N=Math.ceil(t/state.size);if(N<=1){document.getElementById('paginacion').innerHTML='';return;}
  let C=state.page,h=[];
  if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="go(${C-1})">«</a></li>`);
  for(let i=0;i<N;i++)h.push(`<li class="page-item ${i===C?'active':''}"><a class="page-link" href="#" onclick="go(${i})">${i+1}</a></li>`);
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="go(${C+1})">»</a></li>`);
  document.getElementById('paginacion').innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}
function go(p){state.page=p;cargar();}

function preciosProducto(){
  const sel=document.getElementById('n_producto'), opt=sel.options[sel.selectedIndex];
  return { c: opt ? (opt.dataset.c||'') : '', v: opt ? (opt.dataset.v||'') : '' };
}
function abrirNueva(){
  document.getElementById('n_producto').value=state.prod>0?state.prod:'';
  document.getElementById('n_serial').value='';
  document.getElementById('n_factura').value='';
  document.getElementById('n_venc').value='';
  const p=preciosProducto();
  document.getElementById('n_compra').value=p.c;
  document.getElementById('n_venta').value=p.v;
  document.getElementById('n_archivo').value='';
  modalNueva.show();
}
// Al cambiar el producto en el modal se precargan sus precios (si el campo está vacío).
document.getElementById('n_producto').addEventListener('change',()=>{
  const p=preciosProducto();
  if(document.getElementById('n_compra').value==='')document.getElementById('n_compra').value=p.c;
  if(document.getElementById('n_venta').value==='')document.getElementById('n_venta').value=p.v;
});

async function cargarProductosRef(){
  try{
    const d=await api('boletas/resumen?producto_id=0');
    const ops=(d.productos||[]).map(p=>`<tr><td>${p.id}</td><td>${esc(p.nombre)}</td><td>${esc(p.convenio_nombre)}</td></tr>`).join('');
    document.getElementById('cargaProductos').innerHTML=ops||'<tr><td colspan="3" class="text-muted">Sin productos de stock</td></tr>';
  }catch(e){/* el listado de referencia no es crítico */}
}
function abrirCarga(){
  document.getElementById('cargaArchivo').value='';
  document.getElementById('cargaPlantilla').setAttribute('href',API+'boletas/carga/plantilla');
  setCargaMsg('',true);
  modalCarga.show();
  cargarProductosRef();
}
function setCargaMsg(msg,ok){
  const el=document.getElementById('cargaAlert');
  if(!msg){el.className='d-none';el.textContent='';return;}
  el.className='alert alert-'+(ok?'success':'danger');
  el.textContent=msg;
}
function analizarCarga(){
  const f=document.getElementById('cargaArchivo').files[0];
  setCargaMsg('',true);
  if(!f){setCargaMsg('Seleccione un archivo .xls/.xlsx.',false);return;}
  const btn=document.getElementById('btnAnalizar');
  btn.disabled=true; const oldBtn=btn.innerHTML; btn.innerHTML='<i class="fas fa-spinner fa-spin me-1"></i>Analizando…';
  const fd=new FormData(); fd.append('archivo',f);
  api('boletas/carga/analizar',{method:'POST',body:fd})
    .then(res=>{
      setCargaMsg(res.errores>0
        ? `El archivo tiene ${res.errores} fila(s) con error. Se abrirá la vista de validación para corregirlas.`
        : `${res.total} fila(s) válida(s). Se abre la vista de validación.`, res.errores===0);
      setTimeout(()=>{location.href=U+'modules/convenios/pages/carga_boletas_validacion';},900);
    })
    .catch(e=>{setCargaMsg(e.message,false);btn.disabled=false;btn.innerHTML=oldBtn;});
}
async function guardarNueva(){
  const pid=document.getElementById('n_producto').value;
  if(!pid){alert('Seleccione el producto.');return;}
  const fd=new FormData();
  fd.append('producto_id',pid);
  fd.append('serial',document.getElementById('n_serial').value.trim());
  fd.append('numero_factura',document.getElementById('n_factura').value.trim());
  fd.append('fecha_vencimiento',document.getElementById('n_venc').value);
  fd.append('precio_compra',document.getElementById('n_compra').value);
  fd.append('precio_venta',document.getElementById('n_venta').value); // vacío → el service usa el precio del producto
  const ar=document.getElementById('n_archivo').files[0];
  if(ar)fd.append('archivo',ar);
  try{await api('boletas/guardar',{method:'POST',body:fd});showAlert('Boleta creada',true);modalNueva.hide();cargar();cargarKpis();}catch(e){showAlert(e.message);}
}

function abrirVenta(id){
  const b = ROW[id] || {};
  document.getElementById('v_id').value = id;
  document.getElementById('vSerial').textContent = '• ' + esc(b.serial || '') + ' (' + money(b.precio || 0) + ')';
  document.getElementById('v_cedula_input').value = '';
  document.getElementById('v_cedula').value = '';
  document.getElementById('v_metodo').value = 'credito';
  document.getElementById('fin_none').checked = true;
  document.getElementById('wrapSif').classList.add('d-none');
  document.getElementById('v_sifone').value = '';
  modalVender.show();
}
setupAutocomplete('v_cedula_input','v_cedula','v_res');
document.querySelectorAll('input[name="v_fin"]').forEach(r=>r.addEventListener('change',()=>{
  document.getElementById('wrapSif').classList.toggle('d-none',document.querySelector('input[name="v_fin"]:checked').value!=='sifone');
}));
async function guardarVenta(){
  const id=document.getElementById('v_id').value;
  const cedula=document.getElementById('v_cedula').value.trim();
  if(!cedula){alert('Seleccione el comprador.');return;}
  const metodo=document.getElementById('v_metodo').value;
  const fin=document.querySelector('input[name="v_fin"]:checked').value;
  const cuerpo={id,cedula,metodo_venta:metodo};
  if(fin==='sifone'){
    const numero=document.getElementById('v_sifone').value.trim();
    if(!numero){alert('Indique el nº de movimiento Sifone.');return;}
    cuerpo.financiero={tipo:'sifone',numero};
  }else if(fin==='transaccion'){
    cuerpo.financiero={tipo:'transaccion'};
  }
  try{await post('boletas/vender',cuerpo);showAlert('Boleta vendida',true);modalVender.hide();cargar();cargarKpis();}catch(e){showAlert(e.message);}
}

function contabilizar(id){
  document.getElementById('c_id').value=id;
  document.getElementById('c_comprobante').value='';
  modalCont.show();
}
async function guardarCont(){
  const id=document.getElementById('c_id').value, comp=document.getElementById('c_comprobante').value.trim();
  if(!comp){alert('Comprobante requerido.');return;}
  try{await post('boletas/contabilizar',{id,comprobante:comp});showAlert('Boleta contabilizada',true);modalCont.hide();cargar();cargarKpis();}catch(e){showAlert(e.message);}
}
async function anular(id){if(!confirm('¿Anular esta boleta? Se limpiará el vínculo comercial/financiero.'))return;
  try{await post('boletas/anular',{id});showAlert('Boleta anulada',true);cargar();cargarKpis();}catch(e){showAlert(e.message);}}
async function desanular(id){if(!confirm('¿Desanular esta boleta?'))return;
  try{await post('boletas/desanular',{id});showAlert('Boleta desanulada',true);cargar();cargarKpis();}catch(e){showAlert(e.message);}}
async function deshacerVenta(id){if(!confirm('¿Deshacer la venta de esta boleta?'))return;
  try{await post('boletas/deshacer_venta',{id});showAlert('Venta deshecha',true);cargar();cargarKpis();}catch(e){showAlert(e.message);}}
async function eliminar(id){if(!confirm('¿Eliminar definitivamente esta boleta ANULADA?'))return;
  try{await post('boletas/eliminar',{id});showAlert('Boleta eliminada',true);cargar();cargarKpis();}catch(e){showAlert(e.message);}}

async function verTimeline(id){
  try{
    const ev=await api('boletas/eventos?boleta_id='+id);
    document.getElementById('timeline').innerHTML='<div class="timeline">'+(ev.map(e=>`
      <div class="timeline-item">
        <div class="timeline-marker"><i class="fas fa-circle"></i></div>
        <div class="timeline-content">
          <strong>${esc(e.accion)}</strong>
          <div class="small text-muted">${esc(e.usuario_nombre||'Sistema')} • ${fmtDt(e.fecha)}</div>
          ${e.estado_anterior||e.estado_nuevo?`<div class="small">${esc(e.estado_anterior||'')} → ${esc(e.estado_nuevo||'')}</div>`:''}
          ${e.detalle?`<div class="small">${esc(e.detalle)}</div>`:''}
        </div>
      </div>`).join('')||'<div class="text-muted">Sin eventos</div>')+'</div>';
    modalTimeline.show();
  }catch(e){showAlert(e.message);}
}

function abrirArchivo(id){
  document.getElementById('a_id').value=id;
  document.getElementById('a_pass').value='';
  modalArchivo.show();
}
async function verArchivo(action){
  const id=document.getElementById('a_id').value, pass=document.getElementById('a_pass').value;
  if(!pass){alert('Ingrese su contraseña.');return;}
  try{
    const r=await fetch(API+'boletas/serve_archivo',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({boleta_id:id,password:pass,action})});
    if(!r.ok){const j=await r.json();throw new Error(j.error||'Error');}
    const blob=await r.blob();
    const url=URL.createObjectURL(blob);
    if(action==='download'){const a=document.createElement('a');a.href=url;a.download='boleta_'+id;document.body.appendChild(a);a.click();a.remove();}
    else window.open(url,'_blank');
    modalArchivo.hide();
  }catch(e){showAlert(e.message);}
}

function exportar(accion){
  const a=document.createElement('a');
  a.href=API+'boletas/descargar_reporte?accion='+accion;
  a.download='';document.body.appendChild(a);a.click();a.remove();
}

function setupAutocomplete(inputId,hiddenId,resId){
  const input=document.getElementById(inputId),hidden=document.getElementById(hiddenId),cont=document.getElementById(resId);
  let items=[],selIdx=-1;
  function pick(btn){
    hidden.value=btn.dataset.ce;input.value=btn.dataset.nm+' ('+btn.dataset.ce+')';
    cont.innerHTML='';selIdx=-1;
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
        selIdx=0;render();
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

document.addEventListener('DOMContentLoaded',()=>{
  modalNueva=new bootstrap.Modal('#modalNueva');modalVender=new bootstrap.Modal('#modalVender');
  modalCont=new bootstrap.Modal('#modalCont');modalArchivo=new bootstrap.Modal('#modalArchivo');
  modalTimeline=new bootstrap.Modal('#modalTimeline');modalCarga=new bootstrap.Modal('#modalCarga');
  document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
  document.getElementById('fCedula').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.cedula=document.getElementById('fCedula').value.trim();state.page=0;cargar();},300);});
  document.getElementById('fvDesde').addEventListener('change',function(){state.fvDesde=this.value;state.page=0;cargar();});
  document.getElementById('fvHasta').addEventListener('change',function(){state.fvHasta=this.value;state.page=0;cargar();});
  document.querySelectorAll('#fEstados input').forEach(ch=>ch.addEventListener('change',()=>{
    state.estados=['disponible','vendida','contabilizada','anulada'].filter(e=>document.getElementById('est_'+e).checked);
    state.page=0;cargar();
  }));
  document.getElementById('fProducto').addEventListener('change',function(){state.prod=parseInt(this.value||'0');state.page=0;cargar();cargarKpis();});
  cargarProductos().then(()=>{cargarKpis();window.Listado.mountSize('fSizeBox', state, cargar, {class:'small text-muted'});cargar();});
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>