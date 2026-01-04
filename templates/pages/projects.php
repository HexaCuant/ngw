<?php
/**
 * Projects page template
 * @var \Ngw\Models\Project $projectModel
 * @var \Ngw\Models\ProjectGroup $projectGroupModel
 * @var \Ngw\Auth\SessionManager $session
 */

$userId = $session->getUserId();
$projects = $projectModel->getUserProjects($userId);
$groups = $projectGroupModel->getUserGroups($userId);
$ungroupedCount = $projectGroupModel->countUngroupedProjects($userId);
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
    <!-- Groups management panel -->
    <div class="project-groups-panel" style="margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <label for="group-filter" style="font-weight: 500;">Filtrar por grupo:</label>
            <select id="group-filter" onchange="filterProjectsByGroup()" style="min-width: 180px;">
                <option value="-1">Todos los proyectos (<?= count($projects) ?>)</option>
                <option value="null">Sin grupo (<?= $ungroupedCount ?>)</option>
                <?php foreach ($groups as $group) : ?>
                    <option value="<?= e($group['id']) ?>" data-color="<?= e($group['color']) ?>">
                        <?= e($group['name']) ?> (<?= e($group['project_count']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="showCreateGroupModal()" class="btn-secondary btn-small" title="Crear nuevo grupo">
                + Nuevo Grupo
            </button>
            <button type="button" onclick="showManageGroupsModal()" class="btn-secondary btn-small" title="Gestionar grupos">
                ⚙️ Gestionar
            </button>
        </div>
    </div>

    <h3>Mis Proyectos</h3>
    <div id="projects-list">
    <table>
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>Nombre</th>
                <th style="width: 120px;">Grupo</th>
                <th style="width: 220px;">Acciones</th>
            </tr>
        </thead>
        <tbody id="projects-tbody">
            <?php if (empty($projects)) : ?>
                <tr id="no-projects-row">
                    <td colspan="4" class="text-center">No tienes proyectos creados</td>
                </tr>
            <?php else : ?>
                <?php foreach ($projects as $project) : ?>
                    <tr data-project-id="<?= e($project['id']) ?>" data-group-id="<?= e($project['group_id'] ?? '') ?>">
                        <td><?= e($project['id']) ?></td>
                        <td><?= e($project['name']) ?></td>
                        <td>
                            <?php if (!empty($project['group_name'])) : ?>
                                <span class="group-badge" style="background: <?= e($project['group_color']) ?>20; color: <?= e($project['group_color']) ?>; border: 1px solid <?= e($project['group_color']) ?>40; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.85rem;">
                                    <?= e($project['group_name']) ?>
                                </span>
                            <?php else : ?>
                                <span style="color: var(--color-text-muted); font-size: 0.85rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" onclick="openProject(<?= e($project['id']) ?>)" class="btn-primary btn-small">Abrir</button>
                            <button type="button" onclick="showMoveProjectModal(<?= e($project['id']) ?>, '<?= e(addslashes($project['name'])) ?>')" class="btn-secondary btn-small" title="Mover a grupo">📁</button>
                            <button type="button" onclick="deleteProject(<?= e($project['id']) ?>, '<?= e(addslashes($project['name'])) ?>')" class="btn-danger btn-small">Borrar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
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
        
        <?php if (!empty($groups)) : ?>
        <div class="form-group">
            <label for="project_group">Grupo (opcional)</label>
            <select id="project_group" name="group_id">
                <option value="">Sin grupo</option>
                <?php foreach ($groups as $group) : ?>
                    <option value="<?= e($group['id']) ?>"><?= e($group['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <button type="submit" class="btn-success">Crear Proyecto</button>
    </form>
</div>

<!-- Create Group Modal -->
<div id="create-group-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <h3>Crear Nuevo Grupo</h3>
        <form id="create-group-form">
            <div class="form-group">
                <label for="new_group_name">Nombre del grupo</label>
                <input type="text" id="new_group_name" name="group_name" required placeholder="Ej: Prácticas Laboratorio">
            </div>
            <div class="form-group">
                <label>Color</label>
                <div id="color-picker" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php 
                    $colors = \Ngw\Models\ProjectGroup::getAvailableColors();
                    $first = true;
                    foreach ($colors as $hex => $name) : ?>
                        <label style="cursor: pointer;" title="<?= e($name) ?>">
                            <input type="radio" name="group_color" value="<?= e($hex) ?>" <?= $first ? 'checked' : '' ?> style="display: none;">
                            <span class="color-swatch" style="display: inline-block; width: 28px; height: 28px; background: <?= e($hex) ?>; border-radius: 4px; border: 3px solid transparent;"></span>
                        </label>
                    <?php $first = false; endforeach; ?>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                <button type="button" onclick="hideCreateGroupModal()" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-success">Crear Grupo</button>
            </div>
        </form>
    </div>
</div>

<!-- Move Project Modal -->
<div id="move-project-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <h3>Mover Proyecto</h3>
        <p id="move-project-name" style="margin-bottom: 1rem;"></p>
        <form id="move-project-form">
            <input type="hidden" id="move_project_id" name="project_id">
            <div class="form-group">
                <label for="move_to_group">Mover a grupo:</label>
                <select id="move_to_group" name="group_id">
                    <option value="">Sin grupo</option>
                    <?php foreach ($groups as $group) : ?>
                        <option value="<?= e($group['id']) ?>"><?= e($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                <button type="button" onclick="hideMoveProjectModal()" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Mover</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Groups Modal -->
<div id="manage-groups-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <h3>Gestionar Grupos</h3>
        <div id="groups-list" style="max-height: 400px; overflow-y: auto;">
            <!-- Groups will be loaded here -->
        </div>
        <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
            <button type="button" onclick="hideManageGroupsModal()" class="btn-secondary">Cerrar</button>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}
.modal-content {
    background: var(--color-surface);
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: var(--shadow-lg);
    width: 90%;
}
.color-swatch {
    transition: border-color 0.2s;
}
input[type="radio"]:checked + .color-swatch {
    border-color: var(--color-text) !important;
}
.group-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-bottom: 1px solid var(--color-border);
}
.group-item:last-child {
    border-bottom: none;
}
.group-item-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    flex-shrink: 0;
}
.group-item-name {
    flex: 1;
}
.group-item-count {
    color: var(--color-text-muted);
    font-size: 0.85rem;
}
</style>

<script src="js/project-handlers.js"></script>
<script>
// Initialize create project form listener
attachCreateProjectFormListener();

// Project groups functionality
let projectGroups = <?= json_encode($groups) ?>;

function filterProjectsByGroup() {
    const select = document.getElementById('group-filter');
    const groupId = select.value;
    const rows = document.querySelectorAll('#projects-tbody tr[data-project-id]');
    
    rows.forEach(row => {
        const rowGroupId = row.getAttribute('data-group-id') || '';
        let show = false;
        
        if (groupId === '-1') {
            show = true; // Show all
        } else if (groupId === 'null') {
            show = rowGroupId === ''; // Show ungrouped
        } else {
            show = rowGroupId === groupId; // Show specific group
        }
        
        row.style.display = show ? '' : 'none';
    });
    
    // Check if all rows are hidden and show empty message
    const visibleRows = document.querySelectorAll('#projects-tbody tr[data-project-id]:not([style*="display: none"])');
    const noProjectsRow = document.getElementById('no-projects-row');
    
    if (visibleRows.length === 0) {
        if (!noProjectsRow) {
            const emptyRow = document.createElement('tr');
            emptyRow.id = 'empty-filter-row';
            emptyRow.innerHTML = '<td colspan="4" class="text-center">No hay proyectos en este grupo</td>';
            document.getElementById('projects-tbody').appendChild(emptyRow);
        }
    } else {
        const emptyFilterRow = document.getElementById('empty-filter-row');
        if (emptyFilterRow) emptyFilterRow.remove();
    }
}

function showCreateGroupModal() {
    document.getElementById('create-group-modal').style.display = 'flex';
    document.getElementById('new_group_name').focus();
}

function hideCreateGroupModal() {
    document.getElementById('create-group-modal').style.display = 'none';
    document.getElementById('create-group-form').reset();
}

function showMoveProjectModal(projectId, projectName) {
    document.getElementById('move_project_id').value = projectId;
    document.getElementById('move-project-name').textContent = 'Proyecto: ' + projectName;
    
    // Pre-select current group
    const row = document.querySelector(`tr[data-project-id="${projectId}"]`);
    const currentGroup = row ? row.getAttribute('data-group-id') : '';
    document.getElementById('move_to_group').value = currentGroup;
    
    document.getElementById('move-project-modal').style.display = 'flex';
}

function hideMoveProjectModal() {
    document.getElementById('move-project-modal').style.display = 'none';
}

function showManageGroupsModal() {
    loadGroupsList();
    document.getElementById('manage-groups-modal').style.display = 'flex';
}

function hideManageGroupsModal() {
    document.getElementById('manage-groups-modal').style.display = 'none';
}

function loadGroupsList() {
    const container = document.getElementById('groups-list');
    
    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'project_action=get_groups'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            projectGroups = data.groups;
            
            if (data.groups.length === 0) {
                container.innerHTML = '<p class="text-center" style="padding: 2rem; color: var(--color-text-muted);">No tienes grupos creados</p>';
                return;
            }
            
            let html = '';
            data.groups.forEach(g => {
                html += `
                    <div class="group-item" data-group-id="${g.id}">
                        <span class="group-item-color" style="background: ${escapeHtml(g.color)}"></span>
                        <span class="group-item-name" id="group-name-${g.id}">${escapeHtml(g.name)}</span>
                        <span class="group-item-count">${g.project_count} proyecto(s)</span>
                        <button type="button" onclick="editGroup(${g.id})" class="btn-secondary btn-small">✏️</button>
                        <button type="button" onclick="deleteGroup(${g.id}, '${escapeHtml(g.name).replace(/'/g, "\\'")}', ${g.project_count})" class="btn-danger btn-small">🗑️</button>
                    </div>
                `;
            });
            container.innerHTML = html;
        }
    });
}

