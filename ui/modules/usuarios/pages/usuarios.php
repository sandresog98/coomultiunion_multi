<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('usuarios.gestion');
$acc = ui_acciones('usuarios', $staff['rol'] ?? '');
$canCrear  = ui_accion('usuarios', 'crear',    $staff['rol'] ?? '');
$canEdit   = ui_accion('usuarios', 'editar',   $staff['rol'] ?? '');
$canReset  = ui_accion('usuarios', 'resetear', $staff['rol'] ?? '');
$canElim   = ui_accion('usuarios', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Usuarios';
$currentPage = 'usuarios';

// Roles disponibles = claves de ui/config/secciones.php (fuente única de permisos) + 'admin' (super-rol).
$roles = \Permisos::listaRoles();
if (!in_array('admin', $roles, true)) { array_unshift($roles, 'admin'); }

require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-user-cog me-2"></i>Usuarios internos</span>
    <?php if ($canCrear): ?>
    <button class="btn btn-light btn-sm" onclick="abrirCrear()"><i class="fas fa-plus me-1"></i>Nuevo</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="row g-2 mb-3 align-items-end">
      <div class="col-auto" style="max-width:300px">
        <label class="form-label small text-muted mb-0">Buscar</label>
        <div class="input-group">
          <span class="input-group-text"><i class="fas fa-search"></i></span>
          <input class="form-control" id="q" placeholder="Usuario o nombre…">
        </div>
      </div>
      <div class="col-auto ms-auto">
        <span class="small text-muted" id="infoTotal"></span>
      </div>
    </div>
    <div id="alert" class="alert d-none"></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr>
          <th>Usuario</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th>
        </tr></thead>
        <tbody id="tbody"><tr><td colspan="6" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
    <nav id="paginacion" class="d-flex justify-content-center mt-3" aria-label="Paginación"></nav>
  </div>
</div>

<!-- Modal crear/editar -->
<div class="modal fade" id="modalUser" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="modalTitle">Nuevo usuario</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" id="f_id">
      <div class="mb-3" id="wrapUsuario">
        <label class="form-label">Usuario</label>
        <input class="form-control" id="f_usuario">
      </div>
      <div class="mb-3"><label class="form-label">Nombre completo</label>
        <input class="form-control" id="f_nombre"></div>
      <div class="mb-3"><label class="form-label">Email</label>
        <input class="form-control" id="f_email" type="email"></div>
      <div class="mb-3"><label class="form-label">Rol</label>
        <select class="form-select" id="f_rol">
          <?php foreach ($roles as $r): ?><option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option><?php endforeach; ?>
        </select></div>
      <div class="mb-3 form-check" id="wrapEstado" style="display:none">
        <input class="form-check-input" type="checkbox" id="f_activo" checked>
        <label class="form-check-label" for="f_activo">Activo</label>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      <button class="btn btn-brand" id="btnGuardar" onclick="guardar()">Guardar</button>
    </div>
  </div></div>
</div>

<!-- Modal contraseña temporal -->
<div class="modal fade" id="modalPass" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Contraseña temporal</h5>
      <button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <p class="text-muted">Entrega esta contraseña al usuario. Deberá cambiarla al ingresar.</p>
      <div class="input-group">
        <input class="form-control fw-bold" id="tempPass" readonly>
        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('tempPass').value)"><i class="fas fa-copy"></i></button>
      </div>
    </div>
  </div></div>
</div>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const ACC = <?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['usuarios.editar']===true,CAN_RESET=ACC['usuarios.resetear']===true,CAN_ELIM=ACC['usuarios.eliminar']===true;
const alertBox = document.getElementById('alert');
const PAGE_SIZE = 20;
let state = { q: '', page: 0 };
let debounceTimer;
let modalUser, modalPass;

function showAlert(msg, ok=false){ alertBox.className='alert alert-'+(ok?'success':'danger'); alertBox.textContent=msg; alertBox.classList.remove('d-none'); }
async function api(path, opt){ const r=await fetch(API+path, opt); const j=await r.json(); if(!j.ok) throw new Error(j.error||'Error'); return j.data; }

