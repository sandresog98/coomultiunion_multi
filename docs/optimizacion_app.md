# Optimización transversal de la aplicación

**Última actualización:** 2026-10-28  
**Alcance:** Portal, Cobranza, Tienda, Créditos, Solicitudes, Comunicaciones, Convenios/Stock, Pagos, Movimientos, Logs y vistas de base de datos.

---

## 1. Resumen

Se implementaron las mejoras detectadas en la auditoría de toda la aplicación:

- Liberación del lock de sesión en las APIs del Portal.
- Cliente API frontend compartido con timeout y reintentos.
- Carga diferida y paralelización de peticiones en varias pantallas.
- Eliminación de N+1 en Portal, Tienda, Cobranza, Créditos, Solicitudes, Comunicaciones y Stock.
- Paginación, filtros y ordenamiento en SQL para la grilla de Cobranza.
- Índices compuestos y filtros de fecha sargables.
- Normalización de claves de Sifone y reescritura de vistas pesadas.
- Carga masiva de contactos, destinatarios, boletas y eventos por lotes.

---

## 2. Fase 0 — Fundaciones

### 2.1. Sesión del Portal

**Archivo:** `core/portal/PortalAuth.php`

Se replicó el patrón ya usado en `Auth`:

- Caché del actor del portal durante la petición.
- `releaseSession()` con `session_write_close()` en GET/HEAD.
- Las mutaciones conservan la sesión para no perder el contexto CSRF.
- `actor()` usa caché para no reabrir la sesión después de liberarla.
- La búsqueda de cédula en login intenta primero coincidencias exactas y variantes con ceros; el `CAST` queda solo como fallback.

**Resultado:** las peticiones concurrentes del Portal dejan de serializarse.

### 2.2. Cliente API frontend compartido

**Archivo:** `assets/js/ui_helpers.js`

`Listado.api()` ahora:

- Acepta una base explícita (`opts.base`).
- Aplica timeout de 12 s para GET y 30 s para mutaciones.
- Reintenta una vez errores de red y 502/503/504.
- Maneja `AbortController` y muestra un error claro si se agota el tiempo.

Las páginas que tenían un `api()` local fueron migradas a este helper, incluyendo:

- Cobranza, Movimientos, Créditos, Solicitudes, Tienda, Convenios, Configuración, Usuarios, Logs, Chat y Transacciones.

### 2.3. Filtros de fecha sargables

Se reemplazaron filtros como:

```sql
DATE(columna) >= ?
```

por rangos:

```sql
columna >= ? AND columna <= ?
```

Aplicado en:

- `core/logs/LogService.php`
- `core/movimientos/MovimientoService.php`
- `core/pagos/PagosService.php`
- `core/convenios/ProductoStockService.php`
- `core/transacciones/TransaccionService.php` (`listarTodas`)
- `core/portal/PortalService.php` (`movimientos` por mes)

### 2.4. Índices transversales

**Migración:** `sql/migrations/020_perf_app_wide.sql`

Incluye índices para:

- `logs(timestamp)`
- `movimientos_multi(fecha)`, `(cedula, fecha)`, `(tipo, fecha)`
- `transacciones(fecha_creacion)`
- `pagos_pse(estado, fecha_resolucion)`
- `pagos_confiar(fecha)`
- `tienda_stock(producto_id, estado)`
- `tienda_ventas(fecha_venta)`
- `tienda_compras(fecha_compra)`
- `boleteria_boletas(producto_id, estado)`, `(estado, fecha_creacion)`, `(estado, fecha_vendida)`
- `convenios_productos(convenio_id, maneja_stock, activo)`
- `solicitudes(tipo_codigo, estado_codigo, fecha_creacion)`
- `credito_solicitudes(estado, etapa_actual, fecha_creacion)`

### 2.5. N+1 en transacciones y Portal

- `TransaccionService::listarTodas()` ahora trae los detalles con un solo `IN (...)`.
- `PortalService::movimientos()` trae todos los detalles en una consulta.
- `PortalService::convenios()` trae productos y stock en una sola consulta y agrupa en PHP.

### 2.6. Carga diferida en pantallas

