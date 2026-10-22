-- 005_chat_ia.sql — Asistente IA (chat) del Portal de Asociados (cx/).
-- Crea las tablas del módulo chat_* (sin FK ni ENUM, prefijo de módulo),
-- la tabla de precios por modelo (costo en USD, con tarifas estándar y de
-- ventana con descuento + tokens en caché) y los parámetros de configuración.
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/005_chat_ia.sql
-- Idempotente.

-- Conversaciones del asociado. Identidad = cédula (sesión del portal).
-- FK: sifone_asociados.cedula  |  valores.estado: activa|archivada
CREATE TABLE IF NOT EXISTS chat_sesiones (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cedula              VARCHAR(20)  NOT NULL,
  titulo              VARCHAR(255) NULL,
  estado              VARCHAR(15)  NOT NULL DEFAULT 'activa',
  fecha_creacion      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_chatses_cedula (cedula, fecha_actualizacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mensajes de cada conversación. FK: chat_sesiones.id
-- valores.rol: user|assistant  |  valores.estado: ok|error|filtrado
CREATE TABLE IF NOT EXISTS chat_mensajes (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sesion_id         INT UNSIGNED  NOT NULL,
  rol               VARCHAR(15)   NOT NULL,
  contenido         MEDIUMTEXT    NOT NULL,
  modelo            VARCHAR(80)   NULL,
  tokens_prompt     INT           NULL,
  tokens_completion INT           NULL,
  tokens_cached     INT           NULL,                    -- tokens de entrada servidos desde caché
  latencia_ms       INT           NULL,
  costo_usd         DECIMAL(14,6) NULL,
  estado            VARCHAR(15)   NULL,
  fecha_creacion    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chatmsg_sesion (sesion_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Base de conocimiento (RAG ligero por FULLTEXT). Alimenta el system prompt.
-- valores.estado: 1 activo | 0 inactivo
CREATE TABLE IF NOT EXISTS chat_conocimiento (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria           VARCHAR(80)  NULL,
  titulo              VARCHAR(255) NOT NULL,
  palabras_clave      VARCHAR(255) NULL,
  contenido           MEDIUMTEXT   NOT NULL,
  fuente              VARCHAR(255) NULL,
  estado              TINYINT      NOT NULL DEFAULT 1,
  version             INT          NOT NULL DEFAULT 1,
  fecha_actualizacion DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FULLTEXT KEY ft_chatcon (titulo, palabras_clave, contenido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cupo/consumo diario por asociado (control de costos y abuso).
CREATE TABLE IF NOT EXISTS chat_uso (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cedula            VARCHAR(20)   NOT NULL,
  fecha             DATE          NOT NULL,
  mensajes          INT           NOT NULL DEFAULT 0,
  tokens_prompt     INT           NOT NULL DEFAULT 0,
  tokens_completion INT           NOT NULL DEFAULT 0,
  tokens_cached     INT           NOT NULL DEFAULT 0,
  costo_usd         DECIMAL(14,6) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_chatuso_cedula_fecha (cedula, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Precio por modelo en USD por 1.000.000 de tokens, versionado por fecha.
-- Tarifas "off" (con descuento) aplican dentro de la ventana [off_desde_utc, off_hasta_utc).
CREATE TABLE IF NOT EXISTS chat_precios_modelo (
  id                    INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  modelo                VARCHAR(80)   NOT NULL,
  precio_input_usd      DECIMAL(12,6) NOT NULL,
  precio_output_usd     DECIMAL(12,6) NOT NULL,
  precio_cached_usd     DECIMAL(12,6) NOT NULL DEFAULT 0,
  precio_input_off_usd  DECIMAL(12,6) NULL,
  precio_output_off_usd DECIMAL(12,6) NULL,
  precio_cached_off_usd DECIMAL(12,6) NULL,
  off_desde_utc         TINYINT       NULL,               -- hora UTC inicio ventana con descuento (0-24)
  off_hasta_utc         TINYINT       NULL,               -- hora UTC fin ventana con descuento (0-24)
  vigente_desde         DATETIME      NOT NULL,
  nota                  VARCHAR(255)  NULL,
  UNIQUE KEY uq_chatprecio_modelo_fecha (modelo, vigente_desde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valoración de respuestas (útil / no útil). FK: chat_mensajes.id
-- valores.util: 1 útil | 0 no útil
CREATE TABLE IF NOT EXISTS chat_feedback (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mensaje_id     BIGINT UNSIGNED NOT NULL,
  cedula         VARCHAR(20)     NOT NULL,
  util           TINYINT         NOT NULL,
  comentario     VARCHAR(500)    NULL,
  fecha_creacion DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_chatfb_mensaje_cedula (mensaje_id, cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Parámetros configurables del asistente (app_configuracion).
-- ---------------------------------------------------------------------
INSERT INTO app_configuracion (clave, valor, descripcion) VALUES
  ('chat_ia_habilitado',      '1',                         'Asistente IA del portal: 1 activo | 0 deshabilitado'),
  ('chat_ia_limite_diario',   '50',                        'Máximo de mensajes por asociado por día'),
  ('chat_ia_max_tokens',      '800',                       'Tope de tokens de salida por respuesta'),
  ('chat_ia_prompt_version',  '2026-10-16',                'Versión vigente del system prompt'),
  ('chat_ia_contacto_soporte','atenciongalerias@coomultiunion.com', 'Correo de escalado a soporte humano')
ON DUPLICATE KEY UPDATE clave = clave;

-- ---------------------------------------------------------------------
-- Precio real de deepseek-ai/DeepSeek-V4.1-Flash en SiliconFlow
-- (USD por 1.000.000 de tokens). Tarifa estándar 00:00-14:00 UTC y
-- tarifa con descuento 14:00-24:00 UTC.
-- ---------------------------------------------------------------------
INSERT INTO chat_precios_modelo
  (modelo, precio_input_usd, precio_output_usd, precio_cached_usd,
   precio_input_off_usd, precio_output_off_usd, precio_cached_off_usd,
   off_desde_utc, off_hasta_utc, vigente_desde, nota)
VALUES
  ('deepseek-ai/DeepSeek-V4.1-Flash', 0.300000, 1.200000, 0.006000,
   0.150000, 0.600000, 0.003000, 14, 24, '2026-01-01 00:00:00',
   'SiliconFlow: estándar 00-14 UTC · descuento 14-24 UTC')
ON DUPLICATE KEY UPDATE
  precio_input_usd = VALUES(precio_input_usd),
  precio_output_usd = VALUES(precio_output_usd),
  precio_cached_usd = VALUES(precio_cached_usd),
  precio_input_off_usd = VALUES(precio_input_off_usd),
  precio_output_off_usd = VALUES(precio_output_off_usd),
  precio_cached_off_usd = VALUES(precio_cached_off_usd),
  off_desde_utc = VALUES(off_desde_utc),
  off_hasta_utc = VALUES(off_hasta_utc);
