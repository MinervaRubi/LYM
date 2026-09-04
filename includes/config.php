<?php

// =====================================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// =====================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'lym');


// =====================================================
// CONFIGURACIÓN DE SESIONES
// =====================================================

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);


// =====================================================
// INICIAR SESIÓN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// =====================================================
// CONEXIÓN A MYSQL
// =====================================================

function getDBConnection()
{
    try {

        $pdo = new PDO(
            "mysql:host=" . DB_HOST .
            ";dbname=" . DB_NAME .
            ";charset=utf8mb4",

            DB_USER,
            DB_PASS,

            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        return $pdo;

    } catch (PDOException $e) {

        http_response_code(500);

        die(
            "Error de conexión a la base de datos: "
            . $e->getMessage()
        );
    }
}


// =====================================================
// VERIFICAR SESIÓN
// =====================================================

function isLoggedIn()
{
    return isset($_SESSION['user_id'])
        && !empty($_SESSION['user_id']);
}


// =====================================================
// VERIFICAR ADMINISTRADOR
// =====================================================

function isAdmin()
{
    return isLoggedIn()
        && isset($_SESSION['user_role'])
        && $_SESSION['user_role'] === 'admin';
}


// =====================================================
// OBTENER USUARIO ACTUAL
// =====================================================

function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT
            id,
            username,
            email,
            role
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $_SESSION['user_id']
    ]);

    return $stmt->fetch();
}


// =====================================================
// REDIRECCIÓN
// =====================================================

function redirect($url)
{
    header("Location: $url");
    exit();
}


// =====================================================
// LIMPIAR ENTRADA
// =====================================================

function cleanInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);

    return $data;
}


// =====================================================
// TOKEN CSRF
// =====================================================

function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


// =====================================================
// VERIFICAR TOKEN CSRF
// =====================================================

function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token'])
        && hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
}

?>