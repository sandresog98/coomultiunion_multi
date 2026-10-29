# Optimización de la ficha de asociado

**Página:** `ui/modules/asociados/pages/asociados_detalle?cedula=...`  
**Última actualización:** 2026-10-28  
**Alcance:** rendimiento de carga, reducción de llamadas API, eliminación de N+1, bloqueo de sesión PHP e índices de base de datos.

> Para las optimizaciones del resto de módulos, ver [`optimizacion_app.md`](./optimizacion_app.md).

---

## 1. Objetivo

La ficha de asociado se percibía lenta y de forma intermitente mostraba un `503 Service Unavailable` en `api/cobranza/comunicaciones`.

El objetivo de este trabajo fue:

- Reducir el número de peticiones necesarias para pintar la ficha.
- Evitar que una API lenta bloqueara a las demás.
- Eliminar consultas repetidas por cada fila (N+1).
- Acortar el tiempo de espera cuando la base de datos no responde.
- Agregar índices para las consultas más frecuentes.
- Mantener compatibilidad con los endpoints existentes y con la arquitectura API-first del proyecto.

---

## 2. Diagnóstico inicial

Antes de los cambios, la página hacía aproximadamente:

### 2.1. Renderizado PHP

`AsociadoService::ficha()` ejecutaba 4 consultas:

1. `vw_asociados`
2. `vw_productos`
3. `vw_creditos`
4. `asociados_control`

### 2.2. Llamadas API al cargar

| # | Endpoint | Uso |
|---|---|---|
| 1 | `productos/listar` | Catálogo para el modal “Asignar” |
| 2 | `productos/asignaciones` | Tabla de productos asignados |
| 3 | `asociados/movimientos` | Tabla de movimientos |
| 4 | `cobranza/compromisos` | Historial de compromisos |
| 5 | `cobranza/comunicaciones` | Historial de comunicaciones |
| 6 | `cobranza/indagaciones` | Historial de indagaciones |
| 7 | `cobranza/tipos?opciones=1` | Catálogo para modales de cobranza |

Eran **7 llamadas API** más el request HTML, con alrededor de 18 sentencias SQL y 8 conexiones a base de datos.

### 2.3. Problemas encontrados

1. **Bloqueo de sesión PHP:** `Auth::boot()` abría la sesión y nunca la cerraba. El manejador de sesiones por archivos bloquea el archivo de sesión durante toda la petición, por lo que las APIs se ejecutaban una detrás de otra aunque el navegador las lanzara en paralelo.
2. **Reintentos largos:** la capa de BD reintentaba 4 veces y el frontend otras 3. Un fallo de BD podía tardar decenas de segundos.
3. **N+1 en compromisos:** `ComunicacionService::historialCompromisos()` hacía una consulta `SUM` por cada compromiso.
4. **N+1 en indagaciones:** una consulta por cada indagación para traer sus adjuntos.
5. **N+1 en movimientos:** una consulta por cada transacción para traer sus detalles.
6. **N+1 en cláusulas:** una consulta por cada cláusula para contar sus pagos.
7. **Llamadas innecesarias:** catálogo de productos y tipos de comunicación se pedían al cargar aunque solo se usan al abrir modales.
8. **Trabajo duplicado:** `compromisos` es un subconjunto de `comunicaciones`; se consultaban dos veces los mismos datos.
9. **Vistas pesadas:** `vw_productos` ejecuta varias veces `vw_creditos`, y `vw_creditos` hace joins con `CAST(... AS UNSIGNED)` que impiden usar índices sobre `sifone_datacredito`.
10. **503 real de infraestructura:** el log de Apache mostraba `SQLSTATE[HY000] [2002] ...` y el servicio de MariaDB del puerto 3307 se estaba deteniendo/reiniciando. El 503 no era causado por el endpoint de comunicaciones.

---

## 3. Resumen de cambios

