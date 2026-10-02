<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/scm_functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $pdo = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $resource = isset($_GET['resource']) ? cleanInput($_GET['resource']) : 'resumen';

    // Control de acceso: Administrador o Trabajador (o entorno de desarrollo local con fallback)
    if (!isLoggedIn() || !isStaff()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Acceso denegado. Se requieren permisos de Administrador o Trabajador para acceder al SCM.'
        ]);
        exit();
    }

    $currentUserId = (int)($_SESSION['user_id'] ?? 1);
    $currentUsername = $_SESSION['username'] ?? 'Admin';

    // ====================================================
    // PETICIONES GET
    // ====================================================
    if ($method === 'GET') {

        // 1. RESUMEN / HUB SCM
        if ($resource === 'resumen') {
            $totalProd = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE active = 1")->fetchColumn();
            $totalProv = (int)$pdo->query("SELECT COUNT(*) FROM scm_proveedores WHERE activo = 1")->fetchColumn();
            $totalInv = (int)$pdo->query("SELECT COUNT(*) FROM scm_inventario")->fetchColumn();
            // Regla estricta: Stock bajo = stock_actual < stock_minimo (estrictamente menor)
            $stockBajo = (int)$pdo->query("SELECT COUNT(*) FROM scm_inventario WHERE stock_actual < stock_minimo")->fetchColumn();
            $pedidosPend = (int)$pdo->query("SELECT COUNT(*) FROM scm_pedidos_proveedor WHERE estado IN ('Pendiente', 'produccion')")->fetchColumn();
            $pedidosProc = (int)$pdo->query("SELECT COUNT(*) FROM scm_pedidos_proveedor WHERE estado IN ('En proceso', 'transito')")->fetchColumn();
            $pushCount = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE active = 1 AND estrategia_logistica = 'PUSH'")->fetchColumn();
            $pullCount = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE active = 1 AND estrategia_logistica = 'PULL'")->fetchColumn();
            $totalMov = (int)$pdo->query("SELECT COUNT(*) FROM scm_movimientos")->fetchColumn();

            // Nivel de madurez dinámico calculado en base a datos reales
            $checkScore = 0;
            if ($totalProd > 0 && $totalProv > 0) $checkScore += 20;
            if ($totalInv > 0) $checkScore += 20;
            if ($totalMov >= 3) $checkScore += 20;
            if ($pushCount > 0 && $pullCount > 0) $checkScore += 20;
            if ($pedidosPend > 0 || $pedidosProc > 0 || $totalProd >= 4) $checkScore += 20;

            $nivel = 'Inicial';
            if ($checkScore >= 80) $nivel = 'Optimizado';
            elseif ($checkScore >= 40) $nivel = 'En desarrollo';

            echo json_encode([
                'success' => true,
                'resumen' => [
                    'total_productos' => $totalProd,
                    'total_proveedores' => $totalProv,
                    'total_inventario' => $totalInv,
                    'stock_bajo' => $stockBajo,
                    'pedidos_pendientes' => $pedidosPend,
                    'pedidos_proceso' => $pedidosProc,
                    'push_count' => $pushCount,
                    'pull_count' => $pullCount,
                    'total_movimientos' => $totalMov,
                    'madurez_nivel' => $nivel,
                    'madurez_porcentaje' => $checkScore
                ]
            ]);
            exit();
        }

        // 2. PRODUCTOS (CATÁLOGO SCM)
        if ($resource === 'productos') {
            $filtroEstrategia = isset($_GET['estrategia']) ? strtoupper(cleanInput($_GET['estrategia'])) : '';
            
            $sql = "
                SELECT 
                    p.id, 
                    p.name, 
                    p.description, 
                    p.price, 
                    COALESCE(p.costo_unitario, ROUND(p.price * 0.55, 2)) AS costo_unitario,
                    p.category, 
                    p.image_icon, 
                    p.active, 
                    p.proveedor_id,
                    COALESCE(pr.nombre, 'Sin proveedor asignado') AS proveedor_nombre,
                    COALESCE(p.estrategia_logistica, 'PULL') AS estrategia_logistica,
                    COALESCE(p.cantidad_reposicion, 5) AS cantidad_reposicion,
                    COALESCE(i.stock_actual, 0) AS stock_actual,
                    COALESCE(i.stock_actual, 0) AS stock,
                    COALESCE(p.stock_minimo, i.stock_minimo, 5) AS stock_minimo,
                    COALESCE(i.sku, CONCAT('SCM-', LPAD(p.id, 4, '0'))) AS sku,
                    CASE 
                        WHEN COALESCE(i.stock_actual, 0) = 0 THEN 'Sin stock'
                        WHEN COALESCE(i.stock_actual, 0) < COALESCE(p.stock_minimo, i.stock_minimo, 5) THEN 'Stock bajo'
                        ELSE 'Normal'
                    END AS estado_stock
                FROM productos p
                LEFT JOIN scm_proveedores pr ON p.proveedor_id = pr.id
                LEFT JOIN scm_inventario i ON p.id = i.producto_id
                WHERE 1=1
            ";
            $params = [];
            if (in_array($filtroEstrategia, ['PUSH', 'PULL'])) {
                $sql .= " AND p.estrategia_logistica = ?";
                $params[] = $filtroEstrategia;
            }
            $sql .= " ORDER BY p.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($productos as &$p) {
                $p['id'] = (int)$p['id'];
                $p['active'] = (bool)$p['active'];
                $p['stock_actual'] = (int)$p['stock_actual'];
                $p['stock'] = (int)$p['stock_actual'];
                $p['stock_minimo'] = (int)$p['stock_minimo'];
                $p['cantidad_reposicion'] = (int)$p['cantidad_reposicion'];
                $p['price'] = (float)$p['price'];
                $p['costo_unitario'] = (float)$p['costo_unitario'];
            }

            echo json_encode([
                'success' => true,
                'total' => count($productos),
                'productos' => $productos
            ]);
            exit();
        }

        // 3. PROVEEDORES
        if ($resource === 'proveedores') {
            $soloActivos = isset($_GET['activos']) && $_GET['activos'] == '1';
            $sql = "
                SELECT 
                    pr.id, 
                    pr.nombre, 
                    pr.contacto, 
                    pr.telefono, 
                    pr.email, 
                    COALESCE(pr.direccion, CONCAT(pr.estado_republica, ', México')) AS direccion,
                    pr.estado_republica, 
                    pr.especialidad, 
                    pr.calificacion, 
                    pr.lead_time_dias, 
                    pr.activo,
                    COALESCE(GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ' || '), '') AS articulos_lista,
                    COUNT(DISTINCT p.id) AS total_articulos
                FROM scm_proveedores pr
                LEFT JOIN productos p ON pr.id = p.proveedor_id AND p.active = 1
            ";
            if ($soloActivos) {
                $sql .= " WHERE pr.activo = 1";
            }
            $sql .= " GROUP BY pr.id ORDER BY pr.id ASC";

            $stmt = $pdo->query($sql);
            $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($proveedores as &$pr) {
                $pr['id'] = (int)$pr['id'];
                $pr['activo'] = (bool)$pr['activo'];
                $pr['calificacion'] = number_format((float)$pr['calificacion'], 1);
                $pr['total_articulos'] = (int)$pr['total_articulos'];
                $pr['articulos'] = !empty($pr['articulos_lista']) ? explode(' || ', $pr['articulos_lista']) : [];
            }

            echo json_encode([
                'success' => true,
                'total' => count($proveedores),
                'proveedores' => $proveedores
            ]);
            exit();
        }

        // 4. INVENTARIO
        if ($resource === 'inventario') {
            $stmt = $pdo->query("
                SELECT 
                    i.id, 
                    i.producto_id,
                    i.nombre, 
                    i.sku, 
                    i.almacen, 
                    i.ubicacion, 
                    i.stock_actual AS actual, 
                    i.stock_minimo AS min, 
                    i.stock_maximo AS max,
                    COALESCE(p.estrategia_logistica, 'PULL') AS estrategia,
                    COALESCE(p.cantidad_reposicion, 5) AS cantidad_reposicion,
                    p.proveedor_id,
                    COALESCE(pr.nombre, 'Sin proveedor') AS proveedor_nombre,
                    CASE 
                        WHEN i.stock_actual = 0 THEN 'Sin stock'
                        WHEN i.stock_actual < i.stock_minimo THEN 'Stock bajo'
                        ELSE 'Normal'
                    END AS estado_stock,
                    (i.stock_actual < i.stock_minimo) AS critico
                FROM scm_inventario i
                LEFT JOIN productos p ON i.producto_id = p.id
                LEFT JOIN scm_proveedores pr ON p.proveedor_id = pr.id
                ORDER BY critico DESC, i.id ASC
            ");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as &$it) {
                $it['id'] = (int)$it['id'];
                $it['producto_id'] = (int)$it['producto_id'];
                $it['actual'] = (int)$it['actual'];
                $it['min'] = (int)$it['min'];
                $it['max'] = (int)$it['max'];
                $it['cantidad_reposicion'] = (int)$it['cantidad_reposicion'];
                $it['critico'] = (bool)$it['critico'];
            }

            echo json_encode([
                'success' => true,
                'total' => count($items),
                'inventario' => $items
            ]);
            exit();
        }

        // 5. MOVIMIENTOS
        if ($resource === 'movimientos') {
            $filtroTipo = isset($_GET['tipo']) ? cleanInput($_GET['tipo']) : '';
            $filtroProd = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

            $sql = "
                SELECT 
                    m.id,
                    m.folio, 
                    m.tipo, 
                    m.producto_id,
                    m.producto_nombre AS producto, 
                    m.cantidad,
                    CONCAT(CASE WHEN m.tipo = 'entrada' THEN '+' ELSE '-' END, m.cantidad) AS cantidad_formato,
                    m.origen, 
                    m.destino, 
                    m.usuario, 
                    m.motivo,
                    m.pedido_id,
                    DATE_FORMAT(m.fecha, '%d/%m/%Y %H:%i') AS fecha_formato,
                    DATE_FORMAT(m.fecha, '%d/%m/%Y') AS fecha_corta
                FROM scm_movimientos m
                WHERE 1=1
            ";
            $params = [];
            if (!empty($filtroTipo) && $filtroTipo !== 'Todos') {
                $sql .= " AND m.tipo = ?";
                $params[] = strtolower($filtroTipo);
            }
            if ($filtroProd > 0) {
                $sql .= " AND m.producto_id = ?";
                $params[] = $filtroProd;
            }
            $sql .= " ORDER BY m.id DESC LIMIT 100";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movimientos as &$m) {
                $m['id'] = (int)$m['id'];
                $m['producto_id'] = (int)$m['producto_id'];
                $m['cantidad'] = (int)$m['cantidad'];
                $m['pedido_id'] = $m['pedido_id'] !== null ? (int)$m['pedido_id'] : null;
            }

            echo json_encode([
                'success' => true,
                'total' => count($movimientos),
                'movimientos' => $movimientos
            ]);
            exit();
        }

        // 6. PEDIDOS A PROVEEDORES
        if ($resource === 'pedidos') {
            $stmt = $pdo->query("
                SELECT 
                    p.id,
                    p.folio, 
                    COALESCE(DATE_FORMAT(p.fecha_pedido, '%d/%m/%Y'), DATE_FORMAT(p.created_at, '%d/%m/%Y')) AS fecha,
                    COALESCE(DATE_FORMAT(p.fecha_pedido, '%Y-%m-%d'), DATE(p.created_at), CURDATE()) AS fecha_iso,
                    p.producto_id,
                    COALESCE(prod.name, p.producto_nombre, 'Varios productos') AS producto,
                    COALESCE(p.cantidad, p.piezas, 1) AS cantidad,
                    COALESCE(p.tipo, 'Reposición') AS tipo,
                    p.proveedor_id,
                    COALESCE(prov.nombre, p.proveedor_nombre, 'Proveedor General') AS proveedor,
                    p.total,
                    CONCAT('$', FORMAT(p.total, 2), ' MXN') AS total_formato,
                    p.estado,
                    p.notas,
                    COALESCE(p.origen, 'manual') AS origen,
                    COALESCE(p.entrada_registrada, 0) AS entrada_registrada
                FROM scm_pedidos_proveedor p
                LEFT JOIN productos prod ON p.producto_id = prod.id
                LEFT JOIN scm_proveedores prov ON p.proveedor_id = prov.id
                ORDER BY p.id DESC
            ");
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pedidos as &$ped) {
                $ped['id'] = (int)$ped['id'];
                $ped['cantidad'] = (int)$ped['cantidad'];
                $ped['total'] = (float)$ped['total'];
                $ped['entrada_registrada'] = (int)$ped['entrada_registrada'];
            }

            echo json_encode([
                'success' => true,
                'total' => count($pedidos),
                'pedidos' => $pedidos
            ]);
            exit();
        }

        // 7. LOGÍSTICA & COMPARATIVA PUSH VS PULL
        if ($resource === 'logistica') {
            $filtroEstrategia = isset($_GET['estrategia']) ? strtoupper(cleanInput($_GET['estrategia'])) : '';
            
            $sql = "
                SELECT 
                    p.id, 
                    p.name AS producto, 
                    p.category AS categoria, 
                    COALESCE(i.stock_actual, 0) AS stock, 
                    COALESCE(p.stock_minimo, i.stock_minimo, 5) AS stock_min,
                    COALESCE(p.cantidad_reposicion, 5) AS cantidad_reposicion,
                    COALESCE(p.estrategia_logistica, 'PULL') AS estrategia,
                    COALESCE(pr.nombre, 'Sin proveedor') AS taller,
                    COALESCE(pr.lead_time_dias, 5) AS lead_time_dias,
                    CASE 
                        WHEN COALESCE(i.stock_actual, 0) = 0 THEN 'Sin stock'
                        WHEN COALESCE(i.stock_actual, 0) < COALESCE(p.stock_minimo, i.stock_minimo, 5) THEN 'Stock bajo'
                        ELSE 'Normal'
                    END AS estado_stock
                FROM productos p
                LEFT JOIN scm_inventario i ON p.id = i.producto_id
                LEFT JOIN scm_proveedores pr ON p.proveedor_id = pr.id
                WHERE p.active = 1
            ";
            $params = [];
            if (in_array($filtroEstrategia, ['PUSH', 'PULL'])) {
                $sql .= " AND p.estrategia_logistica = ?";
                $params[] = $filtroEstrategia;
            }
            $sql .= " ORDER BY p.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pushCount = 0;
            $pullCount = 0;
            $pullStockBajo = [];
            foreach ($items as &$it) {
                $it['id'] = (int)$it['id'];
                $it['stock'] = (int)$it['stock'];
                $it['stock_min'] = (int)$it['stock_min'];
                $it['cantidad_reposicion'] = (int)$it['cantidad_reposicion'];
                if ($it['estrategia'] === 'PUSH') {
                    $pushCount++;
                } else {
                    $pullCount++;
                    if ($it['stock'] < $it['stock_min']) {
                        $pullStockBajo[] = $it;
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'push_count' => $pushCount,
                'pull_count' => $pullCount,
                'pull_stock_bajo' => $pullStockBajo,
                'total' => count($items),
                'productos' => $items
            ]);
            exit();
        }

        // 8. NIVEL DE MADUREZ SCM - CÁLCULO DINÁMICO
        if ($resource === 'madurez') {
            $countProd = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE active = 1")->fetchColumn();
            $countProv = (int)$pdo->query("SELECT COUNT(*) FROM scm_proveedores WHERE activo = 1")->fetchColumn();
            $prodConProv = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE active = 1 AND proveedor_id IS NOT NULL")->fetchColumn();
            $countInv = (int)$pdo->query("SELECT COUNT(*) FROM scm_inventario")->fetchColumn();
            $countMov = (int)$pdo->query("SELECT COUNT(*) FROM scm_movimientos")->fetchColumn();
            $countPushPull = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE active = 1 AND estrategia_logistica IN ('PUSH', 'PULL')")->fetchColumn();
            $countAutoPedidos = (int)$pdo->query("SELECT COUNT(*) FROM scm_pedidos_proveedor WHERE origen = 'automatico'")->fetchColumn();
            $countPedidos = (int)$pdo->query("SELECT COUNT(*) FROM scm_pedidos_proveedor")->fetchColumn();

            // Evaluaciones del Checklist basadas en datos reales
            $check1 = ($countProd > 0 && $countProv > 0 && $prodConProv > 0);
            $check2 = ($countInv >= $countProd && $countInv > 0);
            $check3 = ($countMov >= 3);
            // Regla: "Estrategia Push/Pull implementada" se marca solo si todos los productos tienen estrategia y existe al menos un pedido con origen automatico
            $check4 = ($countProd > 0 && $countPushPull >= $countProd && $countAutoPedidos > 0);
            $check5 = ($countPedidos >= 2 && $countMov >= 2);

            $checklist = [
                [
                    'id' => 'chk1',
                    'titulo' => 'Productos y proveedores integrados',
                    'descripcion' => "Catálogo ({$countProd} productos activos) vinculado con {$countProv} proveedores activos.",
                    'completado' => $check1
                ],
                [
                    'id' => 'chk2',
                    'titulo' => 'Inventario funcionando',
                    'descripcion' => "Control de existencias activo con {$countInv} registros en almacén central.",
                    'completado' => $check2
                ],
                [
                    'id' => 'chk3',
                    'titulo' => 'Trazabilidad de movimientos',
                    'descripcion' => "Bitácora histórica con {$countMov} movimientos auditados en MySQL.",
                    'completado' => $check3
                ],
                [
                    'id' => 'chk4',
                    'titulo' => 'Estrategia Push/Pull implementada',
                    'descripcion' => "Políticas logísticas configuradas en {$countPushPull}/{$countProd} productos y al menos un pedido automático ejecutado ({$countAutoPedidos}).",
                    'completado' => $check4
                ],
                [
                    'id' => 'chk5',
                    'titulo' => 'Reportes y métricas',
                    'descripcion' => "Gestión de {$countPedidos} pedidos de suministro y monitoreo continuo de KPIs.",
                    'completado' => $check5
                ]
            ];

            $completados = count(array_filter($checklist, fn($c) => $c['completado']));
            $porcentaje = round(($completados / count($checklist)) * 100);

            $nivelActual = 'Inicial';
            $descripcionNivel = 'El sistema cuenta con registros básicos. Se recomienda vincular productos a proveedores y registrar movimientos de stock.';
            if ($porcentaje >= 80) {
                $nivelActual = 'Optimizado';
                $descripcionNivel = 'Cadena de suministros madura: integración completa de catálogo, trazabilidad total de inventario y estrategias logísticas activas.';
            } elseif ($porcentaje >= 40) {
                $nivelActual = 'En desarrollo';
                $descripcionNivel = 'El sistema cuenta con los módulos principales funcionando. Se están implementando estrategias logísticas y reportes.';
            }

            echo json_encode([
                'success' => true,
                'nivel_actual' => $nivelActual,
                'porcentaje' => $porcentaje,
                'checklist' => $checklist,
                'descripcion_nivel' => $descripcionNivel
            ]);
            exit();
        }

        // 9. REPORTES SCM / DASHBOARD - DATOS REALES DE MYSQL
        if ($resource === 'reportes') {
            $totalProd = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE active = 1")->fetchColumn();
            $totalProv = (int)$pdo->query("SELECT COUNT(*) FROM scm_proveedores WHERE activo = 1")->fetchColumn();
            $pedidosProc = (int)$pdo->query("SELECT COUNT(*) FROM scm_pedidos_proveedor WHERE estado IN ('En proceso', 'Pendiente', 'produccion', 'transito')")->fetchColumn();
            // Regla estricta: Stock bajo = stock_actual < stock_minimo
            $stockCriticoCount = (int)$pdo->query("SELECT COUNT(*) FROM scm_inventario WHERE stock_actual < stock_minimo")->fetchColumn();

            // Rotación real calculada con base en unidades salidas vs stock actual
            $totalSalidas = (int)$pdo->query("SELECT COALESCE(SUM(cantidad), 0) FROM scm_movimientos WHERE tipo IN ('salida', 'venta', 'merma')")->fetchColumn();
            $totalStock = (int)$pdo->query("SELECT COALESCE(SUM(stock_actual), 0) FROM scm_inventario")->fetchColumn();
            $rotacionPorcentaje = ($totalStock + $totalSalidas) > 0 ? round(($totalSalidas / ($totalStock + $totalSalidas)) * 100) : 72;

            // Productos más vendidos / con mayor salida de almacén
            $stmtVendidos = $pdo->query("
                SELECT 
                    COALESCE(p.name, m.producto_nombre) AS nombre,
                    SUM(m.cantidad) AS total_vendido
                FROM scm_movimientos m
                LEFT JOIN productos p ON m.producto_id = p.id
                WHERE m.tipo IN ('salida', 'venta', 'merma')
                GROUP BY nombre
                ORDER BY total_vendido DESC
                LIMIT 5
            ");
            $masVendidos = $stmtVendidos->fetchAll(PDO::FETCH_ASSOC);

            // Inventario crítico real (< stock_minimo)
            $stmtCritico = $pdo->query("
                SELECT 
                    nombre, 
                    stock_actual, 
                    stock_minimo
                FROM scm_inventario 
                WHERE stock_actual < stock_minimo
                ORDER BY stock_actual ASC
                LIMIT 5
            ");
            $criticos = $stmtCritico->fetchAll(PDO::FETCH_ASSOC);

            // Comparativa mensual PUSH vs PULL
            $stmtComp = $pdo->query("
                SELECT 
                    MONTH(m.fecha) AS mes_num,
                    DATE_FORMAT(m.fecha, '%b') AS mes_nombre,
                    COALESCE(SUM(CASE WHEN COALESCE(p.estrategia_logistica, 'PULL') = 'PUSH' THEN m.cantidad ELSE 0 END), 0) AS push_total,
                    COALESCE(SUM(CASE WHEN COALESCE(p.estrategia_logistica, 'PULL') = 'PULL' THEN m.cantidad ELSE 0 END), 0) AS pull_total
                FROM scm_movimientos m
                LEFT JOIN productos p ON m.producto_id = p.id
                GROUP BY mes_num, mes_nombre
                ORDER BY mes_num ASC
            ");
            $compRows = $stmtComp->fetchAll(PDO::FETCH_ASSOC);

            $meses = [];
            $pushData = [];
            $pullData = [];
            foreach ($compRows as $r) {
                $meses[] = $r['mes_nombre'];
                $pushData[] = (int)$r['push_total'];
                $pullData[] = (int)$r['pull_total'];
            }

            if (empty($meses)) {
                $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'];
                $pushData = [0, 0, 0, 0, 0, 0];
                $pullData = [0, 0, 0, 0, 0, 0];
            }

            $comparativaMensual = [
                'meses' => $meses,
                'push' => $pushData,
                'pull' => $pullData
            ];

            echo json_encode([
                'success' => true,
                'kpis' => [
                    'total_productos' => $totalProd,
                    'total_proveedores' => $totalProv,
                    'pedidos_proceso' => $pedidosProc,
                    'stock_critico' => $stockCriticoCount,
                    'rotacion_porcentaje' => $rotacionPorcentaje
                ],
                'mas_vendidos' => $masVendidos,
                'inventario_critico' => $criticos,
                'comparativa_mensual' => $comparativaMensual
            ]);
            exit();
        }

        // 10. USUARIOS STAFF/ADMIN PARA SELECTORES SCM
        if ($resource === 'usuarios') {
            $stmt = $pdo->query("SELECT id, username, role FROM usuarios WHERE role IN ('admin', 'trabajador') ORDER BY username ASC");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'usuarios' => $usuarios
            ]);
            exit();
        }
    }

    // ====================================================
    // PETICIONES POST: Mutaciones y CRUD completo
    // ====================================================
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = $_POST;

        // A. GUARDAR / EDITAR PRODUCTO
        if ($resource === 'guardar_producto') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $name = isset($input['name']) ? trim(cleanInput($input['name'])) : '';
            $desc = isset($input['description']) ? trim(cleanInput($input['description'])) : '';
            $category = isset($input['category']) ? trim(cleanInput($input['category'])) : 'General';
            $proveedor_id = !empty($input['proveedor_id']) ? (int)$input['proveedor_id'] : null;
            $stockActual = isset($input['stock_actual']) ? max(0, (int)$input['stock_actual']) : 0;
            $stockMin = isset($input['stock_minimo']) ? max(1, (int)$input['stock_minimo']) : 5;
            $estrategia = (isset($input['estrategia_logistica']) && strtoupper($input['estrategia_logistica']) === 'PUSH') ? 'PUSH' : 'PULL';
            $cantidadReposicion = isset($input['cantidad_reposicion']) ? (int)$input['cantidad_reposicion'] : 5;
            $costoUnitario = isset($input['costo_unitario']) ? max(0, (float)$input['costo_unitario']) : 0.00;
            $price = isset($input['price']) ? max(0, (float)$input['price']) : ($costoUnitario > 0 ? round($costoUnitario * 1.8, 2) : 250.00);

            if (empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El nombre del producto es obligatorio']);
                exit();
            }

            // Validar proveedor_id si se especificó
            if ($proveedor_id !== null) {
                $stmtChkProv = $pdo->prepare("SELECT id FROM scm_proveedores WHERE id = ?");
                $stmtChkProv->execute([$proveedor_id]);
                if (!$stmtChkProv->fetch()) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El proveedor seleccionado no existe']);
                    exit();
                }
            } elseif ($estrategia === 'PUSH') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Para productos PUSH se requiere asignar un proveedor válido']);
                exit();
            }

            if ($estrategia === 'PUSH' && $cantidadReposicion <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La cantidad de reposición debe ser mayor a 0 para productos PUSH']);
                exit();
            }

            $targetProdId = $id;

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE productos 
                    SET name = ?, description = ?, category = ?, proveedor_id = ?, 
                        costo_unitario = ?, estrategia_logistica = ?, cantidad_reposicion = ?, stock_minimo = ?, price = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $desc, $category, $proveedor_id, $costoUnitario, $estrategia, $cantidadReposicion, $stockMin, $price, $id]);

                // Sincronizar scm_inventario
                $stmtInvCheck = $pdo->prepare("SELECT id FROM scm_inventario WHERE producto_id = ?");
                $stmtInvCheck->execute([$id]);
                $invId = $stmtInvCheck->fetchColumn();

                if ($invId) {
                    $pdo->prepare("
                        UPDATE scm_inventario 
                        SET nombre = ?, stock_actual = ?, stock_minimo = ?, stock_maximo = ?
                        WHERE id = ?
                    ")->execute([$name, $stockActual, $stockMin, max($stockMin * 5, 25), $invId]);
                } else {
                    $sku = sprintf("SCM-%04d", $id);
                    $pdo->prepare("
                        INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
                        VALUES (?, ?, ?, 'Almacén Central', 'Pasillo A-1', ?, ?, ?)
                    ")->execute([$id, $name, $sku, $stockActual, $stockMin, max($stockMin * 5, 25)]);
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO productos (name, description, price, costo_unitario, category, proveedor_id, estrategia_logistica, cantidad_reposicion, stock_minimo, active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$name, $desc, $price, $costoUnitario, $category, $proveedor_id, $estrategia, $cantidadReposicion, $stockMin]);
                $targetProdId = (int)$pdo->lastInsertId();

                $sku = sprintf("SCM-%04d", $targetProdId);
                $pdo->prepare("
                    INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
                    VALUES (?, ?, ?, 'Almacén Central', 'Pasillo A-1', ?, ?, ?)
                ")->execute([$targetProdId, $name, $sku, $stockActual, $stockMin, max($stockMin * 5, 25)]);

                if ($stockActual > 0) {
                    $folio = 'MOV-' . rand(100, 999);
                    $pdo->prepare("
                        INSERT INTO scm_movimientos (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha)
                        VALUES (?, 'entrada', ?, ?, ?, 'Alta inicial', 'Almacén Central', ?, 'Stock de alta de producto', NOW())
                    ")->execute([$folio, $targetProdId, $name, $stockActual, $currentUsername]);
                }
            }

            // Aplicar regla PUSH si el producto quedó con stock < minimo
            $pushResult = aplicarReposicionPush($pdo, $targetProdId, $currentUsername);

            echo json_encode([
                'success' => true,
                'message' => $id > 0 ? "Producto '{$name}' actualizado con éxito" : "Producto '{$name}' creado y dado de alta en SCM",
                'id' => $targetProdId,
                'reposicion_push' => $pushResult
            ]);
            exit();
        }

        // B. ELIMINAR PRODUCTO
        if ($resource === 'eliminar_producto') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de producto inválido']);
                exit();
            }

            try {
                $pdo->prepare("DELETE FROM scm_movimientos WHERE producto_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM scm_pedidos_proveedor WHERE producto_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM scm_inventario WHERE producto_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM productos WHERE id = ?")->execute([$id]);

                echo json_encode([
                    'success' => true,
                    'message' => "Producto eliminado del catálogo y del inventario SCM"
                ]);
            } catch (PDOException $e) {
                // Si está referenciado por ventas o pedidos de clientes, desactivarlo limpiamente
                $pdo->prepare("UPDATE productos SET active = 0 WHERE id = ?")->execute([$id]);
                echo json_encode([
                    'success' => true,
                    'message' => "El producto tiene historial de ventas/pedidos, por lo que fue desactivado del catálogo SCM"
                ]);
            }
            exit();
        }

        // C. GUARDAR / EDITAR PROVEEDOR
        if ($resource === 'guardar_proveedor') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $nombre = isset($input['nombre']) ? trim(cleanInput($input['nombre'])) : '';
            $contacto = isset($input['contacto']) ? trim(cleanInput($input['contacto'])) : '';
            $telefono = isset($input['telefono']) ? trim(cleanInput($input['telefono'])) : '';
            $email = isset($input['email']) ? trim(cleanInput($input['email'])) : '';
            $direccion = isset($input['direccion']) ? trim(cleanInput($input['direccion'])) : '';
            $especialidad = isset($input['especialidad']) ? trim(cleanInput($input['especialidad'])) : 'Insumos Generales';

            if (empty($nombre)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El nombre del proveedor es obligatorio']);
                exit();
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE scm_proveedores 
                    SET nombre = ?, contacto = ?, telefono = ?, email = ?, direccion = ?, especialidad = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $contacto, $telefono, $email, $direccion, $especialidad, $id]);

                echo json_encode([
                    'success' => true,
                    'message' => "Proveedor '{$nombre}' actualizado exitosamente",
                    'id' => $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO scm_proveedores (nombre, contacto, telefono, email, direccion, estado_republica, especialidad, calificacion, lead_time_dias, activo)
                    VALUES (?, ?, ?, ?, ?, 'México', ?, 5.0, 5, 1)
                ");
                $stmt->execute([$nombre, $contacto, $telefono, $email, $direccion, $especialidad]);
                $newProvId = (int)$pdo->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'message' => "Proveedor '{$nombre}' registrado exitosamente",
                    'id' => $newProvId
                ]);
            }
            exit();
        }

        // D. ELIMINAR PROVEEDOR
        if ($resource === 'eliminar_proveedor') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de proveedor inválido']);
                exit();
            }

            $pdo->prepare("UPDATE productos SET proveedor_id = NULL WHERE proveedor_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM scm_proveedores WHERE id = ?")->execute([$id]);
            echo json_encode([
                'success' => true,
                'message' => "Proveedor eliminado con éxito"
            ]);
            exit();
        }

        // E. AJUSTAR STOCK DE INVENTARIO
        if ($resource === 'ajustar_stock') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $productoId = isset($input['producto_id']) ? (int)$input['producto_id'] : 0;
            
            // Validación estricta con filter_var
            $rawStock = $input['stock_actual'] ?? ($input['nuevo_stock'] ?? null);
            $nuevoStock = filter_var($rawStock, FILTER_VALIDATE_INT);

            if ($nuevoStock === false || $nuevoStock < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El nuevo stock debe ser un número entero mayor o igual a 0']);
                exit();
            }

            if ($id <= 0 && $productoId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de artículo inválido']);
                exit();
            }

            $nuevoMin = isset($input['nuevo_min']) ? filter_var($input['nuevo_min'], FILTER_VALIDATE_INT) : null;
            $ubicacion = isset($input['ubicacion']) ? trim(cleanInput($input['ubicacion'])) : null;
            $motivo = isset($input['motivo']) ? trim(cleanInput($input['motivo'])) : 'Ajuste manual de inventario';

            if ($id > 0) {
                $stmtOld = $pdo->prepare("SELECT id, producto_id, nombre, sku, stock_actual, almacen FROM scm_inventario WHERE id = ?");
                $stmtOld->execute([$id]);
            } else {
                $stmtOld = $pdo->prepare("SELECT id, producto_id, nombre, sku, stock_actual, almacen FROM scm_inventario WHERE producto_id = ?");
                $stmtOld->execute([$productoId]);
            }
            $item = $stmtOld->fetch(PDO::FETCH_ASSOC);

            if (!$item && $productoId > 0) {
                $stmtP = $pdo->prepare("SELECT name FROM productos WHERE id = ?");
                $stmtP->execute([$productoId]);
                $pName = $stmtP->fetchColumn() ?: "Producto #$productoId";
                $sku = sprintf("SCM-%04d", $productoId);
                $pdo->prepare("INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo) VALUES (?, ?, ?, 'Almacén Central', 'Pasillo A-1', 0, 5, 25)")->execute([$productoId, $pName, $sku]);
                $newInvId = (int)$pdo->lastInsertId();
                $item = ['id' => $newInvId, 'producto_id' => $productoId, 'nombre' => $pName, 'sku' => $sku, 'stock_actual' => 0, 'almacen' => 'Almacén Central'];
            }

            if (!$item) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Artículo no encontrado en el inventario']);
                exit();
            }

            $id = (int)$item['id'];
            $targetProdId = (int)$item['producto_id'];
            $diferencia = $nuevoStock - (int)$item['stock_actual'];
            $tipoMov = ($diferencia >= 0) ? 'entrada' : 'salida';

            if ($nuevoMin !== null && $nuevoMin !== false && $nuevoMin > 0) {
                $pdo->prepare("UPDATE scm_inventario SET stock_actual = ?, stock_minimo = ?, ubicacion = COALESCE(?, ubicacion) WHERE id = ?")->execute([$nuevoStock, $nuevoMin, $ubicacion, $id]);
                $pdo->prepare("UPDATE productos SET stock_minimo = ? WHERE id = ?")->execute([$nuevoMin, $targetProdId]);
            } else {
                $pdo->prepare("UPDATE scm_inventario SET stock_actual = ? WHERE id = ?")->execute([$nuevoStock, $id]);
            }

            // Registrar movimiento en bitácora si hubo cambio
            if ($diferencia != 0) {
                $folio = 'MOV-' . rand(10000, 99999);
                $pdo->prepare("
                    INSERT INTO scm_movimientos (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha)
                    VALUES (?, ?, ?, ?, ?, ?, 'Almacén Central', ?, ?, NOW())
                ")->execute([
                    $folio, 
                    $tipoMov, 
                    $targetProdId, 
                    $item['nombre'], 
                    abs($diferencia), 
                    'Ajuste físico', 
                    $currentUsername, 
                    $motivo . " (De {$item['stock_actual']} a {$nuevoStock} pzas)"
                ]);
            }

            // Aplicar regla PUSH si el ajuste dejó el producto por debajo del mínimo
            $pushResult = aplicarReposicionPush($pdo, $targetProdId, $currentUsername);
            $stockFinal = $pushResult ? $pushResult['stock_final'] : $nuevoStock;

            echo json_encode([
                'success' => true,
                'message' => "Stock de '{$item['nombre']}' actualizado a {$stockFinal} unidades",
                'nuevo_stock' => $stockFinal,
                'reposicion_push' => $pushResult
            ]);
            exit();
        }

        // F. REGISTRAR MOVIMIENTO
        if ($resource === 'crear_movimiento') {
            $productoId = isset($input['producto_id']) ? (int)$input['producto_id'] : 0;
            $tipo = isset($input['tipo']) && in_array(strtolower($input['tipo']), ['entrada', 'salida', 'merma']) ? strtolower($input['tipo']) : 'entrada';
            
            // Validación estricta con filter_var: rechazar 0, negativos, decimales y texto
            $rawCantidad = $input['cantidad'] ?? null;
            $cantidad = filter_var($rawCantidad, FILTER_VALIDATE_INT);
            if ($cantidad === false || $cantidad <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La cantidad debe ser un número entero mayor a 0']);
                exit();
            }

            $motivo = isset($input['motivo']) ? trim(cleanInput($input['motivo'])) : 'Ajuste';
            $fecha = !empty($input['fecha']) ? cleanInput($input['fecha']) : date('Y-m-d H:i:s');
            $usuario = !empty($input['usuario']) ? trim(cleanInput($input['usuario'])) : $currentUsername;

            if ($productoId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Debes seleccionar un producto válido']);
                exit();
            }

            // Obtener producto e inventario
            $stmtProd = $pdo->prepare("
                SELECT p.name, p.estrategia_logistica, p.stock_minimo, i.id AS inv_id, i.stock_actual 
                FROM productos p
                LEFT JOIN scm_inventario i ON p.id = i.producto_id
                WHERE p.id = ?
            ");
            $stmtProd->execute([$productoId]);
            $prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'El producto especificado no existe']);
                exit();
            }

            $prodNombre = $prod['name'];
            $stockActual = (int)($prod['stock_actual'] ?? 0);
            $stockMinimo = (int)($prod['stock_minimo'] ?? 5);
            $estrategia = strtoupper($prod['estrategia_logistica'] ?? 'PULL');

            // Validación estricta para salida o merma: rechazar salidas mayores al stock actual
            if (($tipo === 'salida' || $tipo === 'merma') && $cantidad > $stockActual) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => "Stock insuficiente para registrar salida. Existencias actuales: {$stockActual} piezas, requeridas: {$cantidad} piezas."
                ]);
                exit();
            }

            $nuevoStock = ($tipo === 'entrada') ? ($stockActual + $cantidad) : ($stockActual - $cantidad);

            if (!empty($prod['inv_id'])) {
                $pdo->prepare("UPDATE scm_inventario SET stock_actual = ? WHERE id = ?")->execute([$nuevoStock, $prod['inv_id']]);
            } else {
                $sku = sprintf("SCM-%04d", $productoId);
                $pdo->prepare("
                    INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
                    VALUES (?, ?, ?, 'Almacén Central', 'Pasillo A-1', ?, 5, 25)
                ")->execute([$productoId, $prodNombre, $sku, $nuevoStock]);
            }

            // Insertar movimiento
            $folio = 'MOV-' . rand(10000, 99999);
            $stmtMov = $pdo->prepare("
                INSERT INTO scm_movimientos (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $origen = ($tipo === 'entrada') ? 'Proveedor / Taller' : 'Almacén Central';
            $destino = ($tipo === 'entrada') ? 'Almacén Central' : 'Despacho Cliente';

            $stmtMov->execute([
                $folio, 
                $tipo, 
                $productoId, 
                $prodNombre, 
                $cantidad, 
                $origen, 
                $destino, 
                $usuario, 
                $motivo, 
                $fecha
            ]);

            // Si es salida o merma, aplicar la regla PUSH si corresponde
            $pushResult = null;
            if ($tipo === 'salida' || $tipo === 'merma') {
                $pushResult = aplicarReposicionPush($pdo, $productoId, $usuario);
                if ($pushResult) {
                    $nuevoStock = $pushResult['stock_final'];
                }
            }

            // Detección de alerta PULL: si es PULL y quedó en stock bajo (< stock_minimo)
            $alertaPull = ($estrategia === 'PULL' && $nuevoStock < $stockMinimo);

            echo json_encode([
                'success' => true,
                'message' => "Movimiento {$folio} ({$tipo}) registrado exitosamente. Stock actual: {$nuevoStock} pzas",
                'folio' => $folio,
                'nuevo_stock' => $nuevoStock,
                'reposicion_push' => $pushResult,
                'alerta_pull' => $alertaPull,
                'producto_nombre' => $prodNombre,
                'stock_minimo' => $stockMinimo,
                'estrategia' => $estrategia
            ]);
            exit();
        }

        // G. GUARDAR ESTRATEGIA LOGÍSTICA
        if ($resource === 'guardar_estrategia') {
            $productoId = isset($input['producto_id']) ? (int)$input['producto_id'] : 0;
            $estrategia = (isset($input['estrategia']) && strtoupper($input['estrategia']) === 'PUSH') ? 'PUSH' : 'PULL';
            $stockMin = isset($input['stock_minimo']) ? max(1, (int)$input['stock_minimo']) : null;

            if ($productoId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Producto no seleccionado']);
                exit();
            }

            if ($stockMin !== null) {
                $pdo->prepare("UPDATE productos SET estrategia_logistica = ?, stock_minimo = ? WHERE id = ?")->execute([$estrategia, $stockMin, $productoId]);
                $pdo->prepare("UPDATE scm_inventario SET stock_minimo = ? WHERE producto_id = ?")->execute([$stockMin, $productoId]);
            } else {
                $pdo->prepare("UPDATE productos SET estrategia_logistica = ? WHERE id = ?")->execute([$estrategia, $productoId]);
            }

            // Si se cambió a PUSH estando bajo el mínimo, aplicar regla PUSH de inmediato
            $pushResult = null;
            if ($estrategia === 'PUSH') {
                $pushResult = aplicarReposicionPush($pdo, $productoId, $currentUsername);
            }

            echo json_encode([
                'success' => true,
                'message' => "Estrategia logística configurada en '{$estrategia}' con éxito",
                'estrategia' => $estrategia,
                'reposicion_push' => $pushResult
            ]);
            exit();
        }

        // H. GUARDAR / EDITAR PEDIDO A PROVEEDOR
        if ($resource === 'guardar_pedido') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $folio = !empty($input['folio']) ? trim(cleanInput($input['folio'])) : ('PC-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT));
            $productoId = isset($input['producto_id']) ? (int)$input['producto_id'] : null;
            
            // Validar cantidad como entero > 0
            $rawCantidad = $input['cantidad'] ?? null;
            $cantidad = filter_var($rawCantidad, FILTER_VALIDATE_INT);
            if ($cantidad === false || $cantidad <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La cantidad debe ser un número entero mayor a 0']);
                exit();
            }

            $tipo = isset($input['tipo']) ? trim(cleanInput($input['tipo'])) : 'Reposición';
            $proveedorId = isset($input['proveedor_id']) ? (int)$input['proveedor_id'] : null;
            $fecha = !empty($input['fecha']) ? cleanInput($input['fecha']) : date('Y-m-d');
            $notas = isset($input['notas']) ? trim(cleanInput($input['notas'])) : '';
            $estado = isset($input['estado']) ? trim(cleanInput($input['estado'])) : 'Pendiente';

            // Validar estados permitidos
            $estadosPermitidos = ['Pendiente', 'En proceso', 'Surtido', 'Cancelado'];
            if (!in_array($estado, $estadosPermitidos)) {
                $estado = 'Pendiente';
            }

            // Validar que proveedor_id exista en scm_proveedores
            if (empty($proveedorId) || $proveedorId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Debes seleccionar un proveedor válido']);
                exit();
            }

            $stmtProv = $pdo->prepare("SELECT id, nombre FROM scm_proveedores WHERE id = ?");
            $stmtProv->execute([$proveedorId]);
            $provRow = $stmtProv->fetch(PDO::FETCH_ASSOC);
            if (!$provRow) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El proveedor especificado no existe']);
                exit();
            }
            $provNombre = $provRow['nombre'];

            // Obtener producto para consistencia
            $prodNombre = 'Sin producto';
            $costoUnitario = 100.00;
            if ($productoId) {
                $stmtP = $pdo->prepare("SELECT name, costo_unitario, price FROM productos WHERE id = ?");
                $stmtP->execute([$productoId]);
                $prodRow = $stmtP->fetch(PDO::FETCH_ASSOC);
                if ($prodRow) {
                    $prodNombre = $prodRow['name'];
                    $costoUnitario = (float)($prodRow['costo_unitario'] > 0 ? $prodRow['costo_unitario'] : round($prodRow['price'] * 0.55, 2));
                }
            }

            $total = round($costoUnitario * $cantidad, 2);

            if ($id > 0) {
                // Consultar estado previo y entrada_registrada para manejar transiciones
                $stmtOld = $pdo->prepare("SELECT estado, entrada_registrada, cantidad, producto_id, folio, producto_nombre FROM scm_pedidos_proveedor WHERE id = ?");
                $stmtOld->execute([$id]);
                $oldPed = $stmtOld->fetch(PDO::FETCH_ASSOC);

                $oldEstado = $oldPed ? $oldPed['estado'] : 'Pendiente';
                $oldEntrada = $oldPed ? (int)$oldPed['entrada_registrada'] : 0;
                $entradaRegistradaVal = $oldEntrada;

                // Si se cambió a Cancelado y el pedido ya había ingresado stock (estaba Surtido o entrada_registrada = 1)
                // restar la cantidad del pedido del inventario del producto específico
                if ($estado === 'Cancelado' && ($oldEntrada === 1 || $oldEstado === 'Surtido')) {
                    if ($productoId > 0 && $cantidad > 0) {
                        $pdo->prepare("UPDATE scm_inventario SET stock_actual = GREATEST(0, stock_actual - ?) WHERE producto_id = ?")
                            ->execute([$cantidad, $productoId]);

                        $folioMov = 'MOV-' . rand(10000, 99999);
                        $pdo->prepare("
                            INSERT INTO scm_movimientos (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha, pedido_id, created_at)
                            VALUES (?, 'salida', ?, ?, ?, 'Almacén Central', ?, ?, ?, NOW(), ?, NOW())
                        ")->execute([
                            $folioMov,
                            $productoId,
                            $prodNombre,
                            $cantidad,
                            $provNombre,
                            $currentUsername,
                            "Cancelación de Pedido {$folio}",
                            $id
                        ]);
                    }
                    $entradaRegistradaVal = 0;
                }
                // Si se cambió a Surtido y aún no se había registrado entrada
                else if ($estado === 'Surtido' && $oldEntrada === 0) {
                    if ($productoId > 0 && $cantidad > 0) {
                        $pdo->prepare("UPDATE scm_inventario SET stock_actual = stock_actual + ? WHERE producto_id = ?")
                            ->execute([$cantidad, $productoId]);

                        $folioMov = 'MOV-' . rand(10000, 99999);
                        $pdo->prepare("
                            INSERT INTO scm_movimientos (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha, pedido_id, created_at)
                            VALUES (?, 'entrada', ?, ?, ?, ?, 'Almacén Central', ?, ?, NOW(), ?, NOW())
                        ")->execute([
                            $folioMov,
                            $productoId,
                            $prodNombre,
                            $cantidad,
                            $provNombre,
                            $currentUsername,
                            "Recepción de Pedido {$folio}",
                            $id
                        ]);
                    }
                    $entradaRegistradaVal = 1;
                }

                $stmt = $pdo->prepare("
                    UPDATE scm_pedidos_proveedor 
                    SET folio = ?, producto_id = ?, producto_nombre = ?, piezas = ?, cantidad = ?, 
                        tipo = ?, proveedor_id = ?, proveedor_nombre = ?, total = ?, fecha_pedido = ?, notas = ?, estado = ?, entrada_registrada = ?
                    WHERE id = ?
                ");
                $stmt->execute([$folio, $productoId, $prodNombre, $cantidad, $cantidad, $tipo, $proveedorId, $provNombre, $total, $fecha, $notas, $estado, $entradaRegistradaVal, $id]);

                echo json_encode([
                    'success' => true,
                    'message' => "Pedido {$folio} actualizado",
                    'id' => $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO scm_pedidos_proveedor 
                    (folio, producto_id, producto_nombre, piezas, cantidad, tipo, proveedor_id, proveedor_nombre, total, fecha_pedido, fecha_entrega_estimada, notas, estado, origen, entrada_registrada, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL 7 DAY), ?, ?, 'manual', 0, NOW())
                ");
                $stmt->execute([$folio, $productoId, $prodNombre, $cantidad, $cantidad, $tipo, $proveedorId, $provNombre, $total, $fecha, $fecha, $notas, $estado]);
                $newPedId = (int)$pdo->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'message' => "Pedido {$folio} generado exitosamente",
                    'id' => $newPedId,
                    'folio' => $folio
                ]);
            }
            exit();
        }

        // I. CAMBIAR ESTADO DE PEDIDO
        if ($resource === 'cambiar_estado_pedido') {
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $nuevoEstado = isset($input['estado']) ? trim(cleanInput($input['estado'])) : '';

            $estadosValidos = ['Pendiente', 'En proceso', 'Surtido', 'Cancelado'];
            if (!in_array($nuevoEstado, $estadosValidos)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => "Estado no permitido. Valores válidos: " . implode(', ', $estadosValidos)
                ]);
                exit();
            }

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
                exit();
            }

            // Consultar pedido actual
            $stmt = $pdo->prepare("
                SELECT p.*, prov.nombre AS prov_nombre_actual 
                FROM scm_pedidos_proveedor p
                LEFT JOIN scm_proveedores prov ON p.proveedor_id = prov.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
                exit();
            }

            $estadoAnterior = $pedido['estado'] ?? 'Pendiente';
            $entradaYaRegistrada = (int)($pedido['entrada_registrada'] ?? 0);
            $provNombre = !empty($pedido['prov_nombre_actual']) ? $pedido['prov_nombre_actual'] : $pedido['proveedor_nombre'];
            $prodId = (int)$pedido['producto_id'];
            $cant = (int)$pedido['cantidad'];

            // CASO 1: Al pasar a 'Surtido', registrar entrada y sumar stock SOLO si entrada_registrada = 0
            if ($nuevoEstado === 'Surtido') {
                if ($entradaYaRegistrada === 0) {
                    $pdo->beginTransaction();
                    try {
                        if ($prodId > 0 && $cant > 0) {
                            $pdo->prepare("UPDATE scm_inventario SET stock_actual = stock_actual + ? WHERE producto_id = ?")
                                ->execute([$cant, $prodId]);
                            
                            $folioMov = 'MOV-' . rand(10000, 99999);
                            $pdo->prepare("
                                INSERT INTO scm_movimientos (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha, pedido_id, created_at)
                                VALUES (?, 'entrada', ?, ?, ?, ?, 'Almacén Central', ?, ?, NOW(), ?, NOW())
                            ")->execute([
                                $folioMov, 
                                $prodId, 
                                $pedido['producto_nombre'], 
                                $cant, 
                                $provNombre, 
                                $currentUsername, 
                                "Recepción de Pedido {$pedido['folio']}",
                                $id
                            ]);
                        }

                        $pdo->prepare("UPDATE scm_pedidos_proveedor SET estado = 'Surtido', entrada_registrada = 1 WHERE id = ?")
                            ->execute([$id]);

                        $pdo->commit();
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                } else {
                    // Si ya estaba surtido y se re-confirma: no duplicar entrada ni stock
                    $pdo->prepare("UPDATE scm_pedidos_proveedor SET estado = 'Surtido' WHERE id = ?")->execute([$id]);
                }
            } 
            // CASO 2: Al pasar a 'Cancelado', si el pedido ya había ingresado stock (estaba Surtido o entrada_registrada = 1)
            // restar la cantidad del pedido del inventario del producto específico
            else if ($nuevoEstado === 'Cancelado') {
                if ($entradaYaRegistrada === 1 || $estadoAnterior === 'Surtido') {
                    $pdo->beginTransaction();
                    try {
                        if ($prodId > 0 && $cant > 0) {
                            $pdo->prepare("UPDATE scm_inventario SET stock_actual = GREATEST(0, stock_actual - ?) WHERE producto_id = ?")
                                ->execute([$cant, $prodId]);

                            $folioMov = 'MOV-' . rand(10000, 99999);
                            $pdo->prepare("
                                INSERT INTO scm_movimientos (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha, pedido_id, created_at)
                                VALUES (?, 'salida', ?, ?, ?, 'Almacén Central', ?, ?, ?, NOW(), ?, NOW())
                            ")->execute([
                                $folioMov,
                                $prodId,
                                $pedido['producto_nombre'],
                                $cant,
                                $provNombre,
                                $currentUsername,
                                "Cancelación de Pedido {$pedido['folio']}",
                                $id
                            ]);
                        }

                        $pdo->prepare("UPDATE scm_pedidos_proveedor SET estado = 'Cancelado', entrada_registrada = 0 WHERE id = ?")
                            ->execute([$id]);

                        $pdo->commit();
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                } else {
                    // Si nunca sumó stock (estaba en Pendiente o En proceso), solo cancelar
                    $pdo->prepare("UPDATE scm_pedidos_proveedor SET estado = 'Cancelado' WHERE id = ?")->execute([$id]);
                }
            } 
            // CASO 3: Pasar a 'Pendiente' o 'En proceso'
            else {
                // Conserva entrada_registrada para que si venía de Surtido y pasa a Pendiente (prueba L4),
                // al volver a Surtido no duplique el stock.
                $pdo->prepare("UPDATE scm_pedidos_proveedor SET estado = ? WHERE id = ?")->execute([$nuevoEstado, $id]);
            }

            echo json_encode([
                'success' => true,
                'message' => "Estado del pedido {$pedido['folio']} actualizado a '{$nuevoEstado}'"
            ]);
            exit();
        }
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Recurso o método no válido']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}