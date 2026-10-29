-- 023 — índices de pagos para filtros por fecha y confirmación.
CREATE INDEX IF NOT EXISTS idx_ppse_fecha
  ON pagos_pse (fecha_resolucion);

CREATE INDEX IF NOT EXISTS idx_pconf_estado_cedula
  ON pagos_confirmacion (estado, cedula);
