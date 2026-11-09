<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = getDBConnection();
    
    // Obtener todos los clientes registrados
    $stmt = $pdo->query("
        SELECT 
            c.id, 
            c.nombre, 
            c.correo, 
            COALESCE(c.empresa, 'Sin Empresa') AS empresa,
            COALESCE(u.username, '') AS username
        FROM clientes c
        LEFT JOIN usuarios u ON u.id = c.usuario_id
        ORDER BY c.nombre ASC, c.id DESC
    ");
    
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'clientes' => array_map(function($c) {
            return [
                'id' => (int)$c['id'],
                'nombre' => !empty($c['nombre']) ? $c['nombre'] : (!empty($c['username']) ? $c['username'] : 'Cliente #' . $c['id']),
                'correo' => $c['correo'] ?: '',
                'empresa' => $c['empresa'] ?: 'Sin Empresa'
            ];
        }, $clientes)
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
