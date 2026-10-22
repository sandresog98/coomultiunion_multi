<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('comunicaciones_config');
$pageTitle = 'Configuración de comunicaciones';
$currentPage = 'comunicaciones_configuracion';
$acc = ui_acciones('comunicaciones.configuracion', $staff['rol'] ?? '');
$accRem = ui_acciones('comunicaciones.remitentes', $staff['rol'] ?? '');
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div id="alert" class="alert d-none"></div>
<div id="trialBanner" class="alert alert-warning d-none small"></div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header brand"><i class="fas fa-plug me-2"></i>Conexiones</div>
      <div class="card-body small" id="conexiones"><span class="text-muted">Cargando…</span></div>
      <div class="px-3 pb-3"><div class="alert alert-info small mb-0">El email sale por el <b>SMTP institucional</b> configurado en <code>.env</code> (<code>SMTP_HOST</code>, <code>SMTP_USER</code>, <code>SMTP_PASS</code>). Use un remitente verificado y respete las bajas; el módulo agrega <code>List-Unsubscribe</code>. SendGrid queda como integración opcional futura.</div></div>
      <div class="card-footer d-flex gap-2">
        <?php if (($acc['comunicaciones.configuracion.probar'] ?? false)): ?>
        <button class="btn btn-sm btn-brand" id="btnProbar" onclick="probar()"><i class="fas fa-satellite-dish me-1"></i>Probar conexión</button>
        <button class="btn btn-sm btn-outline-primary" onclick="abrirSmtp()"><i class="fas fa-envelope me-1"></i>Probar SMTP</button>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/plantillas">Plantillas</a>
      </div>
    </div>

    <div class="card">
      <div class="card-header brand"><i class="fas fa-webhook me-2"></i>Webhooks</div>
      <div class="card-body small">
        <label class="form-label small text-muted mb-1">Estados (StatusCallback)</label>
        <div class="input-group input-group-sm mb-2"><input class="form-control" id="urlStatus" readonly><button class="btn btn-outline-secondary" onclick="copiar('urlStatus')"><i class="fas fa-copy"></i></button></div>
        <label class="form-label small text-muted mb-1">Mensajes entrantes</label>
        <div class="input-group input-group-sm mb-2"><input class="form-control" id="urlInbound" readonly><button class="btn btn-outline-secondary" onclick="copiar('urlInbound')"><i class="fas fa-copy"></i></button></div>
        <label class="form-label small text-muted mb-1">Email (SendGrid)</label>
        <div class="input-group input-group-sm mb-2"><input class="form-control" id="urlSendgrid" readonly><button class="btn btn-outline-secondary" onclick="copiar('urlSendgrid')"><i class="fas fa-copy"></i></button></div>
        <p class="text-muted mb-0">Configure la URL de entrantes en el sender/Messaging Service de Twilio. La URL de estados se envía en cada mensaje. En SendGrid agregue <code>?k=SU_SECRETO</code>.</p>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-at me-2"></i>Remitentes</span>
        <?php if (($accRem['comunicaciones.remitentes.guardar'] ?? false)): ?>
        <button class="btn btn-light btn-sm" onclick="abrirRemitente()"><i class="fas fa-plus me-1"></i>Nuevo</button>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Canal</th><th>Etiqueta</th><th>Dirección</th><th>Messaging Service</th><th class="text-center">Media</th><th class="text-center">Estado</th><th></th></tr></thead>
            <tbody id="tbodyRem"><tr><td colspan="7" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header brand"><i class="fas fa-sliders me-2"></i>Parámetros</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label">Hora inicio</label><input class="form-control" id="p_hora_inicio" placeholder="08:00"></div>
          <div class="col-md-3"><label class="form-label">Hora fin</label><input class="form-control" id="p_hora_fin" placeholder="20:00"></div>
          <div class="col-md-3"><label class="form-label">Zona horaria</label><input class="form-control" id="p_zona" placeholder="America/Bogota"></div>
          <div class="col-md-3"><label class="form-label">Mensajes/segundo</label><input type="number" min="1" class="form-control" id="p_mps"></div>
          <div class="col-md-3"><label class="form-label">Lote worker</label><input type="number" min="1" class="form-control" id="p_lote"></div>
          <div class="col-md-3"><label class="form-label">Reintentos</label><input type="number" min="1" class="form-control" id="p_reintentos"></div>
          <div class="col-md-3"><label class="form-label">Máx. destinatarios</label><input type="number" min="1" class="form-control" id="p_max"></div>
          <div class="col-md-3"><label class="form-label">Opt-in obligatorio</label><select class="form-select" id="p_optin"><option value="1">Sí</option><option value="0">No</option></select></div>
          <div class="col-md-4"><label class="form-label">Tarifa SMS / segmento (USD)</label><input type="number" step="0.000001" min="0" class="form-control" id="p_t_sms"></div>
          <div class="col-md-4"><label class="form-label">Tarifa WhatsApp / mensaje (USD)</label><input type="number" step="0.000001" min="0" class="form-control" id="p_t_wa"></div>
          <div class="col-md-4"><label class="form-label">Tarifa email / mensaje (USD)</label><input type="number" step="0.000001" min="0" class="form-control" id="p_t_email"></div>
          <div class="col-md-6"><label class="form-label">Palabras opt-out</label><input class="form-control" id="p_optout"></div>
          <div class="col-md-6"><label class="form-label">Palabras opt-in</label><input class="form-control" id="p_optin_kw"></div>
          <div class="col-12"><label class="form-label">Texto de ayuda</label><input class="form-control" id="p_ayuda"></div>
        </div>
        <?php if (($acc['comunicaciones.configuracion.editar'] ?? false)): ?>
        <div class="mt-3"><button class="btn btn-brand" id="btnGuardar" onclick="guardar()"><i class="fas fa-save me-1"></i>Guardar parámetros</button></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal prueba SMTP -->
