# Multi App v2

Sistema de gestión de una **cooperativa financiera** (PHP 8.2 / MariaDB 11.8, sin framework) — reescritura de
`multi_app` (legacy). Reúne en una sola aplicación:

| Interfaz | Carpeta | Acceso |
|---|---|---|
| Panel de administración (staff) | `ui/` | sesión de staff |
| Portal del asociado | `cx/` | sesión de asociado (cédula + contraseña) |
| Tienda (catálogo público) | `tienda/` | público |
| Convenios (catálogo público) | `convenios/` | público |
| Simulador de crédito | `simulador/` | público |
| API JSON | `api/` | staff / portal / público según el endpoint |
| Lógica de negocio | `core/` | servicios reutilizables (API-first) |

> La política de migración y conservación de datos históricos está en
> [`migracion_datos.md`](./migracion_datos.md) y el cronograma de salida en vivo (cutover
> v1 → v2) en [`plan_migracion_v1_a_v2.md`](./plan_migracion_v1_a_v2.md).

---

## 1. Stack y reglas duras

- **PHP 8.2**, **MariaDB 11.8**, **Bootstrap 5**, tipografía **Poppins**.
- Colores corporativos: `#1882CD` · `#11468A` · `#FCD329` · `#EB3031`.
- **Sin llaves foráneas** en la BD (relaciones en comentarios `-- FK:`).
- **Sin ENUM** (valores posibles en comentarios `-- valores:`).
- **Prefijo de módulo** en tablas (`solicitudes_*`, `sifone_*`, `pagos_*`, `transacciones_*`, `tienda_*`, …).
- **`sql/ddl.sql`** = esquema canónico único (+ `reset_db.sql` y `sql/migrations/`).
- **Secretos en `.env`** (nunca en código ni versionados). Roles/permisos en `ui/config/secciones.php`.
- **Uploads con nombre único**, servidos **solo por API autenticada** (id opaco + verificación de dueño) — nunca ruta directa.
- **API-first**: la lógica vive en `core/` (servicios) y se expone por endpoints JSON; las páginas solo renderizan.
- **Cero pérdida** de datos históricos + trazabilidad id-antiguo ↔ id-nuevo en la migración (ver `migracion_datos.md`).
- Patrón estándar de listados: paginación 20, orden con **whitelist en el servicio** (nunca input directo a `ORDER BY`),
  filtros y total `Mostrando X-Y de Z`.

---

## 2. Estructura del proyecto

```
multi/
├── core/                 # servicios compartidos (Auth, Http, Logger, Permisos, Storage, ...)
│   ├── asociados/  cobranza/  convenios/  creditos/  Fondo/  movimientos/  pagos/
│   ├── portal/     productos/  solicitudes/  tienda/  transacciones/  Email/
│   └── Auth.php · Http.php · Logger.php · Permisos.php · Csrf.php · database.php · env.php
├── api/                  # endpoints JSON (staff / portal / público)
│   ├── solicitudes/  asociados/  creditos/  cobranza/  pagos/  transacciones/  ...
│   ├── tienda/  convenios/  boletas/  fondo_bienestar/  portal/
│   ├── tienda_publico.php · convenios_publico.php · simulacion.php · simulacion_pdf.php
├── ui/                   # interfaz staff  (login, dashboard, modules/*)
├── cx/                   # portal del asociado (login, modules/*)
├── tienda/ · convenios/ · simulador/   # superficies públicas
├── assets/               # css/js/img/favicons compartidos
├── sql/                  # ddl.sql (canónico), migrations/  (dumps/ = local, ignorado)
├── utils/                # vendor (tcpdf, r2min, simplexls/simplexlsx) + ExcelGenerator
└── uploads/              # archivos (NUNCA servidos por URL directa)
```

### Módulos staff (`ui/modules/`)

Usuarios · Asociados · Cargas (Sifone) · Configuración (movimientos/cobranza/productos) · Movimientos ·
Créditos · Cobranza · **Comunicaciones (WhatsApp / SMS / email)** · Logs · Transacciones/Pagos · Tienda ·
Convenios/Boletería (stock) · Solicitudes (Tickets) · Fondo de Bienestar (convenios) · Oficina.

### Comunicaciones (Twilio + SMTP)

Módulo `ui/modules/comunicaciones/` y API `api/comunicaciones/`:

