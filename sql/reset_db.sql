-- =====================================================================
-- multi_app_v2 — reset_db.sql
-- Reinicia el esquema completo: DROP de todas las tablas v2 (Sifone,
-- infraestructura, solicitudes, productos, pagos, transacciones) y
-- re-crea desde el DDL canónico ddl.sql.
-- USO (solo entornos de desarrollo — NUNCA en producción, migracion_datos.md #10):
--   mysql -u USER -p -e "DROP DATABASE IF EXISTS multi_app_v2; CREATE DATABASE multi_app_v2 CHARACTER SET utf8mb4;"
--   mysql -u USER -p multi_app_v2 < sql/ddl.sql
-- (este script sirve si prefieres no recrear la BD: elimina tablas y deja
--  todo listo para `mysql -u USER -p multi_app_v2 < sql/ddl.sql`.)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Sifone (datos contables importados)
DROP TABLE IF EXISTS sifone_asociados;
DROP TABLE IF EXISTS sifone_cartera_aseguradora;
DROP TABLE IF EXISTS sifone_cartera_mora;
DROP TABLE IF EXISTS sifone_balance_prueba;
DROP TABLE IF EXISTS sifone_movimientos_tributarios;
DROP TABLE IF EXISTS sifone_datacredito;
DROP TABLE IF EXISTS sifone_estado_cuenta;

-- Infraestructura (usuarios, portal overlay, jobs de carga, auditoría)
DROP TRIGGER IF EXISTS usuarios_bu;
DROP TABLE IF EXISTS password_history;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS asociados_control;
DROP TABLE IF EXISTS sifone_cargas_archivos;
DROP TABLE IF EXISTS logs;
DROP TABLE IF EXISTS idempotencia;
DROP TABLE IF EXISTS login_intentos;

-- Sistema único de solicitudes (B7)
DROP TRIGGER IF EXISTS solicitudes_bu;
DROP TABLE IF EXISTS solicitudes_det_nuevo_prod;
DROP TABLE IF EXISTS solicitudes_det_redencion;
DROP TABLE IF EXISTS solicitudes_det_actualizacion;
DROP TABLE IF EXISTS solicitudes_det_pqrs;
DROP TABLE IF EXISTS solicitudes_det_fondo;
DROP TABLE IF EXISTS email_cola;
DROP TABLE IF EXISTS fondo_bienestar_redenciones;
DROP TABLE IF EXISTS fondo_bienestar_convenios;
DROP TABLE IF EXISTS solicitudes_calificaciones;
DROP TABLE IF EXISTS solicitudes_adjuntos;
DROP TABLE IF EXISTS solicitudes_comentarios;
DROP TABLE IF EXISTS solicitudes_eventos;
DROP TABLE IF EXISTS solicitudes;
DROP TABLE IF EXISTS solicitudes_transiciones;
DROP TABLE IF EXISTS solicitudes_estados;
DROP TABLE IF EXISTS solicitudes_tipos;

-- Configuración (B4)
DROP TRIGGER IF EXISTS productos_bu;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS asociados_productos;
DROP TABLE IF EXISTS movimientos_multi;

-- Pagos y transacciones (B5)
DROP TABLE IF EXISTS pagos_confirmacion;
DROP TABLE IF EXISTS pagos_conciliacion;
DROP TABLE IF EXISTS pagos_confiar;
DROP TABLE IF EXISTS pagos_pse;
DROP TABLE IF EXISTS transacciones_detalle;
DROP TABLE IF EXISTS transacciones;

-- Convenios + Boletería (B9/B10/B15)
DROP TABLE IF EXISTS boleteria_eventos;
DROP TABLE IF EXISTS boleteria_boletas;
DROP TABLE IF EXISTS convenios_productos;
DROP TABLE IF EXISTS convenios;

-- Tienda (B8)
DROP TABLE IF EXISTS tienda_venta_detalle_stock;
DROP TABLE IF EXISTS tienda_venta_detalles;
DROP TABLE IF EXISTS tienda_venta_pagos;
DROP TABLE IF EXISTS tienda_ventas;
DROP TABLE IF EXISTS tienda_stock;
DROP TABLE IF EXISTS tienda_compra_detalles;
DROP TABLE IF EXISTS tienda_compras;
DROP TABLE IF EXISTS tienda_bonos;
DROP TABLE IF EXISTS tienda_reversiones;
DROP TABLE IF EXISTS tienda_promociones;
DROP TABLE IF EXISTS tienda_rangos_precio;
DROP TABLE IF EXISTS tienda_clientes;
DROP TABLE IF EXISTS tienda_productos;
DROP TABLE IF EXISTS tienda_marcas;
DROP TABLE IF EXISTS tienda_categorias;

-- Vistas
DROP VIEW IF EXISTS vw_movimientos;
DROP VIEW IF EXISTS vw_productos;
DROP VIEW IF EXISTS vw_creditos;
DROP VIEW IF EXISTS vw_asociados;

SET FOREIGN_KEY_CHECKS = 1;

-- Recargar el esquema: mysql -u USER -p multi_app_v2 < sql/ddl.sql