# Migraciones

> **`sql/ddl.sql` es la línea base congelada** del esquema (versión desplegada en dev/test/prod).
> **No se edita**: modificarlo rompería la trazabilidad/auditoría de las bases ya creadas.

## Política

- Una **BD nueva** se crea con `sql/ddl.sql` y, si existen migraciones posteriores a la línea base, se aplican en orden.
- Todo **cambio de esquema posterior** a esta línea base se agrega aquí como una migración nueva,
  numerada de forma consecutiva (`018_...sql`, `019_...sql`, …), **idempotente** cuando sea posible.
- Las migraciones se aplican **en orden** sobre las BD existentes (dev/test/prod).
- No se borran ni se reescriben migraciones ya aplicadas.

## Historial consolidado

Las migraciones `001`–`017` ya están incorporadas en `ddl.sql` y se eliminaron de esta carpeta
(consolidación de la línea base). No deben volver a aplicarse por separado.

## Migraciones nuevas

- `018_productos_producto_validar.sql`: agrega `productos.producto_validar_id` para que una cláusula
  defina contra qué producto se validan sus pagos (ej. “Aportes”).
- `019_perf_asociados_detalle.sql`: índices compuestos para la ficha de asociado (movimientos,
  cobranza, productos y agregados de aportes).
- `020_perf_app_wide.sql`: índices transversales para logs, movimientos, pagos, tienda, boletas,
  solicitudes y créditos.
- `021_perf_views.sql`: columnas generadas numéricas en Sifone y reescritura de `vw_creditos`
  para eliminar los `CAST` en los joins.
- `022_perf_vw_productos.sql`: `vw_productos` calcula `vw_creditos` una sola vez.
- `023_perf_pagos.sql`: índices de pagos por fecha y confirmación.
