# Política de Migración y Conservación de Datos Históricos

## Objetivo

Durante la modernización del sistema de la cooperativa, uno de los principios fundamentales será la **conservación íntegra de toda la información histórica**.

Bajo ninguna circunstancia la refactorización, reorganización de la base de datos o rediseño de la arquitectura podrá provocar la pérdida de datos existentes.

La información acumulada durante años de operación representa un activo crítico para la cooperativa y debe mantenerse disponible, íntegra y consultable.

---

# Principios obligatorios

## 1. Migración completa

Toda la información existente en la base de datos actual deberá migrarse al nuevo modelo de datos.

No se permitirá descartar tablas, registros o relaciones simplemente porque ya no sean utilizadas por la aplicación actual.

Antes de eliminar cualquier estructura deberá demostrarse que:

* La información ya fue migrada correctamente.
* La nueva estructura conserva el mismo significado funcional.
* No existe ningún proceso que dependa de dicha información.
* La validación de la migración fue aprobada.

---

## 2. Cero pérdida de información

El proceso de migración deberá garantizar:

* Conservación de todos los registros históricos.
* Conservación de fechas originales.
* Conservación de usuarios responsables.
* Conservación de estados históricos cuando existan.
* Conservación de relaciones entre registros.
* Conservación de identificadores cuando sea posible o, en su defecto, trazabilidad entre los identificadores antiguos y los nuevos.

No se aceptarán migraciones parciales sin una justificación técnica documentada.

---

## 3. Compatibilidad histórica

El nuevo sistema deberá permitir consultar información generada antes de la migración.

Los usuarios no deben percibir diferencias entre consultar un registro histórico o uno creado después de la modernización.

---

## 4. Trazabilidad

Todo dato migrado deberá poder rastrearse.

Siempre debe ser posible responder preguntas como:

* ¿De qué tabla provino este dato?
* ¿Cuál era su identificador original?
* ¿Cuándo fue migrado?
* ¿Qué transformación recibió durante la migración?

La trazabilidad es obligatoria para facilitar auditorías y verificaciones futuras.

---

## 5. Transformaciones documentadas

Si durante la migración es necesario:

* unir tablas,
* dividir información,
* renombrar campos,
* normalizar datos,
* eliminar duplicados,
* convertir estructuras,

cada transformación deberá quedar documentada indicando:

* estructura original,
* estructura destino,
* motivo del cambio,
* reglas aplicadas,
* impacto funcional.

Ninguna transformación podrá realizarse sin quedar registrada.

---

## 6. Migración validada

Antes de considerar finalizada una migración deberán realizarse verificaciones como mínimo de:

* cantidad de registros,
* integridad referencial,
* consistencia de relaciones,
* valores obligatorios,
* reglas de negocio,
* datos financieros,
* saldos,
* historial de solicitudes,
* información de asociados.

Toda validación deberá quedar documentada.

---

## 7. Migración incremental

La migración deberá diseñarse para poder ejecutarse por etapas.

Idealmente el proceso deberá ser:

* repetible,
* automatizable,
* reversible cuando sea posible,
* verificable mediante reportes.

---

## 8. Base de datos de Sifone

Las tablas provenientes de Sifone constituyen la principal fuente de información del sistema.

Durante la modernización deberán analizarse cuidadosamente todas sus dependencias antes de modificar cualquier proceso.

En especial, deberá evaluarse la consolidación de información utilizando la tabla **Estado de Cuenta**, siempre garantizando que ninguna información histórica proveniente de las tablas originales se pierda.

Si se construye una vista unificada o una nueva estructura de acceso, esta deberá representar fielmente la información existente y mantener compatibilidad con los procesos que dependan de ella.

---

## 9. Solicitudes históricas

Todos los tipos de solicitudes existentes deberán migrarse al nuevo esquema de solicitudes.

Esto incluye:

* solicitudes abiertas,
* solicitudes cerradas,
* solicitudes anuladas,
* historial de cambios,
* comentarios,
* archivos adjuntos,
* estados,
* aprobaciones,
* cualquier dato relacionado con su ciclo de vida.

El nuevo sistema deberá permitir consultar solicitudes creadas antes de la migración sin pérdida de información.

