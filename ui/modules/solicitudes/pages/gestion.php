<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('solicitudes.gestion');
$acc = ui_acciones('solicitudes.gestion', $staff['rol'] ?? '');
$gestionId = (int) ($_GET['id'] ?? 0);
$filtroTipo = isset($_GET['tipo']) ? preg_replace('/[^a-z0-9_]/', '', (string) $_GET['tipo']) : '';
$pageTitle = $gestionId ? 'Detalle de solicitud' : 'Tickets';
$currentPage = 'solicitudes_gestion';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<?php if ($gestionId): ?>
<?php
$pledgeId = $gestionId;
?>
<div class="mb-2"><a class="btn btn-sm btn-outline-primary" href="gestion"><i class="fas fa-arrow-left me-1"></i>Volver al listado</a></div>
<div id="appDet"></div>
<div id="toasts" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080"></div>

<!-- Modal dinámico de confirmación de transición -->
<div class="modal fade" id="actModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="actTitle">Confirmar transición</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p id="actMsg" class="mb-2"></p>
        <div id="actRespWrap" class="mb-2" style="display:none">
          <label class="form-label small" for="actResp">Responsable <span class="text-danger">*</span></label>
          <select id="actResp" class="form-select form-select-sm"></select>
        </div>
        <label class="form-label small" id="actLbl" for="actCom">Comentario</label>
        <textarea id="actCom" rows="2" class="form-control" placeholder="Escriba un comentario…"></textarea>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" id="actOk">Confirmar</button></div>
    </div>
  </div>
</div>

<!-- Modal de reasignación -->
<div class="modal fade" id="reasignarModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Reasignar responsable</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label small" for="reasignarSel">Responsable</label>
        <select id="reasignarSel" class="form-select form-select-sm"></select>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" id="reasignarOk">Reasignar</button></div>
    </div>
  </div>
</div>

