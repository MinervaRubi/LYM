<?php
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado. Debes iniciar sesión como administrador.']);
    exit;
}

try {
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.nombre, c.correo, c.telefono, c.estado, c.etapa_crm, c.created_at,
                    u.username, u.email AS email_usuario
             FROM clientes c
             LEFT JOIN usuarios u ON u.id = c.usuario_id
             ORDER BY c.id DESC"
        );
        $stmt->execute();

        $clientes = $stmt->fetchAll();

        foreach ($clientes as &$cliente) {
            $cliente['nombre'] = $cliente['nombre'] ?? $cliente['username'] ?? 'Sin nombre';
            $cliente['correo'] = $cliente['correo'] ?? $cliente['email_usuario'] ?? 'Sin correo';
        }

        echo json_encode([
            'success' => true,
            'clientes' => $clientes
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Falta el ID del cliente.']);
            exit;
        }

        $id = (int) $input['id'];
        $updates = [];
        $params = [];

        if (isset($input['estado'])) {
            $updates[] = 'estado = ?';
            $params[] = $input['estado'];
        }

        if (isset($input['etapa_crm'])) {
            $updates[] = 'etapa_crm = ?';
            $params[] = $input['etapa_crm'];
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No hay campos para actualizar.']);
            exit;
        }

        $params[] = $id;
        $sql = 'UPDATE clientes SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Cliente actualizado correctamente.']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Falta el ID del cliente.']);
            exit;
        }

        $id = (int) $input['id'];
        $stmt = $pdo->prepare('DELETE FROM clientes WHERE id = ?');
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Cliente eliminado correctamente.'
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
