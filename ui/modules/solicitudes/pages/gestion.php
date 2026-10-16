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
<style>
.timeline{position:relative;padding-left:1.4rem}.timeline::before{content:'';position:absolute;left:6px;top:4px;bottom:4px;width:2px;background:#e1e8f0}
.tl-item{position:relative;margin-bottom:.9rem}.tl-item::before{content:'';position:absolute;left:-1.25rem;top:.3rem;width:12px;height:12px;border-radius:50%;background:var(--brand-blue)}
.tl-item .tl-work{font-size:.9rem}
</style>
<script>
const API=<?php echo json_encode(apiUrl()); ?>,U=<?php echo json_encode(uiBaseUrl()); ?>,ID=<?php echo $pledgeId; ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const SEC_ACCIONES=ACC['solicitudes.gestion.acciones']===true;
const SEC_ASIGNAR =ACC['solicitudes.gestion.asignar']===true;
const SEC_ADJUNTAR=ACC['solicitudes.gestion.adjuntar']===true;
const SEC_COMENTAR=ACC['solicitudes.gestion.comentar']===true;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){return '$'+Number(v||0).toLocaleString('es-CO',{maximumFractionDigits:0});}
function fmtDt(v){if(!v)return'—';let d=new Date(v);return isNaN(d.getTime())?'—':d.toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
var CACHE={categoriasFocus:{},responsables:{}};
async function loadConfig(){
  try{const c=await api('solicitudes/config');CACHE.categoriasFocus={};CACHE.responsables={};(c.categorias||[]).forEach(x=>CACHE.categoriasFocus[x.id]=x.nombre);(c.responsables||[]).forEach(x=>CACHE.responsables[x.id]=x.nombre_completo);}catch(e){}
}
const FASE_BADGE={CREADA:['Creada','secondary'],EN_GESTION:['En gestión','info'],PRE_RESUELTA:['Pre-resuelta','warning'],RESUELTA_POSITIVA:['Positiva','success'],RESUELTA_NEGATIVA:['Negativa','danger'],ANULADA:['Anulada','dark']};
function respName(id){return CACHE.responsables[id]||('#'+id);}
function catName(id){return CACHE.categoriasFocus[id]||('#'+id);}
async function cargarDet(){
  const box=document.getElementById('appDet');box.innerHTML='<div class="text-center text-muted py-5">Cargando…</div>';
  try{
    const d=await api('solicitudes/detalle?id='+ID);
    const s=d.solicitud,det=d.detalle||{},fw=FASE_BADGE[s.fase]||[s.fase,'secondary'];
    let h='';
    h+=`<div class="card mb-3"><div class="card-header brand"><i class="fas fa-file-lines me-2"></i>${esc(s.numero_radicado)} <span class="badge text-bg-light ms-1">${esc(s.tipo_codigo)}</span></div>`;
    h+=`<div class="card-body"><div class="row g-2">
      <div class="col-md-3"><div class="text-muted small">Tipo</div><b>${esc(s.tipo_codigo)}</b></div>
      <div class="col-md-3"><div class="text-muted small">Estado</div><span class="badge text-bg-${fw[1]}">${esc(s.estado_codigo)} · ${fw[0]}</span></div>
      <div class="col-md-3"><div class="text-muted small">Solicitante</div><b>${esc(s.cedula_solicitante)}</b> ${s.solicitante_tipo==='asociado'?'':'(interno)'}</div>
      <div class="col-md-3"><div class="text-muted small">Responsable</div>${respName(s.responsable_id)}</div>
      <div class="col-md-3"><div class="text-muted small">Origen</div>${esc(s.origen)}</div>
      <div class="col-md-3"><div class="text-muted small">Creado</div>${fmtDt(s.fecha_creacion)}</div>
      <div class="col-md-3"><div class="text-muted small">Resultado</div>${esc(s.resultado||'—')}</div>
      <div class="col-md-3"><div class="text-muted small">Respondido</div>${fmtDt(s.fecha_respuesta)}</div>
    </div></div></div>`;

    // Detalle tipado
    h+='<div class="card mb-3"><div class="card-header brand"><i class="fas fa-list me-2"></i>Detalle</div><div class="card-body">';
    h+=renderDetalle(s.tipo_codigo,det);
    if(d.items&&d.items.length){h+='<div class="table-responsive mt-2"><table class="table table-sm mb-0"><thead><tr><th>Producto</th><th>Convenio</th><th>Cantidad</th></tr></thead><tbody>'+d.items.map(i=>`<tr><td>${esc(i.producto_nombre)}</td><td>${esc(i.convenio_nombre)}</td><td>${i.cantidad}</td></tr>`).join('')+'</tbody></table></div>';}
    h+='</div></div>';

    // Diagnóstico de elegibilidad (solo fondo)
    if(s.tipo_codigo==='fondo' && det && det.convenio_id){
      h+=`<div class="card mb-3"><div class="card-header brand"><i class="fas fa-clipboard-check me-2"></i>Fondo de bienestar</div><div class="card-body"><button class="btn btn-sm btn-outline-primary" onclick="diagFondo()"><i class="fas fa-clipboard-check me-1"></i>Diagnóstico de elegibilidad</button></div></div>`;
    }

    // Boletas emitidas (si aplica)
    if(d.boletas&&d.boletas.length){h+=`<div class="card mb-3"><div class="card-header brand"><i class="fas fa-ticket me-2"></i>Boletas emitidas</div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>#</th><th>Serial</th><th>Producto</th><th>Estado</th><th>Cédula</th><th>Método</th></tr></thead><tbody>`+d.boletas.map(b=>`<tr><td>${b.boleta_id}</td><td>${esc(b.serial)}</td><td>${esc(b.producto_nombre||'')}</td><td><span class="badge text-bg-${b.estado==='vendida'?'success':'secondary'}">${esc(b.estado)}</span></td><td>${esc(b.asociado_cedula||'')}</td><td>${esc(b.metodo_venta||'—')}</td></tr>`).join('')+'</tbody></table></div></div>';}

    // Acciones por estado
    const T=d.transiciones||[];
    h+=renderAcciones(s,T);
    // Adjuntos
    h+=`<div class="card mb-3"><div class="card-header brand"><i class="fas fa-paperclip me-2"></i>Adjuntos</div><div class="card-body">${(d.adjuntos||[]).length?d.adjuntos.map(a=>`<a class="btn btn-sm btn-outline-secondary me-1 mb-1" href="${API}solicitudes/serve_adjunto.php?id=${a.id}" target="_blank"><i class="fas fa-file"></i> ${esc(a.nombre_original||a.ruta_archivo)}</a>`).join(''):'<span class="text-muted">Sin adjuntos</span>'}</div></div>`;
    // Timeline
    h+=`<div class="card mb-3"><div class="card-header brand"><i class="fas fa-history me-2"></i>Timeline</div><div class="card-body"><div class="timeline">`+((d.eventos&&d.eventos.length)?d.eventos.map(e=>`<div class="tl-item"><div class="fw-semibold">${esc(e.tipo_evento)}</div><div class="text-muted small">${fmtDt(e.fecha)} ${s.nombre? '':''}</div>${e.estado_anterior||e.estado_nuevo?`<div class="text-muted">${esc(e.estado_anterior||'')} → ${esc(e.estado_nuevo||'')}</div>`:''}${e.mensaje?`<div class="tl-work">${esc(e.mensaje)}</div>`:''}</div>`).join(''):'<div class="text-muted">Sin eventos</div>')+'</div></div></div>';
    // Comentarios
    h+=`<div class="card mb-3"><div class="card-header brand"><i class="fas fa-comments me-2"></i>Comentarios</div><div class="card-body"><div class="mb-3">`+d.comentarios.map(c=>`<div class="border rounded p-2 mb-2"><div class="small"><b>${esc(c.tipo)}</b> <span class="text-muted">${fmtDt(c.fecha_creacion)}</span></div><div>${esc(c.mensaje)}</div></div>`).join('')||'<span class="text-muted">Sin comentarios</span>'+`</div>
    ${SEC_COMENTAR?`<div class="input-group"><input id="cmt" class="form-control" placeholder="Escribir comentario…"><button class="btn btn-primary" onclick="sendCmt()">Enviar</button></div>`:''}</div></div>`;
    box.innerHTML=h;
    try{await loadConfig();document.querySelectorAll('.responsable-render').forEach(()=>{});}catch(e){}
    // re-asociar contenido dinámico de responsables en selects ya construidos: nada pendiente
  }catch(e){box.innerHTML='<div class="alert alert-danger">'+esc(e.message)+'</div>';}
}
function renderDetalle(tipo,det){
  const m={pqrs:()=>`<b>Tipo:</b> ${esc(det.tipo_pqrs)}<br><b>Resumen:</b> ${esc(det.resumen)}<br><b>Descripción:</b> ${esc(det.descripcion)}`,
   actualizar_info:()=>{const rows=[];const f=[['nombre','Nombre'],['celular','Celular'],['email','Email'],['direccion','Dirección'],['ciudad','Ciudad']];f.forEach(([k,l])=>{if(det[k])rows.push(`<div><b>${l}:</b> ${esc(det[k])}</div>`);});return rows.join('');},
   redencion:()=>`<table class="table table-sm w-auto mb-0"><tr><td>Bolsillos</td><td>${money(det.val_bolsillos)}</td></tr><tr><td>Incentivos</td><td>${money(det.val_incentivos)}</td></tr><tr><td>Comisiones</td><td>${money(det.val_comisiones)}</td></tr><tr><td><b>Total</b></td><td><b>${money(det.total_redimir)}</b></td></tr><tr><td>Medio</td><td>${esc(det.medio_redencion||'—')}</td></tr></table>`,
   retiro_incentivos:()=>`<table class="table table-sm w-auto mb-0"><tr><td>Incentivos</td><td>${money(det.val_incentivos)}</td></tr><tr><td>Comisiones</td><td>${money(det.val_comisiones)}</td></tr><tr><td><b>Total</b></td><td><b>${money(det.total_retiro)}</b></td></tr><tr><td>Medio</td><td>${esc(det.medio||'—')}</td></tr></table>`,
   ticket:()=>`<b>Categoría:</b> ${catName(det.categoria_id)}<br><b>Resumen:</b> ${esc(det.resumen)}<br><b>Descripción:</b> ${esc(det.descripcion)}<br><b>Vencimiento:</b> ${fmtDt(det.fecha_vencimiento)}`,
   boleta:()=>`<b>Motivo:</b> ${esc(det.motivo||'')} <br> ${det.direccion_entrega?`<b>Entrega:</b> ${esc(det.direccion_entrega)}<br>`:''}`,
   certificado:()=>`<b>Especie:</b> ${esc(det.especie)} <br><b>Año:</b> ${esc(det.anio||'—')}`,
   fondo:()=>`<b>Convenio:</b> ${esc(det.convenio_nombre||det.convenio_id||'—')}<br><b>Opción:</b> ${esc(det.opcion_elegida||'—')}<br><b>Observaciones:</b> ${esc(det.observaciones||'—')}<br><b>Uso:</b> ${fmtDt(det.fecha_uso)}<br><b>Bloqueo hasta:</b> ${fmtDt(det.bloqueado_hasta)}`,
   nuevo_producto:()=>`<b>Producto id:</b> ${esc(det.producto_id)} <br><b>Día pago:</b> ${esc(det.dia_pago||'—')} <br><b>Valor:</b> ${money(det.valor)}`};
  return (m[tipo]||(()=>JSON.stringify(det||{})))();
}
function actFmt(t){(typeof window.actFmt==='function')}
function renderAcciones(s,T){
  let h='';
  if(SEC_ACCIONES||SEC_ASIGNAR||SEC_ADJUNTAR){
    h+=`<div class="card mb-3"><div class="card-header brand"><i class="fas fa-bolt me-2"></i>Acciones</div><div class="card-body" id="accBox">`;
    const btn=(est,label,cls,conf)=>{const extra=conf?` data-conf="${conf}"`:'';
      const isAprob=est==='aprobada'||est==='aceptado';
      return `<button class="btn btn-sm btn-${cls||(isAprob?'success':'outline-primary')} me-1 mb-1" onclick="doTrans('${est}',this)">${label}</button>`;};
    const acts=[];
    if(SEC_ACCIONES){
      T.forEach(tr=>{
        const dest=tr.estado_destino,label=({recibido:'Marcar recibido',asignado:'Asignar',en_curso:'En curso',en_espera:'En espera',resuelto:'Resuelto',aceptado:'Aceptar',rechazado:'Rechazar',aprobada:'Aprobar',cancelado:'Cancelar'})[dest]||dest;
        acts.push(btn(dest,label, null));
      });
    }
    h+=acts.join('')||'<span class="text-muted">Sin acciones disponibles</span>';
    if(SEC_ASIGNAR){
      h+='<hr>';
      h+=`<div class="row g-2 align-items-end"><div class="col-auto"><label class="form-label small text-muted mb-0">Responsable</label><select id="selResp" class="form-select form-select-sm" style="width:220px"><option value="">—</option>${Object.entries(CACHE.responsables).map(([id,n])=>`<option value="${id}">${esc(n)}</option>`).join('')}</select></div><div class="col-auto"><button class="btn btn-sm btn-outline-primary" onclick="doAsignar()"><i class="fas fa-user-clock"></i> Asignar / Reasignar</button></div></div>`;
    }
    if(SEC_ADJUNTAR){
      h+='<hr>';
      h+=`<div class="input-group mb-2"><span class="input-group-text"><i class="fas fa-file-circle-plus"></i></span><input id="filePick" type="file" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-primary" onclick="doAdjuntar()">Adjuntar</button></div>`;
    }
    h+='</div></div>';
    if(SEC_ACCIONES){
      h+=`<div class="modal" id="cmtModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Confirmar transición</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p id="modalMsg"></p><label class="form-label small">Comentario</label><textarea id="modalCom" rows="2" class="form-control" placeholder="Comentario obligatorio si la transición lo exige"></textarea></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" id="modalOk">Confirmar</button></div></div></div></div>`;
    }
  }
  return h;
}
let pendingDest='';
function doTrans(dest,btn){
  pendingDest=dest;
  const msg=({recibido:'Marcar como recibida',asignado:'Asignar responsable',aprobada:'Resolver como APROBADA',rechazada:'Resolver como RECHAZADA'})[dest]||`Cambiar a ''${dest}''`;
  document.getElementById('modalMsg').textContent=msg+'.';
  const modal=document.getElementById('cmtModal');
  const ok=document.getElementById('modalOk');
  const mb=new bootstrap.Modal(modal);
  ok.onclick=()=>{const c=document.getElementById('modalCom').value;mb.hide();runTrans(pendingDest,c);};
  mb.show();
}
async function runTrans(dest,c){
  const task=dest==='recibido'?'solicitudes/marcar_recibido.php':'solicitudes/transicion.php';
  const body={id:ID,estado_destino:dest};if(c)body.comentario=c;
  try{await api(task,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});cargarDet();}catch(e){alert(e.message);}
}
async function doAsignar(){
  const rid=document.getElementById('selResp').value;if(!rid)return alert('Seleccione responsable');
  try{await api('solicitudes/reasignar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:ID,responsable_id:rid})});cargarDet();}catch(e){alert(e.message);}
}
async function doAdjuntar(){
  const f=document.getElementById('filePick').files[0];if(!f)return alert('Elija un archivo');
  const fd=new FormData();fd.append('id',ID);fd.append('file',f);fd.append('contexto','solicitud');
  const r=await fetch(API+'solicitudes/adjuntar.php',{method:'POST',body:fd});const j=await r.json();if(!j.ok){alert(j.error);return;}cargarDet();
}
async function sendCmt(){
  const c=document.getElementById('cmt').value.trim();if(!c)return;
  try{await api('solicitudes/comentar.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:ID,mensaje:c})});cargarDet();}catch(e){alert(e.message);}
}
async function diagFondo(){
  try{
    const d=await api('solicitudes/detalle?id='+ID);
    const det=d.detalle||{};
    if(!det.convenio_id){alert('Sin convenio para diagnosticar.');return;}
    const r=await api('fondo_bienestar/validar?cedula='+encodeURIComponent(d.solicitud.cedula_solicitante)+'&convenio_id='+det.convenio_id);
    alert('Diagnóstico ('+d.solicitud.cedula_solicitante+'):\n'+r.validacion.mensaje+'\nPagos al día: '+(r.pagos.al_dia?'Sí':'No')+', meses: '+r.pagos.pagos+' · Producto asignado: '+(r.tiene_producto?'Sí':'No'));
  }catch(e){alert(e.message);}
}
loadConfig().then(cargarDet);
</script>
<?php else: ?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list-check me-2"></i>Tickets</span>
    <span class="small text-white-50" id="infoTotal"></span>
  </div>
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2 mb-3" id="tipoChips"></div>
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:220px"><label class="form-label small text-muted mb-0">Buscar</label><div class="input-group"><span class="input-group-text"><i class="fas fa-search"></i></span><input class="form-control" id="q" placeholder="Radicado, cédula, nombre…"></div></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Tipo</label><select class="form-select" id="fTipo" style="width:190px"><option value="">Todos</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Estado</label><select class="form-select" id="fEstado" style="width:160px"><option value="">Todos</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Fase</label><select class="form-select" id="fFase" style="width:150px"><option value="">Todas</option><option>CREADA</option><option>EN_GESTION</option><option>PRE_RESUELTA</option><option>RESUELTA_POSITIVA</option><option>RESUELTA_NEGATIVA</option><option>ANULADA</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Responsable</label><select class="form-select" id="fResp" style="width:190px"><option value="">Todos</option></select></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Cédula</label><input class="form-control" id="fCed" style="width:150px" placeholder="Cédula"></div>
      <div class="col-auto"><label class="form-label small text-muted mb-0">Radicado</label><input class="form-control" id="fRad" style="width:150px" placeholder="Radicado"></div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="numero_radicado">Radicado</th>
          <th class="sortable" data-col="tipo_codigo">Tipo</th>
          <th class="sortable" data-col="cedula_solicitante">Cédula</th><th>Solicitante</th>
          <th>Fase · Estado</th><th class="sortable" data-col="responsable_id">Responsable</th>
          <th class="sortable" data-col="fecha_creacion">Fecha</th><th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3"></nav>
  </div>
</div>

<script>
window.API=<?php echo json_encode(apiUrl()); ?>;
const U=<?php echo json_encode(uiBaseUrl()); ?>,PAGE_SIZE=20,L=window.Listado;
const TIPO_INI=<?php echo json_encode($filtroTipo); ?>;
let state={q:'',tipo:TIPO_INI,estado:'',fase:'',resp:'',ced:'',rad:'',page:0,order:'fecha_creacion',dir:'DESC'},debounceTimer;
let CFG={tipos:[],estados:{},responsables:[]};
const esc=L.esc, fmtDt=L.fmtDt, api=L.api;
async function loadCfg(){
  try{CFG=await api('solicitudes/config');
    document.getElementById('fTipo').innerHTML='<option value="">Todos</option>'+CFG.tipos.map(t=>`<option ${state.tipo===t.codigo?'selected':''} value="${t.codigo}">${esc(t.nombre)}</option>`).join('');
    const est=CFG.tipos.flatMap(t=>(CFG.estados[t.codigo]||[]).map(e=>({t:t.codigo,v:e.codigo,n:e.nombre})));
    document.getElementById('fEstado').innerHTML='<option value="">Todos</option>'+est.filter((x,i,a)=>a.findIndex(y=>y.v===x.v)===i).map(x=>`<option value="${x.v}">${esc(x.n)}</option>`).join('');
    document.getElementById('fResp').innerHTML='<option value="">Todos</option>'+CFG.responsables.map(x=>`<option value="${x.id}">${esc(x.nombre_completo)}</option>`).join('');
    renderChips();
    if(state.tipo)buildEstados(state.tipo,true);
  }catch(e){alert(e.message);}
}
function buildEstados(t){
  let opts;
  if(t){
    opts=(CFG.estados[t]||[]).map(e=>`<option value="${e.codigo}">${esc(e.nombre)}</option>`);
  }else{
    const all=CFG.tipos.flatMap(x=>(CFG.estados[x.codigo]||[]).map(e=>({v:e.codigo,n:e.nombre})));
    opts=all.filter((x,i,a)=>a.findIndex(y=>y.v===x.v)===i).map(x=>`<option value="${x.v}">${esc(x.n)}</option>`);
  }
  document.getElementById('fEstado').innerHTML='<option value="">Todos</option>'+opts.join('');
}
function renderChips(){
  const box=document.getElementById('tipoChips');
  if(!box)return;
  const tipos=CFG.tipos||[];
  const items=[{codigo:'',nombre:'Todos'}].concat(tipos);
  box.innerHTML=items.map(t=>{
    const active=state.tipo===t.codigo;
    return `<button type="button" class="btn btn-sm ${active?'btn-brand':'btn-outline-secondary'}" onclick="setTipo('${t.codigo}')">${esc(t.nombre)}</button>`;
  }).join('');
}
function setTipo(t){
  state.tipo=t;state.estado='';
  document.getElementById('fTipo').value=t;
  buildEstados(t);
  renderChips();
  state.page=0;cargar();
}
L.bindSort(document, state, cargar);
function renderSortIcons(){L.renderSortIcons(document, state);}
document.getElementById('q').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.q=document.getElementById('q').value.trim();state.page=0;cargar();},300);});
document.getElementById('fTipo').addEventListener('change',function(){state.tipo=this.value;state.estado='';buildEstados(state.tipo);renderChips();state.page=0;cargar();});
document.getElementById('fEstado').addEventListener('change',function(){state.estado=this.value;state.page=0;cargar();});
document.getElementById('fFase').addEventListener('change',function(){state.fase=this.value;state.page=0;cargar();});
document.getElementById('fResp').addEventListener('change',function(){state.resp=this.value;state.page=0;cargar();});
document.getElementById('fCed').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.ced=document.getElementById('fCed').value.trim();state.page=0;cargar();},300);});
document.getElementById('fRad').addEventListener('input',()=>{clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>{state.rad=document.getElementById('fRad').value.trim();state.page=0;cargar();},300);});
const ESTBADGE={pendiente:['pendiente','secondary'],recibido:['recibido','info'],asignado:['asignado','info'],backlog:['backlog','secondary'],en_curso:['en curso','warning'],en_espera:['en espera','warning'],resuelto:['resuelto','primary'],aceptado:['aceptado','success'],aprobada:['aprobada','success'],rechazada:['rechazada','danger'],rechazado:['rechazado','danger'],cancelado:['cancelado','dark']};
async function cargar(){
  const off=state.page*PAGE_SIZE;
  const sp=new URLSearchParams({limit:PAGE_SIZE,offset:String(off),order:state.order,dir:state.dir});
  if(state.q)sp.set('q',state.q);if(state.tipo)sp.set('tipo',state.tipo);if(state.estado)sp.set('estado',state.estado);if(state.fase)sp.set('fase',state.fase);if(state.resp)sp.set('responsable',state.resp);if(state.ced)sp.set('cedula',state.ced);if(state.rad)sp.set('radicado',state.rad);
  try{const d=await api('solicitudes/listar?'+sp.toString());const total=d.total||0,rows=d.rows||[];
    document.getElementById('infoTotal').textContent=total>0?`Mostrando ${off+1}-${Math.min(off+PAGE_SIZE,total)} de ${total}`:'';
    renderSortIcons();
    const rname=Object.fromEntries(CFG.responsables.map(r=>[r.id,r.nombre_completo]));
    document.getElementById('tbody').innerHTML=rows.map(r=>{const eb=ESTBADGE[r.estado_codigo]||[r.estado_codigo,'secondary'];
      return `<tr>
      <td><span class="badge text-bg-light">${esc(r.numero_radicado)}</span></td>
      <td><span class="badge text-bg-warning">${esc(r.tipo_nombre||r.tipo_codigo)}</span></td>
      <td>${esc(r.cedula_solicitante)}</td><td>${esc(r.solicitante_nombre||'—')}</td>
      <td><span class="badge text-bg-${eb[1]}">${eb[0]}</span></td>
      <td>${esc(r.responsable_nombre||rname[r.responsable_id]||'—')}</td>
      <td><small>${fmtDt(r.fecha_creacion)}</small></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="gestion?id=${r.id}"><i class="fas fa-edit"></i> Gestionar</a></td></tr>`;}).join('')||'<tr><td colspan="8" class="text-center text-muted">Sin solicitudes</td></tr>';
    renderPagination(total);
  }catch(e){alert(e.message);}
}
function renderPagination(t){document.getElementById('paginacion').innerHTML=L.paginacion(t,state.page,PAGE_SIZE);}
function goPage(p){state.page=p;cargar();}
loadCfg().then(()=>cargar());
</script>
<?php endif; ?>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>