<?php
/**
 * Characters page template
 * @var \Ngw\Models\Character $characterModel
 * @var \Ngw\Auth\SessionManager $session
 * @var \Ngw\Models\Project $projectModel
 */

$userId = $session->getUserId();
$characters = $characterModel->getAvailableCharacters($userId);
$activeCharacterId = $session->get('active_character_id');
$activeCharacter = null;
$activeProjectId = $session->get('active_project_id');
$activeProject = null;

if ($activeProjectId && $projectModel) {
    $activeProject = $projectModel->getById($activeProjectId);
}

if ($activeCharacterId) {
    $activeCharacter = $characterModel->getById($activeCharacterId);
    $genes = $characterModel->getGenes($activeCharacterId);
    $activeGeneId = $session->get('active_gene_id');
    $activeGene = $activeGeneId ? $characterModel->getGeneById((int) $activeGeneId) : null;
    $alleles = $activeGene ? $characterModel->getAlleles((int) $activeGeneId) : [];
    $connections = $characterModel->getConnections($activeCharacterId);
    $showConnections = $session->get('show_connections', false);
}
?>

<script>
    window._activeCharacterId = <?= (int)$activeCharacterId ?>;
</script>

<h2>Gestión de Caracteres</h2>

<?php if ($activeProject) : ?>
    <div class="alert alert-info">
        <strong>Proyecto activo:</strong> <?= e($activeProject['name']) ?>
    </div>
<?php endif; ?>