- Envío a un asociado, a varios asociados con opt-in o a una base CSV/XLS/XLSX, con validación de teléfono/correo y deduplicación.
- Opt-in maestro por asociado en `asociados_control`, administrable desde el detalle del asociado (`ui/modules/asociados/pages/asociados_detalle`).
- Asistente IA opcional para redactar mensajes o HTML en el paso 2 de la nueva comunicación (usa `SILICONFLOW_*`).
- Planes programados (zona horaria `America/Bogota`, almacenados en UTC).
- WhatsApp (plantillas Twilio Content, imágenes/PDF/audio/video), SMS (texto) y email por **SMTP institucional** (PHPMailer). SendGrid queda como integración opcional futura.
- Webhooks de estado, respuestas entrantes y opt-out (`STOP`, `BAJA`, etc.).
- Registro de estados, errores, SID de proveedor y costo real en USD.

Variables y conexión: ver `.env.example` (`TWILIO_*`, `SMTP_*`, `SILICONFLOW_*`, `COM_MEDIA_SECRET`).
Los secretos nunca se guardan en la base. En trial, WhatsApp requiere sandbox o sender aprobado y los SMS
solo llegan a números verificados.

Migraciones: `015_comunicaciones_twilio.sql` (módulo), `016_asociados_comunicaciones_opt_in.sql`
(opt-in maestro en `asociados_control`) y `017_comunicaciones_ia_costo.sql` (costo estimado de IA por plan).

Worker (Hostinger, cada 15 minutos):

```cron
*/15 * * * * /usr/bin/php /home/USUARIO/domains/DOMINIO/core/comunicaciones/worker.php --limit=200 >> /home/USUARIO/logs/comunicaciones.log 2>&1
*/15 * * * * /usr/bin/php /home/USUARIO/domains/DOMINIO/core/comunicaciones/sync.php >> /home/USUARIO/logs/comunicaciones_sync.log 2>&1
```

Webhooks públicos (configurar el sender/Messaging Service en Twilio):

- `https://DOMINIO/api/comunicaciones/webhooks/status.php`
- `https://DOMINIO/api/comunicaciones/webhooks/inbound.php`
- Email SendGrid (opcional futuro): `https://DOMINIO/api/comunicaciones/webhooks/sendgrid.php?k=SECRETO`

La firma `X-Twilio-Signature` se valida con `TWILIO_AUTH_TOKEN`. Para adjuntos y baja de email se usan
URLs firmadas con `COM_MEDIA_SECRET`.

### Portal (`cx/modules/`)

Perfil · Aportes (resumen/extractos/movimientos/retiro) · Créditos (activos + solicitud) · Solicitudes
(hub: PQRS, actualizar info, boletas, redención, certificados, mis solicitudes) · Fondo de Bienestar ·
Convenios (carrito de boletas) · Notificaciones · Asistente IA (chat).

---

## 3. Entorno y comandos

### Windows (XAMPP local — validado)

Se usa **MariaDB 11.8.9 como instancia separada** (para igualar la versión de producción), dejando la MariaDB 10.4
de XAMPP intacta para el resto de proyectos. Se extrajo en `C:\xampp\mariadb-11.8.9-winx64`, datadir `data/`, puerto **3307**.

```powershell
# 1) Iniciar MariaDB 11.8.9 (si no está corriendo)
Start-Process "C:\xampp\mariadb-11.8.9-winx64\bin\mysqld.exe" -ArgumentList "--basedir=C:\xampp\mariadb-11.8.9-winx64","--datadir=C:\xampp\mariadb-11.8.9-winx64\data","--port=3307" -WindowStyle Hidden

# 2) Conectar
C:\xampp\mariadb-11.8.9-winx64\bin\mysql.exe --port=3307 -u root

# 3) Crear/cargar el esquema canónico (BD = multi_app_v2)
C:\xampp\mariadb-11.8.9-winx64\bin\mysql.exe --port=3307 -u root -e "CREATE DATABASE IF NOT EXISTS multi_app_v2 CHARACTER SET utf8mb4"
cmd /c "C:\xampp\mariadb-11.8.9-winx64\bin\mysql.exe --port=3307 -u root multi_app_v2 < sql\ddl.sql"
```

El `.env` local ya apunta a `DB_HOST=127.0.0.1` / `DB_PORT=3307` (ver `.env.example` para todas las variables).

### Linux (XAMPP)

