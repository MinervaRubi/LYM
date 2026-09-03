<?php

// =====================================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// =====================================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'nikenza_store');
}

// =====================================================
// CONFIGURACIÓN DE SESIONES
// =====================================================

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);


// =====================================================
// INICIAR SESIÓN
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// =====================================================
// CONFIGURACIÓN DE BASE DE DATOS
// =====================================================

function getDatabaseCandidates()
{
    $candidates = [];
    $envDatabase = getenv('DB_NAME');

    if ($envDatabase) {
        $candidates[] = $envDatabase;
    }

    foreach (['nikenza_store', 'LYM', 'lym'] as $name) {
        if (!in_array($name, $candidates, true)) {
            $candidates[] = $name;
        }
    }

    return $candidates;
}

function ensureDatabaseSchema()
{
    $candidates = getDatabaseCandidates();
    $selectedName = null;
    $rootPdo = null;

    try {
        $rootPdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        foreach ($candidates as $candidate) {
            $stmt = $rootPdo->query(
                "SHOW DATABASES LIKE '" . str_replace("'", "\\'", $candidate) . "'"
            );

            if ($stmt->rowCount() > 0) {
                $selectedName = $candidate;
                break;
            }
        }

        if ($selectedName === null) {
            $selectedName = 'nikenza_store';
            $rootPdo->exec(
                "CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $selectedName) . "`"
            );
        }

        $dbPdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . $selectedName . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        $schema = [
            "CREATE TABLE IF NOT EXISTS usuarios (
                id INT NOT NULL AUTO_INCREMENT,
                username VARCHAR(50) NOT NULL,
                email VARCHAR(150) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_usuarios_username (username),
                UNIQUE KEY uq_usuarios_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS clientes (
                id INT NOT NULL AUTO_INCREMENT,
                usuario_id INT NOT NULL,
                nombre VARCHAR(100) NOT NULL,
                correo VARCHAR(150) NOT NULL,
                telefono VARCHAR(30) DEFAULT NULL,
                estado ENUM('activo', 'inactivo', 'prospecto') NOT NULL DEFAULT 'activo',
                etapa_crm ENUM('Prospecto', 'Contacto', 'Cotizando', 'Negociación', 'Cliente') NOT NULL DEFAULT 'Prospecto',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_clientes_usuario (usuario_id),
                KEY idx_clientes_estado (estado),
                KEY idx_clientes_etapa (etapa_crm),
                CONSTRAINT fk_clientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS productos (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                description TEXT DEFAULT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                category VARCHAR(100) NOT NULL,
                features JSON DEFAULT NULL,
                image_icon VARCHAR(255) DEFAULT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS paquetes (
                id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(150) NOT NULL,
                description TEXT DEFAULT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                items JSON DEFAULT NULL,
                featured TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS pedidos (
                id INT NOT NULL AUTO_INCREMENT,
                cliente_id INT NOT NULL,
                total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                estado ENUM('pendiente', 'pagado', 'cancelado') NOT NULL DEFAULT 'pendiente',
                observaciones TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pedidos_cliente (cliente_id),
                CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS detalle_pedido (
                id INT NOT NULL AUTO_INCREMENT,
                pedido_id INT NOT NULL,
                producto_id INT DEFAULT NULL,
                cantidad INT NOT NULL DEFAULT 1,
                precio_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_detalle_pedido_pedido (pedido_id),
                KEY idx_detalle_pedido_producto (producto_id),
                CONSTRAINT fk_detalle_pedido_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_detalle_pedido_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS pagos (
                id INT NOT NULL AUTO_INCREMENT,
                pedido_id INT NOT NULL,
                metodo VARCHAR(50) NOT NULL,
                monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                estatus ENUM('pendiente', 'aprobado', 'rechazado') NOT NULL DEFAULT 'pendiente',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_pagos_pedido (pedido_id),
                CONSTRAINT fk_pagos_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS interacciones (
                id INT NOT NULL AUTO_INCREMENT,
                cliente_id INT NOT NULL,
                tipo ENUM('llamada', 'correo', 'whatsapp', 'reunion', 'seguimiento') NOT NULL,
                descripcion TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_interacciones_cliente (cliente_id),
                CONSTRAINT fk_interacciones_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS evaluaciones_crm (
                id INT NOT NULL AUTO_INCREMENT,
                cliente_id INT NOT NULL,
                calificacion TINYINT NOT NULL DEFAULT 0,
                comentario TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_evaluaciones_cliente (cliente_id),
                CONSTRAINT fk_evaluaciones_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach ($schema as $sql) {
            $dbPdo->exec($sql);
        }

        repairCatalogText($dbPdo);
        $GLOBALS['ACTIVE_DB_NAME'] = $selectedName;

        $stmt = $dbPdo->prepare(
            "SELECT id, password_hash, email FROM usuarios WHERE username = ? LIMIT 1"
        );
        $stmt->execute(['admin']);
        $adminUser = $stmt->fetch();

        if (!$adminUser) {
            $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
            $dbPdo->prepare(
                "INSERT INTO usuarios (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')"
            )->execute(['admin', 'admin@lym.com', $adminHash]);

            $adminId = (int) $dbPdo->lastInsertId();
            $stmt = $dbPdo->prepare(
                "SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1"
            );
            $stmt->execute([$adminId]);

            if (!$stmt->fetch()) {
                $dbPdo->prepare(
                    "INSERT INTO clientes (usuario_id, nombre, correo, estado, etapa_crm) VALUES (?, ?, ?, 'activo', 'Cliente')"
                )->execute([$adminId, 'Administrador', 'admin@lym.com']);
            }
        } else {
            if (!password_verify('admin123', $adminUser['password_hash'])) {
                $dbPdo->prepare(
                    "UPDATE usuarios SET password_hash = ?, email = ? WHERE username = ?"
                )->execute([password_hash('admin123', PASSWORD_DEFAULT), 'admin@lym.com', 'admin']);
            }

            $adminId = (int) $adminUser['id'];
            $stmt = $dbPdo->prepare(
                "SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1"
            );
            $stmt->execute([$adminId]);

            if (!$stmt->fetch()) {
                $dbPdo->prepare(
                    "INSERT INTO clientes (usuario_id, nombre, correo, estado, etapa_crm) VALUES (?, ?, ?, 'activo', 'Cliente')"
                )->execute([$adminId, 'Administrador', 'admin@lym.com']);
            }
        }

        return $selectedName;
    } catch (PDOException $e) {
        throw $e;
    }
}


// =====================================================
// CONEXIÓN A MYSQL
// =====================================================

function getDBConnection()
{
    try {
        $selectedDb = ensureDatabaseSchema();

        $pdo = new PDO(
            "mysql:host=" . DB_HOST . 
            ";dbname=" . $selectedDb .
            ";charset=utf8mb4",

            DB_USER,
            DB_PASS,

            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        return $pdo;

    } catch (PDOException $e) {
        die(
            "Error de conexión a la base de datos: "
            . $e->getMessage()
        );
    }
}

function repairCatalogText(PDO $pdo)
{
    $products = [
        1 => [
            'name' => 'Taza Estándar',
            'description' => 'Taza estándar de cerámica personalizada',
            'features' => [
                'Taza estándar de cerámica - $85',
                'Taza con asa de color - $95',
                'Taza mágica - $120',
                'Par de tazas corazón - $215',
                'Taza recta grande 444 ml - $130'
            ]
        ],
        2 => [
            'name' => 'Tapete Afelpado',
            'description' => 'Tapete blanco afelpado suave para interior',
            'features' => [
                'Tapete blanco afelpado suave',
                'Para interior, revés antideslizante',
                'Medida: 34 x 58 cm',
                'Impresión máxima: 30 x 43 cm',
                'Diseño personalizado'
            ]
        ],
        3 => [
            'name' => 'Sudaderas',
            'description' => 'Sudaderas personalizadas de algodón',
            'features' => [
                'Sudadera sencilla - $425',
                'Sudadera con gorra - $500',
                'Algodón, sencilla, cerrada',
                'Cuello redondo',
                'Diseño personalizado'
            ]
        ],
        4 => [
            'name' => 'Camisas de Uniforme',
            'description' => 'Camisas de uniforme en gabardina peinada',
            'features' => [
                'Gabardina peinada, muy durable',
                'Para dama y caballero',
                'Tallas: CH, M, G, EG, 2XL, 3XL, 4XL',
                'Frente: $195 - $535',
                'Frente y vuelta: $510 - $550'
            ]
        ]
    ];

    $productStmt = $pdo->prepare(
        "UPDATE productos
         SET name = ?, description = ?, features = ?
         WHERE id = ?
           AND (
               name LIKE '%??%'
               OR description LIKE '%??%'
               OR CAST(features AS CHAR) LIKE '%??%'
           )"
    );

    foreach ($products as $id => $product) {
        $productStmt->execute([
            $product['name'],
            $product['description'],
            json_encode($product['features'], JSON_UNESCAPED_UNICODE),
            $id
        ]);
    }

    $packages = [
        1 => [
            'name' => 'Paquete Básico',
            'description' => 'Paquete básico con productos esenciales',
            'items' => [
                '1 Taza estándar',
                '1 Llavero de acero con impresión frente y vuelta',
                '1 Rompecabezas tamaño carta',
                'Diseño personalizado a tu elección'
            ]
        ],
        2 => [
            'name' => 'Paquete Viajero',
            'description' => 'Paquete ideal para viajeros',
            'items' => [
                '1 Bolsa ecológica chica',
                '2 Llaveros de acero con impresión frente y vuelta',
                '1 Cojín 20 x 30 cm',
                'Diseño personalizado a tu elección'
            ]
        ],
        3 => [
            'name' => 'Paquete Deportivo',
            'description' => 'Paquete para deportistas',
            'items' => [
                '1 Playera deportiva Dryfit impresa al frente',
                '1 Gorra combinada (color a elegir)',
                '1 Vaso alto de acero (blanco o plata)',
                'Diseño personalizado a tu elección'
            ]
        ],
        4 => [
            'name' => 'Paquete Potterhead',
            'description' => 'Paquete temático de Harry Potter',
            'items' => [
                '1 Playera deportiva Dryfit con diseño de Quidditch',
                '1 Termo cafetero con asa y escudo de Hogwarts',
                '1 Llavero de acero con impresión frente y vuelta',
                'Diseño de tu casa de Hogwarts y fecha de cumpleaños'
            ]
        ]
    ];

    $packageStmt = $pdo->prepare(
        "UPDATE paquetes
         SET name = ?, description = ?, items = ?
         WHERE id = ?
           AND (
               name LIKE '%??%'
               OR description LIKE '%??%'
               OR CAST(items AS CHAR) LIKE '%??%'
           )"
    );

    foreach ($packages as $id => $package) {
        $packageStmt->execute([
            $package['name'],
            $package['description'],
            json_encode($package['items'], JSON_UNESCAPED_UNICODE),
            $id
        ]);
    }
}

function getActiveDatabaseName()
{
    if (!empty($GLOBALS['ACTIVE_DB_NAME'])) {
        return $GLOBALS['ACTIVE_DB_NAME'];
    }

    return DB_NAME;
}


// =====================================================
// VERIFICAR SESIÓN
// =====================================================

function isLoggedIn()
{

    return isset($_SESSION['user_id'])
        && !empty($_SESSION['user_id']);
}


// =====================================================
// VERIFICAR ADMINISTRADOR
// =====================================================

function isAdmin()
{

    return isLoggedIn()
        && isset($_SESSION['user_role'])
        && $_SESSION['user_role'] === 'admin';
}


// =====================================================
// OBTENER USUARIO ACTUAL
// =====================================================

function getCurrentUser()
{

    if (!isLoggedIn()) {
        return null;
    }

    $pdo = getDBConnection();

    $stmt = $pdo->prepare(
        "SELECT id, username, email, role
         FROM usuarios
         WHERE id = ?"
    );

    $stmt->execute([
        $_SESSION['user_id']
    ]);

    return $stmt->fetch();
}


// =====================================================
// REDIRECCIÓN
// =====================================================

function redirect($url)
{

    header("Location: $url");
    exit();
}


// =====================================================
// LIMPIAR ENTRADA
// =====================================================

function cleanInput($data)
{

    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);

    return $data;
}


// =====================================================
// TOKEN CSRF
// =====================================================

function generateCSRFToken()
{

    if (!isset($_SESSION['csrf_token'])) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


// =====================================================
// VERIFICAR TOKEN CSRF
// =====================================================

function verifyCSRFToken($token)
{

    return isset($_SESSION['csrf_token'])
        && hash_equals(
            $_SESSION['csrf_token'],
            $token
        );
}

?>