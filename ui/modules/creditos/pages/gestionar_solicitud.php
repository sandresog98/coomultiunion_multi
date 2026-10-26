<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('creditos');
$rol = $staff['rol'] ?? '';
$sec = ui_secciones('creditos.gestionar_solicitud', $rol);
$acc = ui_acciones('creditos.gestionar_solicitud', $rol);
$pageTitle = 'Gestionar solicitud';
$currentPage = 'creditos_gestion';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
$sid = (int) ($_GET['id'] ?? 0);
?>
<input type="hidden" id="sid" value="<?php echo $sid; ?>">
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span id="titulo"><i class="fas fa-industry me-2"></i>Solicitud <span id="hNumero">…</span></span>
    <span class="d-flex gap-2 flex-wrap" id="hBadges"></span>
  </div>
  <div class="card-body" id="body"><div class="text-center text-muted py-4">Cargando…</div></div>
</div>

<!-- Modal editar datos de la solicitud -->
<div class="modal fade" id="mBasicos"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h6>Editar datos de la solicitud</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label">Nombre <span class="text-danger">*</span></label><input class="form-control" id="bNombre"></div>
      <div class="col-md-6"><label class="form-label">Tipo de solicitante</label>
        <select class="form-select" id="bTipoSolicitante">
          <option value="estudiante">Estudiante</option>
          <option value="empleado_descuento_nomina">Empleado con descuento de nómina</option>
          <option value="empleado_sin_descuento">Empleado sin descuento</option>
          <option value="independiente">Independiente</option>
          <option value="pensionado_descuento_libranza">Pensionado con descuento de libranza</option>
          <option value="pensionado_sin_descuento_libranza">Pensionado sin descuento de libranza</option>
        </select>
      </div>
      <div class="col-md-6"><label class="form-label">Nº identificación</label><input class="form-control" id="bIdentificacion"></div>
      <div class="col-md-6"><label class="form-label">Teléfono</label><input class="form-control" id="bTelefono"></div>
      <div class="col-md-6"><label class="form-label">Correo</label><input class="form-control" id="bCorreo"></div>
      <div class="col-md-6"><label class="form-label">Línea de crédito original</label><select class="form-select" id="bLinea"></select></div>
      <div class="col-md-6"><label class="form-label">Monto deseado</label><input type="number" class="form-control" id="bMonto" step="1000" min="100000"></div>
      <div class="col-md-6"><label class="form-label">Cuotas deseadas</label><input type="number" class="form-control" id="bCuotas" min="1" max="72"></div>
    </div>
    <div class="row g-2 mt-1 align-items-end">
      <div class="col-md-6">
        <div class="form-check"><input class="form-check-input" type="checkbox" id="bCodeudor" onchange="toggleTipoCodeudor()"><label class="form-check-label" for="bCodeudor">¿Desea codeudor?</label></div>
      </div>
      <div class="col-md-6"><label class="form-label">Tipo de codeudor</label>
        <select class="form-select" id="bTipoCodeudor">
          <option value="no_necesita">No necesita</option>
          <option value="codeudor_dependiente">Codeudor dependiente</option>
          <option value="codeudor_pensionado">Codeudor pensionado</option>
        </select>
      </div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarBasicos()"><i class="fas fa-save me-1"></i>Guardar</button></div>
</div></div></div>

<!-- Modal confirmar cambio de etapa -->
<div class="modal fade" id="mEtapa"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6><i class="fas fa-arrows-alt-h me-1"></i>Cambiar de etapa</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">¿Está seguro que quiere pasar de <b id="etDe"></b> a <b id="etA"></b>?</div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="confirmarEtapaNext()">Confirmar</button></div>
</div></div></div>

<!-- Modal plan de pagos PDF -->
<div class="modal fade" id="mPlan"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6>Generar plan de pagos (PDF)</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="row g-2">
    <div class="col-6"><label class="form-label">Aportes</label><input id="plAportes" type="number" class="form-control" value="65000"></div>
    <div class="col-6"><label class="form-label">Bolsillo</label><input id="plBolsillo" type="number" class="form-control" value="0"></div>
    <div class="col-6"><label class="form-label">Fondo solidario</label><input id="plFondo" type="number" class="form-control" value="0"></div>
    <div class="col-6"><label class="form-label">Fecha inicio</label><input id="plInicio" type="date" class="form-control"></div>
    <div class="col-6"><label class="form-label">Primer pago</label><input id="plPrimer" type="date" class="form-control"></div>
  </div></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="generarPlan()"><i class="fas fa-file-pdf me-1"></i>Descargar</button></div>
