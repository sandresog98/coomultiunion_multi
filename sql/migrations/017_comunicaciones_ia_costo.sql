-- 017_comunicaciones_ia_costo.sql
-- Costo estimado de las generaciones de IA usadas para preparar un plan.
-- Se acumula por plan (mensaje + HTML) y se muestra separado del costo de envío.
--
-- Aplicar: mysql multi_app_v2 < sql/migrations/017_comunicaciones_ia_costo.sql
-- Idempotente.

ALTER TABLE com_planes
  ADD COLUMN IF NOT EXISTS ia_costo_usd DECIMAL(14,6) NOT NULL DEFAULT 0 AFTER costo_total_usd,
  ADD COLUMN IF NOT EXISTS ia_modelo VARCHAR(80) NULL AFTER ia_costo_usd,
  ADD COLUMN IF NOT EXISTS ia_tokens_prompt INT NULL AFTER ia_modelo,
  ADD COLUMN IF NOT EXISTS ia_tokens_completion INT NULL AFTER ia_tokens_prompt,
  ADD COLUMN IF NOT EXISTS ia_generaciones INT NOT NULL DEFAULT 0 AFTER ia_tokens_completion;