<div class="two-column-layout">
    <!-- Columna izquierda: Lista de caracteres -->
    <div class="column-left">
        <div class="card">
            <h3>Lista de Caracteres</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Público</th>
                        <th>Acciones</th>
                        <?php if ($activeProject) : ?>
                            <th>Añadir a Proyecto</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($characters)) : ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay caracteres disponibles</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($characters as $char) : ?>
                            <tr <?= $activeCharacterId === (int)$char['id'] ? 'style="background-color: var(--color-surface-light);"' : '' ?>>
                                <td><?= e($char['id']) ?></td>
                                <td><?= e($char['name']) ?></td>
                                <td><?= $char['is_public'] == 1 ? 'Sí' : 'No' ?></td>
                                <td>
                                    <button type="button" onclick="openCharacter(<?= e($char['id']) ?>)" class="btn-primary btn-small">Abrir</button>
                                    
                                    <?php if ((int)$char['creator_id'] === $userId) : ?>
                                        <button type="button" onclick="deleteCharacter(<?= e($char['id']) ?>, '<?= e(addslashes($char['name'])) ?>')" class="btn-danger btn-small">Borrar</button>
                                    <?php endif; ?>
                                </td>
                                <?php if ($activeProject) : ?>
                                    <td>
                                        <button type="button" onclick="addCharacterToProject(<?= e($char['id']) ?>, '<?= e(addslashes($char['name'])) ?>')" class="btn-success btn-small">Añadir</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Columna derecha: Crear nuevo carácter y detalles -->
    <div class="column-right">
        <?php if (!$activeCharacter) : ?>
        <div class="card">
            <h3>Crear Nuevo Carácter</h3>
            <form method="post" id="create-character-form">
                <input type="hidden" name="char_action" value="create">
                
                <div class="form-group">
                    <label for="char_name">Nombre del Carácter</label>
                    <input type="text" id="char_name" name="char_name" required>
                </div>
                
                <?php if ($session->isTeacher() || $session->isAdmin()) : ?>
                    <div class="form-group form-inline">
                        <label>
                            <input type="checkbox" name="visible">
                            Visible
                        </label>
                        <label style="margin-left: 1rem;">
                            <input type="checkbox" name="public">
                            Público
                        </label>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-success">Crear Carácter</button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($activeCharacter) : ?>
            <div class="card" data-active-character-id="<?= e($activeCharacterId ?? 0) ?>">
                <h3>Detalles del Carácter: <?= e($activeCharacter['name']) ?></h3>
                
                <!-- Botones principales -->
                <div style="margin-bottom: 1.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php if ($session->isTeacher() || $session->isAdmin()) : ?>
                        <button type="button" onclick="updateCharacterProps(<?= $activeCharacter['id'] ?>, <?= $activeCharacter['is_visible'] ?>, <?= $activeCharacter['is_public'] ?>)" class="btn-success">Guardar cambios</button>
                    <?php endif; ?>
                    
                    <button type="button" onclick="closeCharacter()" class="btn-secondary">Cerrar carácter</button>
                    
                    <button type="button" class="btn-primary" id="toggle-genes-btn" onclick="toggleGenesView()">
                        Ver Genes
                    </button>
                    
                    <button type="button" class="btn-primary" id="toggle-connections-btn" onclick="toggleConnectionsView()">
                        <?= $showConnections ? 'Ocultar Conexiones' : 'Ver Conexiones' ?>
                    </button>
                    
                    <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                        <button type="button" class="btn-success" onclick="document.getElementById('create-gene-form-container').style.display = document.getElementById('create-gene-form-container').style.display === 'none' ? 'block' : 'none';">
                            Crear nuevo gen
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Tabla de Genes (oculta por defecto) -->
                <div id="genes-view" style="display: none; margin-top: 1.5rem;">
                    <?php if (!empty($genes)) : ?>
                        <h4>Genes del carácter</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Cromosoma</th>
                                    <th>Posición</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($genes as $gene) : ?>
                                    <tr>
                                        <td><?= e($gene['id']) ?></td>
                                        <td><?= e($gene['name']) ?></td>
                                        <td><?php 
                                            $chrDisplay = '';
                                            if ($gene['chromosome']) {
                                                $chrDisplay = $gene['chromosome'];
                                                if ($gene['code']) {
                                                    $chrDisplay .= ' (' . $gene['code'] . ')';
                                                }
                                            } elseif ($gene['code']) {
                                                $chrDisplay = $gene['code'];
                                            }
                                            echo e($chrDisplay);
                                        ?></td>
                                        <td><?= e($gene['position']) ?></td>
                                        <td>
                                            <button type="button" onclick="openGene(<?= e($gene['id']) ?>)" class="btn-primary btn-small">Abrir</button>

                                            <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                                <button type="button" onclick="deleteGene(<?= e($gene['id']) ?>, '<?= e(addslashes($gene['name'])) ?>')" class="btn-danger btn-small">Borrar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="text-center">No hay genes definidos para este carácter.</p>
                    <?php endif; ?>
                </div>

                <!-- Formulario de crear gen (oculto por defecto) -->
                <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                    <div id="create-gene-form-container" style="display: none; margin-top: 1.5rem;">
                        <h4>Crear nuevo gen</h4>
                        <form method="post" id="create-gene-form">
                            <input type="hidden" name="char_action" value="create_gene">
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" name="gene_name" required>
                            </div>
                            <div class="form-group">
                                <label>Cromosoma nº</label>
                                <input type="text" name="gene_chr" placeholder="Ej: 1, 2, 3...">
                            </div>
                            <div class="form-group">
                                <label>Tipo de cromosoma</label>
                                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                    <label>
                                        <input type="checkbox" name="gene_type[]" value="X"> X
                                    </label>
                                    <label>
                                        <input type="checkbox" name="gene_type[]" value="Y"> Y
                                    </label>
                                    <label>
                                        <input type="checkbox" name="gene_type[]" value="A" checked> A
                                    </label>
                                    <label>
                                        <input type="checkbox" name="gene_type[]" value="B" checked> B
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Posición</label>
                                <input type="text" name="gene_pos">
                            </div>
                            <button type="submit" class="btn-success btn-small">Crear Gen</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Vista de alelos de un gen abierto -->
                <?php if ($activeGene) : ?>
                    <div style="margin-top: 1.5rem;">
                        <h4>Gen abierto: <?= e($activeGene['name']) ?></h4>

                        <div style="margin: .5rem 0 1rem 0;">
                            <button type="button" onclick="closeGene()" class="btn-secondary btn-small">Cerrar Gen</button>
                        </div>

                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Valor</th>
                                    <th>Aditivo</th>
                                    <th>Dominancia</th>
                                    <th>Epistasis</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($alleles)) : ?>
                                    <?php foreach ($alleles as $al) : ?>
                                    <?php 
                                        $displayDominance = $al['dominance'];
                                        if ((int)$al['additive'] === 1 && $displayDominance !== null) {
                                            $strDom = (string)$displayDominance;
                                            if (strpos($strDom, '1') === 0) {
                                                $displayDominance = substr($strDom, 1);
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td><?= e($al['id']) ?></td>
                                        <td><?= e($al['name']) ?></td>
                                        <td><?= e($al['value']) ?></td>
                                        <td><?= e((int)$al['additive'] === 1 ? 'Sí' : 'No') ?></td>
                                        <td><?= e($displayDominance) ?></td>
                                        <td><?= e($al['epistasis']) ?></td>
                                        <td>
                                            <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                                <button onclick="deleteAllele(<?= $al['id'] ?>, () => openGene(<?= (int)$activeGene['id'] ?>, true))" class="btn-danger btn-small">Eliminar</button>
                                            <?php endif; ?>
                                        </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No hay alelos definidos</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                            <form method="post" id="add-allele-form" style="margin-top: 1rem;">
                                <input type="hidden" name="char_action" value="add_allele">
                                <h5>Añadir nuevo alelo</h5>
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="allele_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Valor</label>
                                    <input type="text" name="allele_value">
                                </div>
                                <div class="form-group">
                                    <label>Aditivo</label>
                                    <label><input type="checkbox" name="allele_additive" value="1"> Sí</label>
                                </div>
                                <div class="form-group">
                                    <label>Dominancia</label>
                                    <input type="text" name="allele_dominance">
                                </div>
                                <div class="form-group">
                                    <label>Epistasis</label>
                                    <input type="text" name="allele_epistasis">
                                </div>
                                <button type="submit" class="btn-success btn-small">Añadir Alelo</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sección de Conexiones -->
            <a name="connections"></a>
            <div id="connections-view" style="display: <?= $showConnections ? 'block' : 'none' ?>; margin-top: 1.5rem;" class="card">
                <h4>Conexiones del Carácter</h4>
                
                <!-- Mostrar conexiones existentes -->
                <?php if (!empty($connections)) : ?>
                    <table id="connections-table" style="width: 100%; margin-bottom: 1rem;">
                        <thead>
                            <tr>
                                <th>Estado A</th>
                                <th>Gen (Transición)</th>
                                <th>Estado B</th>
                                <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                    <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($connections as $conn) : ?>
                                <?php
                                    // Get gene name for transition
                                    $transGene = $characterModel->getGeneById((int)$conn['transition']);
                                    $geneName = $transGene ? $transGene['name'] : 'Gen #' . $conn['transition'];
                                ?>
                                <tr data-connection-id="<?= e($conn['id']) ?>">
                                    <td>S<?= e($conn['state_a']) ?></td>
                                    <td><?= e($geneName) ?></td>
                                    <td>S<?= e($conn['state_b']) ?></td>
                                    <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                        <td>
                                            <button onclick="deleteConnectionRow(this, <?= e($conn['id']) ?>)" class="btn-danger btn-small">Borrar</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="text-center">No hay conexiones definidas para este carácter.</p>
                <?php endif; ?>

                <!-- Visualización de red de Petri (siempre visible; mostrará placeholder si no hay conexiones) -->
                <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-top: 1rem;">
                    <h5>Diagrama de Red de Petri</h5>
                    <div id="petri-net-diagram" style="width: 100%; min-height: 300px; border: 1px solid var(--color-border); border-radius: 4px; padding: 1rem; background: #fafafa; overflow-x: auto;">
                        <?php if (empty($connections)) : ?>
                            <p id="petri-net-placeholder" class="text-center" style="color: var(--color-text-secondary);">No hay conexiones definidas para este carácter.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Formulario para añadir conexiones -->
                <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                    <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-top: 1rem;">
                        <h5>Añadir nueva conexión</h5>
                        
                        <!-- Update substrates form -->
                        <form method="post" id="substrates-form" style="margin-bottom: 1rem; display:flex; align-items:center; gap:0.5rem;">
                            <input type="hidden" name="char_action" value="update_substrates">
                            <div class="form-group" style="margin:0;">
                                <label style="display:flex; align-items:center; gap:0.5rem;">Número de sustratos: 
                                    <input type="number" 
                                           id="substrates-input" 
                                           name="substrates" 
                                           value="<?= e($activeCharacter['substrates'] ?? 0) ?>" 
                                           min="0" 
                                           max="20"
                                           style="width: 80px;">
                                </label>
                                <small style="color: var(--color-text-secondary); margin-left: 0.5rem;">
                                    (Se actualiza automáticamente)
                                </small>
                            </div>

                        </form>


                        <?php 
                        $numSubstrates = (int)($activeCharacter['substrates'] ?? 0);
                        if ($numSubstrates > 0 && !empty($genes)) : 
                        ?>
                            <form method="post" id="add-connection-form">
                                <input type="hidden" name="char_action" value="add_connection">
                                
                                <div class="form-group">
                                    <label>Estado inicial (S):</label>
                                    <div id="state-a-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php for ($i = 0; $i < $numSubstrates; $i++) : ?>
                                            <label>
                                                <input type="radio" name="state_a" value="<?= $i ?>" required> S<?= $i ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Gen (Transición):</label>
                                    <div id="transition-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php foreach ($genes as $gene) : ?>
                                            <label>
                                                <input type="radio" name="transition" value="<?= e($gene['id']) ?>" required> <?= e($gene['name']) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Estado final (S):</label>
                                    <div id="state-b-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php for ($i = 0; $i < $numSubstrates; $i++) : ?>
                                            <label>
                                                <input type="radio" name="state_b" value="<?= $i ?>" required> S<?= $i ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <button type="submit" class="btn-success">Guardar Conexión</button>
                            </form>
                        <?php elseif ($numSubstrates === 0) : ?>
                            <p id="no-substrates-message" class="text-center" style="color: var(--color-warning);">Primero debes establecer el número de sustratos.</p>
                        <?php elseif (empty($genes)) : ?>
                            <p id="no-genes-warning" class="text-center" style="color: var(--color-warning);">Primero debes crear genes para este carácter.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
// Toggle genes view visibility
const genesViewBtn = document.querySelector('button[class*="btn"]');
document.querySelectorAll('form').forEach(form => {
    const action = form.querySelector('input[name="char_action"]');
    // Removed old toggle handlers - now using direct button onclick
});

// Handle character form submission with AJAX
const createCharacterForm = document.getElementById('create-character-form');
if (createCharacterForm) {
    createCharacterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.set('char_action', 'create_character_ajax');
        
        fetch('index.php?option=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Carácter creado', 'success');
                
                // Add row to characters table
                const tbody = document.querySelector('table tbody');
                if (tbody) {
                    // Remove "no characters" row if exists
                    const emptyRow = tbody.querySelector('td[colspan]');
                    if (emptyRow) {
                        emptyRow.closest('tr').remove();
                    }
                    
                    const char = data.character;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${char.id}</td>
                        <td>${char.name}</td>
                        <td>${char.is_public == 1 ? 'Sí' : 'No'}</td>
                        <td>
                            <button type="button" onclick="openCharacter(${char.id})" class="btn-primary btn-small">Abrir</button>
                            <button type="button" onclick="deleteCharacter(${char.id}, '${char.name.replace(/'/g, "\\'")}')" class="btn-danger btn-small">Borrar</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                }
                
                // Reset form
                createCharacterForm.reset();
            } else {
                showNotification(data.error || 'Error al crear carácter', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
    });
}

// Handle delete allele via AJAX
document.querySelectorAll('.delete-allele-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const alleleId = this.querySelector('[name="allele_id"]').value;
        const row = this.closest('tr');

        confirmAction('¿Eliminar alelo? Esta acción no se puede deshacer.', 'Eliminar', 'Cancelar')
        .then(ok => {
            if (!ok) return false;

            const formData = new FormData();
            formData.append('char_action', 'remove_allele_ajax');
            formData.append('allele_id', alleleId);

            fetch('index.php?option=1', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Alelo eliminado', 'success');
                    row.remove();
                    
                    // Check if table is now empty
                    const tbody = row.closest('tbody');
                    if (tbody && tbody.children.length === 0) {
                        const emptyRow = document.createElement('tr');
                        emptyRow.innerHTML = '<td colspan="5" class="text-center">No hay alelos definidos</td>';
                        tbody.appendChild(emptyRow);
                    }
                } else {
                    showNotification(data.error || 'Error al eliminar alelo', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error de conexión', 'error');
            });
        });
    });
});

