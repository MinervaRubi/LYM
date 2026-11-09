<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión para cambiar tu contraseña']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;

    $passwordActual = isset($input['password_actual']) ? $input['password_actual'] : '';
    $passwordNueva = isset($input['password_nueva']) ? $input['password_nueva'] : '';

    if (empty($passwordActual) || empty($passwordNueva)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La contraseña actual y la nueva contraseña son obligatorias']);
        exit();
    }

    if (strlen($passwordNueva) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La nueva contraseña debe tener al menos 6 caracteres']);
        exit();
    }

    $userId = (int)$_SESSION['user_id'];
    $pdo = getDBConnection();

    // Obtener hash actual de la BD
    $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $currentHash = $stmt->fetchColumn();

    if (!$currentHash || !password_verify($passwordActual, $currentHash)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La contraseña actual es incorrecta']);
        exit();
    }

    // Actualizar hash
    $newHash = password_hash($passwordNueva, PASSWORD_DEFAULT);
    $stmtUpd = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
    $stmtUpd->execute([$newHash, $userId]);

    echo json_encode([
        'success' => true,
        'message' => '¡Tu contraseña ha sido actualizada exitosamente!'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
