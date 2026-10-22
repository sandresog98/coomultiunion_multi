<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('chat.metricas');
$pageTitle = 'Asistente IA — Métricas';
$currentPage = 'chat_metricas';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="row g-3 mb-3" id="kpis">
  <div class="col-6 col-lg-3">
    <div class="card h-100"><div class="card-body">
      <div class="text-muted small"><i class="fas fa-comments me-1"></i>Conversaciones</div>
      <div class="fs-4 fw-bold" id="kSesiones">—</div>
      <div class="small text-muted" id="kSesionesSub"></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100"><div class="card-body">
      <div class="text-muted small"><i class="fas fa-message me-1"></i>Preguntas</div>
      <div class="fs-4 fw-bold" id="kPreguntas">—</div>
      <div class="small text-muted" id="kPreguntasSub"></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100"><div class="card-body">
      <div class="text-muted small"><i class="fas fa-coins me-1"></i>Tokens</div>
      <div class="fs-4 fw-bold" id="kTokens">—</div>
      <div class="small text-muted" id="kTokensSub"></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card h-100"><div class="card-body">
      <div class="text-muted small"><i class="fas fa-dollar-sign me-1"></i>Costo estimado (USD)</div>
      <div class="fs-4 fw-bold" id="kCosto">—</div>
      <div class="small text-muted" id="kCostoSub"></div>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-robot me-2"></i>Registro de conversaciones del asistente</span>
    <span class="small text-white-50" id="infoTotal"></span>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Usuario</label>
        <select class="form-select" id="fUsuario" data-searchable style="width:240px"><option value="">Todos</option></select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Estado</label>
        <select class="form-select" id="fEstado" style="width:130px"><option value="">Todos</option></select>
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Desde</label>
        <input type="date" class="form-control" id="fDesde" style="width:150px">
      </div>
      <div class="col-auto">
        <label class="form-label small text-muted mb-0">Hasta</label>
        <input type="date" class="form-control" id="fHasta" style="width:150px">
      </div>
      <div class="col-auto" style="max-width:280px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="q" placeholder="Título, cédula o nombre…">
        </div>
      </div>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
      <span class="text-muted small"><i class="fas fa-comment-dots me-1"></i>Feedback del asistente:</span>
      <span class="badge text-bg-success"><i class="fas fa-thumbs-up"></i> <span id="fbUtil">0</span> útiles</span>
      <span class="badge text-bg-danger"><i class="fas fa-thumbs-down"></i> <span id="fbNoUtil">0</span> no útiles</span>
      <span class="small text-muted" id="fbPct">—</span>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th class="sortable" data-col="id">#</th>
          <th class="sortable" data-col="cedula">Usuario</th>
          <th>Título</th>
          <th class="sortable text-end" data-col="mensajes">Mensajes</th>
          <th class="sortable text-end" data-col="tokens">Tokens</th>
          <th class="sortable text-end" data-col="costo_usd">Costo USD</th>
          <th class="sortable text-end" data-col="duracion_seg">Duración</th>
          <th class="sortable" data-col="estado">Estado</th>
          <th class="text-center">Feedback</th>
          <th class="sortable" data-col="fecha_creacion" style="min-width:130px">Inicio</th>
          <th></th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="11" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3" aria-label="Paginación"></nav>
  </div>
</div>

<!-- Modal: ver conversación (solo lectura) -->
<div class="modal fade" id="chatModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title"><i class="fas fa-comments me-2"></i><span id="chatModalTitle">Conversación</span></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body p-0">
    <div id="chatModalSub" class="px-3 py-2 border-bottom small text-muted"></div>
    <div id="chatModalBody" class="p-3"><div class="text-center text-muted p-3"><div class="spinner-border spinner-border-sm"></div> Cargando…</div></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
