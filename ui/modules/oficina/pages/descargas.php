<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.descargas');
$canExportar = ui_accion('oficina.descargas', 'exportar', $staff['rol'] ?? '');
$pageTitle = 'Descargas';
$currentPage = 'oficina_descargas';
require dirname(__DIR__, 3) . '/views/layouts/header.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-3 border-bottom">
  <h1 class="h3 mb-0"><i class="fas fa-download me-2"></i>Descargas y reportes</h1>
</div>

<div class="row g-4">
  <!-- Archivo PSE -->
  <div class="col-md-6 col-xxl-4">
    <div class="card h-100">
      <div class="card-header brand d-flex align-items-center">
        <i class="fas fa-university me-2"></i><span>Archivo PSE</span>
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted">Archivo requerido para cargar a PSE: datos de asociados activos, aportes y productos, cartera aseguradora y mora, cálculo de intereses.</p>
        <div class="row g-2 mb-2">
          <div class="col-6"><label class="form-label small text-muted mb-0">Consecutivo inicial (invoice_id)</label></div>
          <div class="col-6"><input type="number" class="form-control form-control-sm" id="pseStart" value="300000" min="1"></div>
        </div>
        <?php if ($canExportar): ?>
        <a class="btn btn-primary mt-auto" onclick="descargarPse()"><i class="fas fa-download me-2"></i>Descargar PSE (Excel)</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Asociados en Mora -->
  <div class="col-md-6 col-xxl-4">
    <div class="card h-100">
      <div class="card-header brand d-flex align-items-center">
        <i class="fas fa-exclamation-triangle me-2"></i><span>Asociados en Mora</span>
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted">Archivo para gestión de cobro: datos de contacto, saldo en mora y días vencidos, desglose por productos y datos de codeudor.</p>
        <?php if ($canExportar): ?>
        <a class="btn btn-warning mt-auto" href="<?php echo apiUrl(); ?>oficina/export.php?tipo=mora"><i class="fas fa-download me-2"></i>Descargar Mora (Excel)</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Transacciones -->
  <div class="col-md-6 col-xxl-4">
    <div class="card h-100">
      <div class="card-header brand d-flex align-items-center">
        <i class="fas fa-exchange-alt me-2"></i><span>Transacciones</span>
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted">Exportar transacciones bancarias PSE/Confiar con conciliación, confirmación, asignaciones y recibos de caja.</p>
        <?php if ($canExportar): ?>
        <a class="btn btn-success mt-auto" href="<?php echo apiUrl(); ?>oficina/export.php?tipo=transacciones"><i class="fas fa-download me-2"></i>Descargar Transacciones (Excel)</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Inventario de Tienda -->
  <div class="col-md-6 col-xxl-4">
    <div class="card h-100">
      <div class="card-header brand d-flex align-items-center">
        <i class="fas fa-store me-2"></i><span>Inventario de Tienda</span>
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted">Stock completo por unidad (ingresado − vendido = disponible), categorías, marcas y precios de productos activos.</p>
        <?php if ($canExportar): ?>
        <a class="btn btn-info mt-auto" href="<?php echo apiUrl(); ?>oficina/export.php?tipo=inventario_tienda"><i class="fas fa-download me-2"></i>Descargar Inventario (Excel)</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Lista de asociados -->
  <div class="col-md-6 col-xxl-4">
    <div class="card h-100">
      <div class="card-header brand d-flex align-items-center">
        <i class="fas fa-users me-2"></i><span>Lista de asociados</span>
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted">Cédula, nombre, teléfono, email y estado. Incluye asociados activos e inactivos.</p>
        <?php if ($canExportar): ?>
        <a class="btn btn-secondary mt-auto" href="<?php echo apiUrl(); ?>oficina/export.php?tipo=asociados_activos"><i class="fas fa-download me-2"></i>Descargar Asociados (Excel)</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Cláusulas Asignadas -->
  <div class="col-md-6 col-xxl-4">
    <div class="card h-100">
      <div class="card-header brand d-flex align-items-center" style="background:linear-gradient(135deg,#6f42c1,#4b2e83)!important;">
        <i class="fas fa-file-contract me-2"></i><span>Cláusulas Asignadas</span>
      </div>
      <div class="card-body d-flex flex-column">
        <p class="small text-muted">Asociados con cláusulas vigentes: monto mensual, fecha de inicio, vigencia, parámetros y estado.</p>
        <?php if ($canExportar): ?>
        <a class="btn mt-auto" href="<?php echo apiUrl(); ?>oficina/export.php?tipo=clausulas_asignadas" style="background-color:#6f42c1;color:#fff"><i class="fas fa-download me-2"></i>Descargar Cláusulas (Excel)</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
const API=<?php echo json_encode(apiUrl()); ?>;
function descargarPse(){
  const s=document.getElementById('pseStart').value||'300000';
  window.location.href=API+'oficina/export.php?tipo=pse&start='+encodeURIComponent(s);
}
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>