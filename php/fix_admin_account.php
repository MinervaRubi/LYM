<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();
    
    $username = 'juanlalo';
    $password = '123456';
    $email = 'juanlalo@gmail.com';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Buscar si existe el usuario por username o email
    $stmt = $pdo->prepare("SELECT id, username FROM usuarios WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
    $stmt->execute([$username, $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Actualizar contraseña y rol de admin
        $update = $pdo->prepare("UPDATE usuarios SET password_hash = ?, role = 'admin' WHERE id = ?");
        $update->execute([$hash, $user['id']]);
        $user_id = $user['id'];
        $msg = "Usuario 'juanlalo' actualizado a rol admin con contraseña '123456'.";
    } else {
        // Insertar nuevo usuario admin
        $insert = $pdo->prepare("INSERT INTO usuarios (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
        $insert->execute([$username, $email, $hash]);
        $user_id = $pdo->lastInsertId();
        $msg = "Usuario 'juanlalo' creado con rol admin y contraseña '123456'.";
    }

    // Verificar / crear entrada en la tabla clientes
    $stmtCli = $pdo->prepare("SELECT id FROM clientes WHERE usuario_id = ? OR correo = ?");
    $stmtCli->execute([$user_id, $email]);
    $cliente = $stmtCli->fetch();

    if (!$cliente) {
        $insCli = $pdo->prepare("INSERT INTO clientes (usuario_id, nombre, correo, estado, etapa_crm) VALUES (?, ?, ?, 'activo', 'Activo')");
        $insCli->execute([$user_id, 'Juan Lalo (Admin)', $email]);
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'user' => [
            'username' => 'juanlalo',
            'role' => 'admin',
            'email' => $email
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
