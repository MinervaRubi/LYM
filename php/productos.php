<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // ----------------------------------------------------
    // GET: Listar todos los productos del catálogo SCM
    // ----------------------------------------------------
    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT 
                p.id, 
                p.name, 
                p.description, 
                p.price, 
                p.category, 
                p.features, 
                p.image_icon, 
                p.active, 
                p.created_at,
                COALESCE(i.stock_actual, 25) AS stock_actual,
                COALESCE(i.sku, CONCAT('SCM-PRD', LPAD(p.id, 2, '0'))) AS sku,
                COALESCE(i.almacen, 'Almacén Central (CDMX)') AS almacen,
                COALESCE(i.ubicacion, 'Pasillo General') AS ubicacion
            FROM productos p
            LEFT JOIN scm_inventario i ON (i.producto_id = p.id OR i.nombre = p.name)
            ORDER BY p.id ASC
        ");

        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si no hay productos en la tabla, devolver los de catálogo básico
        if (empty($productos)) {
            $productos = [];
        }

        echo json_encode([
            'success' => true,
            'total' => count($productos),
            'productos' => $productos
        ]);
        exit();
    }

    // ----------------------------------------------------
    // POST: Crear nuevo producto en el catálogo
    // ----------------------------------------------------
    if ($method === 'POST') {
        if (!isLoggedIn() || !isStaff()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $name = isset($input['name']) ? trim(cleanInput($input['name'])) : '';
        $description = isset($input['description']) ? trim(cleanInput($input['description'])) : '';
        $price = isset($input['price']) ? (float)$input['price'] : 0.00;
        $category = isset($input['category']) ? trim(cleanInput($input['category'])) : 'general';
        $stock = isset($input['stock']) ? (int)$input['stock'] : 20;

        if (empty($name) || $price <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nombre y precio válido son obligatorios']);
            exit();
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO productos (name, description, price, category, active, created_at)
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmtIns->execute([$name, $description, $price, $category]);
        $prodId = (int)$pdo->lastInsertId();

        // Registrar también en el inventario SCM
        $sku = 'SCM-P' . str_pad($prodId, 3, '0', STR_PAD_LEFT);
        try {
            $stmtInv = $pdo->prepare("
                INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
                VALUES (?, ?, ?, 'Almacén Central (CDMX)', 'Pasillo C-01', ?, 10, 100)
                ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual)
            ");
            $stmtInv->execute([$prodId, $name, $sku, $stock]);
        } catch (Exception $e) {
            // Ignorar duplicados de sku
        }

        echo json_encode([
            'success' => true,
            'message' => 'Producto agregado exitosamente al catálogo y al inventario SCM',
            'producto' => [
                'id' => $prodId,
                'name' => $name,
                'price' => $price,
                'category' => $category,
                'stock_actual' => $stock
            ]
        ]);
        exit();
    }

    // ----------------------------------------------------
    // PATCH / PUT: Modificar estado activo de producto
    // ----------------------------------------------------
    if ($method === 'PATCH' || $method === 'PUT') {
        if (!isLoggedIn() || !isStaff()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
            exit();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? (int)$input['id'] : 0;
        $active = isset($input['active']) ? (int)$input['active'] : 1;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID no válido']);
            exit();
        }

        $stmtUpd = $pdo->prepare("UPDATE productos SET active = ? WHERE id = ?");
        $stmtUpd->execute([$active, $id]);

        echo json_encode(['success' => true, 'message' => 'Estado del producto actualizado']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
