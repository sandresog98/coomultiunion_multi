<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('tienda.informacion');
$pageTitle = 'Información y reportes';
$currentPage = 'tienda_informacion';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card"><div class="card-header brand"><i class="fas fa-boxes me-2"></i>Reporte de inventario</div>
      <div class="card-body">
        <p class="small text-muted">Ingresado / vendido / disponible por producto.</p>
        <a class="btn btn-outline-primary btn-sm" href="<?php echo apiUrl(); ?>tienda/reportes.php?action=descargar&tipo=inventario"><i class="fas fa-file-excel me-1"></i>Descargar XLSX</a>
      </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-header brand"><i class="fas fa-money-bill me-2"></i>Reporte de ventas</div>
      <div class="card-body">
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small text-muted mb-0">Desde</label><input type="date" class="form-control form-control-sm" id="rD1"></div>
          <div class="col-6"><label class="form-label small text-muted mb-0">Hasta</label><input type="date" class="form-control form-control-sm" id="rD2"></div>
        </div>
        <a class="btn btn-outline-primary btn-sm" onclick="descargar('ventas')"><i class="fas fa-file-excel me-1"></i>Descargar XLSX</a>
      </div></div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-header brand"><i class="fas fa-file-invoice me-2"></i>Reporte ventas detalle</div>
      <div class="card-body">
        <p class="small text-muted">Por línea de venta con costo y ganancia.</p>
        <a class="btn btn-outline-primary btn-sm" onclick="descargar('ventas_detalle')"><i class="fas fa-file-excel me-1"></i>Descargar XLSX</a>
      </div></div>
  </div>
</div>

<div class="card">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-info-circle me-2"></i>Información del módulo</span>
  </div>
  <div class="card-body">
    <ul class="small">
      <li><strong>Stock:</strong> una fila por unidad; invariante <em>ingresado = vendido + disponible + anulado</em>.</li>
      <li><strong>Ganancia por línea:</strong> subtotal − Σ(precio de compra de sus unidades) (este es el <em>precio Multiunión</em> = ROUND(precio − precio × %/100, −3)).</li>
      <li><strong>Reversión:</strong> devuelve unidades a "disponible" y crea un <strong>bono</strong> (crédito) por el valor revertido, pagable como medio "bono".</li>
      <li><strong>Catálogo público</strong> (`tienda/`): muestra todo el catálogo activo informativo, con promociones y precio Multiunión.</li>
    </ul>
  </div>
</div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>, UI=<?php echo json_encode(uiBaseUrl()); ?>;
function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function descargar(tipo){
  const d1=document.getElementById('rD1').value, d2=document.getElementById('rD2').value;
  const url=API+'tienda/reportes.php?action=descargar&tipo='+tipo+(d1?'&fecha_desde='+d1:'')+(d2?'&fecha_hasta='+d2:'');
  window.location.href=url;
}
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>