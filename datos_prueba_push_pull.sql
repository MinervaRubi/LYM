-- Datos de prueba para el flujo PUSH / PULL de SCM
-- Idempotente: inserta o actualiza según corresponda

-- 1. Proveedores de prueba
INSERT INTO scm_proveedores (nombre, contacto, telefono, email, direccion, estado_republica, especialidad, calificacion, lead_time_dias, activo)
SELECT 'ProveedorA', 'Juan Pérez', '555-111-2233', 'contacto@proveedora.com', 'Calle Industria 10', 'México', 'Textiles y Confección', 5.0, 3, 1
WHERE NOT EXISTS (SELECT 1 FROM scm_proveedores WHERE nombre = 'ProveedorA');

INSERT INTO scm_proveedores (nombre, contacto, telefono, email, direccion, estado_republica, especialidad, calificacion, lead_time_dias, activo)
SELECT 'Proveedor B', 'María Gómez', '555-444-5566', 'ventas@proveedorb.com', 'Av. Artesanías 25', 'Jalisco', 'Alfarería y Cerámica', 4.8, 5, 1
WHERE NOT EXISTS (SELECT 1 FROM scm_proveedores WHERE nombre = 'Proveedor B');

-- Asegurar que estén activos si ya existían
UPDATE scm_proveedores SET activo = 1 WHERE nombre IN ('ProveedorA', 'Proveedor B');

-- 2. Productos y sus inventarios
-- Textil bordado (ProveedorA, PUSH, min 5, max 20, stock 8, reposición 5)
INSERT INTO productos (name, description, price, costo_unitario, category, proveedor_id, estrategia_logistica, stock_minimo, cantidad_reposicion, active)
SELECT 'Textil bordado', 'Textil bordado artesanal de alta calidad', 120.00, 60.00, 'Textiles', 
       (SELECT id FROM scm_proveedores WHERE nombre = 'ProveedorA' LIMIT 1), 'PUSH', 5, 5, 1
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE name = 'Textil bordado');

UPDATE productos 
SET proveedor_id = (SELECT id FROM scm_proveedores WHERE nombre = 'ProveedorA' LIMIT 1),
    estrategia_logistica = 'PUSH',
    stock_minimo = 5,
    cantidad_reposicion = 5,
    active = 1
WHERE name = 'Textil bordado';

INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
SELECT p.id, p.name, CONCAT('SCM-', LPAD(p.id, 4, '0')), 'Almacén Central', 'Pasillo A-1', 8, 5, 20
FROM productos p
WHERE p.name = 'Textil bordado'
  AND NOT EXISTS (SELECT 1 FROM scm_inventario i WHERE i.producto_id = p.id);

UPDATE scm_inventario i
JOIN productos p ON i.producto_id = p.id
SET i.stock_actual = 8, i.stock_minimo = 5, i.stock_maximo = 20, i.nombre = p.name
WHERE p.name = 'Textil bordado';


-- Vasija de barro (Proveedor B, PULL, min 10, max 40, stock 25, reposición 20)
INSERT INTO productos (name, description, price, costo_unitario, category, proveedor_id, estrategia_logistica, stock_minimo, cantidad_reposicion, active)
SELECT 'Vasija de barro', 'Vasija de barro tradicional cocida a mano', 150.00, 75.00, 'Artesanías', 
       (SELECT id FROM scm_proveedores WHERE nombre = 'Proveedor B' LIMIT 1), 'PULL', 10, 20, 1
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE name = 'Vasija de barro');

UPDATE productos 
SET proveedor_id = (SELECT id FROM scm_proveedores WHERE nombre = 'Proveedor B' LIMIT 1),
    estrategia_logistica = 'PULL',
    stock_minimo = 10,
    cantidad_reposicion = 20,
    active = 1
WHERE name = 'Vasija de barro';

INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
SELECT p.id, p.name, CONCAT('SCM-', LPAD(p.id, 4, '0')), 'Almacén Central', 'Pasillo B-2', 25, 10, 40
FROM productos p
WHERE p.name = 'Vasija de barro'
  AND NOT EXISTS (SELECT 1 FROM scm_inventario i WHERE i.producto_id = p.id);

UPDATE scm_inventario i
JOIN productos p ON i.producto_id = p.id
SET i.stock_actual = 25, i.stock_minimo = 10, i.stock_maximo = 40, i.nombre = p.name
WHERE p.name = 'Vasija de barro';


-- Producto control (ProveedorA, PUSH, min 3, max 15, stock 15, reposición 5)
INSERT INTO productos (name, description, price, costo_unitario, category, proveedor_id, estrategia_logistica, stock_minimo, cantidad_reposicion, active)
SELECT 'Producto control', 'Producto de control de inventario y flujo PUSH', 80.00, 40.00, 'Control', 
       (SELECT id FROM scm_proveedores WHERE nombre = 'ProveedorA' LIMIT 1), 'PUSH', 3, 5, 1
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE name = 'Producto control');

UPDATE productos 
SET proveedor_id = (SELECT id FROM scm_proveedores WHERE nombre = 'ProveedorA' LIMIT 1),
    estrategia_logistica = 'PUSH',
    stock_minimo = 3,
    cantidad_reposicion = 5,
    active = 1
WHERE name = 'Producto control';

INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
SELECT p.id, p.name, CONCAT('SCM-', LPAD(p.id, 4, '0')), 'Almacén Central', 'Pasillo C-1', 15, 3, 15
FROM productos p
WHERE p.name = 'Producto control'
  AND NOT EXISTS (SELECT 1 FROM scm_inventario i WHERE i.producto_id = p.id);

UPDATE scm_inventario i
JOIN productos p ON i.producto_id = p.id
SET i.stock_actual = 15, i.stock_minimo = 3, i.stock_maximo = 15, i.nombre = p.name
WHERE p.name = 'Producto control';