<div class="modal fade" id="mSmtp" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Probar envío SMTP</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label">Correo destinatario de la prueba</label>
    <input type="email" class="form-control" id="smtpTo" placeholder="correo@dominio.com">
    <div class="form-text">Se enviará un mensaje corto usando el SMTP configurado.</div>
    <div id="smtpResult" class="small mt-2"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button><button class="btn btn-brand" id="btnSmtpSend" onclick="enviarSmtp()"><i class="fas fa-paper-plane me-1"></i>Enviar prueba</button></div>
</div></div></div>

<!-- Modal remitente -->
<div class="modal fade" id="mRem" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="rTitulo">Remitente</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="rId">
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label">Canal</label><select class="form-select" id="rCanal"><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="email">Email</option></select></div>
      <div class="col-md-6"><label class="form-label">Proveedor</label><select class="form-select" id="rProveedor"><option value="twilio">Twilio</option><option value="sendgrid">SendGrid</option><option value="smtp">SMTP</option></select></div>
      <div class="col-12"><label class="form-label">Etiqueta</label><input class="form-control" id="rEtiqueta" placeholder="Ej. WhatsApp principal"></div>
      <div class="col-12"><label class="form-label">Dirección</label><input class="form-control" id="rDireccion" placeholder="+57... / whatsapp:+57... / correo@dominio"><div class="form-text">Para WhatsApp use el número con prefijo <code>whatsapp:</code>; el sistema lo normaliza.</div></div>
      <div class="col-12"><label class="form-label">Nombre visible (email)</label><input class="form-control" id="rNombre" placeholder="Coomultiunion"></div>
      <div class="col-12"><label class="form-label">Messaging Service SID (opcional)</label><input class="form-control" id="rMsid" placeholder="MG..."></div>
      <div class="col-md-4"><label class="form-label">Mensajes/seg.</label><input type="number" min="1" class="form-control" id="rMps" value="5"></div>
      <div class="col-md-4"><label class="form-label">Soporta media</label><select class="form-select" id="rMedia"><option value="0">No</option><option value="1">Sí</option></select></div>
      <div class="col-md-4"><label class="form-label">Predeterminado</label><select class="form-select" id="rPred"><option value="0">No</option><option value="1">Sí</option></select></div>
      <div class="col-12"><label class="form-label">Activo</label><select class="form-select" id="rActivo"><option value="1">Sí</option><option value="0">No</option></select></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarRemitente()">Guardar</button></div>
