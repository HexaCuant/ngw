<?php
/**
 * Projects page template
 * @var \Ngw\Models\Project $projectModel
 * @var \Ngw\Auth\SessionManager $session
 */

$userId = $session->getUserId();
$projects = $projectModel->getUserProjects($userId);
$activeProjectId = $session->get('active_project_id');
$activeProject = null;
$projectCharacters = [];

// Handle project actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projectAction = $_POST['project_action'] ?? '';

    if ($projectAction === 'create' && !empty($_POST['project_name'])) {
        $name = trim($_POST['project_name']);
        try {
            $newId = $projectModel->create($name, $userId);
            redirect('index.php?option=2');
        } catch (\Exception $e) {
            $error = "Error al crear proyecto: " . $e->getMessage();
        }
    } elseif ($projectAction === 'open' && !empty($_POST['project_id'])) {
        $session->set('active_project_id', (int) $_POST['project_id']);
        redirect('index.php?option=2');
    } elseif ($projectAction === 'close') {
        $session->remove('active_project_id');
        redirect('index.php?option=2');
    } elseif ($projectAction === 'delete' && !empty($_POST['project_id']) && isset($_POST['confirm'])) {
        $projectId = (int) $_POST['project_id'];
        if ($projectModel->isOwner($projectId, $userId)) {
            $projectModel->delete($projectId);
            if ($activeProjectId === $projectId) {
                $session->remove('active_project_id');
            }
        }
        redirect('index.php?option=2');
    }
}

if ($activeProjectId) {
    $activeProject = $projectModel->getById($activeProjectId);
    $projectCharacters = $projectModel->getCharacters($activeProjectId);
}
?>

<div class="card">
    <h2>Gestión de Proyectos</h2>
    
    <?php if ($activeProject) : ?>
        <!-- Active project details -->
        <div class="alert alert-info">
            <strong>Proyecto activo:</strong> <?= e($activeProject['name']) ?> (ID: <?= e($activeProject['id']) ?>)
            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                <input type="hidden" name="project_action" value="close">
                <button type="submit" class="btn-secondary btn-small" style="margin-left: 1rem;">Cerrar Proyecto</button>
            </form>
        </div>
        
        <h3>Caracteres del Proyecto</h3>
        <?php if (!empty($projectCharacters)) : ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Ambiente</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projectCharacters as $char) : ?>
                        <tr>
                            <td><?= e($char['character_id']) ?></td>
                            <td><?= e($char['name']) ?></td>
                            <td><?= e($char['environment']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p class="text-center">No hay caracteres asignados a este proyecto.</p>
            <p class="text-center">Ve a la sección <a href="index.php?option=1">Caracteres</a> para añadir caracteres al proyecto.</p>
        <?php endif; ?>
    <?php endif; ?>
    
    <h3>Mis Proyectos</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($projects)) : ?>
                <tr>
                    <td colspan="3" class="text-center">No tienes proyectos creados</td>
                </tr>
            <?php else : ?>
                <?php foreach ($projects as $project) : ?>
                    <tr>
                        <td><?= e($project['id']) ?></td>
                        <td><?= e($project['name']) ?></td>
                        <td>
                            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                                <input type="hidden" name="project_action" value="open">
                                <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                                <button type="submit" class="btn-primary btn-small">Abrir</button>
                            </form>
                            
                            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" 
                                  class="delete-project-form" data-projectname="<?= e($project['name']) ?>">
                                <input type="hidden" name="project_action" value="delete">
                                <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                                <input type="hidden" name="confirm" value="1">
                                <button type="submit" class="btn-danger btn-small">Borrar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Crear Nuevo Proyecto</h3>
    <form method="post">
        <input type="hidden" name="project_action" value="create">
        
        <div class="form-group">
            <label for="project_name">Nombre del Proyecto</label>
            <input type="text" id="project_name" name="project_name" required 
                   placeholder="Ej: Simulación de población 2025">
        </div>
        
        <button type="submit" class="btn-success">Crear Proyecto</button>
    </form>
</div>

<script>
// Handle delete project confirmation
document.querySelectorAll('.delete-project-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const projectName = this.getAttribute('data-projectname');
        const message = '¿Estás seguro de eliminar el proyecto "' + projectName + '"?\n\nEsta acción no se puede deshacer y eliminará todos los caracteres asociados al proyecto.';
        
        if (!confirm(message)) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
