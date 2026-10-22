<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('comunicaciones');
$pageTitle = 'Comunicaciones';
$currentPage = 'comunicaciones_panel';
$sec = ui_secciones('comunicaciones.panel', $staff['rol'] ?? '');
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<style>
  .com-kpi .card-body { padding: .8rem 1rem; }
  .com-kpi .num { font-size: 1.35rem; font-weight: 700; line-height: 1.1; }
  .com-kpi .lbl { font-size: .75rem; color: var(--bs-secondary-color); }
  .com-status { font-size: .72rem; }
</style>

<div id="alert" class="alert d-none"></div>
<div id="trialBanner" class="alert alert-warning d-none small"></div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card com-kpi"><div class="card-body"><div class="lbl">Mensajes del mes</div><div class="num" id="kTotal">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card com-kpi"><div class="card-body"><div class="lbl">Entregados</div><div class="num text-success" id="kEntregados">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card com-kpi"><div class="card-body"><div class="lbl">Fallidos</div><div class="num text-danger" id="kFallidos">—</div></div></div></div>
  <div class="col-6 col-md-3"><div class="card com-kpi"><div class="card-body"><div class="lbl">Costo del mes (USD)</div><div class="num" id="kCosto">—</div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-calendar-check me-2"></i>Próximos planes</span>
        <a class="btn btn-light btn-sm" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/nueva"><i class="fas fa-plus me-1"></i>Nueva comunicación</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Plan</th><th>Canal</th><th>Programado</th><th class="text-center">Estado</th><th class="text-end">Dest.</th></tr></thead>
            <tbody id="tbodyPlanes"><tr><td colspan="5" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header brand"><i class="fas fa-plug me-2"></i>Estado de la conexión</div>
      <div class="card-body small" id="estadoConexion"><span class="text-muted">Cargando…</span></div>
    </div>
    <div class="card">
      <div class="card-header brand"><i class="fas fa-circle-info me-2"></i>Accesos rápidos</div>
      <div class="card-body d-grid gap-2">
        <a class="btn btn-outline-primary btn-sm" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/planes"><i class="fas fa-list-check me-1"></i>Ver planes</a>
        <a class="btn btn-outline-primary btn-sm" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/historial"><i class="fas fa-clock-rotate-left me-1"></i>Historial y costos</a>
        <a class="btn btn-outline-primary btn-sm" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/plantillas"><i class="fas fa-file-lines me-1"></i>Plantillas</a>
      </div>
    </div>
  </div>
</div>

<div class="card mt-3">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-paper-plane me-2"></i>Últimos mensajes</span>
    <a class="small text-white-50" href="<?= uiBaseUrl() ?>modules/comunicaciones/pages/historial">Ver todo</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>Fecha</th><th>Plan</th><th>Destino</th><th>Canal</th><th class="text-center">Estado</th><th class="text-end">Costo</th></tr></thead>
        <tbody id="tbodyUltimos"><tr><td colspan="6" class="text-center text-muted py-4">Cargando…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<script>
window.API = <?= json_encode(apiUrl()) ?>;
const U = <?= json_encode(uiBaseUrl()) ?>;
const L = window.Listado;
const esc = L.esc;
const EST = {
  borrador:['Borrador','secondary'], programado:['Programado','primary'], en_proceso:['En proceso','warning'],
  completado:['Completado','success'], parcial:['Parcial','warning'], cancelado:['Cancelado','secondary'], fallido:['Fallido','danger'],
  pendiente:['Pendiente','secondary'], enviando:['Enviando','warning'], enviado:['Enviado','primary'],
  entregado:['Entregado','success'], leido:['Leído','success'], omitido:['Omitido','secondary']
};
function badge(e){const x=EST[e]||[e||'—','secondary'];return `<span class="badge text-bg-${x[1]} com-status">${esc(x[0])}</span>`;}
function usd(v){return '$'+(Number(v||0)).toLocaleString('en-US',{minimumFractionDigits:4,maximumFractionDigits:4})+' USD';}
function fmt(v){return v ? new Date(String(v).replace(' ','T')+'Z').toLocaleString('es-CO',{dateStyle:'short',timeStyle:'short'}) : '—';}
function showAlert(msg,ok){const a=document.getElementById('alert');a.className='alert '+(ok?'alert-success':'alert-danger');a.textContent=msg;a.classList.remove('d-none');}
async function api(p,o){return L.api(p,o);}

