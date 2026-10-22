<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('api');
$acc   = ui_acciones('api.authorized_endpoints', $staff['rol'] ?? '');
$canToggle  = $acc['api.authorized_endpoints.toggle']  ?? false;
$canCrear   = $acc['api.authorized_endpoints.crear']   ?? false;
$canRevocar = $acc['api.authorized_endpoints.revocar'] ?? false;
$canRotar   = $acc['api.authorized_endpoints.rotar']   ?? false;
$pageTitle   = 'API · Endpoints autorizados';
$currentPage = 'api_authorized_endpoints';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<style>
.ep-tabla td { vertical-align: middle; }
.ep-clave { font-family: monospace; font-size: .85rem; }
.ep-secreto { font-family: monospace; word-break: break-all; background:#0b2f5f; color:#fff; padding:.6rem .75rem; border-radius:.5rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
  <ul class="nav nav-pills" id="epsTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-endpoints-btn" data-bs-toggle="pill" data-bs-target="#tab-endpoints" type="button" role="tab"><i class="fas fa-plug me-1"></i>Endpoints autorizados</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-tokens-btn" data-bs-toggle="pill" data-bs-target="#tab-tokens" type="button" role="tab"><i class="fas fa-key me-1"></i>Tokens</button>
    </li>
  </ul>
</div>

<div class="tab-content">
  <!-- ── Endpoints autorizados ─────────────────────────────────────── -->
  <div class="tab-pane fade show active" id="tab-endpoints" role="tabpanel">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-plug me-2"></i>Endpoints autorizados para curl</span>
        <span class="d-flex align-items-center gap-2">
          <input class="form-control form-control-sm" id="epBuscar" placeholder="Buscar clave…" style="width:220px">
          <select class="form-select form-select-sm" id="epFiltro" style="width:auto">
            <option value="">Todos</option>
            <option value="autorizado">Solo autorizados</option>
            <option value="accion">Acciones</option>
            <option value="modulo">Módulos</option>
          </select>
        </span>
      </div>
      <div class="card-body">
        <p class="text-muted small">
          Marca qué claves (operaciones o módulos de <code>secciones.php</code>) pueden invocarse con un token
          Bearer. Solo las claves marcadas aquí son utilizables por integraciones externas.
          <?php if (!$canToggle): ?><span class="text-danger">No tienes permiso para modificar.</span><?php endif; ?>
        </p>
        <div class="table-responsive">
          <table class="table table-sm table-hover ep-tabla mb-0">
            <thead><tr><th style="width:70px">Autorizado</th><th style="width:90px">Tipo</th><th>Clave</th></tr></thead>
            <tbody id="epTbody"><tr><td colspan="3" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Cargando…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Tokens ────────────────────────────────────────────────────── -->
  <div class="tab-pane fade" id="tab-tokens" role="tabpanel">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-key me-2"></i>Tokens de integración</span>
        <?php if ($canCrear): ?>
        <button class="btn btn-light btn-sm" onclick="abrirNuevoToken()"><i class="fas fa-plus me-1"></i>Nuevo token</button>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr>
              <th>Nombre</th><th>Prefijo</th><th>Usuario</th><th>Scopes</th>
              <th>Vence</th><th>Último uso</th><th>Estado</th><th></th>
            </tr></thead>
            <tbody id="tkTbody"><tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-2"></i>Cargando…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Nuevo token -->
<?php if ($canCrear): ?>
<div class="modal fade" id="nuevoTokenModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-key me-1"></i>Nuevo token</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre <span class="text-danger">*</span></label>
            <input class="form-control" id="ntNombre" maxlength="120" placeholder="Ej: Integración bot / ERP">
          </div>
          <div class="col-md-6">
            <label class="form-label">Usuario <span class="text-danger">*</span></label>
            <select class="form-select" id="ntUsuario"></select>
            <div class="form-text">El token hereda el rol del usuario (nunca excede sus permisos).</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Vigencia (días)</label>
            <input type="number" class="form-control" id="ntDias" value="180" min="1" max="365">
            <div class="form-text">Expiración obligatoria. Por defecto 180 días (6 meses).</div>
          </div>
          <div class="col-12">
            <label class="form-label d-flex justify-content-between align-items-center">
              <span>Endpoints autorizados para este token <span class="text-danger">*</span></span>
              <span class="small">
                <a href="#" onclick="ntTodos(true);return false;">Todos</a> ·
                <a href="#" onclick="ntTodos(false);return false;">Ninguno</a>
              </span>
            </label>
            <div id="ntScopes" class="border rounded p-2" style="max-height:260px;overflow:auto"><span class="text-muted small">Cargando…</span></div>
          </div>
        </div>
        <div class="text-danger small mt-2" id="ntMsg"></div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="crearToken()"><i class="fas fa-save me-1"></i>Crear token</button></div>
    </div>
  </div>
</div>

<!-- Modal secreto (una sola vez) -->
<div class="modal fade" id="secretoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title text-success"><i class="fas fa-check-circle me-1"></i>Token creado / rotado</h5></div>
      <div class="modal-body">
        <div class="alert alert-warning py-2 small mb-3"><i class="fas fa-triangle-exclamation me-1"></i>Copie el token ahora. <strong>No se volverá a mostrar.</strong></div>
        <div class="ep-secreto" id="scSecreto"></div>
        <div class="mt-2"><button class="btn btn-sm btn-outline-primary" onclick="copiarSecreto()"><i class="fas fa-copy me-1"></i>Copiar</button> <span class="small text-success" id="scCopiado"></span></div>
      </div>
      <div class="modal-footer"><button class="btn btn-brand" data-bs-dismiss="modal">Entendido</button></div>
    </div>
  </div>
</div>
<?php endif; ?>

<div id="toasts" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:1080"></div>

<script>
window.API = <?php echo json_encode(apiUrl()); ?>;
const CAN_TOGGLE = <?php echo $canToggle ? 'true' : 'false'; ?>;
const CAN_REVOKAR = <?php echo $canRevocar ? 'true' : 'false'; ?>;
const CAN_ROTAR = <?php echo $canRotar ? 'true' : 'false'; ?>;
const L = window.Listado, esc = L.esc;
let EP = [];               // catálogo de endpoints
let TOKEN_MODAL = null, SECRETO_MODAL = null;

function toast(msg, type){
  type = type || 'success';
  const box = document.getElementById('toasts'); if(!box) return;
  const el = document.createElement('div');
  el.className = 'toast align-items-center text-bg-' + type + ' border-0';
  el.innerHTML = '<div class="d-flex"><div class="toast-body">' + esc(msg) + '</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  box.appendChild(el); new bootstrap.Toast(el, {delay:4000}).show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}
function apiGet(p){ return L.api(p); }
function apiPost(p, body){
  return fetch(window.API + p, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body||{})})
    .then(r => r.json()).then(j => { if(!j.ok) throw new Error(j.error || 'Error'); return j.data; });
}
function fmt(v){ if(!v) return '—'; const d = new Date(v); return isNaN(d.getTime()) ? '—' : d.toLocaleString('es-CO', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}); }
function expiraBadge(t){
  if(!t.activo || t.revoked_at) return '';
  const d = new Date(t.expires_at); if(isNaN(d.getTime())) return '';
  const dias = Math.ceil((d.getTime() - Date.now()) / 86400000);
  if(dias < 0) return '';
  return dias <= 30
    ? ' <span class="badge text-bg-warning" title="Próximo a vencer">' + dias + 'd</span>'
    : ' <span class="text-muted small">' + dias + 'd</span>';
}

