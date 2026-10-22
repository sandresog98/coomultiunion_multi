<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/guard.php';
$staff = ui_guard('oficina.asociados');
$rol = $staff['rol'] ?? '';
$sec = ui_secciones('asociados.detalle', $rol);
$puedeSaldos    = $sec['asociados.detalle.saldos']    ?? false;
$puedeCreditos  = $sec['asociados.detalle.creditos']  ?? false;
$puedeDetalleCr = $sec['asociados.detalle.creditos_detalle'] ?? false;
$puedeProductos = $sec['asociados.detalle.productos'] ?? false;
$puedeCobranza  = $sec['asociados.detalle.cobranza']  ?? false;
$puedeComunicaciones = $sec['asociados.detalle.comunicaciones'] ?? false;
$acc = ui_acciones('asociados.detalle', $rol);
$canToggle  = ui_accion('asociados.detalle', 'toggle',   $rol);
$canOptIn   = ui_accion('asociados.detalle', 'opt_in',   $rol);
$canAsignar = ui_accion('asociados.detalle', 'asignar',  $rol);
$canEditP   = ui_accion('asociados.detalle', 'editar',   $rol);
$canElimP   = ui_accion('asociados.detalle', 'eliminar', $rol);
$canAdj     = ui_accion('asociados.detalle', 'adjuntar', $rol);
require_once dirname(__DIR__, 4) . '/core/asociados/AsociadoService.php';
require_once dirname(__DIR__, 3) . '/config/paths.php';

$cedula = trim((string) ($_GET['cedula'] ?? ''));
if ($cedula === '') { header('Location: ' . uiBaseUrl() . 'modules/asociados/pages/asociados'); exit; }

