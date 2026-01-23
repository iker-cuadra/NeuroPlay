<?php
session_start();

// Destruir TODO lo relacionado con la sesión
$_SESSION = array();
session_unset();
session_destroy();

// Invalidar cookies de sesión
setcookie(session_name(), '', time() - 3600, '/');

// Evitar volver atrás DE VERDAD
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Redirigir a la pantalla principal
header("Location: neuro.html");
exit;
?>
