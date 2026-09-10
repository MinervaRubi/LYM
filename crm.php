<?php
require_once __DIR__ . '/includes/config.php';

// Control de acceso CRM: requiere permisos de administrador
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Cargar ecosistema CRM
include __DIR__ . '/gestor.html';
?>
