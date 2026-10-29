-- 020 — Índices de rendimiento transversales.
-- Cubre filtros por fecha, listados administrativos y conteos de stock.
-- Idempotente para MariaDB 10.2+.

-- Logs: filtros y orden por fecha.
CREATE INDEX IF NOT EXISTS idx_log_timestamp
  ON logs (timestamp);

-- Movimientos genéricos: filtros por fecha y por cédula/tipo + fecha.
CREATE INDEX IF NOT EXISTS idx_mm_fecha
  ON movimientos_multi (fecha);

CREATE INDEX IF NOT EXISTS idx_mm_cedula_fecha
  ON movimientos_multi (cedula, fecha);

CREATE INDEX IF NOT EXISTS idx_mm_tipo_fecha
  ON movimientos_multi (tipo, fecha);

-- Transacciones: listado global por fecha (el detalle por cédula ya tiene idx_trx_cedula_fecha).
CREATE INDEX IF NOT EXISTS idx_trx_fecha
  ON transacciones (fecha_creacion);

-- Pagos: filtros por estado/fecha y por cédula/fecha.
CREATE INDEX IF NOT EXISTS idx_ppse_estado_fecha
  ON pagos_pse (estado, fecha_resolucion);

CREATE INDEX IF NOT EXISTS idx_pconf_fecha
  ON pagos_confiar (fecha);

-- Tienda: conteos de stock por producto y listados por fecha.
CREATE INDEX IF NOT EXISTS idx_tstock_producto_estado
  ON tienda_stock (producto_id, estado);

CREATE INDEX IF NOT EXISTS idx_tvent_fecha
  ON tienda_ventas (fecha_venta);

CREATE INDEX IF NOT EXISTS idx_tcomp_fecha
  ON tienda_compras (fecha_compra);

-- Boletas: conteos por producto/estado y listados por estado/fecha.
CREATE INDEX IF NOT EXISTS idx_bol_producto_estado
  ON boleteria_boletas (producto_id, estado);

CREATE INDEX IF NOT EXISTS idx_bol_estado_creacion
  ON boleteria_boletas (estado, fecha_creacion);

CREATE INDEX IF NOT EXISTS idx_bol_estado_vendida
  ON boleteria_boletas (estado, fecha_vendida);

-- Convenios: productos ofertables por convenio.
CREATE INDEX IF NOT EXISTS idx_cp_convenio_stock_activo
  ON convenios_productos (convenio_id, maneja_stock, activo);

-- Solicitudes: listado combinado por tipo/estado/fecha.
CREATE INDEX IF NOT EXISTS idx_sol_tipo_estado_fecha
  ON solicitudes (tipo_codigo, estado_codigo, fecha_creacion);

-- Créditos: listado combinado por estado/etapa/fecha.
CREATE INDEX IF NOT EXISTS idx_cs_estado_etapa_fecha
  ON credito_solicitudes (estado, etapa_actual, fecha_creacion);
