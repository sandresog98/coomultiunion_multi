-- 007_api_tokens.sql — API por tokens (Bearer) para integraciones externas (curl).
--
-- Dos tablas:
--   api_tokens                → tokens emitidos (el secreto se guarda SOLO hasheado).
--   api_endpoints_autorizados → allowlist global: qué claves (módulos o acciones)
--                               pueden invocarse por token. La gestiona la UI
--                               (ui/modules/api/pages/authorized_endpoints.php).
--
-- Reglas del proyecto: sin FK ni ENUM (§3), nombres/prefijos consistentes.
-- Expiración OBLIGATORIA (por defecto 180 días; la fija la aplicación al crear).
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/007_api_tokens.sql
-- Idempotente.

CREATE TABLE IF NOT EXISTS api_tokens (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(120) NOT NULL,          -- etiqueta legible ("Integración bot", "ERP")
  prefijo      VARCHAR(12)  NOT NULL,          -- primeros chars del secreto (UI/logs)
  token_hash   VARCHAR(128) NOT NULL,          -- SHA-256 del secreto (nunca en claro)
  actor_id     INT          NOT NULL,          -- FK implícita: usuarios.id (staff)
  scopes       TEXT         NULL,              -- JSON array de claves autorizadas para ESTE token
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  expires_at   DATETIME     NOT NULL,          -- obligatoria (por defecto +180 días)
  last_used_at DATETIME     NULL,
  created_by   INT          NULL,              -- FK implícita: usuarios.id
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  revoked_at   DATETIME     NULL,
  UNIQUE KEY uq_api_token_hash (token_hash),
  INDEX idx_api_token_prefijo (prefijo),
  INDEX idx_api_token_actor (actor_id),
  INDEX idx_api_token_expira (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_endpoints_autorizados (
  clave               VARCHAR(120) NOT NULL,   -- p.ej. 'solicitudes.gestion.crear_ticket' o 'solicitudes'
  descripcion         VARCHAR(255) NULL,
  activo              TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_actualizacion DATETIME     NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Semilla del piloto (Fase 1): crear ticket interno.
-- Se puede activar/desactivar luego desde la UI de endpoints autorizados.
INSERT INTO api_endpoints_autorizados (clave, descripcion, activo, fecha_actualizacion)
VALUES ('solicitudes.gestion.crear_ticket', 'Solicitudes: crear ticket interno', 1, NOW())
ON DUPLICATE KEY UPDATE clave = clave;
