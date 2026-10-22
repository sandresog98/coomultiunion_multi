-- 008_api_endpoints_seed.sql — Autoriza (semilla) los endpoints de la Fase 2.
--
-- Deja en la allowlist global las claves que se expondrán por token (curl).
-- Se puede activar/desactivar cualquiera desde la UI: ui/modules/api/pages/authorized_endpoints.php
--
-- El módulo-gate de operaciones cuyo prefijo no coincide con el módulo del endpoint
-- (ej. pagos.* / movimientos.* → oficina.transacciones) se resuelve en
-- core/Permisos.php::moduloRequerido(), no aquí.
--
-- Aplicar:  mysql multi_app_v2 < sql/migrations/008_api_endpoints_seed.sql
-- Idempotente: no re-activa lo que ya exista (ON DUPLICATE KEY UPDATE clave=clave).

INSERT INTO api_endpoints_autorizados (clave, descripcion, activo, fecha_actualizacion) VALUES
  -- Lecturas de apoyo
  ('oficina.asociados',                  'Asociados: consulta (listar/ficha) y gate de productos', 1, NOW()),
  ('oficina.movimientos_tipos',          'Movimientos: catálogo de tipos',                        1, NOW()),
  ('solicitudes',                        'Solicitudes: listar / detalle / config (lectura)',       1, NOW()),
  -- Solicitudes
  ('solicitudes.gestion.crear_ticket',   'Solicitudes: crear ticket interno',                       1, NOW()),
  -- Asociados (productos y cláusulas)
  ('asociados.detalle.asignar',          'Asociados: asignar producto/cláusula',                   1, NOW()),
  ('asociados.detalle.editar',           'Asociados: editar asignación',                           1, NOW()),
  ('asociados.detalle.eliminar',         'Asociados: eliminar asignación',                         1, NOW()),
  ('asociados.detalle.adjuntar',         'Asociados: adjuntar a asignación',                       1, NOW()),
  -- Cobranza
  ('cobranza.lista.comunicar',           'Cobranza: comunicaciones (crear/editar/eliminar)',       1, NOW()),
  ('cobranza.lista.indagar',             'Cobranza: indagaciones (crear/editar/eliminar)',         1, NOW()),
  ('cobranza.lista.adjuntar',            'Cobranza: adjunto de indagación',                        1, NOW()),
  -- Pagos
  ('pagos.conciliar',                    'Pagos PSE: asignar/desasignar Confiar',                  1, NOW()),
  ('pagos.confirmar',                    'Pagos Confiar: asignar / no válido / desasignar',        1, NOW()),
  ('pagos.upload',                       'Pagos Confiar: subir comprobante',                       1, NOW()),
  -- Movimientos
  ('movimientos.crear',                  'Movimientos: crear',                                     1, NOW()),
  ('movimientos.editar',                 'Movimientos: editar',                                    1, NOW()),
  -- Cargas
  ('cargas.procesar',                    'Cargas: procesar archivo Sifone',                        1, NOW()),
  -- Créditos
  ('creditos.nueva_solicitud.crear',     'Créditos: crear solicitud',                              1, NOW()),
  ('creditos.gestionar_solicitud.etapas','Créditos: cambiar de etapa',                             1, NOW()),
  ('creditos.gestionar_solicitud.campos','Créditos: diligenciar campos',                           1, NOW()),
  -- Tienda: catálogo
  ('tienda.catalogo.crear',              'Tienda catálogo: crear',                                 1, NOW()),
  ('tienda.catalogo.editar',             'Tienda catálogo: editar',                                1, NOW()),
  ('tienda.catalogo.toggle',             'Tienda catálogo: activar/inactivar',                     1, NOW()),
  ('tienda.catalogo.eliminar',           'Tienda catálogo: eliminar',                              1, NOW()),
  -- Tienda: clientes
  ('tienda.clientes.crear',              'Tienda clientes: crear',                                 1, NOW()),
  ('tienda.clientes.editar',             'Tienda clientes: editar',                                1, NOW()),
  ('tienda.clientes.toggle',             'Tienda clientes: activar/inactivar',                     1, NOW()),
  ('tienda.clientes.eliminar',           'Tienda clientes: eliminar',                              1, NOW()),
  -- Convenios
  ('convenios.index.crear',              'Convenios: crear convenio/producto',                     1, NOW()),
  ('convenios.index.editar',             'Convenios: editar convenio/producto',                    1, NOW()),
  ('convenios.index.toggle',             'Convenios: activar/inactivar',                           1, NOW()),
  ('convenios.index.eliminar',           'Convenios: eliminar',                                    1, NOW()),
  -- Boletas (stock de convenios)
  ('convenios.stock.crear',              'Boletas: crear',                                         1, NOW()),
  ('convenios.stock.anular',             'Boletas: anular',                                        1, NOW()),
  ('convenios.stock.vender',             'Boletas: vender',                                        1, NOW()),
  ('convenios.stock.contabilizar',       'Boletas: contabilizar',                                  1, NOW())
ON DUPLICATE KEY UPDATE clave = clave;
