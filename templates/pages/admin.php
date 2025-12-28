<?php
/**
 * Admin panel template
 * @var \Ngw\Models\RegistrationRequest $requestModel
 * @var \Ngw\Auth\SessionManager $session
 * @var \Ngw\Auth\Auth $auth
 */

if (!$session->isAdmin() && !$session->isTeacher()) {
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
    
    <?php 
    // Get users based on role
    if ($isAdmin) {
        $managedUsers = $auth->getAllUsers();
    } else if ($isTeacher) {
        $managedUsers = $auth->getStudentsByTeacher($session->getUserId());
    } else {
        $managedUsers = [];
    }
    ?>
    
    <?php if (!empty($managedUsers)) : ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Email</th>
                <th>Tipo</th>
                <?php if ($isAdmin) : ?><th>Admin</th><?php endif; ?>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($managedUsers as $user) : ?>
                <tr>
                    <td><?= e($user['id']) ?></td>
                    <td><strong><?= e($user['username']) ?></strong></td>
                    <td><?= e($user['email'] ?: '-') ?></td>
                    <td>
                        <?php
                            $userIsAdmin = (int)($user['is_admin'] ?? 0) === 1;
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
                    <?php if ($isAdmin) : ?>
                        <td><?= $userIsAdmin ? '✓ Admin' : '-' ?></td>
                    <?php endif; ?>
                    <td>
                        <?php 
                        // Can reset password if:
                        // - Admin can reset anyone except other admins (and not self)
                        // - Teacher can reset their students only
                        $canReset = false;
                        if ($isAdmin && !$userIsAdmin) {
                            $canReset = true;
                        } else if ($isTeacher && $userRole === 'student') {
                            $canReset = true;
                        }
                        ?>
                        
                        <?php if ($canReset) : ?>
                            <button type="button" class="btn-primary btn-small" 
                                    onclick="openResetPasswordModal(<?= e($user['id']) ?>, '<?= e(addslashes($user['username'])) ?>')">
                                Resetear Contraseña
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($isAdmin && !$userIsAdmin && (int)$user['id'] !== $session->getUserId()) : ?>
                            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;"
                                  class="delete-user-form" data-username="<?= e($user['username']) ?>">
                                <input type="hidden" name="admin_action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                <input type="hidden" name="confirm" value="1">
                                <button type="submit" class="btn-danger btn-small">Eliminar</button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if (!$canReset && (!$isAdmin || $userIsAdmin || (int)$user['id'] === $session->getUserId())) : ?>
                            <span style="color: var(--text-muted);">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <p style="margin-top: 1rem; color: var(--text-muted); font-size: 0.9rem;">
        <?php if ($isAdmin) : ?>
            <strong>Nota:</strong> No puedes eliminar administradores ni tu propia cuenta. Puedes resetear contraseñas de usuarios no admin.
        <?php else : ?>
            <strong>Nota:</strong> Puedes resetear contraseñas de tus alumnos. El alumno deberá cambiar la contraseña la primera vez que entre.
        <?php endif; ?>
    </p>
    <?php else : ?>
        <p class="text-center">No hay usuarios para gestionar.</p>
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

<!-- Modal para resetear contraseña -->
<div id="reset-password-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 400px; width: 90%;">
        <h3 style="margin-top: 0;">Resetear Contraseña</h3>
        <p>Usuario: <strong id="reset-username"></strong></p>
        <form id="reset-password-form">
            <input type="hidden" id="reset-user-id" name="user_id">
            <div class="form-group">
                <label for="reset-new-password">Nueva contraseña temporal</label>
                <input type="text" id="reset-new-password" name="new_password" required minlength="4" placeholder="Escribe la nueva contraseña">
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted);">
                El usuario deberá cambiar esta contraseña la primera vez que entre.
            </p>
            <div id="reset-error" style="color: var(--color-danger); margin-bottom: 1rem; display: none;"></div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn-success">Resetear Contraseña</button>
                <button type="button" class="btn-secondary" onclick="closeResetPasswordModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResetPasswordModal(userId, username) {
    document.getElementById('reset-user-id').value = userId;
    document.getElementById('reset-username').textContent = username;
    document.getElementById('reset-new-password').value = '';
    document.getElementById('reset-error').style.display = 'none';
    document.getElementById('reset-password-modal').style.display = 'flex';
}

function closeResetPasswordModal() {
    document.getElementById('reset-password-modal').style.display = 'none';
}

document.getElementById('reset-password-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const userId = document.getElementById('reset-user-id').value;
    const newPassword = document.getElementById('reset-new-password').value;
    const errorDiv = document.getElementById('reset-error');
    
    if (newPassword.length < 4) {
        errorDiv.textContent = 'La contraseña debe tener al menos 4 caracteres';
        errorDiv.style.display = 'block';
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reset_password');
    formData.append('user_id', userId);
    formData.append('new_password', newPassword);
    
    fetch('index.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                closeResetPasswordModal();
                if (typeof showNotification === 'function') {
                    showNotification(data.message || 'Contraseña reseteada correctamente', 'success');
                } else {
                    alert(data.message || 'Contraseña reseteada correctamente');
                }
            } else {
                errorDiv.textContent = data.error || 'Error al resetear la contraseña';
                errorDiv.style.display = 'block';
            }
        })
        .catch(err => {
            errorDiv.textContent = 'Error de conexión';
            errorDiv.style.display = 'block';
        });
});

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

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeResetPasswordModal();
    }
});

// Close modal on backdrop click
document.getElementById('reset-password-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeResetPasswordModal();
    }
});
</script>
