<?php
// ==============================================================
// ENDPOINT: php/guardar_cliente.php
// Crea clientes desde ERP y módulos externos
// ==============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'lym';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $nombre = trim($input['nombre'] ?? '');
    $empresa = trim($input['empresa'] ?? '');
    $correo = trim($input['email'] ?? $input['correo'] ?? '');
    $telefono = trim($input['telefono'] ?? '');
    $etapa = trim($input['etapa'] ?? $input['etapa_crm'] ?? 'prospecto');

    if (empty($nombre)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'success' => false,
            'message' => 'El nombre del cliente es obligatorio'
        ]);
        exit();
    }

    if (empty($correo)) {
        $correo = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nombre)) . '@cliente.lym';
    }

    $stmt = $pdo->prepare("
        INSERT INTO clientes (nombre, correo, telefono, empresa, estado, etapa_crm, fecha_registro)
        VALUES (?, ?, ?, ?, 'activo', ?, NOW())
    ");
    $stmt->execute([$nombre, $correo, $telefono, $empresa, $etapa]);
    $nuevoId = (int)$pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'success' => true,
        'id' => $nuevoId,
        'message' => 'Cliente guardado correctamente'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Error interno: ' . $ex->getMessage()
    ]);
}
