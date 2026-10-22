-- 011_chat_ia_nombre.sql — Nombre visible del asistente IA del portal.
-- Aplicar:  mysql multi_app_v2 < sql/migrations/011_chat_ia_nombre.sql
-- Idempotente.

INSERT INTO app_configuracion (clave, valor, descripcion) VALUES
  ('chat_ia_nombre', 'Soli', 'Nombre visible del asistente IA del portal (se usa en el prompt y la bienvenida)')
ON DUPLICATE KEY UPDATE clave = clave;
