<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método no permitido. Utiliza POST.']);
        exit();
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $nombre = isset($input['nombre']) ? trim(cleanInput($input['nombre'])) : '';
    $correo = isset($input['correo']) ? trim(cleanInput($input['correo'])) : (isset($input['email']) ? trim(cleanInput($input['email'])) : '');
    $telefono = isset($input['telefono']) ? trim(cleanInput($input['telefono'])) : '';
    $asunto = isset($input['asunto']) ? trim(cleanInput($input['asunto'])) : (isset($input['producto']) ? trim(cleanInput($input['producto'])) : 'Consulta General');
    $mensaje = isset($input['mensaje']) ? trim(cleanInput($input['mensaje'])) : (isset($input['descripcion']) ? trim(cleanInput($input['descripcion'])) : '');

    if (empty($nombre) || empty($correo) || empty($mensaje)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nombre, correo y mensaje son obligatorios.']);
        exit();
    }

    $clienteId = null;

    // Si el usuario tiene sesión activa de cliente
    if (isLoggedIn() && isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        $stmtC = $pdo->prepare("SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1");
        $stmtC->execute([$userId]);
        $clienteId = $stmtC->fetchColumn();
    }

    // Buscar por correo si no se halló por sesión
    if (!$clienteId) {
        $stmtC = $pdo->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
        $stmtC->execute([$correo]);
        $clienteId = $stmtC->fetchColumn();
    }

    // Si no existe, crear el registro de cliente prospecto
    if (!$clienteId) {
        $stmtInsC = $pdo->prepare("
            INSERT INTO clientes (nombre, correo, telefono, estado, etapa_crm, fecha_registro)
            VALUES (?, ?, ?, 'activo', 'Prospecto', NOW())
        ");
        $stmtInsC->execute([$nombre, $correo, $telefono]);
        $clienteId = (int)$pdo->lastInsertId();
    }

    // Insertar como tarea pendiente en interacciones para el administrador
    $descTarea = "📩 MENSAJE DE CLIENTE: [" . $asunto . "]\n" . $mensaje . "\n\nContactar a: " . $nombre . " (" . $correo . ($telefono ? " / Tel: " . $telefono : "") . ")";
    
    $stmtTask = $pdo->prepare("
        INSERT INTO interacciones (cliente_id, usuario_id, tipo, descripcion, estado, prioridad, fecha)
        VALUES (?, 1, 'correo', ?, 'pendiente', 'alta', NOW())
    ");
    $stmtTask->execute([$clienteId, $descTarea]);
    $taskId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => '¡Tu mensaje ha sido enviado correctamente! Ha quedado registrado como tarea pendiente en la agenda del administrador.',
        'task_id' => $taskId,
        'cliente_id' => $clienteId
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de servidor: ' . $e->getMessage()]);
}
