<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('solicitudes.gestion');
$acc = ui_acciones('solicitudes.ticket_categorias', $staff['rol'] ?? '');
$canCrear = ui_accion('solicitudes.ticket_categorias', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('solicitudes.ticket_categorias', 'editar',   $staff['rol'] ?? '');
$canElim  = ui_accion('solicitudes.ticket_categorias', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Categorías de ticket';
$currentPage = 'ticket_categorias';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-folder-tree me-2"></i>Categorías de ticket</span>
    <?php if ($canCrear): ?>
    <button class="btn btn-light btn-sm" onclick="abrirEditor()"><i class="fas fa-plus me-1"></i>Nueva categoría</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle">
        <thead><tr><th>Nombre</th><th>Descripción</th><th>Activa</th><th></th></tr></thead>
        <tbody id="tbody"><tr><td colspan="4" class="text-center text-muted">Cargando…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="edModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Categoría de ticket</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="edId">
    <div class="mb-2"><label class="form-label small">Nombre</label><input class="form-control" id="edN"></div>
    <div class="mb-2"><label class="form-label small">Descripción</label><textarea class="form-control" id="edD" rows="2"></textarea></div>
    <div class="form-check"><input class="form-check-input" type="checkbox" id="edA" checked><label class="form-check-label" for="edA">Activa</label></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" onclick="guardar()">Guardar</button></div>
</div></div></div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['solicitudes.ticket_categorias.editar']===true,CAN_DEL=ACC['solicitudes.ticket_categorias.eliminar']===true;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
async function cargar(){
  try{const d=await api('solicitudes/ticket_categorias.php?todas=1');const rows=d.rows||[];
    document.getElementById('tbody').innerHTML=rows.map(c=>`<tr><td><b>${esc(c.nombre)}</b></td><td>${esc(c.descripcion||'')}</td><td>${c.estado_activo?'<span class="badge text-bg-success">Activa</span>':'<span class="badge text-bg-secondary">Inactiva</span>'}</td>
      <td class="text-end">${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary" onclick="editar(${c.id})"><i class="fas fa-edit"></i></button>`:''} ${CAN_DEL?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar(${c.id})"><i class="fas fa-trash"></i></button>`:''}</td></tr>`).join('')||'<tr><td colspan="4" class="text-center text-muted">Sin categorías</td></tr>';
  }catch(e){alert(e.message);}
}
let EDITING=null;
function abrirEditor(){EDITING=null;document.getElementById('edId').value='';document.getElementById('edN').value='';document.getElementById('edD').value='';document.getElementById('edA').checked=true;new bootstrap.Modal(document.getElementById('edModal')).show();}
function editar(id){api('solicitudes/ticket_categorias.php?todas=1').then(d=>{const c=(d.rows||[]).find(x=>x.id===id);if(!c)return;EDITING=c;document.getElementById('edId').value=c.id;document.getElementById('edN').value=c.nombre;document.getElementById('edD').value=c.descripcion||'';document.getElementById('edA').checked=!!c.estado_activo;new bootstrap.Modal(document.getElementById('edModal')).show();}).catch(e=>alert(e.message));}
async function guardar(){
  const payload={nombre:document.getElementById('edN').value.trim(),descripcion:document.getElementById('edD').value,estado_activo:document.getElementById('edA').checked?1:0};
  const id=document.getElementById('edId').value;if(id)payload.id=id;
  try{await api('solicitudes/ticket_categorias.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});bootstrap.Modal.getInstance(document.getElementById('edModal')).hide();cargar();}catch(e){alert(e.message);}
}
async function eliminar(id){if(!confirm('¿Eliminar esta categoría?'))return;try{await api('solicitudes/ticket_categorias.php?eliminar='+id,{method:'DELETE'});cargar();}catch(e){alert(e.message);}}
document.addEventListener('DOMContentLoaded',cargar);
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>