| Área | Cambio |
|---|---|
| Sesión | Se libera el lock de sesión en APIs de solo lectura |
| Frontend | Carga diferida de movimientos, cobranza y catálogos |
| Frontend | Timeout y reintentos más acotados |
| Frontend | Cache local de asignaciones y catálogos |
| API | Nuevo endpoint `GET /api/cobranza/historial` |
| API | `productos/listar?basico=1` sin campo `parametros` |
| Servicios | Eliminación de N+1 en compromisos, indagaciones, movimientos y cláusulas |
| BD | Migración `019_perf_asociados_detalle.sql` con índices compuestos |
| Documentación | Nuevo endpoint agregado a `api-docs/openapi.yaml` |

---

## 4. Cambios detallados

### 4.1. Liberación del bloqueo de sesión

**Archivo:** `core/Auth.php`

Se agregó:

- `$staffCache`: caché del staff autenticado durante la petición.
- `$staffResolved`: indica que el staff ya se resolvió.
- `$sessionReleased`: evita que `Auth::boot()` reabra la sesión después de liberarla.
- `Auth::releaseSession()`: ejecuta `session_write_close()`.

La liberación se hace al final de:

- `Auth::requireModulo()`
- `Auth::requireAccion()`

Siempre después de `Http::csrf()`.

**Importante:** `releaseSession()` solo actúa en `GET` y `HEAD`. Las mutaciones (`POST`, `PUT`, `DELETE`) conservan la sesión para no perder el contexto de CSRF. Además, `Auth::staff()` usa caché para que un `Auth::actor()` posterior no vuelva a abrir la sesión.

**Resultado:** las llamadas GET concurrentes de la ficha dejan de serializarse.

**Nota de mantenimiento:** si en el futuro un endpoint GET necesita escribir en `$_SESSION` después de la autenticación, debe revisarse antes de usar este mecanismo.

---

### 4.2. Carga diferida y desacople en el frontend

**Archivo:** `ui/modules/asociados/pages/asociados_detalle.php`

Cambios principales:

1. **Asignaciones independientes del catálogo**
   - Antes `cargarProductos()` pedía el catálogo y luego las asignaciones.
   - Ahora `cargarAsignaciones()` se ejecuta sola al cargar la página.
   - El catálogo se carga únicamente en `cargarCatalogoProductos()`, al abrir el modal “Asignar”.

2. **Catálogo de productos con caché**
   - `catalogoProductosCargado` evita volver a pedirlo.
   - `catalogoProductosPromise` evita peticiones duplicadas si se hace doble clic.

3. **Asignaciones con caché**
   - `asignacionesActivas` conserva las filas cargadas.
   - “Editar” y “Toggle” usan esa caché y no vuelven a pedir toda la lista.

4. **Carga por visibilidad**
   - Se agregó `cargarAlVisible(el, fn)` con `IntersectionObserver`.
   - Movimientos y cobranza se cargan cuando su tarjeta entra en pantalla, con un margen de 200 px.
   - Si el navegador no soporta `IntersectionObserver`, se cargan de inmediato.

5. **Tipos de comunicación bajo demanda**
   - `cobCargarTipos()` ya no se ejecuta al cargar la página.
   - Se ejecuta al abrir “Nueva comunicación”, “Nuevo compromiso” o “Editar comunicación”.
   - Tiene caché y control de petición en curso.

6. **Timeout y reintentos**
   - `api()` ahora usa `AbortController`.
   - GET: timeout de 12 segundos.
   - Mutaciones: timeout de 30 segundos.
   - Máximo 2 intentos totales (1 reintento).
   - Si se aborta por timeout, no se reintenta y se muestra un error claro.

7. **Cobranza en una sola petición**
   - `cargarCobranza()` consume `cobranza/historial`.
   - `cobRenderTodo()` pinta los tres historiales con la respuesta unificada.

---

### 4.3. Nuevo endpoint de historial de cobranza

**Archivo:** `api/cobranza/historial.php`

```http
GET /api/cobranza/historial?cedula=0020928185
```

Respuesta:

```json
{
  "ok": true,
  "data": {
    "comunicaciones": [],
    "indagaciones": [],
    "compromisos": []
  }
}
```

