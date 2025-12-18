<?php
/**
 * Generations page template
 * @var \Ngw\Models\Project $projectModel
 * @var \Ngw\Auth\SessionManager $session
 */

$activeProjectId = $session->get('active_project_id');
$activeProject = null;

if ($activeProjectId) {
    $activeProject = $projectModel->getById($activeProjectId);
}
?>

<div class="card">
    <h2>Gestión de Generaciones</h2>
    
    <?php if ($activeProject) : ?>
        <div class="alert alert-success">
            <strong>Proyecto activo:</strong> <?= e($activeProject['proname']) ?> (ID: <?= e($activeProject['id']) ?>)
        </div>
        
        <div class="alert alert-info">
            <strong>Nota:</strong> La funcionalidad de generaciones está en desarrollo. 
            Esta sección permitirá crear generaciones aleatorias, realizar cruces y analizar poblaciones.
        </div>
        
        <h3>Próximas funcionalidades:</h3>
        <ul>
            <li>Crear generación aleatoria con tamaño de población configurable</li>
            <li>Definir cruces entre individuos de generaciones previas</li>
            <li>Visualizar y exportar datos de poblaciones</li>
            <li>Análisis estadístico de fenotipos</li>
        </ul>
        
        <div style="margin-top: 2rem; padding: 1rem; background: #f0f0f0; border-radius: 0.5rem;">
            <h4>Generación aleatoria (mockup)</h4>
            <form style="background: white;">
                <div class="form-group">
                    <label>Tamaño de población</label>
                    <input type="number" placeholder="100" min="1" max="10000">
                </div>
                <div class="form-group">
                    <label>Número de generación</label>
                    <input type="number" placeholder="1" min="1">
                </div>
                <button type="button" class="btn-primary" disabled>Crear Generación (próximamente)</button>
            </form>
        </div>
        
    <?php else : ?>
        <div class="alert alert-warning">
            <strong>Atención:</strong> No hay ningún proyecto activo.
            <br>
            Ve a <a href="index.php?option=2">Proyectos</a> y abre o crea un proyecto para trabajar con generaciones.
        </div>
    <?php endif; ?>
</div>
