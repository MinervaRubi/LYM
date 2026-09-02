<?php
require_once '../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    if (!isLoggedIn() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acceso denegado. Se requieren permisos de administrador.']);
        exit;
    }

    $pdo = getDBConnection();

    $createTables = [
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT NOT NULL AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY username_unique (username),
            UNIQUE KEY email_unique (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS clientes (
            id INT NOT NULL AUTO_INCREMENT,
            usuario_id INT NULL,
            nombre VARCHAR(120) NOT NULL,
            correo VARCHAR(150) NOT NULL,
            telefono VARCHAR(30) DEFAULT NULL,
            estado ENUM('activo', 'inactivo') NOT NULL DEFAULT 'activo',
            etapa_crm ENUM('Prospecto', 'Contacto', 'Cotización', 'Cliente') NOT NULL DEFAULT 'Prospecto',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY correo_unique (correo),
            KEY usuario_id_idx (usuario_id),
            CONSTRAINT fk_clientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS productos (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            category VARCHAR(100) NOT NULL,
            features JSON DEFAULT NULL,
            image_icon VARCHAR(100) DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS pedidos (
            id INT NOT NULL AUTO_INCREMENT,
            cliente_id INT NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            estado ENUM('pendiente', 'pagado', 'entregado', 'cancelado') NOT NULL DEFAULT 'pendiente',
            fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cliente_id_idx (cliente_id),
            CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS detalle_pedido (
            id INT NOT NULL AUTO_INCREMENT,
            pedido_id INT NOT NULL,
            producto_id INT NOT NULL,
            cantidad INT NOT NULL,
            precio_unitario DECIMAL(10,2) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            PRIMARY KEY (id),
            KEY pedido_id_idx (pedido_id),
            KEY producto_id_idx (producto_id),
            CONSTRAINT fk_detalle_pedido_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
            CONSTRAINT fk_detalle_pedido_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS pagos (
            id INT NOT NULL AUTO_INCREMENT,
            pedido_id INT NOT NULL,
            monto DECIMAL(10,2) NOT NULL,
            metodo ENUM('tarjeta', 'transferencia', 'efectivo') NOT NULL DEFAULT 'tarjeta',
            fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            estado ENUM('aprobado', 'pendiente', 'rechazado') NOT NULL DEFAULT 'aprobado',
            PRIMARY KEY (id),
            KEY pedido_id_idx (pedido_id),
            CONSTRAINT fk_pagos_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "CREATE TABLE IF NOT EXISTS crm_interacciones (
            id INT NOT NULL AUTO_INCREMENT,
            cliente_id INT NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            comentario TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cliente_id_idx (cliente_id),
            CONSTRAINT fk_crm_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($createTables as $sql) {
        $pdo->exec($sql);
    }

    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM clientes");
    foreach ($stmt->fetchAll() as $row) {
        $columns[] = $row['Field'];
    }

    if (!in_array('usuario_id', $columns, true)) {
        $pdo->exec('ALTER TABLE clientes ADD COLUMN usuario_id INT NULL AFTER id');
    }

    if (!in_array('etapa_crm', $columns, true)) {
        $pdo->exec("ALTER TABLE clientes ADD COLUMN etapa_crm ENUM('Prospecto','Contacto','Cotización','Cliente') NOT NULL DEFAULT 'Prospecto' AFTER estado");
    }

    if (!in_array('telefono', $columns, true)) {
        $pdo->exec('ALTER TABLE clientes ADD COLUMN telefono VARCHAR(30) NULL AFTER correo');
    }

    $adminHash = '$2y$10$rNrJ9UNlkOyOOGWLgjzHz.lmkOCGza3tjXyWOK2LFs/YbyZE5c3qi';
    $pdo->prepare("INSERT INTO usuarios (username, email, password_hash, role) VALUES (?, ?, ?, 'admin') ON DUPLICATE KEY UPDATE email = VALUES(email), role = VALUES(role)")->execute(['admin', 'admin@lym.com', $adminHash]);

    $adminUserId = (int) $pdo->query("SELECT id FROM usuarios WHERE username = 'admin' LIMIT 1")->fetchColumn();

    if ($adminUserId) {
        $pdo->prepare("INSERT INTO clientes (usuario_id, nombre, correo, telefono, estado, etapa_crm) VALUES (?, 'Ana García', 'ana@correo.com', '5551112233', 'activo', 'Cliente') ON DUPLICATE KEY UPDATE correo = VALUES(correo)")->execute([$adminUserId]);
        $pdo->prepare("INSERT INTO clientes (usuario_id, nombre, correo, telefono, estado, etapa_crm) VALUES (?, 'Luis Torres', 'luis@correo.com', '5554445566', 'activo', 'Cotización') ON DUPLICATE KEY UPDATE correo = VALUES(correo)")->execute([$adminUserId]);
    }

    $pdo->prepare("INSERT INTO crm_interacciones (cliente_id, tipo, comentario) VALUES ((SELECT id FROM clientes WHERE correo = 'ana@correo.com' LIMIT 1), 'Llamada', 'Cliente confirmado para entregar pedido final.')")->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Esquema y relaciones de la base de datos verificados y corregidos.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error en la migración: ' . $e->getMessage()
    ]);
}
?>
