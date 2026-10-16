<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('creditos');
$acc = ui_acciones('creditos.config_secciones', $staff['rol'] ?? '');
$canCrear  = ui_accion('creditos.config_secciones', 'crear',    $staff['rol'] ?? '');
$canEditar = ui_accion('creditos.config_secciones', 'editar',   $staff['rol'] ?? '');
$canElim   = ui_accion('creditos.config_secciones', 'eliminar', $staff['rol'] ?? '');
$canItems  = ui_accion('creditos.config_secciones', 'items',    $staff['rol'] ?? '');
$pageTitle = 'Créditos · Secciones';
$currentPage = 'creditos_config_secciones';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
$OCASIONES = [
    'creacion' => 'Creación', 'revision' => 'Revisión', 'estudio' => 'Estudio',
    'consideracion' => 'Consideración', 'estudio_aprobado' => 'Aprobación',
    'radicado_tesoreria' => 'Radicación / Tesorería', 'estudio_rechazado' => 'Rechazo',
    'final' => 'Desembolso', 'aceptacion' => 'Aceptación y Autorizaciones',
];
$TIPOS = ['estudiante', 'empleado_descuento_nomina', 'empleado_sin_descuento', 'independiente', 'pensionado_descuento_libranza', 'pensionado_sin_descuento_libranza'];
$TIPOS_META = [
    'estudiante' => ['a' => 'Est', 'c' => 'primary'],
    'empleado_descuento_nomina' => ['a' => 'Nom', 'c' => 'info'],
    'empleado_sin_descuento' => ['a' => 'Sin', 'c' => 'success'],
    'independiente' => ['a' => 'Ind', 'c' => 'warning'],
    'pensionado_descuento_libranza' => ['a' => 'Lib', 'c' => 'secondary'],
    'pensionado_sin_descuento_libranza' => ['a' => 'Pen', 'c' => 'dark'],
];
$TIPOS_FULL = [
    'estudiante' => 'Estudiante', 'empleado_descuento_nomina' => 'Empleado desc. nómina',
    'empleado_sin_descuento' => 'Empleado sin descuento', 'independiente' => 'Independiente',
    'pensionado_descuento_libranza' => 'Pensionado desc. libranza', 'pensionado_sin_descuento_libranza' => 'Pensionado sin desc. libranza',
];
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-layer-group me-2"></i>Secciones (etapas) del flujo</span>
    <span class="small text-white-50" id="infoSecciones"></span>
  </div>
  <div class="card-body">

    <!-- Vista lista de secciones -->
    <div id="vistaLista">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="text-muted small">Ingresa a una sección para ver y ordenar sus campos y documentos.</div>
        <?php if ($canCrear): ?>
        <button class="btn btn-brand btn-sm" onclick="nuevaSeccion()"><i class="fas fa-plus me-1"></i>Nueva sección</button>
        <?php endif; ?>
      </div>
      <div class="table-responsive"><table class="table table-sm table-hover align-middle">
        <thead><tr><th>Sección</th><th>Aplica a (etapa)</th><th class="text-center">Campos</th><th class="text-center">Docs</th><th class="text-end">Acciones</th></tr></thead>
        <tbody id="tbodySecciones"><tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table></div>
    </div>

    <!-- Vista detalle de una sección (campos + docs combinados) -->
    <div id="vistaDetalle" class="d-none">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <button class="btn btn-sm btn-outline-secondary me-2" onclick="volverLista()"><i class="fas fa-arrow-left me-1"></i>Volver</button>
          <span class="fw-semibold" id="detalleTitulo"></span>
          <span class="text-muted small ms-1" id="detalleSub"></span>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <select class="form-select form-select-sm" id="previewTipo" onchange="cambiarPreview()" title="Vista previa: resalta los ítems que se mostrarían para este tipo de crédito" style="width:auto">
            <option value="">Vista previa por tipo de crédito…</option>
            <?php foreach ($TIPOS as $t): ?><option value="<?= $t ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $t))) ?></option><?php endforeach; ?>
          </select>
          <?php if ($canItems): ?>
          <button class="btn btn-sm btn-outline-primary" onclick="nuevoCampo()"><i class="fas fa-plus me-1"></i>Campo</button>
          <button class="btn btn-sm btn-brand" onclick="nuevoDoc()"><i class="fas fa-plus me-1"></i>Documento</button>
          <?php endif; ?>
        </div>
      </div>
      <div class="text-muted small mb-2">Usa <i class="fas fa-chevron-up"></i>/<i class="fas fa-chevron-down"></i> para ordenar; el orden se guarda al mover.</div>
      <div class="d-flex flex-wrap align-items-center gap-1 mb-2 small" id="leyendaTipos"></div>
      <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th class="text-center" style="width:48px">#</th><th>Tipo</th><th>Nombre</th><th>Requerido</th><th>Visible en</th><th>Tipo de crédito</th><th class="text-center" style="width:150px">Orden</th><th class="text-end" style="width:120px">Acciones</th></tr></thead>
        <tbody id="tbodyItems"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table></div>
    </div>

  </div>
