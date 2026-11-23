<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();

    // 1. Asegurar que la columna 'role' permita el valor 'soporte'
    // Modificar a VARCHAR(50) para total compatibilidad sin errores de ENUM
    try {
        $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'cliente'");
    } catch (Exception $eCol) {
        // Puede que ya sea VARCHAR o no requiera modificación
    }

    $username = 'soporte';
    $password = 'soporte123';
    $email = 'soporte@lym.com';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $role = 'admin';

    // 2. Verificar si ya existe el usuario
    $stmt = $pdo->prepare("SELECT id, username FROM usuarios WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)");
    $stmt->execute([$username, $email]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE usuarios SET password_hash = ?, role = ?, email = ? WHERE id = ?");
        $update->execute([$hash, $role, $email, $user['id']]);
        $userId = $user['id'];
        $msg = "Usuario 'soporte' actualizado con rol 'soporte' y contraseña 'soporte123'.";
    } else {
        $insert = $pdo->prepare("INSERT INTO usuarios (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        $insert->execute([$username, $email, $hash, $role]);
        $userId = $pdo->lastInsertId();
        $msg = "Usuario 'soporte' creado exitosamente con rol 'soporte' y contraseña 'soporte123'.";
    }

    // 3. Vincular o crear registro en la tabla clientes
    $stmtCli = $pdo->prepare("SELECT id FROM clientes WHERE usuario_id = ? OR LOWER(correo) = LOWER(?)");
    $stmtCli->execute([$userId, $email]);
    $cliente = $stmtCli->fetch();

    if (!$cliente) {
        $insCli = $pdo->prepare("INSERT INTO clientes (usuario_id, nombre, correo, estado, etapa_crm) VALUES (?, ?, ?, 'activo', 'Activo')");
        $insCli->execute([$userId, 'Soporte Técnico LYM', $email]);
    } else {
        $pdo->prepare("UPDATE clientes SET usuario_id = ? WHERE id = ?")->execute([$userId, $cliente['id']]);
    }

    echo json_encode([
        'success' => true,
        'message' => $msg,
        'credentials' => [
            'username' => 'soporte',
            'password' => 'soporte123',
            'email' => $email,
            'role' => $role
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
