<?php
/**
 * Generations page template
 * @var \Ngw\Models\Project $projectModel
 * @var \Ngw\Auth\SessionManager $session
 */

$activeProjectId = $session->get('active_project_id');
$activeProject = null;
$generations = [];

if ($activeProjectId) {
    $activeProject = $projectModel->getById($activeProjectId);
    
    // Load generation model
    require_once __DIR__ . '/../../src/Models/Generation.php';
    $generationModel = new \Ngw\Models\Generation($db);
    $generations = $generationModel->getProjectGenerations($activeProjectId);
}
?>

<div class="card">
    <h2>Gestión de Generaciones</h2>
    
    <?php if ($activeProject) : ?>
        <div class="alert alert-success">
            <strong>Proyecto activo:</strong> <?= e($activeProject['name']) ?> (ID: <?= e($activeProject['id']) ?>)
        </div>
        
        <div class="generations-layout">
            <!-- Panel izquierdo: controles y lista -->
            <div class="left-panel">
                <div class="card">
                    <h3>Crear Generación Aleatoria</h3>
                    <form id="formNewRandomGeneration" onsubmit="return false;">
                        <div class="form-group">
                            <label for="population_size">Tamaño de la población:</label>
                            <input type="number" id="population_size" name="population_size" min="1" value="10" required>
                        </div>
                        <button type="submit" onclick="createRandomGeneration()">Crear Generación</button>
                    </form>
                </div>

                <?php if (!empty($generations)) : ?>
                    <div class="card">
                        <div class="generations-list-header">
                            <h3>Generaciones Existentes</h3>
                            <button onclick="toggleGenerationsList()" id="toggleListBtn">Ocultar</button>
                        </div>
                        <div id="generationsList">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nº</th>
                                        <th>Tipo</th>
                                        <th>Pob.</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($generations as $gen) : ?>
                                        <tr id="gen-row-<?= e($gen['generation_number']) ?>">
                                            <td><?= e($gen['generation_number']) ?></td>
                                            <td><?= e($gen['type']) ?></td>
                                            <td><?= e($gen['population_size']) ?></td>
                                            <td class="actions-cell">
                                                <button onclick="viewGeneration(<?= e($gen['generation_number']) ?>)" title="Abrir">👁️</button>
                                                <button onclick="deleteGeneration(<?= e($gen['generation_number']) ?>)" title="Borrar" class="btn-danger">🗑️</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Panel derecho: visualización de generación -->
            <div class="right-panel">
                <div id="generationViewer" class="card" style="display: none;">
                    <div class="generation-header">
                        <h3>Generación <span id="genNumber"></span></h3>
                        <button onclick="closeGenerationViewer()" class="btn-close">✕</button>
                    </div>
                    <p><strong>Población:</strong> <span id="genPopulation"></span> individuos</p>
                    <p><strong>Tipo:</strong> <span id="genType"></span></p>
                    <p><strong>Fecha:</strong> <span id="genDate"></span></p>
                    <div id="individualsTable"></div>
                </div>
                
                <div id="emptyViewer" class="card empty-state">
                    <p>📊 Selecciona una generación para ver sus detalles</p>
                </div>
            </div>
        </div>
        
    <?php else : ?>
        <div class="alert alert-warning">
            <strong>Atención:</strong> No hay ningún proyecto activo.
            <br>
            Ve a <a href="index.php?option=2">Proyectos</a> y abre o crea un proyecto para trabajar con generaciones.
        </div>
    <?php endif; ?>
</div>

<style>
.generations-layout {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 20px;
    margin-top: 20px;
}

.left-panel, .right-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.generations-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.generations-list-header h3 {
    margin: 0;
}

.generations-list-header button {
    padding: 5px 10px;
    font-size: 0.9em;
}

#generationsList table {
    width: 100%;
}

#generationsList th:nth-child(1),
#generationsList td:nth-child(1) {
    width: 15%;
}

#generationsList th:nth-child(2),
#generationsList td:nth-child(2) {
    width: 20%;
}

#generationsList th:nth-child(3),
#generationsList td:nth-child(3) {
    width: 15%;
}

#generationsList th:nth-child(4),
#generationsList td:nth-child(4) {
    width: 50%;
}

.actions-cell {
    display: flex;
    gap: 5px;
}

.actions-cell button {
    padding: 5px 10px;
    font-size: 1em;
    cursor: pointer;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

.generation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.generation-header h3 {
    margin: 0;
}

.btn-close {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 5px 15px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 1.2em;
}

.btn-close:hover {
    background-color: #5a6268;
}

.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
    text-align: center;
    color: #6c757d;
    font-size: 1.1em;
}

.right-panel .card {
    height: fit-content;
}

/* Toast styles */
.toast-success {
    background-color: #28a745;
}
.toast-error {
    background-color: #dc3545;
}

#individualsTable table {
    margin-top: 15px;
}

