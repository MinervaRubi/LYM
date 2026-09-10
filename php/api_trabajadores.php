<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Solo Administradores pueden gestionar trabajadores
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Se requieren permisos de Administrador.']);
    exit();
}

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Listar usuarios con role = 'trabajador'
        $stmt = $pdo->query("
            SELECT id, username, email, role, created_at
            FROM usuarios
            WHERE role = 'trabajador'
            ORDER BY id DESC
        ");
        $trabajadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'trabajadores' => $trabajadores
        ]);
        exit();
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        $username = isset($input['username']) ? trim(cleanInput($input['username'])) : '';
        $email = isset($input['email']) ? trim(cleanInput($input['email'])) : '';
        $password = isset($input['password']) ? $input['password'] : '';

        if (empty($username) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nombre de usuario, correo y contraseña son obligatorios']);
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Correo electrónico inválido']);
            exit();
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
            exit();
        }

        // Verificar si ya existe usuario o correo
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? OR email = ? LIMIT 1");
        $stmtCheck->execute([$username, $email]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'El nombre de usuario o correo ya está registrado']);
            exit();
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Insertar usuario trabajador
        $stmtIns = $pdo->prepare("
            INSERT INTO usuarios (username, email, password_hash, role)
            VALUES (?, ?, ?, 'trabajador')
        ");
        $stmtIns->execute([$username, $email, $password_hash]);
        $workerId = (int)$pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Trabajador registrado exitosamente',
            'trabajador' => [
                'id' => $workerId,
                'username' => $username,
                'email' => $email,
                'role' => 'trabajador'
            ]
        ]);
        exit();
    }

    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = isset($input['id']) ? (int)$input['id'] : 0;

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID de trabajador no válido']);
            exit();
        }

        $stmtDel = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND role = 'trabajador'");
        $stmtDel->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Trabajador eliminado correctamente']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
