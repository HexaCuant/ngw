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
        $password = $_POST['temp_password'] ?? '';
        
        if (empty($password)) {
            $error = "Debes proporcionar una contraseña temporal";
        } else {
            try {
                $requestModel->approve($requestId, $session->getUserId(), $password);
                $success = "Usuario aprobado correctamente. Contraseña temporal: " . htmlspecialchars($password);
            } catch (\Exception $e) {
                $error = "Error al aprobar: " . $e->getMessage();
            }
        }
        // Refresh to update list
        if (!isset($error)) {
            redirect('index.php?option=4');
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
    }
}

$pendingRequests = $requestModel->getPending();
$approvedRequests = $requestModel->getAll('approved');
$rejectedRequests = $requestModel->getAll('rejected');
?>

<div class="card">
    <h2>Panel de Administración</h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    
    <h3>Solicitudes Pendientes (<?= count($pendingRequests) ?>)</h3>
    
    <?php if (empty($pendingRequests)): ?>
        <p class="text-center">No hay solicitudes pendientes.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Motivo</th>
                    <th>Fecha solicitud</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingRequests as $request): ?>
                    <tr>
                        <td><strong><?= e($request['username']) ?></strong></td>
                        <td><?= e($request['email'] ?: '-') ?></td>
                        <td><?= e($request['reason'] ?: '-') ?></td>
                        <td><?= e($request['requested_at']) ?></td>
                        <td>
                            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;"
                                  onsubmit="return promptPassword(this, <?= $request['id'] ?>);">
                                <input type="hidden" name="admin_action" value="approve">
                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                <input type="hidden" name="temp_password" id="pass_<?= $request['id'] ?>">
                                <button type="submit" class="btn-success btn-small">Aprobar</button>
                            </form>
                            
                            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;"
                                  onsubmit="return confirm('¿Rechazar esta solicitud?');">
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
    <h3>Solicitudes Aprobadas Recientes (<?= count($approvedRequests) ?>)</h3>
    <?php if (empty($approvedRequests)): ?>
        <p class="text-center">No hay solicitudes aprobadas aún.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Email</th>
                    <th>Fecha solicitud</th>
                    <th>Fecha aprobación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($approvedRequests, 0, 10) as $request): ?>
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

<div class="card">
    <h3>Solicitudes Rechazadas Recientes (<?= count($rejectedRequests) ?>)</h3>
    <?php if (empty($rejectedRequests)): ?>
        <p class="text-center">No hay solicitudes rechazadas.</p>
    <?php else: ?>
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
                <?php foreach (array_slice($rejectedRequests, 0, 10) as $request): ?>
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
function promptPassword(form, requestId) {
    const password = prompt('Ingresa una contraseña temporal para el nuevo usuario:');
    if (!password || password.length < 6) {
        alert('La contraseña debe tener al menos 6 caracteres');
        return false;
    }
    document.getElementById('pass_' + requestId).value = password;
    return true;
}
</script>