```bash
MYSQL=/opt/lampp/bin/mysql
SOCK=/opt/lampp/var/mysql/mysql.sock
$MYSQL -u root --socket=$SOCK -e "DROP DATABASE IF EXISTS multi_app_v2; CREATE DATABASE multi_app_v2 CHARACTER SET utf8mb4;"
$MYSQL -u root --socket=$SOCK multi_app_v2 < sql/ddl.sql
```

### Servir la app

Con Apache de XAMPP (URLs limpias vía `.htaccess`, sin `.php`):

- Hub: `http://localhost/projects/multi/multi/`
- Staff: `http://localhost/projects/multi/multi/ui/login`
- Portal: `http://localhost/projects/multi/multi/cx/login`
- Tienda: `http://localhost/projects/multi/multi/tienda/`
- Convenios: `http://localhost/projects/multi/multi/convenios/`
- Simulador: `http://localhost/projects/multi/multi/simulador/`

El portal CX usa URLs amigables (tabla única en `cx/routes.php`), por ejemplo
`/cx/aportes`, `/cx/solicitudes/mis`, `/cx/solicitar-credito`. Las rutas largas
antiguas (`/cx/modules/.../pages/...`) siguen funcionando sin cambios.

O con el servidor embebido (sin Apache):

```bash
php -S 127.0.0.1:8099 -t .     # luego http://127.0.0.1:8099/ui/login
```

### Credenciales de desarrollo

- **Staff**: `admin` / `Admin123*` (contraseña de un solo uso; pide cambiarla al primer ingreso).
- **Portal**: un asociado con cédula y contraseña (hashes bcrypt en `asociados_control`). Si un asociado histórico
  tiene `password_hash = NULL`, el primer acceso pasa por el flujo `password_crear` (código de 6 dígitos por email).

### Migración de datos (legacy → v2)

La política y el mapeo legacy → v2 están en [`migracion_datos.md`](./migracion_datos.md).

- El **esquema** se crea con `sql/ddl.sql` (canónico, ver §3).
- El **migrador** es `sql/migrar_v1_a_v2.py` (Python). Migra los datos base + la **fábrica de créditos**;
  **las solicitudes (PQRS/tickets) no se migran** (se crean desde cero en v2).
- Los **dumps legacy con datos reales NO se versionan**: colócalos en `sql/dumps/` (ignorado por git) solo en tu
  entorno local para la migración.
- Los datos **Sifone** (asociados, datacrédito, movimientos tributarios, etc.) se cargan desde los archivos reales
  por **UI › Cargas**.

```bash
python sql/migrar_v1_a_v2.py --reset                     # todo
python sql/migrar_v1_a_v2.py --only=creditos --reset     # solo la fábrica de créditos
```

---

## 4. Autenticación y sesiones

- **Staff** (`ui/`): sesión `multiappv2_staff` (cookie) vía `core/Auth.php`. Permisos por `ui_guard('modulo')`,
  `ui_accion('pagina','operacion')` y `Auth::requireModulo(...)` / `Http::crud(...)` en el API.
- **Portal** (`cx/`): sesión `multiappv2_portal` vía `core/portal/PortalAuth.php`. Identidad = cédula del asociado;
  **toda consulta se filtra por esa cédula** (nunca se confía en un parámetro del cliente para la identidad).
- **CSRF**: `Http::csrf()` es no-op en GET y exige token (`X-CSRF-Token` o campo `_csrf`) en mutadores POST/PUT/DELETE.
- **Seguridad de archivos**: subidas con whitelist de extensión/MIME y tamaño; servidas por API autenticada con
  re-verificación de dueño y contención anti-traversal. MIME derivado de la extensión (nunca del cliente).

---

## 5. Permisos y RBAC (fuente única: `ui/config/secciones.php`)

El sistema de permisos vive **completo en un solo archivo**: `ui/config/secciones.php`, con tres capas.

### 1) `modulos` — acceso a página / menú / API (por prefijo)

```php
'modulos' => [
    'admin'   => ['*'],                       // súper-rol: TODO
    'lider'   => ['oficina', 'cobranza', 'creditos', 'tienda', 'solicitudes', 'usuarios.gestion', ...],
    'oficina' => ['oficina', 'cobranza', 'creditos', 'tienda', 'solicitudes', ...],
    'tienda'  => ['tienda', 'tienda.ventas', 'convenios.admin', 'oficina.asociados', ...],
    'cobranza_juridica' => ['cobranza.creditos_juridico'],
],
```