</div>

<!-- MODAL SECCIÓN -->
<div class="modal fade" id="mSeccion" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mtSeccion">Nueva sección</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label><input class="form-control" id="s_nombre" oninput="previewSeccion()"></div>
    <div class="mb-2"><label class="form-label">Aplica a (etapa/ocasión) <span class="text-danger">*</span></label>
      <select class="form-select" id="s_ocasion">
        <?php foreach ($OCASIONES as $oc => $lab): ?><option value="<?= $oc ?>"><?= htmlspecialchars($lab) ?> (<?= $oc ?>)</option><?php endforeach; ?>
      </select>
      <div class="form-text" id="s_codigoPrev"></div>
    </div>
    <div class="row g-2">
      <div class="col-md-4"><label class="form-label">Orden</label><input class="form-control" type="number" id="s_orden" value="100"></div>
      <div class="col-md-8">
        <label class="form-label">Visible en</label>
        <div class="d-flex gap-3">
          <div class="form-check"><input class="form-check-input" type="checkbox" id="s_vis_staff" checked><label class="form-check-label">staff</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" id="s_vis_cx" checked><label class="form-check-label">cx</label></div>
        </div>
      </div>
    </div>
    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="s_activo" checked><label class="form-check-label" for="s_activo">Activa</label></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarSeccion()"><i class="fas fa-save me-1"></i>Guardar</button></div>
</div></div></div>

<!-- MODAL CAMPO -->
<div class="modal fade" id="mCampo" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mtCampo">Nuevo campo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Nombre <span class="text-danger">*</span></label><input class="form-control" id="c_nombre" oninput="previewCampo()">
      <div class="form-text" id="c_camposPrev"></div>
    </div>
    <div class="mb-2"><label class="form-label">Tipo</label>
      <select class="form-select" id="c_tipo" onchange="tipoCampoChange()">
        <option value="texto">Texto</option><option value="textoarea">Texto largo</option><option value="numero">Número</option>
        <option value="select">Select</option><option value="fecha">Fecha</option><option value="checkbox">Checkbox</option>
      </select>
    </div>
    <div class="mb-2 d-none" id="c_opc_wrap"><label class="form-label">Opciones (una por línea)</label><textarea class="form-control" id="c_opciones" rows="3"></textarea></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="c_oblig"><label class="form-check-label" for="c_oblig">Obligatorio</label></div>
    <label class="form-label mt-3 mb-1">Aplica para tipo de solicitante <small class="text-muted">(vacío = ninguno)</small></label>
    <div class="d-flex flex-wrap gap-2" id="c_tipos">
      <?php foreach ($TIPOS as $t): $__m = $TIPOS_META[$t] ?? ['a'=>$t,'c'=>'light']; ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="<?= $t ?>" id="c_t_<?= $t ?>"><label class="form-check-label" for="c_t_<?= $t ?>" title="<?= htmlspecialchars($TIPOS_FULL[$t] ?? $t) ?>"><span class="badge text-bg-<?= $__m['c'] ?>" style="font-size:.75rem"><?= htmlspecialchars($__m['a']) ?></span></label></div><?php endforeach; ?>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarCampo()"><i class="fas fa-save me-1"></i>Guardar</button></div>
</div></div></div>