async function cargar(){
  const off = state.page * PAGE_SIZE;
  const qs = `q=${encodeURIComponent(state.q)}&limit=${PAGE_SIZE}&offset=${off}`;
  try{
    const d = await api('usuarios/listar?'+qs);
    const total = d.total || 0;
    document.getElementById('infoTotal').textContent = total > 0 ? `Mostrando ${off+1}-${Math.min(off+PAGE_SIZE, total)} de ${total}` : '';
    const rows = d.rows || [];
    document.getElementById('tbody').innerHTML = rows.map(u => `
      <tr>
        <td>${esc(u.usuario)}</td><td>${esc(u.nombre_completo)}</td><td>${esc(u.email||'')}</td>
        <td><span class="badge text-bg-secondary">${esc(u.rol)}</span></td>
        <td>${u.estado_activo==1?'<span class="badge text-bg-success">Activo</span>':'<span class="badge text-bg-danger">Inactivo</span>'}</td>
        <td class="text-end">
          ${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary" onclick='abrirEditar(${JSON.stringify(u)})'><i class="fas fa-pen"></i></button>`:''}
          ${CAN_RESET?`<button class="btn btn-sm btn-outline-warning" onclick="resetear(${u.id})"><i class="fas fa-key"></i></button>`:''}
          ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar(${u.id})"><i class="fas fa-user-slash"></i></button>`:''}
        </td>
      </tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted">Sin usuarios</td></tr>';
    renderPagination(total);
  }catch(e){ showAlert(e.message); }
}
function renderPagination(total){
  const N = Math.ceil(total / PAGE_SIZE);
  const nav = document.getElementById('paginacion');
  if (N <= 1) { nav.innerHTML = ''; return; }
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
  let h = [];
  if (C > 0) h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C-1})">«</a></li>`);
  pages.forEach(p => { if (p === '...') h.push('<li class="page-item disabled"><span class="page-link">…</span></li>'); else h.push(`<li class="page-item ${p===C?'active':''}"><a class="page-link" href="#" onclick="goPage(${p})">${p+1}</a></li>`); });
  if (C < N-1) h.push(`<li class="page-item"><a class="page-link" href="#" onclick="goPage(${C+1})">»</a></li>`);
  nav.innerHTML = `<ul class="pagination pagination-sm justify-content-center mb-0">${h.join('')}</ul>`;
}
function goPage(p){ state.page = p; cargar(); }

document.getElementById('q').addEventListener('input', () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => { state.q = document.getElementById('q').value.trim(); state.page = 0; cargar(); }, 300);
});
function esc(s){ return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

function abrirCrear(){
  document.getElementById('modalTitle').textContent='Nuevo usuario';
  document.getElementById('f_id').value='';
  document.getElementById('f_usuario').value='';
  document.getElementById('f_nombre').value='';
  document.getElementById('f_email').value='';
  document.getElementById('wrapUsuario').style.display='';
  document.getElementById('wrapEstado').style.display='none';
  modalUser.show();
}
function abrirEditar(u){
  document.getElementById('modalTitle').textContent='Editar usuario';
  document.getElementById('f_id').value=u.id;
  document.getElementById('f_usuario').value=u.usuario;
  document.getElementById('f_nombre').value=u.nombre_completo;
  document.getElementById('f_email').value=u.email||'';
  document.getElementById('f_rol').value=u.rol;
  document.getElementById('f_activo').checked=(u.estado_activo==1);
  document.getElementById('wrapUsuario').style.display='none';
  document.getElementById('wrapEstado').style.display='';
  modalUser.show();
}
async function guardar(){
  const id=document.getElementById('f_id').value;
  const payload={
    nombre_completo:document.getElementById('f_nombre').value.trim(),
    email:document.getElementById('f_email').value.trim(),
    rol:document.getElementById('f_rol').value
  };
  try{
    if(id){
      payload.id=Number(id); payload.estado_activo=document.getElementById('f_activo').checked;
      await api('usuarios/editar',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      showAlert('Usuario actualizado',true);
    }else{
      payload.usuario=document.getElementById('f_usuario').value.trim();
      const d=await api('usuarios/crear',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      document.getElementById('tempPass').value=d.password_temporal; modalPass.show();
    }
    modalUser.hide(); cargar();
  }catch(e){ showAlert(e.message); }
}
async function resetear(id){
  if(!confirm('¿Restablecer la contraseña de este usuario?')) return;
  try{ const d=await api('usuarios/resetear',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    document.getElementById('tempPass').value=d.password_temporal; modalPass.show();
  }catch(e){ showAlert(e.message); }
}
async function eliminar(id){
  if(!confirm('¿Desactivar este usuario?')) return;
  try{ await api('usuarios/eliminar',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    showAlert('Usuario desactivado',true); cargar();
  }catch(e){ showAlert(e.message); }
}
document.addEventListener('DOMContentLoaded',()=>{
  modalUser=new bootstrap.Modal('#modalUser'); modalPass=new bootstrap.Modal('#modalPass'); cargar();
});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