- Reutiliza `ComunicacionService` e `IndagacionService`.
- Comparte una sola conexión PDO y un solo bootstrap de autenticación.
- Mantiene los endpoints individuales existentes para otras pantallas o usos.
- Requiere el módulo `cobranza`, igual que los endpoints anteriores.
- Está documentado en `api-docs/openapi.yaml`.

**Ventaja:** reduce de 3 peticiones a 1 para el bloque de cobranza y evita tres reintentos simultáneos cuando la BD falla.

---

### 4.4. Eliminación de N+1

#### Compromisos

**Archivo:** `core/cobranza/ComunicacionService.php`

Antes:

```php
foreach ($rows as &$r) {
    $suma = $this->sumaPagosRango($cedula, $r['fecha'], $r['fecha_compromiso']);
}
```

Ahora la suma se calcula dentro de la misma consulta SQL mediante una subconsulta correlacionada y se devuelve como `suma_pagado`. El cálculo de `estado_compromiso` se mantiene en PHP.

#### Indagaciones

**Archivo:** `core/cobranza/IndagacionService.php`

Los adjuntos se traen con una sola consulta:

```sql
WHERE indagacion_id IN (?, ?, ...)
```

Luego se agrupan en PHP por `indagacion_id`.

#### Movimientos

**Archivo:** `core/transacciones/TransaccionService.php`

Los detalles se traen con una sola consulta:

```sql
WHERE d.transaccion_id IN (?, ?, ...)
```

También se reemplazó:

```sql
DATE(fecha_creacion) >= ?
```

por rangos de fecha que permiten usar índices:

```sql
fecha_creacion >= ?  -- 'YYYY-MM-DD 00:00:00'
fecha_creacion <= ?  -- 'YYYY-MM-DD 23:59:59'
```

#### Cláusulas de productos

**Archivo:** `core/productos/ProductoService.php`

Antes se ejecutaba una consulta por cada cláusula para contar aportes o pagos de producto.

Ahora:

- Se recopilan los rangos de todas las cláusulas.
- Se hace una consulta en bloque para aportes, solo si hay cláusulas de aportes.
- Se hace una consulta en bloque para pagos por producto, solo si hay cláusulas de producto.
- El conteo de meses se termina en PHP respetando el rango de cada cláusula.

Se validó contra la lógica anterior y los resultados coinciden.

---

### 4.5. Catálogo de productos más liviano

**Archivos:**

- `core/productos/ProductoService.php`
- `api/productos/listar.php`

`listarTiposActivos()` ahora acepta `bool $basico = false`.

- `GET /api/productos/listar` conserva el comportamiento anterior, incluyendo `parametros`.
- `GET /api/productos/listar?basico=1` omite `parametros`, que puede ser un texto largo y que la ficha no utiliza.

La pantalla de configuración sigue usando el endpoint completo.

---

### 4.6. Índices de base de datos

**Archivo:** `sql/migrations/019_perf_asociados_detalle.sql`

La migración es idempotente y agrega:

| Índice | Tabla | Columnas |
|---|---|---|
| `idx_trx_cedula_fecha` | `transacciones` | `cedula, fecha_creacion` |
| `idx_cc_cedula_fecha` | `cobranza_comunicaciones` | `asociado_cedula, fecha, id` |
| `idx_ci_cedula_fecha` | `cobranza_indagaciones` | `asociado_cedula, fecha, id` |
| `idx_ap_cedula_estado_prod` | `asociados_productos` | `cedula, estado_activo, producto_id` |
| `idx_sbp_cedula_nombre` | `sifone_balance_prueba` | `cedula, nombre` |
| `idx_smt_cedula_cuenta_fecha` | `sifone_movimientos_tributarios` | `cedula, cuenta, fecham` |

`sql/ddl.sql` no se modificó porque es la línea base congelada del proyecto.

---

## 5. Archivos modificados

```text
core/Auth.php
core/productos/ProductoService.php
core/cobranza/ComunicacionService.php
core/cobranza/IndagacionService.php
core/transacciones/TransaccionService.php
api/productos/listar.php
api/cobranza/historial.php                  (nuevo)
ui/modules/asociados/pages/asociados_detalle.php
sql/migrations/019_perf_asociados_detalle.sql (nuevo)
sql/migrations/README.md
api-docs/openapi.yaml
```

