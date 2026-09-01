<?php

require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');


// =====================================================
// SOLO POST
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
    // VALIDAR CAMPOS
    // =================================================

    if (
        empty($input['username']) ||
        empty($input['email']) ||
        empty($input['password'])
    ) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'Faltan datos requeridos'
        ]);

        exit();
    }


    // =================================================
    // LIMPIAR DATOS
    // =================================================

    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];


    // =================================================
    // VALIDAR USUARIO
    // =================================================

    if (strlen($username) < 3) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'El nombre de usuario debe tener al menos 3 caracteres'
        ]);

        exit();
    }


    if (strlen($username) > 50) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'El nombre de usuario es demasiado largo'
        ]);

        exit();
    }


    // =================================================
    // VALIDAR EMAIL
    // =================================================

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'Formato de email inválido'
        ]);

        exit();
    }


    // =================================================
    // VALIDAR CONTRASEÑA
    // =================================================

    if (strlen($password) < 6) {

        http_response_code(400);

        echo json_encode([
            'success' => false,
            'error' => 'La contraseña debe tener al menos 6 caracteres'
        ]);

        exit();
    }


    // =================================================
    // CONEXIÓN
    // =================================================

    $pdo = getDBConnection();


    // =================================================
    // VERIFICAR SI YA EXISTE
    // =================================================

    $stmt = $pdo->prepare("
        SELECT id, username, email
        FROM usuarios
        WHERE username = ?
           OR email = ?
        LIMIT 1
    ");

    $stmt->execute([
        $username,
        $email
    ]);

    $existingUser = $stmt->fetch();


    if ($existingUser) {

        http_response_code(409);

        if (
            strtolower($existingUser['username']) ===
            strtolower($username)
        ) {

            $message =
                'El nombre de usuario ya existe';

        } else {

            $message =
                'El correo electrónico ya está registrado';
        }


        echo json_encode([
            'success' => false,
            'error' => $message
        ]);

        exit();
    }


    // =================================================
    // HASH DE CONTRASEÑA
    // =================================================

    $password_hash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    // =================================================
    // INICIAR TRANSACCIÓN
    // =================================================

    $pdo->beginTransaction();


    // =================================================
    // INSERTAR USUARIO
    // =================================================
    //
    // IMPORTANTE:
    // El registro público SIEMPRE será cliente.
    // No aceptamos role enviado desde JavaScript.
    //

    $stmt = $pdo->prepare("
        INSERT INTO usuarios
        (
            username,
            email,
            password_hash,
            role
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'cliente'
        )
    ");

    $stmt->execute([
        $username,
        $email,
        $password_hash
    ]);


    // Obtener ID generado

    $user_id = $pdo->lastInsertId();


    // =================================================
    // INSERTAR CLIENTE
    // =================================================

    $stmt = $pdo->prepare("
        INSERT INTO clientes
        (
            usuario_id,
            nombre,
            correo,
            estado,
            etapa_crm
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'activo',
            'Prospecto'
        )
    ");

    $stmt->execute([
        $user_id,
        $username,
        $email
    ]);


    // Obtener ID del cliente

    $cliente_id = $pdo->lastInsertId();


    // =================================================
    // CONFIRMAR TRANSACCIÓN
    // =================================================

    $pdo->commit();


    // =================================================
    // OBTENER USUARIO CREADO
    // =================================================

    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            email,
            role,
            created_at
        FROM usuarios
        WHERE id = ?
    ");

    $stmt->execute([
        $user_id
    ]);

    $user = $stmt->fetch();


    // =================================================
    // RESPUESTA
    // =================================================

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Usuario registrado exitosamente',
        'user' => $user,
        'cliente_id' => $cliente_id
    ]);


    // =====================================================
// ERROR MYSQL
// =====================================================

} catch (PDOException $e) {

    // Si había una transacción activa, revertirla

    if (
        isset($pdo) &&
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos'
    ]);


    // =====================================================
// ERROR GENERAL
// =====================================================

} catch (Exception $e) {

    if (
        isset($pdo) &&
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor'
    ]);
}

?>