try {
    $data = (new AsociadoService())->ficha($cedula);
} catch (DomainException $e) {
    http_response_code(404);
    echo '<div class="alert alert-danger m-3">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

$p  = $data['persona'] ?? [];
$m  = $data['productos'] ?? [];
$cr = $data['creditos'] ?? [];
$est = $data['estado'] ?? [];
$activo = ($est['estado_activo'] ?? 1) == 1;
$optInCom = (int) ($est['opt_in'] ?? 0) === 1;

$pageTitle = 'Detalle de Asociado';
$currentPage = 'asociados';
require dirname(__DIR__, 3) . '/views/layouts/header.php';

function esc(mixed $s): string { return htmlspecialchars((string) ($s ?? '')); }
function money(?string $v): string { return '$' . number_format((float) ($v ?? 0), 0, ',', '.'); }
function fmtFecha(?string $v): string {
    if (empty($v)) return '—';
    $ts = strtotime($v);
    return $ts ? date('d/m/Y', $ts) : '—';
}
function dec(?string $v, int $d = 0): string {
    return number_format((float) ($v ?? 0), $d, ',', '.');
}
function row(string $label, string $value, string $valueClass = ''): string {
    return "<tr><td><strong>$label</strong></td><td class=\"text-end $valueClass\">$value</td></tr>";
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h3 mb-0"><i class="fas fa-id-card me-2"></i><?php echo esc($p['nombre']); ?> <small class="text-muted fs-6">· <?php echo esc($p['cedula']); ?></small></h1>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo uiBaseUrl(); ?>modules/asociados/pages/asociados"><i class="fas fa-arrow-left me-1"></i>Volver a asociados</a>
</div>

<div class="row g-3">
  <!-- Columna izquierda: Datos personales + Estado unificados -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header brand"><strong><i class="fas fa-user me-2"></i>Asociado</strong></div>
      <div class="card-body small">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <div class="mb-1"><strong>Cédula:</strong> <?php echo esc($p['cedula']); ?></div>
            <div class="mb-1"><strong>Nombre:</strong> <?php echo esc($p['nombre']); ?></div>
          </div>
          <div>
            <?php if ($activo): ?>
              <?php if ($canToggle): ?>
              <button class="btn btn-sm btn-outline-danger" onclick="toggleEstado(0)"><i class="fas fa-ban me-1"></i>Inactivar</button>
              <?php endif; ?>
            <?php else: ?>
              <?php if ($canToggle): ?>
              <button class="btn btn-sm btn-outline-success" onclick="toggleEstado(1)"><i class="fas fa-check me-1"></i>Activar</button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="row mt-2">
          <div class="col-md-6">
            <div class="mb-1"><strong>F. nacimiento:</strong> <?php echo fmtFecha($p['fecha_nacimiento']); ?><?php $fn = $p['fecha_nacimiento'] ?? ''; if ($fn) { $b = new DateTime($fn); $h = new DateTime(); $e = $b->diff($h)->y; if ($e>0) echo " <span class='text-muted'>($e años)</span>"; } ?></div>
            <div class="mb-1"><strong>F. afiliación:</strong> <?php echo fmtFecha($p['fecha_ingreso']); ?></div>
            <div class="mb-1"><strong>F. expedición:</strong> <?php echo fmtFecha($p['fecha_expedicion']); ?></div>
            <div class="mb-1"><strong>Celular:</strong> <?php echo esc($p['celular']); ?></div>
            <div class="mb-1"><strong>Teléfono:</strong> <?php echo esc($p['telefono']); ?></div>
            <div class="mb-1"><strong>Email:</strong> <?php echo esc($p['email']); ?></div>
            <div class="mb-1"><strong>Ciudad:</strong> <?php echo esc($p['ciudad']); ?></div>
            <div class="mb-1"><strong>Dirección:</strong> <?php echo esc($p['direccion']); ?></div>
          </div>
          <div class="col-md-6">
            <div class="mb-1"><strong>Estado:</strong> <?php echo $activo ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-danger">Inactivo</span>'; ?></div>
            <div class="mb-1"><strong>Portal:</strong> <?php echo ($est['tiene_portal'] ?? 0) ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Sin portal</span>'; ?></div>
            <?php if ($puedeComunicaciones): ?>
            <div class="mb-1"><strong>Comunicaciones:</strong>
              <?php echo $optInCom ? '<span class="badge text-bg-success">Opt-in activo</span>' : '<span class="badge text-bg-warning">Sin opt-in</span>'; ?>
              <?php if ($canOptIn): ?>
              <button class="btn btn-sm btn-outline-<?php echo $optInCom ? 'danger' : 'success'; ?> ms-1 py-0 px-2" onclick="toggleOptIn(<?php echo $optInCom ? 0 : 1; ?>)">
                <i class="fas <?php echo $optInCom ? 'fa-toggle-off' : 'fa-toggle-on'; ?> me-1"></i><?php echo $optInCom ? 'Desactivar' : 'Activar'; ?>
              </button>
              <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="mb-1"><strong>Fondo Bienestar:</strong> <?php
              if (($m['fondo_bienestar_tiene_producto'] ?? 0) == 1) {
                echo ($m['fondo_bienestar_al_dia'] ?? 0) == 1
                  ? '<span class="badge text-bg-success">Asignado · Al día</span>'
                  : '<span class="badge text-bg-warning">Asignado · No al día</span>';
              } else { echo '<span class="text-muted">No asignado</span>'; }
            ?></div>
            <div class="mb-1"><strong>Día de pago:</strong> <?php echo $m['producto_dia_pago'] ?? '—'; ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Columna derecha: Saldos a favor + Valor de pagos -->
  <?php if ($puedeSaldos): ?>
  <div class="col-md-6">
    <div class="card mb-3">
      <div class="card-header brand"><strong><i class="fas fa-coins me-2"></i>Saldos a favor</strong></div>
      <div class="card-body p-2">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Concepto</th><th class="text-end">Valor</th></tr></thead>
          <tbody>
            <?php echo row('Aportes totales', money((string) ($m['aportes_totales'] ?? 0))); ?>
            <?php echo row('Incentivos', money((string) ($m['aportes_incentivos'] ?? 0))); ?>
            <?php echo row('Revalorizaciones', money((string) ($m['aportes_revalorizaciones'] ?? 0))); ?>
            <?php echo row('Plan futuro', money((string) ($m['plan_futuro'] ?? 0))); ?>
            <?php echo row('Bolsillos', money((string) ($m['bolsillos'] ?? 0))); ?>
            <?php echo row('Comisiones', money((string) ($m['comisiones'] ?? 0))); ?>
            <?php echo row('Total a favor', money((string) ($m['total_saldos_favor'] ?? 0)), 'fw-bold'); ?>
            <?php echo row('Total incentivos', money((string) ($m['total_incentivos'] ?? 0)), 'fw-bold'); ?>
          </tbody>
        </table>
        <?php if ($m['aportes_ultimo_periodo'] ?? ''): ?>
        <p class="small text-muted mt-2 mb-0"><strong>Último período de aportes:</strong> <?php echo esc($m['aportes_ultimo_periodo']); ?></p>
        <?php endif; ?>
        <?php if ($m['bolsillos_ultima_fecha'] ?? ''): ?>
        <p class="small text-muted mt-1 mb-0"><strong>Último movimiento bolsillos:</strong> <?php echo fmtFecha($m['bolsillos_ultima_fecha']); ?></p>
        <?php endif; ?>

        <?php $totalValor = (float) ($m['producto_mensual_total'] ?? 0) + (float) ($m['pago_minimo_creditos'] ?? 0); ?>
        <hr>
        <h6 class="fw-bold mt-2 mb-2"><i class="fas fa-calculator me-2"></i>Valor de pagos</h6>
        <div class="mb-1"><strong>Producto mensual:</strong> <?php echo money((string) ($m['producto_mensual_total'] ?? 0)); ?></div>
        <div class="mb-1"><strong>Pago mínimo créditos:</strong> <?php echo money((string) ($m['pago_minimo_creditos'] ?? 0)); ?></div>
        <div class="fw-bold text-brand"><strong>Valor total:</strong> <?php echo money((string) $totalValor); ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Créditos -->
<?php if ($puedeCreditos && !empty($cr)): ?>
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-file-invoice me-2"></i>Créditos (<?php echo count($cr); ?>)</strong>
        <?php if ($puedeDetalleCr): ?>
        <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#detalleCreditosModal"><i class="fas fa-eye me-1"></i>Ver detalle</button>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm small align-middle mb-0">
            <thead class="table-light"><tr>
              <th>N°</th><th>Tipo</th><th class="text-center">Inicio</th><th class="text-center">Venc.</th>
              <th class="text-center">F. pago</th>
              <th class="text-end">V. Inicial</th><th class="text-center">Cuotas</th><th class="text-end">Cuota</th>
              <th class="text-end">Capital</th><th class="text-end">Mora</th><th class="text-end">Intereses</th>
              <th class="text-center">Días</th><th class="text-center">Vencidas</th>
              <th class="text-end">Cobranza</th><th class="text-end">Pago mín.</th>
              <th class="text-end">Saldo total</th>
              <th class="text-end">Tasa</th><th class="text-center">Cod.</th>
            </tr></thead>
            <tbody>
              <?php $codeudorModals = ''; foreach ($cr as $c): ?>
              <?php
                $diav = (int) ($c['dias_mora'] ?? 0);
                $clase = $diav >= 30 ? 'text-danger fw-bold' : ($diav >= 1 ? 'text-warning fw-bold' : '');
                $cuotas = ($c['cuotas_pagadas'] ?? 0) > 0 && ($c['cuotas_totales'] ?? 0) > 0
                  ? dec((string) $c['cuotas_pagadas']) . '/' . dec((string) $c['cuotas_totales']) : '—';
                $tieneCodeudor = !empty($c['codeudor_nombre'] ?? '') || !empty($c['codeudor_celular'] ?? '');
                $cdModalId = 'cdModal_' . preg_replace('/[^A-Za-z0-9]/', '_', (string) ($c['numero_credito'] ?? ''));
              ?>
              <tr>
                <td><?php echo esc($c['numero_credito']); ?></td>
                <td><?php echo esc($c['tipo_prestamo']); ?></td>
                <td class="text-center"><?php echo fmtFecha($c['fecha_emision']); ?></td>
                <td class="text-center"><?php echo fmtFecha($c['fecha_vencimiento']); ?></td>
                <td class="text-center"><?php echo fmtFecha($c['fecha_pago']); ?></td>
                <td class="text-end"><?php echo money($c['desembolso_inicial']); ?></td>
                <td class="text-center"><?php echo $cuotas; ?></td>
                <td class="text-end"><?php echo money($c['valor_cuota']); ?></td>
                <td class="text-end"><?php echo money($c['saldo_capital']); ?></td>
                <td class="text-end <?php echo $clase; ?>"><?php echo money($c['saldo_mora']); ?></td>
                <td class="text-end"><?php echo money($c['saldo_intereses']); ?></td>
                <td class="text-center <?php echo $clase; ?>"><?php echo dec((string) $c['dias_mora']); ?></td>
                <td class="text-center <?php echo $clase; ?>"><?php echo dec((string) $c['cuotas_vencidas']); ?></td>
                <td class="text-end"><?php echo money($c['monto_cobranza']); ?></td>
                <td class="text-end"><b><?php echo money($c['pago_minimo']); ?></b></td>
                <td class="text-end"><?php echo money($c['saldo_total']); ?></td>
                <td class="text-end"><?php echo $c['tasa_interes'] === null ? '—' : dec((string) $c['tasa_interes'], 2) . '%'; ?></td>
                <td class="text-center">
                  <?php if ($tieneCodeudor): ?>
                  <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#<?php echo $cdModalId; ?>">Ver</button>
                  <?php $codeudorModals .= "
                  <div class=\"modal fade\" id=\"$cdModalId\" tabindex=\"-1\" aria-hidden=\"true\"><div class=\"modal-dialog\"><div class=\"modal-content\">
                    <div class=\"modal-header\"><h5 class=\"modal-title\"><i class=\"fas fa-user-friends me-2\"></i>Codeudor — Crédito " . esc($c['numero_credito']) . "</h5><button class=\"btn-close\" data-bs-dismiss=\"modal\"></button></div>
                    <div class=\"modal-body\">
                      <div class=\"mb-1\"><strong>Nombre:</strong> " . esc($c['codeudor_nombre']) . "</div>
                      <div class=\"mb-1\"><strong>Celular:</strong> " . esc($c['codeudor_celular']) . "</div>
                      <div class=\"mb-1\"><strong>Email:</strong> " . esc($c['codeudor_email']) . "</div>
                      <div class=\"mb-1\"><strong>Dirección:</strong> " . esc($c['codeudor_direccion']) . "</div>
                    </div>
                    <div class=\"modal-footer\"><button class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Cerrar</button></div>
                  </div></div></div>"; ?>
                  <?php else: ?>
                  —
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($puedeDetalleCr): ?>
<!-- Modal Ver detalle de créditos -->
<div class="modal fade" id="detalleCreditosModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Detalle de créditos</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm small align-middle mb-0">
            <thead class="table-light"><tr>
              <th>N°</th><th>Tipo</th><th class="text-center">Inicio</th><th class="text-center">Venc.</th>
              <th class="text-center">F. pago</th>
              <th class="text-end">V. Inicial</th><th class="text-center">Cuotas</th><th class="text-end">Cuota</th>
              <th class="text-end">Capital</th><th class="text-end">Mora</th><th class="text-end">Intereses</th>
              <th class="text-center">Días</th><th class="text-center">Vencidas</th>
              <th class="text-end">Cobranza</th><th class="text-end">Pago mín.</th>
              <th class="text-end">Saldo total</th><th class="text-end">Tasa</th>
              <th class="text-end">Seguro vida</th><th class="text-end">Seguro deud.</th><th class="text-end">Interés</th>
              <th class="text-center">Cod.</th>
            </tr></thead>
            <tbody>
              <?php foreach ($cr as $c): ?>
              <tr>
                <td><?php echo esc($c['numero_credito']); ?></td>
                <td><?php echo esc($c['tipo_prestamo']); ?></td>
                <td class="text-center"><?php echo fmtFecha($c['fecha_emision']); ?></td>
                <td class="text-center"><?php echo fmtFecha($c['fecha_vencimiento']); ?></td>
                <td class="text-center"><?php echo fmtFecha($c['fecha_pago']); ?></td>
                <td class="text-end"><?php echo money($c['desembolso_inicial']); ?></td>
                <td class="text-center"><?php
                  echo (($c['cuotas_pagadas'] ?? 0) > 0 && ($c['cuotas_totales'] ?? 0) > 0)
                    ? dec((string) $c['cuotas_pagadas']) . '/' . dec((string) $c['cuotas_totales']) : '—'; ?></td>
                <td class="text-end"><?php echo money($c['valor_cuota']); ?></td>
                <td class="text-end"><?php echo money($c['saldo_capital']); ?></td>
                <td class="text-end"><?php echo money($c['saldo_mora']); ?></td>
                <td class="text-end"><?php echo money($c['saldo_intereses']); ?></td>
                <td class="text-center"><?php echo dec((string) $c['dias_mora']); ?></td>
                <td class="text-center"><?php echo dec((string) $c['cuotas_vencidas']); ?></td>
                <td class="text-end"><?php echo money($c['monto_cobranza']); ?></td>
                <td class="text-end"><b><?php echo money($c['pago_minimo']); ?></b></td>
                <td class="text-end"><?php echo money($c['saldo_total']); ?></td>
                <td class="text-end"><?php echo $c['tasa_interes'] === null ? '—' : dec((string) $c['tasa_interes'], 2) . '%'; ?></td>
                <td class="text-end"><?php echo $c['seguro_vida'] === null ? '—' : money((string) $c['seguro_vida']); ?></td>
                <td class="text-end"><?php echo $c['seguro_deudores'] === null ? '—' : money((string) $c['seguro_deudores']); ?></td>
                <td class="text-end"><?php echo $c['interes'] === null ? '—' : money((string) $c['interes']); ?></td>
                <td class="text-center"><?php echo (!empty($c['codeudor_nombre']) || !empty($c['codeudor_celular'])) ? 'Sí' : '—'; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php echo $codeudorModals ?? ''; ?>

<!-- Productos asignados -->
<?php if ($puedeProductos): ?>
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card">
      <div class="card-header brand d-flex justify-content-between align-items-center">
        <strong><i class="fas fa-tags me-2"></i>Productos y cláusulas asignados</strong>
        <div>
          <span class="small text-white-50 me-2">Total activos: <b id="prodTotal">—</b></span>
          <?php if ($canAsignar): ?>
          <button class="btn btn-light btn-sm" onclick="abrirAsignar()"><i class="fas fa-plus me-1"></i>Asignar</button>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body p-0">
        <div id="prodAlert" class="alert d-none m-3 mb-0"></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr>
              <th>Producto</th><th>Tipo</th><th class="text-end">Monto</th>
              <th class="text-center">Día pago</th><th class="text-center">Vigencia</th><th class="text-center">Archivo</th><th class="text-center">Acciones</th>
            </tr></thead>
            <tbody id="prodTbody"><tr><td colspan="7" class="text-center text-muted">Cargando…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal Asignar producto/cláusula -->
<div class="modal fade" id="mAsignar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-plus me-2"></i>Asignar producto o cláusula</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="frmAsignar" onsubmit="return guardarAsignacion(event)">
        <div class="modal-body">
          <input type="hidden" name="cedula" value="<?php echo esc($cedula); ?>">
          <div class="mb-3">
            <label class="form-label">Tipo</label>
            <select class="form-select" name="producto_id" id="asigProdId" required onchange="toggleAsigFields()">
              <option value="" disabled selected>Seleccione…</option>
            </select>
          </div>
          <div class="mb-3" id="asigMonto"><label class="form-label">Monto <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="monto" id="asigMontoInp" min="0" step="0.01" required>
            <div class="form-text" id="asigRango"></div>
          </div>
          <div class="mb-3" id="asigDiaPago"><label class="form-label">Día de pago <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="dia_pago" min="1" max="31" value="1" required></div>
          <div class="mb-3 d-none" id="asigFechaInicio"><label class="form-label">Fecha inicio <span class="text-danger">*</span></label>
            <input type="date" class="form-control" name="fecha_inicio"></div>
          <div class="mb-3 d-none" id="asigMesesVig"><label class="form-label">Meses vigencia <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="meses_vigencia" min="1"></div>
          <div class="mb-3 d-none" id="asigArchivo"><label class="form-label">Archivo</label>
            <input type="file" class="form-control" name="archivo" id="asigArchivoInp" accept=".pdf,.jpg,.jpeg,.png">
            <div class="form-text">PDF, JPG o PNG</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-brand">Asignar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Editar asignación -->
<div class="modal fade" id="mEditar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar asignación</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="frmEditar" onsubmit="return guardarEdicion(event)">
        <div class="modal-body">
          <input type="hidden" name="id" id="editId">
          <div class="mb-1"><strong>Producto:</strong> <span id="editNombre"></span></div>
          <div class="mb-3 mt-2"><label class="form-label">Monto <span class="text-danger">*</span></label>
            <input type="number" class="form-control" name="monto" id="editMonto" min="0" step="0.01" required>
            <div class="form-text" id="editRango"></div>
          </div>
          <div class="mb-3" id="editDiaPago"><label class="form-label">Día de pago</label>
            <input type="number" class="form-control" name="dia_pago" id="editDiaPagoInp" min="1" max="31"></div>
          <div class="mb-3" id="editFechasClausula">
            <div class="row">
              <div class="col-md-6"><label class="form-label">Fecha inicio</label><input type="date" class="form-control" name="fecha_inicio" id="editFechaInicio"></div>
              <div class="col-md-6"><label class="form-label">Meses vigencia</label><input type="number" class="form-control" name="meses_vigencia" id="editMesesVig" min="1"></div>
            </div>
          </div>
          <div class="mb-3 d-none" id="editArchivo"><label class="form-label">Archivo</label>
            <input type="file" class="form-control" name="archivo" id="editArchivoInp" accept=".pdf,.jpg,.jpeg,.png">
            <div class="form-text">PDF, JPG o PNG</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-brand">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($puedeCobranza): ?>
<div class="card mt-3" id="cardCobranza">
  <div class="card-header brand d-flex justify-content-between align-items-center">
    <span><i class="fas fa-phone me-2"></i>Cobranza</span>
    <span>
      <a class="btn btn-light btn-sm" href="<?php echo uiBaseUrl(); ?>modules/cobranza/pages/cobranza?q=<?php echo urlencode($cedula); ?>"><i class="fas fa-external-link-alt me-1"></i>Ir a Cobranza</a>
    </span>
  </div>
  <div class="card-body p-2">
    <div class="row g-3">
      <div class="col-md-4">
        <div class="d-inline-flex align-items-center w-100 mb-1">
          <span class="small fw-semibold text-muted me-auto">Historial de comunicaciones</span>
          <button class="btn btn-sm btn-outline-secondary" onclick="cobNuevaCom()" title="Nueva comunicación"><i class="fas fa-plus"></i></button>
        </div>
        <div id="comunicacionesCob"><div class="text-muted small py-2 text-center">Cargando…</div></div>
      </div>
      <div class="col-md-4">
        <div class="d-inline-flex align-items-center w-100 mb-1">
          <span class="small fw-semibold text-muted me-auto">Historial de indagaciones</span>
          <button class="btn btn-sm btn-outline-secondary" onclick="cobNuevaInd()" title="Nueva indagación"><i class="fas fa-plus"></i></button>
        </div>
        <div id="indagacionesCob"><div class="text-muted small py-2 text-center">Cargando…</div></div>
      </div>
      <div class="col-md-4">
        <div class="d-inline-flex align-items-center w-100 mb-1">
          <span class="small fw-semibold text-muted me-auto">Historial de compromisos</span>
          <button class="btn btn-sm btn-outline-secondary" onclick="cobNuevoComp()" title="Nuevo compromiso"><i class="fas fa-plus"></i></button>
        </div>
        <div id="compromisosCob"><div class="text-muted small py-2 text-center">Cargando…</div></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal comunicación (cobranza) -->
<div class="modal fade" id="mCobCom" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="cobComTitle">Nueva comunicación</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="cobComId">
    <div class="row g-2">
      <div class="col-md-4"><label class="form-label">Tipo</label><select class="form-select" id="cobComTipo"></select></div>
      <div class="col-md-4"><label class="form-label">Estado</label><select class="form-select" id="cobComEstado"></select></div>
      <div class="col-md-4"><label class="form-label">Fecha</label><input type="datetime-local" class="form-control" id="cobComFecha"></div>
      <div class="col-12"><label class="form-label">Comentario</label><textarea class="form-control" id="cobComComent" rows="2"></textarea></div>
      <div class="col-md-6 d-none" id="wrapCobComp"><label class="form-label">Fecha compromiso</label><input type="date" class="form-control" id="cobComFecComp"></div>
      <div class="col-md-6 d-none" id="wrapCobCompM"><label class="form-label">Monto compromiso</label><input type="number" class="form-control" id="cobComMonto" step="1" min="0"></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="cobGuardarCom()">Guardar</button></div>
</div></div></div>

<!-- Modal indagación (cobranza) -->
<div class="modal fade" id="mCobInd" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Nueva indagación</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <input type="hidden" id="cobIndId">
    <div class="mb-2"><label class="form-label">Tipo de indagación</label><input class="form-control" id="cobIndTipo"></div>
    <div class="mb-2"><label class="form-label">Fecha</label><input type="date" class="form-control" id="cobIndFecha"></div>
    <div class="mb-1"><label class="form-label">Adjunto (opcional)</label><input type="file" class="form-control" id="cobIndArchivo"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" onclick="cobGuardarInd()">Guardar</button></div>
</div></div></div>
<?php endif; ?>

<script>
const API = <?php echo json_encode(apiUrl()); ?>;
const CED = <?php echo json_encode($cedula); ?>;
const SEC = <?php echo json_encode($sec, JSON_UNESCAPED_UNICODE); ?>;
const ACC = <?php echo json_encode($acc, JSON_UNESCAPED_UNICODE); ?>;
const PUEDE_COB = SEC['asociados.detalle.cobranza'] === true;
const CAN_ASIGNAR=ACC['asociados.detalle.asignar']===true;
const CAN_EDITAR =ACC['asociados.detalle.editar']===true;
const CAN_ELIMINAR=ACC['asociados.detalle.eliminar']===true;
const CAN_TOGGLE_P=ACC['asociados.detalle.toggle']===true;
const MI = <?php echo json_encode((int) ($staff['id'] ?? 0)); ?>;
let mAsignar, mEditar, productosActivos = [];

function esc(s){return String(s??'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function money(v){v=Number(v||0);return '$'+v.toLocaleString('es-CO',{maximumFractionDigits:0});}
async function api(p,opt){const r=await fetch(API+p,opt);const j=await r.json();if(!j.ok)throw new Error(j.error||'Error');return j.data;}

// ── Toggle estado ──
async function toggleEstado(activo){
  const accion = activo ? 'activar' : 'inactivar';
  if(!confirm(`¿Confirmar ${accion} al asociado?`)) return;
  try{
    await api('asociados/toggle_estado', {method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({cedula:CED,activo:!!activo})});
    location.reload();
  }catch(e){alert(e.message);}
}

async function toggleOptIn(optIn){
  const accion = optIn ? 'activar' : 'desactivar';
  if(!confirm(`¿${accion} el opt-in de comunicaciones para este asociado?`)) return;
  try{
    await api('asociados/opt_in', {method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({cedula:CED,opt_in:!!optIn})});
    location.reload();
  }catch(e){alert(e.message);}
}

// ── Cargar productos ──
async function cargarProductos(){
  try{
    productosActivos = await api('productos/listar');
    const sel = document.getElementById('asigProdId');
    sel.innerHTML = '<option value="" disabled selected>Seleccione…</option>';
    productosActivos.forEach(p=>{
      sel.innerHTML += `<option value="${p.id}" data-tipo="${p.tipo}"
        data-min="${p.valor_minimo}" data-max="${p.valor_maximo}"
        data-archivo="${p.requiere_archivo||0}">${esc(p.nombre)} (${p.tipo})</option>`;
    });
    await cargarAsignaciones();
  }catch(e){alert(e.message);}
}

async function cargarAsignaciones(){
  try{
    const rows = await api('productos/asignaciones?cedula='+encodeURIComponent(CED));
    let total = 0;
    rows.forEach(r=>{ if(r.estado_activo==1) total += parseFloat(r.monto||0); });
    document.getElementById('prodTotal').textContent = money(total);
    document.getElementById('prodTbody').innerHTML = rows.length ? rows.map(r=>{
      const esClausula = r.tipo === 'clausula';
      const vigencia = esClausula ? (r.fecha_inicio?`${r.fecha_inicio} / ${r.meses_vigencia||'—'}m`:'—') : '—';
      const inactivo = r.estado_activo == 0;
      const rowCls = inactivo ? 'text-muted text-decoration-line-through' : '';
      let det = {}, archivo = '—';
      try { det = JSON.parse(r.detalle||'{}'); } catch(e){}
      if(det.archivo_ruta) archivo = `<a href="${det.archivo_ruta}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver archivo"><i class="fas fa-file"></i></a>`;
      return `<tr class="${rowCls}">
        <td>${esc(r.producto_nombre)}${inactivo?' <span class="badge text-bg-secondary">Inactivo</span>':''}</td>
        <td><span class="badge text-bg-light">${esc(r.tipo)}</span></td>
        <td class="text-end">${money(r.monto)}</td>
        <td class="text-center">${esClausula?'—':(r.dia_pago||'—')}</td><td class="text-center">${vigencia}</td>
        <td class="text-center">${archivo}</td>
        <td class="text-center">
          ${CAN_EDITAR?`<button class="btn btn-sm btn-outline-primary me-1" onclick="abrirEditar(${r.id})"><i class="fas fa-edit"></i></button>`:''}
          ${CAN_ELIMINAR?`<button class="btn btn-sm btn-outline-danger me-1" onclick="eliminarAsig(${r.id},'${esc(r.producto_nombre)}')"><i class="fas fa-trash"></i></button>`:''}
          ${CAN_TOGGLE_P?`<button class="btn btn-sm btn-outline-secondary" onclick="toggleProducto(${r.id})" title="${r.estado_activo==1?'Inactivar':'Activar'}"><i class="fas ${r.estado_activo==1?'fa-toggle-on text-success':'fa-toggle-off text-danger'}"></i></button>`:''}
        </td>
      </tr>`;
    }).join('') : '<tr><td colspan="7" class="text-center text-muted">Sin asignaciones</td></tr>';
  }catch(e){alert(e.message);}
}

// ── Upload archivo ──
async function uploadArchivo(fileInput){
  if(!fileInput.files || !fileInput.files[0]) return null;
  const fd = new FormData();
  fd.append('archivo', fileInput.files[0]);
  const r = await api('productos/upload', {method:'POST',body:fd});
  return r.ruta;
}

// ── Toggle campos ──
function toggleAsigFields(){
  const sel = document.getElementById('asigProdId');
  const opt = sel.options[sel.selectedIndex];
  if(!opt) return;
  const tipo = opt.dataset.tipo || '';
  const esClausula = tipo === 'clausula';
  const reqArchivo = parseInt(opt.dataset.archivo||0) === 1;
  document.getElementById('asigDiaPago').classList.toggle('d-none', esClausula);
  document.getElementById('asigFechaInicio').classList.toggle('d-none', !esClausula);
  document.getElementById('asigMesesVig').classList.toggle('d-none', !esClausula);
  document.getElementById('asigArchivo').classList.toggle('d-none', !reqArchivo);
  if(esClausula){
    document.getElementById('asigDiaPago').querySelector('input').required = false;
    document.getElementById('asigFechaInicio').querySelector('input').required = true;
    document.getElementById('asigMesesVig').querySelector('input').required = true;
  }else{
    document.getElementById('asigDiaPago').querySelector('input').required = true;
    document.getElementById('asigFechaInicio').querySelector('input').required = false;
    document.getElementById('asigMesesVig').querySelector('input').required = false;
  }
  const min = parseFloat(opt.dataset.min||0), max = parseFloat(opt.dataset.max||0);
  const inp = document.getElementById('asigMontoInp');
  inp.min = min; inp.max = max || '';
  document.getElementById('asigRango').textContent = `Rango: ${money(min)} - ${max>0?money(max):'sin límite'}`;
}

function abrirAsignar(){
  document.getElementById('frmAsignar').reset();
  document.getElementById('asigProdId').selectedIndex = 0;
  document.getElementById('asigDiaPago').classList.remove('d-none');
  document.getElementById('asigFechaInicio').classList.add('d-none');
  document.getElementById('asigMesesVig').classList.add('d-none');
  document.getElementById('asigArchivo').classList.add('d-none');
  document.getElementById('asigRango').textContent = '';
  document.getElementById('asigArchivoInp').value = '';
  mAsignar.show();
}

async function guardarAsignacion(e){
  e.preventDefault();
  const fd = new FormData(e.target);
  const data = Object.fromEntries(fd);
  data.producto_id = parseInt(data.producto_id);
  data.monto = parseFloat(data.monto);
  data.dia_pago = data.dia_pago ? parseInt(data.dia_pago) : null;
  data.meses_vigencia = data.meses_vigencia ? parseInt(data.meses_vigencia) : null;
  // Subir archivo si existe
  const ruta = await uploadArchivo(document.getElementById('asigArchivoInp'));
  if(ruta) data.detalle = JSON.stringify({archivo_ruta: ruta});
  try{
    await api('productos/asignaciones', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    mAsignar.hide();
    await cargarAsignaciones();
  }catch(e){alert(e.message);}
}

async function abrirEditar(id){
  const asignaciones = await api('productos/asignaciones?cedula='+encodeURIComponent(CED));
  const a = asignaciones.find(r=>r.id===id);
  if(!a) return alert('Asignación no encontrada.');
  document.getElementById('editId').value = a.id;
  document.getElementById('editNombre').textContent = a.producto_nombre+' ('+a.tipo+')';
  document.getElementById('editMonto').value = a.monto;
  document.getElementById('editMonto').min = a.valor_minimo||0;
  document.getElementById('editMonto').max = a.valor_maximo||'';
  document.getElementById('editRango').textContent = `Rango: ${money(a.valor_minimo||0)} - ${parseFloat(a.valor_maximo)>0?money(a.valor_maximo):'sin límite'}`;
  const esClausula = a.tipo === 'clausula';
  const reqArchivo = parseInt(a.requiere_archivo||0) === 1;
  document.getElementById('editDiaPago').classList.toggle('d-none', esClausula);
  document.getElementById('editFechasClausula').classList.toggle('d-none', !esClausula);
  document.getElementById('editArchivo').classList.toggle('d-none', !reqArchivo);
  document.getElementById('editArchivoInp').value = '';
  if(!esClausula) document.getElementById('editDiaPagoInp').value = a.dia_pago||1;
  else{
    document.getElementById('editFechaInicio').value = a.fecha_inicio||'';
    document.getElementById('editMesesVig').value = a.meses_vigencia||'';
  }
  mEditar.show();
}

async function guardarEdicion(e){
  e.preventDefault();
  const fd = new FormData(e.target);
  const data = Object.fromEntries(fd);
  data.id = parseInt(data.id);
  data.monto = parseFloat(data.monto);
  data.dia_pago = data.dia_pago ? parseInt(data.dia_pago) : null;
  data.meses_vigencia = data.meses_vigencia ? parseInt(data.meses_vigencia) : null;
  // Subir archivo si hay nuevo
  const ruta = await uploadArchivo(document.getElementById('editArchivoInp'));
  if(ruta) data.detalle = JSON.stringify({archivo_ruta: ruta});
  try{
    await api('productos/asignaciones', {method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});
    mEditar.hide();
    await cargarAsignaciones();
  }catch(e){alert(e.message);}
}

async function eliminarAsig(id, nombre){
  if(!confirm(`¿Eliminar asignación de "${nombre}"?`)) return;
  try{
    await api('productos/asignaciones', {method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});
    await cargarAsignaciones();
  }catch(e){alert(e.message);}
}

async function toggleProducto(id){
  try{
    const rows = await api('productos/asignaciones?cedula='+encodeURIComponent(CED));
    const a = rows.find(r=>r.id===id);
    if(!a) return;
    const accion = a.estado_activo == 1 ? 'inactivar' : 'activar';
    if(!confirm(`¿Confirmar ${accion} "${a.producto_nombre}"?`)) return;
    await api('productos/asignaciones', {method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,estado_activo:a.estado_activo==1?0:1})});
    await cargarAsignaciones();
  }catch(e){alert(e.message);}
}

document.addEventListener('DOMContentLoaded',()=>{
  if(SEC['asociados.detalle.productos']){
    mAsignar = new bootstrap.Modal('#mAsignar');
    mEditar = new bootstrap.Modal('#mEditar');
    cargarProductos();
  }
  if(PUEDE_COB){ mCobCom=new bootstrap.Modal('#mCobCom'); mCobInd=new bootstrap.Modal('#mCobInd'); cobCargarTipos(); cargarCobranza(); }
});

function cobFdt(v){return v?new Date(v).toLocaleDateString('es-CO'):'—';}
let cobComps=[],cobComs=[],cobInds=[],cobPageComp=0,cobPageComm=0,cobPageInd=0,cobPG=4;

async function cargarCobranza(){
  try{
    cobComps=await api('cobranza/compromisos?cedula='+encodeURIComponent(CED));
    cobComs=await api('cobranza/comunicaciones?cedula='+encodeURIComponent(CED));
    cobInds=await api('cobranza/indagaciones?cedula='+encodeURIComponent(CED));
    cobComs.sort((a,b)=>(b.fecha||'')>(a.fecha||'')?1:-1);
    cobInds.sort((a,b)=>(b.fecha||'')>(a.fecha||'')?1:-1);
    cobPageComp=0;cobPageComm=0;cobPageInd=0;
    renderCompromisos();renderComunicaciones();renderIndagaciones();
  }catch(e){document.getElementById('comunicacionesCob').innerHTML='<div class="text-muted small py-2">Sin datos o sin acceso</div>';}
}
function cobPagination(n,page,fn){const N=Math.ceil(n/cobPG);if(N<=1)return '';
  return `<div class="d-flex justify-content-between align-items-center small text-muted pt-2">
    <span>Mostrando ${page*cobPG+1}–${Math.min((page+1)*cobPG,n)} de ${n}</span>
    <span class="btn-group btn-group-sm">
      <button class="btn btn-outline-secondary" onclick="${fn}(${page-1});return false;" ${page<=0?'disabled':''}>«</button>
      <button class="btn btn-outline-secondary" onclick="${fn}(${page+1});return false;" ${page>=N-1?'disabled':''}>»</button>
    </span></div>`;
}
function renderCompromisos(page){page=page??cobPageComp;cobPageComp=page;
  const n=cobComps.length,slice=cobComps.slice(page*cobPG,(page+1)*cobPG);
  let hc=n?`<div class="table-responsive"><table class="table table-sm small mb-0"><thead class="table-light"><tr><th>Fecha com.</th><th>F. compromiso</th><th>Monto</th><th>Estado</th><th>Tipo</th></tr></thead><tbody>`
    +slice.map(c=>`<tr><td>${cobFdt(c.fecha)}</td><td>${cobFdt(c.fecha_compromiso)}</td><td class="text-end">${money(c.monto_compromiso||0)}</td><td>${cobCompBadge(c.estado_compromiso)}</td><td>${esc(c.tipo_comunicacion)}</td></tr>`).join('')+`</tbody></table></div>`
    :'<div class="text-muted small py-2">Sin compromisos registrados.</div>';
  document.getElementById('compromisosCob').innerHTML=hc+cobPagination(n,page,'renderCompromisos');
}
function renderComunicaciones(page){page=page??cobPageComm;cobPageComm=page;
  const n=cobComs.length,slice=cobComs.slice(page*cobPG,(page+1)*cobPG);
  let h;
  if(n){
    h='<div class="list-group list-group-flush small">'+slice.map(d=>{
      const mine=Number(d.creado_por)===MI;
      const comp=d.estado==='comprometido'&&(d.fecha_compromiso||d.monto_compromiso);
      const actions=mine?`<button class="btn btn-sm btn-outline-secondary ms-1" onclick='cobEditarCom(${JSON.stringify(d)})' title="Editar"><i class="fas fa-pen"></i></button><button class="btn btn-sm btn-outline-danger ms-1" onclick="cobEliminarCom(${d.id})" title="Eliminar"><i class="fas fa-trash"></i></button>`:'';
      return `<div class="list-group-item"><div class="d-flex justify-content-between"><div>
        <strong><i class="fas fa-comments me-1"></i>${esc(d.tipo_comunicacion)}</strong> · ${esc(d.estado)}
        <div class="small text-muted">por ${esc(d.usuario_nombre||'—')}</div>
        ${comp?`<div class="small mt-1"><span class="badge text-bg-info">Compromiso: ${cobFdt(d.fecha_compromiso)}</span> <span class="badge text-bg-success">Monto: ${money(d.monto_compromiso||0)}</span></div>`:''}
      </div><div class="text-end text-nowrap"><small class="text-muted">${cobFdt(d.fecha)}</small>${actions}</div></div>
        <div class="small mt-1">${esc(d.comentario||'')}</div></div>`;
    }).join('')+'</div>';
  } else h='<div class="text-muted small py-2">Sin comunicaciones.</div>';
  document.getElementById('comunicacionesCob').innerHTML=h+cobPagination(n,page,'renderComunicaciones');
}
function renderIndagaciones(page){page=page??cobPageInd;cobPageInd=page;
  const n=cobInds.length,slice=cobInds.slice(page*cobPG,(page+1)*cobPG);
  let h;
  if(n){
    h='<div class="list-group list-group-flush small">'+slice.map(d=>{
      const mine=Number(d.creado_por)===MI;
      const actions=mine?`<button class="btn btn-sm btn-outline-secondary ms-1" onclick='cobEditarInd(${JSON.stringify(d)})' title="Editar"><i class="fas fa-pen"></i></button><button class="btn btn-sm btn-outline-danger ms-1" onclick="cobEliminarInd(${d.id})" title="Eliminar"><i class="fas fa-trash"></i></button>`:'';
      const adj=(d.adjuntos||[]).map(a=>`<a href="${API}cobranza/serve_adjunto?id=${a.id}" target="_blank" class="btn btn-sm btn-outline-secondary me-1"><i class="fas fa-paperclip"></i>${esc(a.nombre_archivo)}</a>`).join('');
      return `<div class="list-group-item"><div class="d-flex justify-content-between"><div>
        <strong><i class="fas fa-search me-1"></i>${esc(d.tipo_indagacion)}</strong>
        <div class="small text-muted">por ${esc(d.usuario_nombre||'—')}</div>
      </div><div class="text-end text-nowrap"><small class="text-muted">${cobFdt(d.fecha)}</small>${actions}</div></div>
        ${adj?`<div class="mt-1">${adj}</div>`:''}</div>`;
    }).join('')+'</div>';
  } else h='<div class="text-muted small py-2">Sin indagaciones.</div>';
  document.getElementById('indagacionesCob').innerHTML=h+cobPagination(n,page,'renderIndagaciones');
}
function cobCompBadge(ec){const m={cumplido:['Cumplido','success'],incumplido:['Incumplido','danger'],pendiente_tiempo:['Pendiente','warning']};const x=m[ec]||['—','secondary'];return `<span class="badge text-bg-${x[1]}">${x[0]}</span>`;}

// ── CRUD cobranza por creador ──
let mCobCom, mCobInd, cobTIPOS={};
async function cobCargarTipos(){
  try{const rows=await api('cobranza/tipos?opciones=1');
    cobTIPOS={};rows.forEach(t=>{cobTIPOS[t.id]={tipo:t.tipo,estado:t.estado};});
    const tipos=[...new Set(rows.map(t=>t.tipo))],estados=[...new Set(rows.map(t=>t.estado))];
    document.getElementById('cobComTipo').innerHTML=tipos.map(t=>`<option value="${t}">${t}</option>`).join('');
    document.getElementById('cobComEstado').innerHTML=estados.map(s=>`<option value="${s}">${s}</option>`).join('');
  }catch(e){}
}
document.getElementById('cobComEstado').addEventListener('change',function(){const s=this.value==='comprometido';document.getElementById('wrapCobComp').classList.toggle('d-none',!s);document.getElementById('wrapCobCompM').classList.toggle('d-none',!s);});
function cobNuevaCom(){
  document.getElementById('cobComId').value='';document.getElementById('cobComFecha').value=new Date().toISOString().slice(0,16);
  document.getElementById('cobComComent').value='';document.getElementById('cobComFecComp').value='';document.getElementById('cobComMonto').value='';
  document.getElementById('wrapCobComp').classList.add('d-none');document.getElementById('wrapCobCompM').classList.add('d-none');
  document.getElementById('cobComTitle').textContent='Nueva comunicación';mCobCom.show();
}
function cobNuevoComp(){
  cobNuevaCom();
  document.getElementById('cobComTitle').textContent='Nuevo compromiso';
  const est=document.getElementById('cobComEstado');
  est.value=(Array.from(est.options).find(o=>o.value==='comprometido')?'comprometido':est.value);
  const s=est.value==='comprometido';
  document.getElementById('wrapCobComp').classList.toggle('d-none',!s);
  document.getElementById('wrapCobCompM').classList.toggle('d-none',!s);
}
function cobEditarCom(c){
  document.getElementById('cobComId').value=c.id;document.getElementById('cobComTitle').textContent='Editar comunicación';
  document.getElementById('cobComTipo').value=c.tipo_comunicacion||'';document.getElementById('cobComEstado').value=c.estado||'';
  document.getElementById('cobComFecha').value=(c.fecha||'').replace(' ','T');
  document.getElementById('cobComComent').value=c.comentario||'';document.getElementById('cobComFecComp').value=c.fecha_compromiso||'';document.getElementById('cobComMonto').value=c.monto_compromiso||'';
  const s=c.estado==='comprometido';document.getElementById('wrapCobComp').classList.toggle('d-none',!s);document.getElementById('wrapCobCompM').classList.toggle('d-none',!s);
  mCobCom.show();
}
async function cobGuardarCom(){
  const d={asociado_cedula:CED,tipo_origen:'credito',tipo_comunicacion:document.getElementById('cobComTipo').value,estado:document.getElementById('cobComEstado').value,
    fecha:document.getElementById('cobComFecha').value.replace('T',' '),comentario:document.getElementById('cobComComent').value.trim(),
    fecha_compromiso:document.getElementById('cobComFecComp').value||null,monto_compromiso:document.getElementById('cobComMonto').value||null};
  if(!d.fecha){alert('La fecha es obligatoria.');return;}
  try{
    const id=document.getElementById('cobComId').value;
    if(id)await api('cobranza/comunicacion',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:id,...d})});
    else await api('cobranza/comunicacion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});
    mCobCom.hide();cargarCobranza();
  }catch(e){alert(e.message);}
}
async function cobEliminarCom(id){if(!confirm('¿Eliminar esta comunicación?'))return;try{await api('cobranza/comunicacion',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});cargarCobranza();}catch(e){alert(e.message);}}
function cobNuevaInd(){
  document.getElementById('cobIndId').value='';document.getElementById('cobIndTipo').value='';
  document.getElementById('cobIndFecha').value=new Date().toISOString().slice(0,10);document.getElementById('cobIndArchivo').value='';mCobInd.show();
}
function cobEditarInd(i){
  document.getElementById('cobIndId').value=i.id;document.getElementById('cobIndTipo').value=i.tipo_indagacion;document.getElementById('cobIndFecha').value=i.fecha;document.getElementById('cobIndArchivo').value='';mCobInd.show();
}
async function cobGuardarInd(){
  const tipo=document.getElementById('cobIndTipo').value.trim(),fec=document.getElementById('cobIndFecha').value,id=document.getElementById('cobIndId').value;
  if(!tipo||!fec){alert('Tipo y fecha son obligatorios.');return;}
  const archivo=document.getElementById('cobIndArchivo').files[0];
  try{
    if(id)await api('cobranza/indagacion',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({id,tipo_indagacion:tipo,fecha:fec})});
    else{
      const nid=(await api('cobranza/indagacion',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({asociado_cedula:CED,tipo_indagacion:tipo,fecha:fec,tipo_origen:'credito'})})).id;
      if(archivo){const fd=new FormData();fd.append('indagacion_id',nid);fd.append('archivo',archivo);await fetch(API+'cobranza/adjunto',{method:'POST',body:fd});}
    }
    mCobInd.hide();cargarCobranza();
  }catch(e){alert(e.message);}
}
async function cobEliminarInd(id){if(!confirm('¿Eliminar esta indagación?'))return;try{await api('cobranza/indagacion',{method:'DELETE',headers:{'Content-Type':'application/json'},body:JSON.stringify({id})});cargarCobranza();}catch(e){alert(e.message);}}
</script>
<?php require dirname(__DIR__, 3) . '/views/layouts/footer.php'; ?>
