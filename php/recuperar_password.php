<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/plantillas_correo.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

try {
    $pdo = getDBConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $accion = trim($input['accion'] ?? 'solicitar_codigo');
    $email = trim(cleanInput($input['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Por favor ingresa un correo electrónico válido.']);
        exit();
    }

    // ==============================================================
    // ACCIÓN 1: SOLICITAR CÓDIGO DE 6 DÍGITOS
    // ==============================================================
    if ($accion === 'solicitar_codigo') {
        $stmtUser = $pdo->prepare("SELECT id, username, email FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmtUser->execute([$email]);
        $user = $stmtUser->fetch();

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No encontramos ninguna cuenta registrada con el correo: ' . $email]);
            exit();
        }

        // Generar código numérico de 6 dígitos
        $codigo = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Desactivar códigos previos no utilizados para este correo
        $stmtCancel = $pdo->prepare("UPDATE password_resets SET utilizado = 1 WHERE email = ? AND utilizado = 0");
        $stmtCancel->execute([$email]);

        // Guardar nuevo código con vigencia de 15 minutos
        $stmtIns = $pdo->prepare("
            INSERT INTO password_resets (email, codigo, expira, utilizado, created_at)
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0, NOW())
        ");
        $stmtIns->execute([$email, $codigo]);

        // Enviar correo de seguridad al cliente
        $mailer = new LYMMailer($pdo);
        $htmlEmail = LYMPlantillasCorreo::alertaSeguridad([
            'nombre' => $user['username'],
            'codigo' => $codigo,
            'motivo' => 'Solicitud de restablecimiento de contraseña para tu cuenta de cliente en LYM'
        ]);

        $envio = $mailer->enviar($email, "Código de Seguridad LYM: {$codigo}", $htmlEmail, 'seguridad', 'OTP-' . $user['id']);

        echo json_encode([
            'success' => true,
            'message' => 'Hemos enviado un código de seguridad de 6 dígitos a tu correo: ' . $email,
            'email' => $email,
            'correo_enviado' => !empty($envio['success'])
        ]);
        exit();
    }

    // ==============================================================
    // ACCIÓN 2: CAMBIAR CONTRASEÑA CON CÓDIGO VERIFICADO
    // ==============================================================
    if ($accion === 'cambiar_password') {
        $codigo = trim($input['codigo'] ?? '');
        $nuevaPassword = $input['password'] ?? '';

        if (empty($codigo) || strlen($codigo) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Debes ingresar el código de 6 dígitos que recibiste en tu correo.']);
            exit();
        }

        if (empty($nuevaPassword) || strlen($nuevaPassword) < 6) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'La nueva contraseña debe tener al menos 6 caracteres.']);
            exit();
        }

        // Verificar código
        $stmtCheck = $pdo->prepare("
            SELECT id FROM password_resets 
            WHERE email = ? AND codigo = ? AND utilizado = 0 AND expira >= NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stmtCheck->execute([$email, $codigo]);
        $resetId = $stmtCheck->fetchColumn();

        if (!$resetId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'El código de seguridad es inválido o ya expiró. Por favor solicita uno nuevo.']);
            exit();
        }

        // Marcar código como utilizado
        $stmtUsed = $pdo->prepare("UPDATE password_resets SET utilizado = 1 WHERE id = ?");
        $stmtUsed->execute([$resetId]);

        // Actualizar contraseña del usuario
        $nuevoHash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
        $stmtUpd = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE LOWER(email) = LOWER(?)");
        $stmtUpd->execute([$nuevoHash, $email]);

        // Obtener nombre de usuario para correo de confirmación
        $stmtName = $pdo->prepare("SELECT username FROM usuarios WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmtName->execute([$email]);
        $username = $stmtName->fetchColumn() ?: 'Cliente';

        // Enviar notificación de confirmación de seguridad
        try {
            $mailer = new LYMMailer($pdo);
            $htmlConf = LYMPlantillasCorreo::passwordCambiada(['nombre' => $username]);
            $mailer->enviar($email, "Aviso de Seguridad: Contraseña Modificada - LYM", $htmlConf, 'seguridad', 'PASS-UPD');
        } catch (Exception $eMail) {
            error_log("Aviso correo password cambiado: " . $eMail->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => '¡Tu contraseña ha sido restablecida exitosamente! Ya puedes iniciar sesión con tu nueva contraseña.'
        ]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error de servidor: ' . $e->getMessage()]);
}
