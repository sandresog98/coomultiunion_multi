<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('creditos');
$acc = ui_acciones('creditos.config_lineas', $staff['rol'] ?? '');
$canCrear  = ui_accion('creditos.config_lineas', 'crear',  $staff['rol'] ?? '');
$canEditar = ui_accion('creditos.config_lineas', 'editar', $staff['rol'] ?? '');
$canToggle = ui_accion('creditos.config_lineas', 'toggle', $staff['rol'] ?? '');
$pageTitle = 'Créditos · Líneas';
$currentPage = 'creditos_config_lineas';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list-ol me-2"></i>Líneas de crédito</span>
    <span class="small text-white-50" id="infoTotal"></span>
  </div>
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="text-muted small" id="infoLineas"></div>
      <?php if ($canCrear): ?>
      <button class="btn btn-brand btn-sm" onclick="nuevaLinea()"><i class="fas fa-plus"></i> Nueva línea</button>
      <?php endif; ?>
    </div>
    <div id="sinMapear" class="alert alert-warning d-none py-2 mb-2 small"></div>
    <div class="table-responsive"><table class="table table-sm table-hover align-middle">
      <thead><tr><th>#</th><th>Nombre</th><th>Alias Sifone</th><th class="text-end">Tasa</th><th class="text-end">Seg.Vida</th><th class="text-end">Seg.Deud</th><th class="text-center">Meses</th><th class="text-center">Fábrica</th><th class="text-center">Activo</th><th class="text-end">Acciones</th></tr></thead>
      <tbody id="tbodyLineas"><tr><td colspan="10" class="text-center text-muted">Cargando…</td></tr></tbody></table></div>
  </div>
</div>

<!-- MODAL LÍNEA -->
<div class="modal fade" id="mLinea" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mtLinea">Nueva línea</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label><input class="form-control" id="l_nombre"></div>
    <div class="mb-2"><label class="form-label">Alias Sifone <small class="text-muted">(nombres de línea tal como llegan en Sifone, separados por coma)</small></label><input class="form-control" id="l_alias" placeholder="ej. CONSUMO 1.7 ESPECIAL 2025, consumo"></div>
    <div class="row g-2">
      <div class="col-6"><label class="form-label">Tasa (%)</label><input class="form-control" type="number" step="0.0001" id="l_tasa"></div>
      <div class="col-6"><label class="form-label">Límite (meses)</label><input class="form-control" type="number" min="0" id="l_meses"></div>
      <div class="col-6"><label class="form-label">Seguro vida (%)</label><input class="form-control" type="number" step="0.0001" id="l_sv"></div>
      <div class="col-6"><label class="form-label">Seguro deudores (%)</label><input class="form-control" type="number" step="0.0001" id="l_sd"></div>
    </div>
    <div class="d-flex gap-4 mt-2">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="l_fabrica" checked><label class="form-check-label" for="l_fabrica">Mostrar en fábrica</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="l_activo" checked><label class="form-check-label" for="l_activo">Activa</label></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarLinea()"><i class="fas fa-save me-1"></i>Guardar</button></div>
