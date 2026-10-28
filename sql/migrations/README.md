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