</div></div></div>

<!-- Modal cancelar -->
<div class="modal fade" id="mCancelar"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="text-danger">Cancelar solicitud</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><label class="form-label">Motivo de cancelación <span class="text-danger">*</span></label><textarea class="form-control" id="caMotivo" rows="3"></textarea></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger" onclick="confirmarCancelar()">Cancelar solicitud</button></div>
</div></div></div>

<!-- Modal rechazar -->
<div class="modal fade" id="mRechazar"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h6 class="text-danger">Rechazar solicitud</h6><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><label class="form-label">Comentario del rechazo <span class="text-danger">*</span></label><textarea class="form-control" id="reComentario" rows="3"></textarea></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger" onclick="confirmarRechazo()">Rechazar</button></div>
</div></div></div>

<style>.sec-din b{font-size:.78rem}.accordion-button{font-weight:600}</style>
<script>
const API=<?php echo json_encode(apiUrl()); ?>, U=<?php echo json_encode(uiBaseUrl()); ?>, SID=<?php echo $sid; ?>;
const SEC=<?php echo json_encode($sec, JSON_UNESCAPED_UNICODE); ?>;
const SEC_DETALLE=SEC['creditos.gestionar_solicitud.detalle']===true;
const SEC_ACEPT  =SEC['creditos.gestionar_solicitud.aceptaciones']===true;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const SEC_BASICOS=ACC['creditos.gestionar_solicitud.datos_basicos']===true;
const SEC_PLAN   =ACC['creditos.gestionar_solicitud.plan_pagos']===true;
const SEC_ETAPAS =ACC['creditos.gestionar_solicitud.etapas']===true;
const SEC_ADJ    =ACC['creditos.gestionar_solicitud.adjuntos']===true;
const SEC_CAMPOS =ACC['creditos.gestionar_solicitud.campos']===true;
const SEC_ACC    =ACC['creditos.gestionar_solicitud.acciones']===true;
const ESTETA={creacion:['Creación','primary'],revision:['Revisión','info'],estudio:['Estudio','warning'],consideracion:['Consideración','warning'],estudio_aprobado:['Aprobado','success'],estudio_rechazado:['Rechazado','danger'],radicado_tesoreria:['Radicado·Tesorería','dark'],final:['Final','dark']};
const ORDT=['creacion','revision','estudio','consideracion','estudio_aprobado','radicado_tesoreria','estudio_rechazado','final'];
const ESTADOS_CERRADOS=['cancelado','rechazado','desembolsado'];
const TIPO_LABEL={estudiante:'Estudiante',empleado_descuento_nomina:'Empleado con descuento de nómina',empleado_sin_descuento:'Empleado sin descuento',independiente:'Independiente',pensionado_descuento_libranza:'Pensionado con descuento de libranza',pensionado_sin_descuento_libranza:'Pensionado sin descuento de libranza'};
const TIPO_CODEUDOR_LABEL={no_necesita:'No necesita',codeudor_dependiente:'Codeudor dependiente',codeudor_pensionado:'Codeudor pensionado'};
let S=null, mensaje=null;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function tipoLabel(v){return TIPO_LABEL[v]||String(v||'—').replace(/_/g,' ');}
function tipoCodeudorLabel(v){return TIPO_CODEUDOR_LABEL[v]||String(v||'—').replace(/_/g,' ');}
function money(v){return Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0});}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
function flag(m){if(m)alert(m);}
function esCerrada(){return !!S && ESTADOS_CERRADOS.includes(String(S.estado));}
function motivoCierre(){return String((S&&S.comentarios_rechazo)||'').trim().replace(/^\s*\[(Cancelado|Cancelada|Rechazado|Rechazada)\]\s*/i,'');}
function alertaEstado(){
  if(!S) return '';
  const est=String(S.estado||'');
  if(!ESTADOS_CERRADOS.includes(est)) return '';
  const cfg={
    cancelado:{cls:'secondary',ic:'fa-ban',titulo:'Solicitud cancelada',texto:'Esta solicitud está cerrada y no admite cambios.'},
    rechazado:{cls:'danger',ic:'fa-circle-xmark',titulo:'Solicitud rechazada',texto:'Esta solicitud está cerrada y no admite cambios.'},
    desembolsado:{cls:'success',ic:'fa-circle-check',titulo:'Crédito desembolsado',texto:'El crédito ya fue desembolsado; la solicitud no admite cambios.'}
  }[est];
  if(!cfg) return '';
  const motivo=(est==='cancelado'||est==='rechazado')?motivoCierre():'';
  const fecha=est==='desembolsado'?(S.fecha_desembolso||S.fecha_actualizacion):S.fecha_actualizacion;
  return `<div class="alert alert-${cfg.cls} d-flex gap-2 align-items-start mb-3">
    <i class="fas ${cfg.ic} mt-1"></i>
    <div><b>${cfg.titulo}</b>${fecha?` <span class="small text-muted">· ${esc(fecha)}</span>`:''}
    ${motivo?`<div class="small mt-1"><b>Motivo:</b> ${esc(motivo)}</div>`:''}
    <div class="small mt-1">${cfg.texto}</div></div></div>`;
}
function bloquearCerrada(){if(esCerrada()){flag('La solicitud está cerrada y no admite cambios.');return true;}return false;}