async function cargar(){
  try{
    const d=await api('comunicaciones/dashboard');
    const m=d.mes||{};
    document.getElementById('kTotal').textContent=m.total||0;
    document.getElementById('kEntregados').textContent=m.entregados||0;
    document.getElementById('kFallidos').textContent=m.fallidos||0;
    document.getElementById('kCosto').textContent=usd(m.costo);

    if(d.trial || d.dry_run || !d.smtp_configurado || d.twilio_configurado && !d.auth_token_configurado){
      const parts=[];
      if(d.trial) parts.push('<b>Cuenta Twilio en modo trial</b>: los SMS solo llegan a números verificados y WhatsApp requiere sandbox o sender aprobado.');
      if(d.dry_run) parts.push('<b>Simulación activa</b> (TWILIO_DRY_RUN=1): los envíos no salen a Twilio.');
      if(!d.smtp_configurado) parts.push('<b>SMTP no configurado</b>: los correos no se podrán enviar hasta definir SMTP_HOST/SMTP_USER/SMTP_PASS en .env.');
      if(d.twilio_configurado && !d.auth_token_configurado) parts.push('<b>Falta TWILIO_AUTH_TOKEN</b>: los webhooks de estado/entrantes se rechazarán por firma inválida.');
      const b=document.getElementById('trialBanner'); b.innerHTML=parts.join('<br>'); b.classList.remove('d-none');
    }

    document.getElementById('tbodyPlanes').innerHTML=(d.planes_proximos||[]).map(p=>`<tr>
      <td><a href="${U}modules/comunicaciones/pages/plan_detalle?id=${p.id}">${esc(p.nombre)}</a><div class="small text-muted">${esc(p.tipo||'')}</div></td>
      <td>${esc(p.canal)}</td><td>${esc(p.programado_para_local||'—')}</td>
      <td class="text-center">${badge(p.estado)}</td><td class="text-end">${p.total_destinatarios||0}</td>
    </tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted py-4">Sin planes programados</td></tr>';

    document.getElementById('tbodyUltimos').innerHTML=(d.ultimos||[]).map(x=>`<tr>
      <td>${fmt(x.fecha_creacion)}</td><td>${esc(x.plan_nombre||'')}</td>
      <td>${esc(x.nombre||x.destino)}<div class="small text-muted">${esc(x.destino)}</div></td>
      <td>${esc(x.canal)}</td><td class="text-center">${badge(x.estado)}</td>
      <td class="text-end">${x.precio_usd!=null?usd(x.precio_usd):'—'}</td>
    </tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-4">Sin mensajes</td></tr>';

    const st=document.getElementById('estadoConexion');
    st.innerHTML=`
      <div class="d-flex justify-content-between"><span>Twilio</span>${d.twilio_configurado?'<span class="badge text-bg-success">Configurado</span>':'<span class="badge text-bg-danger">Sin configurar</span>'}</div>
      <div class="d-flex justify-content-between mt-2"><span>SMTP email</span>${d.smtp_configurado?'<span class="badge text-bg-success">Configurado</span>':'<span class="badge text-bg-danger">Sin configurar</span>'}</div>
      <div class="d-flex justify-content-between mt-2"><span>Moneda</span><span class="fw-semibold">USD</span></div>
      <div class="d-flex justify-content-between mt-2"><span>Modo</span><span>${d.dry_run?'Simulación':'Real'}${d.trial?' · trial':''}</span></div>`;
  }catch(e){showAlert(e.message,false);}
}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
