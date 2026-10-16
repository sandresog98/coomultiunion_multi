<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('configuracion');
$acc = ui_acciones('configuracion', $staff['rol'] ?? '');
$canEdit = ($acc['configuracion.editar'] ?? false) === true;
$pageTitle = 'Parámetros de solicitudes';
$currentPage = 'configuracion';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
?>
<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-sliders me-2"></i>Parámetros de solicitudes</span>
  </div>
  <div class="card-body">
    <div id="alert" class="alert d-none"></div>

    <h6 class="fw-semibold text-brand mb-1">Crédito cooperativa (boletas / convenios)</h6>
    <p class="text-muted small mb-3">Reglas para pagar una solicitud de boletas con crédito de la cooperativa. El asociado califica si cumple <b>alguna</b> de las condiciones.</p>
    <div class="row g-3" style="max-width:760px">
      <div class="col-md-4">
        <label class="form-label" for="f_meses">Antigüedad mínima (meses)</label>
        <input type="number" min="0" step="1" class="form-control" id="f_meses">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="f_aportes">Pagos de aportes mínimos</label>
        <input type="number" min="0" step="1" class="form-control" id="f_aportes">
      </div>
      <div class="col-md-4">
        <label class="form-label" for="f_limite">Límite del crédito (pesos)</label>
        <input type="number" min="0" step="1000" class="form-control" id="f_limite">
      </div>
    </div>

    <div class="mt-3">
      <button class="btn btn-brand" id="btnGuardar" onclick="guardar()" <?= $canEdit ? '' : 'disabled' ?>><i class="fas fa-save me-1"></i>Guardar</button>
    </div>
  </div>
</div>
<script>
window.API=<?php echo json_encode(apiUrl()); ?>;
const canEdit=<?php echo $canEdit ? 'true' : 'false'; ?>;
const L=window.Listado, api=L.api;
function showAlert(msg,ok){var el=document.getElementById('alert');el.className='alert '+(ok?'alert-success':'alert-danger');el.textContent=msg;el.classList.remove('d-none');}
async function cargar(){
  try{
    const d=await api('configuracion/listar');const c=d.config||{};
    document.getElementById('f_meses').value=c.boleta_credito_meses_min??6;
    document.getElementById('f_aportes').value=c.boleta_credito_aportes_min??6;
    document.getElementById('f_limite').value=c.boleta_credito_limite??100000;
  }catch(e){showAlert(e.message,false);}
}
async function guardar(){
  if(!canEdit)return;
  const valores={
    boleta_credito_meses_min: document.getElementById('f_meses').value,
    boleta_credito_aportes_min: document.getElementById('f_aportes').value,
    boleta_credito_limite: document.getElementById('f_limite').value,
  };
  try{
    await api('configuracion/guardar',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({valores})});
    showAlert('Parámetros guardados.',true);
  }catch(e){showAlert(e.message,false);}
}
cargar();
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
