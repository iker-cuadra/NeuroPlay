<?php
// ajax/usuarios_filtrados.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../includes/conexion.php";
require_once "../includes/auth.php";

// Solo profesionales pueden acceder (misma restricción que la página)
requireRole("profesional");

// -------------------------
// FILTRO POR ROL (GET)
// -------------------------
$filtro_rol = $_GET["filtro_rol"] ?? "todos";
$roles_validos = ["todos", "usuario", "familiar", "profesional"];
if (!in_array($filtro_rol, $roles_validos, true)) {
    $filtro_rol = "todos";
}

// -------------------------
// PAGINACIÓN
// -------------------------
$por_pagina = 6;
$pagina_actual = isset($_GET["p"]) ? (int)$_GET["p"] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// Contar total
if ($filtro_rol === "todos") {
    $stmt_count = $conexion->query("SELECT COUNT(*) FROM usuarios");
} else {
    $stmt_count = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = ?");
    $stmt_count->execute([$filtro_rol]);
}
$total_usuarios = (int)$stmt_count->fetchColumn();
$total_paginas = (int)ceil($total_usuarios / $por_pagina);

// Cargar usuarios
if ($filtro_rol === "todos") {
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios ORDER BY id DESC LIMIT $por_pagina OFFSET $offset");
    $stmt->execute();
} else {
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE rol = ? ORDER BY id DESC LIMIT $por_pagina OFFSET $offset");
    $stmt->execute([$filtro_rol]);
}
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------
// HELPERS: foto por defecto según rol
// -------------------------
function foto_por_defecto_por_rol(string $rol): string {
    switch ($rol) {
        case "usuario": return "default_usuario.png";
        case "familiar": return "default_familiar.png";
        case "profesional": return "default_profesional.png";
        default: return "default.png";
    }
}

function foto_a_mostrar(?string $foto, string $rol): string {
    $foto = trim((string)$foto);

    if ($foto === "") {
        return foto_por_defecto_por_rol($rol);
    }

    $defaults = [
        "default.png",
        "default_usuario.png",
        "default_familiar.png",
        "default_profesional.png"
    ];

    if (in_array($foto, $defaults, true)) {
        return foto_por_defecto_por_rol($rol);
    }

    return $foto;
}

// Mantener filtro/página en enlaces de acciones
function qs_keep_ajax(string $filtro_rol, int $pagina_actual, array $extra = []): string {
    $keep = [];
    if ($filtro_rol !== "todos") $keep["filtro_rol"] = $filtro_rol;
    if ($pagina_actual > 0) $keep["p"] = $pagina_actual;

    $all = array_merge($keep, $extra);
    return $all ? ("?" . http_build_query($all)) : "";
}

// -------------------------
// HTML de la tabla (tbody)
// -------------------------
$tabla_html = "";
foreach ($usuarios as $u) {
    $foto = foto_a_mostrar($u["foto"] ?? "", $u["rol"]);
    $tabla_html .= '<tr>';
    $tabla_html .= '  <td style="text-align:center;"><img class="user-photo" src="uploads/' . htmlspecialchars($foto, ENT_QUOTES) . '"></td>';
    $tabla_html .= '  <td>' . htmlspecialchars($u["nombre"]) . '</td>';
    $tabla_html .= '  <td>' . htmlspecialchars($u["email"]) . '</td>';
    $tabla_html .= '  <td><span class="role-badge ' . htmlspecialchars($u["rol"]) . '">' . htmlspecialchars($u["rol"]) . '</span></td>';
    $tabla_html .= '  <td>';
    $tabla_html .= '    <div style="display:flex; gap:10px; justify-content:center;">';

    if (($u["rol"] ?? "") === "usuario") {
        $tabla_html .= '      <a class="action-btn btn-evaluar" href="evaluar_usuario.php' . qs_keep_ajax($filtro_rol, $pagina_actual, ["user_id" => (int)$u["id"]]) . '"><i class="fas fa-cog"></i></a>';
    }

    $tabla_html .= '      <a class="action-btn btn-editar" href="gestionar_users.php' . qs_keep_ajax($filtro_rol, $pagina_actual, ["editar_id" => (int)$u["id"]]) . '"><i class="fas fa-pen"></i></a>';
    $tabla_html .= '      <a class="action-btn btn-eliminar" href="gestionar_users.php' . qs_keep_ajax($filtro_rol, $pagina_actual, ["eliminar_id" => (int)$u["id"]]) . '" onclick="return confirm(\'¿Seguro?\');"><i class="fas fa-trash"></i></a>';

    $tabla_html .= '    </div>';
    $tabla_html .= '  </td>';
    $tabla_html .= '</tr>';
}

// -------------------------
// HTML de la paginación
// -------------------------
$paginacion_html = "";
if ($total_paginas > 1) {
    $paginacion_html .= '<div class="pagination-container">';

    if ($pagina_actual <= 1) {
        $paginacion_html .= '<span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>';
    } else {
        $paginacion_html .= '<a href="#" class="page-link page-ajax" data-page="' . ($pagina_actual - 1) . '"><i class="fas fa-chevron-left"></i></a>';
    }

    for ($i = 1; $i <= $total_paginas; $i++) {
        $active = ($i === $pagina_actual) ? "active" : "";
        $paginacion_html .= '<a href="#" class="page-link page-ajax ' . $active . '" data-page="' . $i . '">' . $i . '</a>';
    }

    if ($pagina_actual >= $total_paginas) {
        $paginacion_html .= '<span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>';
    } else {
        $paginacion_html .= '<a href="#" class="page-link page-ajax" data-page="' . ($pagina_actual + 1) . '"><i class="fas fa-chevron-right"></i></a>';
    }

    $paginacion_html .= '</div>';
}

// -------------------------
// RESPUESTA JSON
// -------------------------
header("Content-Type: application/json; charset=UTF-8");
echo json_encode([
    "tabla" => $tabla_html,
    "paginacion" => $paginacion_html
]);
exit;
