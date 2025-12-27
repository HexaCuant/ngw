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

// Handle add allele form via AJAX
const addAlleleForm = document.getElementById('add-allele-form');
if (addAlleleForm) {
    addAlleleForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.set('char_action', 'add_allele_ajax');
        
        fetch('index.php?option=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Alelo añadido', 'success');
                
                // Add row to table
                const tbody = document.querySelector('#genes-view table tbody');
                if (tbody) {
                    // Remove "no alleles" row if it exists
                    const emptyRow = tbody.querySelector('td[colspan]');
                    if (emptyRow) {
                        emptyRow.closest('tr').remove();
                    }
                    
                    const allele = data.allele;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${allele.id}</td>
                        <td>${allele.name}</td>
                        <td>${allele.value !== null ? allele.value : ''}</td>
                        <td>${allele.dominance !== null ? allele.dominance : ''}</td>
                        <td>${allele.additive == 1 ? 'Sí' : 'No'}</td>
                        <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                            <td>
                                <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" class="delete-allele-form">
                                    <input type="hidden" name="char_action" value="remove_allele">
                                    <input type="hidden" name="allele_id" value="${allele.id}">
                                    <button type="submit" class="btn-danger btn-small">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    `;
                    tbody.appendChild(row);
                    
                    // Attach event listener to new delete button
                    const newForm = row.querySelector('.delete-allele-form');
                    if (newForm) {
                        newForm.addEventListener('submit', function(e) {
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
                                    
                                        const tbody = document.querySelector('#genes-view table tbody');
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
                    }
                }
                
                // Reset form
                addAlleleForm.reset();
            } else {
                showNotification(data.error || 'Error al añadir alelo', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
    });
}

// Handle create gene form via AJAX
const createGeneForm = document.getElementById('create-gene-form');
if (createGeneForm) {
    createGeneForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validate that at least one gene_type checkbox is selected
        const anyTypeChecked = this.querySelectorAll('input[name="gene_type[]"]:checked').length > 0;
        if (!anyTypeChecked) {
            showNotification('Selecciona al menos un tipo de cromosoma (X, Y, A o B)', 'error');
            return;
        }

        const formData = new FormData(this);
        formData.set('char_action', 'create_gene_ajax');
        
        fetch('index.php?option=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Gen creado', 'success');
                
                // Add row to genes table (create table if missing)
                const genesView = document.getElementById('genes-view');
                if (genesView) {
                    genesView.style.display = 'block';

                    let table = genesView.querySelector('table');
                    let tbody = table ? table.querySelector('tbody') : null;
                    if (!tbody) {
                        table = document.createElement('table');
                        table.innerHTML = `
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Cromosoma</th>
                                    <th>Posición</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        `;
                        genesView.appendChild(table);
                        tbody = table.querySelector('tbody');
                    }

                    // Remove placeholder row if exists
                    const emptyRow = tbody.querySelector('td[colspan]');
                    if (emptyRow) emptyRow.closest('tr').remove();

                    const gene = data.gene;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${gene.id}</td>
                        <td>${gene.name}</td>
                        <td>${gene.chromosome}</td>
                        <td>${gene.position}</td>
                        <td>
                            <button type="button" id="gene-toggle-${gene.id}" onclick="toggleGene(${gene.id}, this)" class="btn-primary btn-small">Abrir</button>
                            <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                <button type="button" onclick="deleteGene(${gene.id}, '${gene.name.replace(/'/g, "\\'")}')" class="btn-danger btn-small">Borrar</button>
                            <?php endif; ?>
                        </td>
                    `;
                    tbody.appendChild(row);
                }

                    // Reset and hide form
                    createGeneForm.reset();
                    const formContainer = document.getElementById('create-gene-form-container');
                    if (formContainer) formContainer.style.display = 'none';
            } else {
                showNotification(data.error || 'Error al crear gen', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
    });
}
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
        // If there are connections, show toast and blur
        if (this.dataset.hasConnections === '1') {
            showDisabledToast();
            this.blur();
        }
    });

    // Helper to show a single toast for disabled interaction (rate-limited)
    const disabledMsg = 'No se puede modificar el número de sustratos porque ya existen conexiones definidas para este carácter.';
    let lastDisabledToast = 0;
    function showDisabledToast() {
        const now = Date.now();
        if (now - lastDisabledToast > 800) {
            showNotification(disabledMsg, 'warning');
            lastDisabledToast = now;
        }
    }

    // Always attach click handler but only react when dataset indicates connections
    newInput.addEventListener('click', function(e) {
        if (this.dataset.hasConnections === '1') {
            showDisabledToast();
            e.preventDefault();
            this.blur();
        }
    });

    newInput.addEventListener('input', function() {
        clearTimeout(substratesTimeout);
        
        substratesTimeout = setTimeout(function() {
            // If the input is flagged as having connections, block the update and show a single toast
            if (newInput.dataset.hasConnections === '1') {
                showDisabledToast();
                newInput.value = prevSubstratesValue;
                return;
            }

            const value = parseInt(newInput.value);
            if (!isNaN(value) && value >= 0 && characterId > 0) {
                updateSubstrates(characterId, value, function(data) {
                    // Success: update prev value and UI
                    prevSubstratesValue = data.substrates;
                    reloadSubstrateSelectors(data.substrates);
                }, function(err) {
                    // Error: revert to previous value to make it obvious it didn't change
                    newInput.value = prevSubstratesValue;
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
            }, function(err) {
                newInput.value = prevSubstratesValue;
            });
        }
    });
}

// Handle add connection form via AJAX
const addConnectionForm = document.getElementById('add-connection-form');
if (addConnectionForm) {
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

                // Mark that there are connections now so substrates edits are blocked
                const substratesInput = document.getElementById('substrates-input');
                if (substratesInput) substratesInput.dataset.hasConnections = '1';
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
            btn.textContent = isVisible ? 'Ver Conexiones' : 'Ocultar Conexiones';
        }
        
        // Draw Petri net if connections are visible
        if (!isVisible) {
            setTimeout(drawPetriNet, 100);
        }
    }
}