- El permiso `'oficina'` habilita `'oficina.asociados'`, `'oficina.descargas'`, etc. (coincidencia por prefijo).
- Lo usan: `ui_guard('modulo')` (puerta de cada página), el sidebar y `Auth::requireModulo` (backend).
- Verificador: `Permisos::puede($rol, $modulo)`.

### 2) `secciones` + `pantallas` — visibilidad de bloques **dentro** de una página

```php
'pantallas' => [
    'asociados.detalle' => [
        'modulo' => 'oficina.asociados',
        'strict' => true,                       // deny-by-default en esta página
        'secciones' => ['datos_personales', 'saldos', 'creditos', 'productos', 'cobranza'],
    ],
],
'secciones' => [
    'tienda' => ['asociados.detalle.cobranza' => false],
],
```

- En páginas `strict => true` el default es **invisible** (hay que otorgar); en las demás se hereda el módulo.
- Helpers: `ui_seccion('pagina','seccion')` / `ui_secciones('pagina')` (UI) y
  `Permisos::seccion($rol,$pagina,$seccion)` / `Permisos::seccionesDeRol($rol,$pagina)`.
- Son **solo visibilidad de la UI** (cards/bloques); no autentican escritura.

### 3) `acciones` — operaciones CRUD por rol, **reforzadas en el API**

```php
'acciones' => [
    'admin' => ['*' => true],                          // súper-rol: todas las operaciones
    'lider' => ['creditos.config_lineas.crear' => true, 'tienda.ventas.crear' => true, ...],
    'tienda' => [
        'tienda.ventas.crear'    => true,
        'tienda.ventas.eliminar' => false,             // deny explícito
    ],
],
```

- Clave `<pagina>.<operacion>` con verbos estándar: `crear|editar|eliminar|toggle|exportar|asignar|procesar|…`.
- **Deny-by-default**: si la clave no está otorgada al rol, ese rol **no puede ejecutar la operación** ni ve su botón.
- UI: `ui_accion('pagina','operacion')` / `ui_acciones('pagina')` (gate de botones CRUD).
- API: `Http::crud('pagina','operacion')` (deriva `POST→crear`, `PUT→editar`, `DELETE→eliminar`, o `$op` explícito)
  → `Auth::requireAccion('<pagina>.<operacion>')` devuelve **401/403 reales**.
- Verificador: `Permisos::accion($rol, $clave)` (comodines `'*'` y `'<pagina>.*'`).

### `admin` — súper-rol **declarado en config** (no hardcodeado)

```php
'modulos'   => ['admin' => ['*']],
'secciones' => ['admin' => ['*' => true]],
'acciones'  => ['admin' => ['*' => true]],
```

No hay `if ($rol === 'admin') return true;` en ningún lado: `core/Permisos.php` resuelve todo desde la config.

### Roles

| Rol | Alcance |
|---|---|
| `admin` | Super-rol (todo por config: `['*']`). |
| `lider` | Acceso amplio: oficina, cobranza, créditos, tienda, solicitudes, usuarios, logs, sistema. |
| `oficina` | Operación diaria (tienda, créditos, cobranza, solicitudes, descargas) **sin** usuarios/logs/sistema. |
| `tienda` | Módulos de tienda + convenios/boletas + consulta de asociados; sin escribir en créditos/cobranza/pagos. |
| `cobranza_juridica` | Solo `cobranza.creditos_juridico`. |

Regla de negocio en `core/UsuarioService.php`: un `lider` **no** gestiona usuarios `admin`/`lider`; `admin` gestiona todos.

### Cómo agregar algo nuevo

1. **Módulo** → agregarlo a `modulos` de cada rol + ítem del sidebar (`ui/views/layouts/sidebar.php`, `perm`).
2. **Sección de una página** → declararla en `pantallas` (`modulo`, `strict`, `secciones`) y cablearla en la
   página con `ui_secciones()` + `ui_seccion()`; otorgar en `secciones` (si la página es `strict`).
3. **Operación CRUD** → otorgarla en `acciones` al rol, gatear el botón con `ui_accion()` y añadir
   `Http::crud('pagina','operacion')` al endpoint. Sin el `Http::crud`, un `curl` puede saltar el botón.
