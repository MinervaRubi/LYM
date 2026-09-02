<?php
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    if (!isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Se requieren permisos de administrador.']);
        exit;
    }

    $pdo = getDBConnection();

    $metrics = [
        'clientes' => (int) $pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn(),
        'prospectos' => (int) $pdo->query("SELECT COUNT(*) FROM clientes WHERE etapa_crm = 'Prospecto'")->fetchColumn(),
        'ventas' => (float) $pdo->query('SELECT COALESCE(SUM(total), 0) FROM pedidos')->fetchColumn(),
        'etapaMasAlta' => $pdo->query("SELECT etapa_crm FROM clientes WHERE etapa_crm IS NOT NULL ORDER BY FIELD(etapa_crm, 'Prospecto','Contacto','Cotización','Cliente') DESC LIMIT 1")->fetchColumn() ?: 'Sin datos'
    ];

    $clientes = $pdo->query(
        "SELECT c.id,
                c.nombre,
                c.correo,
                c.telefono,
                c.estado,
                c.etapa_crm,
                u.username,
                COALESCE(COUNT(p.id), 0) AS pedidos,
                COALESCE(SUM(p.total), 0) AS total_vendido,
                MAX(p.fecha) AS ultima_compra
         FROM clientes c
         LEFT JOIN usuarios u ON u.id = c.usuario_id
         LEFT JOIN pedidos p ON p.cliente_id = c.id
         GROUP BY c.id, c.nombre, c.correo, c.telefono, c.estado, c.etapa_crm, u.username
         ORDER BY c.id DESC"
    )->fetchAll();

    echo json_encode([
        'success' => true,
        'metrics' => $metrics,
        'clientes' => array_map(function ($cliente) {
            return [
                'id' => (int) $cliente['id'],
                'nombre' => $cliente['nombre'],
                'correo' => $cliente['correo'],
                'telefono' => $cliente['telefono'],
                'estado' => $cliente['estado'],
                'etapa_crm' => $cliente['etapa_crm'],
                'username' => $cliente['username'],
                'pedidos' => (int) $cliente['pedidos'],
                'total_vendido' => (float) $cliente['total_vendido'],
                'ultima_compra' => $cliente['ultima_compra'] ? date('d/m/Y', strtotime($cliente['ultima_compra'])) : null,
            ];
        }, $clientes)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al consultar el CRM: ' . $e->getMessage()
    ]);
}
?>
