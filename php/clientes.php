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

    $stmt = $pdo->query('SELECT id, nombre, correo, telefono, estado FROM clientes ORDER BY id');
    $clientes = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'clientes' => $clientes
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al consultar clientes: ' . $e->getMessage()
    ]);
}
?>