/**
 * Draw Petri Net diagram based on connections
 */
function drawPetriNet() {
    const container = document.getElementById('petri-net-diagram');
    const table = document.getElementById('connections-table');
    
    if (!container || !table) return;
    
    // Extract connections from table
    const connections = [];
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const cells = row.cells;
        if (cells && cells.length >= 3) {
            const stateA = cells[0].textContent.replace('S', '');
            const transition = cells[1].textContent;
            const stateB = cells[2].textContent.replace('S', '');
            connections.push({ stateA: parseInt(stateA), transition, stateB: parseInt(stateB) });
        }
    });
    
    if (connections.length === 0) return;
    
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
    const verticalSpacing = 120;
    const marginX = 60;
    const marginY = 60;
    
    const statePositions = {};
    const transitionPositions = {};
    
    if (isLinear) {
        // Linear layout: S0 -> T1 -> S1 -> T2 -> S2
        let currentX = marginX;
        const y = marginY + 80;
        
        // Sort connections by sequence
        const sortedConns = [...connections].sort((a, b) => a.stateA - b.stateA);
        const processedStates = new Set();
        
        sortedConns.forEach((conn, index) => {
            // Add state A if not processed
            if (!processedStates.has(conn.stateA)) {
                statePositions[conn.stateA] = { x: currentX, y: y };
                processedStates.add(conn.stateA);
                currentX += horizontalSpacing / 2;
            }
            
            // Add transition
            const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
            transitionPositions[key] = {
                x: currentX,
                y: y,
                label: conn.transition
            };
            currentX += horizontalSpacing / 2;
            
            // Add state B if not processed
            if (!processedStates.has(conn.stateB)) {
                statePositions[conn.stateB] = { x: currentX, y: y };
                processedStates.add(conn.stateB);
                currentX += horizontalSpacing / 2;
            }
        });
    } else {
        // Complex layout: states on top, transitions below
        stateArray.forEach((state, index) => {
            statePositions[state] = {
                x: marginX + index * horizontalSpacing,
                y: marginY + 50
            };
        });
        
        // Group transitions by their position
        connections.forEach((conn, index) => {
            const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
            const avgX = (statePositions[conn.stateA].x + statePositions[conn.stateB].x) / 2;
            const layer = Math.floor(index / Math.max(1, Math.ceil(connections.length / 3)));
            transitionPositions[key] = {
                x: avgX,
                y: marginY + 150 + layer * verticalSpacing,
                label: conn.transition
            };
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

        // Calculate SVG size (ensure finite values)
        let maxX = Math.max(...Object.values(statePositions).map(s => s.x)) + marginX;
        let maxY = Math.max(
            ...Object.values(statePositions).map(s => s.y),
            ...Object.values(transitionPositions).map(t => t.y)
        ) + marginY + 50;

        if (!isFinite(maxX) || !isFinite(maxY) || maxX <= 0 || maxY <= 0) {
            maxX = Math.max(800, marginX + 3 * horizontalSpacing);
            maxY = Math.max(400, marginY + 3 * verticalSpacing);
        }

        // Create SVG
        let svg = `<svg width="${maxX}" height="${maxY}" xmlns="http://www.w3.org/2000/svg">`;

        // Define arrowhead marker
        svg += `<defs><marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><polygon points="0 0, 10 3, 0 6" fill="#666" /></marker></defs>`;
    
    // Draw connections (arcs)
    connections.forEach(conn => {
        const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
        const stateAPos = statePositions[conn.stateA];
        const stateBPos = statePositions[conn.stateB];
        const transPos = transitionPositions[key];
        
        if (isLinear) {
            // Horizontal arrows for linear layout
            const y = stateAPos.y;
            
            // Arc from state A to transition
            const x1 = stateAPos.x + placeRadius;
            const x2 = transPos.x - transitionWidth/2;
            svg += `<line x1="${x1}" y1="${y}" x2="${x2}" y2="${y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
            
            // Arc from transition to state B
            const x3 = transPos.x + transitionWidth/2;
            const x4 = stateBPos.x - placeRadius;
            svg += `<line x1="${x3}" y1="${y}" x2="${x4}" y2="${y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
        } else {
            // Curved arrows for complex layout
            // Arc from state A to transition
            svg += `<line x1="${stateAPos.x}" y1="${stateAPos.y + placeRadius}" x2="${transPos.x}" y2="${transPos.y - transitionHeight/2}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
            
            // Arc from transition to state B
            svg += `<line x1="${transPos.x}" y1="${transPos.y + transitionHeight/2}" x2="${stateBPos.x}" y2="${stateBPos.y - placeRadius}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
        }
    });
    
    // Draw states (places - circles)
    stateArray.forEach(state => {
        const pos = statePositions[state];
        svg += `<circle cx="${pos.x}" cy="${pos.y}" r="${placeRadius}" fill="white" stroke="#2c3e50" stroke-width="2"/>`;
        svg += `<text x="${pos.x}" y="${pos.y + 5}" text-anchor="middle" font-size="14" font-weight="bold" fill="#2c3e50">S${state}</text>`;
    });
    
    // Draw transitions (rectangles)
    Object.entries(transitionPositions).forEach(([key, pos]) => {
        svg += `<rect x="${pos.x - transitionWidth/2}" y="${pos.y - transitionHeight/2}" width="${transitionWidth}" height="${transitionHeight}" fill="#3498db" stroke="#2c3e50" stroke-width="2" rx="4"/>`;
        
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
            const startY = pos.y - (lines.length - 1) * lineHeight / 2;
            lines.forEach((line, i) => {
                svg += `<text x="${pos.x}" y="${startY + i * lineHeight}" text-anchor="middle" font-size="10" font-weight="bold" fill="white">${line}</text>`;
            });
        } else {
            svg += `<text x="${pos.x}" y="${pos.y + 5}" text-anchor="middle" font-size="11" font-weight="bold" fill="white">${label}</text>`;
        }
    });
    
    svg += '</svg>';

    container.innerHTML = svg;
} catch (err) {
    console.error('Error drawing Petri net:', err);
    if (container) {
        container.innerHTML = `<p class="text-center" style="color: var(--color-danger);">Error al generar el diagrama de la Red de Petri. Revisa la consola para más detalles.</p>`;
    }
}
}

