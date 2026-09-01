<?php

require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');


// =====================================================
// SOLO PERMITIR POST
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);

    exit();
}


try {

    // =================================================
    // OBTENER DATOS
    // =================================================

    $input = json_decode(
        file_get_contents('php://input'),
        true
    );


    // Compatibilidad con formularios normales

    if (!is_array($input)) {
        $input = $_POST;
    }


    // =================================================
    // VALIDAR DATOS
    // =================================================

    if (
        empty($input['username']) ||
        empty($input['password'])
    ) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'Faltan usuario y contraseña'
        ]);

        exit();
    }


    // =================================================
    // LIMPIAR USUARIO
    // =================================================

    $username = trim($input['username']);
    $password = $input['password'];


    // =================================================
    // CONEXIÓN
    // =================================================

    $pdo = getDBConnection();


    // =================================================
    // BUSCAR USUARIO
    // =================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            email,
            password_hash,
            role
        FROM usuarios
        WHERE username = ?
           OR email = ?
        LIMIT 1
    ");

    $stmt->execute([
        $username,
        $username
    ]);

    $user = $stmt->fetch();


    // =================================================
    // VERIFICAR CREDENCIALES
    // =================================================

    if (
        !$user ||
        !password_verify(
            $password,
            $user['password_hash']
        )
    ) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'error' => 'Usuario o contraseña incorrectos'
        ]);

        exit();
    }


    // =================================================
    // REGENERAR SESIÓN
    // =================================================

    session_regenerate_id(true);


    // =================================================
    // CREAR SESIÓN
    // =================================================

    $_SESSION['user_id'] = (int) $user['id'];

    $_SESSION['username'] = $user['username'];

    $_SESSION['user_role'] = $user['role'];

    $_SESSION['logged_in'] = true;


    // =================================================
    // RESPUESTA
    // =================================================

    echo json_encode([
        'success' => true,

        'message' => 'Inicio de sesión exitoso',

        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);

    exit();


} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos'
    ]);

    exit();


} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);

    exit();
}

?>