</div></div></div>

<script>
window.API=<?= json_encode(apiUrl()) ?>;
const U=<?= json_encode(uiBaseUrl()) ?>;
const L=window.Listado, esc=L.esc;
const ACC=<?= json_encode($acc, JSON_UNESCAPED_UNICODE) ?>;
const ACCR=<?= json_encode($accRem, JSON_UNESCAPED_UNICODE) ?>;
let cfg=null, remitentes=[], mRem;
function showAlert(msg,ok){const a=document.getElementById('alert');a.className='alert '+(ok?'alert-success':'alert-danger');a.textContent=msg;a.classList.remove('d-none');}
async function api(p,o){return L.api(p,o);}
function post(path,data){return api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}
function copiar(id){const el=document.getElementById(id);el.select();document.execCommand('copy');}
async function cargar(){
  try{
    cfg=await api('comunicaciones/configuracion');
    remitentes=cfg.remitentes||[];
    const tw=cfg.twilio||{}, sg=cfg.sendgrid||{}, c=cfg.config||{};
    if(tw.trial||tw.dry_run){
      const p=[];if(tw.trial)p.push('<b>Modo trial</b>: SMS solo a números verificados; WhatsApp requiere sandbox o sender aprobado.');if(tw.dry_run)p.push('<b>Simulación</b>: los envíos no salen a Twilio.');
      const b=document.getElementById('trialBanner');b.innerHTML=p.join('<br>');b.classList.remove('d-none');
    }
    document.getElementById('conexiones').innerHTML=`
      <div class="d-flex justify-content-between"><span>Twilio</span>${tw.configurado?'<span class="badge text-bg-success">Configurado</span>':'<span class="badge text-bg-danger">Sin configurar</span>'}</div>
      <div class="d-flex justify-content-between mt-2"><span>SMTP email</span>${cfg.smtp&&cfg.smtp.configurado?'<span class="badge text-bg-success">Configurado</span>':'<span class="badge text-bg-danger">Sin configurar</span>'}</div>
      <div class="d-flex justify-content-between mt-2"><span>PHPMailer</span>${cfg.smtp&&cfg.smtp.disponible?'<span class="badge text-bg-success">Disponible</span>':'<span class="badge text-bg-danger">No disponible</span>'}</div>
      <div class="d-flex justify-content-between mt-2"><span>SendGrid (opcional)</span>${sg.configurado?'<span class="badge text-bg-success">Configurado</span>':'<span class="badge text-bg-secondary">No usado</span>'}</div>
      <div class="d-flex justify-content-between mt-2"><span>Firma webhooks</span>${tw.validate_signature?'<span class="badge text-bg-success">Activa</span>':'<span class="badge text-bg-danger">Desactivada</span>'}</div>
      <div class="d-flex justify-content-between mt-2"><span>Auth Token (firma)</span>${tw.auth_token_configurado?'<span class="badge text-bg-success">Configurado</span>':'<span class="badge text-bg-danger">Falta</span>'}</div>
      <div class="d-flex justify-content-between mt-2"><span>Modo</span><span>${tw.dry_run?'Simulación':'Real'}${tw.trial?' · trial':''}</span></div>`;
    document.getElementById('urlStatus').value=tw.status_callback_url||'';
    document.getElementById('urlInbound').value=tw.inbound_callback_url||'';
    document.getElementById('urlSendgrid').value=window.API+'comunicaciones/webhooks/sendgrid.php';
    document.getElementById('p_hora_inicio').value=c.com_hora_inicio||'08:00';
    document.getElementById('p_hora_fin').value=c.com_hora_fin||'20:00';
    document.getElementById('p_zona').value=c.com_zona_horaria||'America/Bogota';
    document.getElementById('p_mps').value=c.com_limite_mps||'5';
    document.getElementById('p_lote').value=c.com_lote||'50';
    document.getElementById('p_reintentos').value=c.com_reintentos||'2';
    document.getElementById('p_max').value=c.com_max_destinatarios||'50000';
    document.getElementById('p_optin').value=c.com_consentimiento_obligatorio==='0'?'0':'1';
    document.getElementById('p_t_sms').value=c.com_tarifa_sms_usd||'0';
    document.getElementById('p_t_wa').value=c.com_tarifa_whatsapp_usd||'0';
    document.getElementById('p_t_email').value=c.com_tarifa_email_usd||'0';
    document.getElementById('p_optout').value=c.com_optout_keywords||'';
    document.getElementById('p_optin_kw').value=c.com_optin_keywords||'';
    document.getElementById('p_ayuda').value=c.com_ayuda_texto||'';
    renderRem();
  }catch(e){showAlert(e.message,false);}
}
function renderRem(){
  document.getElementById('tbodyRem').innerHTML=(remitentes||[]).map(r=>`<tr>
    <td>${esc(r.canal)}</td><td>${esc(r.etiqueta)}</td><td class="font-monospace small">${esc(r.direccion)}</td>
    <td class="small text-muted">${esc(r.messaging_service_sid||'—')}</td><td class="text-center">${r.soporta_media==1?'Sí':'—'}</td>
    <td class="text-center">${r.predeterminado==1?'<span class="badge text-bg-primary">Predeterminado</span>':r.activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-secondary">Inactivo</span>'}</td>
    <td class="text-end text-nowrap">
      ${ACCR['comunicaciones.remitentes.guardar']?`<button class="btn btn-sm btn-outline-primary" data-r="${encodeURIComponent(JSON.stringify(r))}" onclick="editarRem(JSON.parse(decodeURIComponent(this.dataset.r)))"><i class="fas fa-pen"></i></button>`:''}
      ${ACCR['comunicaciones.remitentes.eliminar']?`<button class="btn btn-sm btn-outline-danger" onclick="eliminarRem(${r.id})"><i class="fas fa-trash"></i></button>`:''}
    </td></tr>`).join('')||'<tr><td colspan="7" class="text-center text-muted py-4">Sin remitentes</td></tr>';
}
let mSmtp=null;
function abrirSmtp(){
  mSmtp=mSmtp||new bootstrap.Modal(document.getElementById('mSmtp'));
  document.getElementById('smtpResult').textContent='';
  document.getElementById('smtpTo').value='';
  mSmtp.show();
}
async function enviarSmtp(){
  const to=document.getElementById('smtpTo').value.trim();
  if(!to){document.getElementById('smtpResult').innerHTML='<span class="text-danger">Indique un correo.</span>';return;}
  const b=document.getElementById('btnSmtpSend');b.disabled=true;
  document.getElementById('smtpResult').innerHTML='<span class="text-muted"><i class="fas fa-spinner fa-spin me-1"></i>Enviando…</span>';
  try{
    await post('comunicaciones/smtp_test',{to});
    document.getElementById('smtpResult').innerHTML='<span class="text-success">SMTP aceptó el correo. Revise la bandeja y spam de '+esc(to)+'.</span>';
  }catch(e){
    document.getElementById('smtpResult').innerHTML='<span class="text-danger">'+esc(e.message)+'</span>';
  }finally{b.disabled=false;}
}
function editarRem(r){ abrirRemitente(r); }
function abrirRemitente(r){
  mRem=mRem||new bootstrap.Modal(document.getElementById('mRem'));
  document.getElementById('rTitulo').textContent=r?'Editar remitente':'Nuevo remitente';
  document.getElementById('rId').value=r?r.id:'';
  document.getElementById('rCanal').value=r?r.canal:'whatsapp';
  document.getElementById('rProveedor').value=r?r.proveedor:'twilio';
  document.getElementById('rEtiqueta').value=r?r.etiqueta:'';
  document.getElementById('rDireccion').value=r?r.direccion:'';
  document.getElementById('rNombre').value=r?(r.nombre_remitente||''):'';
  document.getElementById('rMsid').value=r?(r.messaging_service_sid||''):'';
  document.getElementById('rMps').value=r?(r.limite_mps||5):5;
  document.getElementById('rMedia').value=r?(r.soporta_media||0):0;
  document.getElementById('rPred').value=r?(r.predeterminado||0):0;
  document.getElementById('rActivo').value=r?(r.activo||1):1;
  mRem.show();
}
async function guardarRemitente(){
  try{
    await post('comunicaciones/remitentes',{id:parseInt(document.getElementById('rId').value||'0',10),canal:document.getElementById('rCanal').value,proveedor:document.getElementById('rProveedor').value,etiqueta:document.getElementById('rEtiqueta').value,direccion:document.getElementById('rDireccion').value,nombre_remitente:document.getElementById('rNombre').value,messaging_service_sid:document.getElementById('rMsid').value,limite_mps:document.getElementById('rMps').value,soporta_media:document.getElementById('rMedia').value,predeterminado:document.getElementById('rPred').value,activo:document.getElementById('rActivo').value});
    mRem.hide();showAlert('Remitente guardado.',true);cargar();
  }catch(e){showAlert(e.message,false);}
}
async function eliminarRem(id){if(!confirm('¿Eliminar el remitente?'))return;try{await api('comunicaciones/remitentes',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});showAlert('Remitente eliminado.',true);cargar();}catch(e){showAlert(e.message,false);}}
async function guardar(){
  const valores={
    com_hora_inicio:document.getElementById('p_hora_inicio').value,com_hora_fin:document.getElementById('p_hora_fin').value,
    com_zona_horaria:document.getElementById('p_zona').value,com_limite_mps:document.getElementById('p_mps').value,
    com_lote:document.getElementById('p_lote').value,com_reintentos:document.getElementById('p_reintentos').value,
    com_max_destinatarios:document.getElementById('p_max').value,com_consentimiento_obligatorio:document.getElementById('p_optin').value,
    com_tarifa_sms_usd:document.getElementById('p_t_sms').value,com_tarifa_whatsapp_usd:document.getElementById('p_t_wa').value,
    com_tarifa_email_usd:document.getElementById('p_t_email').value,com_optout_keywords:document.getElementById('p_optout').value,
    com_optin_keywords:document.getElementById('p_optin_kw').value,com_ayuda_texto:document.getElementById('p_ayuda').value
  };
  try{await post('comunicaciones/configuracion',{accion:'guardar',valores});showAlert('Parámetros guardados.',true);cargar();}catch(e){showAlert(e.message,false);}
}
async function probar(){
  const b=document.getElementById('btnProbar');b.disabled=true;
  try{const d=await post('comunicaciones/configuracion',{accion:'probar'});const tw=d.twilio||{},sg=d.sendgrid||{},sm=d.smtp||{};showAlert('Twilio: '+(tw.ok?'OK':'ERROR')+' — '+(tw.detalle||'')+' · SMTP: '+(sm.ok?'OK':'ERROR')+' — '+(sm.detalle||'')+' · SendGrid: '+(sg.ok?'OK':'ERROR')+' — '+(sg.detalle||''),tw.ok&&sm.ok);if(tw.cuenta){document.getElementById('conexiones').insertAdjacentHTML('beforeend',`<hr class="my-2"><div class="small">Cuenta: ${esc(tw.cuenta.nombre||'')}<br>Tipo: ${esc(tw.cuenta.tipo||'')}<br>Saldo: ${esc(tw.saldo||'—')} ${esc(tw.moneda||'')}</div>`);}}catch(e){showAlert(e.message,false);}finally{b.disabled=false;}
}
document.getElementById('rCanal').addEventListener('change',function(){
  const c=this.value;
  document.getElementById('rProveedor').value=c==='email'?'smtp':'twilio';
  document.getElementById('rMedia').value=c==='sms'?'0':'1';
});
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
