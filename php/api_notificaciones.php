<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Obtener notificaciones del usuario
        $stmt = $pdo->prepare("
            SELECT id, titulo, mensaje, tipo, leido, fecha
            FROM notificaciones
            WHERE usuario_id = ?
            ORDER BY fecha DESC, id DESC
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $noLeidasCount = 0;
        foreach ($notificaciones as $n) {
            if ($n['leido'] == 0) {
                $noLeidasCount++;
            }
        }

        echo json_encode([
            'success' => true,
            'notificaciones' => $notificaciones,
            'no_leidas' => $noLeidasCount
        ]);
        exit();
    }

    if ($method === 'POST' || $method === 'PATCH') {
        $input = json_decode(file_get_contents('php://input'), true);
        $notifId = isset($input['id']) ? (int)$input['id'] : 0;

        if ($notifId > 0) {
            // Marcar una como leída
            $stmt = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$notifId, $userId]);
        } else {
            // Marcar todas como leídas
            $stmt = $pdo->prepare("UPDATE notificaciones SET leido = 1 WHERE usuario_id = ?");
            $stmt->execute([$userId]);
        }

        echo json_encode(['success' => true, 'message' => 'Notificación(es) actualizada(s)']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