/* ── Endpoints autorizados ─────────────────────────────────────────── */
async function cargarEndpoints(){
  const tb = document.getElementById('epTbody');
  try{
    const d = await apiGet('api_endpoints/listar');
    EP = d.rows || [];
    renderEndpoints();
  }catch(e){ tb.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">' + esc(e.message) + '</td></tr>'; }
}
function renderEndpoints(){
  const q = (document.getElementById('epBuscar').value || '').toLowerCase();
  const f = document.getElementById('epFiltro').value;
  const rows = EP.filter(r => {
    if(q && !r.clave.toLowerCase().includes(q)) return false;
    if(f === 'autorizado' && !r.autorizado) return false;
    if(f === 'accion' && r.tipo !== 'accion') return false;
    if(f === 'modulo' && r.tipo !== 'modulo') return false;
    return true;
  });
  const tb = document.getElementById('epTbody');
  tb.innerHTML = rows.map(r => {
    const badge = r.tipo === 'accion' ? 'text-bg-warning' : (r.tipo === 'modulo' ? 'text-bg-secondary' : 'text-bg-light');
    const sw = '<input class="form-check-input" type="checkbox" ' + (r.autorizado ? 'checked' : '') +
               (CAN_TOGGLE ? '' : ' disabled') + ' onchange="toggleEndpoint(\'' + esc(r.clave) + '\', this.checked, this)">';
    return '<tr><td>' + sw + '</td><td><span class="badge ' + badge + '">' + esc(r.tipo) + '</span></td>'
         + '<td class="ep-clave">' + esc(r.clave) + '</td></tr>';
  }).join('') || '<tr><td colspan="3" class="text-center text-muted py-4">Sin resultados</td></tr>';
}
async function toggleEndpoint(clave, activo, el){
  el.disabled = true;
  try{
    await apiPost('api_endpoints/toggle', {clave: clave, activo: activo ? 1 : 0});
    const it = EP.find(x => x.clave === clave); if(it) it.autorizado = !!activo;
    toast(activo ? 'Endpoint autorizado.' : 'Endpoint desautorizado.');
  }catch(e){ el.checked = !activo; toast(e.message, 'danger'); }
  finally{ el.disabled = !CAN_TOGGLE; }
}

/* ── Tokens ────────────────────────────────────────────────────────── */
async function cargarTokens(){
  const tb = document.getElementById('tkTbody');
  try{
    const rows = await apiGet('api_tokens/listar');
    tb.innerHTML = (rows || []).map(t => {
      const estado = t.revoked_at ? '<span class="badge text-bg-dark">revocado</span>'
        : (t.expirado ? '<span class="badge text-bg-warning">expirado</span>'
        : (t.activo ? '<span class="badge text-bg-success">activo</span>' : '<span class="badge text-bg-secondary">inactivo</span>'));
      const scopes = (t.scopes || []).map(s => '<span class="badge text-bg-light border me-1 mb-1 ep-clave">' + esc(s) + '</span>').join('') || '—';
      let btn = '';
      if (t.activo && !t.revoked_at) {
        if (CAN_ROTAR) btn += '<button class="btn btn-sm btn-outline-secondary me-1" title="Rotar (nuevo secreto)" onclick="rotarToken(' + t.id + ')"><i class="fas fa-arrows-rotate"></i></button>';
        if (CAN_REVOKAR) btn += '<button class="btn btn-sm btn-outline-danger" title="Revocar" onclick="revocarToken(' + t.id + ')"><i class="fas fa-ban"></i></button>';
      }
      return '<tr><td>' + esc(t.nombre) + '</td>'
        + '<td class="ep-clave">' + esc(t.prefijo) + '…</td>'
        + '<td>' + esc(t.nombre_completo || t.usuario || ('#' + t.actor_id)) + ' <span class="badge text-bg-light">' + esc(t.rol || '') + '</span></td>'
        + '<td style="max-width:320px">' + scopes + '</td>'
        + '<td><small>' + fmt(t.expires_at) + '</small>' + expiraBadge(t) + '</td>'
        + '<td><small>' + fmt(t.last_used_at) + '</small></td>'
        + '<td>' + estado + '</td>'
        + '<td class="text-end">' + btn + '</td></tr>';
    }).join('') || '<tr><td colspan="8" class="text-center text-muted py-4">Aún no hay tokens</td></tr>';
  }catch(e){ tb.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">' + esc(e.message) + '</td></tr>'; }
}
async function revocarToken(id){
  if(!confirm('¿Revocar este token? La integración perderá acceso de inmediato.')) return;
  try{ await apiPost('api_tokens/revocar', {id: id}); toast('Token revocado.'); cargarTokens(); }
  catch(e){ toast(e.message, 'danger'); }
}
async function rotarToken(id){
  const dias = prompt('Vigencia del nuevo token (días):', '180');
  if(dias === null) return;
  try{
    const r = await apiPost('api_tokens/rotar', {id: id, dias: parseInt(dias, 10) || 180});
    document.getElementById('scSecreto').textContent = r.secreto;
    document.getElementById('scCopiado').textContent = '';
    if(!SECRETO_MODAL) SECRETO_MODAL = new bootstrap.Modal(document.getElementById('secretoModal'));
    SECRETO_MODAL.show();
    cargarTokens();
  }catch(e){ toast(e.message, 'danger'); }
}

/* ── Crear token ───────────────────────────────────────────────────── */
async function abrirNuevoToken(){
  document.getElementById('ntNombre').value = '';
  document.getElementById('ntDias').value = 180;
  document.getElementById('ntMsg').textContent = '';
  const sel = document.getElementById('ntUsuario');
  sel.innerHTML = '<option value="">Cargando…</option>';
  const box = document.getElementById('ntScopes');
  box.innerHTML = '<span class="text-muted small">Cargando…</span>';
  if(!TOKEN_MODAL) TOKEN_MODAL = new bootstrap.Modal(document.getElementById('nuevoTokenModal'));
  TOKEN_MODAL.show();
  try{
    const us = await apiGet('api_tokens/usuarios');
    sel.innerHTML = '<option value="">— Seleccione —</option>' + us.map(u => '<option value="' + u.id + '">' + esc(u.nombre_completo || u.usuario) + ' (' + esc(u.usuario) + ' · ' + esc(u.rol) + ')</option>').join('');
    const ep = await apiGet('api_endpoints/listar');
    const auth = (ep.rows || []).filter(r => r.autorizado);
    box.innerHTML = auth.length ? auth.map(r => {
      const id = 'sc_' + btoa(r.clave).replace(/[^a-zA-Z0-9]/g, '');
      return '<div class="form-check"><input class="form-check-input nt-scope" type="checkbox" value="' + esc(r.clave) + '" id="' + id + '">'
        + '<label class="form-check-label ep-clave" for="' + id + '">' + esc(r.clave) + '</label></div>';
    }).join('') : '<div class="alert alert-warning mb-0 py-2 small">No hay endpoints autorizados. Autorízalos en la pestaña anterior.</div>';
  }catch(e){ box.innerHTML = '<span class="text-danger small">' + esc(e.message) + '</span>'; }
}
function ntTodos(v){ document.querySelectorAll('.nt-scope').forEach(c => c.checked = v); }
async function crearToken(){
  const msg = document.getElementById('ntMsg');
  const nombre = document.getElementById('ntNombre').value.trim();
  const actor_id = parseInt(document.getElementById('ntUsuario').value, 10) || 0;
  const dias = parseInt(document.getElementById('ntDias').value, 10) || 180;
  const scopes = Array.from(document.querySelectorAll('.nt-scope:checked')).map(c => c.value);
  if(!nombre){ msg.textContent = 'Ingrese el nombre.'; return; }
  if(!actor_id){ msg.textContent = 'Seleccione el usuario.'; return; }
  if(!scopes.length){ msg.textContent = 'Seleccione al menos un endpoint.'; return; }
  msg.textContent = 'Creando…';
  try{
    const r = await apiPost('api_tokens/crear', {nombre: nombre, actor_id: actor_id, dias: dias, scopes: scopes});
    TOKEN_MODAL.hide();
    document.getElementById('scSecreto').textContent = r.secreto;
    document.getElementById('scCopiado').textContent = '';
    if(!SECRETO_MODAL) SECRETO_MODAL = new bootstrap.Modal(document.getElementById('secretoModal'));
    SECRETO_MODAL.show();
    cargarTokens();
  }catch(e){ msg.textContent = e.message; }
}
function copiarSecreto(){
  const t = document.getElementById('scSecreto').textContent;
  navigator.clipboard.writeText(t).then(() => { document.getElementById('scCopiado').textContent = '¡Copiado!'; },
    () => { document.getElementById('scCopiado').textContent = 'Copia manualmente.'; });
}

document.getElementById('epBuscar').addEventListener('input', renderEndpoints);
document.getElementById('epFiltro').addEventListener('change', renderEndpoints);
cargarEndpoints();
cargarTokens();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
