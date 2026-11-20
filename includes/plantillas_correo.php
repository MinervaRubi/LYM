<?php
/**
 * LYM - Plantillas de Correo HTML Oficiales y Responsivas
 */

class LYMPlantillasCorreo {

    private static function envolverPlantilla($titulo, $contenidoHTML) {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titulo}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f6fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.6; }
        .wrapper { width: 100%; max-width: 620px; margin: 30px auto; background: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 32px 24px; text-align: center; color: #ffffff; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.5px; }
        .header p { margin: 6px 0 0; font-size: 14px; opacity: 0.9; }
        .content { padding: 32px 28px; }
        .card { background: #f8fafc; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 22px; }
        .folio-badge { display: inline-block; background: rgba(255,255,255,0.22); color: #ffffff; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 700; margin-top: 10px; }
        .table-items { width: 100%; border-collapse: collapse; margin-top: 14px; }
        .table-items th { text-align: left; padding: 10px 8px; border-bottom: 2px solid #e2e8f0; font-size: 12px; text-transform: uppercase; color: #64748b; }
        .table-items td { padding: 12px 8px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .totals-total { display: flex; justify-content: space-between; padding: 12px 0 0; margin-top: 10px; border-top: 2px dashed #cbd5e1; font-size: 18px; font-weight: 800; color: #4338ca; }
        .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff !important; padding: 12px 26px; border-radius: 999px; text-decoration: none; font-weight: 700; font-size: 14px; text-align: center; margin: 18px 0 6px; box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
        .footer { background: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
        .footer a { color: #667eea; text-decoration: none; font-weight: 600; }
        .security-note { font-size: 11px; color: #94a3b8; margin-top: 14px; line-height: 1.4; border-top: 1px solid #f1f5f9; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>LYM Diseño e Impresión</h1>
            <p>Comprobante Oficial de Tienda y Seguridad</p>
        </div>
        <div class="content">
            {$contenidoHTML}
        </div>
        <div class="footer">
            <p>© {$year} LYM Diseño e Impresión · Comercio Electrónico y Soluciones Personalizadas</p>
            <p>Este correo electrónico fue generado automáticamente de manera segura por el sistema oficial de la tienda LYM.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Correo oficial de confirmación de compra y factura
     */
    public static function compraConfirmada($datos) {
        $folio = htmlspecialchars($datos['folio'] ?? 'OR-00000');
        $clienteNombre = htmlspecialchars($datos['cliente_nombre'] ?? 'Cliente');
        $direccion = htmlspecialchars($datos['direccion'] ?? 'Dirección de entrega registrada');
        $metodoPago = htmlspecialchars($datos['metodo_pago'] ?? 'Monedero Digital');
        $subtotal = number_format((float)($datos['subtotal'] ?? 0), 2);
        $descuento = (float)($datos['descuento'] ?? 0);
        $iva = number_format((float)($datos['iva'] ?? 0), 2);
        $total = number_format((float)($datos['total'] ?? 0), 2);
        $items = $datos['items'] ?? [];

        $filasHTML = "";
        foreach ($items as $it) {
            $nom = htmlspecialchars($it['nombre'] ?? 'Producto');
            $cant = (int)($it['cantidad'] ?? 1);
            $pu = number_format((float)($it['precio_unitario'] ?? 0), 2);
            $sub = number_format((float)($it['subtotal'] ?? ($cant * $it['precio_unitario'])), 2);
            $filasHTML .= "<tr><td><strong>{$nom}</strong></td><td style='text-align:center;'>{$cant}</td><td style='text-align:right;'>\${$pu}</td><td style='text-align:right;'><strong>\${$sub}</strong></td></tr>";
        }

        $descuentoHTML = "";
        if ($descuento > 0) {
            $descFormato = number_format($descuento, 2);
            $descuentoHTML = "<div class='totals-row' style='color:#059669; font-weight:700;'><span>Descuento aplicado:</span><span>-\${$descFormato} MXN</span></div>";
        }

        $body = <<<HTML
        <div style="text-align:center; margin-bottom: 24px;">
            <div style="display:inline-block; width:52px; height:52px; line-height:52px; background:#dcfce7; color:#15803d; border-radius:50%; font-size:26px; font-weight:bold; margin-bottom:10px;">✓</div>
            <h2 style="margin:0; color:#15803d; font-size:22px; font-weight:800;">¡Gracias por tu compra, {$clienteNombre}!</h2>
            <p style="margin:4px 0 0; color:#64748b; font-size:14px;">Tu orden ha sido confirmada y procesada en nuestro sistema ERP y SCM.</p>
            <div style="margin-top:12px;"><span style="background:#e0e7ff; color:#3730a3; padding:6px 14px; border-radius:999px; font-weight:700; font-size:13px;">Folio de Orden: {$folio}</span></div>
        </div>

        <div class="card">
            <h3 style="margin-top:0; font-size:15px; color:#334155; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">📦 Resumen de Artículos</h3>
            <table class="table-items">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th style="text-align:center;">Cant.</th>
                        <th style="text-align:right;">Precio</th>
                        <th style="text-align:right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    {$filasHTML}
                </tbody>
            </table>

            <div style="margin-top: 18px; padding-top: 14px; border-top: 1px solid #e2e8f0;">
                <div class="totals-row"><span>Subtotal:</span><span>\${$subtotal} MXN</span></div>
                {$descuentoHTML}
                <div class="totals-row"><span>Impuestos (IVA 16%):</span><span>\${$iva} MXN</span></div>
                <div class="totals-total"><span>Total Pagado:</span><span>\${$total} MXN</span></div>
            </div>
        </div>

        <div class="card" style="font-size:13px; line-height:1.7;">
            <div style="margin-bottom:8px;"><strong>💳 Método de Pago:</strong> {$metodoPago}</div>
            <div style="margin-bottom:8px;"><strong>📍 Domicilio de Entrega:</strong> {$direccion}</div>
            <div><strong>🛡️ Estatus de Seguridad:</strong> Pago verificado y stock reservado en almacén SCM.</div>
        </div>

        <div style="text-align:center;">
            <a href="http://localhost/perfil.html#pedidos" class="btn">Ver estado de mi pedido en LYM</a>
        </div>

        <div class="security-note">
            Este comprobante es emitido por LYM Diseño e Impresión con fines fiscales y de seguimiento logístico. Si tienes dudas respecto a esta orden, puedes responder a este correo o levantar un ticket con tu folio <strong>{$folio}</strong>.
        </div>
HTML;

        return self::envolverPlantilla("Confirmación de Pedido {$folio} - LYM", $body);
    }

    /**
     * Correo para Atención al Cliente y Seguimiento de Tickets
     */
    public static function ticketNotificacion($datos) {
        $folio = htmlspecialchars($datos['folio'] ?? 'T-100');
        $clienteNombre = htmlspecialchars($datos['cliente_nombre'] ?? 'Estimado Cliente');
        $asunto = htmlspecialchars($datos['asunto'] ?? 'Consulta de Soporte');
        $mensajeOriginal = nl2br(htmlspecialchars($datos['mensaje'] ?? ''));
        $respuesta = !empty($datos['respuesta']) ? nl2br(htmlspecialchars($datos['respuesta'])) : null;
        $estado = htmlspecialchars($datos['estado'] ?? 'En proceso');

        $colorEstado = ($estado === 'Respondido' || $estado === 'Cerrado') ? '#10b981' : '#f59e0b';

        $seccionRespuesta = "";
        if ($respuesta) {
            $seccionRespuesta = <<<HTML
            <div style="background:#ecfdf5; border-left:4px solid #10b981; padding:14px; border-radius:8px; margin-top:16px;">
                <strong style="color:#065f46; display:block; margin-bottom:6px;">💬 Respuesta Oficial de nuestro Equipo:</strong>
                <p style="margin:0; color:#064e3b; font-size:14px;">{$respuesta}</p>
            </div>
HTML;
        }

        $body = <<<HTML
        <div style="text-align:center; margin-bottom: 22px;">
            <h2 style="margin:0; color:#1e293b; font-size:22px; font-weight:800;">Atención a Clientes LYM</h2>
            <div style="margin-top:10px;">
                <span style="background:#e0e7ff; color:#3730a3; padding:6px 14px; border-radius:999px; font-weight:700; font-size:13px;">Ticket: {$folio}</span>
                <span style="background:{$colorEstado}; color:#ffffff; padding:6px 14px; border-radius:999px; font-weight:700; font-size:13px; margin-left:6px;">{$estado}</span>
            </div>
        </div>

        <p>Hola <strong>{$clienteNombre}</strong>,</p>
        <p>Hemos recibido y actualizado tu solicitud de atención en nuestra plataforma oficial:</p>

        <div class="card">
            <div style="margin-bottom:8px;"><strong>Asunto:</strong> {$asunto}</div>
            <div style="margin-top:10px; font-size:14px; color:#475569;">
                <strong>Tu mensaje:</strong><br>
                <div style="margin-top:4px; padding:10px; background:#ffffff; border-radius:6px; border:1px solid #e2e8f0;">{$mensajeOriginal}</div>
            </div>
            {$seccionRespuesta}
        </div>

        <div style="text-align:center;">
            <a href="http://localhost/perfil.html#tickets" class="btn">Consultar historial de tickets</a>
        </div>
HTML;

        return self::envolverPlantilla("Ticket {$folio}: {$asunto} - LYM Soporte", $body);
    }

    /**
     * Correo de Seguridad y Verificación de Cuenta
     */
    public static function alertaSeguridad($datos) {
        $clienteNombre = htmlspecialchars($datos['nombre'] ?? 'Usuario');
        $codigo = htmlspecialchars($datos['codigo'] ?? '123456');
        $motivo = htmlspecialchars($datos['motivo'] ?? 'Verificación de cuenta o inicio de sesión seguro');

        $body = <<<HTML
        <div style="text-align:center; margin-bottom: 24px;">
            <div style="display:inline-block; width:52px; height:52px; line-height:52px; background:#eff6ff; color:#2563eb; border-radius:50%; font-size:26px; font-weight:bold; margin-bottom:10px;">🔒</div>
            <h2 style="margin:0; color:#1e293b; font-size:22px; font-weight:800;">Código de Seguridad Oficial</h2>
            <p style="margin:6px 0 0; color:#64748b; font-size:14px;">{$motivo}</p>
        </div>

        <p>Hola <strong>{$clienteNombre}</strong>,</p>
        <p>Utiliza el siguiente código único de seguridad para completar tu operación en LYM Diseño e Impresión:</p>

        <div style="text-align:center; margin: 26px 0;">
            <div style="display:inline-block; background:linear-gradient(135deg, #4338ca, #6d28d9); color:#ffffff; font-size:32px; font-weight:900; letter-spacing:8px; padding:16px 36px; border-radius:14px; box-shadow:0 6px 18px rgba(67,56,202,0.25);">
                {$codigo}
            </div>
            <div style="margin-top:10px; font-size:12px; color:#64748b;">Este código expirará en 15 minutos.</div>
        </div>

        <div class="card" style="font-size:13px; color:#475569; background:#fffbeb; border-color:#fef3c7;">
            ⚠️ <strong>Aviso de Seguridad:</strong> El equipo oficial de LYM nunca te solicitará este código por teléfono, WhatsApp o redes sociales. Si tú no realizaste esta solicitud, ignora este mensaje.
        </div>
HTML;

        return self::envolverPlantilla("Código de Seguridad LYM: {$codigo}", $body);
    }

    /**
     * Correo de Prueba para el Panel Administrativo
     */
    public static function correoPrueba($destinatario, $emisor) {
        $fecha = date('d/m/Y H:i:s');
        $dest = htmlspecialchars($destinatario);
        $emi = htmlspecialchars($emisor);

        $body = <<<HTML
        <div style="text-align:center; margin-bottom: 24px;">
            <div style="display:inline-block; width:52px; height:52px; line-height:52px; background:#dcfce7; color:#15803d; border-radius:50%; font-size:26px; font-weight:bold; margin-bottom:10px;">✓</div>
            <h2 style="margin:0; color:#15803d; font-size:22px; font-weight:800;">¡Tu conexión SMTP con Gmail funciona al 100%!</h2>
            <p style="margin:6px 0 0; color:#64748b; font-size:14px;">La tienda oficial LYM ahora puede enviar correos reales.</p>
        </div>

        <div class="card" style="font-size:14px;">
            <h3 style="margin-top:0; font-size:15px; color:#334155;">Detalles de la Verificación:</h3>
            <div style="margin-bottom:8px;"><strong>📧 Cuenta Emisora:</strong> {$emi}</div>
            <div style="margin-bottom:8px;"><strong>📬 Cuenta Receptora:</strong> {$dest}</div>
            <div style="margin-bottom:8px;"><strong>🕒 Fecha y Hora:</strong> {$fecha}</div>
            <div><strong>🛡️ Cifrado:</strong> SSL / TLS Seguro (Cifrado de extremo a extremo)</div>
        </div>

        <p style="font-size:14px; color:#475569;">
            A partir de este momento, todos los clientes que realicen compras, abran tickets o soliciten comprobantes recibirán sus correos de manera automática y auténtica.
        </p>
HTML;

        return self::envolverPlantilla("Prueba de Conexión Exitosa - Tienda Oficial LYM", $body);
    }
}