</div></div></div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_CREAR=ACC['creditos.config_lineas.crear']===true,CAN_EDIT=ACC['creditos.config_lineas.editar']===true,CAN_TOGGLE=ACC['creditos.config_lineas.toggle']===true;
let editLineaId=null, modalLinea;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function api(p,o){o=o||{};o.base=(typeof API!=="undefined"?API:(window.API||""));return window.Listado.api(p,o);}
async function cargarLineas(){
  try{
    const l=await api('creditos/lineas');
    document.getElementById('infoTotal').textContent=`${l.length} líneas de crédito`;
    document.getElementById('infoLineas').textContent=`Fuente: migración control_tasas_creditos (ids preservados). ${l.length} líneas.`;
    document.getElementById('tbodyLineas').innerHTML=l.map(x=>`<tr>
      <td class="text-muted">${x.id}</td>
      <td><span class="fw-semibold">${esc(x.nombre_credito)}</span>${x.estado_activo?'':' <span class="badge text-bg-secondary">inactiva</span>'}</td>
      <td class="small text-muted">${esc(x.sifone_alias||'—')}</td>
      <td class="text-end">${x.tasa}</td><td class="text-end">${x.seguro_vida}</td><td class="text-end">${x.seguro_deudores}</td>
      <td class="text-center">${x.limite_meses||'—'}</td><td class="text-center">${x.mostrar_en_fabrica?'Sí':'No'}</td>
      <td class="text-center">${x.estado_activo?'<span class="badge text-bg-success">Activa</span>':'<span class="badge text-bg-secondary">Inactiva</span>'}</td>
<td class="text-end">${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary me-1" onclick='abrirLinea(${JSON.stringify(x)})'><i class="fas fa-edit"></i></button>`:''}${CAN_TOGGLE?`<button class="btn btn-sm ${x.estado_activo?'btn-outline-danger':'btn-outline-success'}" onclick='toggleLinea(${JSON.stringify(x)})'>${x.estado_activo?'<i class="fas fa-ban"></i>':'<i class="fas fa-check"></i>'}</button>`:''}</td></tr>`).join('')||'<tr><td colspan="10" class="text-center text-muted">Sin líneas</td></tr>';
  }catch(e){alert(e.message);}
}
function nuevaLinea(){editLineaId=null;document.getElementById('mtLinea').textContent='Nueva línea';['l_nombre','l_alias','l_tasa','l_meses','l_sv','l_sd'].forEach(i=>document.getElementById(i).value='');document.getElementById('l_fabrica').checked=true;document.getElementById('l_activo').checked=true;modalLinea.show();}
function abrirLinea(x){editLineaId=x.id;document.getElementById('mtLinea').textContent='Editar línea';
  document.getElementById('l_nombre').value=x.nombre_credito||'';document.getElementById('l_alias').value=x.sifone_alias||'';document.getElementById('l_tasa').value=x.tasa||'';
  document.getElementById('l_meses').value=x.limite_meses||'';document.getElementById('l_sv').value=x.seguro_vida||'';
  document.getElementById('l_sd').value=x.seguro_deudores||'';document.getElementById('l_fabrica').checked=x.mostrar_en_fabrica==1;
  document.getElementById('l_activo').checked=x.estado_activo==1;modalLinea.show();}
async function guardarLinea(){
  const g=$=i=>document.getElementById('l_'+i);
  const d={nombre_credito:$('nombre').value.trim(),sifone_alias:$('alias').value.trim(),tasa:parseFloat($('tasa').value)||0,seguro_vida:parseFloat($('sv').value)||0,seguro_deudores:parseFloat($('sd').value)||0,limite_meses:parseInt($('meses').value)||0,mostrar_en_fabrica:$('fabrica').checked?1:0,estado_activo:$('activo').checked?1:0};
  if(!d.nombre_credito){alert('El nombre es obligatorio.');return;}
  try{
    if(editLineaId)await api('creditos/lineas',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:editLineaId,...d})});
    else await api('creditos/lineas',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});
    modalLinea.hide();cargarLineas();
  }catch(e){alert(e.message);}
}
async function toggleLinea(x){const act=x.estado_activo==1?0:1;if(!confirm(`¿${act?'Activar':'Inactivar'} la línea "${x.nombre_credito}"?`))return;
  try{await api('creditos/lineas',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:x.id,estado_activo:act})});cargarLineas();}catch(e){alert(e.message);}}

async function cargarSinMapear(){
  try{
    const u=await api('creditos/lineas?sin_mapear=1');
    const el=document.getElementById('sinMapear');
    if(!u.length){el.classList.add('d-none');el.innerHTML='';return;}
    const total=u.reduce((s,x)=>s+Number(x.creditos||0),0);
    el.innerHTML='<strong><i class="fas fa-triangle-exclamation me-1"></i>'+u.length+' línea(s) de Sifone sin mapear — '+total+' créditos sin línea:</strong><div class="mt-1">'
      +u.map(x=>'<span class="badge text-bg-light border me-1 mb-1">'+esc(x.tipo_prestamo)+' · '+x.creditos+'</span>').join('')+'</div>';
    el.classList.remove('d-none');
  }catch(e){}
}
document.addEventListener('DOMContentLoaded',()=>{modalLinea=new bootstrap.Modal('#mLinea');cargarLineas();cargarSinMapear();});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>