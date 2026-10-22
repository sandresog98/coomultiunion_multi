<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('comunicaciones');
$pageTitle = 'Detalle del plan';
$currentPage = 'comunicaciones_planes';
$acc = ui_acciones('comunicaciones.planes', $staff['rol'] ?? '');
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
$planId = (int) ($_GET['id'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-2">
  <a class="btn btn-outline-secondary btn-sm" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/planes"><i class="fas fa-arrow-left me-1"></i>Volver</a>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary btn-sm" onclick="cargar()"><i class="fas fa-rotate me-1"></i>Actualizar</button>
  </div>
</div>
<div id="alert" class="alert d-none"></div>
<div id="contenido"><div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin me-2"></i>Cargando…</div></div>

<!-- Modal programar -->
<div class="modal fade" id="mProgramar" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Programar plan</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <label class="form-label">Fecha y hora (America/Bogota)</label>
    <input type="datetime-local" class="form-control" id="fProgramado">
    <div class="form-text">Se enviará en la primera ejecución del worker después de esta hora (máx. 15 min).</div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="programar()">Programar</button></div>
</div></div></div>

<script>
window.API=<?= json_encode(apiUrl()) ?>;
const U=<?= json_encode(uiBaseUrl()) ?>;
const PLAN_ID=<?= json_encode($planId) ?>;
const L=window.Listado, esc=L.esc;
const ACC=<?= json_encode($acc, JSON_UNESCAPED_UNICODE) ?>;
const EST={borrador:['Borrador','secondary'],programado:['Programado','primary'],en_proceso:['En proceso','warning'],completado:['Completado','success'],parcial:['Parcial','warning'],cancelado:['Cancelado','secondary'],fallido:['Fallido','danger'],pendiente:['Pendiente','secondary'],enviando:['Enviando','warning'],enviado:['Enviado','primary'],entregado:['Entregado','success'],leido:['Leído','success'],omitido:['Omitido','secondary']};
function badge(e){const x=EST[e]||[e||'—','secondary'];return `<span class="badge text-bg-${x[1]}">${esc(x[0])}</span>`;}
function usd(v){return '$'+(Number(v||0)).toLocaleString('en-US',{minimumFractionDigits:4,maximumFractionDigits:4})+' USD';}
function fmt(v){return v?new Date(String(v).replace(' ','T')+'Z').toLocaleString('es-CO',{dateStyle:'short',timeStyle:'short'}):'—';}
function showAlert(msg,ok){const a=document.getElementById('alert');a.className='alert '+(ok?'alert-success':'alert-danger');a.textContent=msg;a.classList.remove('d-none');}
async function api(p,o){return L.api(p,o);}
function post(path,data){return api(path,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});}
let planActual=null, modalProg=null;
function fechaLocalInput(){
  const d=new Date(Date.now()+5*60000);
  const pad=n=>String(n).padStart(2,'0');
  return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
}
function abrirProgramar(){
  const el=document.getElementById('fProgramado'); el.value=fechaLocalInput();
  modalProg=modalProg||new bootstrap.Modal(document.getElementById('mProgramar'));
  modalProg.show();
}
async function programar(){
  const f=document.getElementById('fProgramado').value;
  if(!f){showAlert('Indique fecha y hora.',false);return;}
  try{await post('comunicaciones/plan',{id:PLAN_ID,accion:'programar',modo:'fecha',programado_para:f});modalProg.hide();showAlert('Plan programado.',true);cargar();}catch(e){showAlert(e.message,false);}
}
async function enviarAhora(){if(!confirm('¿Enviar ahora? Esta acción no se puede deshacer.'))return;try{await post('comunicaciones/plan',{id:PLAN_ID,accion:'enviar_ahora'});showAlert('Envío iniciado.',true);cargar();}catch(e){showAlert(e.message,false);}}
async function cancelar(){if(!confirm('¿Cancelar el plan?'))return;try{await post('comunicaciones/plan',{id:PLAN_ID,accion:'cancelar'});showAlert('Plan cancelado.',true);cargar();}catch(e){showAlert(e.message,false);}}
async function duplicar(){try{const d=await post('comunicaciones/plan',{id:PLAN_ID,accion:'duplicar'});location.href=U+'modules/comunicaciones/pages/plan_detalle?id='+d.id;}catch(e){showAlert(e.message,false);}}
async function eliminar(){if(!confirm('¿Eliminar el plan definitivamente?'))return;try{await api('comunicaciones/planes',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:PLAN_ID})});location.href=U+'modules/comunicaciones/pages/planes';}catch(e){showAlert(e.message,false);}}
async function reintentar(id){try{await post('comunicaciones/plan',{id:PLAN_ID,accion:'reintentar',destinatario_id:id});showAlert('Mensaje reencolado.',true);cargar();}catch(e){showAlert(e.message,false);}}
async function reenviar(id){if(!confirm('¿Reenviar este correo? Se generará un nuevo envío.'))return;try{await post('comunicaciones/plan',{id:PLAN_ID,accion:'reenviar',destinatario_id:id});showAlert('Correo reencolado. El worker lo enviará en el próximo ciclo o use Enviar ahora.',true);cargar();}catch(e){showAlert(e.message,false);}}

