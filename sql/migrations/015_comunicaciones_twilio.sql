-- 015_comunicaciones_twilio.sql — Módulo de comunicaciones (WhatsApp, SMS y email).
--
-- Objetivo: enviar comunicaciones individuales o por base, programar planes,
-- registrar estados/costos de Twilio y SendGrid, y gestionar opt-in/opt-out.
--
-- Reglas del proyecto: sin FK ni ENUM (§3), prefijo de módulo `com_`.
-- Aplicar:  mysql multi_app_v2 < sql/migrations/015_comunicaciones_twilio.sql
-- Idempotente.

-- Remitentes habilitados (números Twilio, WhatsApp o correos SendGrid/SMTP).
-- valores.canal: sms | whatsapp | email
-- valores.proveedor: twilio | sendgrid | smtp
CREATE TABLE IF NOT EXISTS com_remitentes (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  canal                 VARCHAR(12)  NOT NULL,
  proveedor             VARCHAR(15)  NOT NULL DEFAULT 'twilio',
  etiqueta              VARCHAR(100) NOT NULL,
  direccion             VARCHAR(160) NOT NULL,       -- +57..., whatsapp:+57..., correo@dominio
  nombre_remitente      VARCHAR(150) NULL,           -- nombre visible en email
  messaging_service_sid VARCHAR(40)  NULL,           -- MG... (opcional)
  soporta_media         TINYINT(1)   NOT NULL DEFAULT 0,
  limite_mps            INT          NOT NULL DEFAULT 1,
  activo                TINYINT(1)   NOT NULL DEFAULT 1,
  predeterminado        TINYINT(1)   NOT NULL DEFAULT 0,
  creado_por            INT          NULL,           -- FK implícita: usuarios.id
  fecha_creacion        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   DATETIME     NULL,
  UNIQUE KEY uq_com_rem_canal_dir (canal, direccion),
  INDEX idx_com_rem_canal (canal, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contactos por canal con consentimiento (opt-in) y baja (opt-out).
-- valores.canal: sms | whatsapp | email
CREATE TABLE IF NOT EXISTS com_contactos (
  id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
  canal              VARCHAR(12)  NOT NULL,
  direccion          VARCHAR(160) NOT NULL,          -- E.164 o correo
  nombre             VARCHAR(150) NULL,
  asociado_cedula    VARCHAR(20)  NULL,              -- FK implícita: sifone_asociados.cedula
  opt_in             TINYINT(1)   NOT NULL DEFAULT 0,
  opt_in_fuente      VARCHAR(30)  NULL,              -- carga | manual | portal | api
  opt_in_fecha       DATETIME     NULL,
  opt_out            TINYINT(1)   NOT NULL DEFAULT 0,
  opt_out_fuente     VARCHAR(30)  NULL,              -- inbound | manual | sendgrid | api
  opt_out_fecha      DATETIME     NULL,
  opt_out_motivo     VARCHAR(160) NULL,
  activo             TINYINT(1)   NOT NULL DEFAULT 1,
  metadata_json      LONGTEXT     NULL,
  fecha_creacion     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME    NULL,
  UNIQUE KEY uq_com_contacto_canal_dir (canal, direccion),
  INDEX idx_com_contacto_cedula (asociado_cedula),
  INDEX idx_com_contacto_opt (canal, opt_in, opt_out)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Plantillas: Twilio Content (WhatsApp) y plantillas locales/SendGrid.
-- valores.proveedor: twilio_content | sendgrid | local
-- valores.canal: whatsapp | email
-- valores.estado_aprobacion: approved | pending | rejected | paused | disabled | unsubmitted
CREATE TABLE IF NOT EXISTS com_plantillas (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  proveedor             VARCHAR(20)  NOT NULL DEFAULT 'twilio_content',
  proveedor_template_id VARCHAR(80)  NULL,           -- HX... o template id de SendGrid
  nombre                VARCHAR(150) NOT NULL,
  canal                 VARCHAR(12)  NOT NULL DEFAULT 'whatsapp',
  categoria             VARCHAR(25)  NULL,           -- marketing | utility | authentication
  idioma                VARCHAR(8)   NOT NULL DEFAULT 'es',
  asunto                VARCHAR(255) NULL,
  cuerpo                TEXT         NULL,
  cuerpo_html           MEDIUMTEXT   NULL,
  variables_json        TEXT         NULL,           -- {"1":"nombre","2":"cedula"}
  media_json            TEXT         NULL,           -- ["https://..."]
  tipos_json            LONGTEXT     NULL,           -- respuesta cruda de Twilio Content
  estado_aprobacion     VARCHAR(20)  NULL,
  motivo_rechazo        VARCHAR(255) NULL,
  activo                TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_sincronizacion  DATETIME     NULL,
  creado_por            INT          NULL,           -- FK implícita: usuarios.id
  fecha_creacion        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   DATETIME     NULL,
  UNIQUE KEY uq_com_plantilla_proveedor (proveedor, proveedor_template_id),
  INDEX idx_com_plantilla_canal (canal, estado_aprobacion, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adjuntos del módulo. Los archivos se guardan fuera del webroot (uploads/)
-- y se sirven por URL firmada para Twilio o por descarga autenticada.
CREATE TABLE IF NOT EXISTS com_adjuntos (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  nombre_original  VARCHAR(255) NOT NULL,
  clave            VARCHAR(255) NOT NULL,             -- ruta opaca dentro de uploads/comunicaciones
  mime             VARCHAR(120) NOT NULL,
  tamano           BIGINT       NOT NULL DEFAULT 0,
  canal            VARCHAR(12)  NULL,                 -- whatsapp | email | sms
  creado_por       INT          NULL,                 -- FK implícita: usuarios.id
  fecha_creacion   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_com_adjunto_clave (clave),
  INDEX idx_com_adjunto_canal (canal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Planes/campañas. Un envío individual también crea un plan (tipo=individual),
-- para que todo quede trazado y con costo.
-- valores.tipo: masiva | individual | prueba
-- valores.canal: sms | whatsapp | email
-- valores.proveedor: twilio | sendgrid | smtp
-- valores.modo_contenido: texto | plantilla
-- valores.estado: borrador | programado | en_proceso | completado | parcial | cancelado | fallido
CREATE TABLE IF NOT EXISTS com_planes (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  nombre                VARCHAR(160) NOT NULL,
  descripcion           TEXT         NULL,
  tipo                  VARCHAR(15)  NOT NULL DEFAULT 'masiva',
  canal                 VARCHAR(12)  NOT NULL,
  proveedor             VARCHAR(15)  NOT NULL DEFAULT 'twilio',
  remitente_id          INT          NULL,           -- FK implícita: com_remitentes.id
  plantilla_id          INT          NULL,           -- FK implícita: com_plantillas.id
  proveedor_template_id VARCHAR(80)  NULL,           -- snapshot del ContentSid/template
  modo_contenido        VARCHAR(12)  NOT NULL DEFAULT 'texto',
  asunto                VARCHAR(255) NULL,
  cuerpo                MEDIUMTEXT   NULL,
  cuerpo_html           MEDIUMTEXT   NULL,
  media_json            TEXT         NULL,           -- [id_adjunto,...]
  variables_json        TEXT         NULL,           -- {"1":"nombre","2":"cedula"}
  origen                VARCHAR(15)  NOT NULL DEFAULT 'manual',
  filtros_json          TEXT         NULL,
  programado_para       DATETIME     NULL,           -- UTC
  zona_horaria          VARCHAR(40)  NOT NULL DEFAULT 'America/Bogota',
  estado                VARCHAR(15)  NOT NULL DEFAULT 'borrador',
  total_destinatarios   INT          NOT NULL DEFAULT 0,
  total_omitidos        INT          NOT NULL DEFAULT 0,
  total_enviados        INT          NOT NULL DEFAULT 0,
  total_entregados      INT          NOT NULL DEFAULT 0,
  total_leidos          INT          NOT NULL DEFAULT 0,
  total_fallidos        INT          NOT NULL DEFAULT 0,
  costo_estimado_usd    DECIMAL(14,6) NOT NULL DEFAULT 0,
  costo_total_usd       DECIMAL(14,6) NOT NULL DEFAULT 0,
  moneda                VARCHAR(3)   NOT NULL DEFAULT 'USD',
  creado_por            INT          NULL,           -- FK implícita: usuarios.id
  programado_por        INT          NULL,           -- FK implícita: usuarios.id
  fecha_ejecucion       DATETIME     NULL,
  fecha_fin             DATETIME     NULL,
  fecha_cancelacion     DATETIME     NULL,
  cancelado_por         INT          NULL,           -- FK implícita: usuarios.id
  error_resumen         TEXT         NULL,
  fecha_creacion        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   DATETIME     NULL,
  INDEX idx_com_plan_estado (estado, programado_para),
  INDEX idx_com_plan_creado (creado_por),
  INDEX idx_com_plan_canal (canal, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Un destinatario por plan. destino es E.164 o correo según el canal.
-- valores.estado: pendiente | enviando | enviado | entregado | leido | fallido | omitido | cancelado
CREATE TABLE IF NOT EXISTS com_destinatarios (
  id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
  plan_id             INT          NOT NULL,         -- FK implícita: com_planes.id
  contacto_id         BIGINT       NULL,             -- FK implícita: com_contactos.id
  asociado_cedula     VARCHAR(20)  NULL,             -- FK implícita: sifone_asociados.cedula
  nombre              VARCHAR(150) NULL,
  destino             VARCHAR(160) NOT NULL,
  canal               VARCHAR(12)  NOT NULL,
  variables_json      TEXT         NULL,
  estado              VARCHAR(15)  NOT NULL DEFAULT 'pendiente',
  motivo_omitido      VARCHAR(160) NULL,
  proveedor_message_id VARCHAR(80) NULL,             -- SID Twilio o X-Message-Id SendGrid
  estado_proveedor    VARCHAR(25)  NULL,             -- queued/sent/delivered/read/.../accepted/...
  error_code          INT          NULL,
  error_message       VARCHAR(255) NULL,
  num_segmentos       INT          NULL,
  precio_usd          DECIMAL(14,6) NULL,
  precio_moneda       VARCHAR(3)   NULL,
  intentos            INT          NOT NULL DEFAULT 0,
  ultimo_intento      DATETIME     NULL,
  fecha_envio         DATETIME     NULL,
  fecha_entrega       DATETIME     NULL,
  fecha_lectura       DATETIME     NULL,
  fecha_creacion      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME     NULL,
  UNIQUE KEY uq_com_dest_plan_destino (plan_id, destino),
  INDEX idx_com_dest_plan_estado (plan_id, estado),
  INDEX idx_com_dest_sid (proveedor_message_id),
  INDEX idx_com_dest_contacto (contacto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Eventos crudos de estado (webhooks, intentos, errores) para auditoría.
-- valores.tipo: status | inbound | send | error | sendgrid
CREATE TABLE IF NOT EXISTS com_eventos (
  id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
  plan_id             INT          NULL,             -- FK implícita: com_planes.id
  destinatario_id     BIGINT       NULL,             -- FK implícita: com_destinatarios.id
  proveedor           VARCHAR(15)  NULL,             -- twilio | sendgrid | smtp
  proveedor_message_id VARCHAR(80) NULL,
  tipo                VARCHAR(15)  NOT NULL,
  estado              VARCHAR(25)  NULL,
  error_code          INT          NULL,
  error_message       VARCHAR(255) NULL,
  payload_json        LONGTEXT     NULL,
  fecha_evento        DATETIME     NULL,
  fecha_creacion      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_com_evento_plan (plan_id),
  INDEX idx_com_evento_dest (destinatario_id),
  INDEX idx_com_evento_sid (proveedor_message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mensajes entrantes (respuestas, palabras de baja, HELP). Permite la ventana
-- de 24 h de WhatsApp y una futura bandeja conversacional.
CREATE TABLE IF NOT EXISTS com_entrantes (
  id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
  proveedor           VARCHAR(15)  NOT NULL DEFAULT 'twilio',
  proveedor_message_id VARCHAR(80) NOT NULL,
  canal               VARCHAR(12)  NOT NULL,
  de                  VARCHAR(160) NOT NULL,         -- dirección normalizada (sin whatsapp:)
  para                VARCHAR(160) NULL,
  asunto              VARCHAR(255) NULL,
  cuerpo              MEDIUMTEXT   NULL,
  media_json          TEXT         NULL,
  asociado_cedula     VARCHAR(20)  NULL,             -- FK implícita: sifone_asociados.cedula
  contacto_id         BIGINT       NULL,             -- FK implícita: com_contactos.id
  plan_id             INT          NULL,             -- FK implícita: com_planes.id
  destinatario_id     BIGINT       NULL,             -- FK implícita: com_destinatarios.id
  leido               TINYINT(1)   NOT NULL DEFAULT 0,
  atendido_por        INT          NULL,             -- FK implícita: usuarios.id
  fecha_proveedor     DATETIME     NULL,
  fecha_creacion      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_com_entrante_sid (proveedor, proveedor_message_id),
  INDEX idx_com_entrante_de (de),
  INDEX idx_com_entrante_fecha (fecha_creacion),
  INDEX idx_com_entrante_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resumen diario de Usage Records de Twilio (conciliación de costos).
CREATE TABLE IF NOT EXISTS com_uso_twilio (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  proveedor            VARCHAR(15)  NOT NULL DEFAULT 'twilio',
  fecha                DATE         NOT NULL,
  categoria            VARCHAR(40)  NOT NULL,        -- sms | whatsapp | total ...
  cantidad             INT          NOT NULL DEFAULT 0,
  precio_usd           DECIMAL(14,6) NOT NULL DEFAULT 0,
  moneda               VARCHAR(3)   NOT NULL DEFAULT 'USD',
  payload_json         LONGTEXT     NULL,
  fecha_sincronizacion DATETIME     NULL,
  UNIQUE KEY uq_com_uso (proveedor, fecha, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parámetros del módulo (no secretos). Los secretos viven en .env.
INSERT INTO app_configuracion (clave, valor, descripcion) VALUES
  ('com_hora_inicio',                '08:00',                  'Hora local mínima para envíos masivos'),
  ('com_hora_fin',                   '20:00',                  'Hora local máxima para envíos masivos'),
  ('com_zona_horaria',               'America/Bogota',         'Zona horaria de la interfaz'),
  ('com_limite_mps',                 '5',                      'Mensajes por segundo por ejecución'),
  ('com_lote',                       '50',                     'Tamaño de lote del worker'),
  ('com_reintentos',                 '2',                      'Reintentos ante errores transitorios'),
  ('com_consentimiento_obligatorio', '1',                      '1 = bloquear envíos sin opt-in'),
  ('com_tarifa_sms_usd',             '0',                      'Tarifa estimada SMS por segmento (USD)'),
  ('com_tarifa_whatsapp_usd',        '0',                      'Tarifa estimada WhatsApp por mensaje (USD)'),
  ('com_tarifa_email_usd',           '0',                      'Tarifa estimada email por mensaje (USD)'),
  ('com_max_destinatarios',          '50000',                  'Máximo de destinatarios por plan'),
  ('com_optout_keywords',            'STOP,BAJA,CANCELAR,SALIR,UNSUBSCRIBE,NO MAS', 'Palabras que activan opt-out'),
  ('com_optin_keywords',             'ALTA,SI,START,UNSTOP',    'Palabras que reactivan el consentimiento'),
  ('com_ayuda_texto',                'Para dejar de recibir mensajes responde BAJA. Para ayuda responde AYUDA.', 'Texto de ayuda para inbound')
ON DUPLICATE KEY UPDATE clave = clave;
