<?php
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $pdo = getDBConnection();

    if (!isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Se requieren permisos de administrador.']);
        exit;
    }

    $metrics = [
        'clientes' => (int) $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn(),
        'productos' => (int) $pdo->query('SELECT COUNT(*) FROM productos WHERE active = 1')->fetchColumn(),
        'ingresos' => (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE estado <> "cancelado"')->fetchColumn(),
        'pagos' => (int) $pdo->query('SELECT COUNT(*) FROM pagos WHERE estado = "aprobado"')->fetchColumn()
    ];

    $relaciones = $pdo->query(
        'SELECT p.id AS pedido_id, c.nombre AS cliente, p.fecha, p.total, p.estado,
                prod.name AS producto, dp.cantidad, dp.subtotal,
                pag.monto AS pago_monto, pag.metodo AS pago_metodo, pag.estado AS pago_estado
         FROM pedidos p
         INNER JOIN clientes c ON c.id = p.cliente_id
         LEFT JOIN detalle_pedido dp ON dp.pedido_id = p.id
         LEFT JOIN productos prod ON prod.id = dp.producto_id
         LEFT JOIN pagos pag ON pag.pedido_id = p.id
         ORDER BY p.id DESC, dp.id ASC'
    )->fetchAll();

    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
        'relaciones' => $relaciones
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo cargar el dashboard: ' . $e->getMessage()
    ]);
}
?>
