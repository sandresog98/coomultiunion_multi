-- 002_email_cola.sql — Generalización de la cola de correo.
-- Renombra `fondo_bienestar_email_cola` (cola del Fondo) a `email_cola`
-- (cola general de salida de TODOS los correos de la app).
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/002_email_cola.sql
-- Idempotente: el RENAME falla si ya se aplicó; se tolera con `DROP VIEW/...` no.
-- Como el DDL canónico (sql/ddl.sql) ya crea `email_cola`, en una BD fresca
-- no hace falta esta migración; solo para BDs ya cargadas con el nombre viejo.

RENAME TABLE
  IF EXISTS fondo_bienestar_email_cola TO email_cola;