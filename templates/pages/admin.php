<?php
/**
 * Admin panel template
 * @var \Ngw\Models\RegistrationRequest $requestModel
 * @var \Ngw\Auth\SessionManager $session
 * @var \Ngw\Auth\Auth $auth
 */

if (!$session->isAdmin()) {
    echo '<div class="alert alert-error">Acceso denegado</div>';
    return;
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminAction = $_POST['admin_action'] ?? '';

    if ($adminAction === 'approve' && !empty($_POST['request_id'])) {
        $requestId = (int) $_POST['request_id'];

        try {
            $requestModel->approve($requestId, $session->getUserId());
            $success = "Usuario aprobado correctamente. El usuario puede iniciar sesión con la contraseña que eligió.";
            redirect('index.php?option=4');
        } catch (\Exception $e) {
            $error = "Error al aprobar: " . $e->getMessage();
        }
    } elseif ($adminAction === 'reject' && !empty($_POST['request_id'])) {
        $requestId = (int) $_POST['request_id'];

        try {
            $requestModel->reject($requestId, $session->getUserId());
            $success = "Solicitud rechazada";
            redirect('index.php?option=4');
        } catch (\Exception $e) {
            $error = "Error al rechazar: " . $e->getMessage();
        }
    } elseif ($adminAction === 'promote_to_teacher' && !empty($_POST['user_id'])) {
        $userId = (int) $_POST['user_id'];

        try {
            $sql = "UPDATE users SET role = 'teacher' WHERE id = :id AND is_admin = 0";
            $db->execute($sql, ['id' => $userId]);
            $success = "Usuario promovido a profesor correctamente";
            redirect('index.php?option=4');
        } catch (\Exception $e) {
            $error = "Error al promover usuario: " . $e->getMessage();
        }
    } elseif ($adminAction === 'delete_user' && !empty($_POST['user_id']) && isset($_POST['confirm'])) {
        $userId = (int) $_POST['user_id'];

        try {
            $auth->deleteUser($userId, $session->getUserId());
            $success = "Usuario eliminado correctamente";
            redirect('index.php?option=4');
        } catch (\Exception $e) {
            $error = "Error al eliminar usuario: " . $e->getMessage();
        }
    }
}

// Determine if user is admin or teacher
$isAdmin = $session->isAdmin();
$isTeacher = $session->isTeacher() && !$isAdmin;

// Get requests based on role
if ($isAdmin) {
    $pendingRequests = $requestModel->getPending();
    $approvedRequests = $requestModel->getAll('approved');
    $rejectedRequests = $requestModel->getAll('rejected');
} else if ($isTeacher) {
    // Teachers see only their assigned student requests
    $userId = $session->getUserId();
    $sql = "SELECT * FROM registration_requests WHERE status = 'pending' AND assigned_teacher_id = :teacher_id ORDER BY requested_at ASC";
    $pendingRequests = $db->fetchAll($sql, ['teacher_id' => $userId]);
    
    $sql = "SELECT * FROM registration_requests WHERE status = 'approved' AND assigned_teacher_id = :teacher_id ORDER BY requested_at DESC";
    $approvedRequests = $db->fetchAll($sql, ['teacher_id' => $userId]);
    
    $sql = "SELECT * FROM registration_requests WHERE status = 'rejected' AND assigned_teacher_id = :teacher_id ORDER BY requested_at DESC";
    $rejectedRequests = $db->fetchAll($sql, ['teacher_id' => $userId]);
} else {
    $pendingRequests = [];
    $approvedRequests = [];
    $rejectedRequests = [];
}

$allUsers = $auth->getAllUsers();
?>

