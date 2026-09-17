<?php
require_once __DIR__ . '/includes/config.php';

// Control de acceso CRM: requiere permisos de administrador o trabajador
if (!isLoggedIn() || !isStaff()) {
    redirect('index.php');
}

// Cargar ecosistema CRM
include __DIR__ . '/gestor.html';
?>