<style>
.det-topbar{position:sticky;top:.5rem;z-index:1020}
.card-header[data-bs-toggle]{cursor:pointer;user-select:none}
.card-header.brand.collapsed{border-radius:14px !important}
.card-header[data-bs-toggle] .chev{transition:transform .2s ease}
.card-header[data-bs-toggle].collapsed .chev{transform:rotate(-90deg)}
.act-feed{position:relative;padding-left:1.4rem}
.act-feed::before{content:'';position:absolute;left:6px;top:4px;bottom:4px;width:2px;background:#e1e8f0}
.act-item{position:relative;margin-bottom:.9rem}
.act-item::before{content:'';position:absolute;left:-1.25rem;top:.35rem;width:12px;height:12px;border-radius:50%;background:var(--brand-blue)}
.act-item.comentario::before{background:var(--brand-yellow)}
.kv{display:grid;grid-template-columns:auto 1fr;gap:.3rem .75rem;font-size:.88rem;margin:0}
.kv dt{color:#6c757d;font-weight:600;white-space:nowrap}
.kv dd{margin:0}
</style>
<script>
window.API=<?php echo json_encode(apiUrl()); ?>;
const ID=<?php echo $pledgeId; ?>;
const STAFF_ID=<?php echo (int) ($staff['id'] ?? 0); ?>;
const ROL=<?php echo json_encode($staff['rol'] ?? '', JSON_UNESCAPED_UNICODE); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const SEC_ACCIONES=ACC['solicitudes.gestion.acciones']===true;
const SEC_ASIGNAR=ACC['solicitudes.gestion.asignar']===true;
const SEC_REASIGNAR=ACC['solicitudes.gestion.reasignar']===true;
const SEC_ADJUNTAR=ACC['solicitudes.gestion.adjuntar']===true;
const SEC_COMENTAR=ACC['solicitudes.gestion.comentar']===true;
const L=window.Listado,esc=L.esc,money=L.money,fmt=L.fmtDtHora,api=L.api,deb=L.debounce;

var CACHE={categoriasFocus:{},responsables:{}};
async function loadConfig(){
  try{const c=await api('solicitudes/config');CACHE.categoriasFocus={};CACHE.responsables={};(c.categorias||[]).forEach(x=>CACHE.categoriasFocus[x.id]=x.nombre);(c.responsables||[]).forEach(x=>CACHE.responsables[x.id]=x.nombre_completo);}catch(e){}
}
const SD=window.SolDetalle;
const TRANS_LABEL={recibido:'Marcar recibido',asignado:'Asignar',en_curso:'En curso',en_espera:'En espera',resuelto:'Resuelto',aceptado:'Aceptar',rechazado:'Rechazar',rechazada:'Rechazar',aprobada:'Aprobar',cancelado:'Cancelar',anulado:'Anular'};
function respName(id){return CACHE.responsables[id]||('#'+id);}
function catName(id){return CACHE.categoriasFocus[id]||('#'+id);}
function actorName(tipo,id){if(id===''||id==null)return '';return tipo==='interno'?(CACHE.responsables[id]||('Usuario #'+id)):('Asociado '+id);}

function toast(msg,type){
  type=type||'success';
  const box=document.getElementById('toasts');if(!box)return;
  const el=document.createElement('div');
  el.className='toast align-items-center text-bg-'+type+' border-0';
  el.innerHTML='<div class="d-flex"><div class="toast-body">'+esc(msg)+'</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  box.appendChild(el);
  new bootstrap.Toast(el,{delay:4000}).show();
  el.addEventListener('hidden.bs.toast',()=>el.remove());
}

/* Sección plegable con contador. */
function sec(id,icon,label,count,body,open){
  const cid='sec-'+id;
  const badge=count==null?'':' <span class="badge text-bg-light">'+count+'</span>';
  return '<div class="card mb-3">'
    +'<div class="card-header brand d-flex justify-content-between align-items-center'+(open?'':' collapsed')+'" data-bs-toggle="collapse" data-bs-target="#'+cid+'" role="button" aria-expanded="'+(open?'true':'false')+'">'
    +'<span><i class="fas '+icon+' me-2"></i>'+label+badge+'</span><i class="fas fa-chevron-down small chev"></i></div>'
    +'<div id="'+cid+'" class="collapse'+(open?' show':'')+'"><div class="card-body">'+body+'</div></div>'
    +'</div>';
}

let D=null,S=null,REL='';
function relActor(s){
  if(!STAFF_ID)return '';
  if(String(s.responsable_id)===String(STAFF_ID))return 'responsable';
  if(String(s.creado_por)===String(STAFF_ID))return 'creador';
  if(String(s.cedula_solicitante)===String(STAFF_ID))return 'solicitante';
  return '';
}

async function cargarDet(keepScroll){
  const box=document.getElementById('appDet');
  const y=keepScroll?window.scrollY:0;
  box.innerHTML='<div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin me-2"></i>Cargando…</div>';
  try{
    const d=await api('solicitudes/detalle?id='+ID);
    D=d;S=d.solicitud;REL=relActor(S);
    box.innerHTML=renderPantalla();
    if(keepScroll)window.scrollTo(0,y);
  }catch(e){box.innerHTML='<div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-2"></i>'+esc(e.message)+'</div>';}
}

/* ── Cabecera sticky con acciones primarias ── */
function transDisponibles(){
  const T=D.transiciones||[];
  return T.filter(tr=>!tr.rol_relacion||tr.rol_relacion===REL);
}
function btnTrans(tr){
  const dest=tr.estado_destino,label=TRANS_LABEL[dest]||dest;
  const pos=dest==='aprobada'||dest==='aceptado'||dest==='resuelto';
  const req=Number(tr.requiere_comentario)===1;
  const needResp=dest==='asignado';
  const gate=tr.gate_regla?' title="Requiere validación: '+esc(tr.gate_regla)+'"':'';
  return '<button class="btn btn-sm btn-'+(pos?'success':'outline-primary')+'" data-dest="'+esc(dest)+'" data-req="'+(req?1:0)+'" data-resp="'+(needResp?1:0)+'"'+gate+' onclick="abrirTrans(this)">'+esc(label)+(req?' <span class="text-danger">*</span>':'')+'</button>';
}
function slaBadge(){
  if(S.tipo_codigo!=='ticket')return '';
  const v=(D.detalle||{}).fecha_vencimiento;if(!v)return '';
  const d=new Date(v);if(isNaN(d.getTime()))return '';
  const diff=Math.ceil((d.getTime()-Date.now())/86400000);
  let cls='info',txt='Vence en '+diff+'d';
  if(diff<0){cls='danger';txt='Vencido hace '+Math.abs(diff)+'d';}
  else if(diff<=2){cls='warning';txt='Vence en '+diff+'d';}
  return '<span class="badge text-bg-'+cls+'" title="Vencimiento: '+esc(fmt(v))+'"><i class="fas fa-clock me-1"></i>'+txt+'</span>';
}
function topbar(){
  const fw=SD.faseBadge(S.fase);
  const T=transDisponibles();
  const btns=(SEC_ACCIONES&&T.length)?T.map(btnTrans).join(''):(SEC_ACCIONES?'<span class="text-muted small">Sin acciones disponibles</span>':'');
  const sol=esc(S.solicitante_nombre||'—')+(S.solicitante_tipo==='asociado'?(' · C.C. '+esc(S.cedula_solicitante)):' <span class="badge text-bg-secondary">interno</span>');
  return '<div class="det-topbar card mb-3"><div class="card-body d-flex flex-wrap align-items-center gap-2 py-2">'
    +'<div><div class="fw-bold">'+esc(S.numero_radicado)+' <span class="badge text-bg-light">'+esc(SD.tipoLabel(S.tipo_codigo))+'</span></div>'
    +'<div class="small text-muted">'+sol+'</div></div>'
    +'<span class="badge text-bg-'+fw[1]+'">'+esc(SD.estadoLabel(S.estado_codigo))+' · '+fw[0]+'</span>'
    +slaBadge()
    +'<div class="ms-auto d-flex flex-wrap gap-1">'+btns+'</div>'
    +'</div></div>';
}

/* ── Resumen lateral (key: value) ── */
function kvResumen(){
  const row=(k,v)=>'<dt>'+k+'</dt><dd>'+v+'</dd>';
  let h='<dl class="kv">';
  h+=row('Tipo',esc(SD.tipoLabel(S.tipo_codigo)));
  h+=row('Solicitante',esc(S.solicitante_nombre||S.cedula_solicitante)+(S.solicitante_tipo==='asociado'?'<br><span class="text-muted">C.C. '+esc(S.cedula_solicitante)+'</span>':' <span class="badge text-bg-secondary">interno</span>'));
  h+=row('Responsable',esc(S.responsable_id?respName(S.responsable_id):'—')+((S.responsable_id&&puedeReasignar())?' <button class="btn btn-sm btn-link p-0 ms-1 align-baseline" onclick="abrirReasignar()" title="Reasignar responsable"><i class="fas fa-user-pen"></i> Reasignar</button>':''));
  h+=row('Origen',esc(S.origen||'—'));
  h+=row('Creado',esc(fmt(S.fecha_creacion)));
  h+=row('Resultado',esc(S.resultado||'—'));
  h+=row('Respondido',esc(fmt(S.fecha_respuesta)));
  if(D.calificacion&&D.calificacion.puntuacion!=null)h+=row('Calificación',SD.estrellasHtml(D.calificacion.puntuacion)+' <span class="text-muted small">'+D.calificacion.puntuacion+'/5</span>'+(D.calificacion.comentarios?'<br><span class="text-muted small">'+esc(D.calificacion.comentarios)+'</span>':''));
  h+='</dl>';
  if(S.comentario_respuesta)h+='<div class="mt-2 small"><span class="text-muted">Comentario de respuesta:</span><br>'+esc(S.comentario_respuesta)+'</div>';
  return h;
}

/* ── Reasignación (solo liderazgo o el responsable asignado) ── */
function puedeReasignar(){return SEC_REASIGNAR||REL==='responsable';}

/* ── Detalle tipado + items ── */
function renderDetalle(tipo,det){return SD.renderDetalleTipo(tipo,det,{catName:catName});}
function itemsTable(){
  if(!D.items||!D.items.length)return '';
  return '<div class="table-responsive mt-2"><table class="table table-sm mb-0"><thead><tr><th>Producto</th><th>Convenio</th><th class="text-end">Cantidad</th></tr></thead><tbody>'
    +D.items.map(i=>'<tr><td>'+esc(i.producto_nombre)+'</td><td>'+esc(i.convenio_nombre)+'</td><td class="text-end">'+i.cantidad+'</td></tr>').join('')+'</tbody></table></div>';
}
function boletasTable(){
  if(!D.boletas||!D.boletas.length)return '';
  return '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>#</th><th>Serial</th><th>Producto</th><th>Estado</th><th>Cédula</th><th>Método</th></tr></thead><tbody>'
    +D.boletas.map(b=>'<tr><td>'+b.boleta_id+'</td><td>'+esc(b.serial)+'</td><td>'+esc(b.producto_nombre||'')+'</td><td><span class="badge text-bg-'+(b.estado==='vendida'?'success':'secondary')+'">'+esc(b.estado)+'</span></td><td>'+esc(b.asociado_cedula||'')+'</td><td>'+esc(b.metodo_venta||'—')+'</td></tr>').join('')+'</tbody></table></div>';
}
function fondoDiag(){
  const det=D.detalle||{};
  if(S.tipo_codigo!=='fondo'||!det.convenio_id)return '';
  return '<button class="btn btn-sm btn-outline-primary mt-2" onclick="diagFondo()"><i class="fas fa-clipboard-check me-1"></i>Diagnóstico de elegibilidad</button>';
}

/* ── Adjuntos ── */
function adjuntoLink(a){return SD.adjuntoLink(a,API);}
function adjuntosSolicitudBlock(){
  const A=SD.adjuntosSolicitud(D.adjuntos);
  if(!A.length)return '';
  return '<div class="mt-3"><div class="text-muted small mb-1"><i class="fas fa-paperclip me-1"></i>Adjuntos de la solicitud</div><div class="d-flex flex-wrap">'+A.map(adjuntoLink).join('')+'</div></div>';
}
function buildFeed(){return SD.buildActividad(D.eventos,D.adjuntos,{mode:'staff',nombres:CACHE.responsables,respuestaFallback:{texto:S.comentario_respuesta,fecha:S.fecha_respuesta,actor:S.respondido_por?respName(S.respondido_por):'Sistema'}});}
function cerrada(){return ['RESUELTA_POSITIVA','RESUELTA_NEGATIVA','ANULADA'].indexOf(S.fase)!==-1;}
function actividadBody(){
  let h=SD.feedHtml(buildFeed(),API);
  if(SEC_COMENTAR&&cerrada()){h+='<div class="alert alert-secondary py-2 small mt-3 mb-0"><i class="fas fa-lock me-1"></i>La solicitud ya fue respondida; no admite más comentarios.</div>';}
  if(SEC_COMENTAR&&!cerrada()){
    h+='<div class="mt-3 border rounded p-2">'
      +'<textarea id="cmt" rows="2" class="form-control form-control-sm mb-2" placeholder="Escribir un comentario…"></textarea>'
      +'<div class="d-flex align-items-center gap-2">'
      +(SEC_ADJUNTAR?'<input id="cmtFiles" type="file" class="form-control form-control-sm" multiple>':'')
      +'<button class="btn btn-sm btn-primary text-nowrap ms-auto" onclick="sendCmt(this)"><i class="fas fa-paper-plane me-1"></i>Enviar</button>'
      +'</div></div>';
  }
  return h;
}

function renderPantalla(){
  const det=D.detalle||{};
  const main='<div class="col-lg-8">'
    +sec('detalle','fa-list','Detalle',null,renderDetalle(S.tipo_codigo,det)+itemsTable()+fondoDiag()+adjuntosSolicitudBlock(),true)
    +((D.boletas&&D.boletas.length)?sec('boletas','fa-ticket','Boletas emitidas',D.boletas.length,boletasTable(),true):'')
    +sec('actividad','fa-clock-rotate-left','Actividad',buildFeed().length,actividadBody(),true)
    +'</div>';
  const side='<div class="col-lg-4">'
    +sec('resumen','fa-circle-info','Resumen',null,kvResumen(),true)
    +'</div>';
  return topbar()+'<div class="row g-3">'+main+side+'</div>';
}

/* ── Transiciones y acciones ── */
let pendingDest='',pendingReq=false,pendingResp=false,actModalInst=null;
function abrirTrans(btn){
  pendingDest=btn.dataset.dest;pendingReq=btn.dataset.req==='1';pendingResp=btn.dataset.resp==='1';
  const msg=({recibido:'Marcar como recibida',aprobada:'Resolver como APROBADA',rechazada:'Resolver como RECHAZADA',aceptado:'Aceptar la solicitud',rechazado:'Rechazar la solicitud',resuelto:'Marcar como resuelta',asignado:'Asignar responsable'})[pendingDest]||('Cambiar a "'+pendingDest+'"');
  document.getElementById('actTitle').textContent=pendingReq?'Confirmar (comentario obligatorio)':'Confirmar transición';
  document.getElementById('actMsg').textContent=msg+'.';
  document.getElementById('actLbl').innerHTML=pendingReq?'Comentario <span class="text-danger">*</span>':'Comentario (opcional)';
  const com=document.getElementById('actCom');com.value='';com.classList.remove('is-invalid');
  const wrap=document.getElementById('actRespWrap'),sel=document.getElementById('actResp');
  if(pendingResp){
    sel.innerHTML='<option value="">— Seleccione responsable —</option>'+Object.entries(CACHE.responsables).map(([id,n])=>'<option value="'+id+'"'+(String(id)===String(S.responsable_id)?' selected':'')+'>'+esc(n)+'</option>').join('');
    sel.classList.remove('is-invalid');wrap.style.display='';
  }else{wrap.style.display='none';}
  if(!actModalInst)actModalInst=new bootstrap.Modal(document.getElementById('actModal'));
  actModalInst.show();
}
function confirmarTrans(){
  const com=document.getElementById('actCom');const c=com.value.trim();
  if(pendingReq&&!c){com.classList.add('is-invalid');com.focus();return;}
  let rid=null;
  if(pendingResp){const sel=document.getElementById('actResp');rid=sel.value;if(!rid){sel.classList.add('is-invalid');sel.focus();return;}}
  actModalInst.hide();
  runTrans(pendingDest,c,rid);
}
async function runTrans(dest,c,respId){
  let task,body;
  if(dest==='asignado'&&respId){task='solicitudes/asignar.php';body={id:ID,estado_destino:dest,responsable_id:respId};}
  else if(dest==='recibido'){task='solicitudes/marcar_recibido.php';body={id:ID,estado_destino:dest};}
  else{task='solicitudes/transicion.php';body={id:ID,estado_destino:dest};if(c)body.comentario=c;}
  try{await api(task,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});toast('Acción aplicada.');cargarDet(true);}
  catch(e){toast(e.message,'danger');}
}
let reasignarInst=null;
function abrirReasignar(){
  const sel=document.getElementById('reasignarSel');
  sel.innerHTML=Object.entries(CACHE.responsables).map(([id,n])=>'<option value="'+id+'"'+(String(id)===String(S.responsable_id)?' selected':'')+'>'+esc(n)+'</option>').join('');
  sel.classList.remove('is-invalid');
  if(!reasignarInst)reasignarInst=new bootstrap.Modal(document.getElementById('reasignarModal'));
  reasignarInst.show();
}
function confirmarReasignar(){
  const sel=document.getElementById('reasignarSel');const rid=sel.value;
  if(!rid){sel.classList.add('is-invalid');sel.focus();return;}
  reasignarInst.hide();
  doReasignar(rid);
}
async function doReasignar(rid){
  try{await api('solicitudes/reasignar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:ID,responsable_id:rid})});toast('Responsable actualizado.');cargarDet(true);}
  catch(e){toast(e.message,'danger');}
}
async function sendCmt(btn){
  const c=document.getElementById('cmt').value.trim();
  const fi=document.getElementById('cmtFiles');
  const files=fi&&fi.files?fi.files:[];
  if(!c&&!files.length){return;}
  const fd=new FormData();
  fd.append('id',ID);fd.append('mensaje',c||'(adjunto)');
  for(const f of files)fd.append('files[]',f);
  try{const r=await fetch(API+'solicitudes/comentar.php',{method:'POST',body:fd});const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');toast('Comentario agregado.');cargarDet(true);}
  catch(e){toast(e.message,'danger');}
}
async function diagFondo(){
  try{
    const det=(D&&D.detalle)||{};
    if(!det.convenio_id){toast('Sin convenio para diagnosticar.','warning');return;}
    const r=await api('fondo_bienestar/validar?cedula='+encodeURIComponent(S.cedula_solicitante)+'&convenio_id='+det.convenio_id);
    toast((r.validacion.mensaje||'')+' · Pagos al día: '+(r.pagos.al_dia?'Sí':'No')+' · meses: '+r.pagos.pagos+' · producto: '+(r.tiene_producto?'Sí':'No'),'info');
  }catch(e){toast(e.message,'danger');}
}
document.getElementById('actOk').onclick=confirmarTrans;
document.getElementById('reasignarOk').onclick=confirmarReasignar;
loadConfig().then(()=>cargarDet(false));
</script>
<?php else: ?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list-check me-2"></i>Tickets</span>
    <span class="d-flex align-items-center gap-2">
      <span class="small text-white-50" id="infoTotal"></span>
      <span id="fSizeBox"></span>
    </span>
  </div>
  <div class="card-body">
    <!-- Filtros (patrón estándar de listados) -->
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:360px;flex:1 1 240px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="q" placeholder="Radicado, cédula o nombre…">
          <button class="btn btn-outline-secondary" id="qClear" type="button" title="Limpiar búsqueda" style="display:none" onclick="clearQ()"><i class="fas fa-times"></i></button>
        </div>
      </div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Tipo</label><select class="form-select" id="fTipo" data-searchable style="width:210px"><option value="">Todos</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label><select class="form-select" id="fEstado" data-searchable style="width:180px"><option value="">Todos</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Fase</label><select class="form-select" id="fFase" data-searchable style="width:170px"><option value="">Todas</option><option>CREADA</option><option>EN_GESTION</option><option>PRE_RESUELTA</option><option>RESUELTA_POSITIVA</option><option>RESUELTA_NEGATIVA</option><option>ANULADA</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Responsable</label><select class="form-select" id="fResp" data-searchable style="width:220px"><option value="">Todos</option></select></div>
      <div class="col-auto ms-auto d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" id="btnMias" onclick="toggleMias()"><i class="fas fa-user-check me-1"></i>Asignadas a mí</button>
        <button class="btn btn-sm btn-link text-decoration-none" id="btnLimpiar" onclick="limpiarFiltros()" style="display:none">Limpiar</button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead><tr>
          <th class="sortable" data-col="numero_radicado">Radicado</th>
          <th class="sortable" data-col="tipo_codigo">Tipo</th>
          <th class="sortable" data-col="cedula_solicitante">Cédula</th><th>Solicitante</th>
          <th>Fase · Estado</th><th class="sortable" data-col="responsable_id">Responsable</th>
          <th class="sortable" data-col="fecha_creacion">Fecha</th><th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>
<div id="toasts" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080"></div>

<script>
window.API=<?php echo json_encode(apiUrl()); ?>;
const STAFF_ID=<?php echo (int) ($staff['id'] ?? 0); ?>;
const L=window.Listado,esc=L.esc,fmtDt=L.fmtDt,api=L.api,deb=L.debounce;
const TIPO_INI=<?php echo json_encode($filtroTipo); ?>;
let state={q:'',tipo:TIPO_INI,estado:'',fase:'',resp:'',page:0,size:20,order:'fecha_creacion',dir:'DESC'};
let CFG={tipos:[],estados:{},responsables:[]};

function toast(msg,type){
  type=type||'danger';
  const box=document.getElementById('toasts');if(!box)return;
  const el=document.createElement('div');
  el.className='toast align-items-center text-bg-'+type+' border-0';
  el.innerHTML='<div class="d-flex"><div class="toast-body">'+esc(msg)+'</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  box.appendChild(el);new bootstrap.Toast(el,{delay:4000}).show();
  el.addEventListener('hidden.bs.toast',()=>el.remove());
}

async function loadCfg(){
  try{
    CFG=await api('solicitudes/config');
    document.getElementById('fResp').innerHTML='<option value="">Todos</option>'+CFG.responsables.map(x=>'<option value="'+x.id+'">'+esc(x.nombre_completo)+'</option>').join('');
    L.refreshSelect('fResp');
    buildTipos();
    buildEstados(state.tipo||'');
    L.setSelect('fResp',state.resp||'');
    updateFilterUI();
  }catch(e){toast(e.message);}
}
function buildEstados(t){
  let opts;
  if(t){opts=(CFG.estados[t]||[]).map(e=>'<option value="'+e.codigo+'">'+esc(e.nombre)+'</option>');}
  else{const all=CFG.tipos.flatMap(x=>(CFG.estados[x.codigo]||[]).map(e=>({v:e.codigo,n:e.nombre})));opts=all.filter((x,i,a)=>a.findIndex(y=>y.v===x.v)===i).map(x=>'<option value="'+x.v+'">'+esc(x.n)+'</option>');}
  const sel=document.getElementById('fEstado');
  sel.innerHTML='<option value="">Todos</option>'+opts.join('');
  L.refreshSelect(sel);
  L.setSelect(sel,state.estado||'');
}
function buildTipos(){
  const counts=state.counts||{};
  const hay=Object.keys(counts).length>0;
  const sel=document.getElementById('fTipo');
  sel.innerHTML='<option value="">Todos</option>'+(CFG.tipos||[]).map(t=>{
    const n=hay?(counts[t.codigo]||0):null;
    return '<option value="'+t.codigo+'">'+esc(t.nombre)+(n!=null?' ('+n+')':'')+'</option>';
  }).join('');
  L.refreshSelect(sel);
  L.setSelect(sel,state.tipo||'');
}
function updateFilterUI(){
  document.getElementById('btnLimpiar').style.display=(state.q||state.tipo||state.estado||state.fase||state.resp)?'':'none';
  document.getElementById('qClear').style.display=state.q?'':'none';
  const mias=STAFF_ID&&state.resp===String(STAFF_ID);
  document.getElementById('btnMias').classList.toggle('btn-primary',mias);
  document.getElementById('btnMias').classList.toggle('btn-outline-secondary',!mias);
}
function toggleMias(){
  state.resp=(STAFF_ID&&state.resp===String(STAFF_ID))?'':String(STAFF_ID);
  L.setSelect('fResp',state.resp);
  state.page=0;updateFilterUI();cargar();
}
function clearQ(){document.getElementById('q').value='';state.q='';state.page=0;updateFilterUI();cargar();}
function limpiarFiltros(){
  state.q='';state.tipo='';state.estado='';state.fase='';state.resp='';state.page=0;
  document.getElementById('q').value='';L.setSelect('fResp','');buildEstados('');buildTipos();updateFilterUI();cargar();
}
function irDet(id){location.href='gestion?id='+id;}

L.bindSort(document,state,cargar);
function renderSortIcons(){L.renderSortIcons(document,state);}
document.getElementById('q').addEventListener('input',deb(function(){state.q=document.getElementById('q').value.trim();state.page=0;updateFilterUI();cargar();},300));
document.getElementById('fTipo').addEventListener('change',function(){state.tipo=this.value;state.estado='';buildEstados(state.tipo);state.page=0;updateFilterUI();cargar();});
document.getElementById('fEstado').addEventListener('change',function(){state.estado=this.value;state.page=0;updateFilterUI();cargar();});
document.getElementById('fFase').addEventListener('change',function(){state.fase=this.value;state.page=0;updateFilterUI();cargar();});
document.getElementById('fResp').addEventListener('change',function(){state.resp=this.value;state.page=0;updateFilterUI();cargar();});

const ESTBADGE=window.SolDetalle.ESTADO;

async function cargar(){
  const off=state.page*state.size;
  const sp=new URLSearchParams({limit:state.size,offset:String(off),order:state.order,dir:state.dir});
  if(state.q)sp.set('q',state.q);if(state.tipo)sp.set('tipo',state.tipo);if(state.estado)sp.set('estado',state.estado);if(state.fase)sp.set('fase',state.fase);if(state.resp)sp.set('responsable',state.resp);
  try{
    const d=await api('solicitudes/listar?'+sp.toString());
    const total=d.total||0,rows=d.rows||[];
    document.getElementById('infoTotal').textContent=total>0?('Mostrando '+(off+1)+'-'+Math.min(off+state.size,total)+' de '+total):'';
    renderSortIcons();
    state.counts=d.por_tipo||{};
    buildTipos();
    const rname=Object.fromEntries(CFG.responsables.map(r=>[r.id,r.nombre_completo]));
    document.getElementById('tbody').innerHTML=rows.map(r=>{const eb=ESTBADGE[r.estado_codigo]||[r.estado_codigo,'secondary'];
      return '<tr style="cursor:pointer" onclick="irDet('+r.id+')">'
      +'<td><span class="badge text-bg-light">'+esc(r.numero_radicado)+'</span></td>'
      +'<td><span class="badge text-bg-warning">'+esc(r.tipo_nombre||r.tipo_codigo)+'</span></td>'
      +'<td><small>'+esc(r.cedula_solicitante)+'</small></td>'
      +'<td>'+esc(r.solicitante_nombre||'—')+'</td>'
      +'<td><span class="badge text-bg-'+eb[1]+'">'+eb[0]+'</span></td>'
      +'<td>'+esc(r.responsable_nombre||rname[r.responsable_id]||'—')+'</td>'
      +'<td><small>'+fmtDt(r.fecha_creacion)+'</small></td>'
      +'<td class="text-end"><button class="btn btn-sm btn-outline-primary" title="Gestionar" onclick="event.stopPropagation();irDet('+r.id+')"><i class="fas fa-pen-to-square"></i></button></td>'
      +'</tr>';}).join('')||'<tr><td colspan="8" class="text-center text-muted py-4">Sin solicitudes</td></tr>';
    renderPagination(total);
  }catch(e){toast(e.message);}
}
function renderPagination(t){document.getElementById('paginacion').innerHTML=L.paginacion(t,state.page,state.size);}
function goPage(p){state.page=p;cargar();window.scrollTo(0,0);}
L.mountSize('fSizeBox', state, cargar);
loadCfg().then(()=>cargar());
</script>
<?php endif; ?>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
