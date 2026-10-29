<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('convenios.admin');
$acc = ui_acciones('convenios.index', $staff['rol'] ?? '');
$canCrear  = ui_accion('convenios.index', 'crear',    $staff['rol'] ?? '');
$canEdit   = ui_accion('convenios.index', 'editar',   $staff['rol'] ?? '');
$canToggle = ui_accion('convenios.index', 'toggle',   $staff['rol'] ?? '');
$canElim   = ui_accion('convenios.index', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Convenios y productos';
$currentPage = 'convenios';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-handshake me-2"></i>Convenios y productos</span>
    <span>
      <button class="btn btn-light btn-sm me-1 d-none" id="btnNuevoProducto" onclick="abrirProducto()"><i class="fas fa-plus me-1"></i>Nuevo producto</button>
      <?php if ($canCrear): ?>
      <button class="btn btn-light btn-sm" onclick="abrirConvenio()"><i class="fas fa-plus me-1"></i>Nuevo convenio</button>
      <?php endif; ?>
    </span>
  </div>
  <div class="card-body">
    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item"><button class="nav-link active" id="tabConv" data-bs-toggle="tab" data-bs-target="#paneConv" type="button">Convenios</button></li>
      <li class="nav-item"><button class="nav-link" id="tabProd" data-bs-toggle="tab" data-bs-target="#paneProd" type="button">Productos</button></li>
    </ul>

    <div id="alert" class="alert d-none"></div>

    <div class="tab-content">
      <!-- Pestaña Convenios -->
      <div class="tab-pane fade show active" id="paneConv">
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-auto">
            <label class="form-label small text-muted mb-0">Buscar</label>
            <input class="form-control form-control-sm" id="qC" placeholder="Nombre, descripción, correo">
          </div>
          <div class="col-auto">
            <label class="form-label small text-muted mb-0">Estado</label>
            <select class="form-select form-select-sm" id="fEstC" data-searchable>
              <option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option>
            </select>
          </div>
        </div>
        <div class="mb-2 d-flex align-items-center gap-2"><span class="badge text-bg-info" id="infoTotalC">Mostrando 0-0 de 0</span><span id="fSizeC"></span></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead><tr>
              <th style="width:64px">Imagen</th>
              <th class="sortable" data-col="nombre">Convenio</th>
              <th>Productos</th>
              <th>Estado</th>
              <th>Actualizado</th>
              <th class="text-end">Acciones</th>
            </tr></thead>
            <tbody id="tbodyC"><tr><td colspan="6" class="text-center text-muted">Cargando…</td></tr></tbody>
          </table>
        </div>
        <nav id="paginacionC"></nav>
      </div>

      <!-- Pestaña Productos -->
      <div class="tab-pane fade" id="paneProd">
        <div class="row g-2 mb-3 align-items-end">
          <div class="col-auto">
            <label class="form-label small text-muted mb-0">Convenio</label>
            <select class="form-select form-select-sm" id="fConvP" data-searchable><option value="0">Todos</option></select>
          </div>
          <div class="col-auto">
            <label class="form-label small text-muted mb-0">Tipo</label>
            <select class="form-select form-select-sm" id="fTipoP" data-searchable>
              <option value="">Todos</option><option value="beneficio">Beneficio</option><option value="boleta">Boleta (con stock)</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label small text-muted mb-0">Buscar</label>
            <input class="form-control form-control-sm" id="qP" placeholder="Nombre, convenio">
          </div>
        </div>
        <div class="mb-2 d-flex align-items-center gap-2"><span class="badge text-bg-info" id="infoTotalP">Mostrando 0-0 de 0</span><span id="fSizeP"></span></div>
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead><tr>
              <th class="sortable" data-col="nombre">Producto</th>
              <th>Convenio</th>
              <th class="sortable" data-col="valor">Valor</th>
              <th class="sortable" data-col="precio_venta">Pto. venta</th>
              <th>Tipo</th>
              <th>Stock</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr></thead>
            <tbody id="tbodyP"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
          </table>
        </div>
        <nav id="paginacionP"></nav>
      </div>
    </div>
  </div>
</div>

<!-- Modal convenio -->
<div class="modal fade" id="modalConv" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="tConv">Nuevo convenio</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="fc_id">
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Nombre *</label><input class="form-control" id="fc_nombre"></div>
        <div class="col-md-4"><label class="form-label">Teléfono</label><input class="form-control" id="fc_telefono"></div>
        <div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" id="fc_correo"></div>
        <div class="col-md-6"><label class="form-label">Link</label><input class="form-control" id="fc_link"></div>
        <div class="col-md-6"><label class="form-label">Texto anexo</label><input class="form-control" id="fc_anexo"></div>
        <div class="col-md-6"><label class="form-label">Imagen</label><input class="form-control" type="file" id="fc_imagen" accept="image/*"></div>
        <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" id="fc_descripcion" rows="3"></textarea></div>
        <div class="col-12 form-check"><input class="form-check-input" type="checkbox" id="fc_activo" checked><label class="form-check-label" for="fc_activo">Activo</label></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-brand" onclick="guardarConvenio()">Guardar</button>
    </div>
  </div></div>
</div>

<!-- Modal producto -->
<div class="modal fade" id="modalProd" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="tProd">Nuevo producto</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="fp_id">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Convenio *</label><select class="form-select" id="fp_convenio"></select></div>
        <div class="col-md-6"><label class="form-label">Nombre *</label><input class="form-control" id="fp_nombre"></div>
        <div class="col-12"><label class="form-label">Descripción</label><textarea class="form-control" id="fp_descripcion" rows="2"></textarea></div>
        <div class="col-md-6"><label class="form-label">Valor</label><input class="form-control" id="fp_valor" type="number" step="1" min="0"></div>
        <div class="col-md-6"><label class="form-label">Valor general</label><input class="form-control" id="fp_valor_general" type="number" step="1" min="0"></div>
        <div class="col-12">
          <label class="form-label">Tipo</label>
          <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="fp_tipo" id="radBenef" value="0" checked><label class="btn btn-outline-primary" for="radBenef"><i class="fas fa-tag me-1"></i>Beneficio (información)</label>
            <input type="radio" class="btn-check" name="fp_tipo" id="radBoleta" value="1"><label class="btn btn-outline-primary" for="radBoleta"><i class="fas fa-ticket-alt me-1"></i>Boleta con stock</label>
          </div>
        </div>
        <div class="col-md-6 d-none" id="wrapPCompra"><label class="form-label">Precio de compra</label><input class="form-control" id="fp_precio_compra" type="number" step="1" min="0"></div>
        <div class="col-md-6 d-none" id="wrapPVenta"><label class="form-label">Precio de venta</label><input class="form-control" id="fp_precio_venta" type="number" step="1" min="0"></div>
        <div class="col-md-6"><label class="form-label">Imagen</label><input class="form-control" type="file" id="fp_imagen" accept="image/*"></div>
        <div class="col-12 form-check"><input class="form-check-input" type="checkbox" id="fp_activo" checked><label class="form-check-label" for="fp_activo">Activo</label></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-brand" onclick="guardarProducto()">Guardar</button>
    </div>
  </div></div>
</div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const ACC = <?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['convenios.index.editar']===true,CAN_TOGGLE=ACC['convenios.index.toggle']===true,CAN_ELIM=ACC['convenios.index.eliminar']===true;
const alertBox = document.getElementById('alert');
let modalConv, modalProd, debounceC, debounceP;
let stC = { q:'', est:'', page:0, size:20, order:'nombre', dir:'ASC' };
let stP = { conv:0, tipo:'', q:'', page:0, size:20, order:'nombre', dir:'ASC' };

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return'$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
function showAlert(msg,ok=false){alertBox.className='alert alert-'+(ok?'success':'danger');alertBox.textContent=msg;alertBox.classList.remove('d-none');}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
function post(path,data){return api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}

const SORTCOLS={'paneConv':stC,'paneProd':stP};
document.querySelectorAll('.sortable').forEach(th=>th.addEventListener('click',()=>{
  const tp=th.closest('.tab-pane');if(!tp)return;
  const pane=tp.id,st=SORTCOLS[pane];if(!st)return;
  if(st.order===th.dataset.col)st.dir=st.dir==='ASC'?'DESC':'ASC';else{st.order=th.dataset.col;st.dir='ASC';}
  st.page=0;if(pane==='paneConv')cargarConvenios();else cargarProductos();
}));
function renderSortIcons(){document.querySelectorAll('.sortable').forEach(th=>{
  const tp=th.closest('.tab-pane');if(!tp)return;
  const st=SORTCOLS[tp.id];if(!st)return;
  const col=th.dataset.col,a=st.order===col;th.classList.toggle('active',a);
  const txt=th.textContent.trim().replace(/[▲▼]/g,'');
  th.innerHTML=txt+(a?` <span class="sort-icon">${st.dir==='ASC'?'▲':'▼'}</span>`:'');
});}

async function cargarConvenios(){
  try{
    const off=stC.page*stC.size;
    const d=await api(`convenios/listar?ent=convenios&q=${encodeURIComponent(stC.q)}&estado=${stC.est}&order=${stC.order}&dir=${stC.dir}&limit=${stC.size}&offset=${off}`);
    const total=d.total||0;
    document.getElementById('infoTotalC').textContent=total?`Mostrando ${off+1}-${Math.min(off+stC.size,total)} de ${total}`:'';
    document.getElementById('tbodyC').innerHTML=(d.rows||[]).map(c=>`
      <tr>
        <td>${c.imagen?`<img src="${API}convenios/serve_imagen?ent=convenio&id=${c.id}" style="width:56px;height:40px;object-fit:cover;border-radius:6px" onerror="this.style.display='none'">`:''}</td>
        <td><strong>${esc(c.nombre)}</strong><div class="small text-muted">${esc(c.correo||c.telefono||'')}</div></td>
        <td><span class="badge text-bg-secondary">${c.total_productos} productos</span></td>
        <td>${c.activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-danger">Inactivo</span>'}</td>
        <td class="small">${c.fecha_actualizacion||c.fecha_creacion||''}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary" onclick='abrirConvenio(${JSON.stringify(c)})'><i class="fas fa-pen"></i></button>
          ${CAN_TOGGLE?`<button class="btn btn-sm btn-outline-warning" onclick="toggleConv(${c.id})"><i class="fas fa-power-off"></i></button>`:''}
          ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminarConv(${c.id})"><i class="fas fa-trash"></i></button>`:''}
        </td>
      </tr>`).join('')||'<tr><td colspan="6" class="text-center text-muted">Sin convenios</td></tr>';
    renderSortIcons();renderPagination('paginacionC',total,stC);
  }catch(e){showAlert(e.message);}
}
async function cargarProductos(){
  try{
    const off=stP.page*stP.size;
    const d=await api(`convenios/listar?ent=productos&convenio_id=${stP.conv}&tipo=${stP.tipo}&q=${encodeURIComponent(stP.q)}&order=${stP.order}&dir=${stP.dir}&limit=${stP.size}&offset=${off}`);
    const total=d.total||0;
    document.getElementById('infoTotalP').textContent=total?`Mostrando ${off+1}-${Math.min(off+stP.size,total)} de ${total}`:'';
    document.getElementById('tbodyP').innerHTML=(d.rows||[]).map(p=>`
      <tr>
        <td><strong>${esc(p.nombre)}</strong></td>
        <td>${esc(p.convenio_nombre)}</td>
        <td class="text-end">${money(p.valor)}</td>
        <td class="text-end">${p.maneja_stock==1?money(p.precio_venta):'—'}</td>
        <td>${p.maneja_stock==1?'<span class="badge text-bg-warning"><i class="fas fa-ticket-alt me-1"></i>Boleta</span>':'<span class="badge text-bg-info">Beneficio</span>'}</td>
        <td>${p.maneja_stock==1?`<span class="badge text-bg-secondary">${p.total_boletas} boletas</span>`:'—'}</td>
        <td>${p.activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-danger">Inactivo</span>'}</td>
        <td class="text-end">
          ${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary" onclick='abrirProducto(${JSON.stringify(p)})'><i class="fas fa-pen"></i></button>`:''}
          ${CAN_TOGGLE?`<button class="btn btn-sm btn-outline-warning" onclick="toggleProd(${p.id})"><i class="fas fa-power-off"></i></button>`:''}
          ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminarProd(${p.id})"><i class="fas fa-trash"></i></button>`:''}
        </td>
      </tr>`).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin productos</td></tr>';
    renderSortIcons();renderPagination('paginacionP',total,stP);
  }catch(e){showAlert(e.message);}
}
function renderPagination(id,total,state){
  const N=Math.ceil(total/state.size),el=document.getElementById(id);
  if(N<=1){el.innerHTML='';return;}
  let C=state.page,h=[];
  if(C>0)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="go('${id}',${C-1})">«</a></li>`);
  for(let i=0;i<N;i++)h.push(`<li class="page-item ${i===C?'active':''}"><a class="page-link" href="#" onclick="go('${id}',${i})">${i+1}</a></li>`);
  if(C<N-1)h.push(`<li class="page-item"><a class="page-link" href="#" onclick="go('${id}',${C+1})">»</a></li>`);
  el.innerHTML=`<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}
function go(id,p){if(id==='paginacionC')stC.page=p;else stP.page=p;if(id==='paginacionC')cargarConvenios();else cargarProductos();}

async function cargarConveniosSel(){
  const d=await api('convenios/listar?ent=convenios&limit=200');
  const opts=(d.rows||[]).map(c=>`<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
  document.getElementById('fp_convenio').innerHTML='<option value="">Seleccione…</option>'+opts;
  document.getElementById('fConvP').innerHTML='<option value="0">Todos</option>'+opts;
}

function abrirConvenio(c){
  document.getElementById('tConv').textContent=c?'Editar convenio':'Nuevo convenio';
  document.getElementById('fc_id').value=c?c.id:'';
  document.getElementById('fc_nombre').value=c?c.nombre:'';
  document.getElementById('fc_telefono').value=c?c.telefono||'':'';
  document.getElementById('fc_correo').value=c?c.correo||'':'';
  document.getElementById('fc_link').value=c?c.link||'':'';
  document.getElementById('fc_anexo').value=c?c.anexo_nombre||'':'';
  document.getElementById('fc_descripcion').value=c?c.descripcion||'':'';
  document.getElementById('fc_imagen').value='';
  document.getElementById('fc_activo').checked=c?c.activo==1:true;
  modalConv.show();
}
async function guardarConvenio(){
  const fd=new FormData();
  fd.append('ent','convenio');
  fd.append('id',document.getElementById('fc_id').value);
  fd.append('nombre',document.getElementById('fc_nombre').value.trim());
  fd.append('telefono',document.getElementById('fc_telefono').value.trim());
  fd.append('correo',document.getElementById('fc_correo').value.trim());
  fd.append('link',document.getElementById('fc_link').value.trim());
  fd.append('anexo_nombre',document.getElementById('fc_anexo').value.trim());
  fd.append('descripcion',document.getElementById('fc_descripcion').value.trim());
  fd.append('activo',document.getElementById('fc_activo').checked?'1':'0');
  const img=document.getElementById('fc_imagen').files[0];
  if(img)fd.append('imagen',img);
  try{await api('convenios/guardar',{method:'POST',body:fd});showAlert('Convenio guardado',true);modalConv.hide();cargarConvenios();cargarConveniosSel();}catch(e){showAlert(e.message);}
}
async function toggleConv(id){try{await post('convenios/toggle',{id,ent:'convenio'});cargarConvenios();}catch(e){showAlert(e.message);}}
async function eliminarConv(id){
  if(!confirm('¿Eliminar este convenio?'))return;
  try{await post('convenios/eliminar',{id,ent:'convenio'});showAlert('Convenio eliminado',true);cargarConvenios();cargarConveniosSel();}catch(e){showAlert(e.message);}
}

function setTipoProducto(t){
  const boleta=t==='1';
  document.getElementById('wrapPCompra').classList.toggle('d-none',!boleta);
  document.getElementById('wrapPVenta').classList.toggle('d-none',!boleta);
}
document.querySelectorAll('input[name="fp_tipo"]').forEach(r=>r.addEventListener('change',()=>setTipoProducto(document.querySelector('input[name="fp_tipo"]:checked').value)));

function abrirProducto(p){
  document.getElementById('tProd').textContent=p?'Editar producto':'Nuevo producto';
  document.getElementById('fp_id').value=p?p.id:'';
  document.getElementById('fp_convenio').value=p?p.convenio_id:'';
  document.getElementById('fp_nombre').value=p?p.nombre:'';
  document.getElementById('fp_descripcion').value=p?p.descripcion||'':'';
  document.getElementById('fp_valor').value=p?p.valor:'';
  document.getElementById('fp_valor_general').value=p&&p.valor_general!=null?p.valor_general:'';
  const esBoleta=p?p.maneja_stock==1:false;
  document.getElementById(esBoleta?'radBoleta':'radBenef').checked=true;
  setTipoProducto(esBoleta?'1':'0');
  document.getElementById('fp_precio_compra').value=p&&p.precio_compra!=null?p.precio_compra:'';
  document.getElementById('fp_precio_venta').value=p&&p.precio_venta!=null?p.precio_venta:'';
  document.getElementById('fp_imagen').value='';
  document.getElementById('fp_activo').checked=p?p.activo==1:true;
  modalProd.show();
}
async function guardarProducto(){
  const fd=new FormData();
  fd.append('ent','producto');
  fd.append('id',document.getElementById('fp_id').value);
  fd.append('convenio_id',document.getElementById('fp_convenio').value);
  fd.append('nombre',document.getElementById('fp_nombre').value.trim());
  fd.append('descripcion',document.getElementById('fp_descripcion').value.trim());
  fd.append('valor',document.getElementById('fp_valor').value||'0');
  fd.append('valor_general',document.getElementById('fp_valor_general').value);
  const boleta=document.querySelector('input[name="fp_tipo"]:checked').value==='1';
  fd.append('maneja_stock',boleta?'1':'0');
  if(boleta){fd.append('precio_compra',document.getElementById('fp_precio_compra').value||'0');fd.append('precio_venta',document.getElementById('fp_precio_venta').value||'0');}
  fd.append('activo',document.getElementById('fp_activo').checked?'1':'0');
  const img=document.getElementById('fp_imagen').files[0];
  if(img)fd.append('imagen',img);
  try{await api('convenios/guardar',{method:'POST',body:fd});showAlert('Producto guardado',true);modalProd.hide();cargarProductos();}catch(e){showAlert(e.message);}
}
async function toggleProd(id){try{await post('convenios/toggle',{id,ent:'producto'});cargarProductos();}catch(e){showAlert(e.message);}}
async function eliminarProd(id){
  if(!confirm('¿Eliminar este producto?'))return;
  try{await post('convenios/eliminar',{id,ent:'producto'});showAlert('Producto eliminado',true);cargarProductos();}catch(e){showAlert(e.message);}
}

document.addEventListener('DOMContentLoaded',()=>{
  modalConv=new bootstrap.Modal('#modalConv');modalProd=new bootstrap.Modal('#modalProd');
  document.getElementById('qC').addEventListener('input',()=>{clearTimeout(debounceC);debounceC=setTimeout(()=>{stC.q=document.getElementById('qC').value.trim();stC.page=0;cargarConvenios();},300);});
  document.getElementById('fEstC').addEventListener('change',function(){stC.est=this.value;stC.page=0;cargarConvenios();});
  document.getElementById('qP').addEventListener('input',()=>{clearTimeout(debounceP);debounceP=setTimeout(()=>{stP.q=document.getElementById('qP').value.trim();stP.page=0;cargarProductos();},300);});
  document.getElementById('fConvP').addEventListener('change',function(){stP.conv=parseInt(this.value||'0');stP.page=0;cargarProductos();});
  document.getElementById('fTipoP').addEventListener('change',function(){stP.tipo=this.value;stP.page=0;cargarProductos();});
  document.getElementById('tabProd').addEventListener('click',()=>document.getElementById('btnNuevoProducto').classList.remove('d-none'));
  document.getElementById('tabConv').addEventListener('click',()=>document.getElementById('btnNuevoProducto').classList.add('d-none'));
  window.Listado.mountSize('fSizeC', stC, cargarConvenios, {id:'fSizeC', class:'small text-muted'});
  window.Listado.mountSize('fSizeP', stP, cargarProductos, {id:'fSizeP', class:'small text-muted'});
  cargarConveniosSel().then(()=>{cargarConvenios();cargarProductos();});
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>