---

## 10. Reglas generales

Durante la modernización se aplicarán las siguientes reglas:

* Nunca eliminar información sin una validación previa.
* Nunca modificar directamente la base de producción durante el desarrollo.
* Toda migración deberá poder repetirse de forma controlada.
* Todo cambio en la estructura deberá quedar documentado.
* Toda decisión deberá priorizar la integridad de los datos sobre la simplicidad de la implementación.

---

## 11. Módulo Tienda (B8) — migración documentada

**Bloque**: 11 del migrador v1→v2 (one-use, ids preservados, cierra con chequeo de FKs huérfanos que invalida la migración si hay roturas).

### 11.1 Alcance migrado (catálogo)

| Legacy | v2 | Transformación | Registros |
|---|---|---|---|
| `tienda_categoria` | `tienda_categorias` | Sin cambios (ids preservados) | 23 |
| `tienda_marca` | `tienda_marcas` | Sin cambios (ids preservados) | 23 |
| `tienda_producto` | `tienda_productos` | Se **elimina** `precio_asociado`; `precio_compra_aprox`/`precio_venta_aprox` → `precio_compra_sugerido`/`precio_venta_sugerido`; `foto_url` → `foto_path` opaco en `uploads/tienda/`; **flag manual `agotado` migrado (6 marcados / 561 no)** (ids preservados) | 567 |
| `tienda_rangos_precio` | `tienda_rangos_precio` | La tabla **no existe en el dump** `multi_app_legacy` → se siembran los **17 tramos** del migration legacy `2026_06_24_rangos_precio_tienda.sql` (10/12/13/14/15/5 % por tramos), idempotente (`INSERT IGNORE`) en `ddl.sql` y migrador | 17 |
| `tienda_clientes` | `tienda_clientes` | `tipo='externo'`, `cedula=NULL` (los asociados quedan vía `sifone_asociados`/`tienda_ventas.cedula`); `nit_cedula` se conserva | 44 |
| `tienda_promocion` | `tienda_promociones` | Sin cambios (ids preservados) | 12 |

**Fotos**: el directorio legacy `ui/assets/uploads/tienda/` no existe en este entorno → **0 archivos copiados / 548 productos sin foto física** (se conserva el basename solo si el archivo existe). En **desarrollo** se generaron placeholders (PHP GD) para los 25 productos APPLE activos como material de prueba (`foto_path` → `tienda_demo_*.png`).

### 11.2 Precio Multiunión (lógica del legacy)

Con los rangos sembrados se restaura la lógica original de **dos precios** aun sin oferta: `precio_multiunion = ROUND(precio·(1−%rango/100), −3)` (computado en SQL). En el detalle/ficha v2 se muestran:
- `$X (Precio Coomultiunion)` → precio con el descuento del rango.
- `$Y (Precio Comercial)` (tachado) → precio de referencia.
- Con **promoción vigente** se añade `$D (OFERTA)` (verde).

### 11.3 Fuera del alcance (decisión del líder — justificada)

**NO se migran**: compras, stock, ventas, reversiones ni consignaciones (y eventos de tienda):

- `tienda_compra`/`tienda_compra_detalle`/`tienda_compra_imei`/`tienda_inventario` → no se migran; en v2 el stock vive en `tienda_stock` (1 fila por unidad, estados `disponible|vendido|anulado`) y se reconstruye con el flujo nuevo (compras → unidades; ventas consumen unidades multi-unidad con `cantidad>1` vía `tienda_venta_detalle_stock`).
- `tienda_venta`(+detalle+pago) → no se migran; la **ganancia se calcula** (nunca se almacena) = Σ unitario − Σ costo de sus unidades.
- `tienda_reversion` → no se migra; en v2 **reversión = bonos** (nuevo): devuelve unidades a `disponible` + crea `tienda_bonos` (saldo acumulativo, pagable como medio `bono`).
- `tienda_consignacion`/`tienda_consignacion_detalle` y `tienda_eventos` → **se eliminan del diseño v2** (sin tabla ni migración).

**Datos de referencia en el dump legacy** (no migrados, para auditoría futura): compras 87 (781 detalles + 61 IMEIs) · inventario 2.264 · ventas 364 (567 detalles + 384 pagos) · 1 reversión · 37 consignaciones (140 detalles).

