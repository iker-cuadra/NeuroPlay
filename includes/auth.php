<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================================
   FUNCIÓN PARA INICIAR SESIÓN
   ================================ */
function loginUser($usuario) {
    $_SESSION["usuario_id"] = $usuario["id"];
    $_SESSION["nombre"] = $usuario["nombre"];
    $_SESSION["rol"] = $usuario["rol"];
}

/* ================================
   FUNCIÓN PARA CERRAR SESIÓN
   ================================ */
function logoutUser() {
    session_unset();
    session_destroy();
}

/* ================================
   FUNCIÓN PARA EXIGIR UN ROL
   BLOQUEA VOLVER ATRÁS
   ================================ */
function requireRole($rolNecesario) {

    // Evitar que el navegador use caché
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: 0");

    if (!isset($_SESSION["rol"]) || $_SESSION["rol"] !== $rolNecesario) {
        // Redirigir a la página principal si no hay sesión
       header("Location: /htdocs/neuroplay/neuro.html");
        exit;
    }
}

/* ================================
   FUNCIÓN PARA SABER SI HAY SESIÓN
   ================================ */
function isLogged() {
    return isset($_SESSION["usuario_id"]);
}
?>
