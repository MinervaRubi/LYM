<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $pdo = getDBConnection();

    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT id, usuario_id, titulo, contenido, precio, imagen, created_at FROM publicaciones ORDER BY id DESC");
        $posts = $stmt->fetchAll();
        echo json_encode(['success' => true, 'posts' => $posts]);
        exit();
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $titulo = trim($input['titulo'] ?? '');
        $contenido = trim($input['contenido'] ?? '');
        $precio = floatval($input['precio'] ?? 0);
        $imagen = $input['imagen'] ?? 'imagenes/default.png';
        $userId = isLoggedIn() ? (int)$_SESSION['user_id'] : 1;

        if (empty($titulo) || empty($contenido) || $precio <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Título, contenido y precio válidos son obligatorios.']);
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO publicaciones (usuario_id, titulo, contenido, precio, imagen) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $titulo, $contenido, $precio, $imagen]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode(['success' => true, 'message' => 'Publicación creada exitosamente.', 'id' => $newId]);
        exit();
    }

    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? 0);
        $titulo = trim($input['titulo'] ?? '');
        $contenido = trim($input['contenido'] ?? '');
        $precio = floatval($input['precio'] ?? 0);
        $imagen = $input['imagen'] ?? null;

        if ($id <= 0 || empty($titulo) || empty($contenido)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos incompletos para actualizar']);
            exit();
        }

        if ($imagen) {
            $stmt = $pdo->prepare("UPDATE publicaciones SET titulo = ?, contenido = ?, precio = ?, imagen = ? WHERE id = ?");
            $stmt->execute([$titulo, $contenido, $precio, $imagen, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE publicaciones SET titulo = ?, contenido = ?, precio = ? WHERE id = ?");
            $stmt->execute([$titulo, $contenido, $precio, $id]);
        }

        echo json_encode(['success' => true, 'message' => 'Publicación actualizada correctamente.']);
        exit();
    }

    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit();
        }

        $stmt = $pdo->prepare("DELETE FROM publicaciones WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Publicación eliminada correctamente.']);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