### 11.4 Transformaciones y reglas aplicadas

- **Ids preservados** (reglas.md §13.1): categorías, marcas, productos, clientes y promociones conservan su `id` legacy.
- **Flag manual `agotado`**: se conserva y migra (6 productos del legacy marcados). Es un flag **manual** gestionado en el Catálogo staff (checkbox + filtro + toggle); el público lo muestra como sello **AGOTADO** en tarjetas, quick-view y detalle. No depende del stock (`tienda_stock`).
- **Del flujo nuevo**: `tienda_venta_detalle_stock` vincula cada unidad consumida; `tienda_ventas.tipo_cliente` `asociado|externo`; `tienda_venta_pagos.tipo` `efectivo|bold|qr|sifone|sistecredito|alocredito|bono`.
- **Validación**: conteos verificados (23/23/567/17/44/12) y **0 FKs huérfanos** cerrando la migración.

### 11.5 Recarga selectiva del esquema

`sql/ddl.sql` contiene el seed de `credito_config_seccion` (B7a) con `INSERT` plano (no `INSERT IGNORE`), por lo que **no se recarga sobre una BD ya poblada**: usar BD fresca (DROP+CREATE) o recargar solo la sección nueva, p. ej.:
`sed -n '/MÓDULO: Tienda/, $p' sql/ddl.sql | mysql multi_app_v2`

---

# Resultado esperado

Al finalizar el proceso de modernización deberá existir:

* Una nueva base de datos organizada y consistente.
* Toda la información histórica migrada correctamente.
* Trazabilidad completa entre el modelo antiguo y el nuevo.
* Validaciones que certifiquen la integridad de la migración.
* Documentación detallada de todas las transformaciones realizadas.
* Compatibilidad funcional para consultar tanto información histórica como nueva.
* Una arquitectura de datos preparada para evolucionar sin comprometer la integridad de la información de la cooperativa.

La preservación de la información histórica es un requisito obligatorio y tiene prioridad sobre cualquier optimización técnica o simplificación del modelo de datos.

---

# Estado de ejecución (v2 — actualizado)

> La **política** de migración es la de arriba (cero pérdida, trazabilidad, integridad de ids). Aquí el estado **ejecutado** por el migrador v1→v2 (one-use, trunca y re-inserta; fuente `multi_app_legacy`, destino `multi_app_v2`).

## Bloques migrados (12)

| Bloque | Contenido | Cifras |
|---|---|---|
| 1 | `control_usuarios` → `usuarios` (ids preservados + bootstrap admin) | 13 |
| 2–6 | `asociados_control` · `productos`/`cláusulas` → `productos` · asignaciones → `asociados_productos` · comisiones/redenciones → `movimientos_multi` · `control_logs` → `logs` | 647 · 8 · 1.032 · 14 · 24.513 |
| 7 | pagos/transacciones (PSE/Confiar/conciliación/confirmación → `pagos_*`,`transacciones_*`) | 1.807 · 3.141 · 1.482 · 1.269 (466 comprobantes) · trx 2.721+8.346 |
| 8 | `cobranza_*` → `cobranza_*` | 2.230 comunicaciones · 3 indagaciones · 92 remisiones |
| 9 | Créditos: `control_tasas_creditos` → `credito_lineas`; `credito_docs_solicitudes` → `credito_solicitudes`; `credito_docs_documentos` → `credito_documentos` (+ **482 archivos copiados**) | 25 líneas · 160 sol. · 1.483 docs · 36 config |
| 10 | Convenios + Boletería (unificados): `convenios`, `convenios_productos` (+12 productos-boleta por mapeo por nombre), `boleteria_boletas`/`eventos` | 33 · 69+12 · 395 boletas (158 archivos) · 992 eventos |
| 11 | **Tienda SOLO catálogo** (decisión del líder): `tienda_categorias/marcas/productos/rangos/clientes/promociones` | 23 · 23 · 567 (0 fotos/548 sin físico) · 17 rangos · 44 clientes · 12 promo |
| 12 | **Solicitudes unificadas (B7)**: `cx_solicitudes`(+comentarios) → `solicitudes`+detalle tipado · `control_calificaciones` → `solicitudes_calificaciones` · `ticketera_*` → tipo `ticket` (cabecera + eventos + adjuntos) | 98 solicitudes · 14 comentarios · 79 calificaciones · 220 tickets · 863 eventos · 9 adjuntos |

