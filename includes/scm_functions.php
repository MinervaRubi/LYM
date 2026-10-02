<?php
// Funciones del módulo SCM para LYM

if (!function_exists('aplicarReposicionPush')) {
    /**
     * Aplica la regla de reposición automática PUSH a un producto.
     * Si el producto es PUSH y su stock_actual < stock_minimo, genera automáticamente:
     * 1. Pedido al proveedor por cantidad_reposicion (o el múltiplo necesario para stock >= minimo) en estado 'Surtido', origen 'automatico'.
     * 2. Entrada en scm_movimientos con pedido_id y motivo 'Reposición automática PUSH – pedido {folio}'.
     * 3. Incremento de stock en scm_inventario.
     * 4. Notificación para los administradores.
     * 
     * Para PULL devuelve siempre null.
     * 
     * @param PDO $pdo
     * @param int $productoId
     * @param string $usuario
     * @return array|null ['folio' => ..., 'cantidad' => ..., 'proveedor' => ..., 'stock_final' => ...]
     */
    function aplicarReposicionPush(PDO $pdo, int $productoId, string $usuario = 'Sistema SCM'): ?array {
        if ($productoId <= 0) {
            return null;
        }

        // Consultar producto, proveedor e inventario actual
        $stmt = $pdo->prepare("
            SELECT 
                p.id, 
                p.name, 
                p.estrategia_logistica, 
                p.stock_minimo, 
                COALESCE(p.cantidad_reposicion, 5) AS cantidad_reposicion, 
                p.costo_unitario, 
                p.price, 
                p.proveedor_id,
                COALESCE(prov.nombre, 'Proveedor General') AS proveedor_nombre,
                i.id AS inv_id, 
                COALESCE(i.stock_actual, 0) AS stock_actual,
                COALESCE(p.stock_minimo, i.stock_minimo, 5) AS stock_min_calc
            FROM productos p
            LEFT JOIN scm_proveedores prov ON p.proveedor_id = prov.id
            LEFT JOIN scm_inventario i ON p.id = i.producto_id
            WHERE p.id = ?
        ");
        $stmt->execute([$productoId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            return null;
        }

        // Para PULL o cualquier otra estrategia devuelve siempre null
        if (strtoupper($prod['estrategia_logistica'] ?? '') !== 'PUSH') {
            return null;
        }

        $stockActual = (int)$prod['stock_actual'];
        $stockMinimo = (int)$prod['stock_minimo'];

        // Regla estricta: Stock bajo = stock_actual < stock_minimo
        // Si el stock es igual o mayor al mínimo, el estado es Normal y no se repone
        if ($stockActual >= $stockMinimo) {
            return null;
        }

        // Calcular la cantidad de reposición:
        // Si con una sola reposición el stock sigue por debajo del mínimo, 
        // aumentar la cantidad del pedido hasta que el stock quede >= mínimo (un solo pedido y una sola entrada)
        $baseReposicion = max(1, (int)$prod['cantidad_reposicion']);
        $cantidadReponer = $baseReposicion;
        while (($stockActual + $cantidadReponer) < $stockMinimo) {
            $cantidadReponer += $baseReposicion;
        }

        $costoUnitario = (float)($prod['costo_unitario'] > 0 ? $prod['costo_unitario'] : round($prod['price'] * 0.55, 2));
        $totalPedido = round($costoUnitario * $cantidadReponer, 2);

        $isNested = $pdo->inTransaction();
        if (!$isNested) {
            $pdo->beginTransaction();
        }

        try {
            // Folio consecutivo: PC-AUTO-0001
            $stmtLast = $pdo->query("SELECT folio FROM scm_pedidos_proveedor WHERE folio LIKE 'PC-AUTO-%' ORDER BY id DESC LIMIT 1");
            $lastFolio = $stmtLast->fetchColumn();
            $nextNum = 1;
            if ($lastFolio && preg_match('/PC-AUTO-(\d+)/', $lastFolio, $matches)) {
                $nextNum = (int)$matches[1] + 1;
            }
            $folio = sprintf('PC-AUTO-%04d', $nextNum);

            $provId = !empty($prod['proveedor_id']) ? (int)$prod['proveedor_id'] : null;
            $provNombre = $prod['proveedor_nombre'];

            // 1. Crear pedido al proveedor por cantidad_reposicion, con tipo "Reposición", origen "automatico" y estado "Surtido"
            $stmtPed = $pdo->prepare("
                INSERT INTO scm_pedidos_proveedor 
                (folio, producto_id, producto_nombre, piezas, cantidad, tipo, proveedor_id, proveedor_nombre, total, estado, fecha_pedido, fecha_entrega_estimada, notas, origen, entrada_registrada, created_at)
                VALUES (?, ?, ?, ?, ?, 'Reposición', ?, ?, ?, 'Surtido', CURDATE(), CURDATE(), 'Reposición automática PUSH por inventario bajo', 'automatico', 1, NOW())
            ");
            $stmtPed->execute([
                $folio,
                $productoId,
                $prod['name'],
                $cantidadReponer,
                $cantidadReponer,
                $provId,
                $provNombre,
                $totalPedido
            ]);
            $pedidoId = (int)$pdo->lastInsertId();

            // 2. Registrar en scm_movimientos una entrada por esa cantidad, con motivo "Reposición automática PUSH – pedido {folio}", inmediatamente después de la salida
            $folioMov = 'MOV-' . rand(10000, 99999);
            $motivoMov = "Reposición automática PUSH – pedido {$folio}";
            $stmtMov = $pdo->prepare("
                INSERT INTO scm_movimientos 
                (folio, tipo, producto_id, producto_nombre, cantidad, origen, destino, usuario, motivo, fecha, pedido_id, created_at)
                VALUES (?, 'entrada', ?, ?, ?, ?, 'Almacén Central', ?, ?, NOW(), ?, NOW())
            ");
            $stmtMov->execute([
                $folioMov,
                $productoId,
                $prod['name'],
                $cantidadReponer,
                $provNombre,
                $usuario,
                $motivoMov,
                $pedidoId
            ]);

            // 3. Sumar esa cantidad a scm_inventario.stock_actual
            if (!empty($prod['inv_id'])) {
                $pdo->prepare("UPDATE scm_inventario SET stock_actual = stock_actual + ? WHERE id = ?")
                    ->execute([$cantidadReponer, $prod['inv_id']]);
            } else {
                $sku = sprintf("SCM-%04d", $productoId);
                $pdo->prepare("
                    INSERT INTO scm_inventario (producto_id, nombre, sku, almacen, ubicacion, stock_actual, stock_minimo, stock_maximo)
                    VALUES (?, ?, ?, 'Almacén Central', 'Pasillo A-1', ?, ?, ?)
                ")->execute([$productoId, $prod['name'], $sku, $stockActual + $cantidadReponer, $stockMinimo, $stockMinimo * 4]);
            }

            $stockFinal = $stockActual + $cantidadReponer;

            // 4. Notificar a los administradores
            $stmtAdmins = $pdo->query("SELECT id FROM usuarios WHERE LOWER(role) = 'admin'");
            $adminIds = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);
            if (empty($adminIds)) {
                $adminIds = [1];
            }
            $stmtNotif = $pdo->prepare("
                INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, leido, fecha)
                VALUES (?, 'Reposición Automática PUSH', ?, 'info', 0, NOW())
            ");
            $msjNotif = "Regla PUSH aplicada: pedido {$folio} surtido, entrada de {$cantidadReponer} registrada. Stock actual: {$stockFinal}";
            foreach ($adminIds as $adminId) {
                $stmtNotif->execute([(int)$adminId, $msjNotif]);
            }

            if (!$isNested) {
                $pdo->commit();
            }

            return [
                'folio' => $folio,
                'cantidad' => $cantidadReponer,
                'proveedor' => $provNombre,
                'stock_final' => $stockFinal
            ];
        } catch (Exception $e) {
            if (!$isNested && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error en aplicarReposicionPush: " . $e->getMessage());
            throw $e;
        }
    }
}
