-- 013_fix_datacredito_join.sql
--
-- Arregla el join de sifone_datacredito, que nunca pegaba porque Sifone trae
-- cedula y numero_credito SIN ceros a la izquierda, mientras el resto del
-- esquema (sifone_asociados / estado_cuenta / cartera) los guarda a 10.
-- Resultado previo: fecha_vencimiento, desembolso_inicial, valor de cuota y
-- codeudor quedaban NULL en vw_creditos y el portal no distinguía finalizados.
--
--   1) Backfill: normaliza las llaves existentes a 10 (idempotente).
--   2) Recrea vw_creditos con:
--        - join de datacredito por CAST numérico (blindaje si vuelve sin padding).
--        - fecha_vencimiento desde sifone_estado_cuenta.fechav (fuente nativa).
--        - el fix de cuotas de la migración 012 (totales=plazo, pendientes=ec.cuotap).
--
-- SifoneImporter ya normaliza estas llaves al importar (evita que se repita).

UPDATE sifone_datacredito
SET cedula         = LPAD(cedula, 10, '0'),
    numero_credito = LPAD(numero_credito, 10, '0');

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
         ec.fechav  AS fecha_vencimiento,
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
    ON CAST(dc.cedula AS UNSIGNED) = CAST(ec.cedula AS UNSIGNED)
   AND CAST(dc.numero_credito AS UNSIGNED) = CAST(ec.numero AS UNSIGNED)
   AND dc.codeudor = 0
  LEFT JOIN sifone_datacredito dc_co
    ON CAST(dc_co.numero_credito AS UNSIGNED) = CAST(ec.numero AS UNSIGNED)
   AND dc_co.codeudor = 1
  LEFT JOIN sifone_cartera_mora cm
    ON cm.presta = ec.numero AND cm.cedula = ec.cedula
) base;
