-- Migración Idempotente para flujo PUSH / PULL SCM en LYM
ALTER TABLE productos ADD COLUMN IF NOT EXISTS cantidad_reposicion INT NOT NULL DEFAULT 5;
ALTER TABLE scm_pedidos_proveedor ADD COLUMN IF NOT EXISTS origen ENUM('manual','automatico') NOT NULL DEFAULT 'manual';
ALTER TABLE scm_pedidos_proveedor ADD COLUMN IF NOT EXISTS entrada_registrada TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE scm_movimientos ADD COLUMN IF NOT EXISTS pedido_id INT NULL;
