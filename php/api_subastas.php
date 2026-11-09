<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();

    if ($method === 'GET') {
        $subId = isset($_GET['id']) ? (int)$_GET['id'] : 1;
        
        $stmt = $pdo->prepare("SELECT * FROM subastas WHERE id = ? LIMIT 1");
        $stmt->execute([$subId]);
        $subasta = $stmt->fetch();

        if (!$subasta) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Subasta no encontrada']);
            exit();
        }

        // Obtener historial de pujas
        $stmtPujas = $pdo->prepare("SELECT monto, nombre_postor, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') as fecha FROM subasta_pujas WHERE subasta_id = ? ORDER BY id ASC");
        $stmtPujas->execute([$subId]);
        $historial = $stmtPujas->fetchAll();

        echo json_encode([
            'success' => true,
            'subasta' => $subasta,
            'historial' => $historial
        ]);
        exit();
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $subId = (int)($input['subasta_id'] ?? 1);
        $monto = floatval($input['monto'] ?? 0);
        $nombrePostor = trim($input['nombre_postor'] ?? '');
        $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;

        if (empty($nombrePostor)) {
            $nombrePostor = isLoggedIn() && isset($_SESSION['username']) ? $_SESSION['username'] : 'Usuario LYM';
        }

        $stmt = $pdo->prepare("SELECT * FROM subastas WHERE id = ? LIMIT 1");
        $stmt->execute([$subId]);
        $subasta = $stmt->fetch();

        if (!$subasta) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Subasta no encontrada']);
            exit();
        }

        $precioActual = floatval($subasta['precio_actual']);
        $incrementoMin = floatval($subasta['incremento_minimo']);

        if ($monto < ($precioActual + $incrementoMin)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "La puja mínima permitida es $" . ($precioActual + $incrementoMin)]);
            exit();
        }

        // Actualizar precio actual de la subasta
        $stmtUp = $pdo->prepare("UPDATE subastas SET precio_actual = ? WHERE id = ?");
        $stmtUp->execute([$monto, $subId]);

        // Insertar puja
        $stmtIns = $pdo->prepare("INSERT INTO subasta_pujas (subasta_id, usuario_id, nombre_postor, monto) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$subId, $userId, $nombrePostor, $monto]);

        echo json_encode(['success' => true, 'nuevo_precio' => $monto]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
