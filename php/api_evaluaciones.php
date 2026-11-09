<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    // PETICIÓN GET: Obtener evaluaciones y promedios por producto
    if ($method === 'GET') {
        $productoNombre = isset($_GET['producto_nombre']) ? trim($_GET['producto_nombre']) : '';
        $productoId = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

        // 1. Obtener promedios agrupados por producto
        $stmtAvg = $pdo->query("
            SELECT 
                producto_nombre,
                COUNT(*) AS total_reseñas,
                ROUND(AVG(puntuacion_satisfaccion), 1) AS promedio_estrellas
            FROM evaluaciones_crm
            WHERE producto_nombre IS NOT NULL AND producto_nombre != ''
            GROUP BY producto_nombre
        ");
        $promediosRaw = $stmtAvg->fetchAll();
        $promedios = [];
        foreach ($promediosRaw as $row) {
            $promedios[$row['producto_nombre']] = [
                'promedio' => (float)$row['promedio_estrellas'],
                'total' => (int)$row['total_reseñas']
            ];
        }

        // 2. Obtener lista de evaluaciones
        $sql = "
            SELECT 
                e.id,
                e.cliente_id,
                e.producto_id,
                COALESCE(e.producto_nombre, 'Producto General') AS producto_nombre,
                e.puntuacion_satisfaccion,
                e.comentarios,
                e.fecha_evaluacion,
                COALESCE(c.nombre, 'Cliente Registrado') AS cliente,
                COALESCE(c.correo, 'sin_correo@lym.com') AS cliente_correo
            FROM evaluaciones_crm e
            LEFT JOIN clientes c ON c.id = e.cliente_id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($productoNombre)) {
            $sql .= " AND (e.producto_nombre LIKE ? OR e.producto_nombre = ?)";
            $params[] = "%$productoNombre%";
            $params[] = $productoNombre;
        }

        if ($productoId > 0) {
            $sql .= " AND e.producto_id = ?";
            $params[] = $productoId;
        }

        $sql .= " ORDER BY e.fecha_evaluacion DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $evaluaciones = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'promedios' => $promedios,
            'evaluaciones' => array_map(function($ev) {
                return [
                    'id' => (int)$ev['id'],
                    'cliente' => $ev['cliente'],
                    'cliente_correo' => $ev['cliente_correo'],
                    'producto_nombre' => $ev['producto_nombre'],
                    'puntuacion_satisfaccion' => (int)$ev['puntuacion_satisfaccion'],
                    'comentarios' => $ev['comentarios'] ?: '',
                    'fecha_evaluacion' => $ev['fecha_evaluacion']
                ];
            }, $evaluaciones)
        ]);
        exit();
    }

    // PETICIÓN POST: Registrar nueva evaluación de producto
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $productoNombre = isset($input['producto_nombre']) ? trim($input['producto_nombre']) : '';
        $productoId = isset($input['producto_id']) ? (int)$input['producto_id'] : 0;
        $puntuacion = isset($input['puntuacion']) ? (int)$input['puntuacion'] : (isset($input['puntuacion_satisfaccion']) ? (int)$input['puntuacion_satisfaccion'] : 5);
        $comentarios = isset($input['comentarios']) ? trim($input['comentarios']) : (isset($input['comentario']) ? trim($input['comentario']) : '');
        $clienteNombre = isset($input['cliente_nombre']) ? trim($input['cliente_nombre']) : '';
        $clienteCorreo = isset($input['cliente_correo']) ? trim($input['cliente_correo']) : '';

        if (empty($productoNombre)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El nombre del producto es obligatorio.']);
            exit();
        }

        if ($puntuacion < 1 || $puntuacion > 5) {
            $puntuacion = 5;
        }

        // Determinar cliente_id
        $clienteId = null;

        if (isLoggedIn()) {
            $userId = (int)$_SESSION['user_id'];
            $username = $_SESSION['username'] ?? 'Cliente';
            $email = $_SESSION['email'] ?? ($username . '@lym.com');

            $stmtC = $pdo->prepare("SELECT id, nombre, correo FROM clientes WHERE usuario_id = ? LIMIT 1");
            $stmtC->execute([$userId]);
            $c = $stmtC->fetch();
            if ($c) {
                $clienteId = (int)$c['id'];
                // Asegurar que el nombre de la cuenta de usuario sesionada se guarde exactamente
                if (!empty($username)) {
                    $pdo->prepare("UPDATE clientes SET nombre = ? WHERE id = ?")->execute([$username, $clienteId]);
                }
            } else {
                // Crear cliente asociado al usuario autenticado
                $stmtInsC = $pdo->prepare("INSERT INTO clientes (usuario_id, nombre, correo, estado, etapa_crm) VALUES (?, ?, ?, 'activo', 'Cliente')");
                $stmtInsC->execute([$userId, $username, $email]);
                $clienteId = (int)$pdo->lastInsertId();
            }
        }

        // Si no hay cliente_id (usuario visitante no autenticado)
        if (!$clienteId) {
            if (empty($clienteNombre)) {
                $clienteNombre = 'Cliente Anónimo';
            }
            if (empty($clienteCorreo)) {
                $clienteCorreo = 'anonimo_' . time() . '@lym.com';
            }

            // Buscar si ya existe por correo
            $stmtC = $pdo->prepare("SELECT id FROM clientes WHERE correo = ? LIMIT 1");
            $stmtC->execute([$clienteCorreo]);
            $existente = $stmtC->fetchColumn();

            if ($existente) {
                $clienteId = (int)$existente;
            } else {
                $stmtInsC = $pdo->prepare("INSERT INTO clientes (nombre, correo, estado, etapa_crm) VALUES (?, ?, 'activo', 'Prospecto')");
                $stmtInsC->execute([$clienteNombre, $clienteCorreo]);
                $clienteId = (int)$pdo->lastInsertId();
            }
        }

        // Insertar en evaluaciones_crm
        $stmtIns = $pdo->prepare("
            INSERT INTO evaluaciones_crm (cliente_id, producto_id, producto_nombre, puntuacion_satisfaccion, comentarios, fecha_evaluacion)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmtIns->execute([$clienteId, $productoId ?: null, $productoNombre, $puntuacion, $comentarios]);

        $evalId = (int)$pdo->lastInsertId();

        // Registrar automáticamente una Tarea Pendiente para el Administrador
        $descTareaEval = "⭐ NUEVA EVALUACIÓN DE PRODUCTO: " . $productoNombre . " (" . $puntuacion . "/5 estrellas)." . (!empty($comentarios) ? "\nComentario: \"" . $comentarios . "\"" : " Sin comentario adicional.");
        
        $stmtTask = $pdo->prepare("
            INSERT INTO interacciones (cliente_id, usuario_id, tipo, descripcion, estado, prioridad, fecha)
            VALUES (?, 1, 'nota', ?, 'pendiente', 'alta', NOW())
        ");
        $stmtTask->execute([$clienteId, $descTareaEval]);

        echo json_encode([
            'success' => true,
            'message' => '¡Evaluación enviada con éxito! Muchas gracias por tu opinión.',
            'id' => $evalId,
            'producto_nombre' => $productoNombre,
            'puntuacion' => $puntuacion
        ]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error en el servidor: ' . $e->getMessage()]);
}
