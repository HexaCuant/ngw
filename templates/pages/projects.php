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

if ($activeProjectId) {
    $activeProject = $projectModel->getById($activeProjectId);
    $projectCharacters = $projectModel->getCharacters($activeProjectId);
}
?>

<div class="card">
    <h2>Gestión de Proyectos</h2>
    
    <?php if ($activeProject) : ?>
        <!-- Active project details -->
        <div id="active-project-details">
            <div class="alert alert-info">
                <strong>Proyecto activo:</strong> <?= e($activeProject['name']) ?> (ID: <?= e($activeProject['id']) ?>)
                <button type="button" onclick="closeProject()" class="btn-secondary btn-small" style="margin-left: 1rem;">Cerrar Proyecto</button>
            </div>
            
            <h3>Caracteres del Proyecto</h3>
            <?php if (!empty($projectCharacters)) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Ambiente</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projectCharacters as $char) : ?>
                            <tr id="project-char-<?= e($char['character_id']) ?>">
                                <td><?= e($char['character_id']) ?></td>
                                <td><?= e($char['name']) ?></td>
                                <td>
                                    <input type="number" 
                                           id="env-<?= e($char['character_id']) ?>" 
                                           value="<?= e($char['environment']) ?>" 
                                           step="1"
                                           style="width: 100px;"
                                           oninput="markEnvironmentModified(<?= e($char['character_id']) ?>)">
                                </td>
                                <td>
                                    <button type="button" 
                                            onclick="updateEnvironment(<?= e($char['character_id']) ?>)" 
                                            class="btn-primary btn-small">Actualizar</button>
                                    <button type="button" 
                                            onclick="openAlleleFrequencies(<?= e($char['character_id']) ?>, '<?= e(addslashes($char['name'])) ?>')" 
                                            class="btn-secondary btn-small">Frecuencias</button>
                                    <button type="button" 
                                            onclick="removeCharacterFromProject(<?= e($char['character_id']) ?>, '<?= e(addslashes($char['name'])) ?>')" 
                                            class="btn-danger btn-small">Borrar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="text-center">No hay caracteres asignados a este proyecto.</p>
                <p class="text-center">Ve a la sección <a href="index.php?option=1">Caracteres</a> para añadir caracteres al proyecto.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$activeProject) : ?>
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
                            <button type="button" onclick="openProject(<?= e($project['id']) ?>)" class="btn-primary btn-small">Abrir</button>
                            <button type="button" onclick="deleteProject(<?= e($project['id']) ?>, '<?= e(addslashes($project['name'])) ?>')" class="btn-danger btn-small">Borrar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if (!$activeProject) : ?>
<div class="card">
    <h3>Crear Nuevo Proyecto</h3>
    <form id="create-project-form">
        <div class="form-group">
            <label for="project_name">Nombre del Proyecto</label>
            <input type="text" id="project_name" name="project_name" required 
                   placeholder="Ej: Simulación de población 2025">
        </div>
        
        <button type="submit" class="btn-success">Crear Proyecto</button>
    </form>
</div>
<?php endif; ?>

<script src="js/project-handlers.js"></script>
<script>
// Initialize create project form listener
attachCreateProjectFormListener();
</script>
