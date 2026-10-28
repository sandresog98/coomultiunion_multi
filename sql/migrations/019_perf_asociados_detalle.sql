-- 019 — Índices de apoyo para la ficha de asociado.
-- Objetivo: evitar filesorts y escaneos en las consultas de movimientos,
-- historial de cobranza y agregados de la vista de productos.
-- Idempotente para MariaDB 10.2+.

-- Movimientos: paginación/filtros por cédula y fecha.
CREATE INDEX IF NOT EXISTS idx_trx_cedula_fecha
  ON transacciones (cedula, fecha_creacion);

-- Cobranza: historial y compromisos por cédula, ordenados por fecha descendente.
CREATE INDEX IF NOT EXISTS idx_cc_cedula_fecha
  ON cobranza_comunicaciones (asociado_cedula, fecha, id);

CREATE INDEX IF NOT EXISTS idx_ci_cedula_fecha
  ON cobranza_indagaciones (asociado_cedula, fecha, id);

-- Productos asignados: resumen por asociado y estado.
CREATE INDEX IF NOT EXISTS idx_ap_cedula_estado_prod
  ON asociados_productos (cedula, estado_activo, producto_id);

-- Agregados de aportes y movimientos usados por vw_productos.
CREATE INDEX IF NOT EXISTS idx_sbp_cedula_nombre
  ON sifone_balance_prueba (cedula, nombre);

CREATE INDEX IF NOT EXISTS idx_smt_cedula_cuenta_fecha
  ON sifone_movimientos_tributarios (cedula, cuenta, fecham);
