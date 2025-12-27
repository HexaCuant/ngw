<?php
/**
 * Characters page template
 * @var \Ngw\Models\Character $characterModel
 * @var \Ngw\Auth\SessionManager $session
 * @var \Ngw\Models\Project $projectModel
 * @var \Ngw\Database\Database $db
 */

$userId = $session->getUserId();

// Get assigned teacher from session, or fetch from DB if not set (for existing sessions)
$assignedTeacherId = $session->getAssignedTeacherId();
if ($assignedTeacherId === null && $session->isStudent() && $db) {
    $userData = $db->fetchOne("SELECT assigned_teacher_id FROM users WHERE id = :id", ['id' => $userId]);
    if ($userData && $userData['assigned_teacher_id']) {
        $assignedTeacherId = (int) $userData['assigned_teacher_id'];
        // Update session for future requests
        $session->set('assigned_teacher_id', $assignedTeacherId);
    }
}

$characters = $characterModel->getAvailableCharacters($userId, $assignedTeacherId);
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
    $showGenes = $session->get('show_genes', false);
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
            <?php include __DIR__ . '/../partials/character_details.php'; ?>
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

// toggleGenesView and toggleConnectionsView are defined in ajax-handlers.js

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

// toggleConnectionsView, drawEmptyDiagram and drawPetriNet are defined in ajax-handlers.js

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

