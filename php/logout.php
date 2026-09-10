<?php
require_once __DIR__ . '/../includes/config.php';

try {
    // Destruir todas las variables de sesión
    $_SESSION = array();
    
    // Destruir la cookie de sesión si existe
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir la sesión
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
              || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || $_SERVER['REQUEST_METHOD'] === 'POST';

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ]);
        exit();
    } else {
        // Redirigir suavemente al index principal
        header('Location: ../index.php');
        exit();
    }
    
} catch (Exception $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
    } else {
        header('Location: ../index.php');
    }
    exit();
}
?>
