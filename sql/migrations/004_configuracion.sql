-- 004_configuracion.sql — Parámetros configurables de la aplicación.
-- Almacén clave/valor para reglas que antes estaban fijas en código
-- (p. ej. las reglas del crédito cooperativa de boletas/convenios).
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/004_configuracion.sql
-- Idempotente.

CREATE TABLE IF NOT EXISTS app_configuracion (
  clave               VARCHAR(60)  NOT NULL,
  valor               TEXT         NULL,
  descripcion         VARCHAR(255) NULL,
  fecha_actualizacion DATETIME     NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores por defecto (no sobrescribe los existentes).
INSERT INTO app_configuracion (clave, valor, descripcion) VALUES
  ('boleta_credito_meses_min',   '6',      'Antigüedad mínima (meses) para crédito cooperativa en boletas'),
  ('boleta_credito_aportes_min', '6',      'Nº mínimo de pagos de aportes ordinarios para crédito cooperativa'),
  ('boleta_credito_limite',      '100000', 'Límite en pesos del crédito cooperativa para boletas')
ON DUPLICATE KEY UPDATE clave = clave;
