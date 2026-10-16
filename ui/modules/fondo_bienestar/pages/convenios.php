<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('solicitudes.fondo_bienestar');
$rol = $staff['rol'] ?? '';
$acc = ui_acciones('fondo_bienestar.convenios', $rol);
$canCrear  = ui_accion('fondo_bienestar.convenios', 'crear',  $rol);
$canEditar = ui_accion('fondo_bienestar.convenios', 'editar', $rol);
$canToggle = ui_accion('fondo_bienestar.convenios', 'toggle', $rol);
$pageTitle = 'Fondo de Bienestar — Convenios';
$currentPage = 'fondo_bienestar';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-gift me-2"></i>Fondo de Bienestar — Convenios</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
    <?php if ($canCrear): ?>
    <button class="btn btn-light btn-sm" onclick="abrir()"><i class="fas fa-plus me-1"></i>Nuevo convenio</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:280px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Código o nombre…"></div>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="f_estado" data-searchable><option value="">Todos</option><option value="1">Activos</option><option value="0">Inactivos</option></select>
      </div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="codigo">Código</th><th class="sortable" data-col="nombre">Nombre</th>
          <th class="text-center sortable" data-col="meses_requeridos">Meses req.</th>
          <th class="text-center sortable" data-col="bloqueo_meses">Bloqueo</th>
          <th class="text-center">No redime</th><th class="text-center">Cumpleaños</th><th class="text-center">PDF</th>
          <th class="text-center sortable" data-col="orden">Orden</th><th class="text-center sortable" data-col="estado_activo">Estado</th>
          <th class="text-end">Acciones</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="10" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<div class="modal fade" id="m" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mt">Nuevo convenio</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-4 mb-2">
        <label class="form-label">Código <span class="text-danger">*</span></label>
        <input class="form-control" id="f_codigo" placeholder="ej. bono_escolar" autocomplete="off">
        <div class="form-text" id="codigoInfo"></div>
      </div>
      <div class="col-md-8 mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label><input class="form-control" id="f_nombre" placeholder="ej. Bono escolar"></div>
      <div class="col-12 mb-2"><label class="form-label">Descripción</label><textarea class="form-control" id="f_descripcion" rows="2"></textarea></div>
      <div class="col-md-3 mb-2"><label class="form-label">Meses requeridos</label><input class="form-control" id="f_meses_requeridos" type="number" min="0" value="0"></div>
      <div class="col-md-3 mb-2"><label class="form-label">Bloqueo (meses)</label><input class="form-control" id="f_bloqueo_meses" type="number" min="0" placeholder="12"></div>
      <div class="col-md-3 mb-2"><label class="form-label">Meses de espera</label><input class="form-control" id="f_meses_espera_proxima" type="number" min="0" value="0"></div>
      <div class="col-md-2 mb-2"><label class="form-label">Orden</label><input class="form-control" id="f_orden" type="number" value="0"></div>
      <div class="col-md-6 mb-2"><label class="form-label">Opciones (JSON)</label><input class="form-control" id="f_opciones_json" placeholder='["Opción A","Opción B"]' autocomplete="off"></div>
      <div class="col-md-6 mb-2"><label class="form-label">Meses permitidos (JSON o CSV)</label><input class="form-control" id="f_meses_permitidos" placeholder='[1,2,6,7]  o  1,2,6,7' autocomplete="off"></div>
      <div class="col-md-6 mb-2"><label class="form-label">Email entidad</label><input class="form-control" id="f_email_entidad" type="email" placeholder="entidad@dominio.com"></div>
      <div class="col-md-6 mb-2"><label class="form-label">Nombre del PDF obligatorio</label><input class="form-control" id="f_nombre_pdf_obligatorio" placeholder="ej. Lista escolar"></div>
      <div class="col-12">
        <div class="d-flex flex-wrap gap-3">
          <div class="form-check"><input class="form-check-input" type="checkbox" id="f_no_redime"><label class="form-check-label" for="f_no_redime">No redimible (informativo)</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="f_cumpleanos"><label class="form-check-label" for="f_cumpleanos">Requiere cumpleaños (30 días)</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="f_pdf"><label class="form-check-label" for="f_pdf">Requiere PDF obligatorio</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="f_email_entidad_chk"><label class="form-check-label" for="f_email_entidad_chk">Enviar email a la entidad</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="f_estado_activo" checked><label class="form-check-label" for="f_estado_activo">Activo</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="f_agotado"><label class="form-check-label" for="f_agotado">Agotado</label></div>
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
window.API=<?php echo json_encode(apiUrl()); ?>;
const alertBox=document.getElementById('alert'), L=window.Listado;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDITAR=ACC['fondo_bienestar.convenios.editar']===true;
const CAN_TOGGLE=ACC['fondo_bienestar.convenios.toggle']===true;
let state={q:'',estado:'',page:0,size:20,order:'orden',dir:'ASC'}, editId=null, modal;

