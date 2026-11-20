<?php
// ==============================================================
// ETAPA 4 - TIENDA EN LÍNEA, CARRITO, PAGOS Y AGREGADOS
// ENDPOINT INTEGRAL: Catálogo, Carrito, Monedero, Impuestos,
// Promociones, Checkout, Tickets y Clientes
// ARCHIVO: php/tienda_api.php
// ==============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'lym';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error de conexión a la base de datos: ' . $e->getMessage()
    ]);
    exit();
}

if (!function_exists('cleanInput')) {
    function cleanInput($data) {
        return htmlspecialchars(strip_tags(trim($data ?? '')));
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$resource = isset($_GET['resource']) ? trim($_GET['resource']) : '';
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: [];

// Identificar cliente actual (por sesión o default)
if (!function_exists('getClienteActual')) {
    function getClienteActual($pdo) {
        $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
        $username = $_SESSION['usuario'] ?? $_SESSION['username'] ?? null;
        $cliente = null;

        if ($userId) {
            $stmt = $pdo->prepare("SELECT * FROM clientes WHERE usuario_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $cliente = $stmt->fetch();
        }
        if (!$cliente && $username) {
            $stmt = $pdo->prepare("SELECT c.* FROM clientes c JOIN usuarios u ON c.usuario_id = u.id WHERE u.username = ? LIMIT 1");
            $stmt->execute([$username]);
            $cliente = $stmt->fetch();
        }
        if (!$cliente) {
            // Fallback al cliente principal registrado
            $stmt = $pdo->query("SELECT * FROM clientes ORDER BY id ASC LIMIT 1");
            $cliente = $stmt->fetch();
        }
        return $cliente;
    }
}

if (!function_exists('obtenerImagenProducto')) {
    function obtenerImagenProducto($id, $category, $name) {
        $map = [
            1 => 'imagen/taza.jpeg',
            2 => 'imagen/tapete.jpg',
            3 => 'imagen/sudadera.jpg',
            4 => 'imagen/uniforme.jpg',
            5 => 'imagen/termo.jpg',
            6 => 'imagen/taaza.jpeg',
            7 => 'imagen/sub.jpg'
        ];
        if (isset($map[$id]) && file_exists(__DIR__ . '/../' . $map[$id])) {
            return $map[$id];
        }
        $cat = strtolower($category);
        if (strpos($cat, 'taza') !== false || strpos($cat, 'cerám') !== false) return 'imagen/taza.jpeg';
        if (strpos($cat, 'tapete') !== false) return 'imagen/tapete.jpg';
        if (strpos($cat, 'sudadera') !== false) return 'imagen/sudadera.jpg';
        if (strpos($cat, 'uniforme') !== false) return 'imagen/uniforme.jpg';
        if (strpos($cat, 'termo') !== false) return 'imagen/termo.jpg';
        return 'imagen/sub.jpg';
    }
}

try {
    // ==============================================================
    // 1. CATÁLOGO DE PRODUCTOS (PANTALLAS 1, 2 Y 3)
    // ==============================================================
    if ($resource === 'catalogo') {
        $cat = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
        $q = isset($_GET['q']) ? trim($_GET['q']) : (isset($_GET['buscar']) ? trim($_GET['buscar']) : '');
        $orden = isset($_GET['orden']) ? trim($_GET['orden']) : 'recientes';

        $sql = "
            SELECT 
                p.id,
                p.name AS nombre,
                p.description AS descripcion,
                p.category AS categoria,
                p.price AS precio,
                p.costo_unitario,
                p.image_icon,
                p.active,
                COALESCE(i.stock_actual, 0) AS stock,
                COALESCE(p.stock_minimo, i.stock_minimo, 5) AS stock_minimo,
                p.estrategia_logistica
            FROM productos p
            LEFT JOIN scm_inventario i ON p.id = i.producto_id
            WHERE p.active = 1
        ";
        $params = [];

        if (!empty($cat) && strtolower($cat) !== 'todas' && strtolower($cat) !== 'todos') {
            $sql .= " AND LOWER(p.category) = LOWER(?)";
            $params[] = $cat;
        }

        if (!empty($q)) {
            $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
            $term = "%{$q}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if ($orden === 'menor_precio') {
            $sql .= " ORDER BY p.price ASC";
        } elseif ($orden === 'mayor_precio') {
            $sql .= " ORDER BY p.price DESC";
        } elseif ($orden === 'nombre') {
            $sql .= " ORDER BY p.name ASC";
        } else {
            $sql .= " ORDER BY p.id DESC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $productos = $stmt->fetchAll();

        foreach ($productos as &$p) {
            $p['id'] = (int)$p['id'];
            $p['precio'] = (float)$p['precio'];
            $p['stock'] = (int)$p['stock'];
            $p['stock_minimo'] = (int)$p['stock_minimo'];
            $p['imagen'] = obtenerImagenProducto($p['id'], $p['categoria'], $p['nombre']);
        }
        unset($p);

        // Categorías únicas
        $stmtCats = $pdo->query("SELECT DISTINCT category FROM productos WHERE active = 1 AND category IS NOT NULL AND category != '' ORDER BY category ASC");
        $categorias = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success' => true,
            'total' => count($productos),
            'categorias' => $categorias,
            'productos' => $productos
        ]);
        exit();
    }

    // ==============================================================
    // 2. DETALLE DE UN PRODUCTO (PANTALLA 3)
    // ==============================================================
    if ($resource === 'producto_detalle') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.name AS nombre,
                p.description AS descripcion,
                p.category AS categoria,
                p.price AS precio,
                p.costo_unitario,
                p.image_icon,
                COALESCE(i.stock_actual, 0) AS stock,
                COALESCE(p.stock_minimo, i.stock_minimo, 5) AS stock_minimo,
                p.estrategia_logistica
            FROM productos p
            LEFT JOIN scm_inventario i ON p.id = i.producto_id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $producto = $stmt->fetch();

        if (!$producto) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
            exit();
        }

        $producto['id'] = (int)$producto['id'];
        $producto['precio'] = (float)$producto['precio'];
        $producto['stock'] = (int)$producto['stock'];
        $producto['stock_minimo'] = (int)$producto['stock_minimo'];
        $producto['imagen'] = obtenerImagenProducto($producto['id'], $producto['categoria'], $producto['nombre']);

        echo json_encode([
            'success' => true,
            'producto' => $producto
        ]);
        exit();
    }

    // ==============================================================
    // 3. MONEDERO ELECTRÓNICO (PANTALLAS 6, 10 Y 11)
    // ==============================================================
    // 3. MONEDERO ELECTRÓNICO (PANTALLAS 6, 8, 10 Y 11)
    // ==============================================================
    if ($resource === 'monedero') {
        // GET: Consultar saldo e historial de movimientos
        if ($method === 'GET') {
            $esAdmin = (isset($_GET['accion']) && $_GET['accion'] === 'todos_los_movimientos') || isset($_GET['todos']);

            if ($esAdmin) {
                $stmtMovs = $pdo->query("
                    SELECT m.*, c.nombre AS cliente_nombre, c.correo AS cliente_correo 
                    FROM monedero_movimientos m 
                    LEFT JOIN clientes c ON m.cliente_id = c.id 
                    ORDER BY m.fecha DESC, m.id DESC LIMIT 100
                ");
                $movimientos = $stmtMovs->fetchAll();

                echo json_encode([
                    'success' => true,
                    'movimientos' => $movimientos
                ]);
                exit();
            }

            $cliente = getClienteActual($pdo);
            $clienteId = $cliente ? (int)$cliente['id'] : 1;

            $stmtSaldo = $pdo->prepare("SELECT saldo_monedero, nombre, correo FROM clientes WHERE id = ?");
            $stmtSaldo->execute([$clienteId]);
            $cData = $stmtSaldo->fetch();
            $saldo = (float)($cData['saldo_monedero'] ?? 0.00);

            $stmtMovs = $pdo->prepare("
                SELECT id, tipo, monto, saldo_resultante, concepto, referencia, fecha 
                FROM monedero_movimientos 
                WHERE cliente_id = ? 
                ORDER BY fecha DESC, id DESC LIMIT 50
            ");
            $stmtMovs->execute([$clienteId]);
            $movimientos = $stmtMovs->fetchAll();

            echo json_encode([
                'success' => true,
                'cliente_id' => $clienteId,
                'nombre' => $cData['nombre'] ?? 'Cliente',
                'correo' => $cData['correo'] ?? '',
                'saldo_monedero' => $saldo,
                'saldo_formato' => '$' . number_format($saldo, 2),
                'movimientos' => $movimientos
            ]);
            exit();
        }

        // POST: Recargar o bonificar saldo en el monedero
        if ($method === 'POST') {
            $cliente = getClienteActual($pdo);
            $clienteSesionId = $cliente ? (int)$cliente['id'] : 1;
            $targetId = isset($input['cliente_id']) && (int)$input['cliente_id'] > 0 ? (int)$input['cliente_id'] : $clienteSesionId;

            $montoRecarga = (float)($input['monto'] ?? 0);
            $metodoPago = cleanInput($input['metodo_pago'] ?? 'Tarjeta');
            $conceptoInput = cleanInput($input['concepto'] ?? '');

            if ($montoRecarga <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El monto de recarga debe ser mayor a $0']);
                exit();
            }

            $pdo->beginTransaction();
            try {
                // Actualizar saldo del cliente
                $stmtUpd = $pdo->prepare("UPDATE clientes SET saldo_monedero = saldo_monedero + ? WHERE id = ?");
                $stmtUpd->execute([$montoRecarga, $targetId]);

                // Obtener saldo resultante
                $stmtNew = $pdo->prepare("SELECT saldo_monedero, nombre FROM clientes WHERE id = ?");
                $stmtNew->execute([$targetId]);
                $cRow = $stmtNew->fetch();
                $nuevoSaldo = (float)($cRow['saldo_monedero'] ?? 0.00);

                $ref = 'REC-' . strtoupper(substr(uniqid(), -5));
                $concepto = !empty($conceptoInput) ? $conceptoInput : "Recarga de saldo ({$metodoPago})";

                // Registrar en bitácora de movimientos
                $stmtMov = $pdo->prepare("
                    INSERT INTO monedero_movimientos (cliente_id, tipo, monto, saldo_resultante, concepto, referencia, fecha)
                    VALUES (?, 'recarga', ?, ?, ?, ?, NOW())
                ");
                $stmtMov->execute([$targetId, $montoRecarga, $nuevoSaldo, $concepto, $ref]);

                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => '¡Abono exitoso! Se abonaron $' . number_format($montoRecarga, 2) . ' al Monedero de ' . ($cRow['nombre'] ?? 'Cliente') . '.',
                    'monto' => $montoRecarga,
                    'nuevo_saldo' => $nuevoSaldo,
                    'saldo_formato' => '$' . number_format($nuevoSaldo, 2),
                    'referencia' => $ref
                ]);
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Error al procesar la recarga: ' . $e->getMessage()]);
                exit();
            }
        }
    }

    // ==============================================================
    // 4. IMPUESTOS CONFIGURABLES (IVA 16%) (PANTALLA 5)
    // ==============================================================
    if ($resource === 'impuestos') {
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM impuestos WHERE activo = 1 ORDER BY id ASC");
            $impuestos = $stmt->fetchAll();
            $ivaPrincipal = 16.00;
            if (!empty($impuestos)) {
                $ivaPrincipal = (float)$impuestos[0]['porcentaje'];
            }
            foreach ($impuestos as &$imp) {
                $imp['tasa'] = (float)$imp['porcentaje'];
            }
            unset($imp);

            echo json_encode([
                'success' => true,
                'iva_porcentaje' => $ivaPrincipal,
                'impuestos' => $impuestos
            ]);
            exit();
        }

        // POST/PUT: Configurar tasa de impuesto (Admin)
        if ($method === 'POST' || $method === 'PUT') {
            $porcentaje = max(0, (float)($input['porcentaje'] ?? $input['tasa'] ?? 16.00));
            $nombre = cleanInput($input['nombre'] ?? 'IVA General');

            $stmt = $pdo->prepare("UPDATE impuestos SET porcentaje = ?, nombre = ? WHERE id = 1");
            $stmt->execute([$porcentaje, $nombre]);

            echo json_encode([
                'success' => true,
                'message' => 'Tasa de impuesto actualizada correctamente',
                'porcentaje' => $porcentaje,
                'tasa' => $porcentaje
            ]);
            exit();
        }
    }

    // ==============================================================
    // 5. PROMOCIONES Y CUPONES (PANTALLA 4 Y 5)
    // ==============================================================
    if ($resource === 'promociones') {
        // Validar un cupón específico
        if (isset($_GET['validar_codigo'])) {
            $codigo = strtoupper(trim($_GET['validar_codigo']));
            $stmt = $pdo->prepare("
                SELECT * FROM promociones 
                WHERE UPPER(codigo) = ? AND estado = 'activo'
                AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
                AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                LIMIT 1
            ");
            $stmt->execute([$codigo]);
            $promo = $stmt->fetch();

            if ($promo) {
                echo json_encode([
                    'success' => true,
                    'valido' => true,
                    'promocion' => $promo,
                    'mensaje' => "Cupón aplicado: {$promo['nombre']}"
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'valido' => false,
                    'error' => 'Cupón no válido o expirado'
                ]);
            }
            exit();
        }

        // Listar promociones (para admin y tienda)
        if ($method === 'GET') {
            $stmt = $pdo->query("SELECT * FROM promociones ORDER BY id DESC");
            $promos = $stmt->fetchAll();
            foreach ($promos as &$pr) {
                $pr['porcentaje'] = (float)$pr['valor'];
                $pr['activo'] = ($pr['estado'] === 'activo') ? 1 : 0;
            }
            unset($pr);

            echo json_encode([
                'success' => true,
                'promociones' => $promos
            ]);
            exit();
        }

        // Crear o actualizar promoción (Admin)
        if ($method === 'POST') {
            // Actualizar estado de cupón existente (Activar / Desactivar)
            if (isset($input['id']) && (isset($input['activo']) || isset($input['estado']))) {
                $idPromo = (int)$input['id'];
                $nuevoEst = !empty($input['estado']) ? cleanInput($input['estado']) : ((int)$input['activo'] === 1 ? 'activo' : 'inactivo');
                $stmtUpd = $pdo->prepare("UPDATE promociones SET estado = ? WHERE id = ?");
                $stmtUpd->execute([$nuevoEst, $idPromo]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Estado de promoción actualizado a ' . $nuevoEst
                ]);
                exit();
            }

            // Crear nuevo cupón
            $codigo = strtoupper(cleanInput($input['codigo'] ?? uniqid('PROMO-')));
            $valor = (float)($input['valor'] ?? $input['porcentaje'] ?? 10);
            $nombre = cleanInput($input['nombre'] ?? ("Cupón " . $codigo));
            $descripcion = cleanInput($input['descripcion'] ?? "Descuento del {$valor}%");
            $fechaFin = !empty($input['fecha_fin']) ? cleanInput($input['fecha_fin']) : null;

            $stmt = $pdo->prepare("
                INSERT INTO promociones (nombre, descripcion, tipo_descuento, valor, codigo, fecha_fin, estado, created_at)
                VALUES (?, ?, 'porcentaje', ?, ?, ?, 'activo', NOW())
            ");
            $stmt->execute([$nombre, $descripcion, $valor, $codigo, $fechaFin]);

            echo json_encode([
                'success' => true,
                'message' => 'Promoción creada exitosamente',
                'id' => (int)$pdo->lastInsertId()
            ]);
            exit();
        }
    }

    // ==============================================================
    // 6. ATENCIÓN A CLIENTES (TICKETS DE SOPORTE) (PANTALLA 10 Y 12)
    // ==============================================================
    if ($resource === 'tickets') {
        $cliente = getClienteActual($pdo);
        $clienteId = $cliente ? (int)$cliente['id'] : 1;

        // GET: Listar tickets
        if ($method === 'GET') {
            $esAdmin = (isset($_GET['accion']) && $_GET['accion'] === 'todos_los_tickets') 
                       || isset($_GET['todos']) 
                       || (($_SESSION['rol'] ?? '') === 'admin')
                       || (($_SESSION['user_role'] ?? '') === 'admin');

            if ($esAdmin) {
                $stmt = $pdo->query("
                    SELECT t.*, c.nombre AS cliente_nombre, c.correo AS cliente_correo 
                    FROM tickets_atencion t
                    LEFT JOIN clientes c ON t.cliente_id = c.id
                    ORDER BY t.fecha DESC, t.id DESC
                ");
                $tickets = $stmt->fetchAll();
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, folio, asunto, mensaje, estado, respuesta, fecha, fecha_respuesta 
                    FROM tickets_atencion 
                    WHERE cliente_id = ? 
                    ORDER BY fecha DESC, id DESC
                ");
                $stmt->execute([$clienteId]);
                $tickets = $stmt->fetchAll();
            }

            echo json_encode([
                'success' => true,
                'tickets' => $tickets
            ]);
            exit();
        }

        // POST: Crear nuevo ticket o responder ticket existente
        if ($method === 'POST') {
            // Responder a un ticket (Admin)
            if ((isset($input['accion']) && $input['accion'] === 'responder') || isset($input['respuesta'])) {
                $ticketId = (int)($input['id'] ?? $input['ticket_id'] ?? 0);
                $respuesta = cleanInput($input['respuesta'] ?? '');
                $nuevoEstado = cleanInput($input['estado'] ?? 'Respondido');

                if ($ticketId <= 0 || empty($respuesta)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID de ticket y mensaje de respuesta son obligatorios']);
                    exit();
                }

                $stmtUpd = $pdo->prepare("
                    UPDATE tickets_atencion 
                    SET respuesta = ?, estado = ?, fecha_respuesta = NOW(), usuario_respuesta_id = ?
                    WHERE id = ?
                ");
                $stmtUpd->execute([$respuesta, $nuevoEstado, $_SESSION['user_id'] ?? 1, $ticketId]);

                // Enviar correo de respuesta oficial al cliente
                try {
                    require_once __DIR__ . '/../includes/mailer.php';
                    require_once __DIR__ . '/../includes/plantillas_correo.php';
                    $stmtTData = $pdo->prepare("
                        SELECT t.*, c.nombre AS cliente_nombre, c.correo AS cliente_correo 
                        FROM tickets_atencion t 
                        JOIN clientes c ON t.cliente_id = c.id 
                        WHERE t.id = ?
                    ");
                    $stmtTData->execute([$ticketId]);
                    $tData = $stmtTData->fetch();
                    if ($tData && !empty($tData['cliente_correo']) && filter_var($tData['cliente_correo'], FILTER_VALIDATE_EMAIL)) {
                        $mailer = new LYMMailer($pdo);
                        $htmlEmail = LYMPlantillasCorreo::ticketNotificacion([
                            'folio' => $tData['folio'],
                            'cliente_nombre' => $tData['cliente_nombre'],
                            'asunto' => $tData['asunto'],
                            'mensaje' => $tData['mensaje'],
                            'respuesta' => $respuesta,
                            'estado' => $nuevoEstado
                        ]);
                        $mailer->enviar($tData['cliente_correo'], "Respuesta a tu Ticket {$tData['folio']}: {$tData['asunto']} - LYM Soporte", $htmlEmail, 'ticket', $tData['folio']);
                    }
                } catch (Exception $eMail) {
                    error_log("Aviso correo respuesta ticket: " . $eMail->getMessage());
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Respuesta enviada y ticket actualizado.'
                ]);
                exit();
            }

            // Crear nuevo ticket (Cliente)
            $asunto = cleanInput($input['asunto'] ?? '');
            $mensaje = cleanInput($input['mensaje'] ?? '');
            $cid = isset($input['cliente_id']) && (int)$input['cliente_id'] > 0 ? (int)$input['cliente_id'] : $clienteId;

            if (empty($asunto) || empty($mensaje)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Debes indicar un asunto y el detalle de tu solicitud']);
                exit();
            }

            // Generar folio consecutivo seguro buscando el mayor número registrado
            $stmtAllFolios = $pdo->query("SELECT folio FROM tickets_atencion WHERE folio LIKE 'T-%'");
            $maxNum = 105;
            while ($rowF = $stmtAllFolios->fetch()) {
                if (preg_match('/T-(\d+)/', $rowF['folio'], $m)) {
                    $val = (int)$m[1];
                    if ($val > $maxNum) {
                        $maxNum = $val;
                    }
                }
            }
            $folio = sprintf('T-%03d', $maxNum + 1);

            $stmtIns = $pdo->prepare("
                INSERT INTO tickets_atencion (folio, cliente_id, asunto, mensaje, estado, fecha)
                VALUES (?, ?, ?, ?, 'En proceso', NOW())
            ");
            $stmtIns->execute([$folio, $cid, $asunto, $mensaje]);
            $ticketId = (int)$pdo->lastInsertId();

            // Enviar correo de confirmación de ticket al cliente
            try {
                require_once __DIR__ . '/../includes/mailer.php';
                require_once __DIR__ . '/../includes/plantillas_correo.php';
                $stmtCli = $pdo->prepare("SELECT nombre, correo FROM clientes WHERE id = ?");
                $stmtCli->execute([$cid]);
                $clienteTicket = $stmtCli->fetch();
                if ($clienteTicket && !empty($clienteTicket['correo']) && filter_var($clienteTicket['correo'], FILTER_VALIDATE_EMAIL)) {
                    $mailer = new LYMMailer($pdo);
                    $htmlEmail = LYMPlantillasCorreo::ticketNotificacion([
                        'folio' => $folio,
                        'cliente_nombre' => $clienteTicket['nombre'] ?? 'Cliente',
                        'asunto' => $asunto,
                        'mensaje' => $mensaje,
                        'estado' => 'En proceso'
                    ]);
                    $mailer->enviar($clienteTicket['correo'], "Confirmación de Ticket {$folio}: {$asunto} - LYM Soporte", $htmlEmail, 'ticket', $folio);
                }
            } catch (Exception $eMail) {
                error_log("Aviso correo ticket nuevo: " . $eMail->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => '¡Ticket enviado exitosamente! Nuestro equipo le dará seguimiento a la brevedad.',
                'ticket_id' => $ticketId,
                'folio' => $folio,
                'estado' => 'En proceso'
            ]);
            exit();
        }

        // PUT: Responder y actualizar estado de ticket (Admin alternativo)
        if ($method === 'PUT') {
            $ticketId = (int)($input['ticket_id'] ?? $input['id'] ?? 0);
            $respuesta = cleanInput($input['respuesta'] ?? '');
            $nuevoEstado = cleanInput($input['estado'] ?? 'Respondido');

            $stmtUpd = $pdo->prepare("
                UPDATE tickets_atencion 
                SET respuesta = ?, estado = ?, fecha_respuesta = NOW(), usuario_respuesta_id = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([$respuesta, $nuevoEstado, $_SESSION['user_id'] ?? 1, $ticketId]);

            echo json_encode([
                'success' => true,
                'message' => 'Ticket actualizado con respuesta oficial.'
            ]);
            exit();
        }
    }

    // ==============================================================
    // 7. CHECKOUT Y PROCESAMIENTO DE COMPRA (PANTALLAS 5, 6 Y 7)
    // ==============================================================
    if ($resource === 'checkout_procesar') {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido']);
            exit();
        }

        $cliente = getClienteActual($pdo);
        if (isset($input['cliente_id']) && is_numeric($input['cliente_id'])) {
            $stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = ? LIMIT 1");
            $stmtCli->execute([(int)$input['cliente_id']]);
            $foundCli = $stmtCli->fetch();
            if ($foundCli) {
                $cliente = $foundCli;
            }
        }
        $clienteId = $cliente ? (int)$cliente['id'] : 1;

        $items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
        $nombreCompleto = cleanInput($input['nombre_completo'] ?? ($input['nombre'] ?? ($cliente['nombre'] ?? 'Cliente General')));
        $direccion = cleanInput($input['direccion'] ?? ($cliente['direccion'] ?? 'Av. Tecnológico #123'));
        $metodoPago = cleanInput($input['metodo_pago'] ?? 'Monedero');
        $codigoPromocion = strtoupper(cleanInput($input['codigo_promocion'] ?? ($input['cupon'] ?? '')));
        $notas = cleanInput($input['notas'] ?? 'Compra en línea');

        if (empty($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El carrito está vacío. Agrega productos para comprar.']);
            exit();
        }

        // 1. Validar existencias de stock en SCM para cada producto
        $subtotal = 0.00;
        $itemsValidados = [];

        foreach ($items as $it) {
            $pId = (int)($it['id'] ?? $it['producto_id'] ?? $it['id_producto'] ?? 0);
            $cant = max(1, (int)($it['cantidad'] ?? 1));

            $stmtProd = $pdo->prepare("
                SELECT p.id, p.name, p.price, COALESCE(i.stock_actual, 0) AS stock
                FROM productos p
                LEFT JOIN scm_inventario i ON p.id = i.producto_id
                WHERE p.id = ? AND p.active = 1
            ");
            $stmtProd->execute([$pId]);
            $pData = $stmtProd->fetch();

            if (!$pData) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => "El producto #{$pId} no está disponible."]);
                exit();
            }

            if ((int)$pData['stock'] < $cant) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => "Stock insuficiente para '{$pData['name']}'. Disponible: {$pData['stock']} pzas, solicitado: {$cant} pzas."
                ]);
                exit();
            }

            $precioUnit = (float)$pData['price'];
            $partidaSubtotal = $precioUnit * $cant;
            $subtotal += $partidaSubtotal;

            $itemsValidados[] = [
                'producto_id' => $pId,
                'nombre' => $pData['name'],
                'cantidad' => $cant,
                'precio_unitario' => $precioUnit,
                'subtotal' => $partidaSubtotal
            ];
        }

        // 2. Calcular Descuento por promoción si aplica
        $descuento = 0.00;
        if (!empty($codigoPromocion)) {
            $stmtPromo = $pdo->prepare("SELECT * FROM promociones WHERE UPPER(codigo) = ? AND estado = 'activo' LIMIT 1");
            $stmtPromo->execute([$codigoPromocion]);
            $promo = $stmtPromo->fetch();
            if ($promo) {
                if ($promo['tipo_descuento'] === 'porcentaje') {
                    $descuento = round(($subtotal * (float)$promo['valor']) / 100, 2);
                } else {
                    $descuento = min($subtotal, (float)$promo['valor']);
                }
            }
        }

        // 3. Calcular Impuestos (IVA 16%)
        $stmtIva = $pdo->query("SELECT porcentaje FROM impuestos WHERE activo = 1 ORDER BY id ASC LIMIT 1");
        $tasaIva = (float)($stmtIva->fetchColumn() ?: 16.00);
        $baseGravable = max(0, $subtotal - $descuento);
        $impuestosMonto = round(($baseGravable * $tasaIva) / 100, 2);
        $totalFinal = round($baseGravable + $impuestosMonto, 2);

        // 4. Validar saldo si el método es MONEDERO
        $saldoActualCliente = (float)($cliente['saldo_monedero'] ?? 0.00);
        $saldoRestante = $saldoActualCliente;

        if (strtolower($metodoPago) === 'monedero') {
            if ($saldoActualCliente < $totalFinal) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => "Saldo insuficiente en tu Monedero. Tienes $" . number_format($saldoActualCliente, 2) . " y el total a pagar es $" . number_format($totalFinal, 2) . ". Por favor recarga saldo o selecciona otro método."
                ]);
                exit();
            }
            $saldoRestante = round($saldoActualCliente - $totalFinal, 2);
        }

        // 5. Iniciar Transacción Atómica Completa (ERP + SCM + CRM + MONEDERO)
        $pdo->beginTransaction();

        try {
            // A. Generar folio oficial: OR-00648, OR-00649...
            $stmtLastOrd = $pdo->query("SELECT folio FROM erp_ordenes WHERE folio LIKE 'OR-%' ORDER BY id DESC LIMIT 1");
            $lastFolio = $stmtLastOrd->fetchColumn();
            $nextFolioNum = 648;
            if ($lastFolio && preg_match('/OR-(\d+)/', $lastFolio, $m)) {
                $nextFolioNum = (int)$m[1] + 1;
            }
            $folioOrden = sprintf('OR-%05d', $nextFolioNum);

            // B. Insertar encabezado de orden en ERP
            $stmtInsOrd = $pdo->prepare("
                INSERT INTO erp_ordenes (folio, cliente_id, usuario_id, fecha, estado, subtotal, total, notas, created_at)
                VALUES (?, ?, ?, NOW(), 'Procesada', ?, ?, ?, NOW())
            ");
            $stmtInsOrd->execute([
                $folioOrden, 
                $clienteId, 
                $_SESSION['user_id'] ?? 1, 
                $subtotal, 
                $totalFinal, 
                "Método: {$metodoPago}. IVA ({$tasaIva}%): $" . number_format($impuestosMonto, 2) . ". Entrega: {$direccion}. " . $notas
            ]);
            $ordenId = (int)$pdo->lastInsertId();

            // C. Insertar partidas de la orden, descontar en SCM y registrar movimientos
            $stmtInsDet = $pdo->prepare("
                INSERT INTO erp_orden_detalles (orden_id, producto_id, nombre_producto, cantidad, precio_unitario, subtotal, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmtDeductStock = $pdo->prepare("
                UPDATE scm_inventario 
                SET stock_actual = GREATEST(0, stock_actual - ?)
                WHERE producto_id = ?
            ");

            $stmtAddMov = $pdo->prepare("
                INSERT INTO scm_movimientos (folio, producto_id, producto_nombre, tipo, cantidad, origen, destino, motivo, usuario, pedido_id, fecha)
                VALUES (?, ?, ?, 'salida', ?, 'Almacén Central', ?, ?, 'Tienda_Cliente', ?, NOW())
            ");

            foreach ($itemsValidados as $it) {
                // Detalle ERP
                $stmtInsDet->execute([$ordenId, $it['producto_id'], $it['nombre'], $it['cantidad'], $it['precio_unitario'], $it['subtotal']]);

                // Descuento stock SCM
                $stmtDeductStock->execute([$it['cantidad'], $it['producto_id']]);

                // Bitácora SCM completa
                $movFolio = 'MOV-' . rand(10000, 99999);
                $stmtAddMov->execute([
                    $movFolio,
                    $it['producto_id'],
                    $it['nombre'],
                    $it['cantidad'],
                    'Cliente: ' . $nombreCompleto,
                    "Venta cliente / Orden {$folioOrden}",
                    $ordenId
                ]);
            }

            // D. Si el pago fue con MONEDERO, descontar y registrar en historial
            if (strtolower($metodoPago) === 'monedero') {
                $stmtUpdCli = $pdo->prepare("UPDATE clientes SET saldo_monedero = ? WHERE id = ?");
                $stmtUpdCli->execute([$saldoRestante, $clienteId]);

                $stmtMovMon = $pdo->prepare("
                    INSERT INTO monedero_movimientos (cliente_id, tipo, monto, saldo_resultante, concepto, referencia, fecha)
                    VALUES (?, 'compra', ?, ?, ?, ?, NOW())
                ");
                $conceptoMon = "Compra ({$folioOrden})";
                $stmtMovMon->execute([$clienteId, -$totalFinal, $saldoRestante, $conceptoMon, $folioOrden]);
            }

            // E. Registrar en tabla pagos
            try {
                $stmtPago = $pdo->prepare("
                    INSERT INTO pagos (pedido_id, monto, metodo, referencia, estado, fecha_pago)
                    VALUES (?, ?, ?, ?, 'aprobado', NOW())
                ");
                $stmtPago->execute([$ordenId, $totalFinal, $metodoPago, $folioOrden]);
            } catch (Exception $ePago) {
                // Registro tolerante a esquemas previos
                error_log("Aviso pagos: " . $ePago->getMessage());
            }

            // F. Actualizar datos de entrega del cliente si cambiaron
            if (!empty($direccion)) {
                $stmtUpdDir = $pdo->prepare("UPDATE clientes SET direccion = ? WHERE id = ? AND (direccion IS NULL OR direccion = '')");
                $stmtUpdDir->execute([$direccion, $clienteId]);
            }

            // G. Limpiar carrito persistente si existe
            $stmtClearCart = $pdo->prepare("
                DELETE cd FROM carrito_detalle cd 
                JOIN carrito c ON cd.carrito_id = c.id 
                WHERE c.cliente_id = ?
            ");
            $stmtClearCart->execute([$clienteId]);

            $pdo->commit();

            // Disparar envío de correo oficial de compra
            $correoEnviado = false;
            $correoMensaje = null;
            try {
                require_once __DIR__ . '/../includes/mailer.php';
                require_once __DIR__ . '/../includes/plantillas_correo.php';
                $destCorreo = !empty($input['correo']) ? $input['correo'] : (!empty($cliente['correo']) ? $cliente['correo'] : ($_SESSION['email'] ?? ''));
                if (!empty($destCorreo) && filter_var($destCorreo, FILTER_VALIDATE_EMAIL)) {
                    $mailer = new LYMMailer($pdo);
                    $htmlEmail = LYMPlantillasCorreo::compraConfirmada([
                        'folio' => $folioOrden,
                        'cliente_nombre' => $nombreCompleto,
                        'direccion' => $direccion,
                        'metodo_pago' => (strtolower($metodoPago) === 'monedero' ? 'Monedero Digital' : $metodoPago),
                        'subtotal' => $subtotal,
                        'descuento' => $descuento,
                        'iva' => $impuestosMonto,
                        'total' => $totalFinal,
                        'items' => $itemsValidados
                    ]);
                    $envioRes = $mailer->enviar($destCorreo, "Comprobante de Compra {$folioOrden} - Tienda Oficial LYM", $htmlEmail, 'compra', $folioOrden);
                    $correoEnviado = !empty($envioRes['success']);
                    $correoMensaje = $envioRes['message'] ?? ($envioRes['error'] ?? null);
                }
            } catch (Exception $eMail) {
                error_log("Aviso de correo checkout: " . $eMail->getMessage());
            }

            $ordenResumen = [
                'id' => $ordenId,
                'folio' => $folioOrden,
                'metodo_pago' => (strtolower($metodoPago) === 'monedero' ? 'Monedero' : $metodoPago),
                'saldo_disponible_anterior' => $saldoActualCliente,
                'total' => $totalFinal,
                'total_pagado' => $totalFinal,
                'saldo_nuevo' => $saldoRestante,
                'saldo_actual' => $saldoRestante,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuestos' => $impuestosMonto,
                'iva' => $impuestosMonto,
                'tasa_iva' => $tasaIva,
                'correo_cliente' => $cliente['correo'] ?? 'juan@ejemplo.com',
                'nombre_cliente' => $nombreCompleto,
                'direccion' => $direccion,
                'items' => $itemsValidados,
                'fecha' => date('d M Y, H:i')
            ];

            // Retornar datos idénticos a Pantalla 7 de la guía
            echo json_encode([
                'success' => true,
                'message' => '¡Compra realizada con éxito!',
                'correo_enviado' => $correoEnviado,
                'correo_mensaje' => $correoMensaje,
                'orden' => $ordenResumen,
                'orden_id' => $ordenId,
                'folio' => $folioOrden,
                'metodo_pago' => (strtolower($metodoPago) === 'monedero' ? 'Monedero' : $metodoPago),
                'saldo_disponible_anterior' => $saldoActualCliente,
                'total_pagado' => $totalFinal,
                'saldo_actual' => $saldoRestante,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'impuestos' => $impuestosMonto,
                'tasa_iva' => $tasaIva,
                'correo_cliente' => $cliente['correo'] ?? 'juan@ejemplo.com',
                'nombre_cliente' => $nombreCompleto,
                'direccion' => $direccion,
                'items_count' => count($itemsValidados)
            ]);
            exit();

        } catch (Exception $ex) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error durante la transacción: ' . $ex->getMessage()]);
            exit();
        }
    }

    // ==============================================================
    // 8. PERFIL DEL CLIENTE (PANTALLA 10)
    // ==============================================================
    if ($resource === 'cliente_perfil') {
        $cliente = getClienteActual($pdo);
        if (!$cliente) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
            exit();
        }
        $clienteId = (int)$cliente['id'];

        if ($method === 'GET') {
            // Contar pedidos del cliente
            $stmtPed = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(total), 0) AS total_gastado FROM erp_ordenes WHERE cliente_id = ?");
            $stmtPed->execute([$clienteId]);
            $pedStats = $stmtPed->fetch();

            // Listar últimos pedidos
            $stmtListPed = $pdo->prepare("
                SELECT id, folio, total, estado, fecha, notas 
                FROM erp_ordenes 
                WHERE cliente_id = ? 
                ORDER BY id DESC LIMIT 10
            ");
            $stmtListPed->execute([$clienteId]);
            $pedidos = $stmtListPed->fetchAll();

            echo json_encode([
                'success' => true,
                'cliente' => [
                    'id' => $cliente['id'],
                    'nombre' => $cliente['nombre'],
                    'correo' => $cliente['correo'],
                    'telefono' => $cliente['telefono'] ?? '',
                    'direccion' => $cliente['direccion'] ?? 'Av. Tecnológico #123',
                    'saldo_monedero' => (float)$cliente['saldo_monedero'],
                    'saldo_formato' => '$' . number_format((float)$cliente['saldo_monedero'], 2),
                    'total_pedidos' => (int)$pedStats['total'],
                    'total_gastado' => (float)$pedStats['total_gastado']
                ],
                'ultimos_pedidos' => $pedidos
            ]);
            exit();
        }

        if ($method === 'PUT' || $method === 'POST') {
            $nombre = cleanInput($input['nombre'] ?? $cliente['nombre']);
            $telefono = cleanInput($input['telefono'] ?? $cliente['telefono']);
            $direccion = cleanInput($input['direccion'] ?? $cliente['direccion']);

            $stmtUpd = $pdo->prepare("UPDATE clientes SET nombre = ?, telefono = ?, direccion = ? WHERE id = ?");
            $stmtUpd->execute([$nombre, $telefono, $direccion, $clienteId]);

            echo json_encode([
                'success' => true,
                'message' => 'Perfil actualizado exitosamente'
            ]);
            exit();
        }
    }

    // ==============================================================
    // 9. CONFIGURACIÓN Y PRUEBA DE CORREO OFICIAL (SMTP GMAIL)
    // ==============================================================
    if ($resource === 'correo') {
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/plantillas_correo.php';

        // GET: Consultar configuración actual y bitácora de envíos
        if ($method === 'GET') {
            $stmtCfg = $pdo->query("SELECT * FROM configuracion_correo ORDER BY id DESC LIMIT 1");
            $cfg = $stmtCfg->fetch();

            if (!$cfg) {
                $cfg = [
                    'servidor_smtp' => 'smtp.gmail.com',
                    'puerto_smtp' => 465,
                    'seguridad' => 'ssl',
                    'usuario_smtp' => '',
                    'remitente_nombre' => 'LYM Diseño e Impresión Oficial',
                    'remitente_email' => '',
                    'activo' => 1,
                    'has_password' => false
                ];
            } else {
                $cfg['has_password'] = !empty($cfg['password_smtp']);
                unset($cfg['password_smtp']); // Nunca exponer la contraseña en texto plano
            }

            // Bitácora de correos recientes
            $stmtBit = $pdo->query("SELECT * FROM bitacora_correos ORDER BY id DESC LIMIT 50");
            $bitacora = $stmtBit->fetchAll();

            echo json_encode([
                'success' => true,
                'config' => $cfg,
                'bitacora' => $bitacora
            ]);
            exit();
        }

        // POST: Guardar configuración o enviar correo de prueba
        if ($method === 'POST') {
            $accion = $input['accion'] ?? 'guardar_config';

            // Enviar correo de prueba en vivo
            if ($accion === 'probar') {
                $destinatario = cleanInput($input['destinatario'] ?? '');
                if (empty($destinatario) || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Debes especificar un correo destinatario válido (ej. tu_correo@gmail.com).']);
                    exit();
                }

                $stmtCfg = $pdo->query("SELECT * FROM configuracion_correo WHERE activo = 1 ORDER BY id DESC LIMIT 1");
                $cfgActual = $stmtCfg->fetch();
                $remitente = $cfgActual['usuario_smtp'] ?? 'notificaciones@lym.com';

                $mailer = new LYMMailer($pdo);
                $htmlPrueba = LYMPlantillasCorreo::correoPrueba($destinatario, $remitente);

                $resEnvio = $mailer->enviar(
                    $destinatario, 
                    "Verificación de Conexión SMTP - Tienda Oficial LYM", 
                    $htmlPrueba, 
                    'prueba', 
                    'TEST-' . date('His')
                );

                echo json_encode($resEnvio);
                exit();
            }

            // Guardar configuración SMTP
            $servidor = cleanInput($input['servidor_smtp'] ?? 'smtp.gmail.com');
            $puerto = (int)($input['puerto_smtp'] ?? 465);
            $seguridad = in_array(strtolower($input['seguridad'] ?? ''), ['ssl', 'tls']) ? strtolower($input['seguridad']) : 'ssl';
            $usuario = cleanInput($input['usuario_smtp'] ?? '');
            $password = trim($input['password_smtp'] ?? '');
            $remitenteNombre = cleanInput($input['remitente_nombre'] ?? 'LYM Diseño e Impresión Oficial');
            $remitenteEmail = cleanInput($input['remitente_email'] ?? $usuario);
            $activo = isset($input['activo']) ? (int)$input['activo'] : 1;

            // Obtener configuración previa
            $stmtPrev = $pdo->query("SELECT id, password_smtp FROM configuracion_correo ORDER BY id DESC LIMIT 1");
            $prev = $stmtPrev->fetch();

            if ($prev) {
                // Si la contraseña vino vacía en el update, conservamos la que ya estaba guardada
                $pwdToSave = !empty($password) ? $password : $prev['password_smtp'];

                $stmtUpd = $pdo->prepare("
                    UPDATE configuracion_correo 
                    SET servidor_smtp = ?, puerto_smtp = ?, seguridad = ?, usuario_smtp = ?, password_smtp = ?, remitente_nombre = ?, remitente_email = ?, activo = ?
                    WHERE id = ?
                ");
                $stmtUpd->execute([$servidor, $puerto, $seguridad, $usuario, $pwdToSave, $remitenteNombre, $remitenteEmail, $activo, $prev['id']]);
            } else {
                $stmtIns = $pdo->prepare("
                    INSERT INTO configuracion_correo 
                    (servidor_smtp, puerto_smtp, seguridad, usuario_smtp, password_smtp, remitente_nombre, remitente_email, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([$servidor, $puerto, $seguridad, $usuario, $password, $remitenteNombre, $remitenteEmail, $activo]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Configuración de correo oficial guardada exitosamente.'
            ]);
            exit();
        }
    }

    // Recurso no encontrado
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Recurso no encontrado']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}