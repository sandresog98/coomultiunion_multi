-- =====================================================================
-- Rendimiento app-wide — script unificado para DEV
-- Incluye migraciones 019 a 023. Es idempotente.
-- Aplicar con: mysql ... < _perf_unified_dev.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 019_perf_asociados_detalle.sql
-- ---------------------------------------------------------------------
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


-- ---------------------------------------------------------------------
-- 020_perf_app_wide.sql
-- ---------------------------------------------------------------------
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


-- ---------------------------------------------------------------------
-- 021_perf_views.sql
-- ---------------------------------------------------------------------
-- 021 — Claves numéricas normalizadas para vw_creditos.
-- Sifone guarda cédula/número sin ceros a la izquierda; las columnas generadas
-- permiten joins indexables sin CAST en tiempo de consulta.
ALTER TABLE sifone_estado_cuenta
  ADD COLUMN IF NOT EXISTS cedula_num BIGINT AS (CAST(cedula AS UNSIGNED)) STORED,
  ADD COLUMN IF NOT EXISTS numero_num BIGINT AS (CAST(numero AS UNSIGNED)) STORED;

ALTER TABLE sifone_datacredito
  ADD COLUMN IF NOT EXISTS cedula_num BIGINT AS (CAST(cedula AS UNSIGNED)) STORED,
  ADD COLUMN IF NOT EXISTS numero_num BIGINT AS (CAST(numero_credito AS UNSIGNED)) STORED;

CREATE INDEX IF NOT EXISTS idx_sec_cedula_num
  ON sifone_estado_cuenta (cedula_num, numero_num);

CREATE INDEX IF NOT EXISTS idx_sdc_cedula_num
  ON sifone_datacredito (cedula_num, numero_num, codeudor);

CREATE INDEX IF NOT EXISTS idx_sdc_numero_num
  ON sifone_datacredito (numero_num, codeudor);

CREATE OR REPLACE VIEW vw_creditos AS
SELECT base.*,
       GREATEST(0, base.cuotas_totales - base.cuotas_pendientes) AS cuotas_pagadas,
       ROUND(base.saldo_mora / NULLIF(base.valor_cuota, 0), 1) AS cuotas_vencidas,
       base.saldo_sifone + base.monto_cobranza AS saldo_total
FROM (
  SELECT ec.cedula,
         ec.numero  AS numero_credito,
         ec.tipopr  AS tipo_prestamo,
         ec.fechae  AS fecha_emision,
         ec.fechav  AS fecha_vencimiento,
         COALESCE(cm.fechap, dc.fecha_pago) AS fecha_pago,
         dc.desembolso_inicial,
         COALESCE(dc.cuota, ca.valorc, ec.valorc) AS valor_cuota,
         ec.saldoc  AS saldo_capital,
         ec.saldom  AS saldo_mora,
         ec.saldoi  AS saldo_intereses,
         ec.saldot  AS saldo_sifone,
         CASE WHEN ca.plazo > 0 THEN CAST(ca.plazo AS SIGNED)
              ELSE CAST(ec.cuotap AS SIGNED) END AS cuotas_totales,
         CAST(ec.cuotap AS SIGNED) AS cuotas_pendientes,
         COALESCE(cm.diav, 0)   AS dias_mora,
         ca.tasa    AS tasa_seguro,
         cl.tasa    AS tasa_interes,
         ca.plazo,
         CASE WHEN COALESCE(cm.diav, 0) > 60 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.08
              WHEN COALESCE(cm.diav, 0) > 50 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.06
              WHEN COALESCE(cm.diav, 0) > 40 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.05
              WHEN COALESCE(cm.diav, 0) > 30 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.04
              WHEN COALESCE(cm.diav, 0) > 20 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.03
              WHEN COALESCE(cm.diav, 0) > 11 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.02
              ELSE 0 END AS monto_cobranza,
         IF(ec.saldom > 0, ec.saldom, COALESCE(dc.cuota, ca.valorc, ec.valorc))
           + CASE WHEN COALESCE(cm.diav, 0) > 60 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.08
                  WHEN COALESCE(cm.diav, 0) > 50 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.06
                  WHEN COALESCE(cm.diav, 0) > 40 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.05
                  WHEN COALESCE(cm.diav, 0) > 30 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.04
                  WHEN COALESCE(cm.diav, 0) > 20 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.03
                  WHEN COALESCE(cm.diav, 0) > 11 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.02
                  ELSE 0 END AS pago_minimo,
         CASE WHEN cl.id IS NULL THEN NULL
              ELSE FLOOR(ec.saldoc * (cl.seguro_vida / 100) / 30
                         * GREATEST(0, DATEDIFF(CURDATE(), COALESCE(cm.fechap, dc.fecha_pago, ec.fechae) - INTERVAL 1 MONTH))) END AS seguro_vida,
         CASE WHEN cl.id IS NULL THEN NULL
              ELSE FLOOR(ec.saldoc * (cl.seguro_deudores / 100) / 30
                         * GREATEST(0, DATEDIFF(CURDATE(), COALESCE(cm.fechap, dc.fecha_pago, ec.fechae) - INTERVAL 1 MONTH))) END AS seguro_deudores,
         CASE WHEN cl.id IS NULL THEN NULL
              ELSE FLOOR(ec.saldoc * (cl.tasa / 100) / 30
                         * GREATEST(0, DATEDIFF(CURDATE(), COALESCE(cm.fechap, dc.fecha_pago, ec.fechae) - INTERVAL 1 MONTH))) END AS interes,
         dc_co.nombre    AS codeudor_nombre,
         dc_co.celular   AS codeudor_celular,
         dc_co.email     AS codeudor_email,
         dc_co.direccion AS codeudor_direccion
  FROM sifone_estado_cuenta ec
  LEFT JOIN sifone_cartera_aseguradora ca
    ON ca.numero = ec.numero AND ca.cedula = ec.cedula
  LEFT JOIN sifone_datacredito dc
    ON dc.cedula_num = ec.cedula_num
   AND dc.numero_num = ec.numero_num
   AND dc.codeudor = 0
  LEFT JOIN sifone_datacredito dc_co
    ON dc_co.numero_num = ec.numero_num
   AND dc_co.codeudor = 1
  LEFT JOIN sifone_cartera_mora cm
    ON cm.presta = ec.numero AND cm.cedula = ec.cedula
  LEFT JOIN credito_lineas cl
    ON FIND_IN_SET(ec.tipopr, cl.sifone_alias) > 0
) base;


