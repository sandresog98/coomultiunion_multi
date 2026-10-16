<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.rangos_precio');
$acc = ui_acciones('tienda.rangos_precio', $staff['rol'] ?? '');
$canCrear = ui_accion('tienda.rangos_precio', 'crear',    $staff['rol'] ?? '');
$canEdit  = ui_accion('tienda.rangos_precio', 'editar',   $staff['rol'] ?? '');
$canElim  = ui_accion('tienda.rangos_precio', 'eliminar', $staff['rol'] ?? '');
$pageTitle = 'Rangos de precio';
$currentPage = 'tienda_rangos_precio';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="row g-3">
  <div class="col-md-5">
    <div class="card">
      <div class="card-header brand"><i class="fas fa-sliders-h me-2"></i>Nuevo rango</div>
      <div class="card-body">
        <div id="alert" class="alert d-none"></div>
        <input type="hidden" id="rId">
        <div class="row g-2">
          <div class="col-4"><label class="form-label small text-muted mb-0">Desde (*)</label>
            <input type="number" class="form-control" id="rDesde" min="0" step="1"></div>
          <div class="col-4"><label class="form-label small text-muted mb-0">Hasta (*)</label>
            <input type="number" class="form-control" id="rHasta" min="0" step="1"></div>
          <div class="col-4"><label class="form-label small text-muted mb-0">% descuento</label>
            <input type="number" class="form-control" id="rPct" min="0" max="100" step="0.01"></div>
        </div>
        <div class="small text-muted mt-2">Precio Multiunión = ROUND(precio − (precio × % / 100), −3).</div>
        <div class="d-flex gap-2 mt-3">
          <?php if ($canCrear || $canEdit): ?>
          <button class="btn btn-brand" onclick="guardar()"><i class="fas fa-check me-1"></i>Guardar</button>
          <?php endif; ?>
          <button class="btn btn-secondary" style="display:none" id="btnCancelar" onclick="cancelar()">Cancelar edición</button>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-7">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Rangos existentes</span>
        <span class="small text-white-50" id="infoR"></span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead><tr><th class="text-end">Desde</th><th class="text-end">Hasta</th><th class="text-center">%</th><th></th></tr></thead>
            <tbody id="tbody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
const ACC=<?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const CAN_EDIT_R=ACC['tienda.rangos_precio.editar']===true,CAN_ELIM=ACC['tienda.rangos_precio.eliminar']===true;
const alertBox=document.getElementById('alert');
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
async function api(p,o){const r=await fetch(API+p,o);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}
function post(p,d){return api(p,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});}
function showA(m,ok){alertBox.className='alert '+(ok?'alert-success':'alert-danger');alertBox.textContent=m;alertBox.classList.remove('d-none');}
async function cargar(){
  try{
    const d=await api('tienda/rangos_precio.php?action=listar');
    document.getElementById('infoR').textContent=d.length+(d.length===1?' rango':' rangos');
    document.getElementById('tbody').innerHTML=d.map(r=>`<tr>
      <td class="text-end">${money(r.desde)}</td><td class="text-end">${money(r.hasta)}</td>
      <td class="text-center">${Number(r.porcentaje)}%</td>
      <td class="text-end">${CAN_EDIT_R?`<button class="btn btn-sm btn-outline-primary" onclick="editar(${r.id},'${r.desde}','${r.hasta}','${r.porcentaje}')"><i class="fas fa-edit"></i></button>`:''}
        ${CAN_ELIM?`<button class="btn btn-sm btn-outline-danger" onclick="eliminar(${r.id})"><i class="fas fa-trash"></i></button>`:''}</td>
    </tr>`).join('')||'<tr><td colspan="4" class="text-center text-muted">Sin rangos. El precio Multiunión queda igual al catálogo.</td></tr>';
  }catch(e){showA(e.message,false);}
}
function editar(id,desde,hasta,pct){
  document.getElementById('rId').value=id;document.getElementById('rDesde').value=desde;
  document.getElementById('rHasta').value=hasta;document.getElementById('rPct').value=pct;
  document.getElementById('btnCancelar').style.display='';
}
function cancelar(){document.getElementById('rId').value='';document.getElementById('rDesde').value='';document.getElementById('rHasta').value='';document.getElementById('rPct').value='';document.getElementById('btnCancelar').style.display='none';}
async function guardar(){
  const body={id:document.getElementById('rId').value,desde:document.getElementById('rDesde').value,
    hasta:document.getElementById('rHasta').value,porcentaje:document.getElementById('rPct').value};
  try{await post('tienda/rangos_precio.php?action=guardar',body);
    showA('Rango guardado.',true);cancelar();cargar();}catch(e){showA(e.message,false);}
}
async function eliminar(id){if(!confirm('¿Eliminar el rango?'))return;try{await post('tienda/rangos_precio.php?action=eliminar&id='+id,{});cargar();}catch(e){showA(e.message,false);}}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>