- `cobranza.php`: los tipos de comunicación se cargan al abrir el modal.
- `movimientos.php`: la lista carga en paralelo con los tipos; el modal asegura los tipos.
- `tienda/catalogo.php`: categorías y marcas en paralelo.
- `comunicaciones/nueva.php`: remitentes y plantillas en paralelo.
- `convenios/stock.php`: catálogo, KPIs y listado se inician en paralelo; KPIs reutiliza el resumen del catálogo.
- `creditos/config_secciones.php`: conteos en una sola llamada; campos y documentos en paralelo.

---

## 3. Fase 1 — Cobranza

**Archivo:** `core/cobranza/CobranzaService.php`

Antes:

1. Ejecutaba el `UNION` completo sin `LIMIT`.
2. Traía todas las filas.
3. Calculaba compromisos de todas las cédulas.
4. Filtraba y ordenaba en PHP.
5. Hacía `array_slice`.

Ahora:

- Solo calcula la rama de crédito o aportes si hay filtro `tipo_mora`.
- Filtros `q`, banda, rango, remitidos y compromiso se aplican en SQL.
- El compromiso se calcula en una subconsulta con `ROW_NUMBER()` y `SUM`.
- El orden y la paginación se hacen en SQL con `LIMIT/OFFSET`.
- El total se obtiene con `COUNT(*)` sobre el conjunto filtrado.
- El auto-desremitir se aplica solo a las filas de la página.

**Resultado:** la grilla ya no carga todo el universo en cada página.

---

## 4. Fase 2 — Tienda

### 4.1. N+1 de detalle

**Archivo:** `core/tienda/TiendaService.php`

- `obtenerCompra()`: unidades por lote en una consulta.
- `detalleProductoStock()`: series por lote en una consulta.
- `obtenerVenta()`: unidades por línea en una consulta.

### 4.2. Listados con agregados

- `listarStock()` usa agregados por producto en lugar de subconsultas por fila.
- `listarVentas()` usa agregados de ítems, pagos y reversiones.
- `catalogoVenta()` usa un agregado de stock disponible y series.
- `CatalogoService` usa un agregado de stock por producto.
- `TiendaPublicoService::catalogo()` usa una tabla derivada de última compra con stock y un agregado de promociones.

### 4.3. Frontend

- Categorías y marcas se piden en paralelo.
- `stock.php` inicia catálogo, KPIs y listado en paralelo y evita el resumen duplicado.
- Se corrigió una declaración duplicada de `UI` en `tienda/pages/reversiones.php`.

---

## 5. Fase 3 — Créditos y Solicitudes

### 5.1. Configuración de créditos

**Archivo:** `core/creditos/CreditoConfigService.php`

- `renderSeccion()` y `renderSeccionesAll()` cargan campos y documentos de todas las secciones con dos consultas, no dos por sección.
- Nuevo método `seccionesConContadores()`.
- `GET /api/creditos/config?ent=secciones&conteos=1` devuelve `campos_count` y `documentos_count`.
- La pantalla de configuración dejó de hacer 2 peticiones por cada sección.

### 5.2. Solicitudes

**Archivos:** `core/solicitudes/SolicitudService.php`, `core/solicitudes/SolicitudRepository.php`

- `gateStockBoleta()` cuenta stock por producto en una sola consulta.
- Los ítems de boleta y vínculos se insertan por lotes.

---

## 6. Fase 4 — Comunicaciones y Convenios

### 6.1. Comunicaciones masivas

**Archivos:** `core/comunicaciones/ContactoService.php`, `core/comunicaciones/ComunicacionService.php`

Se agregaron:

- `upsertLote()`: upsert de contactos por bloques.
- `setOptInAsociadoLote()`: opt-in/opt-out maestro por lote.
- Inserción de `com_destinatarios` en bloques de 400.
- Los contactos nuevos se resuelven en bloque y se mapean por dirección normalizada.
- Las cédulas inexistentes se devuelven para el fallback individual.

**Resultado:** un plan masivo ya no hace upsert + opt-in + insert por cada destinatario.

### 6.2. Carga masiva de boletas

**Archivo:** `core/convenios/ProductoStockService.php`

- Los seriales existentes se prevalidan en bloque.
- Se detectan duplicados dentro del mismo lote.
- Los eventos se insertan en bloques de 500.

---

## 7. Fase 5 — Vistas y claves Sifone

