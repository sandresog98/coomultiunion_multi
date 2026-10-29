-- 018 — Producto a validar para cláusulas de fidelización.
-- Una cláusula puede apuntar a un producto (ej. "Aporte") cuyos pagos
-- determinan el avance de la vigencia. Sin FK física (§3).
-- Idempotente para MariaDB 10.2+.
ALTER TABLE productos
  ADD COLUMN IF NOT EXISTS producto_validar_id INT NULL AFTER parametros;