</div></div></div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const alertBox = document.getElementById('alert');
let state = { cedula: '', estado: '', desde: '', hasta: '', q: '', page: 0, size: 20, order: 'fecha_actualizacion', dir: 'DESC' };
let debounceTimer;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function intv(v){v=parseInt(v);return isNaN(v)?'0':v.toLocaleString('es-CO');}
function usd(v){v=Number(v)||0;return '$'+v.toLocaleString('en-US',{minimumFractionDigits:4,maximumFractionDigits:4});}
function dur(seg){
  seg = parseInt(seg)||0;
  if (seg <= 0) return '—';
  var m = Math.floor(seg/60), s = seg%60;
  if (m < 60) return m>0 ? m+' min '+(s?s+' s':'') : s+' s';
  var h = Math.floor(m/60); m = m%60;
  return h+' h '+(m?m+' min':'');
}
async function api(p){const r=await fetch(API+p);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

// ── Ordenamiento ──
document.querySelectorAll('.sortable').forEach(th=>{
  th.addEventListener('click',()=>{
    const col = th.dataset.col;
    if(state.order === col) state.dir = state.dir === 'ASC' ? 'DESC' : 'ASC';
    else { state.order = col; state.dir = 'ASC'; }
    state.page = 0;
    cargar();
  });
});
function renderSortIcons(){
  document.querySelectorAll('.sortable').forEach(th=>{
    const col = th.dataset.col;
    const active = state.order === col;
    th.classList.toggle('active', active);
    const txt = th.textContent.trim().replace(/[▲▼]/g,'');
    const arrow = active ? (state.dir === 'ASC' ? '▲' : '▼') : '';
    th.innerHTML = txt + (arrow ? ` <span class="sort-icon">${arrow}</span>` : '');
  });
}

function filtrosParams(){
  const p = [];
  {const v=document.getElementById('fUsuario').value.trim(); if(v) p.push('cedula='+encodeURIComponent(v));}
  {const v=document.getElementById('fEstado').value; if(v) p.push('estado='+encodeURIComponent(v));}
  {const v=document.getElementById('fDesde').value; if(v) p.push('fecha_desde='+encodeURIComponent(v));}
  {const v=document.getElementById('fHasta').value; if(v) p.push('fecha_hasta='+encodeURIComponent(v));}
  {const v=document.getElementById('q').value.trim(); if(v) p.push('q='+encodeURIComponent(v));}
  return p;
}
function buildParams(){
  const p = filtrosParams();
  p.push('limit='+state.size+'&offset='+(state.page*state.size)+'&order='+state.order+'&dir='+state.dir);
  return p.join('&');
}

document.getElementById('q').addEventListener('input',()=>{
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(()=>{ state.page = 0; cargar(); cargarResumen(); }, 300);
});
document.querySelectorAll('#fUsuario,#fEstado,#fDesde,#fHasta').forEach(el=>{
  el.addEventListener('change',()=>{ state.page = 0; cargar(); cargarResumen(); });
});

async function cargar(){
  try{
    const d = await api('chat/listar?'+buildParams());
    const total = d.total || 0, offset = d.offset || 0;
    document.getElementById('infoTotal').textContent = total > 0 ? `Mostrando ${offset+1}-${Math.min(offset+state.size, total)} de ${intv(total)}` : '';
    renderSortIcons();
    document.getElementById('tbody').innerHTML = (d.rows||[]).map(r=>`<tr>
      <td><small>#${r.id}</small></td>
      <td>${esc(r.nombre || r.cedula)}<br><small class="text-muted">${esc(r.cedula)}</small></td>
      <td class="text-truncate" style="max-width:240px">${esc(r.titulo||'—')}</td>
      <td class="text-end">${intv(r.mensajes)}</td>
      <td class="text-end">${intv(r.tokens_total)}</td>
      <td class="text-end">${usd(r.costo_usd)}</td>
      <td class="text-end">${dur(r.duracion_seg)}</td>
      <td>${(()=>{const e=String(r.estado||'');const m={activa:['success','Abierta'],cerrada:['secondary','Cerrada'],archivada:['dark','Archivada']};const x=m[e]||['secondary',e];return `<span class="badge text-bg-${x[0]}">${esc(x[1])}</span>`;})()}</td>
      <td class="text-center">${(r.fb_util||r.fb_no_util)?`<span class="text-success" title="Útil">👍 ${intv(r.fb_util||0)}</span> <span class="text-danger" title="No útil">👎 ${intv(r.fb_no_util||0)}</span>`:'<small class="text-muted">—</small>'}</td>
      <td><small>${r.fecha_creacion?new Date(String(r.fecha_creacion).replace(' ','T')).toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—'}</small></td>
      <td><button class="btn btn-sm btn-outline-primary" onclick="verChat(${r.id})" title="Ver conversación"><i class="fas fa-comments"></i></button></td>
    </tr>`).join('') || '<tr><td colspan="11" class="text-center text-muted">Sin resultados</td></tr>';
    renderPagination(total);
  }catch(e){alertBox.className='alert alert-danger';alertBox.textContent=e.message;alertBox.classList.remove('d-none');}
}

function renderPagination(total){
  const N = Math.ceil(total / state.size);
  if (N <= 1) { document.getElementById('paginacion').innerHTML = ''; return; }
  const C = state.page;
  let pages = [];
  if (N <= 10) { for (let i = 0; i < N; i++) pages.push(i); }
  else {
    pages.push(0);
    if (C > 3) pages.push('...');
    for (let i = Math.max(1, C-2); i <= Math.min(N-2, C+2); i++) pages.push(i);
    if (C < N-4) pages.push('...');
    pages.push(N-1);
  }
  let html = [];
  if (C > 0) html.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);
  pages.forEach(p => {
    if (p === '...') html.push('<li class="page-item disabled"><span class="page-link">…</span></li>');
    else html.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`);
  });
  if (C < N-1) html.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);
  document.getElementById('paginacion').innerHTML = `<ul class="pagination pagination-sm justify-content-center mb-0">${html.join('')}</ul>`;
}
function goPage(p){state.page = p; cargar();}

async function cargarResumen(conOpciones){
  try{
    const qs = filtrosParams().join('&');
    const d = await api('chat/resumen' + (qs ? '?'+qs : ''));
    const t = d.totales || {};
    document.getElementById('kSesiones').textContent = intv(t.sesiones);
    document.getElementById('kSesionesSub').textContent = `${intv(t.sesiones_activas)} abiertas · ${intv(t.sesiones_cerradas)} cerradas`;
    document.getElementById('kPreguntas').textContent = intv(t.preguntas);
    document.getElementById('kPreguntasSub').textContent = `${intv(t.respuestas)} respuestas del asistente`;
    document.getElementById('kTokens').textContent = intv(t.tokens);
    document.getElementById('kTokensSub').textContent = `${intv(t.tokens_prompt)} entrada · ${intv(t.tokens_completion)} salida`;
    document.getElementById('kCosto').textContent = usd(t.costo_usd);
    document.getElementById('kCostoSub').textContent = '';
    // Feedback
    const fb = d.feedback || {};
    const fu = parseInt(fb.utiles)||0, fn = parseInt(fb.no_utiles)||0, ftotal = fu+fn;
    document.getElementById('fbUtil').textContent = intv(fu);
    document.getElementById('fbNoUtil').textContent = intv(fn);
    document.getElementById('fbPct').textContent = ftotal ? `(${Math.round(fu*100/ftotal)}% útil) · ${intv(ftotal)} calificaciones` : 'Sin calificaciones aún';
    // Opciones de filtro (solo la primera vez; se mantienen globales para no perder opciones)
    if (conOpciones) {
      const sel = document.getElementById('fUsuario');
      sel.innerHTML = '<option value="">Todos</option>' + (d.cedulas||[]).map(c=>
        `<option value="${esc(c.cedula)}">${esc(c.nombre||c.cedula)} — ${esc(c.cedula)}</option>`).join('');
      const selE = document.getElementById('fEstado');
      selE.innerHTML = '<option value="">Todos</option>' + (d.estados||[]).map(e=>
        `<option value="${esc(e)}">${esc(e.charAt(0).toUpperCase()+e.slice(1))}</option>`).join('');
    }
  }catch(e){
    alertBox.className='alert alert-warning';alertBox.textContent='No se pudieron cargar los indicadores.';alertBox.classList.remove('d-none');
  }
}

let chatModal = null;
async function verChat(id){
  document.getElementById('chatModalTitle').textContent = 'Conversación #' + id;
  document.getElementById('chatModalSub').textContent = '';
  document.getElementById('chatModalBody').innerHTML = '<div class="text-center text-muted p-3"><div class="spinner-border spinner-border-sm"></div> Cargando…</div>';
  chatModal.show();
  try{
    const d = await api('chat/mensajes?id='+encodeURIComponent(id));
    const s = d.sesion || {}, msgs = d.mensajes || [];
    document.getElementById('chatModalTitle').textContent = 'Conversación #' + (s.id||id);
    document.getElementById('chatModalSub').innerHTML = `${esc(s.nombre||s.cedula||'')} · <code>${esc(s.cedula||'')}</code> · ${esc(s.titulo||'Sin título')} · <span class="badge text-bg-light">${esc(s.estado||'')}</span>`;
    if(!msgs.length){ document.getElementById('chatModalBody').innerHTML='<div class="text-muted text-center py-3">Sin mensajes.</div>'; return; }
    document.getElementById('chatModalBody').innerHTML = msgs.map(m=>{
      const esUser = m.rol === 'user';
      const tk = (parseInt(m.tokens_prompt)||0)+(parseInt(m.tokens_completion)||0);
      const meta = (!esUser && m.estado==='ok')
        ? `<div class="small text-muted mt-1">tokens ${intv(tk)} · ${usd(m.costo_usd)} · ${intv(m.latencia_ms)} ms</div>` : '';
      const fecha = m.fecha_creacion ? new Date(String(m.fecha_creacion).replace(' ','T')).toLocaleString('es-CO',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}) : '';
      const est = (m.estado && m.estado!=='ok') ? ' · ' + esc(m.estado) : '';
      const rated = (!esUser && (m.util === 1 || m.util === 0));
      const fb = rated ? ` <span class="badge ${m.util===1?'text-bg-success':'text-bg-danger'}">${m.util===1?'👍 Útil':'👎 No útil'}</span>` : '';
      const fbCom = (rated && m.comentario) ? `<div class="small fst-italic text-muted">"${esc(m.comentario)}"</div>` : '';
      return `<div class="mb-3 ${esUser?'text-end':''}">
        <div class="d-inline-block text-start p-2 rounded ${esUser?'bg-primary text-white':'bg-light border'}" style="max-width:85%;white-space:pre-wrap;word-break:break-word">${esc(m.contenido||'')}</div>
        <div class="small text-muted">${esUser?'Asociado':'Asistente'}${fecha?' · '+fecha:''}${est}${fb}</div>
        ${meta}
        ${fbCom}
      </div>`;
    }).join('');
  }catch(e){
    document.getElementById('chatModalBody').innerHTML = `<div class="alert alert-danger mb-0">${esc(e.message)}</div>`;
  }
}

document.addEventListener('DOMContentLoaded',()=>{
  chatModal = new bootstrap.Modal('#chatModal');
  cargarResumen(true);
  cargar();
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