<!-- MODAL DOCUMENTO (tipo_documento automático) -->
<div class="modal fade" id="mDoc" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mtDoc">Nuevo documento</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Nombre a mostrar <span class="text-danger">*</span></label><input class="form-control" id="d_nombre" oninput="previewDocTipo()">
      <div class="form-text" id="d_tipoPrev"></div>
    </div>
    <div class="mb-2"><label class="form-label">Descripción</label><input class="form-control" id="d_desc"></div>
    <div class="d-flex gap-4 mt-1">
      <div class="form-check"><input class="form-check-input" type="radio" name="d_req" id="d_oblig"><label class="form-check-label">Obligatorio</label></div>
      <div class="form-check"><input class="form-check-input" type="radio" name="d_req" id="d_opc"><label class="form-check-label">Opcional</label></div>
      <div class="form-check"><input class="form-check-input" type="radio" name="d_req" id="d_trato"><label class="form-check-label">Trato especial</label></div>
    </div>
    <label class="form-label mt-3 mb-1">Aplica para tipo de solicitante</label>
    <div class="d-flex flex-wrap gap-2" id="d_tipos">
      <?php foreach ($TIPOS as $t): $__m = $TIPOS_META[$t] ?? ['a'=>$t,'c'=>'light']; ?><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" value="<?= $t ?>" id="d_t_<?= $t ?>"><label class="form-check-label" for="d_t_<?= $t ?>" title="<?= htmlspecialchars($TIPOS_FULL[$t] ?? $t) ?>"><span class="badge text-bg-<?= $__m['c'] ?>" style="font-size:.75rem"><?= htmlspecialchars($__m['a']) ?></span></label></div><?php endforeach; ?>
    </div>
    <label class="form-label mt-2 mb-0">Visible en</label>
    <div class="d-flex gap-3">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="d_vis_staff" checked><label class="form-check-label">staff</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="d_vis_cx" checked><label class="form-check-label">cx</label></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarDoc()"><i class="fas fa-save me-1"></i>Guardar</button></div>
