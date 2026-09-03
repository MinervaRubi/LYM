<?php
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Acceso denegado. Debes iniciar sesión como administrador.'
    ]);
    exit;
}

try {
    $pdo = getDBConnection();

    $stats = [
        'totalClientes' => 0,
        'clientesActivos' => 0,
        'clientesProspectos' => 0,
        'totalProductos' => 0,
        'productosActivos' => 0,
        'totalPedidos' => 0,
        'pedidosPendientes' => 0,
        'totalInteracciones' => 0,
        'totalPagos' => 0,
    ];

    $queries = [
        'totalClientes' => 'SELECT COUNT(*) AS total FROM clientes',
        'clientesActivos' => "SELECT COUNT(*) AS total FROM clientes WHERE estado = 'activo'",
        'clientesProspectos' => "SELECT COUNT(*) AS total FROM clientes WHERE estado = 'prospecto'",
        'totalProductos' => 'SELECT COUNT(*) AS total FROM productos',
        'productosActivos' => "SELECT COUNT(*) AS total FROM productos WHERE active = 1",
        'totalPedidos' => 'SELECT COUNT(*) AS total FROM pedidos',
        'pedidosPendientes' => "SELECT COUNT(*) AS total FROM pedidos WHERE estado = 'pendiente'",
        'totalInteracciones' => 'SELECT COUNT(*) AS total FROM interacciones',
        'totalPagos' => 'SELECT COUNT(*) AS total FROM pagos',
    ];

    foreach ($queries as $key => $sql) {
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch();
        $stats[$key] = (int) ($row['total'] ?? 0);
    }

    $clientesStmt = $pdo->query(
        "SELECT c.id, c.nombre, c.correo, c.telefono, c.estado, c.etapa_crm, c.created_at,
                u.username, u.email AS email_usuario
         FROM clientes c
         LEFT JOIN usuarios u ON u.id = c.usuario_id
         ORDER BY c.id DESC
         LIMIT 10"
    );
    $clientes = $clientesStmt->fetchAll();

    foreach ($clientes as &$cliente) {
        $cliente['nombre'] = $cliente['nombre'] ?? $cliente['username'] ?? 'Sin nombre';
        $cliente['correo'] = $cliente['correo'] ?? $cliente['email_usuario'] ?? 'Sin correo';
    }

    $productosStmt = $pdo->query(
        "SELECT * FROM productos ORDER BY id DESC LIMIT 10"
    );
    $productos = $productosStmt->fetchAll();

    $pedidosStmt = $pdo->query(
        "SELECT p.id, c.nombre AS cliente, p.total, p.estado, p.created_at
         FROM pedidos p
         LEFT JOIN clientes c ON c.id = p.cliente_id
         ORDER BY p.id DESC
         LIMIT 10"
    );
    $pedidos = $pedidosStmt->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'clientes' => $clientes,
        'productos' => $productos,
        'pedidos' => $pedidos
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}
