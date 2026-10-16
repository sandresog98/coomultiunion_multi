-- 003_boleta_metodo_pago.sql — Método de pago de las solicitudes de boletas (convenios).
-- Agrega `metodo_pago` al detalle de boleta: 'qr' (pago por QR con comprobante) o
-- 'credito' (crédito de la cooperativa, con reglas de elegibilidad y límite).
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/003_boleta_metodo_pago.sql
-- Idempotente: usa IF NOT EXISTS (MariaDB 10.5+/MySQL 8+).

ALTER TABLE solicitudes_det_boleta
  ADD COLUMN IF NOT EXISTS metodo_pago VARCHAR(20) NULL;
