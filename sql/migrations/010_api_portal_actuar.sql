-- 010_api_portal_actuar.sql — Scope especial: actuar en nombre de una cédula (Fase 3).
--
-- Habilita que un token pueda operar en el portal (cx/solicitudes) como un asociado
-- indicado por el header X-Acting-As-Cedula. Debe estar autorizado aquí para poder
-- asignarse a un token desde la UI. Idempotente.
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/010_api_portal_actuar.sql

INSERT INTO api_endpoints_autorizados (clave, descripcion, activo, fecha_actualizacion)
VALUES ('portal.actuar',
        'Portal: actuar en nombre de una cédula (X-Acting-As-Cedula)',
        1, NOW())
ON DUPLICATE KEY UPDATE clave = clave;
