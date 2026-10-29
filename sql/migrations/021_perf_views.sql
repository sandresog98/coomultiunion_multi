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