## Notas de estado
- **Crédito/B7a**: 482 archivos físicos copiados (ruta legacy resuelta en la migración). **Solicitudes (B7)**: motor + tipos construidos y **migración histórica (bloque 12) ejecutada y validada** (98 solicitudes CX + 220 tickets + 79 calificaciones + 14 comentarios + 863 eventos + 9 adjuntos, 0 FKs huérfanos).
- **Storage (R2)**: los archivos de `créditos`, `pagos`, `boletas`, `solicitudes`, `cobranza`, `fondo` se migran a Cloudflare R2 (`scripts/migrar_r2.php`, idempotente); `tienda`, `cargas`, `convenios` quedan **locales**.
- **Tienda transaccional** (compras/stock/ventas/reversiones/bonos) y **solicitudes históricas** son los pendientes de migración adicionales si el liderazgo los solicita.
- **Integridad de ids**: `usuarios.id` y `productos.id` se preservan; el script cierra con **chequeo de FKs huérfanos** que invalida la migración ante roturas.

---

## Validación local (Windows, 2026-10-15) — 12/12 bloques ejecutados ✅

La migración completa se **ejecutó y validó en local** (Windows + MariaDB 11.8.9, misma versión que
Hostinger), con **0 FKs huérfanos** y login `admin` verificado. El detalle del entorno Windows (MariaDB en
puerto 3307) está en el `README.md`.

### Correcciones al migrador (bug de bloque 12, que estaba "anexado sin ejecutar")

1. **`src` reasignado** — el bloque 7 (pagos) reasignaba `src` (la conexión) a una ruta de archivo; el bloque 12
   la usaba como conexión → `AttributeError`. Se abrió una **conexión fresca** (`src12`) en el bloque 12.
2. **`responsable_id` huérfano** — 8 solicitudes del legacy apuntaban al usuario `id=6`, **eliminado** de
   `control_usuarios`. Ahora se **anula a NULL** cuando el responsable no existe en `usuarios`.
3. **`categoria_id` de tickets** — el seed de `ddl.sql` usa ids compactos (1..15) mientras el legacy tiene huecos
   y categorías nuevas. Se re-mapea **por nombre normalizado** (no por id): las categorías del legacy que no existen
   en el seed se **insertan automáticamente** con un id nuevo, y `NULL` se conserva en `NULL`. Incluye normalización
   de la errata `REVERCION`→`REVERSION`.
4. **Referencias a usuarios borrados** (aparecieron con el backup actualizado, que eliminó los usuarios 10 y 11):
   la cobranza (`creado_por`) y otras tablas seguían apuntando a esos usuarios. Se añadió una **limpieza general**
   antes del chequeo de FKs que anula a NULL toda columna de referencia a `usuarios.id` inexistente
   (`creado_por`, `*_por`, `usuario_id`, `responsable_id`, `asignado_por`, `respondido_por`, etc.).

### Nota: datos Sifone (fuera de la migración)

Las tablas `sifone_*` **no forman parte de la migración**: son la fuente maestra importada por el módulo **"Cargas
de archivos"** (`core/import/SifoneImporter`) desde los Excel de Sifone. Para validar en local se cargaron los 7
archivos (asociados 613 · cartera aseguradora 249 · cartera mora 90 · balance 2.596 · movimientos 4.943 · datacredito
3.038 · estado_cuenta 250).

### Pendiente (archivos físicos)

Los **archivos físicos** del legacy (documentos de crédito, comprobantes, boletas, imágenes de convenios y fotos
de tienda) **no se copiaron** en local: el dump legacy solo trae la base de datos, no los binarios. Esos
archivos viven en el servidor de Hostinger y deben **descargarse por separado** antes del go-live (o asumirse su
ausencia, como ya se documenta en `migracion_datos.md`). Conteos afectados: 1.483 docs de crédito · 466
comprobantes · 158 boletas · 548 fotos de tienda.
