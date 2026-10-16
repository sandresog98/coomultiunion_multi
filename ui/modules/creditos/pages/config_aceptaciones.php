<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('creditos');
$acc = ui_acciones('creditos.config_aceptaciones', $staff['rol'] ?? '');
$canCrear  = ui_accion('creditos.config_aceptaciones', 'crear',  $staff['rol'] ?? '');
$canEditar = ui_accion('creditos.config_aceptaciones', 'editar', $staff['rol'] ?? '');
$canElim   = ui_accion('creditos.config_aceptaciones', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Créditos · Aceptación';
$currentPage = 'creditos_config_aceptaciones';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-check-double me-2"></i>Aceptación y Autorizaciones</span>
    <?php if ($canCrear): ?>
    <button class="btn btn-light btn-sm" onclick="nuevaAcept()"><i class="fas fa-plus me-1"></i>Nueva aceptación</button>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <div class="table-responsive"><table class="table table-sm table-hover align-middle"><thead><tr><th>Tipo</th><th>Título</th><th>Texto</th><th class="text-center">Oblig</th><th class="text-end">Acciones</th></tr></thead><tbody id="tbodyAcept"><tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr></tbody></table></div>
  </div>
</div>

<!-- MODAL ACEPTACIÓN -->
<div class="modal fade" id="mAcept" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="mtAcept">Nueva aceptación</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-2"><label class="form-label">Tipo</label>
      <select class="form-select" id="a_tipo"><option value="aceptacion">Aceptación</option><option value="texto">Texto informativo</option></select>
    </div>
    <div class="mb-2"><label class="form-label">Título</label><input class="form-control" id="a_titulo"></div>
    <div class="mb-2"><label class="form-label">Texto</label><textarea class="form-control" id="a_texto" rows="2"></textarea></div>
    <div class="d-flex gap-4">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="a_oblig"><label class="form-check-label" for="a_oblig">Obligatorio</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" id="a_vis" checked><label class="form-check-label" for="a_vis">Visible en staff</label></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="guardarAcept()"><i class="fas fa-save me-1"></i>Guardar</button></div>
</div></div></div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT=ACC['creditos.config_aceptaciones.editar']===true,CAN_DEL=ACC['creditos.config_aceptaciones.eliminar']===true;
let editAceptId=null, modalAcept;

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function slug(s){return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'').slice(0,30);}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

async function cargarAcept(){try{const a=await api('creditos/config?ent=aceptaciones');
  document.getElementById('tbodyAcept').innerHTML=a.map(x=>`<tr><td>${esc(x.tipo)}</td><td>${esc(x.titulo||'')}</td><td class="text-truncate" style="max-width:300px">${esc(x.texto||'')}</td><td class="text-center">${x.obligatorio?'Sí':'No'}</td>
    <td class="text-end">${CAN_EDIT?`<button class="btn btn-sm btn-outline-primary me-1" onclick='abrirAcept(${JSON.stringify(x)})'><i class="fas fa-edit"></i></button>`:''}${CAN_DEL?`<button class="btn btn-sm btn-outline-danger" onclick="delAcept(${x.id})"><i class="fas fa-trash"></i></button>`:''}</td></tr>`).join('')||'<tr><td colspan="5" class="text-center text-muted">Sin aceptaciones</td></tr>';
}catch(e){}}
function nuevaAcept(){editAceptId=null;document.getElementById('mtAcept').textContent='Nueva aceptación';document.getElementById('a_tipo').value='aceptacion';document.getElementById('a_titulo').value='';document.getElementById('a_texto').value='';document.getElementById('a_oblig').checked=false;document.getElementById('a_vis').checked=true;modalAcept.show();}
function abrirAcept(a){editAceptId=a.id;document.getElementById('mtAcept').textContent='Editar aceptación';document.getElementById('a_tipo').value=a.tipo||'aceptacion';document.getElementById('a_titulo').value=a.titulo||'';document.getElementById('a_texto').value=a.texto||'';document.getElementById('a_oblig').checked=!!a.obligatorio;const v=Array.isArray(a.visible_en)?a.visible_en:['staff','cx'];document.getElementById('a_vis').checked=v.includes('staff');modalAcept.show();}
async function guardarAcept(){
  const d={tipo:document.getElementById('a_tipo').value,titulo:document.getElementById('a_titulo').value.trim()||null,texto:document.getElementById('a_texto').value,obligatorio:document.getElementById('a_oblig').checked?1:0,visible_en:['staff','cx'],estado_activo:1};
  try{
    if(editAceptId)await api('creditos/config?ent=aceptaciones',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:editAceptId,...d})});
    else await api('creditos/config?ent=aceptaciones',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({clave:slug(d.titulo||d.tipo)||'acep_'+(Date.now()%10000),...d})});
    modalAcept.hide();cargarAcept();
  }catch(e){alert(e.message);}
}
async function delAcept(id){if(!confirm('¿Eliminar aceptación?'))return;try{await api('creditos/config?ent=aceptaciones',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});cargarAcept();}catch(e){alert(e.message);}}

document.addEventListener('DOMContentLoaded',()=>{modalAcept=new bootstrap.Modal('#mAcept');cargarAcept();});
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>