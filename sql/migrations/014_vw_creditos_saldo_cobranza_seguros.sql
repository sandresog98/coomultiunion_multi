-- 014_vw_creditos_saldo_cobranza_seguros.sql
--
-- 1) saldo_total pasa a incluir SIEMPRE la cobranza (saldo Sifone + monto_cobranza),
--    según la lógica de la v1 ("Saldo Total" = saldot + cobranza).
-- 2) Nuevos campos calculados (que la v1 intentaba mostrar en "Ver detalle" pero
--    nunca resolvió: su JOIN daba 0/249 matches): seguro_vida, seguro_deudores
--    e interes. Se calculan con la tasa de la línea (credito_lineas), prorrateando
--    el mes en curso por días, y CORRIGIENDO el bug de la v1 (dividía seguro_vida
--    por /10; el resto de la app usa /100 → aquí los tres van /100).
-- 3) tasa_interes pasa a ser la tasa de interés real de la línea; la de Sifone
--    (que en realidad es el seguro) se expone como tasa_seguro.
--
-- Puente de nombres: Sifone (tipopr) no coincide con credito_lineas.nombre_credito,
-- así que cada línea guarda sus nombres Sifone en credito_lineas.sifone_alias
-- (lista separada por coma), EDITABLE en la UI Créditos > Líneas.

ALTER TABLE credito_lineas ADD COLUMN IF NOT EXISTS sifone_alias TEXT NULL;

-- Alias Sifone por línea (por nombre, los ids pueden variar).
UPDATE credito_lineas SET sifone_alias = 'consumo,consumo-,consumo-25,CONSUMO-24' WHERE TRIM(nombre_credito) = 'CONSUMO 1.7 ESPECIAL 2025';
UPDATE credito_lineas SET sifone_alias = 'CREDIAGIL-2026'                          WHERE TRIM(nombre_credito) = 'CREDIAGIL 1.9 2026';
UPDATE credito_lineas SET sifone_alias = 'CONSUMO ESPECIAL 1.8-2026'               WHERE TRIM(nombre_credito) = 'CONSUMO 1.8 ESPECIAL 2026';
UPDATE credito_lineas SET sifone_alias = 'BAJO CONSUMO 2026'                       WHERE TRIM(nombre_credito) = 'BAJO CONSUMO 2.0 NUE AP 2026';
UPDATE credito_lineas SET sifone_alias = 'LIBRANZA  1.5-2026'                      WHERE TRIM(nombre_credito) = 'LIBRANZA 1.5 2026';
UPDATE credito_lineas SET sifone_alias = 'CREDI-PORTES-2026'                       WHERE TRIM(nombre_credito) = 'CREDI-APORTES 2026';
UPDATE credito_lineas SET sifone_alias = 'CREDIPOLIZA  1.4-2026'                   WHERE TRIM(nombre_credito) = 'CREDIPOLIZA 1.4 2026';
UPDATE credito_lineas SET sifone_alias = 'TECNO-TELMOVIL -2026'                    WHERE TRIM(nombre_credito) = 'TECNO-TELMOVIL 2026';
UPDATE credito_lineas SET sifone_alias = 'EDUCACION  1.5-2026'                     WHERE TRIM(nombre_credito) = 'EDUCACION 1.5 APORTE 2026';
UPDATE credito_lineas SET sifone_alias = 'CONSUMO-ESPECIAL-1.7-2026'               WHERE TRIM(nombre_credito) = 'CONSUMO 1.7 ESPECIAL 2026';
UPDATE credito_lineas SET sifone_alias = 'CREDIAPORTES PLUS-2026'                  WHERE TRIM(nombre_credito) = 'CRED-APORTES PLUS';
UPDATE credito_lineas SET sifone_alias = 'EMPRENDER-2.1-2026'                      WHERE TRIM(nombre_credito) = 'EMPRENDER 2.1 2026';

-- Limpieza del diseño anterior (tabla puente), ya no se usa.
DROP TABLE IF EXISTS credito_linea_mapeo;

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
    ON CAST(dc.cedula AS UNSIGNED) = CAST(ec.cedula AS UNSIGNED)
   AND CAST(dc.numero_credito AS UNSIGNED) = CAST(ec.numero AS UNSIGNED)
   AND dc.codeudor = 0
  LEFT JOIN sifone_datacredito dc_co
    ON CAST(dc_co.numero_credito AS UNSIGNED) = CAST(ec.numero AS UNSIGNED)
   AND dc_co.codeudor = 1
  LEFT JOIN sifone_cartera_mora cm
    ON cm.presta = ec.numero AND cm.cedula = ec.cedula
  LEFT JOIN credito_lineas cl
    ON FIND_IN_SET(ec.tipopr, cl.sifone_alias) > 0
) base;
