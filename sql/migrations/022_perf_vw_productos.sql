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