-- ---------------------------------------------------------------------
-- 022_perf_vw_productos.sql
-- ---------------------------------------------------------------------
-- 022 — vw_productos calcula vw_creditos una sola vez.
-- Antes se ejecutaban 4 subconsultas correlacionadas contra vw_creditos por fila.
CREATE OR REPLACE VIEW vw_productos AS
SELECT sa.cedula,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND LOWER(nombre) = 'aportes ordinarios'), 0) AS aportes_ordinarios,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND nombre = 'APORTES SOCIALES 2'), 0) AS aportes_sociales,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND nombre = 'Revalorizacion Aportes'), 0) AS aportes_revalorizaciones,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND nombre = 'PLAN FUTURO'), 0) AS plan_futuro,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND LOWER(nombre) = 'aportes ordinarios'), 0)
        + COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                    WHERE cedula = sa.cedula AND nombre = 'APORTES SOCIALES 2'), 0) AS aportes_totales,
       COALESCE((SELECT SUM(FLOOR((POW(1.12, 1/12) - 1) * (ABS(credit) - ABS(debito))))
                 FROM sifone_movimientos_tributarios
                 WHERE cedula = sa.cedula AND cuenta = 31050501), 0) AS aportes_incentivos,
       (SELECT MAX(period) FROM sifone_balance_prueba
        WHERE cedula = sa.cedula AND LOWER(nombre) IN ('aportes ordinarios','aportes sociales 2')) AS aportes_ultimo_periodo,
       COALESCE((SELECT SUM(credit) - SUM(debito)
                 FROM sifone_movimientos_tributarios
                 WHERE cedula = sa.cedula
                   AND ((cuenta = 42309501)
                     OR (cuenta = 51109591 AND detall LIKE '%REVERSION%BOLSILLO%'))
                   AND fecham >= '2025-06-09'
                   AND detall NOT IN ('CIERRE DEL PYG')), 0) AS bolsillos,
       COALESCE(FLOOR((POW(1.06, 1/12) - 1) * (SELECT SUM(credit) - SUM(debito)
                 FROM sifone_movimientos_tributarios
                 WHERE cedula = sa.cedula
                   AND ((cuenta = 42309501)
                     OR (cuenta = 51109591 AND detall LIKE '%REVERSION%BOLSILLO%'))
                   AND fecham >= '2025-06-09'
                   AND detall NOT IN ('CIERRE DEL PYG'))), 0) AS bolsillos_incentivos,
       (SELECT MAX(fecham) FROM sifone_movimientos_tributarios
        WHERE cedula = sa.cedula AND cuenta IN (42309501, 51109591)) AS bolsillos_ultima_fecha,
       GREATEST(0, COALESCE((SELECT SUM(valor) FROM movimientos_multi
                   WHERE cedula = sa.cedula AND tipo = 'comision'), 0)
                 - COALESCE((SELECT SUM(ABS(valor)) FROM movimientos_multi
                   WHERE cedula = sa.cedula AND tipo = 'redencion_comisiones'), 0)) AS comisiones,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND LOWER(nombre) = 'aportes ordinarios'), 0)
        + COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                    WHERE cedula = sa.cedula AND nombre = 'APORTES SOCIALES 2'), 0)
        + COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                    WHERE cedula = sa.cedula AND nombre = 'Revalorizacion Aportes'), 0)
        + COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                    WHERE cedula = sa.cedula AND nombre = 'PLAN FUTURO'), 0)
        + COALESCE((SELECT SUM(credit) - SUM(debito)
                    FROM sifone_movimientos_tributarios
                    WHERE cedula = sa.cedula
                      AND ((cuenta = 42309501)
                        OR (cuenta = 51109591 AND detall LIKE '%REVERSION%BOLSILLO%'))
                      AND fecham >= '2025-06-09'
                      AND detall NOT IN ('CIERRE DEL PYG')), 0)
        + COALESCE((SELECT SUM(FLOOR((POW(1.12, 1/12) - 1) * (ABS(credit) - ABS(debito))))
                    FROM sifone_movimientos_tributarios
                    WHERE cedula = sa.cedula AND cuenta = 31050501), 0)
        + GREATEST(0, COALESCE((SELECT SUM(valor) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'comision'), 0)
                    - COALESCE((SELECT SUM(ABS(valor)) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'redencion_comisiones'), 0)) AS total_saldos_favor,
       COALESCE((SELECT SUM(FLOOR((POW(1.12, 1/12) - 1) * (ABS(credit) - ABS(debito))))
                 FROM sifone_movimientos_tributarios
                 WHERE cedula = sa.cedula AND cuenta = 31050501), 0)
        + GREATEST(0, COALESCE((SELECT SUM(valor) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'comision'), 0)
                    - COALESCE((SELECT SUM(ABS(valor)) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'redencion_comisiones'), 0)) AS total_incentivos,
       COALESCE(cr.total_creditos, 0) AS total_creditos,
       COALESCE(cr.total_capital_pendiente, 0) AS total_capital_pendiente,
       COALESCE(cr.total_mora, 0) AS total_mora,
       COALESCE(cr.pago_minimo_creditos, 0) AS pago_minimo_creditos,
       COALESCE((SELECT SUM(ap.monto) FROM asociados_productos ap
                 INNER JOIN productos p ON p.id = ap.producto_id
                 WHERE ap.cedula = sa.cedula AND p.tipo = 'producto' AND ap.estado_activo = 1 AND p.estado_activo = 1), 0) AS producto_mensual_total,
       COALESCE((SELECT SUM(ap.monto) FROM asociados_productos ap
                 INNER JOIN productos p ON p.id = ap.producto_id
                 WHERE ap.cedula = sa.cedula AND p.tipo = 'clausula' AND ap.estado_activo = 1 AND p.estado_activo = 1), 0) AS clausula_mensual_total,
       (SELECT MIN(ap.dia_pago) FROM asociados_productos ap
        INNER JOIN productos p ON p.id = ap.producto_id
        WHERE ap.cedula = sa.cedula AND p.tipo = 'producto' AND ap.estado_activo = 1 AND p.estado_activo = 1 AND ap.dia_pago IS NOT NULL) AS producto_dia_pago,
       EXISTS (SELECT 1 FROM asociados_productos ap
               INNER JOIN productos p ON p.id = ap.producto_id
               WHERE ap.cedula = sa.cedula AND p.nombre = 'Fondo Bienestar'
                 AND ap.estado_activo = 1 AND p.estado_activo = 1) AS fondo_bienestar_tiene_producto,
       EXISTS (SELECT 1 FROM sifone_movimientos_tributarios
               WHERE cedula = sa.cedula AND cuenta = 42209506 AND credit > 0
                 AND fecha >= DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01')) AS fondo_bienestar_al_dia
FROM (SELECT DISTINCT cedula FROM sifone_asociados WHERE cedula IS NOT NULL AND cedula <> '') sa
LEFT JOIN (
    SELECT cedula,
           COUNT(*) AS total_creditos,
           SUM(saldo_capital) AS total_capital_pendiente,
           SUM(saldo_mora) AS total_mora,
           SUM(pago_minimo) AS pago_minimo_creditos
    FROM vw_creditos
    GROUP BY cedula
) cr ON cr.cedula = sa.cedula;


-- ---------------------------------------------------------------------
-- 023_perf_pagos.sql
-- ---------------------------------------------------------------------
-- 023 — índices de pagos para filtros por fecha y confirmación.
CREATE INDEX IF NOT EXISTS idx_ppse_fecha
  ON pagos_pse (fecha_resolucion);

CREATE INDEX IF NOT EXISTS idx_pconf_estado_cedula
  ON pagos_confirmacion (estado, cedula);