---

## 6. Flujo de carga actual

### Carga inicial

1. Renderizado PHP de la ficha (datos personales, saldos y créditos).
2. `GET /api/productos/asignaciones?cedula=...`
3. Si la tarjeta de movimientos entra en pantalla:
   `GET /api/asociados/movimientos?...`
4. Si la tarjeta de cobranza entra en pantalla:
   `GET /api/cobranza/historial?cedula=...`

### Modales

- “Asignar”: carga `GET /api/productos/listar?basico=1` la primera vez.
- “Nueva comunicación” / “Nuevo compromiso” / “Editar comunicación”:
  carga `GET /api/cobranza/tipos?opciones=1` la primera vez.

### Después de crear/editar/eliminar

- Productos: se recarga la tabla de asignaciones.
- Comunicaciones, compromisos e indagaciones: se recarga una sola vez el historial unificado.

---

## 7. Despliegue

### 7.1. Aplicar la migración

Aplicar antes o después de desplegar el código; es aditiva y no rompe el código anterior. Ejecutar desde la raíz del proyecto:

```bash
mysql -u USUARIO -p -D BASE_DE_DATOS -e "source sql/migrations/019_perf_asociados_detalle.sql"
```

En XAMPP local:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -h 127.0.0.1 -P 3307 -D multi_app_v2 `
  -e "source C:/xampp/htdocs/projects/multi/multi/sql/migrations/019_perf_asociados_detalle.sql"
```

Verificar que los índices existan:

```sql
SHOW INDEX FROM transacciones WHERE Key_name = 'idx_trx_cedula_fecha';
SHOW INDEX FROM cobranza_comunicaciones WHERE Key_name = 'idx_cc_cedula_fecha';
SHOW INDEX FROM sifone_balance_prueba WHERE Key_name = 'idx_sbp_cedula_nombre';
```

### 7.2. Desplegar código

Archivos indicados en la sección 5.

Si el servidor usa OPcache, reiniciar PHP/Apache o limpiar la caché para que tome los cambios.

### 7.3. Validar el endpoint nuevo

```bash
curl -b "multiappv2_staff=TU_SESION" \
  "http://localhost/projects/multi/multi/api/cobranza/historial?cedula=0020928185"
```

Debe responder `200` con las claves `comunicaciones`, `indagaciones` y `compromisos`.

---

## 8. Verificación funcional

### 8.1. En el navegador

Con la pestaña Network de DevTools:

1. Abrir la ficha de un asociado conocido.
2. Confirmar que al cargar solo aparece `productos/asignaciones` (más el HTML).
3. Confirmar que `productos/listar?basico=1` **no** aparece hasta abrir “Asignar”.
4. Confirmar que `cobranza/tipos?opciones=1` **no** aparece hasta abrir un modal de cobranza.
5. Bajar hasta movimientos y cobranza; allí deben aparecer:
   - `asociados/movimientos`
   - `cobranza/historial`
6. Confirmar que las peticiones visibles se solapan en el tiempo (no una detrás de otra).

### 8.2. Acciones a probar

- Asignar un producto o cláusula.
- Editar una asignación.
- Activar/inactivar una asignación.
- Crear una comunicación.
- Crear un compromiso.
- Editar y eliminar una comunicación.
- Crear una indagación con adjunto.
- Filtrar movimientos por origen y fechas.
- Paginar movimientos y los historiales de cobranza.

### 8.3. Pruebas ejecutadas

- PHP lint en todos los archivos modificados: correcto.
- Validación de sintaxis JavaScript de la página: correcta.
- Validación de `openapi.yaml`: correcta (60 rutas).
- Smoke test HTTP de la página y endpoints: `200`.
- Comparación de resultados contra la lógica anterior:
  - 38 cláusulas comparadas, 0 diferencias.
  - 227 compromisos comparados, 0 diferencias.
  - 3 indagaciones con adjuntos, 0 diferencias.
- Prueba de liberación de sesión: la sesión queda cerrada en GET y el staff se recupera desde caché sin reabrirla.
- Página completa sin warnings ni errores PHP.