// Confirm delete connection
document.querySelectorAll('.delete-connection-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        confirmAction('¿Eliminar conexión? Esta acción no se puede deshacer.', 'Eliminar', 'Cancelar')
        .then(ok => {
            if (!ok) return false;
            form.submit();
        });
    });
});

// Auto-update substrates via AJAX
const substratesInputAjax = document.getElementById('substrates-input');
if (substratesInputAjax) {
    const characterId = <?= $activeCharacterId ?? 0 ?>;
    let substratesTimeout;
    let prevSubstratesValue = parseInt(substratesInputAjax.value || 0);
    
    // Remove old listeners by cloning the node
    const newInput = substratesInputAjax.cloneNode(true);
    substratesInputAjax.parentNode.replaceChild(newInput, substratesInputAjax);
    
    // Track previous value so we can revert if server rejects the change
    newInput.addEventListener('focus', function() {
        prevSubstratesValue = parseInt(this.value || 0);
    });

    newInput.addEventListener('input', function() {
        clearTimeout(substratesTimeout);
        
        substratesTimeout = setTimeout(function() {
            const value = parseInt(newInput.value);
            if (!isNaN(value) && value >= 0 && characterId > 0) {
                updateSubstrates(characterId, value, function(data) {
                    // Success: update prev value and UI
                    prevSubstratesValue = data.substrates;
                    reloadSubstrateSelectors(data.substrates);
                    // Remove deleted connections from table
                    if (data.deleted_connections && data.deleted_connections.length > 0) {
                        data.deleted_connections.forEach(function(connId) {
                            const row = document.querySelector('tr[data-connection-id="' + connId + '"]');
                            if (row) row.remove();
                        });
                        // Redraw Petri net
                        if (typeof drawPetriNet === 'function') drawPetriNet();
                    }
                }, function(err) {
                    // Error: revert to previous value to make it obvious it didn't change
                    newInput.value = prevSubstratesValue;
                    if (typeof flashInputHighlight === 'function') flashInputHighlight();
                });
            }
        }, 800);
    });
    
    newInput.addEventListener('blur', function() {
        clearTimeout(substratesTimeout);
        const value = parseInt(newInput.value);
        if (!isNaN(value) && value >= 0 && characterId > 0) {
            updateSubstrates(characterId, value, function(data) {
                prevSubstratesValue = data.substrates;
                reloadSubstrateSelectors(data.substrates);
                // Remove deleted connections from table
                if (data.deleted_connections && data.deleted_connections.length > 0) {
                    data.deleted_connections.forEach(function(connId) {
                        const row = document.querySelector('tr[data-connection-id="' + connId + '"]');
                        if (row) row.remove();
                    });
                    // Redraw Petri net
                    if (typeof drawPetriNet === 'function') drawPetriNet();
                }
            }, function(err) {
                newInput.value = prevSubstratesValue;
            });
        }
    });
}

