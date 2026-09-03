<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

try {
    $pdo = getDBConnection();
    
    // Obtener productos activos
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE active = 1 ORDER BY id");
    $stmt->execute();
    $productos = $stmt->fetchAll();
    
    // Obtener paquetes activos
    $stmt = $pdo->prepare("SELECT * FROM paquetes WHERE active = 1 ORDER BY featured DESC, id");
    $stmt->execute();
    $paquetes = $stmt->fetchAll();
    
    // Procesar features y items como arrays
    foreach ($productos as &$producto) {
        $producto['features'] = json_decode($producto['features'], true) ?: [];
    }
    
    foreach ($paquetes as &$paquete) {
        $paquete['items'] = json_decode($paquete['items'], true) ?: [];
    }
    
    echo json_encode([
        'success' => true,
        'productos' => $productos,
        'paquetes' => $paquetes
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
