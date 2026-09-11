<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = getDBConnection();

    $codigo = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;
        $codigo = isset($input['codigo']) ? trim(cleanInput($input['codigo'])) : '';
    } else {
        $codigo = isset($_GET['codigo']) ? trim(cleanInput($_GET['codigo'])) : '';
    }

    if (empty($codigo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Por favor ingresa un código de cupón.']);
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT id, cliente_id, porcentaje, motivo, estado, codigo_cupon
        FROM solicitudes_descuento
        WHERE UPPER(TRIM(codigo_cupon)) = UPPER(?) AND estado = 'aprobado'
        LIMIT 1
    ");
    $stmt->execute([$codigo]);
    $cupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cupon) {
        echo json_encode([
            'success' => true,
            'cupon' => [
                'codigo' => $cupon['codigo_cupon'],
                'porcentaje' => (float)$cupon['porcentaje'],
                'motivo' => $cupon['motivo']
            ],
            'message' => "¡Cupón de {$cupon['porcentaje']}% de descuento aplicado con éxito!"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Cupón no válido o no se encuentra aprobado por el administrador.'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error al validar cupón: ' . $e->getMessage()]);
}
