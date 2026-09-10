<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Soportar tanto ?resource= como ?action=
    $resource = 'dashboard';
    if (isset($_GET['resource']) && !empty($_GET['resource'])) {
        $resource = cleanInput($_GET['resource']);
    } elseif (isset($_GET['action']) && !empty($_GET['action'])) {
        $resource = cleanInput($_GET['action']);
    }

    // CONTROL DE ACCESO: Administradores y Trabajadores pueden acceder al CRM
    if (!isLoggedIn() || !isStaff()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Acceso denegado. Se requieren permisos de Administrador o Trabajador para acceder al CRM.'
        ]);
        exit();
    }

    $currentUserId = (int)$_SESSION['user_id'];
    $currentUsername = $_SESSION['username'];
    $currentUserRole = $_SESSION['user_role'];

    // ----------------------------------------------------
    // PETICIONES GET
    // ----------------------------------------------------
    if ($method === 'GET') {

        // 1. DASHBOARD METRICS & CLIENTES EN RIESGO
        if ($resource === 'dashboard') {
            $totalClientes = (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
            $clientesActivos = (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE estado = 'activo'")->fetchColumn();
            $clientesInactivos = (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE estado = 'inactivo'")->fetchColumn();
            
            $interaccionesMes = (int)$pdo->query("
                SELECT COUNT(*) FROM interacciones 
                WHERE MONTH(fecha) = MONTH(CURRENT_DATE()) 
                  AND YEAR(fecha) = YEAR(CURRENT_DATE())
            ")->fetchColumn();

            // Clientes sin interacción en los últimos 30 días
            $sinInteraccionCount = (int)$pdo->query("
                SELECT COUNT(*) FROM clientes c
                WHERE NOT EXISTS (
                    SELECT 1 FROM interacciones i 
                    WHERE i.cliente_id = c.id 
                      AND i.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                )
            ")->fetchColumn();

            // Lista detallada de Clientes en Riesgo (máximo 6)
            $stmtRiesgo = $pdo->query("
                SELECT 
                    c.id, 
                    c.nombre, 
                    COALESCE(c.empresa, 'Particular') AS empresa,
                    MAX(i.fecha) AS ultima_fecha,
                    DATEDIFF(NOW(), COALESCE(MAX(i.fecha), c.created_at)) AS dias_sin_contacto
                FROM clientes c
                LEFT JOIN interacciones i ON i.cliente_id = c.id
                GROUP BY c.id, c.nombre, c.empresa, c.created_at
                HAVING dias_sin_contacto >= 30 OR MAX(i.fecha) IS NULL
                ORDER BY dias_sin_contacto DESC
                LIMIT 6
            ");
            $clientesRiesgo = $stmtRiesgo->fetchAll();

            echo json_encode([
                'success' => true,
                'metrics' => [
                    'totalClientes' => $totalClientes,
                    'clientesActivos' => $clientesActivos,
                    'clientesInactivos' => $clientesInactivos,
                    'pctActivos' => $totalClientes > 0 ? MathRound(($clientesActivos / $totalClientes) * 100) : 0,
                    'interaccionesMes' => $interaccionesMes,
                    'sinInteraccionCount' => $sinInteraccionCount
                ],
                'clientesRiesgo' => array_map(function($r) {
                    return [
                        'id' => (int)$r['id'],
                        'nombre' => $r['nombre'],
                        'empresa' => $r['empresa'],
                        'dias' => (int)$r['dias_sin_contacto']
                    ];
                }, $clientesRiesgo),
                'currentUser' => [
                    'id' => $currentUserId,
                    'username' => $currentUsername,
                    'role' => $currentUserRole
                ]
            ]);
            exit();
        }

        // 2. LISTADO DE CLIENTES (CON BÚSQUEDA Y FILTROS)
        if ($resource === 'clientes') {
            $search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
            $estado = isset($_GET['estado']) ? cleanInput($_GET['estado']) : 'todos';
            $etapa = isset($_GET['etapa']) ? cleanInput($_GET['etapa']) : 'todas';

            $sql = "SELECT c.id, c.nombre, COALESCE(c.empresa, 'Sin Empresa') AS empresa, c.correo, c.telefono, c.estado, c.etapa_crm, c.created_at 
                    FROM clientes c WHERE 1=1";
            $params = [];

            if (!empty($search)) {
                $sql .= " AND (c.nombre LIKE ? OR c.correo LIKE ? OR c.empresa LIKE ? OR c.telefono LIKE ?)";
                $like = "%$search%";
                $params = array_merge($params, [$like, $like, $like, $like]);
            }

            if ($estado !== 'todos' && !empty($estado)) {
                $sql .= " AND c.estado = ?";
                $params[] = $estado;
            }

            if ($etapa !== 'todas' && !empty($etapa)) {
                $sql .= " AND c.etapa_crm = ?";
                $params[] = $etapa;
            }

            $sql .= " ORDER BY c.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $clientes = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'clientes' => array_map(function($c) {
                    return [
                        'id' => (int)$c['id'],
                        'nombre' => $c['nombre'],
                        'empresa' => $c['empresa'],
                        'correo' => $c['correo'],
                        'telefono' => $c['telefono'] ?: 'Sin teléfono',
                        'estado' => $c['estado'],
                        'etapa_crm' => $c['etapa_crm'],
                        'created_at' => $c['created_at']
                    ];
                }, $clientes)
            ]);
            exit();
        }

        // 3. DETALLE DE CLIENTE ESPECÍFICO
        if ($resource === 'cliente_detalle') {
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
            $stmt->execute([$id]);
            $cliente = $stmt->fetch();

            if (!$cliente) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
                exit();
            }

            // Obtener historial de interacciones
            $stmtInt = $pdo->prepare("
                SELECT i.id, i.tipo, i.descripcion, i.fecha, COALESCE(u.username, 'Admin') AS responsable
                FROM interacciones i
                LEFT JOIN usuarios u ON u.id = i.usuario_id
                WHERE i.cliente_id = ?
                ORDER BY i.fecha DESC, i.id DESC
            ");
            $stmtInt->execute([$id]);
            $interacciones = $stmtInt->fetchAll();

            // Obtener evaluaciones
            $stmtEval = $pdo->prepare("
                SELECT * FROM evaluaciones_crm WHERE cliente_id = ? ORDER BY fecha_evaluacion DESC
            ");
            $stmtEval->execute([$id]);
            $evaluaciones = $stmtEval->fetchAll();

            echo json_encode([
                'success' => true,
                'cliente' => $cliente,
                'interacciones' => $interacciones,
                'evaluaciones' => $evaluaciones
            ]);
            exit();
        }

        // 4. HISTORIAL DE INTERACCIONES (GLOBAL O POR CLIENTE)
        if ($resource === 'interacciones') {
            $cliente_id = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : 0;
            
            if ($cliente_id > 0) {
                $stmt = $pdo->prepare("
                    SELECT i.id, i.cliente_id, i.tipo, i.descripcion, i.fecha, c.nombre AS cliente, COALESCE(u.username, 'Admin') AS responsable
                    FROM interacciones i
                    JOIN clientes c ON c.id = i.cliente_id
                    LEFT JOIN usuarios u ON u.id = i.usuario_id
                    WHERE i.cliente_id = ?
                    ORDER BY i.fecha DESC, i.id DESC
                ");
                $stmt->execute([$cliente_id]);
            } else {
                $stmt = $pdo->query("
                    SELECT i.id, i.cliente_id, i.tipo, i.descripcion, i.fecha, c.nombre AS cliente, COALESCE(u.username, 'Admin') AS responsable
                    FROM interacciones i
                    JOIN clientes c ON c.id = i.cliente_id
                    LEFT JOIN usuarios u ON u.id = i.usuario_id
                    ORDER BY i.fecha DESC, i.id DESC
                ");
            }

            echo json_encode([
                'success' => true,
                'interacciones' => $stmt->fetchAll()
            ]);
            exit();
        }

        // 5. MI ACTIVIDAD (ACTIVIDAD DEL USUARIO AUTENTICADO O GLOBAL)
        if ($resource === 'mi_actividad') {
            $scope = isset($_GET['scope']) ? cleanInput($_GET['scope']) : 'mi';

            $sql = "
                SELECT 
                    i.id, 
                    i.cliente_id, 
                    i.usuario_id,
                    i.tipo, 
                    i.descripcion, 
                    COALESCE(i.estado, 'completada') AS estado,
                    COALESCE(i.prioridad, 'media') AS prioridad,
                    i.fecha, 
                    c.nombre AS cliente,
                    c.correo AS cliente_correo,
                    COALESCE(c.empresa, 'Sin Empresa') AS cliente_empresa,
                    COALESCE(u.username, 'Admin') AS responsable
                FROM interacciones i
                JOIN clientes c ON c.id = i.cliente_id
                LEFT JOIN usuarios u ON u.id = i.usuario_id
            ";

            $params = [];
            if ($scope === 'mi') {
                $sql .= " WHERE (i.usuario_id = ? OR i.usuario_id IS NULL)";
                $params[] = $currentUserId;
            }

            $sql .= " ORDER BY i.fecha DESC, i.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $actividades = $stmt->fetchAll();

            $total = count($actividades);
            $unicos = count(array_unique(array_column($actividades, 'cliente_id')));
            
            $llamadas = 0;
            $correos = 0;
            $reuniones = 0;
            $notas = 0;
            $pendientes = 0;
            $completadas = 0;
            $hoyCount = 0;
            $todayStr = date('Y-m-d');

            foreach ($actividades as $a) {
                $t = strtolower($a['tipo']);
                if (strpos($t, 'llamada') !== false) $llamadas++;
                elseif (strpos($t, 'correo') !== false || strpos($t, 'email') !== false) $correos++;
                elseif (strpos($t, 'reun') !== false || strpos($t, 'visita') !== false) $reuniones++;
                else $notas++;

                $est = strtolower($a['estado']);
                if ($est === 'pendiente') {
                    $pendientes++;
                } else {
                    $completadas++;
                }

                if (substr($a['fecha'], 0, 10) === $todayStr) {
                    $hoyCount++;
                }
            }

            echo json_encode([
                'success' => true,
                'scope' => $scope,
                'currentUser' => [
                    'id' => $currentUserId,
                    'username' => $currentUsername
                ],
                'metrics' => [
                    'total' => $total,
                    'clientes_unicos' => $unicos,
                    'llamadas' => $llamadas,
                    'correos' => $correos,
                    'reuniones' => $reuniones,
                    'notas' => $notas,
                    'pendientes' => $pendientes,
                    'completadas' => $completadas,
                    'hoy' => $hoyCount
                ],
                'actividades' => $actividades
            ]);
            exit();
        }

        // 6. REPORTES Y GRÁFICAS (DATOS AGRUPADOS DE MYSQL)
        if ($resource === 'reportes') {
            $interaccionesPorTipo = $pdo->query("
                SELECT tipo, COUNT(*) AS cantidad 
                FROM interacciones 
                GROUP BY tipo
            ")->fetchAll();

            $clientesPorEtapa = $pdo->query("
                SELECT etapa_crm, COUNT(*) AS cantidad 
                FROM clientes 
                GROUP BY etapa_crm
            ")->fetchAll();

            $metrics = [
                'totalClientes' => (int)$pdo->query("SELECT COUNT(*) FROM clientes")->fetchColumn(),
                'clientesActivos' => (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE estado = 'activo'")->fetchColumn(),
                'interaccionesMes' => (int)$pdo->query("SELECT COUNT(*) FROM interacciones WHERE MONTH(fecha) = MONTH(CURRENT_DATE()) AND YEAR(fecha) = YEAR(CURRENT_DATE())")->fetchColumn(),
                'sinInteraccionCount' => (int)$pdo->query("SELECT COUNT(*) FROM clientes c WHERE NOT EXISTS (SELECT 1 FROM interacciones i WHERE i.cliente_id = c.id AND i.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY))")->fetchColumn()
            ];

            echo json_encode([
                'success' => true,
                'metrics' => $metrics,
                'interaccionesPorTipo' => $interaccionesPorTipo,
                'clientesPorEtapa' => $clientesPorEtapa
            ]);
            exit();
        }

        // 7. LISTA DE USUARIOS (SOLO ADMINS)
        if ($resource === 'usuarios') {
            if ($currentUserRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
                exit();
            }

            $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM usuarios ORDER BY id DESC");
            echo json_encode([
                'success' => true,
                'usuarios' => $stmt->fetchAll()
            ]);
            exit();
        }

        // 8. EVALUACIONES CRM
        if ($resource === 'evaluaciones') {
            $stmt = $pdo->query("
                SELECT 
                    e.id, 
                    COALESCE(e.producto_nombre, 'Producto General') AS producto_nombre,
                    e.puntuacion_satisfaccion, 
                    COALESCE(e.comentarios, '') AS comentarios, 
                    e.fecha_evaluacion, 
                    COALESCE(c.nombre, 'Cliente Anónimo') AS cliente,
                    COALESCE(c.correo, '') AS cliente_correo
                FROM evaluaciones_crm e
                LEFT JOIN clientes c ON c.id = e.cliente_id
                ORDER BY e.fecha_evaluacion DESC
            ");
            $evaluaciones = $stmt->fetchAll();

            $total = count($evaluaciones);
            $promedio = 0;
            if ($total > 0) {
                $sum = array_sum(array_column($evaluaciones, 'puntuacion_satisfaccion'));
                $promedio = round($sum / $total, 1);
            }

            echo json_encode([
                'success' => true,
                'total' => $total,
                'promedio_general' => $promedio,
                'evaluaciones' => $evaluaciones
            ]);
            exit();
        }
    }

    // ----------------------------------------------------
    // PETICIONES POST (CREAR / EDITAR)
    // ----------------------------------------------------
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        // CREAR NUEVO CLIENTE
        if ($resource === 'crear_cliente' || $resource === 'clientes') {
            $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
            $correo = isset($input['correo']) ? trim($input['correo']) : '';
            $telefono = isset($input['telefono']) ? trim($input['telefono']) : '';
            $empresa = isset($input['empresa']) ? trim($input['empresa']) : '';
            $etapa = isset($input['etapa_crm']) ? trim($input['etapa_crm']) : 'Prospecto';

            if (empty($nombre) || empty($correo)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El nombre y correo son obligatorios.']);
                exit();
            }

            $stmt = $pdo->prepare("
                INSERT INTO clientes (nombre, correo, telefono, empresa, estado, etapa_crm, fecha_registro)
                VALUES (?, ?, ?, ?, 'activo', ?, NOW())
            ");
            $stmt->execute([$nombre, $correo, $telefono, $empresa, $etapa]);

            echo json_encode([
                'success' => true,
                'message' => 'Cliente registrado exitosamente',
                'id' => (int)$pdo->lastInsertId()
            ]);
            exit();
        }

        // EDITAR DATOS DE CLIENTE
        if ($resource === 'editar_cliente') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
            $correo = isset($input['correo']) ? trim($input['correo']) : '';
            $telefono = isset($input['telefono']) ? trim($input['telefono']) : '';
            $empresa = isset($input['empresa']) ? trim($input['empresa']) : '';
            $estado = isset($input['estado']) ? trim($input['estado']) : 'activo';

            if ($id <= 0 || empty($nombre) || empty($correo)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID, nombre y correo son requeridos.']);
                exit();
            }

            $stmt = $pdo->prepare("
                UPDATE clientes SET nombre = ?, correo = ?, telefono = ?, empresa = ?, estado = ? WHERE id = ?
            ");
            $stmt->execute([$nombre, $correo, $telefono, $empresa, $estado, $id]);

            echo json_encode(['success' => true, 'message' => 'Cliente actualizado correctamente']);
            exit();
        }

        // EDITAR ETAPA CRM
        if ($resource === 'editar_etapa') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $etapa = isset($input['etapa_crm']) ? trim($input['etapa_crm']) : '';

            if ($id <= 0 || empty($etapa)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Faltan datos requeridos.']);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE clientes SET etapa_crm = ? WHERE id = ?");
            $stmt->execute([$etapa, $id]);

            echo json_encode(['success' => true, 'message' => 'Etapa CRM actualizada']);
            exit();
        }

        // REGISTRAR / PROGRAMAR INTERACCIÓN O TAREA
        if ($resource === 'interacciones') {
            $cliente_id = isset($input['cliente_id']) ? (int)$input['cliente_id'] : 0;
            $tipo = isset($input['tipo']) ? strtolower(trim($input['tipo'])) : 'nota';
            $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : '';
            $estado = isset($input['estado']) ? strtolower(trim($input['estado'])) : 'completada';
            $prioridad = isset($input['prioridad']) ? strtolower(trim($input['prioridad'])) : 'media';
            $fecha = isset($input['fecha']) && !empty($input['fecha']) ? trim($input['fecha']) : date('Y-m-d H:i:s');

            if ($cliente_id <= 0 || empty($descripcion)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Cliente y descripción son requeridos.']);
                exit();
            }

            // Normalizar fecha si viene en formato YYYY-MM-DD
            if (strlen($fecha) === 10) {
                $fecha .= ' 09:00:00';
            }

            $stmt = $pdo->prepare("
                INSERT INTO interacciones (cliente_id, usuario_id, tipo, descripcion, estado, prioridad, fecha)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$cliente_id, $currentUserId, $tipo, $descripcion, $estado, $prioridad, $fecha]);
            $insertedId = (int)$pdo->lastInsertId();

            // Generar notificación para el cliente
            $tipoLabel = ucfirst($tipo);
            $fechaFmt = date('d/m/Y H:i', strtotime($fecha));
            crearNotificacionCliente(
                $cliente_id,
                "📅 Nueva Actividad Agendada: {$tipoLabel}",
                "El equipo ha agendado una actividad ($tipoLabel) para ti programada para el {$fechaFmt}. Detalles: {$descripcion}",
                'actividad_agendada'
            );

            echo json_encode([
                'success' => true,
                'message' => 'Actividad guardada con éxito y notificación enviada al cliente',
                'id' => $insertedId
            ]);
            exit();
        }

        // CAMBIAR ESTADO DE INTERACCIÓN (PENDIENTE / COMPLETADA / CANCELADA)
        if ($resource === 'cambiar_estado_interaccion') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $estado = isset($input['estado']) ? strtolower(trim($input['estado'])) : 'completada';

            if ($id <= 0 || !in_array($estado, ['pendiente', 'completada', 'cancelada'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID o estado no válido.']);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE interacciones SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);

            echo json_encode([
                'success' => true,
                'message' => "Estado de la actividad actualizado a '$estado'."
            ]);
            exit();
        }

        // ELIMINAR CLIENTE (SOLO ADMINS)
        if ($resource === 'eliminar_cliente') {
            if ($currentUserRole !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo los administradores pueden eliminar clientes']);
                exit();
            }

            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Cliente eliminado']);
            exit();
        }
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Petición no soportada']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de Base de Datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}

function MathRound($val) {
    return round($val);
}
