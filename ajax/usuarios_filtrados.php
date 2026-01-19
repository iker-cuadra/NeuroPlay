<?php
require_once "../includes/conexion.php";
require_once "../includes/auth.php";

requireRole("profesional");

$filtro = $_GET["filtro_rol"] ?? "todos";
$roles_validos = ["todos", "usuario", "familiar", "profesional"];
if (!in_array($filtro, $roles_validos, true)) {
    $filtro = "todos";
}

if ($filtro === "todos") {
    $stmt = $conexion->query("SELECT id, nombre, email, rol, foto FROM usuarios ORDER BY id DESC");
} else {
    $stmt = $conexion->prepare("SELECT id, nombre, email, rol, foto FROM usuarios WHERE rol = ? ORDER BY id DESC");
    $stmt->execute([$filtro]);
}

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($usuarios as $u): ?>
<tr>
    <td style="text-align:center;">
        <img class="user-photo" src="uploads/<?= $u['foto'] ?: 'default.png' ?>">
    </td>
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
<?php endforeach; ?>
