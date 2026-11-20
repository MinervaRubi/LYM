<?php
/**
 * LYM - Motor de Envío de Correos SMTP Seguro (Gmail, Outlook, etc.)
 * Soporta SSL (465) y TLS (587) nativo sin dependencias de Composer.
 */

require_once __DIR__ . '/config.php';

class LYMMailer {
    private $host;
    private $port;
    private $security; // 'ssl' o 'tls'
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $pdo;

    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDBConnection();
        $this->cargarConfiguracion();
    }

    /**
     * Carga la configuración SMTP desde la base de datos
     */
    private function cargarConfiguracion() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM configuracion_correo WHERE activo = 1 ORDER BY id DESC LIMIT 1");
            $cfg = $stmt->fetch();

            if ($cfg) {
                $this->host = $cfg['servidor_smtp'] ?: 'smtp.gmail.com';
                $this->port = (int)($cfg['puerto_smtp'] ?: 465);
                $this->security = strtolower($cfg['seguridad'] ?: 'ssl');
                $this->username = $cfg['usuario_smtp'] ?: '';
                $this->password = $cfg['password_smtp'] ?: '';
                $this->fromName = $cfg['remitente_nombre'] ?: 'LYM Diseño e Impresión Oficial';
                $this->fromEmail = $cfg['remitente_email'] ?: ($this->username ?: 'notificaciones@lym.com');
            } else {
                $this->host = 'smtp.gmail.com';
                $this->port = 465;
                $this->security = 'ssl';
                $this->username = '';
                $this->password = '';
                $this->fromName = 'LYM Diseño e Impresión Oficial';
                $this->fromEmail = 'notificaciones@lym.com';
            }
        } catch (Exception $e) {
            $this->host = 'smtp.gmail.com';
            $this->port = 465;
            $this->security = 'ssl';
            $this->username = '';
            $this->password = '';
            $this->fromName = 'LYM Diseño e Impresión Oficial';
            $this->fromEmail = 'notificaciones@lym.com';
        }
    }

    /**
     * Enviar correo electrónico
     * @param string $destinatario
     * @param string $asunto
     * @param string $cuerpoHTML
     * @param string $tipo ('compra', 'ticket', 'seguridad', 'prueba')
     * @param string $referencia Folio o ID
     * @return array ['success' => bool, 'message' => string, 'log' => string]
     */
    public function enviar($destinatario, $asunto, $cuerpoHTML, $tipo = 'general', $referencia = null) {
        $destinatario = trim($destinatario);
        if (empty($destinatario) || !filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => "Dirección de correo destinatario no válida: '{$destinatario}'"];
        }

        if (empty($this->username) || empty($this->password)) {
            $errMsg = "Las credenciales SMTP de la tienda oficial no han sido configuradas (Usuario o Contraseña de aplicación vacíos).";
            $this->registrarBitacora($destinatario, $asunto, $tipo, $referencia, 'fallido', $errMsg);
            return [
                'success' => false, 
                'error' => $errMsg,
                'detalles' => 'Configura tu cuenta de Gmail y su contraseña de aplicación en la sección de Configuración de Correo del Gestor.'
            ];
        }

        $log = [];
        $socket = null;

        try {
            $timeout = 15;
            $hostPrefix = ($this->security === 'ssl' || $this->port == 465) ? "ssl://{$this->host}" : "tcp://{$this->host}";
            
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);

            $log[] = "Conectando a {$hostPrefix}:{$this->port}...";
            $socket = @stream_socket_client("{$hostPrefix}:{$this->port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

            if (!$socket) {
                throw new Exception("No se pudo conectar al servidor SMTP: {$errstr} ({$errno})");
            }

            stream_set_timeout($socket, $timeout);
            $resp = $this->leerRespuesta($socket);
            $log[] = "Servidor conectado: " . trim($resp);

            // Handshake inicial
            $this->enviarComando($socket, "EHLO " . gethostname(), $log);
            $resp = $this->leerRespuesta($socket);
            $log[] = trim($resp);

            // STARTTLS si es puerto 587 / modo tls
            if (($this->security === 'tls' || $this->port == 587) && strpos($hostPrefix, 'ssl://') === false) {
                $this->enviarComando($socket, "STARTTLS", $log);
                $resp = $this->leerRespuesta($socket);
                $log[] = trim($resp);

                if (substr($resp, 0, 3) !== '220') {
                    throw new Exception("Fallo al iniciar STARTTLS: " . $resp);
                }

                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$crypto) {
                    throw new Exception("No se pudo negociar cifrado TLS con el servidor.");
                }

                $this->enviarComando($socket, "EHLO " . gethostname(), $log);
                $resp = $this->leerRespuesta($socket);
                $log[] = trim($resp);
            }

            // Autenticación AUTH LOGIN
            $this->enviarComando($socket, "AUTH LOGIN", $log);
            $resp = $this->leerRespuesta($socket);
            $log[] = trim($resp);
            if (substr($resp, 0, 3) !== '334') {
                throw new Exception("Servidor rechazó AUTH LOGIN: " . $resp);
            }

            $this->enviarComando($socket, base64_encode($this->username), $log, true);
            $resp = $this->leerRespuesta($socket);
            $log[] = trim($resp);
            if (substr($resp, 0, 3) !== '334') {
                throw new Exception("Usuario SMTP rechazado: " . $resp);
            }

            $this->enviarComando($socket, base64_encode($this->password), $log, true);
            $resp = $this->leerRespuesta($socket);
            $log[] = trim($resp);
            if (substr($resp, 0, 3) !== '235') {
                throw new Exception("Autenticación fallida con Gmail/SMTP (revisa tu usuario y contraseña de aplicación): " . $resp);
            }

            // Origen y Destino
            $this->enviarComando($socket, "MAIL FROM:<{$this->fromEmail}>", $log);
            $resp = $this->leerRespuesta($socket);
            $log[] = trim($resp);
            if (substr($resp, 0, 3) !== '250') {
                throw new Exception("Error en MAIL FROM: " . $resp);
            }

            $this->enviarComando($socket, "RCPT TO:<{$destinatario}>", $log);
            $resp = $this->leerRespuesta($socket);
            $log[] = trim($resp);
            if (substr($resp, 0, 3) !== '250') {
                throw new Exception("Error en RCPT TO para '{$destinatario}': " . $resp);
            }

            // Enviar contenido DATA
            $this->enviarComando($socket, "DATA", $log);
            $resp = $this->leerRespuesta($socket);
            $log[] = trim($resp);
            if (substr($resp, 0, 3) !== '354') {
                throw new Exception("Servidor no aceptó DATA: " . $resp);
            }

            // Encabezados MIME RFC 5322
            $asuntoCodificado = "=?UTF-8?B?" . base64_encode($asunto) . "?=";
            $nombreRemitenteCodificado = "=?UTF-8?B?" . base64_encode($this->fromName) . "?=";
            $fechaRfc = date('r');
            $messageId = "<" . md5(uniqid(time())) . "@" . ($this->host ?: 'lym.com') . ">";

            $headers = [];
            $headers[] = "From: {$nombreRemitenteCodificado} <{$this->fromEmail}>";
            $headers[] = "To: <{$destinatario}>";
            $headers[] = "Subject: {$asuntoCodificado}";
            $headers[] = "Date: {$fechaRfc}";
            $headers[] = "Message-ID: {$messageId}";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: base64";
            $headers[] = "X-Mailer: LYM Official Store Mailer/1.0";

            $cuerpoBase64 = chunk_split(base64_encode($cuerpoHTML));
            $mensajeCompleto = implode("\r\n", $headers) . "\r\n\r\n" . $cuerpoBase64 . "\r\n.";

            fwrite($socket, $mensajeCompleto . "\r\n");
            $resp = $this->leerRespuesta($socket);
            $log[] = trim($resp);

            if (substr($resp, 0, 3) !== '250') {
                throw new Exception("El servidor no confirmó la entrega del mensaje: " . $resp);
            }

            // Cerrar conexión limpia
            $this->enviarComando($socket, "QUIT", $log);
            fclose($socket);

            $this->registrarBitacora($destinatario, $asunto, $tipo, $referencia, 'enviado', 'Entrega SMTP exitosa (250 OK)');

            return [
                'success' => true,
                'message' => 'Correo enviado exitosamente a ' . $destinatario,
                'log' => implode("\n", $log)
            ];

        } catch (Exception $e) {
            if ($socket && is_resource($socket)) {
                @fclose($socket);
            }
            $errMsg = $e->getMessage();
            $this->registrarBitacora($destinatario, $asunto, $tipo, $referencia, 'fallido', $errMsg);

            return [
                'success' => false,
                'error' => $errMsg,
                'log' => implode("\n", $log)
            ];
        }
    }

    private function enviarComando($socket, $cmd, &$log, $ocultar = false) {
        fwrite($socket, $cmd . "\r\n");
        if (!$ocultar) {
            $log[] = "> " . $cmd;
        } else {
            $log[] = "> [CREDENCIAL CIFRADA]";
        }
    }

    private function leerRespuesta($socket) {
        $data = "";
        while ($line = fgets($socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    private function registrarBitacora($destinatario, $asunto, $tipo, $referencia, $estado, $mensajeServidor) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO bitacora_correos (destinatario, asunto, tipo, referencia, estado, mensaje_servidor, fecha)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$destinatario, $asunto, $tipo, $referencia, $estado, substr($mensajeServidor, 0, 500)]);
        } catch (Exception $e) {
            error_log("Error al registrar bitácora de correos: " . $e->getMessage());
        }
    }
}
