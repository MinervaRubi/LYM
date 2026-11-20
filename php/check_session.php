<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

try {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        
        if ($user) {
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            // Obtener datos del cliente vinculado
            $pdo = getDBConnection();
            $stmtCli = $pdo->prepare("SELECT id, nombre, saldo_monedero, direccion FROM clientes WHERE usuario_id = ? LIMIT 1");
            $stmtCli->execute([(int)$user['id']]);
            $cliRow = $stmtCli->fetch(PDO::FETCH_ASSOC);

            if (!$cliRow && !empty($user['email'])) {
                $stmtCliMail = $pdo->prepare("SELECT id, nombre, saldo_monedero, direccion FROM clientes WHERE LOWER(correo) = LOWER(?) LIMIT 1");
                $stmtCliMail->execute([$user['email']]);
                $cliRow = $stmtCliMail->fetch(PDO::FETCH_ASSOC);
                if ($cliRow) {
                    $pdo->prepare("UPDATE clientes SET usuario_id = ? WHERE id = ?")->execute([(int)$user['id'], (int)$cliRow['id']]);
                }
            }

            $clienteIdReal = $cliRow ? (int)$cliRow['id'] : (int)$user['id'];
            $saldoReal = $cliRow ? (float)$cliRow['saldo_monedero'] : 1200.00;

            echo json_encode([
                'authenticated' => true,
                'user' => [
                    'id' => (int)$user['id'],
                    'cliente_id' => $clienteIdReal,
                    'username' => $user['username'],
                    'nombre' => $cliRow['nombre'] ?? $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'saldo_monedero' => $saldoReal,
                    'direccion' => $cliRow['direccion'] ?? ''
                ]
            ]);
        } else {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            echo json_encode(['authenticated' => false]);
        }
    } else {
        echo json_encode(['authenticated' => false]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