let RENDER_CACHE=null, RENDER_PROMISE=null;
async function renderAllData(){
  if(RENDER_CACHE!==null) return RENDER_CACHE;
  if(RENDER_PROMISE) return RENDER_PROMISE;   // dedupe llamadas concurrentes
  const url='creditos/render?seccion=all&canal=staff&tipo_solicitante='+encodeURIComponent(S.tipo_solicitante||'');
  RENDER_PROMISE=(async()=>{
    for(let i=0;i<3;i++){
      try{ RENDER_CACHE=await api(url); return RENDER_CACHE; }
      catch(e){ if(i===2) throw e; await new Promise(r=>setTimeout(r,300*(i+1))); }
    }
  })();
  try{ return await RENDER_PROMISE; } finally { RENDER_PROMISE=null; }
}
async function cargar(){
  RENDER_CACHE=null; RENDER_PROMISE=null;
  try{
    S=await api('creditos/detalle?id='+SID);
  }catch(e){
    document.getElementById('body').innerHTML=`<div class="alert alert-danger mb-0">No se pudo cargar la solicitud: ${esc(e.message)}</div>`;
    return;
  }
  const et=ESTETA[S.etapa_actual]||[S.etapa_actual,'secondary'];
  document.getElementById('hNumero').textContent=S.numero_solicitud;
  const eb={cancelado:['Cancelada','secondary'],rechazado:['Rechazada','danger']}[S.estado];
  const badges=eb
    ? `<span class="badge text-bg-${eb[1]}">${eb[0]}</span><span class="badge text-bg-light border text-muted">Etapa: ${esc(et[0])}</span>`
    : `<span class="badge text-bg-${et[1]}">${et[0]}</span>`+(S.estado==='desembolsado'?`<span class="badge text-bg-success">Desembolsado</span>`:'');
  document.getElementById('hBadges').innerHTML=badges;
  const b=document.getElementById('body');
  let h=alertaEstado();
  h+=`<div class="d-flex flex-wrap gap-2 mb-3">`;
  h+=`${SEC_PLAN?`<button class="btn btn-outline-info btn-sm" onclick="abrirPlan()"><i class="fas fa-file-pdf me-1"></i>Plan de pagos</button>`:''}
    ${SEC_ACC&&!esCerrada()?`<button class="btn btn-outline-danger btn-sm" onclick="abrirRechazo()">Rechazar</button>
    <button class="btn btn-outline-secondary btn-sm" onclick="abrirCancelar()">Cancelar</button>`:''}
    <a class="btn btn-outline-secondary btn-sm ms-auto" href="${U}modules/cobranza/pages/cobranza?q=${encodeURIComponent(S.cedula_asociado||'')}"><i class="fas fa-phone"></i> Cobranza</a></div>`;
  h+=`${SEC_ETAPAS&&!esCerrada()?`<div id="controlEtapas" class="mb-3"></div>`:''}`;
  h+=`<div class="card mb-3">
    <div class="card-header brand d-flex justify-content-between align-items-center">
      <span><i class="fas fa-file-invoice-dollar me-1"></i>Datos de la solicitud</span>
      ${S.origen==='ui'&&SEC_BASICOS&&!esCerrada()
        ? `<button class="btn btn-light btn-sm" onclick="abrirBasicos()"><i class="fas fa-pen-to-square me-1"></i>Editar</button>`
        : `<span class="badge text-bg-light">${esCerrada()?'Cerrada':'Solo lectura'}</span>`}
    </div>
    <div class="card-body"><div class="row g-2 small">
      <div class="col-md-4"><b>Valor deseado:</b> ${S.monto_deseado!=null?'$'+money(S.monto_deseado):'—'}</div>
      <div class="col-md-4"><b>Cuotas deseadas:</b> ${esc(S.numero_cuotas_deseadas??'—')}</div>
      <div class="col-md-4"><b>Línea original:</b> ${esc(S.linea_solicitada_nombre||'—')}</div>
      <div class="col-md-4"><b>Tipo de solicitante:</b> ${esc(tipoLabel(S.tipo_solicitante))}</div>
      <div class="col-md-4"><b>Nº identificación:</b> ${esc(S.numero_identificacion||'—')}</div>
      <div class="col-md-4"><b>¿Desea codeudor?:</b> ${S.desea_codeudor?'Sí':'No'}${S.desea_codeudor&&S.tipo_codeudor&&S.tipo_codeudor!=='no_necesita'?` <span class="text-muted">(${esc(tipoCodeudorLabel(S.tipo_codeudor))})</span>`:''}</div>
      <div class="col-md-4"><b>Fecha creación:</b> ${esc(S.fecha_creacion||'—')}</div>
    </div></div>
  </div>`;
  h+=`<div id="infoSolicitante"></div>`;
  h+=`${SEC_ACEPT?`<div id="aceptacionResumen" class="mt-3"></div>`:''}`;
  h+=`<h6 class="mt-4 fw-semibold"><i class="fas fa-layer-group me-1"></i>Secciones del flujo</h6>`;
  h+=`<div class="accordion mt-2" id="secAcord"></div>`;
  b.innerHTML=h;
  // Secuencial a propósito: evita conexiones BD concurrentes desde la misma
  // página (algunos entornos locales rechazan SYNs concurrentes) y acelera la carga.
  try{ renderControlEtapas(); }catch(e){}
  await Promise.allSettled([renderSolicitante()]);
  if(SEC_ACEPT) await Promise.allSettled([renderAceptaciones()]);
  await renderSecciones();
}
async function renderSolicitante(){
  const div=document.getElementById('infoSolicitante');let p=null;
  try{const f=await api('asociados/ficha?cedula='+encodeURIComponent(S.cedula_asociado||''));p=f.persona;}catch(e){}
  const btn=p?`<a class="btn btn-sm btn-outline-secondary" href="${U}modules/asociados/pages/asociados_detalle?cedula=${encodeURIComponent(S.cedula_asociado||'')}" target="_blank"><i class="fas fa-id-card me-1"></i>Ir a detalle del asociado</a>`:'';
  div.innerHTML=`<div class="card"><div class="card-header brand d-flex justify-content-between align-items-center"><span><i class="fas fa-user me-1"></i>Información del solicitante</span>${btn}</div><div class="card-body">
    <div class="row g-2 small">
      <div class="col-12 mb-1"><b>Origen:</b> ${S.origen==='ui'?'<span class="badge text-bg-primary">UI</span>':'<span class="badge text-bg-secondary">CX</span>'}</div>
      <div class="col-md-4"><b>Nombre:</b> ${esc(S.nombre_solicitante||'—')}</div>
      <div class="col-md-4"><b>Cédula:</b> ${esc(S.cedula_asociado||'—')}${p?` <span class="text-muted">(${esc(p.codigo_interno||'')})</span>`:''}</div>
      <div class="col-md-4"><b>Tipo solicitante:</b> ${esc(S.tipo_solicitante||'—')}</div>
      <div class="col-md-4"><b>Teléfono:</b> ${esc(S.numero_telefono||(p?p.telefono:'')||'—')}</div>
      <div class="col-md-4"><b>Correo:</b> ${esc(S.correo_electronico||(p?p.email:'')||'—')}</div>
      <div class="col-md-4"><b>Celular:</b> ${esc(p?p.celular:'')||'—'}</div>
      <div class="col-md-4"><b>Dirección:</b> ${esc(p?(p.direccion||''):'')||'—'}${p&&p.barrio?` <small class="text-muted">(${esc(p.barrio)})</small>`:''}</div>
      <div class="col-md-4"><b>Empresa:</b> ${esc(p?p.empresa:'')||'—'}</div>
      <div class="col-md-4"><b>Ocupación:</b> ${esc(p?p.ocupacion:'')||'—'}</div>
      <div class="col-md-4"><b>Ciudad:</b> ${esc(p?(p.ciudad||''):'')||'—'}</div>
      <div class="col-md-4"><b>Estado civil:</b> ${esc(p?p.estado_civil:'')||'—'}</div>
      <div class="col-md-4"><b>Fecha ingreso:</b> ${esc(p?p.fecha_ingreso:'')||'—'}</div>
    </div></div></div>`;
}
let etapaDestino=null;
function renderControlEtapas(){
  const box=document.getElementById('controlEtapas');
  if(!box) return;
  if(esCerrada()){box.innerHTML='';return;}
  const cur=S.etapa_actual, curIdx=ORDT.indexOf(cur);
  box.innerHTML=`<div class="d-flex flex-wrap gap-1 align-items-center">`+ORDT.map((e,i)=>{
    const eS=ESTETA[e]||[e,'secondary'];const isCur=e===cur;
    const visited=i<curIdx;const state=isCur?'btn-brand':((visited||i===curIdx)?'btn-outline-secondary':'btn-outline-light text-muted');
    return `<button class="btn btn-sm ${state}" ${isCur?'disabled':''} onclick='pedirEtapa("${e}")'>${esc(eS[0])}</button>`;
  }).join('')+`</div>`;
}
function pedirEtapa(e){
  if(bloquearCerrada())return;
  if(e===S.etapa_actual)return;
  if(e==='estudio_rechazado'){abrirRechazo();return;}   // el rechazo exige comentario + auditoría
  etapaDestino=e;
  document.getElementById('etDe').textContent=(ESTETA[S.etapa_actual]||[S.etapa_actual])[0];
  document.getElementById('etA').textContent=(ESTETA[e]||[e])[0];
  new bootstrap.Modal('#mEtapa').show();
}
async function confirmarEtapaNext(){
  if(bloquearCerrada())return;
  const e=etapaDestino;if(!e)return;
  try{await api('creditos/transicion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:SID,accion:'etapa',destino:e})});bootstrap.Modal.getInstance(document.getElementById('mEtapa')).hide();cargar();}catch(err){bootstrap.Modal.getInstance(document.getElementById('mEtapa')).hide();flag(err.message);}
}
async function renderSecciones(){
  const box=document.getElementById('secAcord');
  const r=await renderAllData();
  const grupos=(r.secciones||[]).filter(g=>g.seccion&&g.seccion.ocasion&&g.seccion.ocasion!=='aceptacion');
  if(!grupos.length){box.innerHTML='<div class="text-muted small">Sin secciones configuradas.</div>';return;}
  box.innerHTML='';let idx=0;
  for(const g of grupos){
    const ac=g.seccion.ocasion;const active=(ac===S.etapa_actual)?' show':'';
    const campos=g.campos||[];const docs=g.documentos||[];
    const itemId='sec'+safeId(ac)+idx;
    box.insertAdjacentHTML('beforeend',`<div class="accordion-item">
      <div class="accordion-header"><button class="accordion-button ${active?'':'collapsed'}" data-bs-toggle="collapse" data-bs-target="#${itemId}">
        <span class="me-2 badge text-bg-${(ESTETA[ac]||[])[1]||'secondary'}">${esc((ESTETA[ac]||[ac])[0])}</span>
        ${campos.length+docs.length} ítems</button></div>
      <div id="${itemId}" class="accordion-collapse collapse ${active}" data-bs-parent="#secAcord"><div class="accordion-body">
        ${renderItemsTable(ac,campos,docs)}
      </div></div></div>`);
    idx++;
  }
}
function fmtMiles(v){const s=String(v??'').replace(/[^\d]/g,'');return s?Number(s).toLocaleString('es-CO'):'';}
function campoIni(c){
  const val=(S.valores||[]).find(v=>v.config_campo_id===c.id);
  if(val && val.valor!=null && val.valor!=='') return String(val.valor);
  const col=CAMPOS_TIP[c.campo];
  if(col && S[col]!=null && S[col]!=='') return String(S[col]);
  return '';
}
const CAMPOS_TIP={monto_aprobado:'monto_aprobado',linea_aprobada:'linea_credito_aprobada_id',cuotas_aprobadas:'cuotas_aprobadas',codeudor_aprobado:'codeudor_aprobado',desea_codeudor:'desea_codeudor',tipo_codeudor:'tipo_codeudor',base_aportes:'base_aportes',fianza:'fianza',prestamos_recoger:'prestamos_recoger',dto_papeleria:'dto_papeleria',numero_sifone:'numero_credito_sifone',fecha_desembolso:'fecha_desembolso',valor_desembolso:'valor_real_desembolso',desembolsado_a_pac:'desembolsado_pac'};
const CODEUDOR_OPTS=[['no_necesita','No necesita'],['codeudor_dependiente','Codeudor dependiente'],['codeudor_pensionado','Codeudor pensionado']];
const TIPO_CODEUDOR_OPTS=[['codeudor_dependiente','Dependiente'],['codeudor_pensionado','Pensionado']];
function campoControl(ac,c){
  const ini=campoIni(c), ds=`data-set="cam-${ac}-${c.id}" data-campo="${c.id}"`, dis=esCerrada()?' disabled':'';
  if(c.tipo==='checkbox')return `<input class="form-check-input" type="checkbox" ${ds} ${ini==='1'?'checked':''}${dis}>`;
  if(c.tipo==='select'){
    let o='';
    if(c.campo==='linea_aprobada')o=(S.lineas||[]).map(x=>`<option value="${x.id}" ${ini===String(x.id)?'selected':''}>${esc(x.nombre_credito)}</option>`).join('');
    else if(c.campo==='codeudor_aprobado')o=CODEUDOR_OPTS.map(x=>`<option value="${x[0]}" ${ini===x[0]?'selected':''}>${x[1]}</option>`).join('');
    else if(c.campo==='tipo_codeudor')o=TIPO_CODEUDOR_OPTS.map(x=>`<option value="${x[0]}" ${ini===x[0]?'selected':''}>${x[1]}</option>`).join('');
    else{try{const arr=JSON.parse(c.opciones||'[]');o=(Array.isArray(arr)?arr:[]).map(x=>`<option value="${esc(x.v??x)}" ${ini==(x.v??x)?'selected':''}>${esc(x.l??x)}</option>`).join('');}catch(e){o='';}}
    return `<select class="form-select form-select-sm" ${ds}${dis}><option value="">—</option>${o}</select>`;
  }
  if(c.tipo==='fecha')return `<input class="form-control form-control-sm" type="date" ${ds} value="${esc(ini)}"${dis}>`;
  if(c.tipo==='numero')return `<input class="form-control form-control-sm text-end" type="text" inputmode="numeric" data-num="1" oninput="this.value=fmtMiles(this.value)" ${ds} value="${fmtMiles(ini)}"${dis}>`;
  return `<input class="form-control form-control-sm" type="text" ${ds} value="${esc(ini)}"${dis}>`;
}
function renderItemsTable(ac,campos,docs){
  const items=docs.map(d=>({kind:'doc',o:d.orden||0,obj:d})).concat(campos.map(c=>({kind:'campo',o:c.orden||0,obj:c})))
    .sort((a,b)=>(a.o-b.o)||(a.kind===b.kind?0:(a.kind==='doc'?-1:1)));
  if(!items.length)return '<div class="text-muted small">Sin campos ni documentos configurados en esta etapa.</div>';
  const rows=items.map(it=>{
    if(it.kind==='campo'){const c=it.obj;
      return `<tr><td class="text-center text-muted">·</td><td class="text-nowrap"><span class="badge text-bg-info">Campo</span></td>
        <td class="text-nowrap">${esc(c.nombre)}${c.obligatorio?'<span class="text-danger">*</span>':''}</td><td>${campoControl(ac,c)}</td></tr>`;
    }
    const d=it.obj;const doc=(S.documentos||[]).find(x=>x.etapa===ac&&x.tipo_documento===d.tipo_documento);
    const btn=(onclick,ttl,ic,cls)=>`<button class="btn btn-sm ${cls}" title="${ttl}" onclick="${onclick}"><i class="${ic}"></i></button>`;
    const editable=!esCerrada();
    const up=SEC_ADJ&&editable?btn(`subirDoc('${ac}','${d.tipo_documento}')`,doc?'Reemplazar':'Subir',doc?'fas fa-rotate':'fas fa-upload','btn-outline-secondary'):'';
    const ver=doc?`<a class="btn btn-sm btn-outline-primary" title="Ver" href="${API}creditos/serve_documento?id=${doc.id}" target="_blank"><i class="fas fa-eye"></i></a>`:'';
    const del=SEC_ADJ&&doc&&editable?`<button class="btn btn-sm btn-outline-danger" title="Eliminar" data-nombre="${esc(d.nombre_mostrar||'')}" onclick="eliminarDoc(${doc.id},this.dataset.nombre)"><i class="fas fa-trash"></i></button>`:'';
    return `<tr><td class="text-center text-muted">·</td><td class="text-nowrap"><span class="badge text-bg-primary">Documento</span></td>
      <td class="text-nowrap">${esc(d.nombre_mostrar)}${d.es_obligatorio?'<span class="text-danger">*</span>':''}</td>
      <td><div class="d-flex gap-1 align-items-center justify-content-end text-nowrap">
        ${SEC_ADJ&&editable?`<input type="file" class="form-control form-control-sm" id="docup_${ac}_${d.tipo_documento}" accept=".pdf,.png,.jpg,.jpeg" style="max-width:130px;width:130px">`:''}
        ${up}${ver}${del}</div></td></tr>`;
  }).join('');
  return `<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th style="width:30px"></th><th>Tipo</th><th>Ítem</th><th>Valor / Acción</th></tr></thead><tbody>${rows}</tbody></table></div>
    ${SEC_CAMPOS&&!esCerrada()?`<button class="btn btn-sm btn-outline-primary" onclick="guardarCampos('${ac}')"><i class="fas fa-save me-1"></i>Guardar campos</button>`:''}`;
}
function safeId(s){return (s||'s').replace(/[^A-Za-z0-9_]/g,'_');}
async function guardarCampos(ac){
  if(bloquearCerrada())return;
  const vals=[];document.querySelectorAll(`[data-set^="cam-${ac}-"]`).forEach(el=>{
    const id=parseInt(el.getAttribute('data-campo')||'0');if(!id)return;
    vals.push({config_campo_id:id,valor:el.type==='checkbox'?(el.checked?'1':'0'):(el.dataset&&el.dataset.num?el.value.replace(/[^\d]/g,''):el.value)});
  });
  try{await api('creditos/valores',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:SID,valores:vals})});flag('Campos guardados');}catch(e){flag(e.message);}
}
async function subirDoc(ac,tipo){
  if(bloquearCerrada())return;
  const el=document.getElementById('docup_'+ac+'_'+tipo);if(!el||!el.files[0]){flag('Selecciona un archivo.');return;}
  const fd=new FormData();fd.append('solicitud_id',SID);fd.append('etapa',ac);fd.append('tipo_documento',tipo);fd.append('archivo',el.files[0]);
  try{const r=await fetch(API+'creditos/documento',{method:'POST',body:fd});const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');flag('Documento guardado.');cargar();}catch(e){flag(e.message);}
}
async function eliminarDoc(id,nom){if(bloquearCerrada())return;if(!confirm(`¿Eliminar el documento "${nom}"?`))return;
  try{await api('creditos/documento',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});cargar();}catch(e){flag(e.message);}}