> Las mediciones de tiempo locales no sustituyen una prueba de carga en producción.

---

## 9. Diagnóstico del 503

El error original:

```text
GET /api/cobranza/comunicaciones?cedula=... 503 (Service Unavailable)
```

no era causado por la consulta de comunicaciones. En `C:\xampp\apache\logs\error.log` aparecía:

```text
[cobranza-api] DB no disponible: SQLSTATE[HY000] [2002]
No se puede establecer una conexión...
```

y el log de MariaDB mostraba paradas/reinicios del servicio del puerto 3307.

Para diagnosticarlo:

1. Probar la salud de la BD:

   ```text
   GET /api/ping.php?db=1
   ```

2. Revisar el log de Apache:

   ```powershell
   Get-Content C:\xampp\apache\logs\error.log -Tail 50
   ```

3. Revisar el estado del servicio:

   ```powershell
   Get-Service mysql
   ```

4. Revisar el log de MariaDB:

   ```text
   C:\xampp\mariadb-11.8.9-winx64\data\DESKTOP-KI3TM42.err
   ```

**Nota:** los cambios de código no corrigen un servicio de BD que se detiene. Lo que hacen es evitar que ese fallo bloquee toda la página y reducir el tiempo de espera.

---

## 10. Rollback

Si fuera necesario revertir:

1. Revertir los archivos de la sección 5.
2. Opcionalmente, eliminar los índices nuevos:

```sql
DROP INDEX idx_trx_cedula_fecha ON transacciones;
DROP INDEX idx_cc_cedula_fecha ON cobranza_comunicaciones;
DROP INDEX idx_ci_cedula_fecha ON cobranza_indagaciones;
DROP INDEX idx_ap_cedula_estado_prod ON asociados_productos;
DROP INDEX idx_sbp_cedula_nombre ON sifone_balance_prueba;
DROP INDEX idx_smt_cedula_cuenta_fecha ON sifone_movimientos_tributarios;
```

3. El endpoint `cobranza/historial` puede quedar sin uso sin afectar a los endpoints anteriores.

---

## 11. Pendientes y siguientes optimizaciones

### Base de datos

- Reescribir `vw_creditos` para evitar los `CAST(... AS UNSIGNED)` en los joins con `sifone_datacredito`.
- Hacer que `vw_productos` calcule `vw_creditos` una sola vez en lugar de cuatro.
- Evaluar una tabla resumen `asociados_resumen` si el volumen de producción crece.
- Revisar `MAX`/`SUM` repetidos dentro de `vw_productos`.

### API

- Evaluar paginación del lado del servidor para los historiales de cobranza; actualmente se traen hasta 50/100 filas y se pagina en el cliente.
- Evaluar un endpoint agregado de toda la actividad de la ficha si se quiere reducir aún más el número de conexiones.

### Infraestructura

- Identificar por qué el servicio MariaDB del puerto 3307 se detiene/reinicia.
- Evaluar reducir o hacer configurables los reintentos de conexión de `core/database.php` (hoy son 4); el frontend ya corta a los 12 segundos.
- Activar OPcache en `php.ini`.
- Activar `mod_deflate` para HTML, JSON, CSS y JS.
- Revisar `max_connections` y el número de conexiones por usuario si la aplicación crece.

### Operación

- Agregar monitoreo periódico de `/api/ping.php?db=1`.
- Revisar el log de Apache después de cada despliegue.

---

## 12. Notas de mantenimiento

- `sql/ddl.sql` está congelado. Cualquier cambio de esquema debe ir en `sql/migrations/` con numeración consecutiva.
- Los endpoints individuales de cobranza siguen existiendo; el nuevo `historial` es una optimización para la ficha, no un reemplazo obligatorio.
- `productos/listar?basico=1` es una variante optimizada; la pantalla de configuración debe seguir usando `productos/listar` sin parámetros.
- Si se agrega una nueva sección a la ficha, evaluar si debe cargarse de inmediato o mediante `cargarAlVisible()`.
- Si se agrega un endpoint GET que necesite escribir en sesión, revisar la interacción con `Auth::releaseSession()`.