</div></div></div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDITAR=ACC['creditos.config_secciones.editar']===true;
const CAN_ELIM  =ACC['creditos.config_secciones.eliminar']===true;
const CAN_ITEMS =ACC['creditos.config_secciones.items']===true;
const OCASIONES=<?php echo json_encode($OCASIONES); ?>, TIPOS=<?php echo json_encode($TIPOS); ?>;
const TIPO_META={
  'estudiante':{a:'Est',c:'primary'},'empleado_descuento_nomina':{a:'Nom',c:'info'},
  'empleado_sin_descuento':{a:'Sin',c:'success'},'independiente':{a:'Ind',c:'warning'},
  'pensionado_descuento_libranza':{a:'Lib',c:'secondary'},'pensionado_sin_descuento_libranza':{a:'Pen',c:'dark'}
};
const TIPOS_FULL={'estudiante':'Estudiante','empleado_descuento_nomina':'Empleado desc. nómina','empleado_sin_descuento':'Empleado sin descuento','independiente':'Independiente','pensionado_descuento_libranza':'Pensionado desc. libranza','pensionado_sin_descuento_libranza':'Pensionado sin desc. libranza'};
const TIPOS_LIST=Object.keys(TIPO_META);
function renderLeyendaTipos(){
  const el=document.getElementById('leyendaTipos');if(!el)return;
  el.innerHTML=TIPOS_LIST.map(t=>{const m=TIPO_META[t]||{a:t,c:'light'};return `<span class="text-nowrap" title="${esc(TIPOS_FULL[t]||t)}"><span class="badge text-bg-${m.c} align-baseline" style="font-size:.68rem">${esc(m.a)}</span> <span class="text-muted">${esc(TIPOS_FULL[t]||t)}</span></span> · `;}).join('').replace(/ · $/,'');
}
let modalSeccion, modalCampo, modalDoc;
let editSeccionId=null, editCampoId=null, editDocId=null, curSec=0, SC=[], curItems=[];

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function slug(s){return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').slice(0,40);}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
function visArr(elStaff,elCx){const v=[];if(elStaff.checked)v.push('staff');if(elCx.checked)v.push('cx');return v;}
function parseArr(v){if(Array.isArray(v))return v;if(typeof v==='string'&&v.trim()){try{const j=JSON.parse(v);if(Array.isArray(j))return j;}catch(e){}return v.replace(/[\[\]"]/g,'').split(',').map(s=>s.trim()).filter(Boolean);}return [];}

/* ========== VISTA LISTA DE SECCIONES ========== */
async function cargarSecciones(){
  try{
    SC=await api('creditos/config?ent=secciones');
    document.getElementById('infoSecciones').textContent=`${SC.length} secciones`;
    document.getElementById('tbodySecciones').innerHTML=SC.map(s=>`
      <tr>
        <td><span class="fw-semibold">${esc(s.nombre)}</span>${s.estado_activo?'':' <span class="badge text-bg-secondary">inactiva</span>'}</td>
        <td>${esc(s.ocasion||s.codigo)}</td>
        <td class="text-center" id="cntc_${s.id}">—</td>
        <td class="text-center" id="cntd_${s.id}">—</td>
        <td class="text-end">
          <button class="btn btn-sm btn-brand me-1" onclick="verSeccion(${s.id})"><i class="fas fa-list me-1"></i>Detalle</button>
          ${CAN_EDITAR?`<button class="btn btn-sm btn-outline-secondary me-1" onclick='abrirSeccion(${JSON.stringify(s)})'><i class="fas fa-edit"></i></button>`:''}
          ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="delSeccion(${s.id})"><i class="fas fa-trash"></i></button>`:''}
        </td>
      </tr>`).join('')||'<tr><td colspan="5" class="text-center text-muted">Sin secciones</td></tr>';
    for(const s of SC){cargarContadores(s.id);}
  }catch(e){alert(e.message);}
}
async function cargarContadores(id){
  try{
    const c=await api('creditos/config?ent=campos&seccion_id='+id),d=await api('creditos/config?ent=documentos&seccion_id='+id);
    document.getElementById('cntc_'+id).textContent=c.length;
    document.getElementById('cntd_'+id).textContent=d.length;
  }catch(e){}
}

/* ========== DETALLE (campos + docs en una sola lista editable) ========== */
function verSeccion(id){
  curSec=id;const s=SC.find(x=>x.id===id);
  document.getElementById('detalleTitulo').textContent=s.nombre;
  document.getElementById('detalleSub').textContent=`${s.codigo} · ${s.ocasion||s.codigo}`;
  document.getElementById('vistaLista').classList.add('d-none');
  document.getElementById('vistaDetalle').classList.remove('d-none');
  cargarDetalle();
}
function volverLista(){document.getElementById('vistaDetalle').classList.add('d-none');document.getElementById('vistaLista').classList.remove('d-none');}
async function cargarDetalle(){
  try{
    const campos=await api('creditos/config?ent=campos&seccion_id='+curSec);
    const docs=await api('creditos/config?ent=documentos&seccion_id='+curSec);
    let items=docs.map(x=>({kind:'doc',obj:x,orden:x.orden||0}));
    items=items.concat(campos.map(c=>({kind:'campo',obj:c,orden:c.orden||0,plus:1})));
    items.sort((a,b)=>(a.orden-b.orden)||(a.kind===b.kind?0:(a.kind==='doc'?-1:1)));
    curItems=items;renderItems();
  }catch(e){alert(e.message);}
}
function itemLabel(it){return it.kind==='doc'?it.obj.nombre_mostrar:it.obj.nombre;}
function itemSub(it){return it.kind==='doc'?it.obj.tipo_documento:it.obj.tipo;}
function itemReq(it){
  if(it.kind==='doc')return it.obj.es_obligatorio?1:(it.obj.es_opcional?2:(it.obj.es_trato_especial?3:0));
  return it.obj.obligatorio?1:0;
}
function reqBadge(r){return r===1?'<span class="badge text-bg-danger">oblig</span>':r===2?'<span class="badge text-bg-warning">opcional</span>':r===3?'<span class="badge text-bg-secondary">trato</span>':'';}
function visBadges(it){
  const v=parseArr(it.obj.visible_en);
  const has=(k)=>v.includes(k);
  const b=[];
  b.push(has('staff')?'<span class="badge text-bg-secondary">staff</span>':'<span class="badge text-bg-light border"><i class="fas fa-times text-muted"></i></span>');
  b.push(has('cx')?'<span class="badge text-bg-secondary">cx</span>':'<span class="badge text-bg-light border"><i class="fas fa-times text-muted"></i></span>');
  return b.join(' ');
}
function tiposDeIt(it){return it.kind==='doc'?parseArr(it.obj.para_tipo_solicitante):parseArr(it.obj.para_tipo_solicitante);}
function aplicarPreview(){return document.getElementById('previewTipo').value;}
function aplicaTipo(it,tipo){
  if(!tipo)return null;
  return tiposDeIt(it).includes(tipo);
}
function tiposBadges(it){
  const ts=tiposDeIt(it);
  if(!ts.length)return '<span class="badge text-bg-light border text-muted"><i class="fas fa-ban me-1"></i>ninguno</span>';
  if(ts.length===TIPOS_LIST.length)return '<span class="badge text-bg-success" title="Aplica a los 6 tipos de crédito"><i class="fas fa-check me-1"></i>Todos</span>';
  return '<span class="text-nowrap">'+ts.map(t=>{const m=TIPO_META[t]||{a:t,c:'light border'};return `<span class="badge text-bg-${m.c}" title="${esc(TIPOS_FULL[t]||t)}" style="font-size:.7rem">${esc(m.a)}</span>`;}).join(' ')+'</span>';
}
function cambiarPreview(){renderItems();}
function renderItems(){
  const prev=aplicarPreview();
  document.getElementById('tbodyItems').innerHTML=curItems.map((it,i)=>{
    const aplica=prev?aplicaTipo(it,prev):null;
    const dim=aplica===false?' style="opacity:.35"':'';
    return `<tr${dim}>
      <td class="text-center text-muted">${i+1}</td>
      <td><span class="badge text-bg-${it.kind==='doc'?'primary':'info'}">${it.kind==='doc'?'Documento':'Campo'}</span></td>
      <td>
        <span class="fw-semibold">${esc(itemLabel(it))}</span>
        ${it.kind==='campo'?`<small class="text-muted ms-1">(${esc(itemSub(it))}${itemReq(it)?' *':''})</small>`:''}
      </td>
      <td>${it.kind==='doc'?reqBadge(itemReq(it))||'<span class="text-muted">—</span>':(itemReq(it)?reqBadge(1):'<span class="text-muted">opcional</span>')}</td>
      <td class="text-nowrap">${visBadges(it)}</td>
      <td>${tiposBadges(it)}</td>
      <td class="text-center">
        ${CAN_ITEMS?`<div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" onclick="moveItem(${i},-1)" ${i===0?'disabled':''}><i class="fas fa-chevron-up"></i></button>
          <button class="btn btn-outline-secondary" onclick="moveItem(${i},1)" ${i===curItems.length-1?'disabled':''}><i class="fas fa-chevron-down"></i></button>
        </div>`:`${i+1}`}
      </td>
      <td class="text-end">
        ${CAN_EDITAR?`<button class="btn btn-sm btn-outline-primary me-1" onclick='editarItem(${i})'><i class="fas fa-edit"></i></button>`:''}
        ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick='delItem(${i})'><i class="fas fa-trash"></i></button>`:''}
      </td>
    </tr>`;
  }).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin campos ni documentos.</td></tr>';
}
function editarItem(i){const it=curItems[i];it.kind==='campo'?abrirCampo(it.obj):abrirDoc(it.obj);}
async function moveItem(i,dir){
  const j=i+dir;if(j<0||j>=curItems.length)return;
  const tmp=curItems[i];curItems[i]=curItems[j];curItems[j]=tmp;
  renderItems();await persistOrden();
}
async function persistOrden(){
  try{
    for(let k=0;k<curItems.length;k++){
      const it=curItems[k],body={id:it.obj.id,orden:k+1};
      if(it.kind==='campo')await api('creditos/config?ent=campos',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
      else await api('creditos/config?ent=documentos',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
    }
    cargarContadores(curSec);
  }catch(e){alert(e.message);}
}
async function delItem(i){const it=curItems[i];if(!confirm(`¿Eliminar ${it.kind==='doc'?'documento':'campo'} "${itemLabel(it)}"?`))return;
  try{
    const e=it.kind==='campo'?'campos':'documentos';
    await api('creditos/config?ent='+e,{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:it.obj.id})});
    cargarDetalle();cargarContadores(curSec);
  }catch(e){alert(e.message);}
}

/* ========== SECCIÓN ========== */
function previewSeccion(){if(editSeccionId){document.getElementById('s_codigoPrev').innerHTML='Código: <code>'+esc(editSeccionId)+'</code>';return;}
  const n=document.getElementById('s_nombre').value;document.getElementById('s_codigoPrev').innerHTML=n?('Código sugerido: <code>'+esc(slug(n))+'</code>'):'Escriba el nombre…';}
function nuevaSeccion(){editSeccionId=null;document.getElementById('mtSeccion').textContent='Nueva sección';document.getElementById('s_nombre').value='';document.getElementById('s_ocasion').selectedIndex=0;document.getElementById('s_orden').value=100;document.getElementById('s_vis_staff').checked=true;document.getElementById('s_vis_cx').checked=true;document.getElementById('s_activo').checked=true;previewSeccion();modalSeccion.show();}
function abrirSeccion(s){editSeccionId=s.id;document.getElementById('mtSeccion').textContent='Editar sección';document.getElementById('s_nombre').value=s.nombre||'';document.getElementById('s_ocasion').value=s.ocasion||s.codigo||'creacion';document.getElementById('s_orden').value=s.orden||100;const v=Array.isArray(s.visible_en)?s.visible_en:['staff','cx'];document.getElementById('s_vis_staff').checked=v.includes('staff');document.getElementById('s_vis_cx').checked=v.includes('cx');document.getElementById('s_activo').checked=s.estado_activo==1;document.getElementById('s_codigoPrev').innerHTML='Código: <code>'+esc(s.codigo)+'</code>';modalSeccion.show();}
async function guardarSeccion(){
  const nom=document.getElementById('s_nombre').value.trim(),oc=document.getElementById('s_ocasion').value;
  if(!nom){alert('El nombre es obligatorio.');return;}
  const d={nombre:nom,ocasion:oc,orden:parseInt(document.getElementById('s_orden').value)||100,visible_en:visArr(document.getElementById('s_vis_staff'),document.getElementById('s_vis_cx')),estado_activo:document.getElementById('s_activo').checked?1:0};
  try{
    if(editSeccionId)await api('creditos/config?ent=secciones',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:editSeccionId,...d})});
    else await api('creditos/config?ent=secciones',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({codigo:slug(nom)||oc,...d})});
    modalSeccion.hide();cargarSecciones();
  }catch(e){alert(e.message);}
}
async function delSeccion(id){const s=SC.find(x=>x.id===id);if(!confirm(`¿Eliminar la sección "${s?s.nombre:''}" y sus campos/documentos?`))return;
  try{await api('creditos/config?ent=secciones',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});if(curSec===id){curSec=0;volverLista();}cargarSecciones();}catch(e){alert(e.message);}}