<div class="card">
    <h2>
        <?php if ($isAdmin) : ?>
            Panel de Administración
        <?php else : ?>
            Panel de Profesor - Solicitudes de Alumnos
        <?php endif; ?>
    </h2>
    
    <?php if (isset($error)) : ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)) : ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    
    <h3>Solicitudes Pendientes (<?= count($pendingRequests) ?>)</h3>
    
    <?php if (empty($pendingRequests)) : ?>
        <p class="text-center">No hay solicitudes pendientes.</p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Tipo</th>
                    <th>Motivo</th>
                    <?php if ($isAdmin) : ?><th>Profesor asignado</th><?php endif; ?>
                    <th>Fecha solicitud</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingRequests as $request) : ?>
                    <tr>
                        <td><strong><?= e($request['username']) ?></strong></td>
                        <td><?= e($request['email'] ?: '-') ?></td>
                        <td>
                            <?php
                                $role = $request['role'] ?? 'student';
                                $roleLabel = $role === 'teacher' ? 'Profesor' : 'Alumno';
                                $roleColor = $role === 'teacher' ? 'color: #3b82f6;' : '';
                            ?>
                            <span style="<?= $roleColor ?>"><?= e($roleLabel) ?></span>
                        </td>
                        <td><?= e($request['reason'] ?: '-') ?></td>
                        <?php if ($isAdmin) : ?>
                            <td>
                                <?php
                                    if (!empty($request['assigned_teacher_id'])) {
                                        $teacher = $db->fetchOne("SELECT username FROM users WHERE id = :id", ['id' => $request['assigned_teacher_id']]);
                                        echo $teacher ? e($teacher['username']) : '-';
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                        <?php endif; ?>
                        <td><?= e($request['requested_at']) ?></td>
                        <td>
                                                        <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" class="admin-approve-form">
                                <input type="hidden" name="admin_action" value="approve">
                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                <button type="submit" class="btn-success btn-small">Aprobar</button>
                            </form>
                            
                                                        <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" class="admin-reject-form">
                                <input type="hidden" name="admin_action" value="reject">
                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                <button type="submit" class="btn-danger btn-small">Rechazar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Gestión de Usuarios</h2>
    
    <?php if ($isAdmin) : ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Email</th>
                <th>Tipo</th>
                <th>Admin</th>
                <th>Estado</th>
                <th>Fecha creación</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allUsers as $user) : ?>
                <tr>
                    <td><?= e($user['id']) ?></td>
                    <td><strong><?= e($user['username']) ?></strong></td>
                    <td><?= e($user['email'] ?: '-') ?></td>
                    <td>
                        <?php
                            $userIsAdmin = (int)$user['is_admin'] === 1;
                            $userRole = $user['role'] ?? 'student';
                            if ($userIsAdmin) {
                                $roleLabel = 'Admin';
                                $roleColor = 'color: #ef4444;';
                            } else {
                                $roleLabel = $userRole === 'teacher' ? 'Profesor' : 'Alumno';
                                $roleColor = $userRole === 'teacher' ? 'color: #3b82f6;' : '';
                            }
                        ?>
                        <span style="<?= $roleColor ?>"><?= e($roleLabel) ?></span>
                    </td>
                    <td><?= $userIsAdmin ? '✓ Admin' : '-' ?></td>
                    <td>
                        <?php if ((int)$user['is_approved'] === 1) : ?>
                            <span style="color: var(--success-color);">✓ Aprobado</span>
                        <?php else : ?>
                            <span style="color: var(--warning-color);">⏳ Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($user['created_at']) ?></td>
                    <td>
                        <?php if ((int)$user['is_admin'] === 0 && (int)$user['id'] !== $session->getUserId()) : ?>
                            <?php if ($userRole === 'student') : ?>
                                <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;"
                                      class="promote-teacher-form">
                                    <input type="hidden" name="admin_action" value="promote_to_teacher">
                                    <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                    <button type="submit" class="btn-primary btn-small" style="background-color: #3b82f6; padding: 0.25rem 0.5rem; font-size: 0.875rem;">Profesor</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none; margin-left: 0.25rem;"
                                  class="delete-user-form" data-username="<?= e($user['username']) ?>">
                                <input type="hidden" name="admin_action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                <input type="hidden" name="confirm" value="1">
                                <button type="submit" class="btn-danger btn-small">Eliminar</button>
                            </form>
                        <?php else : ?>
                            <span style="color: var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <p style="margin-top: 1rem; color: var(--text-muted); font-size: 0.9rem;">
        <strong>Nota:</strong> No puedes eliminar administradores ni tu propia cuenta.
    </p>
    <?php else : ?>
        <p style="color: var(--text-muted);">La gestión de usuarios solo está disponible para administradores.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Solicitudes Aprobadas Recientes (<?= count($approvedRequests) ?>)</h3>
    <?php if (empty($approvedRequests)) : ?>
        <p class="text-center">No hay solicitudes aprobadas aún.</p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Tipo</th>
                    <th>Fecha solicitud</th>
                    <th>Fecha aprobación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($approvedRequests, 0, 10) as $request) : ?>
                    <tr>
                        <td><?= e($request['username']) ?></td>
                        <td><?= e($request['email'] ?: '-') ?></td>
                        <td>
                            <?php
                                $role = $request['role'] ?? 'student';
                                $roleLabel = $role === 'teacher' ? 'Profesor' : 'Alumno';
                                $roleColor = $role === 'teacher' ? 'color: #3b82f6;' : '';
                            ?>
                            <span style="<?= $roleColor ?>"><?= e($roleLabel) ?></span>
                        </td>
                        <td><?= e($request['requested_at']) ?></td>
                        <td><?= e($request['processed_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h3>Solicitudes Rechazadas Recientes (<?= count($rejectedRequests) ?>)</h3>
    <?php if (empty($rejectedRequests)) : ?>
        <p class="text-center">No hay solicitudes rechazadas.</p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Fecha solicitud</th>
                    <th>Fecha rechazo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($rejectedRequests, 0, 10) as $request) : ?>
                    <tr>
                        <td><?= e($request['username']) ?></td>
                        <td><?= e($request['email'] ?: '-') ?></td>
                        <td><?= e($request['requested_at']) ?></td>
                        <td><?= e($request['processed_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
// Handle delete user confirmation
document.querySelectorAll('.delete-user-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const username = this.getAttribute('data-username');
        const message = '¿Estás seguro de eliminar al usuario "' + username + '"?\n\nEsta acción no se puede deshacer.';
        e.preventDefault();
        confirmAction(message, 'Eliminar', 'Cancelar')
        .then(ok => {
            if (!ok) return false;
            form.submit();
        });
    });
});

// Handle promote to teacher confirmation
document.querySelectorAll('.promote-teacher-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        confirmAction('¿Promover este usuario a profesor?\n\nPodrá recibir solicitudes de registro de sus alumnos.', 'Promover', 'Cancelar')
        .then(ok => {
            if (!ok) return false;
            form.submit();
        });
    });
});

// Hook approve/reject forms to use modal confirm
document.querySelectorAll('.admin-approve-form, .admin-reject-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const isApprove = form.classList.contains('admin-approve-form');
        const message = isApprove ? '¿Aprobar esta solicitud? El usuario podrá iniciar sesión con su contraseña.' : '¿Rechazar esta solicitud?';
        confirmAction(message, isApprove ? 'Aprobar' : 'Rechazar', 'Cancelar')
        .then(ok => {
            if (!ok) return false;
            form.submit();
        });
    });
});
</script>
