<?php

require_once '../includes/config.php';

try {

    // Obtener conexión
    $pdo = getDBConnection();

    echo "<h1>✅ Conexión exitosa</h1>";

    echo "<p><strong>Base de datos activa:</strong> " . htmlspecialchars(getActiveDatabaseName(), ENT_QUOTES, 'UTF-8') . "</p>";

    // =====================================================
    // TABLAS DE LA BASE DE DATOS
    // =====================================================

    $tables = [
        'usuarios',
        'clientes',
        'productos',
        'paquetes',
        'pedidos',
        'detalle_pedido',
        'pagos',
        'interacciones',
        'evaluaciones_crm'
    ];

    echo "<h2>Estado de las tablas</h2>";

    foreach ($tables as $table) {

        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");

        if ($stmt->rowCount() > 0) {

            echo "<p>✅ Tabla <strong>$table</strong> existe</p>";

            $countStmt = $pdo->query(
                "SELECT COUNT(*) AS total FROM `$table`"
            );

            $result = $countStmt->fetch();

            echo "<p style='margin-left:30px;'>";
            echo "📊 Registros: " . $result['total'];
            echo "</p>";

        } else {

            echo "<p>❌ Tabla <strong>$table</strong> NO existe</p>";
        }
    }

    // =====================================================
    // INFORMACIÓN DEL SISTEMA
    // =====================================================

    echo "<hr>";

    echo "<h2>Información del sistema</h2>";

    echo "<p>";
    echo "<strong>PHP:</strong> ";
    echo phpversion();
    echo "</p>";

    echo "<p>";
    echo "<strong>PDO:</strong> ";
    echo implode(', ', PDO::getAvailableDrivers());
    echo "</p>";

} catch (Exception $e) {

    echo "<h1>❌ Error de conexión</h1>";

    echo "<p>";
    echo "<strong>Error:</strong> ";
    echo htmlspecialchars($e->getMessage());
    echo "</p>";

    echo "<hr>";

    echo "<h3>Revisa lo siguiente:</h3>";

    echo "<ul>";
    echo "<li>Apache esté iniciado en XAMPP</li>";
    echo "<li>MySQL esté iniciado en XAMPP</li>";
    echo "<li>La base de datos configurada exista y tenga permisos de lectura y escritura</li>";
    echo "<li>Las tablas requeridas hayan sido creadas</li>";
    echo "<li>Las variables DB_HOST, DB_USER, DB_PASS y DB_NAME sean correctas si usas variables de entorno</li>";
    echo "</ul>";
}
?>