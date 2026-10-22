-- 009_api_token_rate.sql — Límite de solicitudes por token (ventana fija).
--
-- Un registro por (token, ventana). La ventana se calcula como
-- floor(epoch / API_TOKEN_RATE_WINDOW) en el código (core/TokenAuth.php).
-- Sin FK/ENUM (§3). Idempotente.
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/009_api_token_rate.sql

CREATE TABLE IF NOT EXISTS api_token_rate (
  token_id INT         NOT NULL,   -- FK implícita: api_tokens.id
  ventana  VARCHAR(20) NOT NULL,   -- número de ventana (epoch / ventana_seg)
  usos     INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (token_id, ventana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