/* ========== CAMPO ========== */
function previewCampo(){if(editCampoId){document.getElementById('c_camposPrev').innerHTML='En edición.';return;}
  const n=document.getElementById('c_nombre').value;document.getElementById('c_camposPrev').innerHTML=n?('Clave: <code>'+esc(slug(n))+'.campo</code>'):'Escriba el nombre…';}
function tipoCampoChange(){document.getElementById('c_opc_wrap').classList.toggle('d-none',document.getElementById('c_tipo').value!=='select');}
function nuevoCampo(){editCampoId=null;document.getElementById('mtCampo').textContent='Nuevo campo';document.getElementById('c_nombre').value='';document.getElementById('c_tipo').value='texto';document.getElementById('c_opciones').value='';document.getElementById('c_oblig').checked=false;document.querySelectorAll('#c_tipos input').forEach(c=>c.checked=false);tipoCampoChange();previewCampo();modalCampo.show();}
function abrirCampo(c){editCampoId=c.id;document.getElementById('mtCampo').textContent='Editar campo';document.getElementById('c_nombre').value=c.nombre||'';document.getElementById('c_tipo').value=c.tipo||'texto';document.getElementById('c_opciones').value=(Array.isArray(c.opciones)?c.opciones.join('\n'):'');document.getElementById('c_oblig').checked=c.obligatorio==1;document.querySelectorAll('#c_tipos input').forEach(x=>{x.checked=parseArr(c.para_tipo_solicitante).includes(x.value);});tipoCampoChange();document.getElementById('c_camposPrev').innerHTML='Clave: <code>'+esc(c.campo||'')+'</code>';modalCampo.show();}
async function guardarCampo(){
  const nom=document.getElementById('c_nombre').value.trim();if(!nom){alert('El nombre es obligatorio.');return;}
  const tipo=document.getElementById('c_tipo').value;
  const para_tipo_solicitante=[...document.querySelectorAll('#c_tipos input:checked')].map(x=>x.value);
  const d={nombre:nom,tipo,opciones:tipo==='select'?(document.getElementById('c_opciones').value.split('\n').map(x=>x.trim()).filter(Boolean)):null,obligatorio:document.getElementById('c_oblig').checked?1:0,para_tipo_solicitante,estado_activo:1};
  try{
    if(editCampoId)await api('creditos/config?ent=campos',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:editCampoId,...d})});
    else await api('creditos/config?ent=campos',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({seccion_id:curSec,campo:slug(nom),...d})});
    modalCampo.hide();cargarDetalle();cargarContadores(curSec);
  }catch(e){alert(e.message);}
}

