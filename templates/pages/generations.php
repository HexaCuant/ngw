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
    $nextGenerationNumber = $generationModel->getNextGenerationNumber($activeProjectId);
}
?>

<div class="card">
    <h2>Gestión de Generaciones</h2>
    
    <?php if ($activeProject) : ?>
        <div class="alert alert-success">
            <strong>Proyecto activo:</strong> <?= e($activeProject['name']) ?> (ID: <?= e($activeProject['id']) ?>)
        </div>
        <!-- Parent selection will be done inline in the generation viewer (checkbox column) -->
        
        <div class="generations-layout">
            <!-- Panel izquierdo: controles y lista -->
            <div class="left-panel">
                <div class="card">
                    <h3>Crear Generación Aleatoria</h3>
                    <form id="formNewRandomGeneration" onsubmit="return false;">
                        <div class="form-group">
                            <label for="population_size">Tamaño de la población:</label>
                            <input type="number" id="population_size" name="population_size" min="1" required>
                        </div>
                        <button type="submit" onclick="createRandomGeneration()">Crear Generación</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Crear Generación por Cruce</h3>
                    <form id="formNewCrossGeneration" onsubmit="return false;">
                        <div class="form-group">
                            <label for="cross_target_gen">Número de generación (target):</label>
                            <input type="number" id="cross_target_gen" name="cross_target_gen" min="1" value="<?= e($nextGenerationNumber) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="cross_parent_gen">Generación parentales (fuente):</label>
                            <select id="cross_parent_gen" name="cross_parent_gen">
                                <?php foreach ($generations as $g) : ?>
                                    <option value="<?= e($g['generation_number']) ?>"><?= e($g['generation_number']) ?> - <?= e($g['type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cross_population">Tamaño población:</label>
                            <input type="number" id="cross_population" name="cross_population" min="1" required>
                        </div>
                        <div class="form-row gap-1">
                            <button type="button" id="btnOpenParentSelector" onclick="openParentSelector(Number(document.getElementById('cross_parent_gen').value), Number(document.getElementById('cross_target_gen').value))" class="btn-primary">Seleccionar parentales</button>
                            <button type="button" onclick="createCrossGeneration()" class="btn-success">Crear por Cruce</button>
                            <button type="button" id="btnToggleParentals" onclick="toggleParentals()" class="btn-secondary">Mostrar parentales</button>
                        </div>
                        <div id="parentalsList" style="margin-top:10px; position:relative; min-height:40px;">
                            <div id="parentalsSpinner" style="display:none; position:absolute; right:10px; top:10px;">⏳ Cargando...</div>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h3>Crear Múltiples Cruces</h3>
                    <form id="formMultipleCrosses" onsubmit="return false;">
                        <div class="form-group">
                            <label for="multi_source_gen">Generación parentales (fuente):</label>
                            <select id="multi_source_gen" name="multi_source_gen">
                                <?php foreach ($generations as $g) : ?>
                                    <option value="<?= e($g['generation_number']) ?>"><?= e($g['generation_number']) ?> - <?= e($g['type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1">
                                <label for="multi_num_indiv">Num. individuos por cruce:</label>
                                <input type="number" id="multi_num_indiv" value="2" min="1">
                            </div>
                            <div class="form-group" style="flex:1">
                                <label for="multi_num_crosses">Número de cruces:</label>
                                <input type="number" id="multi_num_crosses" value="3" min="1">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex:1">
                                <label for="multi_population">Tamaño población (cada cruce):</label>
                                <input type="number" id="multi_population" min="1">
                            </div>
                            <div class="form-group" style="flex:1">
                                <label>Tipo de cruce:</label>
                                <div class="form-row">
                                    <label><input type="radio" name="multi_cross_type" value="associative" checked> Asociativo</label>
                                    <label style="margin-left:10px;"><input type="radio" name="multi_cross_type" value="random"> Aleatorio</label>
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <button type="button" onclick="createMultipleCrosses()" class="btn-primary">Crear múltiples cruces</button>
                            <div id="multiCrossSpinner" style="display:none;">⏳ Creando...</div>
                        </div>
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
                    <div id="downloadLinks" style="margin-top:10px;">
                        <strong>Descargar datos:</strong>
                        <a id="linkCsvDot" href="#" onclick="downloadGenerationCSV('dot')" class="btn-secondary" target="_blank" rel="noopener">CSV ; (punto decimal)</a>
                        <a id="linkCsvComma" href="#" onclick="downloadGenerationCSV('comma')" class="btn-secondary" target="_blank" rel="noopener">CSV ; (coma decimal)</a>
                    </div>
                        <div id="parentSelectionControls" class="parent-selection-controls" style="display:none; margin-top:10px;">
                            <div class="psc-info">
                                <span id="parentSelectionInfo"></span>
                                <div class="psc-count">Seleccionados: <span id="parentSelectionCount">0</span></div>
                            </div>

                            <div class="psc-box">
                                <div class="psc-row">
                                    <div class="psc-inputs">
                                        <label for="truncation_percent" class="psc-label">Truncamiento (%)</label>
                                        <input type="number" id="truncation_percent" min="1" max="100" value="10" class="psc-input-number">
                                        <label for="truncation_dir" class="psc-label">Dirección</label>
                                        <select id="truncation_dir" aria-label="Dirección" class="psc-select">
                                            <option value="top">Superior</option>
                                            <option value="bottom">Inferior</option>
                                        </select>
                                        <button type="button" onclick="applyTruncationSelection()" class="btn-primary">Aplicar truncamiento</button>
                                        <button type="button" onclick="clearParentSelection()" class="btn-secondary">Limpiar</button>
                                    </div>
                                </div>
                            </div>

                            <div class="psc-actions-outside" style="margin-top:0.6rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                                <button type="button" onclick="cancelParentSelection()" class="btn-secondary">Cancelar selección</button>
                                <button type="button" onclick="addSelectedParentals()" class="btn-primary">Agregar seleccionados</button>
                            </div>
                        </div>
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

.stats-row td {
    background-color: var(--color-surface-dark) !important;
    color: var(--color-text) !important;
    font-weight: 700 !important;
    border-top: 1px solid var(--color-border-light);
}

.stats-row:hover td {
    background-color: var(--color-surface-dark) !important;
}

.parental-stats-row td {
    background-color: var(--color-bg-dark) !important;
}

.parental-stats-row:hover td {
    background-color: var(--color-bg-dark) !important;
}

@media (max-width: 1024px) {
    .generations-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
let currentGeneration = null;
// Parent selection state (declared early to avoid TDZ issues when renderGenerationData runs)
let parentSelectionMode = false;
let parentSelectionSource = null;
let parentSelectionTarget = null;
let parentSelectionExisting = new Set();
let parentalsVisible = false;

// Global error handlers to surface JS errors to the page for debugging
window.addEventListener('error', function (ev) {
    console.error('Unhandled error:', ev.error || ev.message, ev);
    const gt = document.getElementById('global-toast');
    if (gt) {
        gt.textContent = 'JS Error: ' + (ev.error && ev.error.message ? ev.error.message : ev.message || 'Unknown');
        gt.classList.add('toast-error');
        gt.style.display = 'block';
        setTimeout(() => gt.style.display = 'none', 8000);
    }
});
window.addEventListener('unhandledrejection', function (ev) {
    console.error('Unhandled rejection:', ev.reason);
    const gt = document.getElementById('global-toast');
    if (gt) {
        gt.textContent = 'Unhandled Promise Rejection: ' + (ev.reason && ev.reason.message ? ev.reason.message : String(ev.reason));
        gt.classList.add('toast-error');
        gt.style.display = 'block';
        setTimeout(function() { gt.style.display = 'none'; }, 8000);
    }
});
console.debug('generations.js initialized');
// Load generation parentals helper
// (kept inline to avoid extra HTTP requests; small helper copied from _generation_parentals.js)
// Render parentals for a cross generation: for each parent generation, show rows with id and phenotype summary
async function renderGenerationParentals(groupedParentals, parentalStats) {
    const container = document.getElementById('generationParentals');
    if (!container) return;
    if (!groupedParentals || Object.keys(groupedParentals).length === 0) {
        container.innerHTML = '';
        return;
    }

    // Show interim spinner
        container.innerHTML = '<div class="card parentals-card"><div style="padding:0.5rem">⏳ Cargando parentales...</div></div>';

    const parents = Object.keys(groupedParentals).sort((a,b)=>Number(a)-Number(b));

    // Fetch individuals for each parent generation
    const fetchPromises = parents.map(pg => {
        const form = new FormData();
        form.append('project_action', 'get_generation_details');
        form.append('generation_number', pg);
        return fetch('index.php?option=2', { method: 'POST', body: form })
            .then(r => r.json())
            .then(data => ({ pg, data }))
            .catch(err => ({ pg, error: err }));
    });

    const results = await Promise.all(fetchPromises);

    // Determine all phenotype character names across fetched generations
    const charNamesSet = new Set();
    const perPgIdMap = {}; // pg -> { id -> phenotypes }

    for (const res of results) {
        const pg = res.pg;
        const ids = groupedParentals[pg] || [];

        if (res.error || !res.data || !res.data.success) {
            // mark entries as missing
            perPgIdMap[pg] = {
                _missing: true,
                ids: ids
            };
            continue;
        }

        const individuals = res.data.individuals || [];
        const idMap = {};
        for (const ind of individuals) {
            idMap[String(ind.id)] = ind.phenotypes || {};
            for (const k of Object.keys(ind.phenotypes || {})) charNamesSet.add(k);
        }
        // include any ids that might not be present in individuals (safeguard)
        for (const id of ids) {
            if (!idMap[String(id)]) idMap[String(id)] = {};
        }
        perPgIdMap[pg] = { _missing: false, ids: ids, idMap };
    }

    const charNames = Array.from(charNamesSet);

    // Build table header with character names as columns
    let htmlHeader = '<div class="card parentals-card"><h4>Parentales</h4>';
    htmlHeader += '<table style="width:100%; margin-top:10px;"><thead><tr><th>Generación origen</th><th>ID</th>';
    for (const cname of charNames) htmlHeader += '<th>' + escapeHtml(cname) + '</th>';
    htmlHeader += '</tr></thead><tbody>';
    let html = htmlHeader;

    for (const pg of parents) {
        const entry = perPgIdMap[pg];
        const ids = groupedParentals[pg] || [];
        if (!entry || entry._missing) {
            for (const id of ids) {
                html += '<tr>';
                html += '<td>' + escapeHtml(pg) + '</td>';
                html += '<td>' + escapeHtml(id) + '</td>';
                for (let i = 0; i < charNames.length; i++) html += '<td><em>No disponible</em></td>';
                html += '</tr>';
            }
            continue;
        }

        for (const id of entry.ids) {
            const phen = entry.idMap[String(id)] || {};
            html += '<tr>';
            html += '<td>' + escapeHtml(pg) + '</td>';
            html += '<td>' + escapeHtml(id) + '</td>';
            for (const cname of charNames) {
                if (phen && cname in phen) {
                    html += '<td>' + Number(phen[cname]).toFixed(4) + '</td>';
                } else {
                    html += '<td></td>';
                }
            }
            html += '</tr>';
        }
    }

    // Add parental statistics rows if available
    if (parentalStats && Object.keys(parentalStats).length > 0) {
        html += '<tr class="stats-row parental-stats-row"><td colspan="2"><strong>Media (Parentales)</strong></td>';
        for (const cname of charNames) {
            const s = parentalStats[cname];
            html += '<td>' + (s ? s.mean.toFixed(4) : '-') + '</td>';
        }
        html += '</tr>';
        html += '<tr class="stats-row parental-stats-row"><td colspan="2"><strong>Varianza (Parentales)</strong></td>';
        for (const cname of charNames) {
            const s = parentalStats[cname];
            html += '<td>' + (s ? s.variance.toFixed(4) : '-') + '</td>';
        }
        html += '</tr>';
    }

    html += '</tbody></table></div>';
    container.innerHTML = html;
}
// Auto-refresh parentals table when the target generation input changes
const crossTargetInput = document.getElementById('cross_target_gen');
if (crossTargetInput) {
    // debounce helper
    function debounce(fn, wait) {
        let t = null;
        return function(...args) {
            if (t) clearTimeout(t);
            t = setTimeout(() => {
                t = null;
                fn.apply(this, args);
            }, wait);
        };
    }

    const onTargetInput = debounce(function () {
        if (parentalsVisible) {
            const target = Number(this.value);
            if (target && target > 0) loadParentalsForTarget(target);
        }
    }, 200);

    crossTargetInput.addEventListener('input', onTargetInput);
}
// Note: source-change auto-update removed — parent source select changes do not auto-refresh the table

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
                // Rebuild parent generation selects so the new generation is available as a source
                refreshGenerationSelects();
            } else {
                // First generation: create the generations list section dynamically
                // This prevents page reload and allows the generation to be displayed immediately
                const leftPanel = document.querySelector('.left-panel');
                if (leftPanel) {
                    const listCardHtml = `
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
                                    <tr id="gen-row-${genNum}">
                                        <td>${genNum}</td>
                                        <td>${escapeHtml(type)}</td>
                                        <td>${escapeHtml(pop)}</td>
                                        <td class="actions-cell">
                                            <button onclick="viewGeneration(${genNum})" title="Abrir">👁️</button>
                                            <button onclick="deleteGeneration(${genNum})" title="Borrar" class="btn-danger">🗑️</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>`;
                    leftPanel.insertAdjacentHTML('beforeend', listCardHtml);
                    // Rebuild parent generation selects so the new generation is available as a source
                    refreshGenerationSelects();
                } else {
                    // Fallback: reload if we cannot find the left panel
                    window.location.reload();
                    return;
                }
            }

            // Open the newly created generation in the viewer using returned data
            renderGenerationData({
                generation_number: data.generation_number,
                population_size: data.population_size,
                type: data.type,
                created_at: data.created_at,
                individuals: data.individuals,
                parentals: data.parentals || {}
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
    console.debug('viewGeneration called', generationNumber);
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
        console.debug('viewGeneration response', data);
        try {
            if (data.success) {
                renderGenerationData(data);
            } else {
                showToast('Error: ' + (data.error || 'Error al cargar la generación'), 'error');
            }
        } catch (err) {
            console.error('renderGenerationData error', err);
            showToast('Error al mostrar la generación: ' + (err && err.message ? err.message : String(err)), 'error');
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
                // Remove option from parent generation select if present
                const parentSelectOpt = document.querySelector('#cross_parent_gen option[value="' + generationNumber + '"]');
                if (parentSelectOpt) parentSelectOpt.remove();
                // Also remove from multiple-cross source select
                const multiSelectOpt = document.querySelector('#multi_source_gen option[value="' + generationNumber + '"]');
                if (multiSelectOpt) multiSelectOpt.remove();
                // Cancel parent selection mode if the deleted generation was the source
                if (typeof parentSelectionSource !== 'undefined' && Number(parentSelectionSource) === Number(generationNumber)) {
                    cancelParentSelection();
                }
                // Rebuild selects to reflect the deletion
                refreshGenerationSelects();
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

// Add a generation option to parent selection selects safely
function addGenerationOption(genNum, type) {
    try {
        console.debug('addGenerationOption called', genNum, type);
        const text = genNum + ' - ' + (type || '');
        const selIds = ['cross_parent_gen', 'multi_source_gen'];
        selIds.forEach(id => {
            const s = document.getElementById(id);
            if (!s) return;
            if (s.querySelector('option[value="' + genNum + '"]')) return;
            const opt = document.createElement('option');
            opt.value = String(genNum);
            opt.textContent = text;
            // insert as first option (newest first)
            if (s.firstElementChild) s.insertBefore(opt, s.firstElementChild);
            else s.appendChild(opt);
        });
    } catch (err) {
        console.error('addGenerationOption error', err);
    }
}

// Rebuild both parent generation selects from the current generations list (keeps order)
function refreshGenerationSelects() {
    try {
        const tbody = document.querySelector('#generationsList tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const gens = rows.map(r => {
            const tds = r.querySelectorAll('td');
            return { num: Number(tds[0].textContent.trim()), type: tds[1] ? tds[1].textContent.trim() : '' };
        });
        // sort descending by generation number
        gens.sort((a,b) => Number(b.num) - Number(a.num));

        const selIds = ['cross_parent_gen', 'multi_source_gen'];
        selIds.forEach(id => {
            const s = document.getElementById(id);
            if (!s) return;
            // preserve current selection
            const prev = s.value;
            s.innerHTML = '';
            for (const g of gens) {
                const opt = document.createElement('option');
                opt.value = String(g.num);
                opt.textContent = g.num + ' - ' + (g.type || '');
                s.appendChild(opt);
            }
            // restore selection if present
            if (prev && s.querySelector('option[value="' + prev + '"]')) s.value = prev;
        });
    } catch (err) {
        console.error('refreshGenerationSelects error', err);
    }
}

// Render generation data (used by view and create)
function renderGenerationData(data) {
    try {
        console.debug('renderGenerationData', data && data.generation_number);
        currentGeneration = data.generation_number;

        // Update viewer content
        document.getElementById('genNumber').textContent = data.generation_number;
        document.getElementById('genPopulation').textContent = data.population_size || '';
        document.getElementById('genType').textContent = data.type || '';
        document.getElementById('genDate').textContent = data.created_at || '';

        // Update CSV link hrefs so they point to the actual download URL (with generation number)
        try {
            const linkDot = document.getElementById('linkCsvDot');
            const linkComma = document.getElementById('linkCsvComma');
            if (linkDot) {
                if (data.csv_dot_url) {
                    linkDot.href = data.csv_dot_url;
                } else {
                    linkDot.href = `index.php?option=2&project_action=download_generation_csv&generation_number=${encodeURIComponent(data.generation_number)}&decimal=dot`;
                }
                linkDot.download = `generation_${data.generation_number}_decimal_dot.csv`;
            }
            if (linkComma) {
                if (data.csv_comma_url) {
                    linkComma.href = data.csv_comma_url;
                } else {
                    linkComma.href = `index.php?option=2&project_action=download_generation_csv&generation_number=${encodeURIComponent(data.generation_number)}&decimal=comma`;
                }
                linkComma.download = `generation_${data.generation_number}_decimal_comma.csv`;
            }
        } catch (err) {
            console.error('Error updating CSV link hrefs', err);
        }

    // Build individuals table
    let tableHtml = '<table><thead><tr>';
    const selectionActive = (typeof parentSelectionMode !== 'undefined') && parentSelectionMode && Number(data.generation_number) === Number(parentSelectionSource);
    if (selectionActive) {
        tableHtml += '<th>Parental</th>';
    }
    tableHtml += '<th>ID Individuo</th>';
    const firstIndividual = data.individuals && data.individuals[0];
    if (firstIndividual && firstIndividual.phenotypes) {
        for (const charName in firstIndividual.phenotypes) {
            tableHtml += '<th>' + escapeHtml(charName) + '</th>';
        }
    }
    tableHtml += '</tr></thead><tbody>';

    for (const item of data.individuals || []) {
        tableHtml += '<tr>';
        if (selectionActive) {
            const checked = parentSelectionExisting && parentSelectionExisting.has(String(item.id)) ? ' checked' : '';
            tableHtml += '<td><input type="checkbox" class="viewer-parental-checkbox" data-id="' + escapeHtml(item.id) + '"' + checked + '></td>';
        }
        tableHtml += '<td>' + escapeHtml(item.id) + '</td>';
        for (const value of Object.values(item.phenotypes || {})) {
            tableHtml += '<td>' + Number(value).toFixed(4) + '</td>';
        }
        tableHtml += '</tr>';
    }

    // Add statistics rows
    if (data.stats && Object.keys(data.stats).length > 0) {
        tableHtml += '<tr class="stats-row"><td colspan="' + (selectionActive ? 2 : 1) + '"><strong>Media</strong></td>';
        if (firstIndividual && firstIndividual.phenotypes) {
            for (const charName in firstIndividual.phenotypes) {
                const s = data.stats[charName];
                tableHtml += '<td>' + (s ? s.mean.toFixed(4) : '-') + '</td>';
            }
        }
        tableHtml += '</tr>';
        tableHtml += '<tr class="stats-row"><td colspan="' + (selectionActive ? 2 : 1) + '"><strong>Varianza</strong></td>';
        if (firstIndividual && firstIndividual.phenotypes) {
            for (const charName in firstIndividual.phenotypes) {
                const s = data.stats[charName];
                tableHtml += '<td>' + (s ? s.variance.toFixed(4) : '-') + '</td>';
            }
        }
        tableHtml += '</tr>';
    }

    tableHtml += '</tbody></table>';
    document.getElementById('individualsTable').innerHTML = tableHtml;

    // Show or hide parent selection controls
    if (selectionActive) {
        const ctrl = document.getElementById('parentSelectionControls');
        if (ctrl) {
            ctrl.style.display = 'block';
            const info = document.getElementById('parentSelectionInfo');
            if (info) info.textContent = 'Seleccionando parentales de generación ' + parentSelectionSource + ' → objetivo ' + parentSelectionTarget;
        }
    } else {
        const ctrl = document.getElementById('parentSelectionControls');
        if (ctrl) ctrl.style.display = 'none';
    }

        // Show viewer, hide empty state
        document.getElementById('emptyViewer').style.display = 'none';
        document.getElementById('generationViewer').style.display = 'block';

        // If this generation is a cross, and parentals data exists, show them above the individuals
        try {
            const genParentalsContainer = document.getElementById('generationParentals');
            if (genParentalsContainer) {
                if (data.parentals && Object.keys(data.parentals).length > 0) {
                    console.debug('Rendering parentals for generation', data.generation_number, data.parentals);
                    renderGenerationParentals(data.parentals, data.parental_stats);
                } else {
                    genParentalsContainer.innerHTML = '';
                }
            }
        } catch (err) {
            console.error('Error rendering generation parentals', err);
        }
    } catch (err) {
        console.error('Error rendering generation data', err);
        const gt = document.getElementById('global-toast');
        if (gt) {
            gt.textContent = 'Error al mostrar generación: ' + (err && err.message ? err.message : String(err));
            gt.classList.add('toast-error');
            gt.style.display = 'block';
            setTimeout(() => gt.style.display = 'none', 5000);
        } else {
            alert('Error al mostrar generación: ' + (err && err.message ? err.message : String(err)));
        }
    }
}

// Show a transient toast message (type: 'success'|'error')
function showToast(message, type, duration) {
    if (typeof type === 'undefined') type = 'success';
    if (typeof duration === 'undefined') duration = 3500;
    const el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.classList.remove('toast-success', 'toast-error');
    el.classList.add(type === 'success' ? 'toast-success' : 'toast-error');
    el.style.display = 'block';

    clearTimeout(window._toastTimeout);
    window._toastTimeout = setTimeout(function() {
        el.style.display = 'none';
    }, duration);
}

// Parent selection (inline in viewer)

function openParentSelector(parentGen, targetGen) {
    console.debug('openParentSelector called', parentGen, targetGen);
    // Validate inputs
    if (!parentGen || parentGen <= 0) {
        showToast('Generación parentales (fuente) inválida', 'error');
        return;
    }
    if (!targetGen || targetGen <= 0) {
        showToast('Generación objetivo inválida', 'error');
        return;
    }

    parentSelectionMode = true;
    parentSelectionSource = parentGen;
    parentSelectionTarget = targetGen;
    parentSelectionExisting = new Set();

    // Load existing parentals for target generation to pre-check
    const formData = new FormData();
    formData.append('project_action', 'get_parentals');
    formData.append('generation_number', targetGen);

    fetch('index.php?option=2', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        console.debug('get_parentals response for target', targetGen, data);
        if (data && data.success && Array.isArray(data.parentals)) {
            for (const p of data.parentals) {
                if (Number(p.parent_generation_number) === Number(parentGen)) {
                    parentSelectionExisting.add(String(p.individual_id));
                }
            }
        }

        // Open the source generation in the viewer; renderGenerationData will honor selection mode
        viewGeneration(parentGen);
    })
    .catch(err => {
        console.error(err);
        showToast('Error al cargar parentales existentes: ' + (err && err.message ? err.message : String(err)), 'error');
    });
}

function cancelParentSelection() {
    parentSelectionMode = false;
    parentSelectionSource = null;
    parentSelectionTarget = null;
    parentSelectionExisting = new Set();

    // Refresh current view to remove checkbox column
    if (currentGeneration) viewGeneration(currentGeneration);
}

function clearParentSelection() {
    // Uncheck all selection checkboxes in the viewer and update count
    const boxes = Array.from(document.querySelectorAll('.viewer-parental-checkbox'));
    boxes.forEach(cb => cb.checked = false);
    updateSelectionCount();
}

function applyTruncationSelection() {
    const percentEl = document.getElementById('truncation_percent');
    const dirEl = document.getElementById('truncation_dir');
    if (!percentEl || !dirEl) return;

    let pct = Number(percentEl.value) || 0;
    pct = Math.max(0, Math.min(100, pct));
    const dir = dirEl.value === 'bottom' ? 'bottom' : 'top';

    const rows = Array.from(document.querySelectorAll('#individualsTable table tbody tr'));
    const total = rows.length;
    if (total === 0) return;

    // Calculate count: round to nearest integer but at least 1 when pct>0
    let count = Math.round((pct / 100) * total);
    if (pct > 0 && count === 0) count = 1;

    // Uncheck all first
    const boxes = Array.from(document.querySelectorAll('.viewer-parental-checkbox'));
    boxes.forEach(cb => cb.checked = false);

    if (count === 0) {
        updateSelectionCount();
        return;
    }

    if (dir === 'top') {
        // top = first N rows
        for (let i = 0; i < count && i < rows.length; i++) {
            const cb = rows[i].querySelector('.viewer-parental-checkbox');
            if (cb) cb.checked = true;
        }
    } else {
        // bottom = last N rows
        for (let i = rows.length - 1; i >= Math.max(0, rows.length - count); i--) {
            const cb = rows[i].querySelector('.viewer-parental-checkbox');
            if (cb) cb.checked = true;
        }
    }

    updateSelectionCount();
}

function updateSelectionCount() {
    const count = document.querySelectorAll('.viewer-parental-checkbox:checked').length;
    const el = document.getElementById('parentSelectionCount');
    if (el) el.textContent = String(count);
}

function addSelectedParentals() {
    if (!parentSelectionMode || !parentSelectionSource || !parentSelectionTarget) {
        showToast('Modo selección no activo', 'error');
        return;
    }

    const checkboxes = Array.from(document.querySelectorAll('.viewer-parental-checkbox:checked'));
    if (checkboxes.length === 0) {
        showToast('No hay parentales seleccionados', 'error');
        return;
    }

    const ids = checkboxes.map(cb => cb.getAttribute('data-id'));

    const formData = new FormData();
    formData.append('project_action', 'add_parental');
    formData.append('generation_number', parentSelectionTarget);
    formData.append('parent_generation_number', parentSelectionSource);
    ids.forEach(id => formData.append('individual_ids[]', id));

    fetch('index.php?option=2', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Parentales agregados: ' + (data.inserted || ids.length), 'success');
            // Ensure parentals panel is visible and refresh parentals table for the target generation, then cancel selection
            parentalsVisible = true;
            const btn = document.getElementById('btnToggleParentals');
            if (btn) btn.textContent = 'Ocultar parentales';
            loadParentalsForTarget(parentSelectionTarget);
            cancelParentSelection();
        } else {
            showToast('Error: ' + (data.error || 'No se pudieron agregar parentales'), 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error al agregar parentales: ' + err.message, 'error');
    });
}

// Load parentals for a specific target generation and render them
function loadParentalsForTarget(targetGen) {
    if (!targetGen || targetGen <= 0) return;
    const form = new FormData();
    form.append('project_action', 'get_parentals');
    form.append('generation_number', targetGen);

    const spinner = document.getElementById('parentalsSpinner');
    const start = Date.now();
    if (spinner) spinner.style.display = 'block';

    fetch('index.php?option=2', { method: 'POST', body: form })
    .then(r => r.json())
    .then(data => {
        const elapsed = Date.now() - start;
        const minShow = 180; // ms
        const hideSpinner = () => { if (spinner) spinner.style.display = 'none'; };
        if (elapsed < minShow) {
            setTimeout(hideSpinner, minShow - elapsed);
        } else {
            hideSpinner();
        }

        if (!data.success) {
            showToast('Error al cargar parentales: ' + (data.error || 'Error desconocido'), 'error');
            return;
        }
        renderParentals(data.parentals || [], targetGen, data.target_exists || false);
    })
    .catch(err => {
        if (spinner) spinner.style.display = 'none';
        console.error(err);
        showToast('Error al cargar parentales: ' + err.message, 'error');
    });
}

function loadParentalsFromForm() {
    const targetGen = Number(document.getElementById('cross_target_gen').value);
    parentalsVisible = true;
    const btn = document.getElementById('btnToggleParentals');
    if (btn) btn.textContent = 'Ocultar parentales';
    // use the currently selected source to filter
    const selectedParent = Number(document.getElementById('cross_parent_gen').value) || null;
    loadParentalsForTarget(targetGen, selectedParent);
}

function toggleParentals() {
    const btn = document.getElementById('btnToggleParentals');
    const targetGen = Number(document.getElementById('cross_target_gen').value);
    if (parentalsVisible) {
        // hide
        const container = document.getElementById('parentalsList');
        if (container) container.innerHTML = '';
        parentalsVisible = false;
        if (btn) btn.textContent = 'Mostrar parentales';
    } else {
        // show
        parentalsVisible = true;
        if (btn) btn.textContent = 'Ocultar parentales';
        loadParentalsForTarget(targetGen);
    }
}

function renderParentals(parentals, targetGen, targetExists) {
    if (typeof targetExists === 'undefined') targetExists = false;
    const container = document.getElementById('parentalsList');
    if (!container) return;
    if (!parentals || parentals.length === 0) {
        const note = targetExists ? '<p class="text-center">No hay parentales agregados para la generación ' + escapeHtml(targetGen) + '. Nota: la generación objetivo ya existe, no se pueden añadir ni eliminar parentales.</p>' : '<p class="text-center">No hay parentales agregados para la generación ' + escapeHtml(targetGen) + '.</p>';
            container.innerHTML = '<div class="card parentals-card">' + note + '</div>';
        return;
    }

    // grouped: parent_generation_number => [ids]
    const grouped = {};
    for (const p of parentals) {
        const pg = p.parent_generation_number;
        grouped[pg] = grouped[pg] || [];
        grouped[pg].push(p.individual_id);
    }

    let html = '<div class="card parentals-card"><h4>Parentales agregados (target ' + escapeHtml(targetGen) + ')</h4>';
    html += '<table style="width:100%; margin-top:10px;"><thead><tr><th>Generación origen</th><th>Individuo</th>';
    if (!targetExists) {
        html += '<th>Acción</th>';
    }
    html += '</tr></thead><tbody>';
    for (const pg of Object.keys(grouped).sort((a,b)=>Number(b)-Number(a))) {
        for (const id of grouped[pg]) {
            html += '<tr>';
            html += '<td>' + escapeHtml(pg) + '</td>';
            html += '<td>' + escapeHtml(id) + '</td>';
            if (!targetExists) {
                html += '<td><button class="btn-danger btn-small" onclick="deleteParental(' + Number(targetGen) + ',' + Number(pg) + ',' + Number(id) + ')">Quitar</button></td>';
            }
            html += '</tr>';
        }
    }
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function deleteParental(targetGen, parentGen, individualId) {
    confirmAction('¿Eliminar parental ' + individualId + ' (gen ' + parentGen + ') de target ' + targetGen + '?', 'Quitar', 'Cancelar')
    .then(ok => {
        if (!ok) return;
        const form = new FormData();
        form.append('project_action', 'delete_parental');
        form.append('generation_number', targetGen);
        form.append('parent_generation_number', parentGen);
        form.append('individual_id', individualId);

        fetch('index.php?option=2', { method: 'POST', body: form })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast('Parental eliminado', 'success');
                loadParentalsForTarget(targetGen);
            } else {
                showToast('Error: ' + (data.error || 'No se pudo eliminar parental'), 'error');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('Error al eliminar parental: ' + err.message, 'error');
        });
    });
}

function createCrossGeneration() {
    const population = Number(document.getElementById('cross_population').value);
    const targetGen = Number(document.getElementById('cross_target_gen').value);

    if (!population || population <= 0) {
        showToast('Tamaño de población inválido', 'error');
        return;
    }
    if (!targetGen || targetGen <= 0) {
        showToast('Número de generación inválido', 'error');
        return;
    }

    // Check that there are parentals assigned for this target prior to creating the cross
    const checkForm = new FormData();
    checkForm.append('project_action', 'get_parentals');
    checkForm.append('generation_number', targetGen);
    fetch('index.php?option=2', { method: 'POST', body: checkForm })
    .then(r => r.json())
    .then(check => {
        if (!check.success) {
            showToast('Error al verificar parentales: ' + (check.error || 'Error desconocido'), 'error');
            return;
        }
        if (!check.parentals || check.parentals.length === 0) {
            showToast('No hay parentales asignados para la generación ' + targetGen + '. Añade parentales antes de crear el cruce.', 'error');
            return;
        }

        const form = new FormData();
        form.append('project_action', 'create_cross_generation');
        form.append('population_size', population);
        form.append('generation_number', targetGen);
        // If a source is selected and panel visible, we can refresh the parentals panel (no `data` available here)
        const parentEl = document.getElementById('cross_parent_gen');
        const selectedParent = parentEl ? Number(parentEl.value) : null;
        if (parentalsVisible) {
            // reload the parentals list for the current target (will fetch fresh data)
            loadParentalsForTarget(targetGen);
        }
        return fetch('index.php?option=2', { method: 'POST', body: form }).then(r => r.json());
    })
    .then(data => {
        console.debug('createCrossGeneration response', data);
        if (!data) return; // aborted earlier
        if (data.success) {
            // Add to list
            const tbody = document.querySelector('#generationsList tbody');
            const genNum = data.generation_number;
            const pop = data.population_size || '';
            const type = data.type || 'cross';

            if (tbody) {
                const rowHtml = `\n                    <tr id="gen-row-${genNum}">\n                        <td>${genNum}</td>\n                        <td>${escapeHtml(type)}</td>\n                        <td>${escapeHtml(pop)}</td>\n                        <td class="actions-cell">\n                            <button onclick="viewGeneration(${genNum})" title="Abrir">👁️</button>\n                            <button onclick="deleteGeneration(${genNum})" title="Borrar" class="btn-danger">🗑️</button>\n                        </td>\n                    </tr>`;
                tbody.insertAdjacentHTML('afterbegin', rowHtml);
                // Rebuild parent generation selects so the new generation is available as a source
                refreshGenerationSelects();
            } else {
                // First generation: create the generations list section dynamically
                const leftPanel = document.querySelector('.left-panel');
                if (leftPanel) {
                    const listCardHtml = `
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
                                    <tr id="gen-row-${genNum}">
                                        <td>${genNum}</td>
                                        <td>${escapeHtml(type)}</td>
                                        <td>${escapeHtml(pop)}</td>
                                        <td class="actions-cell">
                                            <button onclick="viewGeneration(${genNum})" title="Abrir">👁️</button>
                                            <button onclick="deleteGeneration(${genNum})" title="Borrar" class="btn-danger">🗑️</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>`;
                    leftPanel.insertAdjacentHTML('beforeend', listCardHtml);
                    // Rebuild parent generation selects so the new generation is available as a source
                    refreshGenerationSelects();
                } else {
                    // Fallback: reload if we cannot find the left panel
                    window.location.reload();
                    return;
                }
            }

            renderGenerationData({
                generation_number: data.generation_number,
                population_size: data.population_size,
                type: data.type,
                created_at: data.created_at,
                individuals: data.individuals,
                parentals: data.parentals || {}
            });

            showToast('Generación por cruce ' + data.generation_number + ' creada con éxito', 'success');
        } else {
            showToast('Error: ' + (data.error || 'Error desconocido'), 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Error al crear generación por cruce: ' + err.message, 'error');
    });
}

function createMultipleCrosses() {
    const source = Number(document.getElementById('multi_source_gen').value);
    const numIndiv = Number(document.getElementById('multi_num_indiv').value);
    const numCrosses = Number(document.getElementById('multi_num_crosses').value);
    const population = Number(document.getElementById('multi_population').value);
    const typeEl = document.querySelector('input[name="multi_cross_type"]:checked');
    const type = typeEl ? typeEl.value : 'associative';

    if (!source || source <= 0) { showToast('Generación fuente inválida','error'); return; }
    if (!numIndiv || numIndiv <= 0) { showToast('Número de individuos inválido','error'); return; }
    if (!numCrosses || numCrosses <= 0) { showToast('Número de cruces inválido','error'); return; }
    if (!population || population <= 0) { showToast('Tamaño de población inválido','error'); return; }

    const spinner = document.getElementById('multiCrossSpinner');
    if (spinner) spinner.style.display = 'inline-block';

    const form = new FormData();
    form.append('project_action','create_multiple_crosses');
    form.append('source_generation', source);
    form.append('individuals_per_cross', numIndiv);
    form.append('number_of_crosses', numCrosses);
    form.append('population_size', population);
    form.append('cross_type', type);

    fetch('index.php?option=2', { method: 'POST', body: form })
    .then(r => r.json())
    .then(data => {
        if (spinner) spinner.style.display = 'none';
        if (!data) return;
        if (!data.success) {
            showToast('Error: ' + (data.error || 'Error desconocido'),'error');
            return;
        }
        let firstCreated = null;
        let listAlreadyCreated = false;
        for (const res of data.results || []) {
            if (res.success) {
                const genNum = res.generation_number;
                const pop = res.population_size || '';
                let tbody = document.querySelector('#generationsList tbody');
                if (!tbody && !listAlreadyCreated) {
                    // First generation: create the generations list section dynamically
                    const leftPanel = document.querySelector('.left-panel');
                    if (leftPanel) {
                        const listCardHtml = `
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
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>`;
                        leftPanel.insertAdjacentHTML('beforeend', listCardHtml);
                        listAlreadyCreated = true;
                        tbody = document.querySelector('#generationsList tbody');
                    }
                }
                if (tbody) {
                    const rowHtml = `\n                    <tr id="gen-row-${genNum}">\n                        <td>${genNum}</td>\n                        <td>${escapeHtml('cross')}</td>\n                        <td>${escapeHtml(pop)}</td>\n                        <td class="actions-cell">\n                            <button onclick="viewGeneration(${genNum})" title="Abrir">👁️</button>\n                            <button onclick="deleteGeneration(${genNum})" title="Borrar" class="btn-danger">🗑️</button>\n                        </td>\n                    </tr>`;
                    tbody.insertAdjacentHTML('afterbegin', rowHtml);
                }
                showToast('Cruce creado: ' + genNum, 'success');
                if (!firstCreated) {
                    firstCreated = res;
                }
            } else {
                showToast('Error creating gen ' + res.generation_number + ': ' + (res.error || 'error'), 'error');
            }
        }

        // Rebuild parent generation selects if any were created
        if (listAlreadyCreated) {
            refreshGenerationSelects();
        }

        // Open first created generation in viewer
        if (firstCreated) {
            renderGenerationData({
                generation_number: firstCreated.generation_number,
                population_size: firstCreated.population_size,
                type: 'cross',
                created_at: firstCreated.created_at,
                individuals: firstCreated.individuals,
                parentals: firstCreated.parentals || {}
            });
        }
    })
    .catch(err => {
        if (spinner) spinner.style.display = 'none';
        console.error(err);
        showToast('Error al crear múltiples cruces: ' + err.message,'error');
    });
}

// Initialize event bindings to be resilient in case inline `onclick` attributes do not work
(function initGenerationBindings() {
    try {
        const toggleBtn = document.getElementById('btnToggleParentals');
        if (toggleBtn && !toggleBtn._bound) {
            toggleBtn.addEventListener('click', toggleParentals);
            toggleBtn._bound = true;
        }

        // Ensure existing 'Abrir' buttons open the generation correctly
        document.querySelectorAll('#generationsList button[title="Abrir"]').forEach(b => {
            if (b._bound) return;
            b.addEventListener('click', function (ev) {
                const tr = this.closest('tr');
                if (!tr) return;
                const td = tr.querySelector('td');
                if (!td) return;
                const genNum = Number(td.textContent.trim());
                if (genNum) viewGeneration(genNum);
            });
            b._bound = true;
        });

        // Ensure the 'Seleccionar parentales' button works even if inline handler fails
        try {
            const openBtn = document.getElementById('btnOpenParentSelector');
            if (openBtn && !openBtn._bound) {
                openBtn.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    const parentEl = document.getElementById('cross_parent_gen');
                    const targetEl = document.getElementById('cross_target_gen');
                    const parentGen = parentEl ? Number(parentEl.value) : null;
                    const targetGen = targetEl ? Number(targetEl.value) : null;
                    openParentSelector(parentGen, targetGen);
                });
                openBtn._bound = true;
            }
        } catch (err) {
            console.error('open parent selector binding error', err);
        }

        // Keep selection count in sync when checkboxes change (event delegation)
        if (!document._parentalCheckboxChangeBound) {
            document.addEventListener('change', function (ev) {
                const t = ev.target;
                if (t && t.classList && t.classList.contains('viewer-parental-checkbox')) {
                    updateSelectionCount();
                }
            });
            document._parentalCheckboxChangeBound = true;
        }

        // Fallback: use event delegation to handle clicks in case inline handlers are disabled
        if (!document._generationClickDelegated) {
            document.addEventListener('click', function (ev) {
                try {
                    const t = ev.target;
                    // Toggle parentals button (may be a child element inside the button)
                    const toggleBtn = t.closest && t.closest('#btnToggleParentals');
                    if (toggleBtn) {
                        ev.preventDefault();
                        toggleParentals();
                        return;
                    }

                    // Open generation ('Abrir' buttons inside #generationsList)
                    const openBtn = t.closest && t.closest('#generationsList button[title="Abrir"]');
                    if (openBtn) {
                        ev.preventDefault();
                        const tr = openBtn.closest('tr');
                        if (tr) {
                            const td = tr.querySelector('td');
                            if (td) {
                                const genNum = Number(td.textContent.trim());
                                if (genNum) viewGeneration(genNum);
                            }
                        }
                        return;
                    }
                } catch (err) {
                    console.error('delegated click handler error', err);
                }
                            // Ensure the new generation is available as a parent source in selects
            });
            document._generationClickDelegated = true;
        }

        console.debug('initGenerationBindings completed');
    } catch (err) {
        console.error('initGenerationBindings error', err);
    }
})();

// Function to download generation data CSV (semicolon columns)
function downloadGenerationCSV(decimalSeparator) {
    const genNum = document.getElementById('genNumber').textContent;
    if (!genNum) {
        showToast('No hay generación abierta', 'error');
        return;
    }
    const url = `index.php?option=2&project_action=download_generation_csv&generation_number=${encodeURIComponent(genNum)}&decimal=${encodeURIComponent(decimalSeparator)}`;
    window.open(url, '_blank');
}

// Expose key functions on window to ensure inline onclick handlers can find them
try {
    window.deleteGeneration = deleteGeneration;
    window.viewGeneration = viewGeneration;
    window.openParentSelector = openParentSelector;
    window.createRandomGeneration = createRandomGeneration;
    window.createCrossGeneration = createCrossGeneration;
    window.createMultipleCrosses = createMultipleCrosses;
    window.addSelectedParentals = addSelectedParentals;
    window.downloadGenerationCSV = downloadGenerationCSV;
    console.debug('Exposed functions on window for inline handlers');
} catch (err) {
    console.error('Error exposing functions to window', err);
}
</script>