const esc=L.esc, api=L.api, debounce=L.debounce;
L.bindSort(document, state, cargar);
function renderSortIcons(){L.renderSortIcons(document, state);}
function chk(v){return v==1||v==='1';}
document.getElementById('q').addEventListener('input',debounce(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300));
document.getElementById('f_estado').addEventListener('change',()=>{state.estado=document.getElementById('f_estado').value;state.page=0;cargar();});

async function cargar(){
  const off=state.page*state.size;
  try{
    const d=await api('fondo_bienestar/listar?q='+encodeURIComponent(state.q)+'&estado='+state.estado+'&order='+state.order+'&dir='+state.dir+'&limit='+state.size+'&offset='+off);
    const total=d.total||0;
    document.getElementById('infoTotal').textContent=total>0?`Mostrando ${off+1}-${Math.min(off+state.size,total)} de ${total}`:'';
    renderSortIcons();
    document.getElementById('tbody').innerHTML=(d.rows||[]).map(r=>`<tr>
      <td><code>${esc(r.codigo)}</code></td>
      <td><span class="fw-semibold">${esc(r.nombre)}</span><div class="small text-muted">${esc(r.descripcion||'')}</div></td>
      <td class="text-center"><span class="badge text-bg-light">${r.meses_requeridos}</span></td>
      <td class="text-center">${r.bloqueo_meses!==null&&r.bloqueo_meses!==''?r.bloqueo_meses:'—'}</td>
      <td class="text-center">${chk(r.no_redime)?'<i class="fas fa-check text-warning"></i>':'—'}</td>
      <td class="text-center">${chk(r.requiere_cumpleanos_30_dias)?'<i class="fas fa-cake-candles text-success"></i>':'—'}</td>
      <td class="text-center">${chk(r.requiere_pdf_obligatorio)?'<i class="fas fa-file-pdf text-danger"></i>':'—'}</td>
      <td class="text-center">${r.orden}</td>
      <td class="text-center">${chk(r.estado_activo)?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-secondary">Inactivo</span>'}</td>
      <td class="text-end">
        ${CAN_EDITAR?`<button class="btn btn-sm btn-outline-primary me-1" onclick='abrir(${JSON.stringify(r)})'><i class="fas fa-edit"></i></button>`:''}
        ${CAN_TOGGLE?`<button class="btn btn-sm ${chk(r.estado_activo)?'btn-outline-danger':'btn-outline-success'}" onclick="toggle(${JSON.stringify(r)})"><i class="fas ${chk(r.estado_activo)?'fa-ban':'fa-check'}"></i></button>`:''}
      </td></tr>`).join('')||'<tr><td colspan="10" class="text-center text-muted">Sin registros</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}
function renderPagination(t){document.getElementById('paginacion').innerHTML=L.paginacion(t,state.page,state.size);}
function goPage(p){state.page=p;cargar();}

function abrir(r){
  editId=r?r.id:null;
  document.getElementById('mt').textContent=editId?'Editar convenio':'Nuevo convenio';
  document.getElementById('f_codigo').value=r?(r.codigo||''):'';
  document.getElementById('f_codigo').readOnly=!!editId;   // read-only al editar
  document.getElementById('codigoInfo').textContent=editId?'Código inmutable al editar':'';
  document.getElementById('f_nombre').value=r?(r.nombre||''):'';
  document.getElementById('f_descripcion').value=r?(r.descripcion||''):'';
  document.getElementById('f_meses_requeridos').value=r?(r.meses_requeridos??0):0;
  document.getElementById('f_bloqueo_meses').value=r?(r.bloqueo_meses??''):'';
  document.getElementById('f_meses_espera_proxima').value=r?(r.meses_espera_proxima??0):0;
  document.getElementById('f_orden').value=r?(r.orden??0):0;
  document.getElementById('f_opciones_json').value=r?(r.opciones_json||''):'';
  document.getElementById('f_meses_permitidos').value=r?(r.meses_permitidos||''):'';
  document.getElementById('f_email_entidad').value=r?(r.email_entidad||''):'';
  document.getElementById('f_nombre_pdf_obligatorio').value=r?(r.nombre_pdf_obligatorio||''):'';
  document.getElementById('f_no_redime').checked=r?chk(r.no_redime):false;
  document.getElementById('f_cumpleanos').checked=r?chk(r.requiere_cumpleanos_30_dias):false;
  document.getElementById('f_pdf').checked=r?chk(r.requiere_pdf_obligatorio):false;
  document.getElementById('f_email_entidad_chk').checked=r?chk(r.envia_email_entidad):false;
  document.getElementById('f_estado_activo').checked=r?chk(r.estado_activo):true;
  document.getElementById('f_agotado').checked=r?chk(r.agotado):false;
  modal.show();
}

async function guardar(){
  const data={
    id:editId||0,
    codigo:document.getElementById('f_codigo').value.trim(),
    nombre:document.getElementById('f_nombre').value.trim(),
    descripcion:document.getElementById('f_descripcion').value,
    meses_requeridos:parseInt(document.getElementById('f_meses_requeridos').value||'0',10),
    bloqueo_meses:document.getElementById('f_bloqueo_meses').value===''?null:parseInt(document.getElementById('f_bloqueo_meses').value,10),
    meses_espera_proxima:parseInt(document.getElementById('f_meses_espera_proxima').value||'0',10),
    orden:parseInt(document.getElementById('f_orden').value||'0',10),
    opciones_json:document.getElementById('f_opciones_json').value.trim()||null,
    meses_permitidos:document.getElementById('f_meses_permitidos').value.trim()||null,
    email_entidad:document.getElementById('f_email_entidad').value.trim()||null,
    nombre_pdf_obligatorio:document.getElementById('f_nombre_pdf_obligatorio').value.trim()||null,
    no_redime:document.getElementById('f_no_redime').checked,
    requiere_cumpleanos_30_dias:document.getElementById('f_cumpleanos').checked,
    requiere_pdf_obligatorio:document.getElementById('f_pdf').checked,
    envia_email_entidad:document.getElementById('f_email_entidad_chk').checked,
    estado_activo:document.getElementById('f_estado_activo').checked,
    agotado:document.getElementById('f_agotado').checked,
  };
  if(!data.nombre){alert('El nombre es obligatorio.');return;}
  try{
    await api('fondo_bienestar/guardar',{method:editId?'PUT':'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    modal.hide();cargar();
  }catch(e){alert(e.message);}
}

async function toggle(r){
  const act=r.estado_activo==1?0:1;
  if(!confirm(`¿${act?'Activar':'Inactivar'} el convenio "${r.nombre}"?`))return;
  try{await api('fondo_bienestar/toggle',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:r.id})});cargar();}
  catch(e){alert(e.message);}
}

document.addEventListener('DOMContentLoaded',()=>{modal=new bootstrap.Modal('#m');L.mountSize('fSizeBox', state, cargar);cargar();});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>