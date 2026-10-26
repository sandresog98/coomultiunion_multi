-- =====================================================================
-- multi_app_v2 — DDL CANÓNICO (Apéndice A §3.3)
-- MariaDB 11.8.3 · InnoDB · utf8mb4
-- Reglas: SIN llaves foráneas (relaciones en comentarios `-- FK:`),
--         SIN ENUM (valores posibles en comentarios `-- valores:`),
--         prefijo de módulo en el nombre de tabla (sifone_*, solicitudes_*, pagos_*, ...).
-- Fuente de datos: archivos Sifone (.xls) cargados desde la UI de Cargas.
-- Consolidado a partir de ddl_v2.sql (esquema canónico) + bloque solicitudes_*.
-- =====================================================================

SET NAMES utf8mb4;

-- #####################################################################
-- MÓDULO: Sifone — 7 tablas de datos contables importados
-- Todas las tablas se recargan desde archivos Sifone vía el importador.
-- Llave universal del sistema: cedula (une todas las tablas Sifone).
-- datacredito: columnas SEMÁNTICAS (no posicionales a..cd), solo las usadas.
-- #####################################################################

-- Maestro de asociados (reload completo desde 'sifone_libro').
-- Llave: cedula. ~90 columnas con datos personales, laborales, financieros, SARLAFT.
CREATE TABLE IF NOT EXISTS sifone_asociados (
  cedula VARCHAR(20), clased VARCHAR(10), codigo VARCHAR(20), antigu DECIMAL(10,2),
  nombre VARCHAR(255), fechai DATE, fecnac DATE, ciunac VARCHAR(100),
  ahorro DECIMAL(15,2), otroah DECIMAL(15,2), aporte DECIMAL(15,2), acumul DECIMAL(15,2),
  saldoc DECIMAL(15,2), saldoa DECIMAL(15,2), direcc VARCHAR(255), vereda VARCHAR(100),
  ciudad VARCHAR(100), depart VARCHAR(100), celula VARCHAR(20), mail VARCHAR(255),
  telefo VARCHAR(20), coment TEXT, cencos VARCHAR(50), nomcen VARCHAR(100), compan VARCHAR(100),
  cuotaf DECIMAL(15,2), fecing DATE, salari DECIMAL(15,2), salint VARCHAR(10), aprend VARCHAR(5),
  porarp VARCHAR(5), otroin DECIMAL(15,2), endeu DECIMAL(15,2), porend DECIMAL(15,2), xnomina VARCHAR(5),
  cuenta VARCHAR(50), banco VARCHAR(100), tipocu VARCHAR(50), sexo VARCHAR(5), estrat INT, escola INT,
  cabfam VARCHAR(5), emplea VARCHAR(5), ocupac INT, jorlab INT, locali VARCHAR(100), barrio VARCHAR(100),
  tipoco VARCHAR(50), fechav VARCHAR(50), quince VARCHAR(10), estadc VARCHAR(50), socio VARCHAR(5),
  directi VARCHAR(5), ciuced VARCHAR(100), dirlab VARCHAR(255), tellab VARCHAR(20), ciulab VARCHAR(100),
  ocupro VARCHAR(100), actciu VARCHAR(100), otrosdctos DECIMAL(15,2), fecexp DATE, grupo_sang VARCHAR(10),
  rh VARCHAR(5), egresos DECIMAL(15,2), pasivos DECIMAL(15,2), activos DECIMAL(15,2), tipo_vvda VARCHAR(50),
  pers_cargo DECIMAL(5,2), form_ing VARCHAR(100), conc_ing VARCHAR(100), regimen VARCHAR(50), asisasamb VARCHAR(5),
  fcesant VARCHAR(100), fpensio VARCHAR(100), eps VARCHAR(100), per_planta VARCHAR(5), per_admven VARCHAR(5),
  scorin DECIMAL(10,2), pep VARCHAR(5), decorf VARCHAR(40), cons_cdr VARCHAR(20), fecha_act DATE,
  pais VARCHAR(100), paisrf INT,
  INDEX idx_sa_cedula (cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Créditos vigentes (reload desde 'sifone_cartera_aseguradora').
-- Llave: cedula + numero. Una fila por cada crédito activo del asociado.
CREATE TABLE IF NOT EXISTS sifone_cartera_aseguradora (
  cedula VARCHAR(20), numero VARCHAR(20), priape VARCHAR(100), segape VARCHAR(100),
  nombr1 VARCHAR(100), nombre VARCHAR(255), fecnac DATE, fechae DATE, plazo DECIMAL(10,2),
  sexo CHAR(1), edad INT, ahorro DECIMAL(15,2), carter DECIMAL(15,2), tipopr VARCHAR(255),
  valorc DECIMAL(15,2), tasa DECIMAL(12,4), -- amplio: la fuente puede traer valores atípicos (cero pérdida)
  INDEX idx_sca_cedula (cedula), INDEX idx_sca_numero (numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Créditos en mora (reload desde 'sifone_cartera_mora').
-- Llave: cedula + presta. presta = sifone_cartera_aseguradora.numero.
CREATE TABLE IF NOT EXISTS sifone_cartera_mora (
  nombre VARCHAR(255), telefo VARCHAR(20), cedula VARCHAR(20), codigo VARCHAR(20),
  ahorro DECIMAL(15,2), volunt DECIMAL(15,2), aporte DECIMAL(15,2), presta VARCHAR(20),
  formap VARCHAR(10), cantid DECIMAL(10,2), sdomor DECIMAL(15,2), tipopr VARCHAR(10),
  fechae DATE, tasain DECIMAL(10,2), diav DECIMAL(10,2), fechap DATE, provis DECIMAL(15,2),
  ultpag DATE, direcc VARCHAR(255), pagadu VARCHAR(255), celula VARCHAR(20), ciures VARCHAR(100),
  cencos VARCHAR(50), nomcen VARCHAR(100), barrio VARCHAR(100), mail VARCHAR(255), intmora DECIMAL(15,2),
  INDEX idx_scm_cedula (cedula), INDEX idx_scm_presta (presta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Balance de prueba contable (reload desde 'sifone_balance_prueba').
-- Llave: cedula + cuenta. Alimenta los saldos de aportes.
-- Cuentas clave: 'aportes ordinarios', 'APORTES SOCIALES 2', 'Revalorizacion Aportes', 'PLAN FUTURO'.
CREATE TABLE IF NOT EXISTS sifone_balance_prueba (
  cuenta VARCHAR(50), nombre VARCHAR(255), cedula VARCHAR(50), nombrt VARCHAR(255),
  salant DECIMAL(18,2), debito DECIMAL(18,2), credit DECIMAL(18,2), nuesal DECIMAL(18,2),
  grupo1 BIGINT, nombr1 VARCHAR(255), grupo2 BIGINT, nombr2 VARCHAR(255),
  grupo3 BIGINT, nombr3 VARCHAR(255), grupo4 BIGINT, nombr4 VARCHAR(255),
  salantg1 DECIMAL(18,2), debitog1 DECIMAL(18,2), creditg1 DECIMAL(18,2), nuesalg1 DECIMAL(18,2),
  salantg2 DECIMAL(18,2), debitog2 DECIMAL(18,2), creditg2 DECIMAL(18,2), nuesalg2 DECIMAL(18,2),
  salantg3 DECIMAL(18,2), debitog3 DECIMAL(18,2), creditg3 DECIMAL(18,2), nuesalg3 DECIMAL(18,2),
  salantg4 DECIMAL(18,2), debitog4 DECIMAL(18,2), creditg4 DECIMAL(18,2), nuesalg4 DECIMAL(18,2),
  salantc DECIMAL(18,2), nuesalc DECIMAL(18,2), debitoc DECIMAL(18,2), creditc DECIMAL(18,2),
  detall VARCHAR(255), cuentx VARCHAR(50), period VARCHAR(7), longitud INT,
  INDEX idx_sbp_cedula (cedula), INDEX idx_sbp_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Movimientos tributarios/mayor (reload POR AÑO desde 'sifone_movimientos_tributarios').
-- Llave: cedula + cuenta + fecham. Alimenta incentivos (cta 31050501) y bolsillos (42309501, 51109591).
CREATE TABLE IF NOT EXISTS sifone_movimientos_tributarios (
  cuenta BIGINT, nombrc VARCHAR(255), period VARCHAR(7), cedula VARCHAR(50), nombre VARCHAR(255),
  compro VARCHAR(20), numero VARCHAR(50), docref VARCHAR(50), fecham DATE, detall VARCHAR(255),
  saldoi DECIMAL(18,0), debito DECIMAL(18,0), credit DECIMAL(18,0), saldof DECIMAL(18,0), base DECIMAL(18,0),
  usuari VARCHAR(100), cencos VARCHAR(50), fecha DATE, hora TIME,
  INDEX idx_smt_cedula (cedula), INDEX idx_smt_cuenta (cuenta), INDEX idx_smt_fecham (fecham)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Datacrédito: SEMÁNTICO y solo columnas usadas (reemplaza sifone_datacredito posicional + su vista).
-- Llave: cedula + numero_credito. Columnas mapeadas del archivo posicional (b..bb).
-- col g + 1 mes → fecha_vencimiento (lógica de la vista legacy).
CREATE TABLE IF NOT EXISTS sifone_datacredito (
  cedula            VARCHAR(20),   -- archivo col b
  numero_credito    VARCHAR(20),   -- col c
  nombre            VARCHAR(255),  -- col d
  fecha_emision     DATE,          -- col f
  fecha_vencimiento DATE,          -- col g + 1 mes (lógica de la vista legacy)
  codeudor          INT,           -- col h
  estado_credito    VARCHAR(50),   -- col m (5 = finalizado)
  fecha_pago        DATE,          -- col q
  desembolso_inicial DECIMAL(18,2),-- col x
  saldo_capital     DECIMAL(18,2), -- col y
  cuota             DECIMAL(18,2), -- col aa
  cuotas_iniciales  DECIMAL(18,2), -- col ac
  cuotas_pendientes DECIMAL(18,2), -- col ad
  direccion         VARCHAR(255),  -- col az
  email             VARCHAR(255),  -- col ba
  celular           VARCHAR(50),   -- col bb
  INDEX idx_sdc_cedula (cedula), INDEX idx_sdc_numero (numero_credito), INDEX idx_sdc_estado (estado_credito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Estado de cuenta consolidado (reload con guardarraíl 90% desde 'sifone_estado_cuenta').
-- Visa unificada de productos por asociado: 1 fila por crédito con capital, mora, intereses, cuotas y saldo a favor.
CREATE TABLE IF NOT EXISTS sifone_estado_cuenta (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(500), area VARCHAR(500), cencos VARCHAR(500), fechag DATE, cedula VARCHAR(50),
  fechai DATE, cuotaf DECIMAL(15,2), ahorro DECIMAL(15,2), ahorrv DECIMAL(15,2), aporte DECIMAL(15,2),
  saldof DECIMAL(15,2), numero VARCHAR(50), tipopr VARCHAR(500), fechae DATE, fechav DATE,
  valorp DECIMAL(15,2), cuotap INT, valorc DECIMAL(15,2), saldom DECIMAL(15,2), saldoc DECIMAL(15,2),
  saldoi DECIMAL(15,2), saldot DECIMAL(15,2),
  INDEX idx_sec_cedula (cedula), INDEX idx_sec_numero (numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- INFRAESTRUCTURA: usuarios, auditoría, jobs de carga
-- #####################################################################

-- Usuarios del staff (login UI). Contraseña de un solo uso + rotación.
CREATE TABLE IF NOT EXISTS usuarios (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  usuario              VARCHAR(50)  NOT NULL,
  password             VARCHAR(255) NOT NULL,
  nombre_completo      VARCHAR(100) NOT NULL,
  email                VARCHAR(100) NULL,
  rol                  VARCHAR(20)  NOT NULL,
  -- valores: admin, lider, oficina, tienda, cobranza_juridica (ver roles.json)
  estado_activo        TINYINT(1)   NOT NULL DEFAULT 1,
  password_is_one_use  TINYINT(1)   NOT NULL DEFAULT 1,
  password_changed_at  TIMESTAMP    NULL DEFAULT NULL,
  fecha_creacion       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  TIMESTAMP    NULL DEFAULT NULL,
  UNIQUE KEY uq_u_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TRIGGER IF EXISTS usuarios_bu;
CREATE TRIGGER usuarios_bu BEFORE UPDATE ON usuarios
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- password_history: historial de contraseñas del staff (rotación 3 meses, no repetir últimas 5).
-- FK: usuarios.id
CREATE TABLE IF NOT EXISTS password_history (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT          NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ph_user (user_id),
  INDEX idx_ph_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuario admin inicial (Admin123*, un solo uso → cambio forzado).
INSERT INTO usuarios (usuario, password, nombre_completo, email, rol, password_is_one_use)
SELECT 'admin', '$2y$10$Z5.wfXNf5NfmkQm36uXGY.Odbbjv1lR5p65t2o/y33yyCB2JU5oWW', 'Administrador', NULL, 'admin', 1
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE usuario = 'admin');

-- Overlay del asociado para portal (credenciales + estado). El maestro de datos es sifone_asociados.
CREATE TABLE IF NOT EXISTS asociados_control (
  cedula                 VARCHAR(20) NOT NULL,
  -- FK: sifone_asociados.cedula
  password_hash          VARCHAR(255) NULL,
  password_set_at        TIMESTAMP NULL DEFAULT NULL,
  reset_token            VARCHAR(64) NULL,
  reset_token_expires_at DATETIME NULL,
  estado_activo          TINYINT(1) NOT NULL DEFAULT 1,
  opt_in                 TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = autoriza comunicaciones (WhatsApp/SMS/email)',
  fecha_actualizacion    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (cedula),
  INDEX idx_ac_reset (reset_token),
  INDEX idx_ac_estado (estado_activo),
  INDEX idx_ac_opt_in (opt_in)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jobs de carga Sifone. Registra cada archivo procesado.
CREATE TABLE IF NOT EXISTS sifone_cargas_archivos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  tipo           VARCHAR(50) NOT NULL,
  -- valores: sifone_libro, sifone_cartera_aseguradora, sifone_cartera_mora,
  --          sifone_balance_prueba, sifone_movimientos_tributarios,
  --          sifone_datacredito, sifone_estado_cuenta
  archivo_ruta   VARCHAR(512) NOT NULL,
  estado         VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  -- valores: pendiente, procesando, completado, error
  mensaje_log    TEXT NULL,
  registros      INT NULL,
  usuario_id     INT NULL,
  -- FK: usuarios.id
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NULL DEFAULT NULL,
  INDEX idx_sca_tipo (tipo),
  INDEX idx_sca_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auditoría de acciones del sistema.
CREATE TABLE IF NOT EXISTS logs (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario        VARCHAR(20)  NULL,
  -- usuarios.id o cédula del asociado (mixto)
  timestamp         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  accion            VARCHAR(20)  NOT NULL,
  -- valores: login, crear, editar, eliminar, descargar
  modulo            VARCHAR(50)  NOT NULL,
  detalle           TEXT         NULL,
  ip_address        VARCHAR(45)  NULL,
  user_agent        TEXT         NULL,
  datos_anteriores  LONGTEXT     NULL,
  datos_nuevos      LONGTEXT     NULL,
  nivel             VARCHAR(12)  NOT NULL DEFAULT 'info',
  -- valores: info, warning, error, critical
  INDEX idx_log_accion (accion),
  INDEX idx_log_modulo (modulo),
  INDEX idx_log_usuario (id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Idempotencia (prevención de doble envío): respuestas devueltas por
-- X-Idempotency-Key para no re-insertar/re-aplicar en mutadores de doble clic/reintento.
-- respuesta NULL = operación "en proceso" (carrera concurrente). Sin FK/ENUM (§3).
-- FK: (implícito por valor) actor usuario = Auth::actor() [interno] o PortalAuth cedula [asociado]
CREATE TABLE IF NOT EXISTS idempotencia (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(64)  NOT NULL,          -- X-Idempotency-Key (header/body)
  modulo      VARCHAR(50)  NOT NULL,          -- módulo del endpoint (p. ej. boletas, tienda.ventas)
  usuario     VARCHAR(40)  NULL,              -- actor uid: 'interno:<id>' o 'asociado:<cedula>'
  respuesta   LONGTEXT     NULL,              -- JSON {ok,data} persistido; NULL = en proceso
  http_code   INT          NULL,              -- código HTTP original para el replay
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_idem_clave (clave),
  INDEX idx_idem_fecha (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- #####################################################################
-- SISTEMA ÚNICO DE SOLICITUDES (B7) — núcleo + familia CX (Fase 1)
-- Origen: docs/design/12 (bloque solicitudes). Integrado al DDL canónico en la consolidación.
-- #####################################################################

CREATE TABLE IF NOT EXISTS solicitudes_tipos (
  codigo               VARCHAR(40)  NOT NULL,           -- PK. ej: pqrs, actualizar_info, redencion, nuevo_producto
  nombre               VARCHAR(120) NOT NULL,
  prefijo_radicado     VARCHAR(10)  NOT NULL,           -- ej: PQRS, ACT, RED, NPR
  requiere_asignacion  TINYINT(1)   NOT NULL DEFAULT 1, -- 1 = usa responsable
  permite_reapertura   TINYINT(1)   NOT NULL DEFAULT 0,
  permite_anulacion    TINYINT(1)   NOT NULL DEFAULT 0,
  detalle_tabla        VARCHAR(60)  NULL,               -- ej: solicitudes_det_pqrs
  permite_comentarios  TINYINT(1)   NOT NULL DEFAULT 1,
  activo               TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_creacion       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_estados (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  tipo_codigo   VARCHAR(40)  NOT NULL,   -- FK: solicitudes_tipos.codigo
  codigo        VARCHAR(40)  NOT NULL,   -- ej: pendiente, asignado, aprobada, rechazada
  nombre        VARCHAR(120) NOT NULL,
  fase          VARCHAR(30)  NOT NULL,   -- valores: CREADA, EN_GESTION, PRE_RESUELTA, RESUELTA_POSITIVA, RESUELTA_NEGATIVA, ANULADA
  es_final      TINYINT(1)   NOT NULL DEFAULT 0,
  orden         INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uq_sol_estado (tipo_codigo, codigo),
  INDEX idx_sol_estado_tipo (tipo_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_transiciones (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  tipo_codigo         VARCHAR(40) NOT NULL,   -- FK: solicitudes_tipos.codigo
  estado_origen       VARCHAR(40) NOT NULL,   -- FK: solicitudes_estados.codigo
  estado_destino      VARCHAR(40) NOT NULL,   -- FK: solicitudes_estados.codigo
  rol_relacion        VARCHAR(20) NULL,       -- valores: solicitante, responsable, creador, NULL(=cualquiera con permiso)
  requiere_comentario TINYINT(1)  NOT NULL DEFAULT 0,
  gate_regla          VARCHAR(60) NULL,       -- id de regla a validar (handler en código)
  efecto              VARCHAR(60) NULL,       -- id de efecto post-transición (handler en código)
  UNIQUE KEY uq_sol_trans (tipo_codigo, estado_origen, estado_destino),
  INDEX idx_sol_trans_tipo (tipo_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Cabecera genérica
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS solicitudes (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  numero_radicado        VARCHAR(30)  NOT NULL,   -- visible; prefijo por tipo + consecutivo
  tipo_codigo            VARCHAR(40)  NOT NULL,   -- FK: solicitudes_tipos.codigo
  fase                   VARCHAR(30)  NOT NULL DEFAULT 'CREADA', -- valores: ver solicitudes_estados.fase
  estado_codigo          VARCHAR(40)  NOT NULL,   -- FK: solicitudes_estados.codigo (por tipo)
  cedula_solicitante     VARCHAR(20)  NOT NULL,   -- FK: sifone_asociados.cedula (asociado) | usuarios.id (interno)
  solicitante_tipo       VARCHAR(15)  NOT NULL DEFAULT 'asociado', -- valores: asociado, interno
  responsable_id         INT          NULL,       -- FK: usuarios.id
  asignado_por           INT          NULL,       -- FK: usuarios.id
  fecha_asignacion       DATETIME     NULL,
  resultado              VARCHAR(30)  NULL,       -- valores por tipo: aprobada, rechazada, desembolsada, entregada...
  respondido_por         INT          NULL,       -- FK: usuarios.id
  comentario_respuesta   TEXT         NULL,
  ruta_archivo_respuesta VARCHAR(500) NULL,
  fecha_respuesta        DATETIME     NULL,
  origen                 VARCHAR(10)  NOT NULL DEFAULT 'portal', -- valores: ui, portal
  creado_por             INT          NULL,       -- FK: usuarios.id (NULL si portal)
  fecha_creacion         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion    DATETIME     NULL DEFAULT NULL,  -- lo fija trigger BU
  -- Trazabilidad de migración (cero pérdida, ver migracion_datos.md):
  origen_tabla           VARCHAR(60)  NULL,       -- ej: cx_solicitudes
  origen_id              VARCHAR(40)  NULL,       -- id en la tabla legacy
  datos_legacy           LONGTEXT     NULL,       -- JSON histórico de respaldo
  UNIQUE KEY uq_sol_radicado (numero_radicado),
  INDEX idx_sol_tipo (tipo_codigo),
  INDEX idx_sol_estado (estado_codigo),
  INDEX idx_sol_fase (fase),
  INDEX idx_sol_cedula (cedula_solicitante),
  INDEX idx_sol_responsable (responsable_id),
  INDEX idx_sol_origen (origen_tabla, origen_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TRIGGER IF EXISTS solicitudes_bu;
CREATE TRIGGER solicitudes_bu BEFORE UPDATE ON solicitudes
FOR EACH ROW SET NEW.fecha_actualizacion = NOW();

-- ---------------------------------------------------------------------
-- Timeline, comentarios, adjuntos, calificaciones
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS solicitudes_eventos (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id            INT          NOT NULL,   -- FK: solicitudes.id
  tipo_evento             VARCHAR(30)  NOT NULL,   -- valores: creacion, comentario, cambio_estado, asignacion, reasignacion, adjunto, respuesta
  estado_anterior         VARCHAR(40)  NULL,
  estado_nuevo            VARCHAR(40)  NULL,
  responsable_anterior_id INT          NULL,       -- FK: usuarios.id
  responsable_nuevo_id    INT          NULL,       -- FK: usuarios.id
  actor_tipo              VARCHAR(15)  NOT NULL,   -- valores: asociado, interno, sistema
  actor_id                VARCHAR(20)  NULL,       -- cedula o usuarios.id
  mensaje                 TEXT         NULL,
  fecha                   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sol_ev_solicitud (solicitud_id),
  INDEX idx_sol_ev_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_comentarios (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id   INT          NOT NULL,   -- FK: solicitudes.id
  tipo           VARCHAR(15)  NOT NULL,   -- valores: solicitante, interno
  autor_id       VARCHAR(20)  NULL,       -- cedula (solicitante) | usuarios.id (interno)
  mensaje        TEXT         NOT NULL,
  ruta_archivo   VARCHAR(500) NULL,       -- adjunto opcional (1)
  fecha_creacion TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sol_com_solicitud (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_adjuntos (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id    INT          NOT NULL,  -- FK: solicitudes.id
  evento_id       INT          NULL,      -- FK: solicitudes_eventos.id ; NULL = adjunto de creación
  contexto        VARCHAR(20)  NOT NULL,  -- valores: solicitud, respuesta, comentario, documento
  tipo            VARCHAR(15)  NOT NULL,  -- valores: imagen, pdf, enlace
  tipo_documento  VARCHAR(100) NULL,      -- para contexto=documento (crédito/fondo)
  nombre_original VARCHAR(500) NULL,
  nombre_archivo  VARCHAR(500) NULL,      -- nombre único en disco (regla 2.3)
  ruta_archivo    VARCHAR(500) NOT NULL,
  tamano_archivo  INT          NULL,
  tipo_mime       VARCHAR(100) NULL,
  subido_por      VARCHAR(20)  NULL,      -- cedula o usuarios.id
  fecha_creacion  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sol_adj_solicitud (solicitud_id),
  INDEX idx_sol_adj_evento (evento_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_calificaciones (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id       INT          NOT NULL,   -- FK: solicitudes.id
  puntuacion         TINYINT      NULL,       -- 1..5
  comentarios        TEXT         NULL,
  token_acceso       VARCHAR(64)  NOT NULL,
  fecha_calificacion DATETIME     NULL,
  fecha_creacion     DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sol_calif_token (token_acceso),
  INDEX idx_sol_calif_solicitud (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Detalle tipado — familia CX (Fase 1)
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS solicitudes_det_pqrs (
  solicitud_id INT NOT NULL,             -- FK: solicitudes.id
  tipo_pqrs    VARCHAR(20)  NOT NULL,    -- valores: Peticiones, Quejas, Reclamos, Sugerencias
  resumen      VARCHAR(200) NOT NULL,
  descripcion  TEXT         NOT NULL,
  PRIMARY KEY (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_det_actualizacion (
  solicitud_id  INT NOT NULL,            -- FK: solicitudes.id
  nombre        VARCHAR(255) NULL,
  celular       VARCHAR(20)  NULL,
  email         VARCHAR(255) NULL,
  direccion     VARCHAR(255) NULL,
  ciudad        VARCHAR(100) NULL,
  observaciones TEXT         NULL,
  PRIMARY KEY (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_det_redencion (
  solicitud_id    INT NOT NULL,          -- FK: solicitudes.id
  val_bolsillos   DECIMAL(15,2) NOT NULL DEFAULT 0,
  val_incentivos  DECIMAL(15,2) NOT NULL DEFAULT 0,
  val_comisiones  DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_redimir   DECIMAL(15,2) NOT NULL DEFAULT 0,
  medio_redencion VARCHAR(40)  NULL,     -- valores: retiro_efectivo, cruce_cartera, abono_aportes, convenio_recreacion, convenio_tecnologia
  comentarios     TEXT NULL,
  PRIMARY KEY (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_det_nuevo_prod (
  solicitud_id INT NOT NULL,             -- FK: solicitudes.id
  producto_id  INT NOT NULL,             -- FK: productos.id
  dia_pago     TINYINT UNSIGNED NULL,    -- 1..31
  valor        DECIMAL(12,2) NULL,
  PRIMARY KEY (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Seed de configuración — los 4 tipos CX
-- ---------------------------------------------------------------------

INSERT IGNORE INTO solicitudes_tipos
  (codigo, nombre, prefijo_radicado, requiere_asignacion, permite_reapertura, permite_anulacion, detalle_tabla, permite_comentarios)
VALUES
  ('pqrs',            'PQRS',                        'PQRS', 1, 0, 0, 'solicitudes_det_pqrs',          1),
  ('actualizar_info', 'Actualización de información','ACT',  1, 0, 0, 'solicitudes_det_actualizacion', 0),
  ('redencion',       'Redención de saldos',         'RED',  1, 0, 0, 'solicitudes_det_redencion',     1),
  ('nuevo_producto',  'Nuevo producto',              'NPR',  1, 0, 0, 'solicitudes_det_nuevo_prod',    0)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Estados: ciclo común de la familia cx_solicitudes (pendiente/asignado/aprobada/rechazada)
INSERT IGNORE INTO solicitudes_estados (tipo_codigo, codigo, nombre, fase, es_final, orden) VALUES
  ('pqrs','pendiente','Pendiente','CREADA',0,1),
  ('pqrs','asignado','Asignado','EN_GESTION',0,2),
  ('pqrs','aprobada','Aprobada','RESUELTA_POSITIVA',1,3),
  ('pqrs','rechazada','Rechazada','RESUELTA_NEGATIVA',1,4),
  ('actualizar_info','pendiente','Pendiente','CREADA',0,1),
  ('actualizar_info','asignado','Asignado','EN_GESTION',0,2),
  ('actualizar_info','aprobada','Aprobada','RESUELTA_POSITIVA',1,3),
  ('actualizar_info','rechazada','Rechazada','RESUELTA_NEGATIVA',1,4),
  ('redencion','pendiente','Pendiente','CREADA',0,1),
  ('redencion','asignado','Asignado','EN_GESTION',0,2),
  ('redencion','aprobada','Aprobada','RESUELTA_POSITIVA',1,3),
  ('redencion','rechazada','Rechazada','RESUELTA_NEGATIVA',1,4),
  ('nuevo_producto','pendiente','Pendiente','CREADA',0,1),
  ('nuevo_producto','asignado','Asignado','EN_GESTION',0,2),
  ('nuevo_producto','aprobada','Aprobada','RESUELTA_POSITIVA',1,3),
  ('nuevo_producto','rechazada','Rechazada','RESUELTA_NEGATIVA',1,4);

-- Transiciones: asignar (cualquiera con permiso) → responder (solo el responsable)
INSERT IGNORE INTO solicitudes_transiciones
  (tipo_codigo, estado_origen, estado_destino, rol_relacion, requiere_comentario, gate_regla, efecto)
VALUES
  ('pqrs','pendiente','asignado',NULL,0,NULL,NULL),
  ('pqrs','asignado','aprobada','responsable',1,NULL,NULL),
  ('pqrs','asignado','rechazada','responsable',1,NULL,NULL),
  ('actualizar_info','pendiente','asignado',NULL,0,NULL,NULL),
  ('actualizar_info','asignado','aprobada','responsable',1,NULL,NULL),
  ('actualizar_info','asignado','rechazada','responsable',1,NULL,NULL),
  ('redencion','pendiente','asignado',NULL,0,NULL,NULL),
  ('redencion','asignado','aprobada','responsable',1,NULL,NULL),
  ('redencion','asignado','rechazada','responsable',1,NULL,NULL),
  ('nuevo_producto','pendiente','asignado',NULL,0,NULL,NULL),
  ('nuevo_producto','asignado','aprobada','responsable',1,NULL,'crear_asignacion_producto'),
  ('nuevo_producto','asignado','rechazada','responsable',1,NULL,NULL);

-- #####################################################################
-- VISTAS — Columnas Sifone renombradas a nombres comprensibles
-- #####################################################################

DROP VIEW IF EXISTS vw_asociados;
CREATE VIEW vw_asociados AS
SELECT cedula,
       codigo   AS codigo_interno,
       nombre,
       fechai   AS fecha_ingreso,
       fecnac   AS fecha_nacimiento,
       fecexp   AS fecha_expedicion,
       sexo,
       estadc   AS estado_civil,
       escola   AS escolaridad,
       estrat   AS estrato,
       celula   AS celular,
       telefo   AS telefono,
       mail     AS email,
       direcc   AS direccion,
       barrio,
       vereda,
       ciudad,
       depart   AS departamento,
       locali   AS localidad,
       pais,
       compan   AS empresa,
       ocupro   AS ocupacion,
       tipoco   AS tipo_contrato,
       cencos   AS centro_costo_codigo,
       nomcen   AS centro_costo_nombre,
       cabfam   AS cabeza_familia,
       tipo_vvda AS tipo_vivienda,
       fecha_act AS fecha_actualizacion,
       coment   AS comentarios
FROM sifone_asociados;

-- Vista unificada de créditos. 1 fila por crédito del asociado.
-- Base: sifone_estado_cuenta (fuente primaria de productos).
-- Complementa con tasa y plazo de cartera_aseguradora,
-- cuotas y codeudor de datacredito, y días mora de cartera_mora.
-- Incluye campos calculados: cuotas_pagadas, cuotas_vencidas, monto_cobranza, pago_minimo.
-- Cuotas: totales = plazo real (cartera_aseguradora), pendientes = ec.cuotap,
--         pagadas = totales - pendientes. (Sifone no trae el total de cuotas aparte.)
-- fecha_vencimiento: se toma de sifone_estado_cuenta (fuente nativa), no de datacredito.
-- Join datacredito: por COUNT/CAST numérico porque Sifone trae cedula y numero_credito
--         sin ceros a la izquierda (el resto del esquema los guarda a 10).
-- saldo_total = saldo Sifone + monto_cobranza (siempre incluye la cobranza).
-- seguros/interés: prorrateo diario del mes en curso sobre saldo_capital, según la
--         línea (credito_lineas, resuelta por FIND_IN_SET contra su sifone_alias).
--         Tasas en % mensual → /100.
-- Catálogo de líneas de crédito (tasas/seguros/límites) configurable.
-- Se define ANTES de vw_creditos porque la vista hace LEFT JOIN con esta tabla.
CREATE TABLE IF NOT EXISTS credito_lineas (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  nombre_credito     VARCHAR(100) NOT NULL,
  limite_meses       INT NULL,
  tasa               DECIMAL(8,4) NOT NULL DEFAULT 0,
  seguro_vida        DECIMAL(5,4) NOT NULL DEFAULT 0,
  seguro_deudores    DECIMAL(5,4) NOT NULL DEFAULT 0,
  estado_activo      TINYINT(1) NOT NULL DEFAULT 1,
  mostrar_en_fabrica TINYINT(1) NOT NULL DEFAULT 1,
  -- Nombres de línea tal como los trae Sifone (sifone_*.tipopr), separados por coma.
  -- Permite resolver la línea de un crédito importado (vw_creditos). Editable en la UI.
  sifone_alias       TEXT NULL,
  creado_por         INT NULL,
  -- FK: usuarios.id
  fecha_creacion     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP VIEW IF EXISTS vw_creditos;
CREATE VIEW vw_creditos AS
SELECT base.*,
       GREATEST(0, base.cuotas_totales - base.cuotas_pendientes) AS cuotas_pagadas,
       ROUND(base.saldo_mora / NULLIF(base.valor_cuota, 0), 1) AS cuotas_vencidas,
       base.saldo_sifone + base.monto_cobranza AS saldo_total
FROM (
  SELECT ec.cedula,
         ec.numero  AS numero_credito,
         ec.tipopr  AS tipo_prestamo,
         ec.fechae  AS fecha_emision,
         ec.fechav  AS fecha_vencimiento,
         COALESCE(cm.fechap, dc.fecha_pago) AS fecha_pago,
         dc.desembolso_inicial,
         COALESCE(dc.cuota, ca.valorc, ec.valorc) AS valor_cuota,
         ec.saldoc  AS saldo_capital,
         ec.saldom  AS saldo_mora,
         ec.saldoi  AS saldo_intereses,
         ec.saldot  AS saldo_sifone,
         CASE WHEN ca.plazo > 0 THEN CAST(ca.plazo AS SIGNED)
              ELSE CAST(ec.cuotap AS SIGNED) END AS cuotas_totales,
         CAST(ec.cuotap AS SIGNED) AS cuotas_pendientes,
         COALESCE(cm.diav, 0)   AS dias_mora,
         ca.tasa    AS tasa_seguro,
         cl.tasa    AS tasa_interes,
         ca.plazo,
         CASE WHEN COALESCE(cm.diav, 0) > 60 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.08
              WHEN COALESCE(cm.diav, 0) > 50 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.06
              WHEN COALESCE(cm.diav, 0) > 40 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.05
              WHEN COALESCE(cm.diav, 0) > 30 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.04
              WHEN COALESCE(cm.diav, 0) > 20 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.03
              WHEN COALESCE(cm.diav, 0) > 11 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.02
              ELSE 0 END AS monto_cobranza,
         IF(ec.saldom > 0, ec.saldom, COALESCE(dc.cuota, ca.valorc, ec.valorc))
           + CASE WHEN COALESCE(cm.diav, 0) > 60 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.08
                  WHEN COALESCE(cm.diav, 0) > 50 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.06
                  WHEN COALESCE(cm.diav, 0) > 40 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.05
                  WHEN COALESCE(cm.diav, 0) > 30 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.04
                  WHEN COALESCE(cm.diav, 0) > 20 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.03
                  WHEN COALESCE(cm.diav, 0) > 11 THEN COALESCE(dc.cuota, ca.valorc, ec.valorc) * 0.02
                  ELSE 0 END AS pago_minimo,
         CASE WHEN cl.id IS NULL THEN NULL
              ELSE FLOOR(ec.saldoc * (cl.seguro_vida / 100) / 30
                         * GREATEST(0, DATEDIFF(CURDATE(), COALESCE(cm.fechap, dc.fecha_pago, ec.fechae) - INTERVAL 1 MONTH))) END AS seguro_vida,
         CASE WHEN cl.id IS NULL THEN NULL
              ELSE FLOOR(ec.saldoc * (cl.seguro_deudores / 100) / 30
                         * GREATEST(0, DATEDIFF(CURDATE(), COALESCE(cm.fechap, dc.fecha_pago, ec.fechae) - INTERVAL 1 MONTH))) END AS seguro_deudores,
         CASE WHEN cl.id IS NULL THEN NULL
              ELSE FLOOR(ec.saldoc * (cl.tasa / 100) / 30
                         * GREATEST(0, DATEDIFF(CURDATE(), COALESCE(cm.fechap, dc.fecha_pago, ec.fechae) - INTERVAL 1 MONTH))) END AS interes,
         dc_co.nombre    AS codeudor_nombre,
         dc_co.celular   AS codeudor_celular,
         dc_co.email     AS codeudor_email,
         dc_co.direccion AS codeudor_direccion
  FROM sifone_estado_cuenta ec
  LEFT JOIN sifone_cartera_aseguradora ca
    ON ca.numero = ec.numero AND ca.cedula = ec.cedula
  LEFT JOIN sifone_datacredito dc
    ON CAST(dc.cedula AS UNSIGNED) = CAST(ec.cedula AS UNSIGNED)
   AND CAST(dc.numero_credito AS UNSIGNED) = CAST(ec.numero AS UNSIGNED)
   AND dc.codeudor = 0
  LEFT JOIN sifone_datacredito dc_co
    ON CAST(dc_co.numero_credito AS UNSIGNED) = CAST(ec.numero AS UNSIGNED)
   AND dc_co.codeudor = 1
  LEFT JOIN sifone_cartera_mora cm
    ON cm.presta = ec.numero AND cm.cedula = ec.cedula
  LEFT JOIN credito_lineas cl
    ON FIND_IN_SET(ec.tipopr, cl.sifone_alias) > 0
) base;

-- #####################################################################
-- INFRAESTRUCTURA: movimientos no-Sifone
-- #####################################################################

-- Movimientos genéricos (comisiones, redenciones, ajustes...).
-- Reemplaza control_comisiones y redenciones_*.
CREATE TABLE IF NOT EXISTS movimientos_multi (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  cedula         VARCHAR(20)  NOT NULL,
  -- FK: sifone_asociados.cedula
  tipo           VARCHAR(30)  NOT NULL,
  -- FK: movimientos_tipos.tipo (catálogo configurable)
  clasificacion  VARCHAR(10)  NOT NULL DEFAULT 'ingreso',
  -- valores: ingreso, egreso
  valor          DECIMAL(15,2) NOT NULL,
  fecha          DATE         NOT NULL,
  detalle        TEXT         NULL,
  -- Flexible: JSON o texto libre según el tipo
  creado_por     INT          NULL,
  -- FK: usuarios.id
  fecha_creacion TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mm_cedula (cedula),
  INDEX idx_mm_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catálogo de tipos de movimiento (configurable desde Configuración > Tipos de movimiento).
CREATE TABLE IF NOT EXISTS movimientos_tipos (
  tipo              VARCHAR(30) NOT NULL PRIMARY KEY,
  nombre            VARCHAR(80) NOT NULL,
  clasificacion     VARCHAR(10) NOT NULL,
  -- valores: ingreso, egreso
  requiere_referido TINYINT(1)  NOT NULL DEFAULT 0,
  -- 1 = exige un asociado referido (ej. comisión)
  valida_disponible TINYINT(1)  NOT NULL DEFAULT 0,
  -- 1 = limita el egreso al disponible (ej. redención comisiones)
  activo            TINYINT(1)  NOT NULL DEFAULT 1,
  creado_por        INT         NULL,
  -- FK: usuarios.id
  fecha_creacion    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed inicial: solo los 2 tipos base; el resto los crean los usuarios (Configuración > Tipos de movimiento).
INSERT IGNORE INTO movimientos_tipos (tipo, nombre, clasificacion, requiere_referido, valida_disponible, activo) VALUES
  ('comision',             'Comisión',              'ingreso', 1, 0, 1),
  ('redencion_comisiones', 'Redención comisiones',  'egreso',  0, 1, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- #####################################################################
-- MÓDULO: Cobranza (B6) — unificada (mora de crédito + mora de aportes)
-- #####################################################################

-- Catálogo configurable de tipos/estados de comunicación de cobranza.
CREATE TABLE IF NOT EXISTS cobranza_tipos_comunicacion (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  tipo           VARCHAR(20) NOT NULL,
  -- valores: llamada, whatsapp, email, sms
  estado         VARCHAR(30) NOT NULL,
  -- valores: sin_respuesta, informa_pago, comprometido, pendiente
  label          VARCHAR(80) NOT NULL,
  tipo_origen    VARCHAR(10) NOT NULL DEFAULT 'ambos',
  -- valores: credito, aportes, ambos
  activo         TINYINT(1)  NOT NULL DEFAULT 1,
  creado_por     INT         NULL,
  -- FK: usuarios.id
  fecha_creacion TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ctc_tipo_estado (tipo, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed inicial (idempotente). El resto lo crean los usuarios (Configuración > Tipos de comunicación).
INSERT IGNORE INTO cobranza_tipos_comunicacion (tipo, estado, label, tipo_origen) VALUES
  ('llamada','sin_respuesta','Llamada · Sin respuesta','ambos'),
  ('llamada','informa_pago','Llamada · Informa de pago','ambos'),
  ('llamada','comprometido','Llamada · Comprometido a pagar','ambos'),
  ('whatsapp','sin_respuesta','WhatsApp · Sin respuesta','ambos'),
  ('whatsapp','informa_pago','WhatsApp · Informa de pago','ambos'),
  ('whatsapp','comprometido','WhatsApp · Comprometido a pagar','ambos'),
  ('email','sin_respuesta','Email · Sin respuesta','ambos'),
  ('email','informa_pago','Email · Informa de pago','ambos'),
  ('sms','sin_respuesta','Mensaje de texto · Sin respuesta','ambos')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Comunicaciones de cobranza (con compromiso de pago opcional).
CREATE TABLE IF NOT EXISTS cobranza_comunicaciones (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  asociado_cedula  VARCHAR(20) NOT NULL,
  -- FK: sifone_asociados.cedula
  tipo_origen      VARCHAR(10) NOT NULL DEFAULT 'credito',
  -- valores: credito, aportes
  tipo_comunicacion VARCHAR(20) NOT NULL,
  -- FK: cobranza_tipos_comunicacion.tipo
  estado           VARCHAR(30) NOT NULL,
  -- FK: cobranza_tipos_comunicacion.estado
  comentario       TEXT NULL,
  fecha            DATETIME NOT NULL,
  fecha_compromiso DATE NULL,
  monto_compromiso DECIMAL(12,2) NULL,
  creado_por       INT NULL,
  -- FK: usuarios.id
  fecha_creacion   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cc_cedula (asociado_cedula),
  INDEX idx_cc_origen (tipo_origen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indagaciones de cobranza (con adjuntos).
CREATE TABLE IF NOT EXISTS cobranza_indagaciones (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  asociado_cedula VARCHAR(20) NOT NULL,
  -- FK: sifone_asociados.cedula
  tipo_origen     VARCHAR(10) NOT NULL DEFAULT 'credito',
  -- valores: credito, aportes
  tipo_indagacion VARCHAR(80) NOT NULL,
  fecha           DATE NOT NULL,
  creado_por      INT NULL,
  -- FK: usuarios.id
  fecha_creacion  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ci_cedula (asociado_cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cobranza_indagaciones_adjuntos (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  indagacion_id  INT NOT NULL,
  -- FK: cobranza_indagaciones.id
  nombre_archivo VARCHAR(255) NULL,
  ruta_archivo   VARCHAR(500) NOT NULL,
  tipo_mime      VARCHAR(100) NULL,
  tamaño_archivo INT NULL,
  fecha_subida   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cia_ind (indagacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Remisión a cobranza jurídica.
CREATE TABLE IF NOT EXISTS cobranza_remisiones_juridica (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  asociado_cedula VARCHAR(20) NOT NULL,
  -- FK: sifone_asociados.cedula
  remitido       TINYINT(1) NOT NULL DEFAULT 0,
  -- valores: 0, 1
  fecha_remision DATETIME NULL,
  creado_por     INT NULL,
  -- FK: usuarios.id
  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_crj_cedula (asociado_cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Snapshot de mora capturado al comunicar.
CREATE TABLE IF NOT EXISTS cobranza_detalle_mora (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  comunicacion_id INT NULL,
  -- FK: cobranza_comunicaciones.id (NULL = snapshot manual sin comunicación)
  asociado_cedula VARCHAR(20) NOT NULL,
  aportes_monto  DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_creditos INT NOT NULL DEFAULT 0,
  creado_por     INT NULL,
  -- FK: usuarios.id
  fecha          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cdm_comm (comunicacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cobranza_detalle_mora_credito (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  detalle_id     INT NOT NULL,
  -- FK: cobranza_detalle_mora.id
  numero_credito VARCHAR(50) NULL,
  deuda_capital  DECIMAL(12,2) DEFAULT 0,
  deuda_mora     DECIMAL(12,2) DEFAULT 0,
  dias_mora      INT DEFAULT 0,
  fecha_pago     DATE NULL,
  INDEX idx_cdmc_det (detalle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- MÓDULO: Créditos / Fábrica de Créditos (B7a) — flujo + motor dinámico
-- #####################################################################

-- Catálogo de líneas de crédito (tasas/seguros/límites) configurable.
-- NOTA: se define ANTES de `vw_creditos` (la vista hace LEFT JOIN con esta tabla); ver más arriba.

-- Cabecera de solicitud de crédito (Fábrica). Columna tipadas (núcleo).
CREATE TABLE IF NOT EXISTS credito_solicitudes (
  id                        INT AUTO_INCREMENT PRIMARY KEY,
  numero_solicitud          VARCHAR(30) NOT NULL,
  -- prefijo FC (UI Fábrica) / CD (portal). UNIQUE
  origen                    VARCHAR(10) NOT NULL DEFAULT 'ui',
  -- valores: ui, portal
  cedula_asociado           VARCHAR(20) NOT NULL,
  -- FK: sifone_asociados.cedula
  tipo_solicitante          VARCHAR(50) NULL,
  -- valores: estudiante, empleado_descuento_nomina, empleado_sin_descuento, independiente, pensionado_descuento_libranza, pensionado_sin_descuento_libranza
  nombre_solicitante        VARCHAR(255) NULL,
  numero_identificacion     VARCHAR(30) NULL,
  numero_telefono           VARCHAR(20) NULL,
  correo_electronico        VARCHAR(255) NULL,
  monto_deseado             DECIMAL(15,2) NULL,
  numero_cuotas_deseadas    INT NULL,
  desea_codeudor            TINYINT(1) NULL,
  tipo_codeudor             VARCHAR(20) NULL,
  -- valores: no_necesita, codeudor_dependiente, codeudor_pensionado
  tasa_interes_id           INT NULL,
  -- FK: credito_lineas.id (línea solicitada)
  monto_aprobado            DECIMAL(15,2) NULL,
  cuotas_aprobadas          INT NULL,
  codeudor_aprobado         VARCHAR(30) NULL,
  linea_credito_aprobada_id INT NULL,
  -- FK: credito_lineas.id
  estado                    VARCHAR(20) NOT NULL DEFAULT 'solicitado',
  -- valores: solicitado, revisado, con_estudio, desembolsado, rechazado, cancelado
  etapa_actual              VARCHAR(30) NOT NULL DEFAULT 'creacion',
  -- valores: creacion, revision, estudio, consideracion, estudio_aprobado, estudio_rechazado, radicado_tesoreria, final
  comentarios_rechazo       TEXT NULL,
  -- Costos / PAC
  base_aportes              DECIMAL(15,2) NULL,
  fianza                    DECIMAL(15,2) NULL,
  prestamos_recoger         DECIMAL(15,2) NULL,
  dto_papeleria             DECIMAL(15,2) NULL,
  desembolsado_pac          DECIMAL(15,2) NULL,
  -- Desembolso
  numero_credito_sifone     VARCHAR(30) NULL,
  valor_real_desembolso     DECIMAL(15,2) NULL,
  fecha_desembolso          DATE NULL,
  -- Aceptación (registro)
  aceptado_por_asociado     TINYINT(1) NULL,
  fecha_aceptacion          DATETIME NULL,
  ip_aceptacion             VARCHAR(45) NULL,
  -- Trazabilidad de migración
  origen_tabla              VARCHAR(60) NULL,
  origen_id                 VARCHAR(40) NULL,
  datos_legacy              LONGTEXT NULL,
  creado_por                INT NULL,
  -- FK: usuarios.id
  fecha_creacion            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion       TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_cs_numero (numero_solicitud),
  INDEX idx_cs_cedula (cedula_asociado),
  INDEX idx_cs_estado (estado),
  INDEX idx_cs_etapa (etapa_actual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Secciones dinámicas de una solicitud, ligadas a una etapa/vista del flujo.
CREATE TABLE IF NOT EXISTS credito_config_seccion (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  codigo       VARCHAR(40) NOT NULL,
  -- clave única de la sección/etapa
  nombre       VARCHAR(120) NOT NULL,
  ocasion      VARCHAR(30) NOT NULL,
  -- a qué aplica: una etapa del flujo, 'documentos' o 'aceptacion'
  visible_en   VARCHAR(60) NOT NULL DEFAULT '["staff","cx"]',
  -- JSON: canales donde se muestra (staff, cx)
  orden        INT NOT NULL DEFAULT 100,
  estado_activo TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cfs_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Campos dinámicos por sección (los que se piden y se guardan en EAV).
CREATE TABLE IF NOT EXISTS credito_config_campo (
  id                     INT AUTO_INCREMENT PRIMARY KEY,
  seccion_id             INT NOT NULL,
  -- FK: credito_config_seccion.id
  campo                  VARCHAR(60) NOT NULL,
  nombre                 VARCHAR(160) NOT NULL,
  tipo                   VARCHAR(20) NOT NULL DEFAULT 'texto',
  -- valores: texto, textoarea, numero, select, fecha, checkbox
  opciones               TEXT NULL,
  -- JSON para tipo=select: [{v, l}, ...]
  obligatorio            TINYINT(1) NOT NULL DEFAULT 0,
  para_tipo_solicitante  TEXT NULL,
  -- JSON: null/[] = todos; o lista de tipos
  visible_en             VARCHAR(60) NOT NULL DEFAULT '["staff","cx"]',
  orden                  INT NOT NULL DEFAULT 100,
  estado_activo          TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cfc_seccion_campo (seccion_id, campo),
  INDEX idx_cfc_seccion (seccion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documentos requeridos por sección (cuáles se piden y se guardan).
CREATE TABLE IF NOT EXISTS credito_config_documento (
  id                      INT AUTO_INCREMENT PRIMARY KEY,
  seccion_id              INT NOT NULL,
  -- FK: credito_config_seccion.id
  tipo_documento          VARCHAR(100) NOT NULL,
  nombre_mostrar          VARCHAR(255) NOT NULL,
  descripcion             TEXT NULL,
  es_obligatorio          TINYINT(1) NOT NULL DEFAULT 1,
  es_opcional             TINYINT(1) NOT NULL DEFAULT 0,
  es_trato_especial       TINYINT(1) NOT NULL DEFAULT 0,
  para_tipo_solicitante   TEXT NULL,
  visible_en              VARCHAR(60) NOT NULL DEFAULT '["staff","cx"]',
  orden                   INT NOT NULL DEFAULT 100,
  estado_activo           TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cfd_seccion_tipo (seccion_id, tipo_documento),
  INDEX idx_cfd_seccion (seccion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "Aceptación y Autorizaciones" configurable: textos y checkpoints.
CREATE TABLE IF NOT EXISTS credito_config_aceptacion (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  clave          VARCHAR(60) NOT NULL,
  tipo           VARCHAR(20) NOT NULL DEFAULT 'aceptacion',
  -- valores: texto, aceptacion
  titulo         VARCHAR(255) NULL,
  texto          TEXT NULL,
  obligatorio    TINYINT(1) NOT NULL DEFAULT 0,
  visible_en     VARCHAR(60) NOT NULL DEFAULT '["staff","cx"]',
  orden          INT NOT NULL DEFAULT 100,
  estado_activo  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cfa_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores dinámicos por solicitud (EAV) para config_campo y aceptación.
CREATE TABLE IF NOT EXISTS credito_campos_valores (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id      INT NOT NULL,
  -- FK: credito_solicitudes.id
  config_campo_id   INT NULL,
  -- FK: credito_config_campo.id (NULL para aceptación)
  config_aceptacion_id INT NULL,
  -- FK: credito_config_aceptacion.id (NULL para campos)
  valor             TEXT NULL,
  fecha_creacion    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ccv_solicitud (solicitud_id),
  UNIQUE KEY uq_ccv_uniq (solicitud_id, config_campo_id, config_aceptacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documentos subidos por solicitud.
CREATE TABLE IF NOT EXISTS credito_documentos (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id      INT NOT NULL,
  -- FK: credito_solicitudes.id
  etapa             VARCHAR(30) NOT NULL,
  tipo_documento    VARCHAR(100) NOT NULL,
  nombre_archivo    VARCHAR(255) NULL,
  ruta_archivo      VARCHAR(500) NOT NULL,
  tamaño_archivo    INT NULL,
  tipo_mime         VARCHAR(100) NULL,
  es_obligatorio    TINYINT(1) NOT NULL DEFAULT 0,
  es_opcional       TINYINT(1) NOT NULL DEFAULT 0,
  subido_por        INT NULL,
  -- FK: usuarios.id (NULL si portal)
  fecha_subida      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cd_solicitud_etapa_tipo (solicitud_id, etapa, tipo_documento),
  INDEX idx_cd_solicitud (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Catálogo de productos y cláusulas. Unifica control_productos y control_clausulas.
CREATE TABLE IF NOT EXISTS productos (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  nombre               VARCHAR(100) NOT NULL,
  descripcion          TEXT NULL,
  tipo                 VARCHAR(30)  NOT NULL,
  -- valores: producto, clausula
  parametros           TEXT NULL,
  requiere_archivo     TINYINT(1)   DEFAULT 0,
  valor_minimo         DECIMAL(10,2) NOT NULL DEFAULT 0,
  valor_maximo         DECIMAL(10,2) NOT NULL DEFAULT 0,
  prioridad            INT          NOT NULL DEFAULT 100,
  estado_activo        TINYINT(1)   DEFAULT 1,
  creado_por           INT          NULL,
  -- FK: usuarios.id
  fecha_creacion       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  actualizado_por      INT          NULL,
  fecha_actualizacion  TIMESTAMP    NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TRIGGER IF EXISTS productos_bu;
CREATE TRIGGER productos_bu BEFORE UPDATE ON productos
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- Asignación de productos y cláusulas a asociados.
-- Unifica control_asignacion_asociado_producto y control_asignacion_asociado_clausula.
CREATE TABLE IF NOT EXISTS asociados_productos (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  cedula              VARCHAR(20)  NOT NULL,
  -- FK: sifone_asociados.cedula
  producto_id         INT          NOT NULL,
  -- FK: productos.id
  monto               DECIMAL(12,2) NOT NULL,
  fecha_inicio        DATE         NULL,
  dia_pago            TINYINT UNSIGNED NULL,
  -- 1..31 (NULL para cláusulas)
  meses_vigencia      INT          NULL,
  detalle             TEXT         NULL,
  estado_activo       TINYINT(1)   DEFAULT 1,
  creado_por          INT          NULL,
  -- FK: usuarios.id
  fecha_creacion      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP    NULL DEFAULT NULL,
  INDEX idx_ap_cedula (cedula),
  INDEX idx_ap_prod (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TRIGGER IF EXISTS asociados_productos_bu;
CREATE TRIGGER asociados_productos_bu BEFORE UPDATE ON asociados_productos
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- #####################################################################
-- MÓDULO: pagos (PSE, Confiar, conciliación, confirmación)
-- #####################################################################

-- Pagos recibidos vía PSE (cargados desde archivo Excel).
CREATE TABLE IF NOT EXISTS pagos_pse (
  pse_id             VARCHAR(50) NOT NULL,
  cus                BIGINT      NOT NULL,
  valor              BIGINT      NOT NULL,
  banco_originador   VARCHAR(50) NOT NULL,
  estado             VARCHAR(50) NOT NULL,
  referencia_1       VARCHAR(50) NOT NULL,
  referencia_2       BIGINT      NOT NULL,
  -- cédula del pagador (sin FK)
  referencia_3       VARCHAR(50) NOT NULL,
  -- nombre del pagador
  fecha_creada       DATETIME    NOT NULL,
  fecha_resolucion   DATETIME    NOT NULL,
  PRIMARY KEY (pse_id),
  INDEX idx_ppse_cedula (referencia_2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Estados de cuenta Confiar (cargados desde archivo Excel).
CREATE TABLE IF NOT EXISTS pagos_confiar (
  confiar_id       VARCHAR(50) NOT NULL,
  fecha            DATE        NULL,
  descripcion      VARCHAR(255) NULL,
  documento        VARCHAR(255) NULL,
  oficina          VARCHAR(255) NULL,
  valor_consignacion DOUBLE    NULL,
  valor_retiro     DOUBLE      NULL,
  saldo            DOUBLE      NULL,
  tipo_transaccion VARCHAR(50) NULL,
  PRIMARY KEY (confiar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conciliación automática PSE ↔ Confiar (por valor + fecha).
CREATE TABLE IF NOT EXISTS pagos_conciliacion (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  pse_id           VARCHAR(50) NOT NULL,
  -- FK: pagos_pse.pse_id
  confiar_id       VARCHAR(50) NOT NULL,
  -- FK: pagos_confiar.confiar_id
  tipo             VARCHAR(20) NULL,
  -- valores: directa, grupal, manual
  fecha_creacion   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pc_pse (pse_id),
  INDEX idx_pc_confiar (confiar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Confirmación de pagos Confiar: asignación a cédula + comprobante.
CREATE TABLE IF NOT EXISTS pagos_confirmacion (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  confiar_id       VARCHAR(50) NOT NULL UNIQUE,
  -- FK: pagos_confiar.confiar_id
  cedula           VARCHAR(20) NULL,
  -- FK: sifone_asociados.cedula
  ruta_comprobante VARCHAR(255) NULL,
  -- servido vía API autenticada
  comentario       TEXT NULL,
  estado           VARCHAR(20) NOT NULL DEFAULT 'pendiente',
  -- valores: pendiente, asignado, no_valido
  motivo_rechazo   TEXT NULL,
  rechazado_por    INT NULL,
  -- FK: usuarios.id
  fecha_rechazo    DATETIME NULL,
  creado_por       INT NULL,
  -- FK: usuarios.id
  fecha_creacion   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pconf_cedula (cedula),
  INDEX idx_pconf_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- MÓDULO: transacciones (creación de transacciones con rubros)
-- #####################################################################

CREATE TABLE IF NOT EXISTS transacciones (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  cedula           VARCHAR(20) NOT NULL,
  -- FK: sifone_asociados.cedula
  origen           VARCHAR(20) NOT NULL,
  -- valores: pse, confiar, boleta (los pagos Cash/QR legacy se canalizan vía Confiar/transacción; 'boleta' = venta de boleta de convenio)
  pse_id           VARCHAR(50) NULL,
  -- FK: pagos_pse.pse_id
  confiar_id       VARCHAR(50) NULL,
  -- FK: pagos_confiar.confiar_id
  recibo_caja      VARCHAR(50) NOT NULL,
  valor_total      DECIMAL(12,2) NOT NULL,
  usuario_id       INT NULL,
  -- FK: usuarios.id
  fecha_creacion   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_trx_cedula (cedula),
  INDEX idx_trx_origen (origen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transacciones_detalle (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  transaccion_id    INT NOT NULL,
  -- FK: transacciones.id
  tipo_rubro        VARCHAR(30) NOT NULL,
  -- valores: credito_mora, cobranza, credito, producto, boleta
  referencia_credito VARCHAR(50) NULL,
  -- FK: sifone_cartera_aseguradora.numero
  producto_id       INT NULL,
  -- FK: productos.id
  descripcion       VARCHAR(200) NULL,
  valor_recomendado DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_asignado    DECIMAL(12,2) NOT NULL DEFAULT 0,
  fecha_creacion    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_txd_trx (transaccion_id),
  INDEX idx_txd_tipo (tipo_rubro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- MÓDULO: convenios + boletería unificados (B9/B10/B15)
-- Un convenio (entidad/empresa) tiene productos; un producto puede ser un
-- "beneficio" (informativo, maneja_stock=0) o una "boleta/ticket" con stock
-- serializado (maneja_stock=1). El stock unitario vive en boleteria_boletas.
-- Sin FK ni ENUM (relaciones en comentarios -- FK:, valores en -- valores:).
-- LOS TIMESTAMPS DE ACTUALIZACIÓN LOS MANEJA EL SERVICE (no triggers).
-- #####################################################################

CREATE TABLE IF NOT EXISTS convenios (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  nombre             VARCHAR(255) NOT NULL,
  descripcion        TEXT NULL,
  telefono           VARCHAR(50) NULL,
  correo             VARCHAR(255) NULL,
  link               VARCHAR(500) NULL,
  anexo_nombre       VARCHAR(255) NULL,
  imagen             VARCHAR(500) NULL,
  -- basename opaco en uploads/convenios/ o URL externa http(s)
  activo             TINYINT(1) NOT NULL DEFAULT 1,
  -- valores: 0=inactivo, 1=activo
  creado_por         INT NULL,
  -- FK: usuarios.id
  fecha_creacion     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_conv_activo (activo),
  INDEX idx_conv_creado (creado_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS convenios_productos (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  convenio_id        INT NOT NULL,
  -- FK: convenios.id
  nombre             VARCHAR(255) NOT NULL,
  descripcion        TEXT NULL,
  valor              DECIMAL(12,2) NOT NULL DEFAULT 0,
  valor_general      DECIMAL(12,2) NULL,
  imagen             VARCHAR(500) NULL,
  -- basename opaco en uploads/convenios/ o URL externa http(s)
  activo             TINYINT(1) NOT NULL DEFAULT 1,
  -- valores: 0=inactivo, 1=activo
  precio_compra      DECIMAL(12,2) NULL,
  -- nullable (solo productos tipo boleta)
  precio_venta       DECIMAL(12,2) NULL,
  -- nullable (solo productos tipo boleta)
  maneja_stock       TINYINT(1) NOT NULL DEFAULT 0,
  -- valores: 0=beneficio informativo, 1=boleta/ticket con stock serializado
  creado_por         INT NULL,
  -- FK: usuarios.id
  fecha_creacion     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_cp_convenio (convenio_id),
  INDEX idx_cp_activo (activo),
  INDEX idx_cp_stock (maneja_stock),
  INDEX idx_cp_creado (creado_por)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS boleteria_boletas (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  producto_id           INT NOT NULL,
  -- FK: convenios_productos.id  (reemplaza el legacy boleteria_categoria_id)
  serial                VARCHAR(64) NOT NULL,
  numero_factura        VARCHAR(100) NULL,
  precio_compra_snapshot  DECIMAL(12,2) NOT NULL,
  precio_venta_snapshot   DECIMAL(12,2) NOT NULL,
  archivo_ruta          VARCHAR(255) NULL,
  -- basename opaco en uploads/boletas/ (sin subrutas)
  fecha_vencimiento     DATE NULL,
  estado                VARCHAR(30) NOT NULL DEFAULT 'disponible',
  -- valores: disponible, vendida, anulada, contabilizada
  asociado_cedula       VARCHAR(20) NULL,
  -- FK: sifone_asociados.cedula (comprador)
  metodo_venta          VARCHAR(30) NULL,
  -- valores: credito, regalo_cooperativa
  comprobante           VARCHAR(255) NULL,
  sifone_numero         VARCHAR(50) NULL,
  -- FK: sifone_movimientos_tributarios.numero (nº de movimiento Sifone vinculado)
  transaccion_id        INT NULL,
  -- FK: transacciones.id (venta financiera)
  fecha_creacion        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_vendida         TIMESTAMP NULL,
  fecha_contabilizacion TIMESTAMP NULL,
  fecha_actualizacion   TIMESTAMP NULL DEFAULT NULL,
  creado_por            INT NULL,
  -- FK: usuarios.id
  vendido_por           INT NULL,
  -- FK: usuarios.id
  contabilizado_por     INT NULL,
  -- FK: usuarios.id
  UNIQUE KEY uq_bol_serial_prod (producto_id, serial),
  INDEX idx_bol_producto (producto_id),
  INDEX idx_bol_estado (estado),
  INDEX idx_bol_cedula (asociado_cedula),
  INDEX idx_bol_vencimiento (fecha_vencimiento),
  INDEX idx_bol_sifone (sifone_numero),
  INDEX idx_bol_transaccion (transaccion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS boleteria_eventos (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  boleta_id       INT NOT NULL,
  -- FK: boleteria_boletas.id
  usuario_id      INT NOT NULL,
  -- FK: usuarios.id
  accion          VARCHAR(30) NOT NULL,
  -- valores: crear, vender, contabilizar, anular, desanular, deshacer_venta,
  --          eliminar, cargar_documento, descargar
  estado_anterior VARCHAR(30) NULL,
  -- valores: disponible, vendida, anulada, contabilizada (o NULL)
  estado_nuevo    VARCHAR(30) NULL,
  -- valores: disponible, vendida, anulada, contabilizada (o NULL)
  detalle         TEXT NULL,
  fecha           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_be_boleta (boleta_id),
  INDEX idx_be_usuario (usuario_id),
  INDEX idx_be_accion (accion),
  INDEX idx_be_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- VISTAS
-- #####################################################################

-- Vista unificada de movimientos: Sifone + multi.
DROP VIEW IF EXISTS vw_movimientos;
CREATE VIEW vw_movimientos AS
SELECT cedula, 'sifone_debito' AS tipo, fecham AS fecha, debito AS valor, detall AS detalle
FROM sifone_movimientos_tributarios WHERE debito > 0
UNION ALL
SELECT cedula, 'sifone_credito' AS tipo, fecham AS fecha, credit AS valor, detall AS detalle
FROM sifone_movimientos_tributarios WHERE credit > 0
UNION ALL
SELECT cedula, tipo, fecha, valor, detalle
FROM movimientos_multi;

-- Resumen monetario por asociado: aportes, bolsillos, comisiones, créditos, productos asignados, fondo bienestar.
-- 1 fila por asociado. Todos los cálculos en SQL. 23 campos.
-- Fuentes: balance_prueba + mov_tributarios + movimientos_multi + vw_creditos + productos + asociados_productos.
DROP VIEW IF EXISTS vw_productos;
CREATE VIEW vw_productos AS
SELECT sa.cedula,
       -- Aportes (6 campos)
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND LOWER(nombre) = 'aportes ordinarios'), 0) AS aportes_ordinarios,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND nombre = 'APORTES SOCIALES 2'), 0) AS aportes_sociales,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND nombre = 'Revalorizacion Aportes'), 0) AS aportes_revalorizaciones,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND nombre = 'PLAN FUTURO'), 0) AS plan_futuro,
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND LOWER(nombre) = 'aportes ordinarios'), 0)
        + COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                    WHERE cedula = sa.cedula AND nombre = 'APORTES SOCIALES 2'), 0) AS aportes_totales,
       COALESCE((SELECT SUM(FLOOR((POW(1.12, 1/12) - 1) * (ABS(credit) - ABS(debito))))
                 FROM sifone_movimientos_tributarios
                 WHERE cedula = sa.cedula AND cuenta = 31050501), 0) AS aportes_incentivos,
       (SELECT MAX(period) FROM sifone_balance_prueba
        WHERE cedula = sa.cedula AND LOWER(nombre) IN ('aportes ordinarios','aportes sociales 2')) AS aportes_ultimo_periodo,
       -- Bolsillos (3 campos)
       COALESCE((SELECT SUM(credit) - SUM(debito)
                 FROM sifone_movimientos_tributarios
                 WHERE cedula = sa.cedula
                   AND ((cuenta = 42309501)
                     OR (cuenta = 51109591 AND detall LIKE '%REVERSION%BOLSILLO%'))
                   AND fecham >= '2025-06-09'
                   AND detall NOT IN ('CIERRE DEL PYG')), 0) AS bolsillos,
       COALESCE(FLOOR((POW(1.06, 1/12) - 1) * (SELECT SUM(credit) - SUM(debito)
                 FROM sifone_movimientos_tributarios
                 WHERE cedula = sa.cedula
                   AND ((cuenta = 42309501)
                     OR (cuenta = 51109591 AND detall LIKE '%REVERSION%BOLSILLO%'))
                   AND fecham >= '2025-06-09'
                   AND detall NOT IN ('CIERRE DEL PYG'))), 0) AS bolsillos_incentivos,
       (SELECT MAX(fecham) FROM sifone_movimientos_tributarios
        WHERE cedula = sa.cedula AND cuenta IN (42309501, 51109591)) AS bolsillos_ultima_fecha,
-- Comisiones (1 campo) — netas: comisiones − redenciones de comisiones (≥0)
        GREATEST(0, COALESCE((SELECT SUM(valor) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'comision'), 0)
                  - COALESCE((SELECT SUM(ABS(valor)) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'redencion_comisiones'), 0)) AS comisiones,
       -- Totales (2 campos)
       COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                 WHERE cedula = sa.cedula AND LOWER(nombre) = 'aportes ordinarios'), 0)
        + COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                    WHERE cedula = sa.cedula AND nombre = 'APORTES SOCIALES 2'), 0)
        + COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                    WHERE cedula = sa.cedula AND nombre = 'Revalorizacion Aportes'), 0)
        + COALESCE((SELECT SUM(ABS(COALESCE(nuesal,0))) FROM sifone_balance_prueba
                    WHERE cedula = sa.cedula AND nombre = 'PLAN FUTURO'), 0)
        + COALESCE((SELECT SUM(credit) - SUM(debito)
                    FROM sifone_movimientos_tributarios
                    WHERE cedula = sa.cedula
                      AND ((cuenta = 42309501)
                        OR (cuenta = 51109591 AND detall LIKE '%REVERSION%BOLSILLO%'))
                      AND fecham >= '2025-06-09'
                      AND detall NOT IN ('CIERRE DEL PYG')), 0)
        + COALESCE((SELECT SUM(FLOOR((POW(1.12, 1/12) - 1) * (ABS(credit) - ABS(debito))))
                    FROM sifone_movimientos_tributarios
                    WHERE cedula = sa.cedula AND cuenta = 31050501), 0)
        + GREATEST(0, COALESCE((SELECT SUM(valor) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'comision'), 0)
                    - COALESCE((SELECT SUM(ABS(valor)) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'redencion_comisiones'), 0)) AS total_saldos_favor,
       COALESCE((SELECT SUM(FLOOR((POW(1.12, 1/12) - 1) * (ABS(credit) - ABS(debito))))
                 FROM sifone_movimientos_tributarios
                 WHERE cedula = sa.cedula AND cuenta = 31050501), 0)
        + GREATEST(0, COALESCE((SELECT SUM(valor) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'comision'), 0)
                    - COALESCE((SELECT SUM(ABS(valor)) FROM movimientos_multi
                    WHERE cedula = sa.cedula AND tipo = 'redencion_comisiones'), 0)) AS total_incentivos,
       -- Créditos desde vw_creditos (4 campos)
       COALESCE((SELECT COUNT(*) FROM vw_creditos WHERE cedula = sa.cedula), 0) AS total_creditos,
       COALESCE((SELECT SUM(saldo_capital) FROM vw_creditos WHERE cedula = sa.cedula), 0) AS total_capital_pendiente,
       COALESCE((SELECT SUM(saldo_mora) FROM vw_creditos WHERE cedula = sa.cedula), 0) AS total_mora,
       COALESCE((SELECT SUM(pago_minimo) FROM vw_creditos WHERE cedula = sa.cedula), 0) AS pago_minimo_creditos,
       -- Productos asignados (3 campos)
       COALESCE((SELECT SUM(ap.monto) FROM asociados_productos ap
                 INNER JOIN productos p ON p.id = ap.producto_id
                 WHERE ap.cedula = sa.cedula AND p.tipo = 'producto' AND ap.estado_activo = 1 AND p.estado_activo = 1), 0) AS producto_mensual_total,
       COALESCE((SELECT SUM(ap.monto) FROM asociados_productos ap
                 INNER JOIN productos p ON p.id = ap.producto_id
                 WHERE ap.cedula = sa.cedula AND p.tipo = 'clausula' AND ap.estado_activo = 1 AND p.estado_activo = 1), 0) AS clausula_mensual_total,
       (SELECT MIN(ap.dia_pago) FROM asociados_productos ap
        INNER JOIN productos p ON p.id = ap.producto_id
        WHERE ap.cedula = sa.cedula AND p.tipo = 'producto' AND ap.estado_activo = 1 AND p.estado_activo = 1 AND ap.dia_pago IS NOT NULL) AS producto_dia_pago,
       -- Fondo bienestar (2 campos)
       EXISTS (SELECT 1 FROM asociados_productos ap
               INNER JOIN productos p ON p.id = ap.producto_id
               WHERE ap.cedula = sa.cedula AND p.nombre = 'Fondo Bienestar'
                 AND ap.estado_activo = 1 AND p.estado_activo = 1) AS fondo_bienestar_tiene_producto,
       EXISTS (SELECT 1 FROM sifone_movimientos_tributarios
               WHERE cedula = sa.cedula AND cuenta = 42209506 AND credit > 0
                 AND fecha >= DATE_FORMAT(CURRENT_DATE - INTERVAL 1 MONTH, '%Y-%m-01')) AS fondo_bienestar_al_dia
FROM (SELECT DISTINCT cedula FROM sifone_asociados WHERE cedula IS NOT NULL AND cedula <> '') sa;

-- #####################################################################
-- CONFIG DE CRÉDITOS (B7a) — snapshot del estado en ejecución.
-- Secciones/etapas, campos tipados de crédito (con visibilidad por tipo de
-- solicitante), documentos y aceptaciones. Mantener en sync con la migración.
-- #####################################################################

INSERT INTO credito_config_seccion (`id`, `codigo`, `nombre`, `ocasion`, `visible_en`, `orden`, `estado_activo`) VALUES ('28', 'creacion', 'Creación', 'creacion', '["staff","cx"]', '10', '1'), ('29', 'revision', 'Revisión', 'revision', '["staff","cx"]', '20', '1'), ('30', 'estudio', 'Estudio', 'estudio', '["staff","cx"]', '30', '1'), ('31', 'consideracion', 'Consideración', 'consideracion', '["staff","cx"]', '40', '1'), ('32', 'estudio_aprobado', 'Aprobación', 'estudio_aprobado', '["staff","cx"]', '50', '1'), ('33', 'radicado_tesoreria', 'Radicación / Tesorería', 'radicado_tesoreria', '["staff","cx"]', '60', '1'), ('34', 'estudio_rechazado', 'Rechazo', 'estudio_rechazado', '["staff","cx"]', '70', '1'), ('35', 'final', 'Desembolso', 'final', '["staff","cx"]', '80', '1'), ('36', 'aceptacion', 'Aceptación y Autorizaciones', 'aceptacion', '["staff","cx"]', '90', '1');

INSERT INTO credito_config_campo (`id`, `seccion_id`, `campo`, `nombre`, `tipo`, `obligatorio`, `para_tipo_solicitante`, `visible_en`, `orden`, `estado_activo`) VALUES ('5', '35', 'numero_sifone', 'Numero Sifone', 'numero', '1', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('6', '35', 'fecha_desembolso', 'Fecha desembolso', 'fecha', '1', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('7', '35', 'valor_desembolso', 'Valor desembolso', 'numero', '1', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('8', '35', 'desembolsado_a_pac', 'Desembolsado a PAC', 'checkbox', '0', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('9', '32', 'monto_aprobado', 'Valor aprobado', 'numero', '1', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('10', '32', 'linea_aprobada', 'Línea aprobada', 'select', '1', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('11', '32', 'cuotas_aprobadas', 'Cuotas aprobadas', 'numero', '1', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('12', '32', 'codeudor_aprobado', 'Codeudor aprobado', 'select', '1', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('13', '32', 'desea_codeudor', '¿Desea codeudor?', 'checkbox', '0', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('14', '32', 'tipo_codeudor', 'Tipo de codeudor', 'select', '0', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('15', '32', 'base_aportes', 'Base de aportes', 'numero', '0', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('16', '32', 'fianza', 'Fianza', 'numero', '0', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('17', '32', 'prestamos_recoger', 'Préstamos a recoger', 'numero', '0', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1'), ('18', '32', 'dto_papeleria', 'Dto. papelería', 'numero', '0', '["estudiante","empleado_descuento_nomina","empleado_sin_descuento","independiente","pensionado_descuento_libranza","pensionado_sin_descuento_libranza"]', '["staff","cx"]', '100', '1');

INSERT INTO credito_config_documento (`id`, `seccion_id`, `tipo_documento`, `nombre_mostrar`, `descripcion`, `es_obligatorio`, `es_opcional`, `es_trato_especial`, `para_tipo_solicitante`, `visible_en`, `orden`, `estado_activo`) VALUES ('109', '28', 'cedula', 'Cédula (Copia ampliada al 150%)', 'Copia de cédula ampliada al 150%', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '1', '1'), ('110', '28', 'rut', 'RUT', 'Copia RUT', '1', '0', '0', '["independiente"]', '["staff","cx"]', '2', '1'), ('111', '28', 'camara_comercio', 'Cámara y Comercio', 'Registro mercantil cámara y comercio no mayor a 30 días', '0', '1', '0', '["independiente"]', '["staff","cx"]', '3', '1'), ('112', '28', 'comprobante_pago_1', 'Comprobante de Pago 1', 'Comprobantes de pago 2 últimas quincenas o meses', '1', '0', '0', '["empleado_descuento_nomina", "empleado_sin_descuento", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '4', '1'), ('113', '28', 'comprobante_pago_2', 'Comprobante de Pago 2', 'Comprobantes de pago 2 últimas quincenas o meses', '1', '0', '0', '["empleado_descuento_nomina", "empleado_sin_descuento", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '5', '1'), ('114', '28', 'certificacion_laboral', 'Certificación Laboral', 'Menor a 30 días de expedición', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento"]', '["staff","cx"]', '6', '1'), ('115', '28', 'certificado_ingresos_retenciones', 'Certificado de Ingresos y Retenciones', 'Año inmediatamente anterior, para solicitudes mayores a $4,0 MM', '0', '1', '0', '["empleado_descuento_nomina", "empleado_sin_descuento"]', '["staff","cx"]', '7', '1'), ('116', '28', 'soporte_otros_ingresos_1', 'Soporte de Otros Ingresos 1', 'Soporte de otros ingresos', '0', '1', '1', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '8', '1'), ('117', '28', 'soporte_otros_ingresos_2', 'Soporte de Otros Ingresos 2', 'Soporte de otros ingresos', '0', '1', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '9', '1'), ('118', '28', 'soporte_otros_ingresos_3', 'Soporte de Otros Ingresos 3', 'Soporte de otros ingresos', '0', '1', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '10', '1'), ('119', '28', 'ingresos_certificados_contador', 'Ingresos Certificados por Contador Público', 'No mayor a 30 días de vigencia, junto con la copia de Tarjeta Profesional y Certificado de Antecedentes Disciplinarios', '0', '0', '1', '["independiente"]', '["staff","cx"]', '11', '1'), ('120', '28', 'declaracion_renta', 'Declaración de Renta', 'Año gravable si está obligado a presentar, de lo contrario adjuntar Certificación juramentada', '0', '0', '1', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '12', '1'), ('121', '28', 'estados_financieros', 'Estados Financieros', 'De dos últimos años firmados por contador público, con copia de matrícula y antecedentes disciplinarios no mayor a 90 días', '0', '1', '0', '["independiente"]', '["staff","cx"]', '13', '1'), ('122', '28', 'certificado_tradicion_inmueble', 'Certificado de Tradición de Inmueble', 'Menor a 60 días de expedición', '0', '1', '0', '["empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '14', '1'), ('123', '28', 'orden_matricula_universitaria', 'Orden de Matrícula Universitaria', 'Copia orden de matrícula universitaria', '1', '0', '0', '["estudiante"]', '["staff","cx"]', '15', '1'), ('124', '28', 'servicio_publico_domicilio', 'Servicio Público de Domicilio', 'Copia servicio público de domicilio actual', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '16', '1'), ('125', '28', 'tarjeta_propiedad_vehiculo', 'Tarjeta de Propiedad de Vehículo', 'Copia de tarjeta de propiedad de vehículo', '0', '1', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '17', '1'), ('126', '28', 'extracto_bancario_1', 'Extracto Bancario 1', 'Extractos bancarios últimos tres meses', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '18', '1'), ('127', '28', 'extracto_bancario_2', 'Extracto Bancario 2', 'Extractos bancarios últimos tres meses', '0', '1', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '19', '1'), ('128', '28', 'extracto_bancario_3', 'Extracto Bancario 3', 'Extractos bancarios últimos tres meses', '0', '1', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '20', '1'), ('129', '29', 'estudio_datacredito', 'Estudio en Datacredito', 'Estudio en Datacredito', '0', '0', '1', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '1', '1'), ('130', '29', 'certificado_aportes_trebol', 'Certificado de Aportes Trébol', 'Certificado de Aportes Trébol', '0', '0', '1', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '2', '1'), ('131', '29', 'formato_fondeo_independientes', 'Formato de Fondeo para Independientes', 'Formato de fondeo para independientes', '1', '0', '0', '["independiente"]', '["staff","cx"]', '3', '1'), ('132', '29', 'simulacion_credito_sifone', 'Simulación de Crédito Sifone', 'Simulación de Crédito Sifone', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '4', '1'), ('133', '30', 'evaluacion_credito_cartera', 'Evaluación del Crédito por el Área de Cartera', 'Evaluación del crédito por el área de cartera', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '1', '1'), ('134', '30', 'cedula_codeudor', 'Cédula de Codeudor', 'Cédula de codeudor', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '2', '1'), ('135', '30', 'codeudor_comprobante_1', 'Codeudor Comprobante 1', 'Comprobantes de pago 2 últimas quincenas o meses', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '3', '1'), ('136', '30', 'codeudor_comprobante_2', 'Codeudor Comprobante 2', 'Comprobantes de pago 2 últimas quincenas o meses', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '4', '1'), ('137', '30', 'codeudor_certificacion_laboral', 'Codeudor Certificación Laboral', 'Menor a 30 días de expedición', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '5', '1'), ('138', '30', 'data_credito_codeudor', 'Data Credito Codeudor', 'Data Credito Codeudor', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '6', '1'), ('139', '35', 'estudio_credito_sifone', 'Estudio de Crédito Sifone', 'Estudio de Crédito Sifone', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '1', '1'), ('140', '35', 'documento_pagare', 'Documento Pagaré', 'Documento Pagaré', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '2', '1'), ('141', '35', 'documento_libranza', 'Documento Libranza para Crédito', 'Documento Libranza para Crédito', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '3', '1'), ('142', '35', 'plan_pagos', 'Plan de Pagos', 'Plan de Pagos', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '4', '1'), ('143', '30', 'acta_credito', 'Acta de crédito', 'Acta de crédito del estudio', '1', '0', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '7', '1'), ('144', '35', 'comprobante_egreso', 'Comprobante de Egreso', 'Comprobante de egreso del crédito', '0', '1', '0', '["estudiante", "empleado_descuento_nomina", "empleado_sin_descuento", "independiente", "pensionado_descuento_libranza", "pensionado_sin_descuento_libranza"]', '["staff","cx"]', '5', '1');

INSERT INTO credito_config_aceptacion (`id`, `clave`, `tipo`, `titulo`, `texto`, `obligatorio`, `visible_en`, `orden`, `estado_activo`) VALUES ('10', 'habeas_data', 'texto', 'AUTORIZACIÓN DE TRATAMIENTO DE DATOS PERSONALES Y CONSULTA EN CENTRALES DE RIESGO', 'Para continuar con su solicitud de crédito, requerimos su autorización expresa para el manejo de sus datos personales, de conformidad con la Ley 1581 de 2012.', '0', '["staff","cx"]', '10', '1'), ('11', 'acepta_tratamiento_datos', 'aceptacion', 'Acepto el tratamiento de datos personales y consulta en centrales de riesgo', '', '1', '["staff","cx"]', '20', '1'), ('12', 'acepta_ofertas_comerciales', 'aceptacion', 'Acepto recibir ofertas comerciales', '', '0', '["staff","cx"]', '30', '1');

-- #####################################################################
-- SEED: Convenios (B10) — 33 convenios + 69 productos (id EXPLÍCITO,
-- idéntico al legacy para conservar los ids en la migración). Idempotente (INSERT IGNORE).
-- #####################################################################
INSERT IGNORE INTO convenios (id, nombre, descripcion, telefono, correo, link, anexo_nombre) VALUES
(1,'PARQUE JAIME DUQUE','El Pasaporte Legado ofrece acceso integral a las principales atracciones culturales, mecánicas y naturales del Parque Jaime Duque (incluyendo el Taj Mahal y el Bioparque Wakatá), exceptuando únicamente las bicicletas del Ecoparque y el Tren de los Andes.','3134031555','solicitudes@coomultiunion.com','https://parquejaimeduque.com/','Ver detalles'),
(2,'MUNDO AVENTURA','El Pasaporte Silver + Combo está diseñado para personas de menos de 149 cm. El Pasaporte Gold + Combo es para personas que midan más de 150 cm e incluye 33 atracciones ilimitadas.','3134031555','solicitudes@coomultiunion.com','https://mundoaventura.com.co/','Ver detalles'),
(3,'SALITRE MAGICO','El Pasaporte Nitro + Combo para personas de más de 140 cm, incluyendo acceso a la montaña rusa Drakko y 27 atracciones. El Pasaporte Nitro Kids + Combo para niños de hasta 139 cm.','3134031555','solicitudes@coomultiunion.com','https://salitremagico.com.co/','Ver detalles'),
(4,'SKY ZONE TRAMPOLINE PARK','El plan Salto Freestyle + Combo incluye 90 minutos de salto, medias especiales, combo de perro con gaseosa y acceso a la actividad Glow Party.','3134031555','solicitudes@coomultiunion.com','https://www.skyzone.com.co/','Ver detalles'),
(5,'MULTI PARQUE','La Manilla Maxi de Multiparque otorga derecho a disfrutar de atracciones mecánicas y no mecánicas: Skybikes, montaña rusa, carros chocones, lasertag, granja y botes.','3134031555','solicitudes@coomultiunion.com','https://multiparque.com/','Ver detalles'),
(6,'MALOKA','Recorrido Exploratorio + Laboratorio + Proyección. Elige entre tres laboratorios según la edad. Válido de martes a domingo y festivos.','3134031555','solicitudes@coomultiunion.com','https://maloka.org/','Ver detalles'),
(7,'CINEMA PROCINAL','Entrada + Confitería válidas para 2D, 3D e IMAX. Combo 1 ($22.100) y Combo 3 ($33.000). Válidas en Bogotá, Soacha, Medellín, Cartagena, Villavicencio y Barrancabermeja.','3134031555','solicitudes@coomultiunion.com','https://procinal.com.co/ciudad/sd/cartelera','Ver cartelera'),
(8,'CINECOLOMBIA','Boleta General 2D y Bono Cineco Pass Premium para todos los formatos. Combos de confitería. Redimibles a nivel nacional.','3134031555','solicitudes@coomultiunion.com','https://www.cinecolombia.com/','Ver cartelera'),
(9,'CINEMARK','Boletas generales 2D y 3D para cualquier función a nivel nacional. Combos de confitería disponibles.','3134031555','solicitudes@coomultiunion.com','https://www.cinemark.com.co/','Ver cartelera'),
(10,'CINE ROYAL FILMS','Combo que integra boleta 2D, gaseosa mediana, caja de crispetas y perro caliente. Válido a nivel nacional.','3134031555','solicitudes@coomultiunion.com','https://cinemasroyalfilms.com/cartelera/bogot%C3%A1','Ver cartelera'),
(11,'PISCILAGO','Pasaporte Piscitour con ingreso a todas las atracciones, tarjeta médica, almuerzo y refrigerio. Niños hasta 4 años no pagan.','3134031555','solicitudes@coomultiunion.com','https://www.piscilago.co/','Ver detalles'),
(12,'CATEDRAL DE SAL DE ZIPAQUIRA','Pasaporte Standard con ingreso a la catedral, museos, mapping, cortometraje 3D Nucuma y City Tour. Domingo a domingo.','3134031555','solicitudes@coomultiunion.com','https://goo.su/KyD5M','Ver detalles'),
(13,'KANALOA PARQUE ACUATICO','Pasadía con uso ilimitado de piscinas y toboganes, acceso a la granja Makondo. Martes a domingo y festivos.','3134031555','solicitudes@coomultiunion.com','https://kanaloa.com.co/','Ver detalles'),
(14,'BIOPARQUE UKUMARI','Ingreso con tres recorridos autoguiados, más de 90 especies de animales y 6,000 tipos de plantas. Lunes a domingo.','3134031555','solicitudes@coomultiunion.com','https://ukumari.org/','Ver detalles'),
(15,'CARIBE AVENTURA','Pasadía con uso ilimitado de atracciones acuáticas y no acuáticas. Niños menores de 2 años gratis.','3134031555','solicitudes@coomultiunion.com','https://caribeaventura.com/','Ver detalles'),
(16,'LAS BAILARINAS PARQUE AGUA Y AVENTURA','Acceso a piscinas termales, baño turco y pozos de lodo. Tres modalidades de alimentación.','3134031555','solicitudes@coomultiunion.com','https://hotelcampestrelasbailarinas.com/','Ver detalles'),
(17,'MINA DE SAL NEMOCON','Recorrido por el Museo de la Historia de la Sabana, espejos de salmuera, cascada de sal y más. Lunes a domingo.','3134031555','solicitudes@coomultiunion.com','https://www.minadesal.com/','Ver detalles'),
(18,'PARQUE EXPLORA','Pasadía combinado Parque Explora y Planetario: Acuario, Dinosaurios, salas interactivas y Domo.','3134031555','solicitudes@coomultiunion.com','https://www.parqueexplora.org/','Ver detalles'),
(19,'INFAPARQUE ACUATICO IKARUS','Pasadía con acceso a Piscina Atlántida, Tobogán Río de Icaria, Cascada Neda, Pegaso y más.','3134031555','solicitudes@coomultiunion.com','https://www.inflaparqueikarus.com.co/','Ver detalles'),
(20,'PARQUE DEL CAFE','Pasaporte Múltiple (125 cm+), Junior (90-124 cm) y Senior. Acceso ilimitado a atracciones. Miércoles a domingo.','3134031555','solicitudes@coomultiunion.com','https://parquedelcafe.co/','Ver detalles'),
(21,'HACIENDA NAPOLES','Tres niveles: Plan Básico (1 día), Plan Safari (1 día + zonas acuáticas) y Plan Premium (3 días).','3134031555','solicitudes@coomultiunion.com','https://haciendanapoles.com/tienda','Ver detalles'),
(22,'PANACA','Pasaporte Panaca (1 día), Pasaporte Campo (2 días) y Pasaporte Travesía Noche (espectáculo nocturno).','3134031555','solicitudes@coomultiunion.com','https://panaca.co/','Ver detalles'),
(23,'PLAYA HAWAI','Entrada al parque con acceso ilimitado a todas las atracciones acuáticas. Opción con almuerzo.','3134031555','solicitudes@coomultiunion.com','https://playahawai.com/','Ver detalles'),
(24,'Kyqa Mhuysqa','Manillas Infantiles (Chuta y Tunjito) para niños y Paquetes Colombia para adultos con opción Extremo.','3134031555','solicitudes@coomultiunion.com','https://kyqamhuysqa.bio.link/','Ver detalles'),
(25,'COMBO PARTE Y COMPARTE ALAS (KFC)','Combos para consumo en sitio: Parte y Comparte ($47.800) y Hot Wings ($21.500). Válidos a nivel nacional.','3134031555','solicitudes@coomultiunion.com','https://www.kfc.co/','Ver detalles'),
(26,'POPSY','Postres con base de helado en sabores tradicionales, especiales o exclusivos. Redención en puntos a nivel nacional.','3134031555','solicitudes@coomultiunion.com','https://heladospopsy.com/','Ver detalles'),
(27,'GYM SMART FIT','Plan Black VIP Mensualidad sin inscripción. Acceso a sedes nacionales e internacionales, 5 invitados gratis, clases grupales.','3134031555','solicitudes@coomultiunion.com','https://www.smartfit.com.co/','Ver detalles'),
(28,'ODONTOLOGIA','Bono de odontología para limpieza y profilaxis. Examen clínico de primera vez sin costo y atención de urgencias 24 horas.','3134031555','solicitudes@coomultiunion.com','','Ver detalles'),
(29,'B&B JURIDICO','Asesoría jurídica especializada para asociados.','3134031555','solicitudes@coomultiunion.com','https://www.bybjuridicos.com/','Ver detalles'),
(30,'SEGUROS','Financiación para SOAT y Pólizas Todo Riesgo. Cobertura del 100%. Tasa especial de interés. Plazos de 6 y 12 meses.','3134031555','solicitudes@coomultiunion.com','','Ver detalles'),
(31,'EXEQUIAL EXEPAZ','Protección integral ante fallecimiento. Servicio funerario, seguro de vida, protección de mascotas. Cobertura Nacional.','3134031555','solicitudes@coomultiunion.com','https://www.jardinesdepaz.com/','Ver detalles'),
(32,'VIAJES-NACIONAL INTERNACIONAL','Convenio con Orion Group. Tarifas de Mayorista, 5% descuento adicional. Cuota inicial financiable.','3134031555','solicitudes@coomultiunion.com','https://oriongroupmayorista.com/index.php/producto-del-mes/','Ver detalles'),
(33,'PARQUE TEMATICO MANOA','Experiencias con diferentes niveles: Laberinto, Explorador y Guerrero. Cada tarifa incluye acceso según el nivel seleccionado.','3134031555','solicitudes@coomultiunion.com','','Ver detalles');

INSERT IGNORE INTO convenios_productos (id, convenio_id, nombre, descripcion, valor, valor_general) VALUES
(1,1,'Pasaporte el legado','Pasaporte físico',69000,NULL),
(2,2,'Pasaporte Silver + Combo','Pasaporte virtual',73500,NULL),
(3,2,'Pasaporte Gold + Combo','Pasaporte virtual',98950,NULL),
(4,3,'Pasaporte Nitro Combo+ Bono Lluvia','Pasaporte virtual',83800,NULL),
(5,3,'Pasaporte Nitro Kids + Combo+ Seguro de lluvia','Pasaporte virtual',65900,NULL),
(6,4,'Salto Freestyle + Combo','Pasaporte virtual',74500,NULL),
(7,5,'Manilla maxi','Pasaporte virtual',68000,NULL),
(8,6,'Recorrido Exploratorio + Laboratorio + Proyección','Pasaporte virtual – Reserva',61000,NULL),
(9,7,'Entrada válida (2D -3D – IMAX) + combo 1','Bonos virtuales',22100,NULL),
(10,7,'Entrada válida (2D -3D – IMAX) + combo 3','Bonos virtuales',33000,NULL),
(11,8,'Boleta General 2D','Bonos virtuales',13950,NULL),
(12,8,'Bono Cineco Pass Premium (2D, 3D, Dinamix, Onyx, IMAX, Platino)','Bonos virtuales',21500,NULL),
(13,8,'Combo Cine Fan: Crispetas 100g + Gaseosa 960ml','Bonos virtuales',14950,NULL),
(14,8,'Combo Cine Pro: Crispetas 100g + Gaseosa 960ml + Perro Caliente','Bonos virtuales',28300,NULL),
(15,8,'Combo Cine Fan Junior: Crispetas 55g + Gaseosa 640ml + Chocolatina','Bonos virtuales',14100,NULL),
(16,9,'Boleta general 2D','Bonos virtuales',12400,NULL),
(17,9,'Boleta general 3D','Bonos virtuales',18950,NULL),
(18,9,'Combo 1: Crispetas 100g + Gaseosa de 532ml','Bonos virtuales',12650,NULL),
(19,9,'Combo 3: Crispetas 100g + Gaseosa pet de 384ml + Perro Caliente','Bonos virtuales',24600,NULL),
(20,10,'Combo Royal Films: Boleta 2D + Gaseosa + Crispeta','Bonos virtuales',30000,NULL),
(21,11,'Pasaporte Piscitour (No afiliado)','Pasaporte virtual',96000,NULL),
(22,12,'Pasaporte Standard - Adulto (13 a 59 años)','Reserva - orden virtual',85000,NULL),
(23,12,'Pasaporte Standard - Niño (4 a 12 años)','Reserva - orden virtual',72000,NULL),
(24,12,'Pasaporte Standard - Adulto mayor (+60) y discapacidad','Reserva - orden virtual',72000,NULL),
(25,13,'Pasadía','Reserva - orden virtual',62500,NULL),
(26,14,'Pasadía - Adulto (+140 cm)','Reserva - orden virtual',51900,NULL),
(27,14,'Pasadía - Niño (2 a 12 años)','Reserva - orden virtual',36000,NULL),
(28,14,'Pasadía - Adulto mayor y discapacidad','Reserva - orden virtual',40000,NULL),
(29,15,'Pasadía Jueves a Sábado - Niño (2 a 11 años)','Reserva - orden virtual',47500,NULL),
(30,15,'Pasadía Jueves a Sábado - Adulto (+12 años)','Reserva - orden virtual',85500,NULL),
(31,15,'Pasadía Jueves a Sábado - Adulto mayor','Reserva - orden virtual',72000,NULL),
(32,15,'Pasadía Domingo/Festivo - Niño (2 a 11 años)','Reserva - orden virtual',76000,NULL),
(33,15,'Pasadía Domingo/Festivo - Adulto (+12 años)','Reserva - orden virtual',115000,NULL),
(34,15,'Pasadía Domingo/Festivo - Adulto mayor','Reserva - orden virtual',96000,NULL),
(35,16,'Pasadía - Niño (5 a 12 años)','Reserva - orden virtual',70800,NULL),
(36,16,'Pasadía - Adulto (13 a 70 años)','Reserva - orden virtual',79500,NULL),
(37,17,'Pasadía - Adulto (13 a 59 años)','Reserva - orden virtual',41800,NULL),
(38,17,'Pasadía - Niño (4 a 12 años)','Reserva - orden virtual',29900,NULL),
(39,17,'Pasadía - Adulto mayor y discapacidad','Reserva - orden virtual',29900,NULL),
(40,18,'Pasadía','Reserva - orden virtual',72400,NULL),
(41,19,'Pasadía - Niño (3 a 12) y adulto mayor','Reserva - orden virtual',52000,NULL),
(42,19,'Pasadía - Adulto (13 a 59 años)','Reserva - orden virtual',54700,NULL),
(43,20,'Pasaporte Múltiple','Reserva - orden virtual',94500,NULL),
(44,20,'Pasaporte Junior (90 a 124 cm)','Reserva - orden virtual',66000,NULL),
(45,20,'Pasaporte Senior','Reserva - orden virtual',66000,NULL),
(46,21,'Pasadía Básico','Reserva - orden virtual',94500,NULL),
(47,21,'Pasadía Safari','Reserva - orden virtual',151700,NULL),
(48,21,'Pasadía Premium','Reserva - orden virtual',208500,NULL),
(49,22,'Panaca Adulto','Reserva - orden virtual',107800,NULL),
(50,22,'Panaca Niño','Reserva - orden virtual',76200,NULL),
(51,23,'Tarifa niño (4 a 11) y adulto mayor','Reserva - orden virtual',42800,NULL),
(52,23,'Tarifa adulto (12 a 59 años)','Reserva - orden virtual',52300,NULL),
(53,24,'Tarifa Chuta','Reserva - orden virtual',40000,NULL),
(54,24,'Tarifa Tunjito','Reserva - orden virtual',26800,NULL),
(55,25,'Combo Parte y Comparte alas en sitio','Reserva - orden virtual',47800,NULL),
(56,25,'Combo Hot Wings en sitio','Reserva - orden virtual',21500,NULL),
(57,26,'2 conos de 1 bola 90 grms','Reserva - orden virtual',14500,NULL),
(58,26,'Sundae 1 bola','Reserva - orden virtual',9600,NULL),
(59,26,'Copa Gelarti','Reserva - orden virtual',10300,NULL),
(60,26,'Brownie con helado','Reserva - orden virtual',13000,NULL),
(61,27,'Plan Black VIP Mensualidad','Reserva - orden virtual',114000,NULL),
(62,28,'Beneficio Fondo Bienestar','MULTI',100000,NULL),
(63,31,'Beneficio Fondo Bienestar','MULTI',36000,NULL),
(64,32,'Beneficio Fondo Bienestar','VARIOS',1234,NULL),
(65,30,'Beneficio Fondo Bienestar','MULTI',0,NULL),
(66,33,'Tarifa Laberinto','Reserva - orden virtual',37800,NULL),
(67,33,'Tarifa Explorador','Reserva - orden virtual',86000,NULL),
(68,33,'Tarifa Guerrero','Reserva - orden virtual',152000,NULL),
(69,26,'1 cono de 2 bolas 90grms','Reserva-orden virtual',9350,NULL);

-- #####################################################################
-- MÓDULO: Tienda (B8 + B15 tienda pública)
-- Catálogo (categorías/marcas/productos), rangos de precio, clientes,
-- compras → stock (1 fila por unidad), ventas → ganancia, reversiones →
-- bonos y promociones. Sin FK ni ENUM (relaciones en comentarios -- FK:,
-- valores en -- valores:). LOS TIMESTAMPS DE ACTUALIZACIÓN LOS MANEJA EL
-- SERVICE (no triggers).
-- #####################################################################

CREATE TABLE IF NOT EXISTS tienda_categorias (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  nombre               VARCHAR(120) NOT NULL,
  estado_activo        TINYINT(1) NOT NULL DEFAULT 1,
  -- valores: 0=inactiva, 1=activa
  creado_por           INT NULL,
  -- FK: usuarios.id
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_tcat_nombre (nombre),
  INDEX idx_tcat_activo (estado_activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_marcas (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  nombre               VARCHAR(120) NOT NULL,
  estado_activo        TINYINT(1) NOT NULL DEFAULT 1,
  -- valores: 0=inactiva, 1=activa
  creado_por           INT NULL,
  -- FK: usuarios.id
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_tmar_nombre (nombre),
  INDEX idx_tmar_activo (estado_activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_productos (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id          INT NOT NULL,
  -- FK: tienda_categorias.id
  marca_id              INT NOT NULL,
  -- FK: tienda_marcas.id
  nombre                VARCHAR(200) NOT NULL,
  descripcion           TEXT NULL,
  foto_path             VARCHAR(255) NULL,
  -- basename opaco en uploads/tienda/ (o URL externa http(s))
  precio_compra_sugerido DECIMAL(12,2) NULL,
  precio_venta_sugerido  DECIMAL(12,2) NULL,
  estado_activo         TINYINT(1) NOT NULL DEFAULT 1,
  -- valores: 0=inactivo, 1=activo
  agotado               TINYINT(1) NOT NULL DEFAULT 0,
  -- valores: 0=disponible, 1=agotado (flag MANUAL del catálogo)
  creado_por            INT NULL,
  -- FK: usuarios.id
  fecha_creacion        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_tprod (categoria_id, marca_id, nombre),
  INDEX idx_tprod_cat (categoria_id),
  INDEX idx_tprod_marca (marca_id),
  INDEX idx_tprod_activo (estado_activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_rangos_precio (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  desde                DECIMAL(12,2) NOT NULL,
  hasta                DECIMAL(12,2) NOT NULL,
  porcentaje           DECIMAL(5,2) NOT NULL,
  -- porcentaje de descuento (0..100) → precio Multiunión = ROUND(precio - (precio * % / 100), -3)
  creado_por           INT NULL,
  -- FK: usuarios.id
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_trango_desde (desde),
  UNIQUE KEY uq_trango_hasta (hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed de rangos de precio (idéntico al migration del legacy
-- 2026_06_24_rangos_precio_tienda.sql): precio Multiunión = ROUND(precio·(1-%/100), -3).
INSERT IGNORE INTO tienda_rangos_precio (desde, hasta, porcentaje) VALUES
  (1,500000,10), (500001,1000000,12), (1000001,1500000,13), (1500001,2000000,14),
  (2000001,2500000,15), (2500001,3000000,15), (3000001,3500000,15), (3500001,4000000,15),
  (4000001,4500000,15), (4500001,5000000,5), (5000001,5500000,5), (5500001,6000000,5),
  (6000001,6500000,5), (6500001,7000000,5), (7000001,7500000,5), (7500001,8000000,5),
  (8000001,8500000,5);

CREATE TABLE IF NOT EXISTS tienda_clientes (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  tipo                 VARCHAR(20) NOT NULL DEFAULT 'externo',
  -- valores: asociado, externo
  cedula               VARCHAR(20) NULL,
  -- FK: sifone_asociados.cedula (solo si tipo='asociado')
  nit_cedula           VARCHAR(50) NULL,
  -- documento del cliente externo (si tipo='externo')
  nombre               VARCHAR(200) NOT NULL,
  telefono             VARCHAR(50) NULL,
  email                VARCHAR(120) NULL,
  estado_activo        TINYINT(1) NOT NULL DEFAULT 1,
  -- valores: 0=inactivo, 1=activo
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_tcli_doc (nit_cedula),
  UNIQUE KEY uq_tcli_cedula (cedula),
  INDEX idx_tcli_tipo (tipo),
  INDEX idx_tcli_activo (estado_activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_compras (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id           INT NULL,
  -- FK: usuarios.id
  observacion          VARCHAR(300) NULL,
  fecha_compra         DATE NULL,
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_tcomp_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_compra_detalles (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  compra_id             INT NOT NULL,
  -- FK: tienda_compras.id
  producto_id           INT NOT NULL,
  -- FK: tienda_productos.id
  cantidad              INT NOT NULL,
  precio_compra         DECIMAL(12,2) NOT NULL,
  precio_venta_sugerido DECIMAL(12,2) NOT NULL,
  fecha_creacion        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tcomd_compra (compra_id),
  INDEX idx_tcomd_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_stock (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  compra_detalle_id    INT NULL,
  -- FK: tienda_compra_detalles.id (NULL si el origen no pudo preservarse)
  producto_id          INT NOT NULL,
  -- FK: tienda_productos.id
  precio_compra        DECIMAL(12,2) NOT NULL,
  precio_venta         DECIMAL(12,2) NULL,
  numero_serie         VARCHAR(30) NULL,
  -- IMEI/serial de la unidad (único; NULL para unidades sin serie)
  estado               VARCHAR(20) NOT NULL DEFAULT 'disponible',
  -- valores: disponible, vendido, anulado
  venta_detalle_id     INT NULL,
  -- FK: tienda_venta_detalles.id (solo si estado='vendido')
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY uq_tstock_serie (numero_serie),
  INDEX idx_tstock_compra_det (compra_detalle_id),
  INDEX idx_tstock_producto (producto_id),
  INDEX idx_tstock_estado (estado),
  INDEX idx_tstock_vta_det (venta_detalle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_ventas (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  tipo_cliente         VARCHAR(20) NOT NULL DEFAULT 'externo',
  -- valores: asociado, externo
  cedula               VARCHAR(20) NULL,
  -- FK: sifone_asociados.cedula (si tipo_cliente='asociado')
  cliente_id           INT NULL,
  -- FK: tienda_clientes.id (si tipo_cliente='externo')
  usuario_id           INT NULL,
  -- FK: usuarios.id
  total                DECIMAL(12,2) NOT NULL DEFAULT 0,
  ceco                 VARCHAR(100) NULL,
  -- valores: TIENDA GALERIAS, COOPERATIVA OFICINA, REDES - WEB
  fecha_venta          DATETIME NULL,
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tvent_tipo (tipo_cliente),
  INDEX idx_tvent_cedula (cedula),
  INDEX idx_tvent_cliente (cliente_id),
  INDEX idx_tvent_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_venta_detalles (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  venta_id             INT NOT NULL,
  -- FK: tienda_ventas.id
  producto_id          INT NOT NULL,
  -- FK: tienda_productos.id
  cantidad             INT NOT NULL,
  -- >1 permitido (línea multi-unidad; se vincula una fila tienda_stock por unidad)
  precio_unitario      DECIMAL(12,2) NOT NULL,
  subtotal             DECIMAL(12,2) NOT NULL,
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tvdet_venta (venta_id),
  INDEX idx_tvdet_producto (producto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_venta_detalle_stock (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  venta_detalle_id     INT NOT NULL,
  -- FK: tienda_venta_detalles.id
  stock_id             INT NOT NULL,
  -- FK: tienda_stock.id (unidad física consumida)
  UNIQUE KEY uq_tvds_stock (stock_id),
  INDEX idx_tvds_vta_det (venta_detalle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_venta_pagos (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  venta_id             INT NOT NULL,
  -- FK: tienda_ventas.id
  tipo                 VARCHAR(20) NOT NULL,
  -- valores: efectivo, bold, qr, sifone, sistecredito, alocredito, bono
  monto                DECIMAL(12,2) NOT NULL,
  numero_credito_sifone VARCHAR(50) NULL,
  -- obligatorio si tipo='sifone' | 'sistecredito' | 'alocredito'
  bono_id              INT NULL,
  -- FK: tienda_bonos.id (si tipo='bono')
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tvpago_venta (venta_id),
  INDEX idx_tvpago_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_reversiones (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  venta_detalle_id     INT NOT NULL,
  -- FK: tienda_venta_detalles.id (línea revertida)
  motivo               TEXT NULL,
  usuario_id           INT NULL,
  -- FK: usuarios.id
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_trev_vta_det (venta_detalle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_bonos (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  cedula               VARCHAR(20) NULL,
  -- FK: sifone_asociados.cedula (acreedor asociado, si aplica)
  cliente_id           INT NULL,
  -- FK: tienda_clientes.id (acreedor externo, si aplica)
  monto_original       DECIMAL(12,2) NOT NULL,
  saldo                DECIMAL(12,2) NOT NULL,
  -- saldo acumulativo disponible; se descuenta al pagar con tipo='bono'
  origen_reversion_id  INT NULL,
  -- FK: tienda_reversiones.id
  estado               VARCHAR(20) NOT NULL DEFAULT 'activo',
  -- valores: activo, usado, expirado
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_tbono_cedula (cedula),
  INDEX idx_tbono_cliente (cliente_id),
  INDEX idx_tbono_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tienda_promociones (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  producto_id          INT NOT NULL,
  -- FK: tienda_productos.id
  precio_descuento     DECIMAL(15,2) NOT NULL,
  fecha_inicio         DATE NOT NULL,
  fecha_fin            DATE NOT NULL,
  estado_activo        TINYINT(1) NOT NULL DEFAULT 1,
  -- valores: 0=inactiva, 1=activa
  creado_por           INT NULL,
  -- FK: usuarios.id
  fecha_creacion       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion  TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_tpromo_producto (producto_id),
  INDEX idx_tpromo_fechas (fecha_inicio, fecha_fin),
  INDEX idx_tpromo_activo (estado_activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- FONDO DE BIENESTAR (B7 Fase 2) — maestro de convenios + flujo de solicitud
-- Origen: docs/07c_FONDO_BIENESTAR.md. Reconstrucción v2:
--  - El maestro `fondo_bienestar_convenios` conserva el CRUD legado (sin FK/ENUM).
--  - La solicitud vive en el motor único de solicitudes como tipo `fondo`
--    (cabecera `solicitudes` + detalle tipado `solicitudes_det_fondo`).
--  - La redención (bloqueo/frecuencia) queda en `fondo_bienestar_redenciones`.
--  - Emails: se encolan en `email_cola` y los envía el worker
--    SMTP real `core/Email/worker.php` (Mailer, credenciales solo en .env).
-- #####################################################################

-- Maestro de convenios y requisitos (portado de fondo_bienestar_convenios legacy)
CREATE TABLE IF NOT EXISTS fondo_bienestar_convenios (
  id                           INT AUTO_INCREMENT PRIMARY KEY,
  codigo                       VARCHAR(50)  NOT NULL UNIQUE,
  nombre                       VARCHAR(255) NOT NULL,
  descripcion                  TEXT NULL,
  meses_requeridos             INT NOT NULL DEFAULT 0,
  no_redime                    BOOLEAN DEFAULT FALSE,
  bloqueo_meses                INT NULL,
  requiere_cumpleanos_30_dias  BOOLEAN DEFAULT FALSE,
  opciones_json                TEXT NULL,      -- JSON ["A","B"]
  requiere_pdf_obligatorio     BOOLEAN DEFAULT FALSE,
  nombre_pdf_obligatorio       VARCHAR(255) NULL,
  envia_email_entidad          BOOLEAN DEFAULT FALSE,
  email_entidad                VARCHAR(255) NULL,
  meses_permitidos             VARCHAR(100) NULL, -- JSON [1,2,6] o CSV "1,2,6"
  meses_espera_proxima         INT DEFAULT 0,
  orden                        INT DEFAULT 0,
  estado_activo                BOOLEAN DEFAULT TRUE,
  agotado                      TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=Producto marcado como agotado',
  fecha_creacion               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion          DATETIME NULL,
  INDEX idx_fbc_activo (estado_activo),
  INDEX idx_fbc_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Detalle tipado del tipo de solicitud 'fondo' (motor único de solicitudes)
CREATE TABLE IF NOT EXISTS solicitudes_det_fondo (
  solicitud_id           INT NOT NULL,          -- FK: solicitudes.id
  convenio_id            INT NOT NULL,          -- FK: fondo_bienestar_convenios.id (sin FK física)
  opcion_elegida         VARCHAR(100) NULL,
  ruta_pdf_obligatorio   VARCHAR(500) NULL,
  observaciones          TEXT NULL,
  dato_extra             JSON NULL,
  fecha_uso              DATE NULL,             -- set por el efecto al aprobar (= hoy)
  bloqueado_hasta        DATE NULL,             -- set por el efecto al aprobar (hoy + bloqueo_meses)
  PRIMARY KEY (solicitud_id),
  INDEX idx_sdf_convenio (convenio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historial de redenciones (fuente de bloqueo y frecuencia para validarApto)
CREATE TABLE IF NOT EXISTS fondo_bienestar_redenciones (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  cedula_asociado    VARCHAR(20) NOT NULL,      -- FK: sifone_asociados.cedula (sin FK física)
  convenio_id        INT NOT NULL,
  solicitud_id       INT NULL,                  -- FK: solicitudes.id
  fecha_redencion    DATE NOT NULL,
  bloqueado_hasta    DATE NULL,
  fecha_creacion     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fbr_cedula (cedula_asociado),
  INDEX idx_fbr_convenio (convenio_id),
  INDEX idx_fbr_fecha (fecha_redencion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cola de salida de TODOS los correos (SMTP real vía core/Email/Mailer.php + worker.php).
-- Encolan: FondoMailService (fondo/entidad), Efectos (retiro_aporte), api/solicitudes,
-- api/creditos, api/portal. Worker: php core/Email/worker.php (pendiente→enviado/fallido).
CREATE TABLE IF NOT EXISTS email_cola (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  tipo            VARCHAR(30)  NOT NULL,   -- valores: crear, asignar, aprobar, rechazar, entidad
  destino         VARCHAR(255) NOT NULL,   -- email destinatario
  asunto          VARCHAR(255) NULL,
  cuerpo          TEXT NULL,
  solicitud_id    INT NULL,                -- FK: solicitudes.id
  estado          VARCHAR(20) NOT NULL DEFAULT 'pendiente', -- valores: pendiente, enviado, fallido
  error           TEXT NULL,
  fecha_creacion  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_envio     DATETIME NULL,
  INDEX idx_fecc_estado (estado),
  INDEX idx_fecc_solicitud (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed maestro — los 8 convenios legado (orden de columnas del DDL v2 arriba)
INSERT IGNORE INTO fondo_bienestar_convenios
  (codigo, nombre, meses_requeridos, no_redime, bloqueo_meses, requiere_cumpleanos_30_dias,
   opciones_json, requiere_pdf_obligatorio, nombre_pdf_obligatorio, envia_email_entidad,
   email_entidad, meses_permitidos, meses_espera_proxima, orden, estado_activo)
VALUES
  ('subsidio_exequial','Subsidio Exequial',0,TRUE,NULL,FALSE,NULL,FALSE,NULL,FALSE,NULL,'1',0,1,TRUE),
  ('bono_tecnologia','Bono de tecnología',3,FALSE,12,FALSE,NULL,FALSE,NULL,FALSE,NULL,NULL,0,2,TRUE),
  ('bono_escolar','Bono escolar',6,FALSE,12,FALSE,NULL,TRUE,'Lista escolar',FALSE,NULL,'[1,2,6,7]',10,3,TRUE),
  ('bono_cumpleanos','Bono de cumpleaños',6,FALSE,NULL,TRUE,'["Bono Spa","Bono Cine"]',FALSE,NULL,FALSE,NULL,NULL,4,4,TRUE),
  ('asesoria_juridica','Asesoría Jurídica',6,FALSE,12,FALSE,NULL,FALSE,NULL,TRUE,NULL,NULL,5,5,TRUE),
  ('bono_odontologico','Bono odontológico',9,FALSE,12,FALSE,NULL,FALSE,NULL,TRUE,NULL,NULL,6,6,TRUE),
  ('bono_centro_ocular','Bono Centro ocular Miopía (consulta)',9,FALSE,12,FALSE,NULL,FALSE,NULL,TRUE,NULL,NULL,7,7,TRUE),
  ('ancheta','Ancheta',12,FALSE,12,FALSE,NULL,FALSE,NULL,FALSE,NULL,'[12]',0,8,TRUE)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Seed configuración motor: tipo 'fondo' (estados + transiciones con efecto)
INSERT IGNORE INTO solicitudes_tipos
  (codigo, nombre, prefijo_radicado, requiere_asignacion, permite_reapertura, permite_anulacion, detalle_tabla, permite_comentarios)
VALUES
  ('fondo','Fondo de Bienestar','FB',1,0,0,'solicitudes_det_fondo',1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT IGNORE INTO solicitudes_estados (tipo_codigo, codigo, nombre, fase, es_final, orden) VALUES
  ('fondo','pendiente','Pendiente','CREADA',0,1),
  ('fondo','asignado','Asignado','EN_GESTION',0,2),
  ('fondo','aprobada','Aprobada','RESUELTA_POSITIVA',1,3),
  ('fondo','rechazada','Rechazada','RESUELTA_NEGATIVA',1,4);

INSERT IGNORE INTO solicitudes_transiciones
  (tipo_codigo, estado_origen, estado_destino, rol_relacion, requiere_comentario, gate_regla, efecto)
VALUES
  ('fondo','pendiente','asignado',NULL,0,NULL,NULL),
  ('fondo','asignado','aprobada','responsable',1,NULL,'crear_redencion_fondo'),
  ('fondo','asignado','rechazada','responsable',1,NULL,NULL);

-- #####################################################################
-- ############ SOLICITUDES UNIFICADAS — FAMILIA COMPLETA (B7) ##########
-- ############ ticket · boleta · retiro_incentivos · certificado ########
-- ############ (append; no toca bloques previos) ########################
-- #####################################################################
-- Detalle tipado para cada uno de los tipos de la familia ampliada.
-- PK=solicitud_id → FK: solicitudes.id (relación en comentario, sin FK física).

CREATE TABLE IF NOT EXISTS solicitudes_det_ticket (
  solicitud_id     INT NOT NULL,            -- FK: solicitudes.id
  categoria_id     INT NULL,                -- FK: solicitudes_ticket_categorias.id
  resumen          VARCHAR(200) NOT NULL,
  descripcion      TEXT NULL,
  fecha_vencimiento DATE NULL,              -- cumplimiento del ticket
  PRIMARY KEY (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_ticket_categorias (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  nombre            VARCHAR(120) NOT NULL,
  descripcion       VARCHAR(500) NULL,
  estado_activo     TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NULL,
  UNIQUE KEY uq_stc_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_det_boleta (
  solicitud_id     INT NOT NULL,            -- FK: solicitudes.id
  motivo           TEXT NULL,
  direccion_entrega VARCHAR(255) NULL,
  metodo_pago      VARCHAR(20) NULL,        -- 'qr' | 'credito'
  PRIMARY KEY (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Items del carrito de boletas (1 fila por producto-boleta del pedido).
CREATE TABLE IF NOT EXISTS solicitudes_det_boleta_items (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id      INT NOT NULL,           -- FK: solicitudes.id
  producto_id       INT NOT NULL,           -- FK: convenios_productos.id (maneja_stock=1)
  cantidad          INT NOT NULL DEFAULT 1,
  precio_venta_snapshot DECIMAL(12,2) NULL,
  INDEX idx_sdbi_solicitud (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vínculo persistente solicitud↔boleta (unidades vendidas al aprobar).
CREATE TABLE IF NOT EXISTS solicitudes_boletas (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  solicitud_id INT NOT NULL,                -- FK: solicitudes.id
  boleta_id    INT NOT NULL,                -- FK: boleteria_boletas.id
  producto_id  INT NULL,                    -- FK: convenios_productos.id
  item_id      INT NULL,                    -- FK: solicitudes_det_boleta_items.id
  INDEX idx_sb_solicitud (solicitud_id),
  INDEX idx_sb_boleta (boleta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_det_retiro_incentivos (
  solicitud_id   INT NOT NULL,              -- FK: solicitudes.id
  val_incentivos DECIMAL(15,2) NOT NULL DEFAULT 0,
  val_comisiones DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_retiro   DECIMAL(15,2) NOT NULL DEFAULT 0,
  medio          VARCHAR(40) NULL,          -- valores: abono_aportes, retiro_efectivo, transferencia, otro
  PRIMARY KEY (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS solicitudes_det_certificado (
  solicitud_id INT NOT NULL,                -- FK: solicitudes.id
  especie      VARCHAR(30) NOT NULL,        -- valores: declaracion_renta, estado_cuenta
  anio         INT NULL,                    -- año gravable / período
  PRIMARY KEY (solicitud_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Configuración de los nuevos tipos
-- ---------------------------------------------------------------------
INSERT IGNORE INTO solicitudes_tipos
  (codigo, nombre, prefijo_radicado, requiere_asignacion, permite_reapertura, permite_anulacion, detalle_tabla, permite_comentarios)
VALUES
  ('ticket',            'Ticket interno (UI→UI)',     'TKT', 1, 1, 1, 'solicitudes_det_ticket',            1),
  ('boleta',            'Solicitud de boletas',       'BOL', 1, 0, 0, 'solicitudes_det_boleta',            1),
  ('retiro_incentivos', 'Retiro de incentivos',       'INC', 1, 0, 0, 'solicitudes_det_retiro_incentivos', 1),
  ('certificado',       'Certificado (documento)',    'CER', 1, 0, 0, 'solicitudes_det_certificado',      1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- Estados TICKET (máquina interna tipo ticketera). 'rechazado' reabre → en_curso.
INSERT IGNORE INTO solicitudes_estados (tipo_codigo, codigo, nombre, fase, es_final, orden) VALUES
  ('ticket','backlog','Backlog','CREADA',0,1),
  ('ticket','en_curso','En Curso','EN_GESTION',0,2),
  ('ticket','en_espera','En Espera','EN_GESTION',0,3),
  ('ticket','resuelto','Resuelto','PRE_RESUELTA',0,4),
  ('ticket','aceptado','Aceptado','RESUELTA_POSITIVA',1,5),
  ('ticket','rechazado','Rechazado','RESUELTA_NEGATIVA',0,6),
  ('ticket','cancelado','Cancelado','ANULADA',1,7);

INSERT IGNORE INTO solicitudes_transiciones
  (tipo_codigo, estado_origen, estado_destino, rol_relacion, requiere_comentario, gate_regla, efecto)
VALUES
  ('ticket','backlog','en_curso',NULL,0,NULL,NULL),
  ('ticket','en_curso','en_espera','responsable',0,NULL,NULL),
  ('ticket','en_espera','en_curso','responsable',0,NULL,NULL),
  ('ticket','en_curso','resuelto','responsable',1,NULL,NULL),
  ('ticket','resuelto','aceptado','creador',0,NULL,NULL),
  ('ticket','resuelto','rechazado','creador',1,NULL,NULL),
  ('ticket','rechazado','en_curso','creador',0,NULL,NULL),
  ('ticket','backlog','cancelado',NULL,0,NULL,NULL),
  ('ticket','en_curso','cancelado',NULL,0,NULL,NULL),
  ('ticket','en_espera','cancelado',NULL,0,NULL,NULL);

-- Estados BOLETA / RETIRO_INCENTIVOS / CERTIFICADO (ciclo CX estándar).
INSERT IGNORE INTO solicitudes_estados (tipo_codigo, codigo, nombre, fase, es_final, orden) VALUES
  ('boleta','pendiente','Pendiente','CREADA',0,1),
  ('boleta','asignado','Asignado','EN_GESTION',0,2),
  ('boleta','aprobada','Aprobada','RESUELTA_POSITIVA',1,3),
  ('boleta','rechazada','Rechazada','RESUELTA_NEGATIVA',1,4),
  ('retiro_incentivos','pendiente','Pendiente','CREADA',0,1),
  ('retiro_incentivos','asignado','Asignado','EN_GESTION',0,2),
  ('retiro_incentivos','aprobada','Aprobada','RESUELTA_POSITIVA',1,3),
  ('retiro_incentivos','rechazada','Rechazada','RESUELTA_NEGATIVA',1,4),
  ('certificado','pendiente','Pendiente','CREADA',0,1),
  ('certificado','asignado','Asignado','EN_GESTION',0,2),
  ('certificado','aprobada','Aprobada','RESUELTA_POSITIVA',1,3),
  ('certificado','rechazada','Rechazada','RESUELTA_NEGATIVA',1,4);

INSERT IGNORE INTO solicitudes_transiciones
  (tipo_codigo, estado_origen, estado_destino, rol_relacion, requiere_comentario, gate_regla, efecto)
VALUES
  ('boleta','pendiente','asignado',NULL,0,NULL,NULL),
  ('boleta','asignado','aprobada','responsable',1,'stock_boleta','emitir_boletas'),
  ('boleta','asignado','rechazada','responsable',1,NULL,NULL),
  ('retiro_incentivos','pendiente','asignado',NULL,0,NULL,NULL),
  ('retiro_incentivos','asignado','aprobada','responsable',1,'saldo_incentivos','crear_movimiento_incentivos'),
  ('retiro_incentivos','asignado','rechazada','responsable',1,NULL,NULL),
  ('certificado','pendiente','asignado',NULL,0,NULL,NULL),
  ('certificado','asignado','aprobada','responsable',1,NULL,'entregar_certificado'),
  ('certificado','asignado','rechazada','responsable',1,NULL,NULL);

-- PQRS: paso intermedio "recibido" (adición al ciclo existente; idempotente).
INSERT IGNORE INTO solicitudes_estados (tipo_codigo, codigo, nombre, fase, es_final, orden) VALUES
  ('pqrs','recibido','Recibido','EN_GESTION',0,2);

INSERT IGNORE INTO solicitudes_transiciones
  (tipo_codigo, estado_origen, estado_destino, rol_relacion, requiere_comentario, gate_regla, efecto)
VALUES
  ('pqrs','pendiente','recibido',NULL,0,NULL,NULL),
  ('pqrs','recibido','asignado',NULL,0,NULL,NULL),
  ('pqrs','recibido','aprobada','responsable',1,NULL,NULL),
  ('pqrs','recibido','rechazada','responsable',1,NULL,NULL);

-- ---------------------------------------------------------------------
-- Catálogo de movimientos para retiro de incentivos (gate valida saldo).
-- ---------------------------------------------------------------------
INSERT IGNORE INTO movimientos_tipos (tipo, nombre, clasificacion, requiere_referido, valida_disponible, activo) VALUES
  ('retiro_incentivos', 'Retiro incentivos', 'egreso', 0, 0, 1),
  ('retiro_comision',   'Retiro comisión',   'egreso', 0, 0, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ---------------------------------------------------------------------
-- Categorías de ticket (catálogo, migradas de ticketera_categoria)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO solicitudes_ticket_categorias (id, nombre, descripcion, estado_activo) VALUES
  (1,'TAREAS','Tareas generales del área',1),
  (2,'LINK DE PAGOS','Gestión de links de pago',1),
  (3,'ESTADOS DE CUENTA','Solicitudes de estados de cuenta',1),
  (4,'QUEJAS Y RECLAMOS','Quejas y reclamos internos',1),
  (5,'SOLICITUD DE DOCUMENTOS','Solicitud de documentos',1),
  (6,'BOLETERIA','Gestión de boletería',1),
  (7,'GESTION CREDITOS','Gestión de créditos',1),
  (8,'FALLAS TECNICAS','Fallas técnicas de sistemas',1),
  (9,'NUEVOS DESARROLLOS MULTI','Nuevos desarrollos',1),
  (10,'SOLICITUD DE DESEMBOLSOS','Solicitudes de desembolsos',1),
  (11,'PIEZAS PUBLICITARIAS','Piezas publicitarias',1),
  (12,'TAREAS-TIENDA','Tareas de la tienda',1),
  (13,'SOLICITUD IMEI DE CELULARES','Solicitud de IMEI',1),
  (14,'REVERSION DE APORTES','Reversión de aportes',1),
  (15,'AJUSTE DE CARTERA','Ajustes de cartera',1)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- #####################################################################
-- ############ RETIRO DE APORTES (B13) — tipo 'retiro_aporte' ##########
-- #####################################################################
CREATE TABLE IF NOT EXISTS solicitudes_det_retiro_aporte (
  solicitud_id              INT NOT NULL,             -- FK: solicitudes.id
  tipo                      VARCHAR(10) NOT NULL,     -- valores: total, parcial
  monto_solicitado          DECIMAL(15,2) NULL,       -- solo se pide en 'parcial' (NULL en total)
  ruta_pdf_retiro           VARCHAR(500) NULL,        -- PDF firma de retiro (portal)
  ruta_pdf_certificado      VARCHAR(500) NULL,        -- PDF certificado de aportes (portal)
  fecha_estimada_desembolso DATE NULL,                -- +90d (total) / +30d (parcial)
  PRIMARY KEY (solicitud_id),
  INDEX idx_sdra_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO solicitudes_tipos
  (codigo, nombre, prefijo_radicado, requiere_asignacion, permite_reapertura, permite_anulacion, detalle_tabla, permite_comentarios)
VALUES
  ('retiro_aporte', 'Retiro de aportes', 'RET', 0, 0, 0, 'solicitudes_det_retiro_aporte', 0)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

INSERT IGNORE INTO solicitudes_estados (tipo_codigo, codigo, nombre, fase, es_final, orden) VALUES
  ('retiro_aporte','pendiente','Pendiente','CREADA',0,1),
  ('retiro_aporte','desembolsado','Desembolsado','RESUELTA_POSITIVA',1,2),
  ('retiro_aporte','rechazada','Rechazada','RESUELTA_NEGATIVA',1,3);

INSERT IGNORE INTO solicitudes_transiciones
  (tipo_codigo, estado_origen, estado_destino, rol_relacion, requiere_comentario, gate_regla, efecto)
VALUES
  ('retiro_aporte','pendiente','desembolsado',NULL,0,'plazo_desembolso','notificar_desembolso_retiro'),
  ('retiro_aporte','pendiente','rechazada',NULL,1,NULL,NULL);

-- #####################################################################
-- ############ SEGURIDAD — intentos de login (rate-limit) ############
-- #####################################################################
CREATE TABLE IF NOT EXISTS login_intentos (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  clave       VARCHAR(191) NOT NULL,   -- p.ej. 'staff:<ip>:<usuario>' o 'portal:<ip>:<cedula>'
  intento_at  DATETIME NOT NULL,
  INDEX idx_li_clave (clave),
  INDEX idx_li_at (intento_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- ############ CONFIGURACIÃ“N (parÃ¡metros clave/valor) #################
-- #####################################################################
CREATE TABLE IF NOT EXISTS app_configuracion (
  clave               VARCHAR(60)  NOT NULL,
  valor               TEXT         NULL,
  descripcion         VARCHAR(255) NULL,
  fecha_actualizacion DATETIME     NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- ############ ASISTENTE IA — chat del portal (cx/) ##################
-- Sin FK ni ENUM (regla §3). Prefijo de módulo: chat_*
-- #####################################################################

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
  off_desde_utc         TINYINT       NULL,
  off_hasta_utc         TINYINT       NULL,
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

-- Parámetros configurables del asistente.
INSERT INTO app_configuracion (clave, valor, descripcion) VALUES
  ('chat_ia_habilitado',      '1',                         'Asistente IA del portal: 1 activo | 0 deshabilitado'),
  ('chat_ia_limite_diario',   '50',                        'Máximo de mensajes por asociado por día'),
  ('chat_ia_max_tokens',      '800',                       'Tope de tokens de salida por respuesta'),
  ('chat_ia_prompt_version',  '2026-10-16',                'Versión vigente del system prompt'),
  ('chat_ia_contacto_soporte','atenciongalerias@coomultiunion.com', 'Correo de escalado a soporte humano'),
  ('chat_ia_sesion_ttl',      '15',                        'Minutos de inactividad tras los que se cierra una conversación del asistente'),
  ('chat_ia_nombre',          'Soli',                      'Nombre visible del asistente IA del portal')
ON DUPLICATE KEY UPDATE clave = clave;

-- >>> Precio real de deepseek-ai/DeepSeek-V4.1-Flash en SiliconFlow
--     (USD por 1.000.000 de tokens). Estándar 00-14 UTC · descuento 14-24 UTC.
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

-- #####################################################################
-- ############ API POR TOKENS (integraciones externas / curl) #########
-- #####################################################################
-- Ver sql/migrations/007_api_tokens.sql. Sin FK/ENUM (§3).
-- api_tokens: tokens emitidos; el secreto se guarda SOLO como SHA-256.
-- api_endpoints_autorizados: allowlist global (módulos o acciones) invocables
--   por token; la gestiona ui/modules/api/pages/authorized_endpoints.php.
CREATE TABLE IF NOT EXISTS api_tokens (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(120) NOT NULL,          -- etiqueta legible
  prefijo      VARCHAR(12)  NOT NULL,          -- primeros chars del secreto (UI/logs)
  token_hash   VARCHAR(128) NOT NULL,          -- SHA-256 del secreto (nunca en claro)
  actor_id     INT          NOT NULL,          -- FK implícita: usuarios.id (staff)
  scopes       TEXT         NULL,              -- JSON array de claves autorizadas del token
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
  clave               VARCHAR(120) NOT NULL,   -- 'solicitudes.gestion.crear_ticket' o 'solicitudes'
  descripcion         VARCHAR(255) NULL,
  activo              TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_actualizacion DATETIME     NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Semilla de endpoints autorizados (ver sql/migrations/008_api_endpoints_seed.sql).
-- Solo estas claves pueden invocarse por token; el resto responde 403.
INSERT INTO api_endpoints_autorizados (clave, descripcion, activo, fecha_actualizacion) VALUES
  ('oficina.asociados',                  'Asociados: consulta (listar/ficha) y gate de productos', 1, NOW()),
  ('oficina.movimientos_tipos',          'Movimientos: catálogo de tipos',                        1, NOW()),
  ('solicitudes',                        'Solicitudes: listar / detalle / config (lectura)',       1, NOW()),
  ('solicitudes.gestion.crear_ticket',   'Solicitudes: crear ticket interno',                       1, NOW()),
  ('asociados.detalle.asignar',          'Asociados: asignar producto/cláusula',                   1, NOW()),
  ('asociados.detalle.editar',           'Asociados: editar asignación',                           1, NOW()),
  ('asociados.detalle.eliminar',         'Asociados: eliminar asignación',                         1, NOW()),
  ('asociados.detalle.adjuntar',         'Asociados: adjuntar a asignación',                       1, NOW()),
  ('cobranza.lista.comunicar',           'Cobranza: comunicaciones (crear/editar/eliminar)',       1, NOW()),
  ('cobranza.lista.indagar',             'Cobranza: indagaciones (crear/editar/eliminar)',         1, NOW()),
  ('cobranza.lista.adjuntar',            'Cobranza: adjunto de indagación',                        1, NOW()),
  ('pagos.conciliar',                    'Pagos PSE: asignar/desasignar Confiar',                  1, NOW()),
  ('pagos.confirmar',                    'Pagos Confiar: asignar / no válido / desasignar',        1, NOW()),
  ('pagos.upload',                       'Pagos Confiar: subir comprobante',                       1, NOW()),
  ('movimientos.crear',                  'Movimientos: crear',                                     1, NOW()),
  ('movimientos.editar',                 'Movimientos: editar',                                    1, NOW()),
  ('cargas.procesar',                    'Cargas: procesar archivo Sifone',                        1, NOW()),
  ('creditos.nueva_solicitud.crear',     'Créditos: crear solicitud',                              1, NOW()),
  ('creditos.gestionar_solicitud.etapas','Créditos: cambiar de etapa',                             1, NOW()),
  ('creditos.gestionar_solicitud.campos','Créditos: diligenciar campos',                           1, NOW()),
  ('tienda.catalogo.crear',              'Tienda catálogo: crear',                                 1, NOW()),
  ('tienda.catalogo.editar',             'Tienda catálogo: editar',                                1, NOW()),
  ('tienda.catalogo.toggle',             'Tienda catálogo: activar/inactivar',                     1, NOW()),
  ('tienda.catalogo.eliminar',           'Tienda catálogo: eliminar',                              1, NOW()),
  ('tienda.clientes.crear',              'Tienda clientes: crear',                                 1, NOW()),
  ('tienda.clientes.editar',             'Tienda clientes: editar',                                1, NOW()),
  ('tienda.clientes.toggle',             'Tienda clientes: activar/inactivar',                     1, NOW()),
  ('tienda.clientes.eliminar',           'Tienda clientes: eliminar',                              1, NOW()),
  ('convenios.index.crear',              'Convenios: crear convenio/producto',                     1, NOW()),
  ('convenios.index.editar',             'Convenios: editar convenio/producto',                    1, NOW()),
  ('convenios.index.toggle',             'Convenios: activar/inactivar',                           1, NOW()),
  ('convenios.index.eliminar',           'Convenios: eliminar',                                    1, NOW()),
  ('convenios.stock.crear',              'Boletas: crear',                                         1, NOW()),
  ('convenios.stock.anular',             'Boletas: anular',                                        1, NOW()),
  ('convenios.stock.vender',             'Boletas: vender',                                        1, NOW()),
  ('convenios.stock.contabilizar',       'Boletas: contabilizar',                                  1, NOW()),
  ('portal.actuar',                       'Portal: actuar en nombre de una cédula (X-Acting-As-Cedula)', 1, NOW())
ON DUPLICATE KEY UPDATE clave = clave;

-- Límite de solicitudes por token (ventana fija). Ver sql/migrations/009_api_token_rate.sql.
CREATE TABLE IF NOT EXISTS api_token_rate (
  token_id INT         NOT NULL,   -- FK implícita: api_tokens.id
  ventana  VARCHAR(20) NOT NULL,   -- número de ventana (epoch / ventana_seg)
  usos     INT         NOT NULL DEFAULT 0,
  PRIMARY KEY (token_id, ventana)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- #####################################################################
-- ############ COMUNICACIONES — WhatsApp / SMS / Email ################
-- Sin FK ni ENUM (regla §3). Prefijo de módulo: com_*
-- Espejo de sql/migrations/015_comunicaciones_twilio.sql
-- #####################################################################

-- Remitentes habilitados (números Twilio, WhatsApp o correos SendGrid/SMTP).
-- valores.canal: sms | whatsapp | email
-- valores.proveedor: twilio | sendgrid | smtp
CREATE TABLE IF NOT EXISTS com_remitentes (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  canal                 VARCHAR(12)  NOT NULL,
  proveedor             VARCHAR(15)  NOT NULL DEFAULT 'twilio',
  etiqueta              VARCHAR(100) NOT NULL,
  direccion             VARCHAR(160) NOT NULL,
  nombre_remitente      VARCHAR(150) NULL,
  messaging_service_sid VARCHAR(40)  NULL,
  soporta_media         TINYINT(1)   NOT NULL DEFAULT 0,
  limite_mps            INT          NOT NULL DEFAULT 1,
  activo                TINYINT(1)   NOT NULL DEFAULT 1,
  predeterminado        TINYINT(1)   NOT NULL DEFAULT 0,
  creado_por            INT          NULL,
  fecha_creacion        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   DATETIME     NULL,
  UNIQUE KEY uq_com_rem_canal_dir (canal, direccion),
  INDEX idx_com_rem_canal (canal, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contactos por canal con consentimiento (opt-in) y baja (opt-out).
CREATE TABLE IF NOT EXISTS com_contactos (
  id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
  canal              VARCHAR(12)  NOT NULL,
  direccion          VARCHAR(160) NOT NULL,
  nombre             VARCHAR(150) NULL,
  asociado_cedula    VARCHAR(20)  NULL,
  opt_in             TINYINT(1)   NOT NULL DEFAULT 0,
  opt_in_fuente      VARCHAR(30)  NULL,
  opt_in_fecha       DATETIME     NULL,
  opt_out            TINYINT(1)   NOT NULL DEFAULT 0,
  opt_out_fuente     VARCHAR(30)  NULL,
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
CREATE TABLE IF NOT EXISTS com_plantillas (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  proveedor             VARCHAR(20)  NOT NULL DEFAULT 'twilio_content',
  proveedor_template_id VARCHAR(80)  NULL,
  nombre                VARCHAR(150) NOT NULL,
  canal                 VARCHAR(12)  NOT NULL DEFAULT 'whatsapp',
  categoria             VARCHAR(25)  NULL,
  idioma                VARCHAR(8)   NOT NULL DEFAULT 'es',
  asunto                VARCHAR(255) NULL,
  cuerpo                TEXT         NULL,
  cuerpo_html           MEDIUMTEXT   NULL,
  variables_json        TEXT         NULL,
  media_json            TEXT         NULL,
  tipos_json            LONGTEXT     NULL,
  estado_aprobacion     VARCHAR(20)  NULL,
  motivo_rechazo        VARCHAR(255) NULL,
  activo                TINYINT(1)   NOT NULL DEFAULT 1,
  fecha_sincronizacion  DATETIME     NULL,
  creado_por            INT          NULL,
  fecha_creacion        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   DATETIME     NULL,
  UNIQUE KEY uq_com_plantilla_proveedor (proveedor, proveedor_template_id),
  INDEX idx_com_plantilla_canal (canal, estado_aprobacion, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adjuntos del módulo.
CREATE TABLE IF NOT EXISTS com_adjuntos (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  nombre_original  VARCHAR(255) NOT NULL,
  clave            VARCHAR(255) NOT NULL,
  mime             VARCHAR(120) NOT NULL,
  tamano           BIGINT       NOT NULL DEFAULT 0,
  canal            VARCHAR(12)  NULL,
  creado_por       INT          NULL,
  fecha_creacion   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_com_adjunto_clave (clave),
  INDEX idx_com_adjunto_canal (canal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Planes/campañas.
CREATE TABLE IF NOT EXISTS com_planes (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  nombre                VARCHAR(160) NOT NULL,
  descripcion           TEXT         NULL,
  tipo                  VARCHAR(15)  NOT NULL DEFAULT 'masiva',
  canal                 VARCHAR(12)  NOT NULL,
  proveedor             VARCHAR(15)  NOT NULL DEFAULT 'twilio',
  remitente_id          INT          NULL,
  plantilla_id          INT          NULL,
  proveedor_template_id VARCHAR(80)  NULL,
  modo_contenido        VARCHAR(12)  NOT NULL DEFAULT 'texto',
  asunto                VARCHAR(255) NULL,
  cuerpo                MEDIUMTEXT   NULL,
  cuerpo_html           MEDIUMTEXT   NULL,
  media_json            TEXT         NULL,
  variables_json        TEXT         NULL,
  origen                VARCHAR(15)  NOT NULL DEFAULT 'manual',
  filtros_json          TEXT         NULL,
  programado_para       DATETIME     NULL,
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
  ia_costo_usd          DECIMAL(14,6) NOT NULL DEFAULT 0,
  ia_modelo             VARCHAR(80)  NULL,
  ia_tokens_prompt      INT          NULL,
  ia_tokens_completion  INT          NULL,
  ia_generaciones       INT          NOT NULL DEFAULT 0,
  moneda                VARCHAR(3)   NOT NULL DEFAULT 'USD',
  creado_por            INT          NULL,
  programado_por        INT          NULL,
  fecha_ejecucion       DATETIME     NULL,
  fecha_fin             DATETIME     NULL,
  fecha_cancelacion     DATETIME     NULL,
  cancelado_por         INT          NULL,
  error_resumen         TEXT         NULL,
  fecha_creacion        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion   DATETIME     NULL,
  INDEX idx_com_plan_estado (estado, programado_para),
  INDEX idx_com_plan_creado (creado_por),
  INDEX idx_com_plan_canal (canal, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Destinatarios por plan.
CREATE TABLE IF NOT EXISTS com_destinatarios (
  id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
  plan_id             INT          NOT NULL,
  contacto_id         BIGINT       NULL,
  asociado_cedula     VARCHAR(20)  NULL,
  nombre              VARCHAR(150) NULL,
  destino             VARCHAR(160) NOT NULL,
  canal               VARCHAR(12)  NOT NULL,
  variables_json      TEXT         NULL,
  estado              VARCHAR(15)  NOT NULL DEFAULT 'pendiente',
  motivo_omitido      VARCHAR(160) NULL,
  proveedor_message_id VARCHAR(80) NULL,
  estado_proveedor    VARCHAR(25)  NULL,
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

-- Eventos crudos de estado.
CREATE TABLE IF NOT EXISTS com_eventos (
  id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
  plan_id             INT          NULL,
  destinatario_id     BIGINT       NULL,
  proveedor           VARCHAR(15)  NULL,
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

-- Mensajes entrantes.
CREATE TABLE IF NOT EXISTS com_entrantes (
  id                  BIGINT AUTO_INCREMENT PRIMARY KEY,
  proveedor           VARCHAR(15)  NOT NULL DEFAULT 'twilio',
  proveedor_message_id VARCHAR(80) NOT NULL,
  canal               VARCHAR(12)  NOT NULL,
  de                  VARCHAR(160) NOT NULL,
  para                VARCHAR(160) NULL,
  asunto              VARCHAR(255) NULL,
  cuerpo              MEDIUMTEXT   NULL,
  media_json          TEXT         NULL,
  asociado_cedula     VARCHAR(20)  NULL,
  contacto_id         BIGINT       NULL,
  plan_id             INT          NULL,
  destinatario_id     BIGINT       NULL,
  leido               TINYINT(1)   NOT NULL DEFAULT 0,
  atendido_por        INT          NULL,
  fecha_proveedor     DATETIME     NULL,
  fecha_creacion      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_com_entrante_sid (proveedor, proveedor_message_id),
  INDEX idx_com_entrante_de (de),
  INDEX idx_com_entrante_fecha (fecha_creacion),
  INDEX idx_com_entrante_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resumen diario de Usage Records de Twilio.
CREATE TABLE IF NOT EXISTS com_uso_twilio (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  proveedor            VARCHAR(15)  NOT NULL DEFAULT 'twilio',
  fecha                DATE         NOT NULL,
  categoria            VARCHAR(40)  NOT NULL,
  cantidad             INT          NOT NULL DEFAULT 0,
  precio_usd           DECIMAL(14,6) NOT NULL DEFAULT 0,
  moneda               VARCHAR(3)   NOT NULL DEFAULT 'USD',
  payload_json         LONGTEXT     NULL,
  fecha_sincronizacion DATETIME     NULL,
  UNIQUE KEY uq_com_uso (proveedor, fecha, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parámetros del módulo (los secretos viven en .env).
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

-- Parámetros de crédito cooperativa para boletas (mismos defaults que ConfigService).
INSERT INTO app_configuracion (clave, valor, descripcion) VALUES
  ('boleta_credito_meses_min',   '6',      'Antigüedad mínima (meses) para crédito cooperativa en boletas'),
  ('boleta_credito_aportes_min', '6',      'N° mínimo de pagos de aportes ordinarios para crédito cooperativa'),
  ('boleta_credito_limite',      '100000', 'Límite en pesos del crédito cooperativa para boletas')
ON DUPLICATE KEY UPDATE clave = clave;