function editGroup(groupId) {
    const nameSpan = document.getElementById(`group-name-${groupId}`);
    const currentName = nameSpan.textContent;
    const newName = prompt('Nuevo nombre para el grupo:', currentName);
    
    if (newName && newName.trim() !== '' && newName !== currentName) {
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `project_action=update_group&group_id=${groupId}&group_name=${encodeURIComponent(newName.trim())}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Grupo actualizado', 'success');
                loadGroupsList();
                location.reload(); // Refresh to update all selects
            } else {
                showToast(data.error || 'Error al actualizar', 'error');
            }
        });
    }
}

function deleteGroup(groupId, groupName, projectCount) {
    let msg = `¿Borrar el grupo "${groupName}"?`;
    if (projectCount > 0) {
        msg += `\n\nLos ${projectCount} proyecto(s) en este grupo quedarán sin grupo asignado.`;
    }
    
    if (confirm(msg)) {
        fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `project_action=delete_group&group_id=${groupId}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Grupo borrado', 'success');
                location.reload(); // Refresh to update everything
            } else {
                showToast(data.error || 'Error al borrar', 'error');
            }
        });
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Create group form submission
document.getElementById('create-group-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('new_group_name').value.trim();
    const color = document.querySelector('input[name="group_color"]:checked')?.value || '#6366f1';
    
    if (!name) return;
    
    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `project_action=create_group&group_name=${encodeURIComponent(name)}&color=${encodeURIComponent(color)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Grupo creado', 'success');
            hideCreateGroupModal();
            location.reload(); // Refresh to update selects
        } else {
            showToast(data.error || 'Error al crear grupo', 'error');
        }
    });
});

// Move project form submission
document.getElementById('move-project-form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const projectId = document.getElementById('move_project_id').value;
    const groupId = document.getElementById('move_to_group').value;
    
    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `project_action=move_project_to_group&project_id=${projectId}&group_id=${groupId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Proyecto movido', 'success');
            hideMoveProjectModal();
            location.reload(); // Refresh to update table
        } else {
            showToast(data.error || 'Error al mover proyecto', 'error');
        }
    });
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideCreateGroupModal();
        hideMoveProjectModal();
        hideManageGroupsModal();
    }
});

// Close modals by clicking backdrop
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
});
</script>