async function cargar(){
  if(!PLAN_ID){showAlert('Plan no indicado.',false);return;}
  try{
    const d=await api('comunicaciones/plan?id='+PLAN_ID);
    planActual=d.plan;
    const p=d.plan;
    const acciones=[];
    if(['borrador','programado'].includes(p.estado)&&ACC['comunicaciones.planes.programar']){
      acciones.push(`<button class="btn btn-sm btn-brand" onclick="abrirProgramar()"><i class="fas fa-calendar-plus me-1"></i>${p.estado==='programado'?'Reprogramar':'Programar'}</button>`);
      acciones.push(`<button class="btn btn-sm btn-outline-success" onclick="enviarAhora()"><i class="fas fa-paper-plane me-1"></i>Enviar ahora</button>`);
    }
    if(['borrador','programado','en_proceso'].includes(p.estado)&&ACC['comunicaciones.planes.cancelar']){
      acciones.push(`<button class="btn btn-sm btn-outline-warning" onclick="cancelar()"><i class="fas fa-ban me-1"></i>Cancelar</button>`);
    }
    if(ACC['comunicaciones.planes.duplicar']) acciones.push(`<button class="btn btn-sm btn-outline-secondary" onclick="duplicar()"><i class="fas fa-copy me-1"></i>Duplicar</button>`);
    if(['borrador','cancelado'].includes(p.estado)&&ACC['comunicaciones.planes.eliminar']) acciones.push(`<button class="btn btn-sm btn-outline-danger" onclick="eliminar()"><i class="fas fa-trash me-1"></i>Eliminar</button>`);

    document.getElementById('contenido').innerHTML=`
      <div class="card mb-3">
        <div class="card-header brand d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span><i class="fas fa-calendar-check me-2"></i>${esc(p.nombre)}</span>
          <span class="d-flex gap-2 flex-wrap">${acciones.join('')}</span>
        </div>
        <div class="card-body">
          <div class="row g-3 small">
            <div class="col-md-3"><div class="text-muted">Canal</div><div class="fw-semibold">${esc(p.canal)} · ${esc(p.tipo||'')}</div></div>
            <div class="col-md-3"><div class="text-muted">Remitente</div><div class="fw-semibold">${esc(p.remitente_etiqueta||'Sin remitente')}</div><div class="text-muted">${esc(p.remitente_direccion||'')}</div></div>
            <div class="col-md-3"><div class="text-muted">Programado</div><div class="fw-semibold">${esc(p.programado_para_local||'Inmediato / no programado')}</div></div>
            <div class="col-md-3"><div class="text-muted">Estado</div><div>${badge(p.estado)}</div></div>
            <div class="col-md-3"><div class="text-muted">Costo estimado</div><div class="fw-semibold">${usd(p.costo_estimado_usd)}</div></div>
            <div class="col-md-3"><div class="text-muted">Costo real</div><div class="fw-semibold">${usd(p.costo_total_usd)}</div></div>
            <div class="col-md-3"><div class="text-muted">Costo IA (USD)</div><div class="fw-semibold">${usd(p.ia_costo_usd)}</div><div class="small text-muted">${p.ia_generaciones?esc((p.ia_generaciones||0)+' generación(es) · '+(p.ia_modelo||'')):'Sin IA'}</div></div>
            <div class="col-md-3"><div class="text-muted">Total envío + IA</div><div class="fw-semibold">${usd(Number(p.costo_total_usd||p.costo_estimado_usd||0)+Number(p.ia_costo_usd||0))}</div></div>
            <div class="col-md-6"><div class="text-muted">Contenido</div><div>${esc((p.asunto?p.asunto+' · ':'')+(p.cuerpo||'').slice(0,300))}</div></div>
          </div>
          ${p.cuerpo_html?`<div class="mt-2"><div class="small fw-semibold text-muted mb-1"><i class="fas fa-code me-1"></i>Vista previa HTML</div><iframe id="planHtmlPreview" sandbox="allow-same-origin" title="Vista previa HTML" style="width:100%;height:420px;border:1px solid #dee2e6;border-radius:8px;background:#fff;color-scheme:light"></iframe></div>`:''}
          ${p.canal==='email'?`<div class="alert alert-info small mt-2 mb-0">El email sale por SMTP. No hay confirmación de entrega del proveedor: si no aparece en la bandeja, revise <b>spam</b> y la autenticación del dominio (SPF/DKIM/DMARC). Puede reenviar desde la tabla de destinatarios.</div>`:''}
          <div class="row g-2 mt-2">
            <div class="col-6 col-md-2"><div class="border rounded p-2 text-center"><div class="small text-muted">Destinatarios</div><strong>${p.total_destinatarios||0}</strong></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2 text-center"><div class="small text-muted">Enviados</div><strong>${p.total_enviados||0}</strong></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2 text-center"><div class="small text-muted">Entregados</div><strong>${p.total_entregados||0}</strong></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2 text-center"><div class="small text-muted">Leídos</div><strong>${p.total_leidos||0}</strong></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2 text-center"><div class="small text-muted">Fallidos</div><strong class="text-danger">${p.total_fallidos||0}</strong></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2 text-center"><div class="small text-muted">Omitidos</div><strong>${p.total_omitidos||0}</strong></div></div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header brand"><i class="fas fa-users me-2"></i>Destinatarios y estado</div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:520px;overflow:auto">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="sticky-top bg-body"><tr><th>Destino</th><th>Nombre</th><th>Estado</th><th>SID / ID</th><th>Error</th><th class="text-end">Costo</th><th>Fechas</th><th></th></tr></thead>
              <tbody>${(d.destinatarios||[]).map(x=>`<tr>
                <td>${esc(x.destino)}</td><td>${esc(x.nombre||'')}</td><td>${badge(x.estado)}${x.motivo_omitido?`<div class="small text-muted">${esc(x.motivo_omitido)}</div>`:''}</td>
                <td class="small text-muted">${esc(x.proveedor_message_id||'—')}</td>
                <td class="small text-danger">${esc(x.error_message||'')}${x.error_code?` <span class="text-muted">(${x.error_code})</span>`:''}</td>
                <td class="text-end">${x.precio_usd!=null?usd(x.precio_usd):'—'}</td>
                <td class="small text-muted">Envío: ${fmt(x.fecha_envio)}<br>Entrega: ${fmt(x.fecha_entrega)}<br>Lectura: ${fmt(x.fecha_lectura)}</td>
                <td class="text-end text-nowrap">
                  ${['fallido','omitido'].includes(x.estado)&&ACC['comunicaciones.planes.reintentar']?`<button class="btn btn-sm btn-outline-primary" onclick="reintentar(${x.id})" title="Reintentar"><i class="fas fa-rotate-right"></i></button>`:''}
                  ${p.canal==='email'&&['enviado','entregado','leido'].includes(x.estado)&&ACC['comunicaciones.planes.reintentar']?`<button class="btn btn-sm btn-outline-secondary" onclick="reenviar(${x.id})" title="Reenviar correo"><i class="fas fa-paper-plane"></i></button>`:''}
                </td>
              </tr>`).join('') || '<tr><td colspan="8" class="text-center text-muted py-4">Sin destinatarios</td></tr>'}</tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header brand"><i class="fas fa-list-ul me-2"></i>Eventos recientes</div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:280px;overflow:auto">
            <table class="table table-sm mb-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Estado</th><th>Mensaje</th><th>SID / ID</th></tr></thead>
            <tbody>${(d.eventos||[]).map(e=>`<tr><td>${fmt(e.fecha_creacion)}</td><td>${esc(e.tipo)}</td><td>${esc(e.estado||'')}</td><td class="small text-danger">${esc(e.error_message||'')}</td><td class="small text-muted">${esc(e.proveedor_message_id||'')}</td></tr>`).join('')||'<tr><td colspan="5" class="text-center text-muted py-3">Sin eventos</td></tr>'}</tbody></table>
          </div>
        </div>
      </div>`;
    const hp=document.getElementById('planHtmlPreview');
    if(hp && p.cuerpo_html){
      let h=String(p.cuerpo_html);
      h=h.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'')
         .replace(/\son\w+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi,'')
         .replace(/javascript\s*:/gi,'');
      const abre=(h.match(/</g)||[]).length, cierra=(h.match(/>/g)||[]).length;
      hp.srcdoc=abre!==cierra
        ? '<!doctype html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;padding:16px;color:#b02a37"><h4>HTML incompleto</h4><p>El HTML guardado tiene etiquetas sin cerrar; no se puede previsualizar correctamente.</p></body></html>'
        : (/<html|<body/i.test(h) ? h : '<!doctype html><html><head><meta charset="utf-8"><style>html,body{color-scheme:light}body{font-family:Arial,Helvetica,sans-serif;padding:16px;background:#fff;color:#333;font-size:14px}</style></head><body>'+h+'</body></html>');
    }
    if(p.estado==='en_proceso'){setTimeout(cargar,15000);}
  }catch(e){showAlert(e.message,false);}
}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
