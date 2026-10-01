<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = getDBConnection();
    
    // Obtener pedidos reales de la base de datos
    $sql = "
        SELECT p.id, p.cliente_id, c.nombre as cliente_nombre, p.estado, p.total, p.created_at,
               GROUP_CONCAT(CONCAT(dp.cantidad, ' ', dp.nombre_producto) SEPARATOR '||') as items
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN detalle_pedido dp ON p.id = dp.pedido_id
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 10
    ";
    $pedidos = $pdo->query($sql)->fetchAll();
    
    $lista = [];
    foreach ($pedidos as $row) {
        $prods = !empty($row['items']) ? explode('||', $row['items']) : ['Productos personalizados'];
        $estado = strtolower($row['estado']);
        $clase = 'entregado';
        if ($estado === 'en_proceso' || $estado === 'pendiente') $clase = 'preparacion';
        elseif ($estado === 'enviado') $clase = 'enviado';
        
        $lista[] = [
            'folio' => '#LYM-10' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
            'fecha' => date('d/m/Y', strtotime($row['created_at'])),
            'total' => (float)$row['total'],
            'estado' => ucfirst(str_replace('_', ' ', $row['estado'])),
            'clase' => $clase,
            'productos' => $prods
        ];
    }
    
    echo json_encode(['success' => true, 'compras' => $lista]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
