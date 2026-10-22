-- 016_asociados_comunicaciones_opt_in.sql
-- Opt-in maestro de comunicaciones por asociado.
-- El detalle del asociado lo administra (UI asociados_detalle) y el módulo
-- de comunicaciones lo consulta como fuente de consentimiento.
--
-- Aplicar: mysql multi_app_v2 < sql/migrations/016_asociados_comunicaciones_opt_in.sql
-- Idempotente.

ALTER TABLE asociados_control
  ADD COLUMN IF NOT EXISTS opt_in TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1 = autoriza comunicaciones (WhatsApp/SMS/email)' AFTER estado_activo;

-- Índice opcional (se crea solo si no existe; MariaDB 11.8 lo soporta).
CREATE INDEX IF NOT EXISTS idx_ac_opt_in ON asociados_control (opt_in);
