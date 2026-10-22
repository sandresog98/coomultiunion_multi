-- 012_fix_vw_creditos_cuotas.sql
--
-- Corrige el cálculo de cuotas de vw_creditos.
--
-- Problema: la vista mostraba a los créditos como "pagados" aunque tuvieran
-- saldo vivo. Dos causas:
--   1. `cuotas_totales` tomaba sifone_estado_cuenta.cuotap, que en realidad
--      son las cuotas PENDIENTES (no el total del crédito).
--   2. `cuotas_pendientes` se tomaba de sifone_datacredito.cuotas_pendientes y,
--      al no existir/matchar la fila, caía a 0. Resultado: pagadas = totales.
--
-- Fix:
--   * cuotas_totales    = sifone_cartera_aseguradora.plazo (meses reales del
--                         crédito; fallback a ec.cuotap si no hay plazo).
--   * cuotas_pendientes = sifone_estado_cuenta.cuotap.
--   * cuotas_pagadas    = cuotas_totales - cuotas_pendientes (nunca negativo).
--
-- Idempotente: CREATE OR REPLACE VIEW (no rompe a vw_asociados, que la referencia).

CREATE OR REPLACE VIEW vw_creditos AS
SELECT base.*,
       GREATEST(0, base.cuotas_totales - base.cuotas_pendientes) AS cuotas_pagadas,
       ROUND(base.saldo_mora / NULLIF(base.valor_cuota, 0), 1) AS cuotas_vencidas,
       CASE WHEN base.dias_mora > 60 THEN base.valor_cuota * 0.08
            WHEN base.dias_mora > 50 THEN base.valor_cuota * 0.06
            WHEN base.dias_mora > 40 THEN base.valor_cuota * 0.05
            WHEN base.dias_mora > 30 THEN base.valor_cuota * 0.04
            WHEN base.dias_mora > 20 THEN base.valor_cuota * 0.03
            WHEN base.dias_mora > 11 THEN base.valor_cuota * 0.02
            ELSE 0 END AS monto_cobranza,
       IF(base.saldo_mora > 0, base.saldo_mora, base.valor_cuota)
         + CASE WHEN base.dias_mora > 60 THEN base.valor_cuota * 0.08
                WHEN base.dias_mora > 50 THEN base.valor_cuota * 0.06
                WHEN base.dias_mora > 40 THEN base.valor_cuota * 0.05
                WHEN base.dias_mora > 30 THEN base.valor_cuota * 0.04
                WHEN base.dias_mora > 20 THEN base.valor_cuota * 0.03
                WHEN base.dias_mora > 11 THEN base.valor_cuota * 0.02
                ELSE 0 END AS pago_minimo
FROM (
  SELECT ec.cedula,
         ec.numero  AS numero_credito,
         ec.tipopr  AS tipo_prestamo,
         ec.fechae  AS fecha_emision,
         dc.fecha_vencimiento,
         COALESCE(cm.fechap, dc.fecha_pago) AS fecha_pago,
         dc.desembolso_inicial,
         COALESCE(dc.cuota, ca.valorc, ec.valorc) AS valor_cuota,
         ec.saldoc  AS saldo_capital,
         ec.saldom  AS saldo_mora,
         ec.saldoi  AS saldo_intereses,
         ec.saldot  AS saldo_total,
         CASE WHEN ca.plazo > 0 THEN CAST(ca.plazo AS SIGNED)
              ELSE CAST(ec.cuotap AS SIGNED) END AS cuotas_totales,
         CAST(ec.cuotap AS SIGNED) AS cuotas_pendientes,
         COALESCE(cm.diav, 0)   AS dias_mora,
         ca.tasa    AS tasa_interes,
         ca.plazo,
         dc_co.nombre    AS codeudor_nombre,
         dc_co.celular   AS codeudor_celular,
         dc_co.email     AS codeudor_email,
         dc_co.direccion AS codeudor_direccion
  FROM sifone_estado_cuenta ec
  LEFT JOIN sifone_cartera_aseguradora ca
    ON ca.numero = ec.numero AND ca.cedula = ec.cedula
  LEFT JOIN sifone_datacredito dc
    ON dc.numero_credito = ec.numero AND dc.cedula = ec.cedula AND dc.codeudor = 0
  LEFT JOIN sifone_datacredito dc_co
    ON dc_co.numero_credito = ec.numero AND dc_co.codeudor = 1
  LEFT JOIN sifone_cartera_mora cm
    ON cm.presta = ec.numero AND cm.cedula = ec.cedula
) base;
