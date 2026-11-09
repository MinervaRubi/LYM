<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/scm_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    $pdo = getDBConnection();
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $items = $input['items'] ?? [];
    $totalRecibido = floatval($input['total'] ?? 0);
    $metodo = $input['metodo'] ?? 'tarjeta';
    $notas = trim($input['notas'] ?? 'Pedido generado desde la tienda web');

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'El pedido debe incluir al menos un producto']);
        exit();
    }

    // Identificar o crear cliente asociado
    $clienteId = 3; // Cliente por defecto (Minelokota) si no hay sesión
    if (isLoggedIn() && isset($_SESSION['user_id'])) {
        $uid = (int)$_SESSION['user_id'];
        $stmtC = $pdo->prepare("SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1");
        $stmtC->execute([$uid]);
        $found = $stmtC->fetchColumn();
        if ($found) {
            $clienteId = (int)$found;
        }
    }

    $pdo->beginTransaction();

    // Preparar consultas para productos e inventario
    $stmtGetProdById = $pdo->prepare("SELECT id, name, price, stock_minimo, estrategia_logistica FROM productos WHERE id = ?");
    $stmtFindProdByName = $pdo->prepare("SELECT id, name, price, stock_minimo, estrategia_logistica FROM productos WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
    
    $stmtFindInvByProdId = $pdo->prepare("SELECT id, sku, nombre, stock_actual FROM scm_inventario WHERE producto_id = ? LIMIT 1");
    $stmtFindInvByName = $pdo->prepare("SELECT id, sku, nombre, stock_actual FROM scm_inventario WHERE LOWER(nombre) LIKE LOWER(?) LIMIT 1");
    $stmtUpInv = $pdo->prepare("UPDATE scm_inventario SET stock_actual = GREATEST(0, stock_actual - ?) WHERE id = ?");
    $stmtMov = $pdo->prepare("
        INSERT INTO scm_movimientos (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha) 
        VALUES (?, 'salida', ?, ?, ?, 'Almacén Central', 'Cliente Web', 'Venta Tienda', ?, NOW())
    ");

    $itemsProcesados = [];
    $subtotalCalculado = 0;

    foreach ($items as $item) {
        $nombre = trim($item['producto'] ?? $item['name'] ?? 'Producto personalizado');
        $cant = max(1, intval($item['cantidad'] ?? 1));
        $rawProdId = isset($item['producto_id']) ? intval($item['producto_id']) : 0;
        
        $prodRow = null;
        if ($rawProdId > 0) {
            $stmtGetProdById->execute([$rawProdId]);
            $prodRow = $stmtGetProdById->fetch(PDO::FETCH_ASSOC);
        }
        if (!$prodRow) {
            $stmtFindProdByName->execute(["%$nombre%"]);
            $prodRow = $stmtFindProdByName->fetch(PDO::FETCH_ASSOC);
        }

        $prodId = $prodRow ? (int)$prodRow['id'] : ($rawProdId > 0 ? $rawProdId : null);
        // Tomar el precio desde la base de datos si existe; de lo contrario el enviado
        $precio = ($prodRow && floatval($prodRow['price']) > 0) 
            ? floatval($prodRow['price']) 
            : floatval($item['precio'] ?? $item['price'] ?? 0);
        
        $sub = round($precio * $cant, 2);
        $subtotalCalculado += $sub;

        $itemsProcesados[] = [
            'producto_id' => $prodId,
            'nombre' => $prodRow ? $prodRow['name'] : $nombre,
            'cantidad' => $cant,
            'precio' => $precio,
            'subtotal' => $sub
        ];
    }

    $subtotal = $subtotalCalculado;
    $envio = $subtotal >= 500 ? 0 : 80;
    $total = $subtotal + $envio;
    if ($total <= 0 && $totalRecibido > 0) {
        $total = $totalRecibido;
    }

    // 1. Insertar en tabla pedidos
    $stmtPed = $pdo->prepare("
        INSERT INTO pedidos (cliente_id, estado, subtotal, envio, total, metodo_pago, notas, created_at)
        VALUES (?, 'en_proceso', ?, ?, ?, ?, ?, NOW())
    ");
    $stmtPed->execute([$clienteId, $subtotal, $envio, $total, $metodo, $notas]);
    $pedidoId = (int)$pdo->lastInsertId();

    // 2. Insertar cada ítem en detalle_pedido y descontar inventario SCM
    $stmtDet = $pdo->prepare("
        INSERT INTO detalle_pedido (pedido_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $reposicionesPush = [];

    foreach ($itemsProcesados as $it) {
        $stmtDet->execute([$pedidoId, $it['producto_id'], $it['nombre'], $it['cantidad'], $it['precio'], $it['subtotal']]);

        // Ajuste en inventario SCM por producto_id (o por nombre como respaldo)
        $invRow = null;
        if ($it['producto_id']) {
            $stmtFindInvByProdId->execute([$it['producto_id']]);
            $invRow = $stmtFindInvByProdId->fetch(PDO::FETCH_ASSOC);
        }
        if (!$invRow) {
            $stmtFindInvByName->execute(["%{$it['nombre']}%"]);
            $invRow = $stmtFindInvByName->fetch(PDO::FETCH_ASSOC);
        }

        if ($invRow) {
            $invId = $invRow['id'];
            $invNombre = $invRow['nombre'];
            $stmtUpInv->execute([$it['cantidad'], $invId]);
            
            $folioMov = 'MOV-' . rand(10000, 99999);
            $stmtMov->execute([$folioMov, $it['producto_id'], $invNombre, $it['cantidad'], "Venta en tienda web de pedido #$pedidoId"]);
        }

        // Aplicar regla PUSH si el producto quedó con stock < stock_minimo
        if ($it['producto_id']) {
            $pushRes = aplicarReposicionPush($pdo, $it['producto_id'], 'Venta Tienda');
            if ($pushRes) {
                $reposicionesPush[] = $pushRes;
            }
        }
    }

    // 3. Registrar el pago
    $stmtPago = $pdo->prepare("
        INSERT INTO pagos (pedido_id, metodo, referencia, monto, estado, fecha_pago, created_at)
        VALUES (?, ?, ?, ?, 'aprobado', NOW(), NOW())
    ");
    $ref = strtoupper($metodo) . '-TXN-' . rand(10000, 99999);
    $stmtPago->execute([$pedidoId, $metodo, $ref, $total]);

    // 4. Crear notificación para administración y cliente
    $pdo->prepare("
        INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, leido, fecha)
        VALUES (1, 'Nuevo Pedido Confirmado', ?, 'info', 0, NOW())
    ")->execute(["Se ha registrado el pedido #$pedidoId por un total de $$total MXN."]);

    $pdo->commit();

    $folio = '#LYM-10' . str_pad($pedidoId, 3, '0', STR_PAD_LEFT);

    echo json_encode([
        'success' => true,
        'pedido_id' => $pedidoId,
        'folio' => $folio,
        'total' => $total,
        'reposicion_push' => !empty($reposicionesPush) ? $reposicionesPush[0] : null,
        'reposiciones' => $reposicionesPush,
        'message' => '¡Pedido registrado y procesado exitosamente en MySQL!'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al guardar pedido: ' . $e->getMessage()]);
}
