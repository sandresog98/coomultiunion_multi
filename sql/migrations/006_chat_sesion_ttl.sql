-- 006_chat_sesion_ttl.sql — Cierre de conversaciones del asistente por inactividad.
-- Agrega el parámetro configurable del TTL (minutos de inactividad tras los que
-- una conversación se cierra y hay que abrir una nueva).
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/006_chat_sesion_ttl.sql
-- Idempotente.

INSERT INTO app_configuracion (clave, valor, descripcion) VALUES
  ('chat_ia_sesion_ttl', '15', 'Minutos de inactividad tras los que se cierra una conversación del asistente')
ON DUPLICATE KEY UPDATE clave = clave;
