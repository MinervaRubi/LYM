<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT id, usuario_id, nombre, titulo, texto, likes, DATE_FORMAT(created_at, '%Y-%m-%d') as fecha FROM comunidad_comentarios ORDER BY id ASC");
        $comentarios = $stmt->fetchAll();
        echo json_encode(['success' => true, 'comentarios' => $comentarios]);
        exit();
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? 'crear';

        if ($action === 'like') {
            $id = (int)($input['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE comunidad_comentarios SET likes = likes + 1 WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
                exit();
            }
        }

        $nombre = trim($input['nombre'] ?? '');
        $titulo = trim($input['titulo'] ?? '');
        $texto = trim($input['texto'] ?? '');
        $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : null;

        if (empty($nombre) || empty($texto)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nombre y comentario son obligatorios.']);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO comunidad_comentarios (usuario_id, nombre, titulo, texto, likes) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$userId, $nombre, $titulo, $texto]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode(['success' => true, 'id' => $newId]);
        exit();
    }

    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM comunidad_comentarios WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit();
        }
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
