<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    $pdo = getDBConnection();

    // Parámetros de búsqueda y filtrado
    $q = trim($_GET['q'] ?? $_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $sort = strtolower(trim($_GET['sort'] ?? $_GET['order'] ?? ''));
    $minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? floatval($_GET['min_price']) : null;
    $maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? floatval($_GET['max_price']) : null;

    // 1. Filtrado dinámico de Productos
    $sqlProd = "SELECT * FROM productos WHERE active = 1";
    $paramsProd = [];

    if (!empty($q)) {
        $sqlProd .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $paramsProd[] = "%$q%";
        $paramsProd[] = "%$q%";
        $paramsProd[] = "%$q%";
    }

    if (!empty($category) && !in_array(strtolower($category), ['todos', 'all'])) {
        $sqlProd .= " AND (LOWER(category) = LOWER(?) OR LOWER(name) LIKE LOWER(?))";
        $paramsProd[] = $category;
        $paramsProd[] = "%$category%";
    }

    if ($minPrice !== null) {
        $sqlProd .= " AND price >= ?";
        $paramsProd[] = $minPrice;
    }

    if ($maxPrice !== null) {
        $sqlProd .= " AND price <= ?";
        $paramsProd[] = $maxPrice;
    }

    switch ($sort) {
        case 'price_asc':
        case 'precio_asc':
        case 'precio_menor':
            $sqlProd .= " ORDER BY price ASC";
            break;
        case 'price_desc':
        case 'precio_desc':
        case 'precio_mayor':
            $sqlProd .= " ORDER BY price DESC";
            break;
        case 'alpha_asc':
        case 'name_asc':
        case 'abecedario_asc':
        case 'a-z':
            $sqlProd .= " ORDER BY name ASC";
            break;
        case 'alpha_desc':
        case 'name_desc':
        case 'abecedario_desc':
        case 'z-a':
            $sqlProd .= " ORDER BY name DESC";
            break;
        default:
            $sqlProd .= " ORDER BY id ASC";
            break;
    }

    $stmtProd = $pdo->prepare($sqlProd);
    $stmtProd->execute($paramsProd);
    $productos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

    // 2. Filtrado dinámico de Paquetes
    $sqlPaq = "SELECT * FROM paquetes WHERE active = 1";
    $paramsPaq = [];

    if (!empty($q)) {
        $sqlPaq .= " AND (name LIKE ? OR description LIKE ?)";
        $paramsPaq[] = "%$q%";
        $paramsPaq[] = "%$q%";
    }

    if ($minPrice !== null) {
        $sqlPaq .= " AND price >= ?";
        $paramsPaq[] = $minPrice;
    }

    if ($maxPrice !== null) {
        $sqlPaq .= " AND price <= ?";
        $paramsPaq[] = $maxPrice;
    }

    switch ($sort) {
        case 'price_asc':
        case 'precio_asc':
        case 'precio_menor':
            $sqlPaq .= " ORDER BY price ASC";
            break;
        case 'price_desc':
        case 'precio_desc':
        case 'precio_mayor':
            $sqlPaq .= " ORDER BY price DESC";
            break;
        case 'alpha_asc':
        case 'name_asc':
        case 'abecedario_asc':
        case 'a-z':
            $sqlPaq .= " ORDER BY name ASC";
            break;
        case 'alpha_desc':
        case 'name_desc':
        case 'abecedario_desc':
        case 'z-a':
            $sqlPaq .= " ORDER BY name DESC";
            break;
        default:
            $sqlPaq .= " ORDER BY featured DESC, id ASC";
            break;
    }

    $stmtPaq = $pdo->prepare($sqlPaq);
    $stmtPaq->execute($paramsPaq);
    $paquetes = $stmtPaq->fetchAll(PDO::FETCH_ASSOC);

    // Decodificar JSON de features e items
    foreach ($productos as &$producto) {
        $producto['features'] = !empty($producto['features']) ? (json_decode($producto['features'], true) ?: []) : [];
    }
    unset($producto);

    foreach ($paquetes as &$paquete) {
        $paquete['items'] = !empty($paquete['items']) ? (json_decode($paquete['items'], true) ?: []) : [];
    }
    unset($paquete);

    echo json_encode([
        'success' => true,
        'query' => $q,
        'category' => $category,
        'sort' => $sort,
        'total_productos' => count($productos),
        'total_paquetes' => count($paquetes),
        'productos' => $productos,
        'paquetes' => $paquetes
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>