// Handle add connection form via AJAX
const addConnectionForm = document.getElementById('add-connection-form');
if (addConnectionForm && !addConnectionForm._listenerAttached) {
    addConnectionForm._listenerAttached = true;
    addConnectionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const characterId = <?= $activeCharacterId ?? 0 ?>;
        const stateA = document.querySelector('input[name="state_a"]:checked')?.value;
        const transition = document.querySelector('input[name="transition"]:checked')?.value;
        const stateB = document.querySelector('input[name="state_b"]:checked')?.value;
        
        if (stateA !== undefined && transition && stateB !== undefined && characterId > 0) {
            addConnection(characterId, parseInt(stateA), parseInt(transition), parseInt(stateB), function(data) {
                console.log('Connection added, data:', data);
                // Add new row to connections table
                addConnectionToTable(data.connection);
                
                // Reset form
                addConnectionForm.reset();

                // Re-enable all substrate radio inputs so they are available for the next connection
                const stateInputs = document.querySelectorAll('input[name="state_a"], input[name="state_b"]');
                stateInputs.forEach(function(input) {
                    input.disabled = false;
                    input.checked = false;
                    const label = input.closest('label');
                    if (label) {
                        label.style.opacity = '1';
                        label.style.cursor = 'pointer';
                    }
                });

                // Re-apply validation listeners
                if (typeof setupStateValidation === 'function') setupStateValidation();
            });
        }
    });
}

