<?php
require_once __DIR__ . '/includes/config.php';

// Control de acceso: solo administradores
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Cargar panel de gestión / administración
include __DIR__ . '/gestor.html';
?>
