# Plan de migración y salida en vivo — Multi v1 → Multi v2

> Proyecto: `C:\xampp\htdocs\projects\multi\multi` (Multi App v2 — PHP 8.2 / MariaDB 11.8, sin framework).
> Este documento es el **cronograma de cutover** (salida en vivo). La política de migración de datos y el
> detalle de bloques/trazabilidad están en [`migracion_datos.md`](./migracion_datos.md).

---

## Objetivo

Pasar de **Multi v1** (legacy) a **Multi v2** de forma ordenada y sin pérdida de información, garantizando:

- Que no ingresen **solicitudes nuevas** desde v1 a partir del **19 de octubre**.
- Que **todas las solicitudes abiertas** en v1 queden **resueltas** antes del **24 de octubre**.
- Que v2 se pruebe en **test** sobre el host antes de ir a producción.
- Que **producción quede alineada con test** antes del apagado de v1.
- Que el **apagado de v1 (29 oct)** y el **encendido de v2 (30 oct)** ocurran con validación previa.

Año de referencia de las fechas: **2026**.

---

## Cronograma

| # | Fecha | Día | Hito | Acción |
|---|-------|-----|------|--------|
| 1 | **19 oct** | Lunes | Corte de entrada en v1 | **Dejamos de recibir solicitudes desde Multi v1.** v1 queda como solo consulta; no se admiten solicitudes nuevas. |
| 2 | **24 oct** | Sábado | Corte de solicitudes abiertas | **Las solicitudes abiertas ya deben estar resueltas.** No debe quedar ninguna solicitud pendiente/abierta en v1. |
| 3 | **26 oct** | Lunes | Test en el host | **Cargamos y encendemos test en el host** y realizamos pruebas, corrigiendo los hallazgos. |
| 4 | **28 oct** | Miércoles | Prod + alineación | **Cargamos producción** y validamos que quede **alineado con test**. |
| 5 | **29 oct** | Jueves | Apagado de v1 | **Apagamos Multi v1.** |
| 6 | **30 oct** | Viernes | Encendido de v2 | **Encendemos producción (Multi v2).** |

> ⚠️ **Ventana entre el 29 y el 30 de octubre**: v1 queda apagada y v2 todavía no publicada. Se debe
> confirmar que esta ventana sea aceptable y acotada, o definir un aviso a los usuarios.

---

## Detalle por hito

### 1. Lunes 19 oct — Dejamos de recibir solicitudes desde v1

- v1 pasa a **modo solo consulta** para solicitudes (no permite crear nuevas).
- Los usuarios deben comenzar a usar v2 (o canal alterno) para solicitudes nuevas.
- Verificar que no existan accesos/automatismos que sigan creando solicitudes en v1.

### 2. Sábado 24 oct — Solicitudes abiertas resueltas

- **Requisito de corte**: 0 solicitudes abiertas/pendientes en v1.
- Revisar la migración de **solicitudes históricas** (bloque 12: `solicitudes` + detalle tipado,
  calificaciones y tickets) y confirmar que las abiertas quedan cerradas antes de migrar.
- Dejar evidencia del conteo final (abiertas = 0).

### 3. Lunes 26 oct — Test en el host

- **Cargar y encender test en el host** (BD + esquema `ddl.sql` + archivos + `.env` del host).
- Ejecutar el plan de pruebas y **corregir sobre la marcha**.
- Ejecutar la migración legacy → v2 y validar **0 FKs huérfanos** (el migrador cierra con ese chequeo).
- Validar login staff (`admin`) y portal del asociado.

### 4. Miércoles 28 oct — Producción + alineación con test

- **Cargar producción** (mismo esquema/versión que test).
- **Validar que producción quede alineada con test**: misma versión de código, esquema y migración.
- Confirmar que la migración de **archivos físicos** (créditos, comprobantes, boletas, fotos de tienda,
  documentos de convenios) esté completa — ver pendiente en `migracion_datos.md`.

### 5. Jueves 29 oct — Apagamos v1

- Con producción ya cargada y validada, **apagar Multi v1**.
- Comunicar el corte a los usuarios.

### 6. Viernes 30 oct — Encendemos producción (v2)

- **Encender/publicar Multi v2** en producción.
- Verificar accesos (staff, portal, tienda, convenios, simulador) y flujo de solicitudes.

---

## Checklist de validación (previo al go-live)

- [ ] Migración legacy → v2 ejecutada y **0 FKs huérfanos**.
- [ ] Conteos de registros validados (12 bloques — ver `migracion_datos.md`).
- [ ] Solicitudes históricas migradas y **0 abiertas** en v1 al 24 oct.
- [ ] Archivos físicos copiados/validados (créditos, pagos, boletas, fotos, convenios).
- [ ] Storage R2 verificado (`scripts/migrar_r2.php`, idempotente).
- [ ] Datos Sifone cargados desde los Excel (`UI › Cargas`).
- [ ] Login staff (`admin`) y portal del asociado funcionando.
- [ ] **Producción == test** (código + esquema + datos migrados).
- [ ] Ventana de indisponibilidad (29→30 oct) comunicada.

---

## Pendientes / decisiones por confirmar

- Ventana exacta de cada operación (horas) y **quién** ejecuta cada hito.
- Manejo de la **ventana de indisponibilidad** entre el apagado de v1 y el encendido de v2.
- Aviso/notificación a los asociados durante el corte.