// Add connection row to table dynamically
function addConnectionToTable(connection) {
    console.log('Adding connection to table:', connection);
    let tbody = document.querySelector('#connections-table tbody');
    
    // If table doesn't exist or shows "no connections" message, create/update it
    if (!tbody) {
        // Create table structure if it doesn't exist
        const tableContainer = document.querySelector('#connections-view');
        if (tableContainer) {
            const noConnectionsMsg = tableContainer.querySelector('p.text-center');
            if (noConnectionsMsg) {
                noConnectionsMsg.remove();
            }
            
            const table = document.createElement('table');
            table.id = 'connections-table';
            table.style.width = '100%';
            table.style.marginBottom = '1rem';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Estado A</th>
                        <th>Gen (Transición)</th>
                        <th>Estado B</th>
                        <?php if ($session->isTeacher() || $session->isAdmin() || (int)($activeCharacter['creator_id'] ?? 0) === $userId) : ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            `;
            
            const addConnectionSection = document.querySelector('#connections-view > div[style*="border-top"]');
            if (addConnectionSection) {
                tableContainer.insertBefore(table, addConnectionSection);
            } else {
                tableContainer.appendChild(table);
            }
            
            tbody = table.querySelector('tbody');
        }
    } else {
        // Remove "no connections" row if it exists
        const noConnRow = tbody.querySelector('td[colspan]');
        if (noConnRow) {
            noConnRow.closest('tr').remove();
        }
    }
    
    if (tbody) {
        const row = document.createElement('tr');
        row.setAttribute('data-connection-id', connection.id);
        row.innerHTML = `
            <td>S${connection.state_a}</td>
            <td>${connection.gene_name || 'Gen #' + connection.transition}</td>
            <td>S${connection.state_b}</td>
            <?php if ($session->isTeacher() || $session->isAdmin() || (int)($activeCharacter['creator_id'] ?? 0) === $userId) : ?>
                <td>
                    <button onclick="deleteConnectionRow(this, ${connection.id})" 
                            class="btn-danger btn-small">Borrar</button>
                </td>
            <?php endif; ?>
        `;
        tbody.appendChild(row);
        console.log('Row added to tbody, total rows:', tbody.children.length);
        
        // Redraw Petri net diagram
        drawPetriNet();
    } else {
        console.error('tbody not found!');
    }
}

// Toggle views without page reload
function toggleGenesView() {
    const genesView = document.getElementById('genes-view');
    const btn = document.getElementById('toggle-genes-btn');
    
    if (genesView) {
        const isVisible = genesView.style.display !== 'none';
        genesView.style.display = isVisible ? 'none' : 'block';
        
        if (btn) {
            btn.textContent = isVisible ? 'Ver Genes' : 'Ocultar Genes';
        }
    }
}

function toggleGene(geneId, btnElement) {
    // If the button element is provided, use it; otherwise fall back to lookup by id
    const btn = btnElement || document.getElementById('gene-toggle-' + geneId);
    const allelesSection = document.getElementById('alleles-section');
    console.debug('toggleGene called for', geneId, 'btnElement?', !!btnElement, 'existing allelesSection?', !!allelesSection);
    
    // Check if this gene is currently open
    if (allelesSection) {
        closeGene();
        // Also update button state
        if (btn) {
            btn.textContent = 'Abrir';
            btn.className = 'btn-primary btn-small';
            console.debug('toggleGene: set to Abrir for gene', geneId);
        }
    } else {
        openGene(geneId);
        // Update button state optimistically
        if (btn) {
            btn.textContent = 'Cerrar';
            btn.className = 'btn-secondary btn-small';
            console.debug('toggleGene: optimistically set to Cerrar for gene', geneId);
        }
    }
}

function toggleConnectionsView() {
    const connectionsView = document.getElementById('connections-view');
    const btn = document.getElementById('toggle-connections-btn');
    
    if (connectionsView) {
        const isVisible = connectionsView.style.display !== 'none';
        connectionsView.style.display = isVisible ? 'none' : 'block';
        
        if (btn) {
            btn.textContent = isVisible ? 'Ver Panel de Conexiones' : 'Ocultar Panel de Conexiones';
        }
        
        // Draw Petri net if connections are visible
        if (!isVisible) {
            setTimeout(drawPetriNet, 100);
        }
    }
}

/**
 * Draw Petri Net diagram with only genes/transitions (no connections)
 */
function drawEmptyDiagram(container, states, transitions) {
    // Remove placeholder if present
    const placeholder = container.querySelector('#petri-net-placeholder');
    if (placeholder) placeholder.remove();

    const marginX = 40;
    const marginY = 40;
    const transitionWidth = 50;
    const transitionHeight = 70;
    const horizontalSpacing = 150;
    const verticalSpacing = 120;

    // Layout transitions horizontally at the top
    let svg = `<svg width="${Math.max(600, transitions.length * horizontalSpacing + 2 * marginX)}" height="400" xmlns="http://www.w3.org/2000/svg">`;
    svg += `<defs><marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><polygon points="0 0, 10 3, 0 6" fill="#999" /></marker></defs>`;
    
    // Draw title
    svg += `<text x="20" y="25" font-size="12" font-weight="bold" fill="#666">Genes/Transiciones disponibles:</text>`;
    
    // Draw transitions
    transitions.forEach((transition, index) => {
        const x = marginX + index * horizontalSpacing;
        const y = marginY + 50;
        
        // Draw transition rectangle
        svg += `<rect x="${x - transitionWidth/2}" y="${y - transitionHeight/2}" width="${transitionWidth}" height="${transitionHeight}" fill="#e8f4f8" stroke="#3498db" stroke-width="2" stroke-dasharray="5,5" rx="4"/>`;
        
        // Draw transition label
        svg += `<text x="${x}" y="${y + 5}" text-anchor="middle" font-size="11" font-weight="bold" fill="#2c3e50">${transition}</text>`;
    });
    
    // Draw info text
    svg += `<text x="20" y="350" font-size="11" fill="#999">Crea conexiones para conectar sustratos con genes transiciones</text>`;
    
    if (states.length > 0) {
        svg += `<text x="20" y="370" font-size="11" fill="#999">Sustratos disponibles: S${states.join(', S')}</text>`;
    }
    
    svg += `</svg>`;
    container.innerHTML = svg;
}

/**
 * Draw Petri Net diagram based on connections
 */
function drawPetriNet() {
    const container = document.getElementById('petri-net-diagram');
    
    if (!container) {
        console.log('drawPetriNet: container not found');
        return;
    }
    
    // Extract connections from table (if it exists)
    const connections = [];
    const table = document.getElementById('connections-table');
    if (table) {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const cells = row.cells;
            if (cells && cells.length >= 3) {
                const stateA = cells[0].textContent.replace('S', '').trim();
                const transition = cells[1].textContent.trim();
                const stateB = cells[2].textContent.replace('S', '').trim();
                connections.push({ stateA: parseInt(stateA), transition, stateB: parseInt(stateB) });
            }
        });
    }
    
    // Get available genes/transitions from the form
    const transitionInputs = document.querySelectorAll('input[name="transition"]');
    const availableTransitions = [];
    transitionInputs.forEach(input => {
        // Extract the gene name from the input's next sibling text node
        let geneName = '';
        let nextNode = input.nextSibling;
        while (nextNode) {
            if (nextNode.nodeType === Node.TEXT_NODE) {
                geneName = nextNode.textContent.trim();
                if (geneName) break; // Found the gene name
            }
            nextNode = nextNode.nextSibling;
        }
        if (geneName && !availableTransitions.includes(geneName)) {
            availableTransitions.push(geneName);
        }
    });
    
    // Get available states from the form
    const stateInputs = document.querySelectorAll('input[name="state_a"]');
    const availableStates = [];
    stateInputs.forEach(input => {
        const label = input.closest('label');
        if (label) {
            const text = label.textContent.replace('S', '').trim();
            if (text && !availableStates.includes(parseInt(text))) {
                availableStates.push(parseInt(text));
            }
        }
    });
    
    // If no connections but we have states and transitions, show them
    if (connections.length === 0 && (availableStates.length > 0 || availableTransitions.length > 0)) {
        drawEmptyDiagram(container, availableStates, availableTransitions);
        return;
    }
    
    if (connections.length === 0) {
        return;
    }
    
    // Get unique states
    const states = new Set();
    connections.forEach(c => {
        states.add(c.stateA);
        states.add(c.stateB);
    });
    const stateArray = Array.from(states).sort((a, b) => a - b);
    
    // Detect if it's a linear chain
    const isLinear = connections.length === 1 || connections.every((c, i, arr) => {
        // Check if it forms a sequential chain
        if (i === 0) return true;
        return arr[i-1].stateB === c.stateA;
    });
    
    // Calculate layout
    const placeRadius = 25;
    const transitionWidth = 40;
    const transitionHeight = 60;
    const horizontalSpacing = 180;
    const verticalSpacing = 100;
    const marginX = 60;
    const marginY = 100;
    
    const statePositions = {};
    const transitionPositions = {};
    
    if (isLinear) {
        // Linear layout: S0 -> T1 -> S1 -> T2 -> S2 (all in a horizontal line)
        let currentX = marginX;
        const y = marginY;
        
        // Sort connections by sequence
        const sortedConns = [...connections].sort((a, b) => a.stateA - b.stateA);
        const processedStates = new Set();
        
        sortedConns.forEach((conn, index) => {
            // Add state A if not processed
            if (!processedStates.has(conn.stateA)) {
                statePositions[conn.stateA] = { x: currentX, y: y };
                processedStates.add(conn.stateA);
                currentX += horizontalSpacing;
            }
            
            // Add transition - position it between states
            const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
            transitionPositions[key] = {
                x: currentX,
                y: y,
                label: conn.transition
            };
            currentX += horizontalSpacing;
            
            // Add state B if not processed
            if (!processedStates.has(conn.stateB)) {
                statePositions[conn.stateB] = { x: currentX, y: y };
                processedStates.add(conn.stateB);
                currentX += horizontalSpacing;
            }
        });
    } else {
        // Complex layout: all states in horizontal line, genes distributed vertically
        const stateY = marginY;
        stateArray.forEach((state, index) => {
            statePositions[state] = {
                x: marginX + index * horizontalSpacing,
                y: stateY
            };
        });
        
        // Group connections by state pair (stateA, stateB)
        const connectionsByPair = {};
        connections.forEach(conn => {
            const pairKey = `${conn.stateA}-${conn.stateB}`;
            if (!connectionsByPair[pairKey]) {
                connectionsByPair[pairKey] = [];
            }
            connectionsByPair[pairKey].push(conn);
        });
        
        // Position transitions based on grouping
        Object.entries(connectionsByPair).forEach(([pairKey, pairConns]) => {
            const [stateA, stateB] = pairKey.split('-').map(Number);
            const stateAX = statePositions[stateA].x;
            const stateBX = statePositions[stateB].x;
            const avgX = (stateAX + stateBX) / 2;
            
            const numGenes = pairConns.length;
            
            if (numGenes === 1) {
                // Single gene: place on same horizontal line as states
                const conn = pairConns[0];
                const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
                transitionPositions[key] = {
                    x: avgX,
                    y: stateY,
                    label: conn.transition
                };
            } else {
                // Multiple genes: distribute symmetrically
                const isOdd = numGenes % 2 === 1;
                
                pairConns.forEach((conn, index) => {
                    const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
                    let yOffset;
                    
                    if (isOdd) {
                        // Odd number: one in center, rest above and below
                        const centerIndex = Math.floor(numGenes / 2);
                        if (index === centerIndex) {
                            // Center gene on horizontal line
                            yOffset = 0;
                        } else if (index < centerIndex) {
                            // Genes before center go above
                            const layer = centerIndex - index;
                            yOffset = -layer * verticalSpacing;
                        } else {
                            // Genes after center go below
                            const layer = index - centerIndex;
                            yOffset = layer * verticalSpacing;
                        }
                    } else {
                        // Even number: half above, half below
                        const halfPoint = numGenes / 2;
                        if (index < halfPoint) {
                            // First half goes above
                            const layer = halfPoint - index;
                            yOffset = -layer * verticalSpacing;
                        } else {
                            // Second half goes below
                            const layer = index - halfPoint + 1;
                            yOffset = layer * verticalSpacing;
                        }
                    }
                    
                    transitionPositions[key] = {
                        x: avgX,
                        y: stateY + yOffset,
                        label: conn.transition
                    };
                });
            }
        });
    }
    
    try {
        // Remove placeholder if present
        const placeholder = container.querySelector('#petri-net-placeholder');
        if (placeholder) placeholder.remove();

        // Defensive fallback: ensure we have at least some state positions
        if (Object.keys(statePositions).length === 0) {
            // Build simple layout from unique states
            const uniqueStates = Array.from(new Set(connections.flatMap(c => [c.stateA, c.stateB]))).sort((a,b) => a-b);
            uniqueStates.forEach((s, i) => {
                statePositions[s] = { x: marginX + i * horizontalSpacing, y: marginY + 50 };
            });
        }

        if (Object.keys(transitionPositions).length === 0) {
            // Place transitions centered between their states
            connections.forEach((conn, index) => {
                const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
                const avgX = (statePositions[conn.stateA].x + statePositions[conn.stateB].x) / 2;
                transitionPositions[key] = {
                    x: avgX,
                    y: marginY + 150 + Math.floor(index / Math.max(1, Math.ceil(connections.length / 3))) * verticalSpacing,
                    label: conn.transition
                };
            });
        }

        // Calculate genes at top section
        const genesNotInConnections = availableTransitions.filter(geneName => {
            return !Object.values(transitionPositions).some(pos => pos.label === geneName);
        });
        const numUnconnectedGenes = genesNotInConnections.length;
        const genesTopHeight = numUnconnectedGenes > 0 ? 120 : 20;
        
        const connectionOffsetY = genesTopHeight;

        // Build SVG content first, then calculate bounds
        let svgContent = '';
        
        // Define arrowhead marker
        svgContent += `<defs><marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><polygon points="0 0, 10 3, 0 6" fill="#666" /></marker></defs>`;
    
        // Draw only genes that are NOT in connections at the top
        if (genesNotInConnections.length > 0) {
            svgContent += `<text x="20" y="25" font-size="12" font-weight="bold" fill="#666">Genes sin conexiones:</text>`;
            genesNotInConnections.forEach((geneName, index) => {
                const x = marginX + (index % 4) * 150;
                const y = 60 + Math.floor(index / 4) * 100;
                
                svgContent += `<rect x="${x - transitionWidth/2}" y="${y - transitionHeight/2}" width="${transitionWidth}" height="${transitionHeight}" fill="#e8f4f8" stroke="#3498db" stroke-width="2" stroke-dasharray="5,5" rx="4"/>`;
                svgContent += `<text x="${x}" y="${y + 5}" text-anchor="middle" font-size="11" font-weight="bold" fill="#2c3e50">${geneName}</text>`;
            });
        }
    
        // Track all coordinates for bounds calculation
        let allXs = [];
        let allYs = [];
    
        // Draw connections (arcs)
        connections.forEach(conn => {
            const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
            const stateAPos = statePositions[conn.stateA];
            const stateBPos = statePositions[conn.stateB];
            const transPos = transitionPositions[key];
            
            if (isLinear) {
                // Horizontal arrows for linear layout - all in same horizontal line
                const y = stateAPos.y + connectionOffsetY;
                
                // Arc from state A to transition (horizontal)
                const x1 = stateAPos.x + placeRadius;
                const x2 = transPos.x - transitionWidth/2;
                svgContent += `<line x1="${x1}" y1="${y}" x2="${x2}" y2="${y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
                
                // Arc from transition to state B (horizontal)
                const x3 = transPos.x + transitionWidth/2;
                const x4 = stateBPos.x - placeRadius;
                svgContent += `<line x1="${x3}" y1="${y}" x2="${x4}" y2="${y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
            } else {
                // Straight arrows for complex layout
                const stateAYOffset = connectionOffsetY + stateAPos.y;
                const stateBYOffset = connectionOffsetY + stateBPos.y;
                const transYOffset = connectionOffsetY + transPos.y;
                
                // Transition always uses center of vertical walls
                // Left wall center for incoming, right wall center for outgoing
                const trans_left_x = transPos.x - transitionWidth/2;
                const trans_right_x = transPos.x + transitionWidth/2;
                const trans_center_y = transYOffset;
                
                // From state A to transition (left wall)
                let stateA_x, stateA_y;
                
                // Calculate angle to determine exit point from circle
                const dx = trans_left_x - stateAPos.x;
                const dy = trans_center_y - stateAYOffset;
                const angle = Math.atan2(dy, dx);
                
                stateA_x = stateAPos.x + placeRadius * Math.cos(angle);
                stateA_y = stateAYOffset + placeRadius * Math.sin(angle);
                
                svgContent += `<line x1="${stateA_x}" y1="${stateA_y}" x2="${trans_left_x}" y2="${trans_center_y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
                
                // From transition (right wall) to state B
                let stateB_x, stateB_y;
                
                // Calculate angle to determine entry point to circle
                const dx2 = stateBPos.x - trans_right_x;
                const dy2 = stateBYOffset - trans_center_y;
                const angle2 = Math.atan2(dy2, dx2);
                
                stateB_x = stateBPos.x - placeRadius * Math.cos(angle2);
                stateB_y = stateBYOffset - placeRadius * Math.sin(angle2);
                
                svgContent += `<line x1="${trans_right_x}" y1="${trans_center_y}" x2="${stateB_x}" y2="${stateB_y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
                
                // Track coordinates
                allXs.push(stateA_x, trans_left_x, trans_right_x, stateB_x);
                allYs.push(stateA_y, trans_center_y, stateB_y);
            }
        });
        
        // Draw states (places - circles)
        stateArray.forEach(state => {
            const pos = statePositions[state];
            const cy = pos.y + connectionOffsetY;
            svgContent += `<circle cx="${pos.x}" cy="${cy}" r="${placeRadius}" fill="white" stroke="#2c3e50" stroke-width="2"/>`;
            svgContent += `<text x="${pos.x}" y="${cy + 5}" text-anchor="middle" font-size="14" font-weight="bold" fill="#2c3e50">S${state}</text>`;
            allXs.push(pos.x - placeRadius, pos.x + placeRadius);
            allYs.push(cy - placeRadius, cy + placeRadius);
        });
        
        // Draw transitions (rectangles) that are part of connections
        Object.entries(transitionPositions).forEach(([key, pos]) => {
            const ty = pos.y + connectionOffsetY;
            svgContent += `<rect x="${pos.x - transitionWidth/2}" y="${ty - transitionHeight/2}" width="${transitionWidth}" height="${transitionHeight}" fill="#3498db" stroke="#2c3e50" stroke-width="2" rx="4"/>`;
            
            allXs.push(pos.x - transitionWidth/2, pos.x + transitionWidth/2);
            allYs.push(ty - transitionHeight/2, ty + transitionHeight/2);
            
            // Split long labels into multiple lines
            const maxChars = 6;
            const label = pos.label;
            if (label.length > maxChars) {
                const words = label.split(/\s+/);
                let lines = [];
                let currentLine = '';
                
                words.forEach(word => {
                    if ((currentLine + word).length > maxChars && currentLine.length > 0) {
                        lines.push(currentLine.trim());
                        currentLine = word + ' ';
                    } else {
                        currentLine += word + ' ';
                    }
                });
                if (currentLine.trim()) lines.push(currentLine.trim());
                
                const lineHeight = 12;
                const startY = ty - (lines.length - 1) * lineHeight / 2;
                lines.forEach((line, i) => {
                    svgContent += `<text x="${pos.x}" y="${startY + i * lineHeight}" text-anchor="middle" font-size="10" font-weight="bold" fill="white">${line}</text>`;
                });
            } else {
                svgContent += `<text x="${pos.x}" y="${ty + 5}" text-anchor="middle" font-size="11" font-weight="bold" fill="white">${label}</text>`;
            }
        });
        
        // Calculate final SVG dimensions based on actual content
        const minX = Math.min(...allXs, 20) - 20;
        const maxX = Math.max(...allXs) + 20;
        const minY = Math.min(...allYs, 0) - 20;
        const maxY = Math.max(...allYs) + 20;
        
        const svgWidth = maxX - minX;
        const svgHeight = maxY - minY;
        
        // Create final SVG with viewBox to show all content
        const svg = `<svg width="${svgWidth}" height="${svgHeight}" viewBox="${minX} ${minY} ${svgWidth} ${svgHeight}" xmlns="http://www.w3.org/2000/svg">${svgContent}</svg>`;

        container.innerHTML = svg;
} catch (err) {
    console.error('Error drawing Petri net:', err);
    if (container) {
        container.innerHTML = `<p class="text-center" style="color: var(--color-danger);">Error al generar el diagrama de la Red de Petri. Revisa la consola para más detalles.</p>`;
    }
}
}

// Disable same state selection (prevent state_a == state_b)
// Use document-level event delegation (survives any DOM replacement)
if (!window._stateValidationConfigured) {
    window._stateValidationConfigured = true;
    
    function applyStateValidation() {
        const stateAInputs = document.querySelectorAll('input[name="state_a"]');
        const stateBInputs = document.querySelectorAll('input[name="state_b"]');
        const selectedA = document.querySelector('input[name="state_a"]:checked');
        const selectedB = document.querySelector('input[name="state_b"]:checked');
        
        // Re-enable all first
        stateBInputs.forEach(function(radioB) {
            const label = radioB.closest('label');
            radioB.disabled = false;
            if (label) {
                label.style.opacity = '1';
                label.style.cursor = 'pointer';
            }
        });
        
        stateAInputs.forEach(function(radioA) {
            const label = radioA.closest('label');
            radioA.disabled = false;
            if (label) {
                label.style.opacity = '1';
                label.style.cursor = 'pointer';
            }
        });
        
        // If state_a is selected, disable matching state_b
        if (selectedA) {
            const valueA = selectedA.value;
            stateBInputs.forEach(function(radioB) {
                if (radioB.value === valueA) {
                    radioB.disabled = true;
                    radioB.checked = false;
                    const label = radioB.closest('label');
                    if (label) {
                        label.style.opacity = '0.4';
                        label.style.cursor = 'not-allowed';
                    }
                }
            });
        }
        
        // If state_b is selected, disable matching state_a
        if (selectedB) {
            const valueB = selectedB.value;
            stateAInputs.forEach(function(radioA) {
                if (radioA.value === valueB) {
                    radioA.disabled = true;
                    radioA.checked = false;
                    const label = radioA.closest('label');
                    if (label) {
                        label.style.opacity = '0.4';
                        label.style.cursor = 'not-allowed';
                    }
                }
            });
        }
    }
    
    // Event delegation on document level - survives any DOM replacement
    document.addEventListener('change', function(e) {
        if (e.target.name === 'state_a' || e.target.name === 'state_b') {
            applyStateValidation();
        }
    });
    
    document.addEventListener('click', function(e) {
        if (e.target.name === 'state_a' || e.target.name === 'state_b') {
            applyStateValidation();
        }
    });
}

// Function kept for compatibility but now just applies validation (listeners already on document)
function setupStateValidation() {
    // Just apply validation for currently selected states
    const selectedA = document.querySelector('input[name="state_a"]:checked');
    const selectedB = document.querySelector('input[name="state_b"]:checked');
    if (selectedA || selectedB) {
        // Trigger the validation logic
        const event = new Event('change', { bubbles: true });
        if (selectedA) selectedA.dispatchEvent(event);
        else if (selectedB) selectedB.dispatchEvent(event);
    }
}

// Initialize state validation on page load (called from ajax-handlers.js after DOM ready)
// setupStateValidation();

// Delete connection and remove row from table
function deleteConnectionRow(button, connectionId) {
    if (!confirm('¿Estás seguro de que deseas borrar esta conexión?')) {
        return;
    }
    
    const row = button.closest('tr');
    
    deleteConnection(connectionId, function(data) {
        // Remove the row from the table
        row.remove();
        
        // Check if table is now empty
        const tbody = document.querySelector('#connections-table tbody');
        if (tbody && tbody.children.length === 0) {
            // Replace table with "no connections" message
            const table = document.getElementById('connections-table');
            if (table) {
                const noConnMsg = document.createElement('p');
                noConnMsg.className = 'text-center';
                noConnMsg.textContent = 'No hay conexiones definidas para este carácter.';
                table.replaceWith(noConnMsg);

                // Rebuild connection form UI based on current substrates value
                const substratesInput = document.getElementById('substrates-input');
                const currentSubstrates = substratesInput ? parseInt(substratesInput.value || 0) : 0;
                if (typeof reloadSubstrateSelectors === 'function') {
                    reloadSubstrateSelectors(currentSubstrates);
                }
                
                // Redraw diagram with available genes/transitions
                drawPetriNet();
            }
        } else {
            // Redraw Petri net
            drawPetriNet();
        }
    });
}

// Draw Petri net on page load if connections are visible
document.addEventListener('DOMContentLoaded', function() {
    const connectionsView = document.getElementById('connections-view');
    if (connectionsView && connectionsView.style.display !== 'none') {
        drawPetriNet();
    }
});
</script>

