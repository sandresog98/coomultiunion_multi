<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('comunicaciones');
$pageTitle = 'Nueva comunicación';
$currentPage = 'comunicaciones_nueva';
$acc = ui_acciones('comunicaciones.nueva', $staff['rol'] ?? '');
$canIA = ($acc['comunicaciones.nueva.ia'] ?? false) === true;
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<style>
  .com-step { border:0; border-bottom:2px solid transparent; background:transparent; padding:.5rem .25rem; color:var(--bs-secondary-color); font-weight:600; }
  .com-step.active { color:var(--brand); border-bottom-color:var(--brand); }
  .com-preview { background:#e5ddd5; border-radius:14px; padding:16px; min-height:150px; }
  .com-bubble { background:#fff; border-radius:10px; padding:10px 12px; max-width:85%; white-space:pre-wrap; box-shadow:0 1px 2px rgba(0,0,0,.12); color:#111827; }
  [data-bs-theme="dark"] .com-preview { background:#111827; border:1px solid #374151; }
  [data-bs-theme="dark"] .com-bubble { background:#1f2937; color:#e5e7eb; }
  .email-preview { background:#fff; color:#111827; border:1px solid #dee2e6; border-radius:10px; overflow:hidden; }
  .email-preview-head { background:#11468A; color:#fff; padding:10px 14px; font-weight:600; font-size:.9rem; }
  .email-preview-subject { padding:10px 14px; border-bottom:1px solid #e5e7eb; font-weight:600; font-size:.9rem; }
  .email-preview-body { padding:14px; min-height:110px; white-space:pre-wrap; font-size:.9rem; }
  .com-step { cursor:default; }
  .com-chip { display:inline-flex; align-items:center; gap:.35rem; background:var(--bs-tertiary-bg); border:1px solid var(--bs-border-color); border-radius:999px; padding:.2rem .55rem; font-size:.8rem; }
  .com-result { cursor:pointer; }
  .com-result:hover { background:var(--bs-tertiary-bg); }
  .com-resumen span { margin-right:.8rem; }
</style>

<div class="card mb-3">
  <div class="card-body py-2 d-flex flex-wrap gap-3">
    <span class="com-step active" id="stepBtn1">1 · Destinatarios</span>
    <span class="com-step" id="stepBtn2">2 · Mensaje</span>
    <span class="com-step" id="stepBtn3">3 · Programación</span>
  </div>
</div>

<div id="alert" class="alert d-none"></div>

<!-- PASO 1 -->
<div id="paso1">
  <div class="card mb-3">
    <div class="card-header brand"><i class="fas fa-users me-2"></i>¿A quién le vamos a enviar?</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Canal</label>
          <select class="form-select" id="fCanal" onchange="cambiarCanal()">
            <option value="whatsapp">WhatsApp</option>
            <option value="sms">SMS</option>
            <option value="email">Email</option>
          </select>
        </div>
        <div class="col-md-3 d-none" id="boxFormatoEmail">
          <label class="form-label">Formato del correo</label>
          <select class="form-select" id="fFormatoEmail" onchange="cambiarFormatoEmail()">
            <option value="texto">Texto plano</option>
            <option value="html">HTML</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Tipo de destinatario</label>
          <div class="d-flex gap-3 pt-2 flex-wrap">
            <div class="form-check"><input class="form-check-input" type="radio" name="tipoDest" id="tipoAsociado" value="asociado" checked onchange="cambiarTipo()"><label class="form-check-label" for="tipoAsociado">Un asociado específico</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="tipoDest" id="tipoVarios" value="varios" onchange="cambiarTipo()"><label class="form-check-label" for="tipoVarios">Varios asociados (con opt-in)</label></div>
            <div class="form-check"><input class="form-check-input" type="radio" name="tipoDest" id="tipoBase" value="base" onchange="cambiarTipo()"><label class="form-check-label" for="tipoBase">Cargar una base (CSV/XLSX)</label></div>
          </div>
        </div>
      </div>

      <div id="boxAsociado" class="mt-3">
        <label class="form-label">Buscar asociado por cédula, nombre, teléfono o correo</label>
        <div class="input-group" style="max-width:520px">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="qAsociado" placeholder="Ej. 12345678 o nombre…" autocomplete="off">
        </div>
        <div id="resAsociados" class="list-group mt-2" style="max-width:720px"></div>
        <div id="asociadoSel" class="mt-3 d-none"></div>
      </div>

      <div id="boxVarios" class="mt-3 d-none">
        <label class="form-label">Buscar y seleccionar asociados</label>
        <div class="input-group" style="max-width:520px">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="qVarios" placeholder="Cédula, nombre, teléfono o correo…" autocomplete="off">
        </div>
        <div class="form-text">Solo se pueden seleccionar asociados con el opt-in de comunicaciones activo. Los demás se muestran bloqueados.</div>
        <div id="resVarios" class="list-group mt-2" style="max-width:760px"></div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <span class="small text-muted" id="variosCount">0 seleccionados</span>
          <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarVarios()">Limpiar selección</button>
        </div>
        <div id="seleccionadosVarios" class="d-flex flex-wrap gap-2 mt-2"></div>
      </div>

      <div id="boxBase" class="mt-3 d-none">
        <div class="row g-2 align-items-end">
          <div class="col-md-7">
            <label class="form-label">Archivo CSV, XLS o XLSX</label>
            <input type="file" class="form-control" id="fArchivo" accept=".csv,.txt,.xls,.xlsx">
          </div>
          <div class="col-md-3">
            <button class="btn btn-outline-primary w-100" id="btnPreview" onclick="previsualizarBase()"><i class="fas fa-eye me-1"></i>Previsualizar</button>
          </div>
        </div>
        <p class="small text-muted mt-2 mb-1">Columnas reconocidas: <code>cedula</code>, <code>telefono</code>, <code>email</code>, <code>nombre</code>, <code>opt_in</code>, <code>opt_out</code>. Las demás columnas quedan disponibles como variables.</p>
        <div id="baseResumen" class="com-resumen small mt-2"></div>
        <div class="table-responsive mt-2" style="max-height:320px;overflow:auto">
          <table class="table table-sm table-hover align-middle d-none" id="tablaBase">
            <thead><tr><th>#</th><th>Destino</th><th>Nombre</th><th>Cédula</th><th>Opt-in</th><th>Estado</th><th>Motivo</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="d-flex justify-content-end mt-3">
    <button type="button" class="btn btn-brand" onclick="siguientePaso(2)">Siguiente: Mensaje <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</div>

<!-- PASO 2 -->
<div id="paso2" class="d-none">
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header brand"><i class="fas fa-comment-dots me-2"></i>Mensaje</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Remitente</label>
              <select class="form-select" id="fRemitente"></select>
              <div class="form-text" id="remitenteHelp"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Modo de contenido</label>
              <select class="form-select" id="fModo" onchange="cambiarModo()">
                <option value="texto">Texto libre</option>
                <option value="plantilla">Plantilla</option>
              </select>
            </div>
            <div class="col-12 d-none" id="boxPlantilla">
              <label class="form-label">Plantilla</label>
              <select class="form-select" id="fPlantilla" onchange="cambiarPlantilla()"></select>
              <div class="form-text" id="plantillaHelp"></div>
            </div>
            <?php if ($canIA): ?>
            <div class="col-12 d-none" id="boxIA">
              <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="validarCoherenciaIA()"><i class="fas fa-list-check me-1"></i>Validar coherencia</button>
                <span class="small text-muted align-self-center" id="iaCostoAcumulado"></span>
              </div>
            </div>
            <?php endif; ?>
            <div class="col-12" id="boxAsunto" style="display:none">
              <label class="form-label">Asunto</label>
              <div class="input-group">
                <input class="form-control" id="fAsunto" maxlength="255" oninput="actualizarPreview()">
                <?php if ($canIA): ?><button type="button" class="btn btn-outline-primary" onclick="abrirIA('asunto')" title="Generar asunto con IA"><i class="fas fa-wand-magic-sparkles"></i></button><?php endif; ?>
              </div>
            </div>
            <div class="col-12" id="boxCuerpo">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0" id="lblCuerpo">Mensaje</label>
                <?php if ($canIA): ?><button type="button" class="btn btn-sm btn-outline-primary" id="btnIAMensaje" onclick="abrirIA('mensaje')"><i class="fas fa-wand-magic-sparkles me-1"></i>Generar con IA</button><?php endif; ?>
              </div>
              <textarea class="form-control mt-1" id="fCuerpo" rows="7" oninput="actualizarPreview()" placeholder="Escriba el mensaje… Puede usar {{nombre}}, {{cedula}}, {{telefono}} y {{email}}."></textarea>
              <div class="form-text"><span id="contador">0</span> caracteres · <span id="segmentos">—</span></div>
            </div>
            <div class="col-12 d-none" id="boxHtml">
              <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0">HTML del correo</label>
                <?php if ($canIA): ?><button type="button" class="btn btn-sm btn-outline-primary" id="btnIAHtml" onclick="abrirIA('html')"><i class="fas fa-code me-1"></i>Generar con IA</button><?php endif; ?>
              </div>
              <textarea class="form-control font-monospace mt-1" id="fHtml" rows="5" oninput="actualizarPreviewHtml()" placeholder="<p>Hola {{nombre}}…</p>"></textarea>
            </div>
            <div class="col-12" id="boxMedia">
              <label class="form-label">Adjuntos (opcional)</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="file" class="form-control" id="fMedia" multiple accept="image/*,.pdf,.mp4,.mp3,.ogg,.opus,.m4a,.doc,.docx,.xls,.xlsx,.pptx,.vcf">
                <button class="btn btn-outline-primary" onclick="subirMedia()" id="btnMedia">Subir</button>
              </div>
              <div class="form-text">WhatsApp: imágenes/PDF hasta 5 MB (imágenes) / 20 MB. Email: adjuntos. SMS en Colombia no admite adjuntos.</div>
              <div id="mediaList" class="d-flex flex-wrap gap-2 mt-2"></div>
            </div>
            <div class="col-12 d-none" id="boxVars">
              <label class="form-label">Variables de la plantilla</label>
              <div class="row g-2" id="varsBox"></div>
              <div class="form-text">Indique de dónde sale cada variable por destinatario.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header brand"><i class="fas fa-eye me-2"></i>Vista previa</div>
        <div class="card-body">
          <div class="com-preview" id="previewBox"><div class="com-bubble text-muted">Escriba el mensaje para ver la vista previa.</div></div>
          <div id="emailPreviewWrap" class="d-none">
            <div class="small fw-semibold text-muted mb-1"><i class="fas fa-envelope me-1"></i>Vista previa del correo</div>
            <div class="email-preview">
              <div class="email-preview-head"><i class="fas fa-envelope me-1"></i>Coomultiunion</div>
              <div class="email-preview-subject" id="emailPreviewSubject">(Sin asunto)</div>
              <div class="email-preview-body" id="emailPreviewBody">Escriba el texto del correo…</div>
              <div id="htmlPreviewWrap" class="d-none p-2">
                <iframe id="htmlPreview" sandbox="allow-same-origin" title="Vista previa HTML" style="width:100%;height:360px;border:1px solid #dee2e6;border-radius:8px;background:#fff;color-scheme:light"></iframe>
              </div>
            </div>
          </div>
          <div id="previewMedia" class="small text-muted mt-2"></div>
        </div>
      </div>
    </div>
  </div>
  <div class="d-flex justify-content-between mt-3">
    <button type="button" class="btn btn-outline-secondary" onclick="irPaso(1)"><i class="fas fa-arrow-left me-1"></i>Anterior</button>
    <button type="button" class="btn btn-brand" onclick="siguientePaso(3)">Siguiente: Programación <i class="fas fa-arrow-right ms-1"></i></button>
  </div>
</div>

<!-- PASO 3 -->
<div id="paso3" class="d-none">
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header brand"><i class="fas fa-calendar-plus me-2"></i>Programación</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nombre del plan</label>
              <div class="input-group">
                <input class="form-control" id="fNombre" maxlength="160" placeholder="Ej. Recordatorio de pago · octubre">
                <?php if ($canIA): ?><button type="button" class="btn btn-outline-primary" onclick="abrirIA('titulo')" title="Generar título con IA"><i class="fas fa-wand-magic-sparkles"></i></button><?php endif; ?>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label">Descripción (opcional)</label>
              <textarea class="form-control" id="fDescripcion" rows="2"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Cuándo enviar</label>
              <div class="d-flex gap-3">
                <div class="form-check"><input class="form-check-input" type="radio" name="modoEnvio" id="modoAhora" value="ahora" checked><label class="form-check-label" for="modoAhora">Ahora</label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="modoEnvio" id="modoFecha" value="programado"><label class="form-check-label" for="modoFecha">Programar</label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="modoEnvio" id="modoBorrador" value="borrador"><label class="form-check-label" for="modoBorrador">Guardar borrador</label></div>
              </div>
            </div>
            <div class="col-md-6 d-none" id="boxFecha">
              <label class="form-label">Fecha y hora (America/Bogota)</label>
              <input type="datetime-local" class="form-control" id="fProgramado">
            </div>
            <div class="col-12" id="resumenFinal"></div>
          </div>
        </div>
        <div class="card-footer d-flex gap-2 justify-content-between">
          <button type="button" class="btn btn-outline-secondary" onclick="irPaso(2)"><i class="fas fa-arrow-left me-1"></i>Anterior</button>
          <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/planes">Cancelar</a>
            <button class="btn btn-brand" id="btnGuardar" onclick="guardar()"><i class="fas fa-paper-plane me-1"></i><span id="btnGuardarTxt">Enviar</span></button>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header brand"><i class="fas fa-circle-check me-2"></i>Resumen</div>
        <div class="card-body small" id="resumenLateral"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal validación IA -->
<div class="modal fade" id="mValidar" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-list-check me-2"></i>Validación de coherencia</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body" id="validarBody"></div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
</div></div></div>

<!-- Modal IA -->
<div class="modal fade" id="mIA" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-wand-magic-sparkles me-2"></i>Ayuda para redactar</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="alert alert-info small">La IA propone un borrador. Revísalo antes de enviarlo: no inventa datos, pero debes validar el contenido y las variables.</div>
    <div class="mb-2"><label class="form-label">¿Qué debe comunicar?</label><textarea class="form-control" id="iaObjetivo" rows="4" maxlength="1200" placeholder="Ej. Recordar el pago de la cuota del crédito, indicar el canal de atención y agradecer…"></textarea></div>
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label">Tono</label><select class="form-select" id="iaTono"><option value="institucional">Institucional</option><option value="cercano">Cercano</option><option value="formal">Formal</option><option value="motivador">Motivador</option></select></div>
      <div class="col-md-6"><label class="form-label">Extensión</label><select class="form-select" id="iaLargo"><option value="breve">Breve</option><option value="medio" selected>Media</option><option value="amplio">Amplia</option></select></div>
    </div>
    <div class="form-text mt-2" id="iaHelp"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" id="btnIAGenerar" onclick="generarIA()"><i class="fas fa-wand-magic-sparkles me-1"></i>Generar</button></div>
</div></div></div>

<script>
window.API=<?= json_encode(apiUrl()) ?>;
const U=<?= json_encode(uiBaseUrl()) ?>;
const L=window.Listado, esc=L.esc;
const ACC=<?= json_encode($acc, JSON_UNESCAPED_UNICODE) ?>;
let state={canal:'whatsapp',tipo:'asociado',asociado:null,contacto:null,seleccionados:[],file:null,preview:null,media:[],plantilla:null,plantillas:[],remitentes:[],ia:{costo_usd:0,modelo:'',tokens_prompt:0,tokens_completion:0,generaciones:0}};
let debounce, mIA=null, mValidar=null, iaTipo='mensaje';
function showAlert(msg,ok){const a=document.getElementById('alert');a.className='alert '+(ok?'alert-success':'alert-danger');a.textContent=msg;a.classList.remove('d-none');a.scrollIntoView({behavior:'smooth',block:'center'});}
async function api(p,o){return L.api(p,o);}
function post(path,data){return api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}
function validarFase1(mostrar){
  const fail=(m)=>{if(mostrar)showAlert(m,false);return false;};
  if(state.tipo==='asociado'){
    if(!state.asociado)return fail('Seleccione un asociado.');
    const destino=state.canal==='email'?state.asociado.email:state.asociado.telefono;
    if(!destino)return fail('El asociado no tiene '+(state.canal==='email'?'correo':'teléfono')+' para este canal.');
    if(state.contacto&&parseInt(state.contacto.opt_out,10)===1)return fail('El contacto está en opt-out.');
    if(!state.optIn)return fail('Debe confirmar el opt-in del asociado.');
  }else if(state.tipo==='varios'){
    if(!state.seleccionados.length)return fail('Seleccione al menos un asociado con opt-in activo.');
    if(state.seleccionados.some(a=>parseInt(a.opt_in||0,10)!==1))return fail('Hay asociados sin opt-in en la selección.');
    if(state.seleccionados.some(a=>state.canal==='email'?!a.email:!a.telefono))return fail('Hay asociados sin '+(state.canal==='email'?'correo':'teléfono')+' para el canal seleccionado.');
  }else{
    if(!state.file)return fail('Cargue una base de destinatarios.');
    if(!state.preview||!(state.preview.resumen.ok>0))return fail('La base no tiene destinatarios válidos.');
  }
  return true;
}
function validarFase2(mostrar){
  const fail=(m)=>{if(mostrar)showAlert(m,false);return false;};
  const rem=document.getElementById('fRemitente');
  if(!rem||!rem.value)return fail('Seleccione un remitente activo.');
  const modo=document.getElementById('fModo').value;
  const cuerpo=document.getElementById('fCuerpo').value.trim();
  const html=document.getElementById('fHtml').value.trim();
  if(modo==='plantilla'){
    if(!state.plantilla)return fail('Seleccione una plantilla aprobada.');
    if(state.canal==='email'&&!document.getElementById('fAsunto').value.trim())return fail('Indique el asunto del correo.');
  }else if(state.canal==='email'){
    if(!document.getElementById('fAsunto').value.trim())return fail('Indique el asunto del correo.');
    const fmt=document.getElementById('fFormatoEmail').value;
    if(fmt==='html'&&!html)return fail('Escriba o genere el HTML del correo.');
    if(fmt==='texto'&&!cuerpo)return fail('Escriba el mensaje del correo.');
  }else if(!cuerpo){
    return fail('Escriba el mensaje.');
  }
  return true;
}
function irPaso(n){
  if(n>1&&!validarFase1(true))return;
  if(n>2&&!validarFase2(true))return;
  [1,2,3].forEach(i=>{document.getElementById('paso'+i).classList.toggle('d-none',i!==n);document.getElementById('stepBtn'+i).classList.toggle('active',i===n);});
  if(n===3){actualizarResumen();}
  window.scrollTo({top:0,behavior:'smooth'});
}
function siguientePaso(n){ irPaso(n); }
function cambiarTipo(){
  state.tipo=document.querySelector('input[name="tipoDest"]:checked').value;
  document.getElementById('boxAsociado').classList.toggle('d-none',state.tipo!=='asociado');
  document.getElementById('boxVarios').classList.toggle('d-none',state.tipo!=='varios');
  document.getElementById('boxBase').classList.toggle('d-none',state.tipo!=='base');
  actualizarResumen();
}
async function cambiarCanal(){
  state.canal=document.getElementById('fCanal').value;
  state.asociado=null;state.contacto=null;state.file=null;state.preview=null;state.plantilla=null;state.media=[];
  document.getElementById('asociadoSel').classList.add('d-none');
  document.getElementById('tablaBase').classList.add('d-none');
  document.getElementById('baseResumen').innerHTML='';
  document.getElementById('mediaList').innerHTML='';
  document.getElementById('fModo').value='texto';
  document.getElementById('fPlantilla').value='';
  document.getElementById('fCuerpo').readOnly=false;
  document.getElementById('boxMedia').classList.toggle('d-none',state.canal==='sms');
  const boxIA=document.getElementById('boxIA');
  if(boxIA) boxIA.classList.remove('d-none');
  document.getElementById('fFormatoEmail').value='texto';
  cambiarFormatoEmail();
  await cargarRemitentes();
  await cargarPlantillas();
  cambiarModo();actualizarPreview();actualizarResumen();
  renderSeleccionados();
  if(document.getElementById('qVarios').value.trim().length>=2) buscarVarios();
}
async function cargarRemitentes(){
  try{
    state.remitentes=await api('comunicaciones/remitentes?canal='+encodeURIComponent(state.canal));
    const sel=document.getElementById('fRemitente');
    sel.innerHTML=(state.remitentes||[]).map(r=>`<option value="${r.id}" ${r.predeterminado==1?'selected':''}>${esc(r.etiqueta)} · ${esc(r.direccion)}</option>`).join('') || '<option value="">Sin remitente configurado</option>';
    document.getElementById('remitenteHelp').textContent=(state.remitentes&&state.remitentes.length)?'':'No hay remitente configurado. Puede guardar el plan como borrador y configurarlo después.';
  }catch(e){showAlert(e.message,false);}
}
async function cargarPlantillas(){
  try{
    state.plantillas=await api('comunicaciones/plantillas?canal='+encodeURIComponent(state.canal)+'&aprobadas=1');
    const sel=document.getElementById('fPlantilla');
    sel.innerHTML='<option value="">Seleccione una plantilla…</option>'+(state.plantillas||[]).map(p=>`<option value="${p.id}">${esc(p.nombre)}${p.categoria?' · '+esc(p.categoria):''}</option>`).join('');
    document.getElementById('boxPlantilla').classList.toggle('d-none',!state.plantillas.length);
    if(!state.plantillas.length && document.getElementById('fModo').value==='plantilla'){document.getElementById('fModo').value='texto';}
  }catch(e){showAlert(e.message,false);}
}
function cambiarFormatoEmail(){
  const email=state.canal==='email';
  const fmt=document.getElementById('fFormatoEmail').value;
  const plantilla=document.getElementById('fModo').value==='plantilla';
  document.getElementById('boxFormatoEmail').classList.toggle('d-none',!email);
  document.getElementById('boxAsunto').style.display=email?'':'none';
  document.getElementById('boxCuerpo').classList.toggle('d-none',plantilla||(email&&fmt==='html'));
  document.getElementById('boxHtml').classList.toggle('d-none',plantilla||!email||fmt!=='html');
  document.getElementById('boxHtml').style.display='';
  const msgBtn=document.getElementById('btnIAMensaje');
  const htmlBtn=document.getElementById('btnIAHtml');
  if(msgBtn) msgBtn.style.display=plantilla||(email&&fmt==='html')?'none':'';
  if(htmlBtn) htmlBtn.style.display=plantilla||(email&&fmt!=='html')?'none':'';
  const boxIA=document.getElementById('boxIA');
  if(boxIA) boxIA.classList.toggle('d-none',plantilla);
  document.getElementById('lblCuerpo').textContent=email?'Texto plano':'Mensaje';
  actualizarPreview();
}
function cambiarModo(){
  const modo=document.getElementById('fModo').value;
  document.getElementById('boxPlantilla').classList.toggle('d-none',modo!=='plantilla');
  const plantilla=state.plantilla;
  if(modo==='plantilla' && plantilla){
    document.getElementById('fAsunto').value=plantilla.asunto||'';
    document.getElementById('fCuerpo').value=plantilla.cuerpo||'';
    document.getElementById('fHtml').value=plantilla.cuerpo_html||'';
  }
  document.getElementById('boxVars').classList.toggle('d-none',modo!=='plantilla' || !plantilla || !(plantilla.variables||[]).length);
  cambiarFormatoEmail();
  actualizarPreview();actualizarResumen();
}
function cambiarPlantilla(){
  const id=parseInt(document.getElementById('fPlantilla').value||'0',10);
  state.plantilla=(state.plantillas||[]).find(p=>parseInt(p.id,10)===id)||null;
  if(state.plantilla){
    document.getElementById('fAsunto').value=state.plantilla.asunto||'';
    document.getElementById('fCuerpo').value=state.plantilla.cuerpo||'';
    document.getElementById('fHtml').value=state.plantilla.cuerpo_html||'';
    document.getElementById('fCuerpo').readOnly=true;
    document.getElementById('plantillaHelp').textContent='Plantilla aprobada. Las variables se reemplazan por destinatario.';
  }else{
    document.getElementById('fCuerpo').readOnly=false;
    document.getElementById('plantillaHelp').textContent='';
  }
  renderVars();cambiarModo();
}
function renderVars(){
  const box=document.getElementById('varsBox');
  const vars=(state.plantilla&&state.plantilla.variables)||[];
  if(!vars.length){box.innerHTML='';return;}
  const campos=['nombre','cedula','telefono','email'];
  box.innerHTML=vars.map(v=>{
    const def=campos.includes(String(v))?String(v):'nombre';
    return `<div class="col-md-6"><label class="form-label small">Variable {{${esc(v)}}}</label>
    <select class="form-select form-select-sm" data-var="${esc(v)}">
      <option value="nombre" ${def==='nombre'?'selected':''}>Nombre del asociado</option>
      <option value="cedula" ${def==='cedula'?'selected':''}>Cédula</option>
      <option value="telefono" ${def==='telefono'?'selected':''}>Teléfono</option>
      <option value="email" ${def==='email'?'selected':''}>Correo</option>
    </select></div>`;
  }).join('');
}
document.getElementById('qAsociado').addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(buscarAsociado,300);});
document.getElementById('qVarios').addEventListener('input',()=>{clearTimeout(debounce);debounce=setTimeout(buscarVarios,300);});
async function buscarAsociado(){
  const q=document.getElementById('qAsociado').value.trim();
  const box=document.getElementById('resAsociados');
  if(q.length<2){box.innerHTML='';return;}
  try{
    const d=await api('comunicaciones/buscar?q='+encodeURIComponent(q));
    const rows=d.asociados||[];
    box.innerHTML=rows.map(a=>`<button type="button" class="list-group-item list-group-item-action com-result" data-a="${encodeURIComponent(JSON.stringify(a))}" onclick="seleccionarAsociado(JSON.parse(decodeURIComponent(this.dataset.a)))">
      <div class="d-flex justify-content-between"><span class="fw-semibold">${esc(a.nombre)}</span><span class="small text-muted">${esc(a.cedula)}</span></div>
      <div class="small text-muted">${esc(a.telefono||'Sin teléfono')} ${a.email?'· '+esc(a.email):''}</div>
    </button>`).join('') || '<div class="text-muted small p-2">Sin resultados</div>';
  }catch(e){showAlert(e.message,false);}
}
async function seleccionarAsociado(a){
  state.asociado=a;state.contacto=null;state.optIn=false;
  document.getElementById('resAsociados').innerHTML='';
  const destino=state.canal==='email'?(a.email||''):(a.telefono||'');
  let contacto=null;
  if(destino){
    try{const d=await api('comunicaciones/contactos?canal='+encodeURIComponent(state.canal)+'&direccion='+encodeURIComponent(destino));contacto=d.contacto;}catch(e){}
  }
  state.contacto=contacto;
  const masterOptIn=parseInt(a.opt_in||0,10)===1;
  const tieneOptIn=masterOptIn || !!(contacto&&parseInt(contacto.opt_in,10)===1);
  const optOut=!!(contacto&&parseInt(contacto.opt_out,10)===1);
  state.optIn=tieneOptIn;
  const box=document.getElementById('asociadoSel');
  box.classList.remove('d-none');
  box.innerHTML=`<div class="border rounded p-3">
    <div class="d-flex justify-content-between gap-2 flex-wrap">
      <div><div class="fw-semibold">${esc(a.nombre)}</div><div class="small text-muted">Cédula ${esc(a.cedula)} · ${esc(destino||'Sin dato para este canal')}</div></div>
      <div class="text-end">${tieneOptIn?'<span class="badge text-bg-success">Opt-in registrado</span>':optOut?'<span class="badge text-bg-danger">Opt-out</span>':'<span class="badge text-bg-warning">Sin opt-in</span>'}</div>
    </div>
    ${optOut?'<div class="alert alert-danger small mt-2 mb-0">Este contacto está dado de baja para el canal. No se puede enviar.</div>':''}
    ${!tieneOptIn&&!optOut?`<div class="form-check mt-2"><input class="form-check-input" type="checkbox" id="chkOptIn" ${state.optIn?'checked':''} onchange="state.optIn=this.checked"><label class="form-check-label small" for="chkOptIn">Confirmo que el asociado autorizó recibir esta comunicación (opt-in).</label></div>`:''}
    ${!destino?'<div class="alert alert-warning small mt-2 mb-0">El asociado no tiene '+(state.canal==='email'?'correo':'teléfono')+' válido para este canal.</div>':''}
    ${destino&&!optOut?`<button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="bajaContacto()"><i class="fas fa-ban me-1"></i>Registrar baja</button>`:''}
  </div>`;
  actualizarPreview();actualizarResumen();
}
async function buscarVarios(){
  const q=document.getElementById('qVarios').value.trim();
  const box=document.getElementById('resVarios');
  if(q.length<2){box.innerHTML='';return;}
  try{
    const d=await api('comunicaciones/buscar?q='+encodeURIComponent(q));
    const rows=d.asociados||[];
    box.innerHTML=rows.map(a=>{
      const opt=parseInt(a.opt_in||0,10)===1;
      const destinoOk=state.canal==='email'?!!a.email:!!a.telefono;
      const selectable=opt&&destinoOk;
      const checked=state.seleccionados.some(x=>String(x.cedula)===String(a.cedula));
      const badge=!opt?'<span class="badge text-bg-warning">Opt-in desactivado</span>':(!destinoOk?'<span class="badge text-bg-secondary">Sin '+(state.canal==='email'?'correo':'teléfono')+'</span>':'<span class="badge text-bg-success">Opt-in activo</span>');
      return `<label class="list-group-item list-group-item-action d-flex align-items-start gap-2 ${selectable?'':'opacity-75'}" data-a="${encodeURIComponent(JSON.stringify(a))}">
        <input class="form-check-input mt-1" type="checkbox" ${selectable?'':'disabled'} ${checked?'checked':''} onchange="toggleVarios(this)">
        <span class="flex-grow-1">
          <span class="d-flex justify-content-between gap-2"><span class="fw-semibold">${esc(a.nombre)}</span><span class="small text-muted">${esc(a.cedula)}</span></span>
          <span class="small text-muted d-block">${esc(a.telefono||'Sin teléfono')} ${a.email?'· '+esc(a.email):''}</span>
        </span>
        ${badge}
      </label>`;
    }).join('') || '<div class="text-muted small p-2">Sin resultados</div>';
  }catch(e){showAlert(e.message,false);}
}
function toggleVarios(el){
  const label=el.closest('label');
  let a={};
  try{a=JSON.parse(decodeURIComponent(label.dataset.a));}catch(e){return;}
  if(parseInt(a.opt_in||0,10)!==1){el.checked=false;showAlert('Este asociado no tiene opt-in activo. Actívelo en el detalle del asociado para poder incluirlo.',false);return;}
  if(state.canal==='email' && !a.email){el.checked=false;showAlert('El asociado no tiene correo válido.',false);return;}
  if(state.canal!=='email' && !a.telefono){el.checked=false;showAlert('El asociado no tiene teléfono válido.',false);return;}
  const i=state.seleccionados.findIndex(x=>String(x.cedula)===String(a.cedula));
  if(el.checked && i<0) state.seleccionados.push(a);
  if(!el.checked && i>=0) state.seleccionados.splice(i,1);
  renderSeleccionados();actualizarResumen();
}
function renderSeleccionados(){
  document.getElementById('variosCount').textContent=state.seleccionados.length+' seleccionado(s)';
  document.getElementById('seleccionadosVarios').innerHTML=state.seleccionados.map(a=>`<span class="com-chip"><i class="fas fa-user-check"></i>${esc(a.nombre)} <button type="button" class="btn btn-sm btn-link p-0 text-danger" onclick="quitarVarios('${esc(a.cedula)}')">&times;</button></span>`).join('');
}
function quitarVarios(cedula){
  state.seleccionados=state.seleccionados.filter(x=>String(x.cedula)!==String(cedula));
  renderSeleccionados();actualizarResumen();buscarVarios();
}
function limpiarVarios(){
  state.seleccionados=[];renderSeleccionados();actualizarResumen();buscarVarios();
}
async function bajaContacto(){
  if(!state.asociado)return;
  const destino=state.canal==='email'?state.asociado.email:state.asociado.telefono;
  if(!destino)return;
  if(!confirm('¿Registrar la baja de este contacto para '+state.canal+'? No se le podrán enviar más comunicaciones.'))return;
  try{
    await api('comunicaciones/contactos',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({canal:state.canal,direccion:destino,opt_out:1,motivo:'Solicitud del asociado'})});
    state.optIn=false;
    if(state.contacto){state.contacto.opt_out=1;state.contacto.opt_in=0;}
    showAlert('Baja registrada para '+destino+'.',true);
    await seleccionarAsociado(state.asociado);
  }catch(e){showAlert(e.message,false);}
}
function fileName(){return state.file?state.file.name:'';}
async function previsualizarBase(){
  const f=document.getElementById('fArchivo').files[0];
  if(!f){showAlert('Seleccione un archivo.',false);return;}
  const fd=new FormData();fd.append('archivo',f);fd.append('canal',state.canal);
  try{
    document.getElementById('btnPreview').disabled=true;
    const d=await api('comunicaciones/base_preview',{method:'POST',body:fd});
    state.file=f;state.preview=d;
    const r=d.resumen||{};
    document.getElementById('baseResumen').innerHTML=`<span class="badge text-bg-success">${r.ok||0} válidos</span> <span class="badge text-bg-warning">${r.omitido||0} omitidos</span> <span class="badge text-bg-danger">${r.error||0} errores</span> <span class="badge text-bg-secondary">${r.duplicado||0} duplicados</span> <span class="badge text-bg-warning">${r.sin_opt_in||0} sin opt-in</span>`;
    const tb=document.querySelector('#tablaBase tbody');
    tb.innerHTML=(d.rows||[]).map(x=>`<tr class="${x.estado==='error'?'table-danger':x.estado==='omitido'?'table-warning':''}">
      <td>${x.n}</td><td>${esc(x.destino||'')}</td><td>${esc(x.nombre||'')}</td><td>${esc(x.cedula||'')}</td>
      <td>${x.opt_in?'Sí':'No'}</td><td>${esc(x.estado)}</td><td class="small">${esc(x.motivo||'')}</td></tr>`).join('');
    document.getElementById('tablaBase').classList.remove('d-none');
    if(!(r.ok>0)) showAlert('Ningún registro es válido para envío. Revise opt-in y datos.',false);
  }catch(e){showAlert(e.message,false);}
  finally{document.getElementById('btnPreview').disabled=false;}
}
async function subirMedia(){
  const files=Array.from(document.getElementById('fMedia').files||[]);
  if(!files.length)return;
  if(state.media.length+files.length>10){showAlert('Máximo 10 adjuntos por mensaje.',false);return;}
  const btn=document.getElementById('btnMedia');btn.disabled=true;
  try{
    for(const f of files){
      const fd=new FormData();fd.append('archivo',f);fd.append('canal',state.canal);
      const d=await api('comunicaciones/media',{method:'POST',body:fd});
      state.media.push(d);
    }
    renderMedia();actualizarPreview();
  }catch(e){showAlert(e.message,false);}
  finally{btn.disabled=false;document.getElementById('fMedia').value='';}
}
function renderMedia(){
  document.getElementById('mediaList').innerHTML=state.media.map((m,i)=>`<span class="com-chip"><i class="fas ${String(m.mime||'').startsWith('image/')?'fa-image':'fa-paperclip'}"></i>${esc(m.nombre_original)} <button class="btn btn-sm btn-link p-0 text-danger" onclick="quitarMedia(${i})">&times;</button></span>`).join('');
}
function quitarMedia(i){state.media.splice(i,1);renderMedia();actualizarPreview();}
function actualizarPreview(){
  const body=document.getElementById('fCuerpo').value||'';
  const len=body.length;
  document.getElementById('contador').textContent=len;
  document.getElementById('segmentos').textContent=state.canal==='sms'?('≈ '+Math.max(1,Math.ceil(len/160))+' segmento(s)'):'';
  const texto=body||(state.plantilla?state.plantilla.cuerpo:'');
  const ref=state.asociado||state.seleccionados[0]||null;
  const nombre=ref?ref.nombre:'Nombre';
  const cedula=ref?ref.cedula:'Cédula';
  const muestra=(texto||'Escriba el mensaje para ver la vista previa.').replace(/\{\{\s*nombre\s*\}\}/gi,nombre).replace(/\{\{\s*cedula\s*\}\}/gi,cedula);
  const email=state.canal==='email';
  document.getElementById('previewBox').classList.toggle('d-none',email);
  document.getElementById('emailPreviewWrap').classList.toggle('d-none',!email);
  if(email){
    document.getElementById('emailPreviewSubject').textContent=document.getElementById('fAsunto').value.trim()||'(Sin asunto)';
    const fmt=document.getElementById('fFormatoEmail').value;
    const eb=document.getElementById('emailPreviewBody');
    const rawHtml=document.getElementById('fHtml').value.trim();
    if(fmt==='html'&&!rawHtml){
      eb.textContent='Escriba o genere el HTML del correo…';
      eb.classList.remove('d-none');
    }else{
      eb.textContent=muestra;
      eb.classList.toggle('d-none',fmt==='html');
    }
  }else{
    document.getElementById('previewBox').innerHTML=`<div class="com-bubble">${esc(muestra)}</div>`;
  }
  document.getElementById('previewMedia').textContent=state.media.length?('Adjuntos: '+state.media.map(m=>m.nombre_original).join(', ')):'';
  actualizarPreviewHtml();
}
function actualizarPreviewHtml(){
  const wrap=document.getElementById('htmlPreviewWrap');
  const frame=document.getElementById('htmlPreview');
  if(!wrap||!frame)return;
  const raw=document.getElementById('fHtml').value||'';
  const fmt=document.getElementById('fFormatoEmail').value;
  if(state.canal!=='email'||fmt!=='html'||!raw.trim()){wrap.classList.add('d-none');frame.srcdoc='';return;}
  const ref=state.asociado||state.seleccionados[0]||null;
  const nombre=ref?ref.nombre:'Nombre';
  const cedula=ref?ref.cedula:'Cédula';
  let texto=raw.replace(/\{\{\s*nombre\s*\}\}/gi,nombre).replace(/\{\{\s*cedula\s*\}\}/gi,cedula);
  // Defensa adicional: el iframe no permite scripts, pero evitamos que se
  // intente ejecutar contenido activo al previsualizar HTML pegado manualmente.
  texto=texto.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'')
             .replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi,'')
             .replace(/javascript\s*:/gi,'');
  const abre=(texto.match(/</g)||[]).length;
  const cierra=(texto.match(/>/g)||[]).length;
  if(abre!==cierra){
    frame.srcdoc='<!doctype html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;padding:16px;color:#b02a37"><h4>HTML incompleto</h4><p>El HTML tiene etiquetas sin cerrar. Corrígelo o vuelve a generarlo con IA.</p></body></html>';
  }else if(/<html|<body/i.test(texto)){
    frame.srcdoc=texto;
  }else{
    frame.srcdoc='<!doctype html><html><head><meta charset="utf-8"><style>'
      +'html,body{color-scheme:light}body{margin:0;padding:18px;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827}'
      +'.card{max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.06)}'
      +'.header{background:linear-gradient(135deg,#11458B,#1384D2);color:#fff;padding:20px;text-align:center;font-size:18px;font-weight:700}'
      +'.content{padding:20px 24px 26px;font-size:14px;line-height:1.5}'
      +'.footer{padding:14px 24px;border-top:1px solid #eef2f7;background:#fafbff;color:#6b7280;font-size:12px}'
      +'</style></head><body><div class="card"><div class="header">Comunicación Coomultiunion</div><div class="content">'+texto+'</div><div class="footer">No responder a este email. Enviado automáticamente por el sistema.</div></div></body></html>';
  }
  wrap.classList.remove('d-none');
}
function actualizarResumen(){
  const n=state.tipo==='asociado'?(state.asociado?1:0):state.tipo==='varios'?state.seleccionados.length:(state.preview&&state.preview.resumen?state.preview.resumen.ok:0);
  const rem=document.getElementById('fRemitente');
  const remTxt=rem&&rem.selectedIndex>=0?rem.options[rem.selectedIndex].text:'—';
  const html=`
    <div class="d-flex justify-content-between"><span>Canal</span><strong>${esc(state.canal)}</strong></div>
    <div class="d-flex justify-content-between"><span>Destinatarios válidos</span><strong>${n}</strong></div>
    <div class="d-flex justify-content-between"><span>Remitente</span><strong class="text-end">${esc(remTxt)}</strong></div>
    <div class="d-flex justify-content-between"><span>Contenido</span><strong>${state.media.length?'Texto + '+state.media.length+' adjunto(s)':'Texto'}</strong></div>
    <div class="d-flex justify-content-between"><span>Costo envío</span><strong>Se calcula al guardar</strong></div>
    <div class="d-flex justify-content-between"><span>Costo IA</span><strong>${state.ia.generaciones?usd(state.ia.costo_usd):'—'}</strong></div>
    <div class="d-flex justify-content-between"><span>Moneda</span><strong>USD</strong></div>`;
  document.getElementById('resumenLateral').innerHTML=html;
  document.getElementById('resumenFinal').innerHTML=`<div class="border rounded p-2 small">${html}</div>`;
}
document.querySelectorAll('input[name="modoEnvio"]').forEach(r=>r.addEventListener('change',()=>{
  document.getElementById('boxFecha').classList.toggle('d-none',document.querySelector('input[name="modoEnvio"]:checked').value!=='programado');
  const txt=document.getElementById('btnGuardarTxt');
  const v=document.querySelector('input[name="modoEnvio"]:checked').value;
  txt.textContent=v==='ahora'?'Enviar':v==='programado'?'Programar':'Guardar borrador';
}));
function abrirIA(tipo){
  if(document.getElementById('fModo').value==='plantilla'){showAlert('La IA se usa con contenido de texto libre; seleccione "Texto libre".',false);return;}
  iaTipo=tipo;
  mIA=mIA||new bootstrap.Modal(document.getElementById('mIA'));
  document.getElementById('iaObjetivo').value='';
  document.getElementById('iaHelp').textContent=tipo==='html'
    ? 'Se generará un fragmento HTML con estilos inline para el correo. No incluye <script>.'
    : (tipo==='asunto'
      ? 'Se generará una línea corta para el asunto del correo.'
      : (tipo==='titulo'
        ? 'Se generará un título breve para identificar el plan.'
        : 'Se generará el texto del mensaje. Puede usar {{nombre}}, {{cedula}}, {{telefono}} y {{email}}.'));
  mIA.show();
}
function actualizarCostoIA(){
  const el=document.getElementById('iaCostoAcumulado');
  if(!el)return;
  if(!state.ia.generaciones){el.textContent='';return;}
  el.textContent='IA: '+usd(state.ia.costo_usd)+' · '+state.ia.generaciones+' generación(es)';
}
function usd(v){return '$'+(Number(v||0)).toLocaleString('en-US',{minimumFractionDigits:4,maximumFractionDigits:4})+' USD';}
async function generarIA(){
  const objetivo=document.getElementById('iaObjetivo').value.trim();
  if(!objetivo){showAlert('Indique qué debe comunicar el mensaje.',false);return;}
  const btn=document.getElementById('btnIAGenerar');btn.disabled=true;
  try{
    const d=await post('comunicaciones/ia_generar',{
      tipo:iaTipo,
      canal:state.canal,
      objetivo,
      tono:document.getElementById('iaTono').value,
      largo:document.getElementById('iaLargo').value,
      contexto:iaTipo==='html'?document.getElementById('fHtml').value:document.getElementById('fCuerpo').value,
    });
    if(iaTipo==='html'){
      document.getElementById('fHtml').value=d.texto;
      document.getElementById('fHtml').focus();
    }else if(iaTipo==='asunto'){
      document.getElementById('fAsunto').value=d.texto;
      document.getElementById('fAsunto').focus();
    }else if(iaTipo==='titulo'){
      document.getElementById('fNombre').value=d.texto;
      document.getElementById('fNombre').focus();
    }else{
      document.getElementById('fCuerpo').value=d.texto;
      document.getElementById('fCuerpo').focus();
    }
    acumularIA(d);
    mIA.hide();
    actualizarPreview();
    showAlert('Borrador generado con IA. Revísalo antes de enviar.',true);
  }catch(e){showAlert(e.message,false);}
  finally{btn.disabled=false;}
}
function acumularIA(d){
  state.ia.costo_usd += Number(d.costo_usd||0);
  state.ia.tokens_prompt += Number(d.tokens_prompt||0);
  state.ia.tokens_completion += Number(d.tokens_completion||0);
  state.ia.generaciones += 1;
  state.ia.modelo = d.modelo || state.ia.modelo;
  actualizarCostoIA();
  actualizarResumen();
}
function datosBorrador(){
  const esEmail=state.canal==='email';
  const fmt=document.getElementById('fFormatoEmail').value;
  const n=state.tipo==='asociado'?(state.asociado?1:0):state.tipo==='varios'?state.seleccionados.length:(state.preview&&state.preview.resumen?Number(state.preview.resumen.ok||0):0);
  return {
    canal:state.canal,
    formato:esEmail?fmt:'texto',
    asunto:document.getElementById('fAsunto').value.trim(),
    cuerpo:esEmail&&fmt==='html'?'':document.getElementById('fCuerpo').value,
    cuerpo_html:esEmail&&fmt==='html'?document.getElementById('fHtml').value:'',
    adjuntos:state.media.map(m=>({nombre:m.nombre_original,mime:m.mime})),
    destinatarios:n,
  };
}
async function validarCoherenciaIA(){
  mValidar=mValidar||new bootstrap.Modal(document.getElementById('mValidar'));
  document.getElementById('validarBody').innerHTML='<div class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Revisando con IA…</div>';
  mValidar.show();
  try{
    const d=await post('comunicaciones/ia_validar',datosBorrador());
    acumularIA(d);
    const probs=(d.problemas||[]).map(x=>'<li>'+esc(x)+'</li>').join('');
    const sugs=(d.sugerencias||[]).map(x=>'<li>'+esc(x)+'</li>').join('');
    document.getElementById('validarBody').innerHTML=`
      <div class="alert ${d.ok?'alert-success':'alert-warning'} mb-2"><strong>${d.ok?'Sin observaciones críticas':'Hay observaciones'}</strong> ${esc(d.resumen||'')}</div>
      ${probs?`<div class="fw-semibold small text-danger">Problemas</div><ul class="small">${probs}</ul>`:''}
      ${sugs?`<div class="fw-semibold small text-primary">Sugerencias</div><ul class="small">${sugs}</ul>`:''}
      <div class="small text-muted">Costo de esta validación: ${usd(d.costo_usd)} · modelo ${esc(d.modelo||'')}</div>`;
  }catch(e){
    document.getElementById('validarBody').innerHTML='<div class="alert alert-danger mb-0">'+esc(e.message)+'</div>';
  }
}
function variablesMap(){
  const out={};
  document.querySelectorAll('#varsBox [data-var]').forEach(sel=>{out[sel.dataset.var]=sel.value;});
  return out;
}
function validarAntes(){
  if(!validarFase1(true)||!validarFase2(true))return false;
  if(!document.getElementById('fNombre').value.trim()){showAlert('Indique un nombre para el plan.',false);return false;}
  if(document.querySelector('input[name="modoEnvio"]:checked').value==='programado'&&!document.getElementById('fProgramado').value){showAlert('Indique fecha y hora.',false);return false;}
  return true;
}
async function guardar(){
  if(!validarAntes())return;
  const modoEnvio=document.querySelector('input[name="modoEnvio"]:checked').value;
  const data={
    nombre:document.getElementById('fNombre').value.trim(),
    descripcion:document.getElementById('fDescripcion').value.trim(),
    canal:state.canal,
    tipo:state.tipo==='asociado'?'individual':'masiva',
    remitente_id:parseInt(document.getElementById('fRemitente').value||'0',10)||0,
    plantilla_id:parseInt(document.getElementById('fPlantilla').value||'0',10)||0,
    modo_contenido:document.getElementById('fModo').value,
    asunto:document.getElementById('fAsunto').value.trim(),
    cuerpo:state.canal==='email'&&document.getElementById('fFormatoEmail').value==='html'?'':document.getElementById('fCuerpo').value,
    cuerpo_html:state.canal==='email'&&document.getElementById('fFormatoEmail').value==='html'?document.getElementById('fHtml').value:'',
    media_ids:state.media.map(m=>m.id),
    variables_map:variablesMap(),
    modo_envio:modoEnvio,
    programar_ahora:modoEnvio==='ahora'?1:0,
    programado_para:modoEnvio==='programado'?document.getElementById('fProgramado').value:'',
    origen:state.tipo==='base'?'carga':'manual',
    ia_costo_usd:state.ia.costo_usd,
    ia_modelo:state.ia.modelo,
    ia_tokens_prompt:state.ia.tokens_prompt,
    ia_tokens_completion:state.ia.tokens_completion,
    ia_generaciones:state.ia.generaciones,
  };
  const btn=document.getElementById('btnGuardar');btn.disabled=true;
  try{
    let d;
    if(state.tipo==='asociado'){
      data.destinatarios=[{cedula:state.asociado.cedula,nombre:state.asociado.nombre,telefono:state.asociado.telefono||'',email:state.asociado.email||'',opt_in:state.optIn?1:0}];
      d=await post('comunicaciones/planes',data);
    }else if(state.tipo==='varios'){
      data.destinatarios=state.seleccionados.map(a=>({cedula:a.cedula,nombre:a.nombre,telefono:a.telefono||'',email:a.email||'',opt_in:1}));
      d=await post('comunicaciones/planes',data);
    }else{
      const fd=new FormData();
      Object.keys(data).forEach(k=>{
        const v=data[k];
        fd.append(k, typeof v==='object'?JSON.stringify(v):String(v));
      });
      fd.append('archivo',state.file);
      d=await api('comunicaciones/planes',{method:'POST',body:fd});
    }
    showAlert(modoEnvio==='ahora'?'Comunicación creada; el envío quedó en proceso.':'Plan guardado correctamente.',true);
    setTimeout(()=>{location.href=U+'modules/comunicaciones/pages/plan_detalle?id='+d.id;},700);
  }catch(e){showAlert(e.message,false);}
  finally{btn.disabled=false;}
}
// Fecha por defecto: próxima hora en punto + 5 min
(function(){
  const d=new Date(Date.now()+5*60000);d.setSeconds(0,0);
  const pad=n=>String(n).padStart(2,'0');
  document.getElementById('fProgramado').value=d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
  document.getElementById('fNombre').value='Comunicación '+d.toLocaleDateString('es-CO');
})();
cambiarCanal();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
