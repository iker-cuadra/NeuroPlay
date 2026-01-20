<?php
require_once "../includes/conexion.php";
require_once "../includes/auth.php";

requireRole("profesional");

// Parámetros de entrada
$filtro = $_GET["filtro_rol"] ?? "todos";
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$por_pagina = 6;
$offset = ($pagina_actual - 1) * $por_pagina;

// 1. Contar total para la paginación dinámica
if ($filtro === "todos") {
    $stmt_count = $conexion->query("SELECT COUNT(*) FROM usuarios");
} else {
    $stmt_count = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = ?");
    $stmt_count->execute([$filtro]);
}
$total_usuarios = $stmt_count->fetchColumn();
$total_paginas = ceil($total_usuarios / $por_pagina);

// 2. Obtener los usuarios con LIMIT y OFFSET
if ($filtro === "todos") {
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
} else {
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE rol = ? ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $filtro, PDO::PARAM_STR);
    $stmt->bindValue(2, $por_pagina, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
}
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Construir el HTML de la Tabla
ob_start();
foreach ($usuarios as $u): ?>
<tr>
    <td style="text-align:center;"><img class="user-photo" src="uploads/<?= $u['foto'] ?: 'default.png' ?>"></td>
    <td><?= htmlspecialchars($u["nombre"]) ?></td>
    <td><?= htmlspecialchars($u["email"]) ?></td>
    <td><span class="role-badge <?= $u["rol"] ?>"><?= $u["rol"] ?></span></td>
    <td>
        <div style="display:flex; gap:10px; justify-content:center;">
            <?php if ($u['rol'] === 'usuario'): ?>
                <a class="action-btn btn-evaluar" href="evaluar_usuario.php?user_id=<?= $u["id"] ?>"><i class="fas fa-cog"></i></a>
            <?php endif; ?>
            <a class="action-btn btn-editar" href="gestionar_users.php?editar_id=<?= $u["id"] ?>"><i class="fas fa-pen"></i></a>
            <a class="action-btn btn-eliminar" href="gestionar_users.php?eliminar_id=<?= $u["id"] ?>" onclick="return confirm('¿Seguro?');"><i class="fas fa-trash"></i></a>
        </div>
    </td>
</tr>
<?php endforeach;
$tabla_html = ob_get_clean();

// ... (Todo el código anterior de usuarios_filtrados.php se mantiene igual hasta el punto 4)

// 4. Construir el HTML de la Paginación con flechas < >
ob_start();
if ($total_paginas > 1): ?>
    
    <?php if ($pagina_actual <= 1): ?>
        <span class="page-link disabled"><i class="fas fa-chevron-left"></i></span>
    <?php else: ?>
        <a href="#" class="page-link page-ajax" data-page="<?= $pagina_actual - 1 ?>">
            <i class="fas fa-chevron-left"></i>
        </a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
        <a href="#" class="page-link page-ajax <?= ($i == $pagina_actual) ? 'active' : '' ?>" data-page="<?= $i ?>">
            <?= $i ?>
        </a>
    <?php endfor; ?>

    <?php if ($pagina_actual >= $total_paginas): ?>
        <span class="page-link disabled"><i class="fas fa-chevron-right"></i></span>
    <?php else: ?>
        <a href="#" class="page-link page-ajax" data-page="<?= $pagina_actual + 1 ?>">
            <i class="fas fa-chevron-right"></i>
        </a>
    <?php endif; ?>

<?php endif;
$paginacion_html = ob_get_clean();

// 5. Respuesta final
header('Content-Type: application/json');
echo json_encode([
    'tabla' => $tabla_html,
    'paginacion' => $paginacion_html
]);