### 7.1. `vw_creditos`

**Migración:** `sql/migrations/021_perf_views.sql`

- Se agregaron columnas generadas `cedula_num` y `numero_num` a `sifone_estado_cuenta` y `sifone_datacredito`.
- Se agregaron índices sobre esas columnas.
- Se reescribió `vw_creditos` para hacer joins por columnas numéricas indexadas, sin `CAST` en tiempo de consulta.
- `PortalService` también usa las columnas generadas.

### 7.2. `vw_productos`

**Migración:** `sql/migrations/022_perf_vw_productos.sql`

- Se reemplazaron las 4 subconsultas a `vw_creditos` por un único agregado `LEFT JOIN`.
- Se validó que los valores de créditos, capital, mora y pago mínimo coincidan para todas las cédulas.

---

## 8. Migraciones

Aplicar en orden en cada entorno:

```bash
mysql -u USUARIO -p -D BASE_DE_DATOS -e "source sql/migrations/019_perf_asociados_detalle.sql"
mysql -u USUARIO -p -D BASE_DE_DATOS -e "source sql/migrations/020_perf_app_wide.sql"
mysql -u USUARIO -p -D BASE_DE_DATOS -e "source sql/migrations/021_perf_views.sql"
mysql -u USUARIO -p -D BASE_DE_DATOS -e "source sql/migrations/022_perf_vw_productos.sql"
mysql -u USUARIO -p -D BASE_DE_DATOS -e "source sql/migrations/023_perf_pagos.sql"
```

En XAMPP local:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -h 127.0.0.1 -P 3307 -D multi_app_v2 `
  -e "source C:/xampp/htdocs/projects/multi/multi/sql/migrations/020_perf_app_wide.sql"
```

Repetir para `021` y `022`.

---

## 9. Verificación

### 9.1. Pruebas realizadas

- PHP lint sobre todos los archivos modificados: sin errores.
- Sintaxis JavaScript de las páginas modificadas: sin errores.
- Validación de `openapi.yaml`: correcta.
- Smoke test HTTP de páginas y endpoints principales: `200`.
- Comparación de resultados:
  - 38 cláusulas de productos: 0 diferencias.
  - 227 compromisos: 0 diferencias.
  - 3 indagaciones con adjuntos: 0 diferencias.
  - `vw_productos` contra la lógica anterior: 0 diferencias en todas las cédulas.
- Cobranza: filtros por tipo, banda, rango, compromiso y búsqueda probados.
- Portal: liberación de sesión y caché del actor verificadas.
- Tienda: compras, stock, ventas, catálogo y catálogo público probados.

### 9.2. Qué revisar en el navegador

1. Abrir cada módulo y revisar la pestaña Network.
2. Confirmar que las peticiones concurrentes se solapan.
3. Confirmar que no hay llamadas duplicadas al abrir una pantalla.
4. Probar creación/edición de:
   - comunicación,
   - compromiso,
   - indagación,
   - asignación de producto,
   - venta,
   - compra,
   - carga masiva de boletas.
5. Revisar que los filtros de fecha devuelvan los mismos resultados que antes.

---

## 10. Pendientes

- Investigar por qué el servicio MariaDB del puerto 3307 se detiene/reinicia.
- Activar OPcache y `mod_deflate` en el entorno.
- Evaluar paginación del lado del servidor para historiales de cobranza.
- Revisar agregados de `PagosService` si el volumen de pagos crece.
- Revisar exportaciones grandes para streaming.
- Evaluar una tabla resumen `asociados_resumen` si el volumen de asociados crece.
- Revisar los `CAST` restantes en `ChatMetricasService` y exportaciones de `DescargasService`.

---

## 11. Notas de mantenimiento

- `sql/ddl.sql` sigue congelado; todo cambio va en `sql/migrations/`.
- Las columnas generadas de Sifone se actualizan solas al importar datos.
- Si se agrega un endpoint GET que escriba en sesión, revisar `Auth::releaseSession()` y `PortalAuth::releaseSession()`.
- Si se agrega una pantalla con varias secciones, evaluar `IntersectionObserver` y llamadas API agregadas.
- Si se agrega una operación masiva, usar los helpers de lote existentes en Comunicaciones y Stock.