// Disable same state selection (prevent state_a == state_b)
function setupStateValidation() {
    const stateAInputs = document.querySelectorAll('input[name="state_a"]');
    const stateBInputs = document.querySelectorAll('input[name="state_b"]');
    
    if (stateAInputs.length === 0 || stateBInputs.length === 0) return;
    
    // When state_a is selected, disable the same value in state_b
    stateAInputs.forEach(function(radioA) {
        radioA.addEventListener('change', function() {
            if (this.checked) {
                const selectedValue = this.value;
                
                // Re-enable all state_b inputs first
                stateBInputs.forEach(function(radioB) {
                    const label = radioB.closest('label');
                    radioB.disabled = false;
                    if (label) {
                        label.style.opacity = '1';
                        label.style.cursor = 'pointer';
                    }
                });
                
                // Disable the matching state_b
                stateBInputs.forEach(function(radioB) {
                    if (radioB.value === selectedValue) {
                        radioB.disabled = true;
                        radioB.checked = false; // Uncheck if it was selected
                        const label = radioB.closest('label');
                        if (label) {
                            label.style.opacity = '0.4';
                            label.style.cursor = 'not-allowed';
                        }
                    }
                });
            }
        });
    });
    
    // When state_b is selected, disable the same value in state_a
    stateBInputs.forEach(function(radioB) {
        radioB.addEventListener('change', function() {
            if (this.checked) {
                const selectedValue = this.value;
                
                // Re-enable all state_a inputs first
                stateAInputs.forEach(function(radioA) {
                    const label = radioA.closest('label');
                    radioA.disabled = false;
                    if (label) {
                        label.style.opacity = '1';
                        label.style.cursor = 'pointer';
                    }
                });
                
                // Disable the matching state_a
                stateAInputs.forEach(function(radioA) {
                    if (radioA.value === selectedValue) {
                        radioA.disabled = true;
                        radioA.checked = false; // Uncheck if it was selected
                        const label = radioA.closest('label');
                        if (label) {
                            label.style.opacity = '0.4';
                            label.style.cursor = 'not-allowed';
                        }
                    }
                });
            }
        });
    });
}

// Initialize state validation on page load
setupStateValidation();

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
                
                // Clear diagram and show placeholder message (do not hide container)
                const diagram = document.getElementById('petri-net-diagram');
                if (diagram) {
                    diagram.innerHTML = '<p class="text-center" style="color: var(--color-text-secondary);">No hay conexiones definidas para este carácter.</p>';
                }

                // Re-enable substrates input and remove server-side warning
                const substratesInput = document.getElementById('substrates-input');
                if (substratesInput) {
                    substratesInput.disabled = false;
                }
                const substratesWarning = document.getElementById('substrates-warning');
                if (substratesWarning) substratesWarning.remove();

                // Rebuild connection form UI based on current substrates value
                const currentSubstrates = substratesInput ? parseInt(substratesInput.value || 0) : 0;
                if (typeof reloadSubstrateSelectors === 'function') {
                    reloadSubstrateSelectors(currentSubstrates);
                }
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
