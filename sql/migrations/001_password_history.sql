-- Migración: historial de contraseñas del staff (rotación 3 meses, no repetir últimas 5).
-- Mismo esquema que en sql/ddl.sql (canónico). Idempotente.
CREATE TABLE IF NOT EXISTS password_history (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT          NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ph_user (user_id),
  INDEX idx_ph_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;