async function renderAceptaciones(){
  const cont=document.getElementById('aceptacionResumen');
  const d=await renderAllData();const list=d.aceptaciones||[];
  const ok=(S.aceptado_por_asociado||list.some(a=>{const v=S.aceptaciones.find(x=>x.config_aceptacion_id===a.id);return v&&v.valor==='1';}));
  const chks=list.map(a=>{const v=S.aceptaciones.find(x=>x.config_aceptacion_id===a.id);const okk=v&&v.valor==='1';
    return `<span class="badge ${okk?'text-bg-success':'text-bg-light border text-muted'}" title="${esc(a.titulo||'')}">${okk?'✓':'✕'} ${esc(a.titulo?a.titulo.split(' ').slice(0,4).join(' '):'item')}</span>`;}).join(' ');
  cont.innerHTML=`<div class="card"><div class="card-header brand py-1"><i class="fas fa-check-double me-1"></i>Aceptación y Autorizaciones</div><div class="card-body small py-2">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <i class="fas ${ok?'fa-check-circle text-success':'fa-times-circle text-muted'}"></i>
      <b>Aceptó:</b> ${ok?'<span class="badge text-bg-success">Sí</span>':'<span class="badge text-bg-secondary">No / Pendiente</span>'}
      ${S.fecha_aceptacion?`<span class="text-muted">· ${esc(S.fecha_aceptacion)}</span>`:''}
      <span class="ms-auto">${chks||'<span class="text-muted">Sin items configurados</span>'}</span>
    </div></div></div>`;
}
/* ===== acciones ===== */
function toggleTipoCodeudor(){
  const chk=document.getElementById('bCodeudor'), sel=document.getElementById('bTipoCodeudor');
  sel.disabled=!chk.checked;
  if(!chk.checked) sel.value='no_necesita';
}
function abrirBasicos(){
  if(bloquearCerrada())return;
  document.getElementById('bNombre').value=S.nombre_solicitante||'';
  document.getElementById('bTipoSolicitante').value=S.tipo_solicitante||'';
  document.getElementById('bIdentificacion').value=S.numero_identificacion||'';
  document.getElementById('bTelefono').value=S.numero_telefono||'';
  document.getElementById('bCorreo').value=S.correo_electronico||'';
  document.getElementById('bMonto').value=S.monto_deseado??'';
  document.getElementById('bCuotas').value=S.numero_cuotas_deseadas??'';
  const cur=parseInt(S.tasa_interes_id||0,10);
  const lineas=S.lineas||[];
  let opts=lineas.map(x=>`<option value="${x.id}">${esc(x.nombre_credito)}</option>`).join('');
  if(cur>0 && !lineas.some(x=>parseInt(x.id,10)===cur)){
    opts=`<option value="${cur}">${esc(S.linea_solicitada_nombre||('Línea #'+cur))}</option>`+opts;
  }
  document.getElementById('bLinea').innerHTML='<option value="">—</option>'+opts;
  document.getElementById('bLinea').value=cur>0?String(cur):'';
  document.getElementById('bCodeudor').checked=!!Number(S.desea_codeudor);
  document.getElementById('bTipoCodeudor').value=S.tipo_codeudor||'no_necesita';
  toggleTipoCodeudor();
  new bootstrap.Modal('#mBasicos').show();
}
async function guardarBasicos(){
  if(bloquearCerrada())return;
  const monto=parseFloat(document.getElementById('bMonto').value)||0;
  const cuotas=parseInt(document.getElementById('bCuotas').value)||0;
  if(monto<100000){flag('El monto deseado debe ser al menos $100.000.');return;}
  if(cuotas<1){flag('Las cuotas deseadas deben ser al menos 1.');return;}
  const desea=document.getElementById('bCodeudor').checked;
  const d={
    nombre_solicitante:document.getElementById('bNombre').value.trim(),
    tipo_solicitante:document.getElementById('bTipoSolicitante').value,
    numero_identificacion:document.getElementById('bIdentificacion').value.trim(),
    numero_telefono:document.getElementById('bTelefono').value.trim(),
    correo_electronico:document.getElementById('bCorreo').value.trim(),
    monto_deseado:monto,
    numero_cuotas_deseadas:cuotas,
    tasa_interes_id:parseInt(document.getElementById('bLinea').value||'0',10)||0,
    desea_codeudor:desea,
    tipo_codeudor:desea?(document.getElementById('bTipoCodeudor').value||'no_necesita'):'no_necesita'
  };
  try{await api('creditos/transicion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:SID,accion:'actualizar',...d})});bootstrap.Modal.getInstance(document.getElementById('mBasicos')).hide();cargar();}catch(e){flag(e.message);}
}
function abrirPlan(){document.getElementById('plAportes').value=65000;document.getElementById('plBolsillo').value=0;document.getElementById('plFondo').value=0;document.getElementById('plInicio').value=S.fecha_desembolso||'';document.getElementById('plPrimer').value='';new bootstrap.Modal('#mPlan').show();}
function generarPlan(){
  const q=new URLSearchParams();q.set('id',SID);q.set('aportes',document.getElementById('plAportes').value||65000);q.set('bolsillo',document.getElementById('plBolsillo').value||0);q.set('fondo_solidario',document.getElementById('plFondo').value||0);
  const inicio=document.getElementById('plInicio').value,primer=document.getElementById('plPrimer').value;if(inicio)q.set('fecha_inicio',inicio);if(primer)q.set('primer_pago',primer);
  window.open(API+'creditos/plan_pdf?'+q.toString(),'_blank');
}
function abrirCancelar(){if(bloquearCerrada())return;document.getElementById('caMotivo').value='';new bootstrap.Modal('#mCancelar').show();}
function abrirRechazo(){if(bloquearCerrada())return;document.getElementById('reComentario').value='';new bootstrap.Modal('#mRechazar').show();}
async function confirmarCancelar(){if(bloquearCerrada())return;const m=document.getElementById('caMotivo').value.trim();if(!m){flag('El motivo es obligatorio.');return;}try{await api('creditos/transicion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:SID,accion:'cancelar',motivo:m})});bootstrap.Modal.getInstance(document.getElementById('mCancelar')).hide();cargar();}catch(e){flag(e.message);}}
async function confirmarRechazo(){if(bloquearCerrada())return;const m=document.getElementById('reComentario').value.trim();if(!m){flag('El comentario es obligatorio.');return;}try{await api('creditos/transicion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:SID,accion:'rechazar',comentario:m})});bootstrap.Modal.getInstance(document.getElementById('mRechazar')).hide();cargar();}catch(e){flag(e.message);}}
document.addEventListener('DOMContentLoaded',cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>