@media (max-width: 1024px) {
    .generations-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
let currentGeneration = null;

function createRandomGeneration() {
    const form = document.getElementById('formNewRandomGeneration');
    const formData = new FormData(form);
    formData.append('project_action', 'create_random_generation');
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Add new generation to list (if list exists)
            const tbody = document.querySelector('#generationsList tbody');
            const genNum = data.generation_number;
            const pop = data.population_size || '';
            const type = data.type || 'random';

            if (tbody) {
                const rowHtml = `\n                    <tr id="gen-row-${genNum}">\n                        <td>${genNum}</td>\n                        <td>${escapeHtml(type)}</td>\n                        <td>${escapeHtml(pop)}</td>\n                        <td class="actions-cell">\n                            <button onclick="viewGeneration(${genNum})" title="Abrir">👁️</button>\n                            <button onclick="deleteGeneration(${genNum})" title="Borrar" class="btn-danger">🗑️</button>\n                        </td>\n                    </tr>`;
                tbody.insertAdjacentHTML('afterbegin', rowHtml);
            } else {
                // Fallback: reload if we cannot update list
                window.location.reload();
                return;
            }

            // Open the newly created generation in the viewer using returned data
            renderGenerationData({
                generation_number: data.generation_number,
                population_size: data.population_size,
                type: data.type,
                created_at: data.created_at,
                individuals: data.individuals
            });
            // Show success toast
            showToast('Generación ' + data.generation_number + ' creada con éxito', 'success');
        } else {
            showToast('Error: ' + (data.error || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al crear la generación: ' + error.message, 'error');
    });
}

function viewGeneration(generationNumber) {
    const formData = new FormData();
    formData.append('project_action', 'get_generation_details');
    formData.append('generation_number', generationNumber);
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            renderGenerationData(data);
        } else {
            showToast('Error: ' + (data.error || 'Error al cargar la generación'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error al cargar la generación: ' + error.message, 'error');
    });
}

function closeGenerationViewer() {
    currentGeneration = null;
    document.getElementById('generationViewer').style.display = 'none';
    document.getElementById('emptyViewer').style.display = 'flex';
}

function deleteGeneration(generationNumber) {
    confirmAction('¿Estás seguro de que quieres borrar la generación ' + generationNumber + '?', 'Borrar', 'Cancelar')
    .then(ok => {
        if (!ok) return;

        const formData = new FormData();
        formData.append('project_action', 'delete_generation');
        formData.append('generation_number', generationNumber);
        
        fetch('index.php?option=2', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showToast('Generación ' + generationNumber + ' borrada con éxito', 'success');
                
                // Remove row from table
                const row = document.getElementById('gen-row-' + generationNumber);
                if (row) {
                    row.remove();
                }
                
                // Close viewer if this generation was open
                if (currentGeneration === generationNumber) {
                    closeGenerationViewer();
                }
            } else {
                showToast('Error: ' + (data.error || 'Error al borrar la generación'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error al borrar la generación: ' + error.message, 'error');
        });
    });
}

function toggleGenerationsList() {
    const list = document.getElementById('generationsList');
    const btn = document.getElementById('toggleListBtn');
    
    if (list.style.display === 'none') {
        list.style.display = 'block';
        btn.textContent = 'Ocultar';
    } else {
        list.style.display = 'none';
        btn.textContent = 'Mostrar';
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Render generation data (used by view and create)
function renderGenerationData(data) {
    currentGeneration = data.generation_number;

    // Update viewer content
    document.getElementById('genNumber').textContent = data.generation_number;
    document.getElementById('genPopulation').textContent = data.population_size || '';
    document.getElementById('genType').textContent = data.type || '';
    document.getElementById('genDate').textContent = data.created_at || '';

    // Build individuals table
    let tableHtml = '<table><thead><tr><th>ID Individuo</th>';
    const firstIndividual = data.individuals && data.individuals[0];
    if (firstIndividual && firstIndividual.phenotypes) {
        for (const charName in firstIndividual.phenotypes) {
            tableHtml += '<th>' + escapeHtml(charName) + '</th>';
        }
    }
    tableHtml += '</tr></thead><tbody>';

    for (const item of data.individuals || []) {
        tableHtml += '<tr><td>' + escapeHtml(item.id) + '</td>';
        for (const value of Object.values(item.phenotypes || {})) {
            tableHtml += '<td>' + Number(value).toFixed(4) + '</td>';
        }
        tableHtml += '</tr>';
    }

    tableHtml += '</tbody></table>';
    document.getElementById('individualsTable').innerHTML = tableHtml;

    // Show viewer, hide empty state
    document.getElementById('emptyViewer').style.display = 'none';
    document.getElementById('generationViewer').style.display = 'block';
}

// Show a transient toast message (type: 'success'|'error')
function showToast(message, type = 'success', duration = 3500) {
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.classList.remove('toast-success', 'toast-error');
    el.classList.add(type === 'success' ? 'toast-success' : 'toast-error');
    el.style.display = 'block';

    clearTimeout(window._toastTimeout);
    window._toastTimeout = setTimeout(() => {
        el.style.display = 'none';
    }, duration);
}
</script>