/* ========== DOCUMENTO (tipo_documento automático) ========== */
function previewDocTipo(){
  if(editDocId&&curItems.some(x=>x.kind==='doc'&&x.obj.id===editDocId)){return;}
  const n=document.getElementById('d_nombre').value.trim();
  document.getElementById('d_tipoPrev').innerHTML=n?('Tipo (automático): <code>'+esc(slug(n))+'</code>'):'Escriba el nombre para generar el tipo…';
}
function nuevoDoc(){editDocId=null;document.getElementById('mtDoc').textContent='Nuevo documento';document.getElementById('d_nombre').value='';document.getElementById('d_desc').value='';document.getElementById('d_oblig').checked=true;document.getElementById('d_opc').checked=false;document.getElementById('d_trato').checked=false;document.querySelectorAll('#d_tipos input').forEach(c=>c.checked=false);document.getElementById('d_vis_staff').checked=true;document.getElementById('d_vis_cx').checked=true;previewDocTipo();modalDoc.show();}
function abrirDoc(d){editDocId=d.id;document.getElementById('mtDoc').textContent='Editar documento';document.getElementById('d_nombre').value=d.nombre_mostrar||'';document.getElementById('d_desc').value=d.descripcion||'';document.getElementById('d_oblig').checked=!!d.es_obligatorio;document.getElementById('d_opc').checked=!!d.es_opcional;document.getElementById('d_trato').checked=!!d.es_trato_especial;document.getElementById('d_tipos').querySelectorAll('input').forEach(c=>{c.checked=parseArr(d.para_tipo_solicitante).includes(c.value);});const v=parseArr(d.visible_en).length?parseArr(d.visible_en):['staff','cx'];document.getElementById('d_vis_staff').checked=v.includes('staff');document.getElementById('d_vis_cx').checked=v.includes('cx');document.getElementById('d_tipoPrev').innerHTML='Tipo: <code>'+esc(d.tipo_documento||slug(d.nombre_mostrar||''))+'</code>';modalDoc.show();}
async function guardarDoc(){
  const nom=document.getElementById('d_nombre').value.trim();if(!nom){alert('El nombre es obligatorio.');return;}
  const d={nombre_mostrar:nom,descripcion:document.getElementById('d_desc').value.trim(),
    es_obligatorio:document.getElementById('d_oblig').checked?1:0,es_opcional:document.getElementById('d_opc').checked?1:0,es_trato_especial:document.getElementById('d_trato').checked?1:0,
    para_tipo_solicitante:[...document.getElementById('d_tipos').querySelectorAll('input:checked')].map(c=>c.value),visible_en:visArr(document.getElementById('d_vis_staff'),document.getElementById('d_vis_cx')),estado_activo:1};
  try{
    if(editDocId){// conserva el tipo_documento actual al editar
      const obj=curItems.find(x=>x.kind==='doc'&&x.obj.id===editDocId)?.obj;
      await api('creditos/config?ent=documentos',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:editDocId,...d,tipo_documento:obj?obj.tipo_documento:slug(nom)})});
    }else{
      await api('creditos/config?ent=documentos',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({seccion_id:curSec,tipo_documento:slug(nom),...d})});
    }
    modalDoc.hide();cargarDetalle();cargarContadores(curSec);
  }catch(e){alert(e.message);}
}

document.addEventListener('DOMContentLoaded',()=>{modalSeccion=new bootstrap.Modal('#mSeccion');modalCampo=new bootstrap.Modal('#mCampo');modalDoc=new bootstrap.Modal('#mDoc');renderLeyendaTipos();cargarSecciones();});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>