/**
 * AJAX Handlers for GenWeb NG
 * Provides smooth updates without full page reloads
 */

/**
 * Show notification message
 */
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? 'var(--color-success, #28a745)' : 'var(--color-danger, #dc3545)'};
        color: white;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease-out;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/**
 * Flash a brief highlight on the substrates input to visually draw attention
 */
function flashInputHighlight() {
    const input = document.getElementById('substrates-input');
    if (!input) return;
    input.classList.add('highlight-toast');
    // Ensure class is removed after animation
    setTimeout(() => input.classList.remove('highlight-toast'), 1000);
}

/**
 * Escape HTML special characters to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Toggle genes view visibility
 */
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

/**
 * Toggle connections view visibility
 */
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
    const horizontalSpacing = 75;
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
        let geneName = '';
        let nextNode = input.nextSibling;
        while (nextNode) {
            if (nextNode.nodeType === Node.TEXT_NODE) {
                geneName = nextNode.textContent.trim();
                if (geneName) break;
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
        if (i === 0) return true;
        return arr[i-1].stateB === c.stateA;
    });
    
    // Calculate layout
    const placeRadius = 25;
    const transitionWidth = 40;
    const transitionHeight = 60;
    const horizontalSpacing = 90;
    const verticalSpacing = 100;
    const marginX = 60;
    const marginY = 100;
    
    const statePositions = {};
    const transitionPositions = {};
    
    if (isLinear) {
        // Linear layout
        let currentX = marginX;
        const y = marginY;
        
        const sortedConns = [...connections].sort((a, b) => a.stateA - b.stateA);
        const processedStates = new Set();
        
        sortedConns.forEach((conn, index) => {
            if (!processedStates.has(conn.stateA)) {
                statePositions[conn.stateA] = { x: currentX, y: y };
                processedStates.add(conn.stateA);
                currentX += horizontalSpacing;
            }
            
            const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
            transitionPositions[key] = {
                x: currentX,
                y: y,
                label: conn.transition
            };
            currentX += horizontalSpacing;
            
            if (!processedStates.has(conn.stateB)) {
                statePositions[conn.stateB] = { x: currentX, y: y };
                processedStates.add(conn.stateB);
                currentX += horizontalSpacing;
            }
        });
    } else {
        // Complex layout
        const stateY = marginY;
        const stateSpacing = horizontalSpacing * 2;
        stateArray.forEach((state, index) => {
            statePositions[state] = {
                x: marginX + index * stateSpacing,
                y: stateY
            };
        });
        
        // Group connections by state pair
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
                const conn = pairConns[0];
                const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
                transitionPositions[key] = {
                    x: avgX,
                    y: stateY,
                    label: conn.transition
                };
            } else {
                const isOdd = numGenes % 2 === 1;
                
                pairConns.forEach((conn, index) => {
                    const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
                    let yOffset;
                    
                    if (isOdd) {
                        const centerIndex = Math.floor(numGenes / 2);
                        if (index === centerIndex) {
                            yOffset = 0;
                        } else if (index < centerIndex) {
                            const layer = centerIndex - index;
                            yOffset = -layer * verticalSpacing;
                        } else {
                            const layer = index - centerIndex;
                            yOffset = layer * verticalSpacing;
                        }
                    } else {
                        const halfPoint = numGenes / 2;
                        if (index < halfPoint) {
                            const layer = halfPoint - index;
                            yOffset = -layer * verticalSpacing;
                        } else {
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

        // Defensive fallback
        if (Object.keys(statePositions).length === 0) {
            const uniqueStates = Array.from(new Set(connections.flatMap(c => [c.stateA, c.stateB]))).sort((a,b) => a-b);
            uniqueStates.forEach((s, i) => {
                statePositions[s] = { x: marginX + i * horizontalSpacing, y: marginY + 50 };
            });
        }

        if (Object.keys(transitionPositions).length === 0) {
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

        // Build SVG content
        let svgContent = '';
        
        svgContent += `<defs><marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto"><polygon points="0 0, 10 3, 0 6" fill="#666" /></marker></defs>`;
    
        // Draw genes that are NOT in connections at the top
        if (genesNotInConnections.length > 0) {
            svgContent += `<text x="20" y="25" font-size="12" font-weight="bold" fill="#666">Genes sin conexiones:</text>`;
            genesNotInConnections.forEach((geneName, index) => {
                const x = marginX + (index % 4) * 150;
                const y = 60 + Math.floor(index / 4) * 100;
                
                svgContent += `<rect x="${x - transitionWidth/2}" y="${y - transitionHeight/2}" width="${transitionWidth}" height="${transitionHeight}" fill="#e8f4f8" stroke="#3498db" stroke-width="2" stroke-dasharray="5,5" rx="4"/>`;
                svgContent += `<text x="${x}" y="${y + 5}" text-anchor="middle" font-size="11" font-weight="bold" fill="#2c3e50">${geneName}</text>`;
            });
        }
    
        let allXs = [];
        let allYs = [];
    
        // Draw connections (arcs)
        connections.forEach(conn => {
            const key = `${conn.stateA}-${conn.transition}-${conn.stateB}`;
            const stateAPos = statePositions[conn.stateA];
            const stateBPos = statePositions[conn.stateB];
            const transPos = transitionPositions[key];
            
            if (isLinear) {
                const y = stateAPos.y + connectionOffsetY;
                
                const x1 = stateAPos.x + placeRadius;
                const x2 = transPos.x - transitionWidth/2;
                svgContent += `<line x1="${x1}" y1="${y}" x2="${x2}" y2="${y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
                
                const x3 = transPos.x + transitionWidth/2;
                const x4 = stateBPos.x - placeRadius;
                svgContent += `<line x1="${x3}" y1="${y}" x2="${x4}" y2="${y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
            } else {
                const stateAYOffset = connectionOffsetY + stateAPos.y;
                const stateBYOffset = connectionOffsetY + stateBPos.y;
                const transYOffset = connectionOffsetY + transPos.y;
                
                const trans_left_x = transPos.x - transitionWidth/2;
                const trans_right_x = transPos.x + transitionWidth/2;
                const trans_center_y = transYOffset;
                
                let stateA_x, stateA_y;
                
                const dx = trans_left_x - stateAPos.x;
                const dy = trans_center_y - stateAYOffset;
                const angle = Math.atan2(dy, dx);
                
                stateA_x = stateAPos.x + placeRadius * Math.cos(angle);
                stateA_y = stateAYOffset + placeRadius * Math.sin(angle);
                
                svgContent += `<line x1="${stateA_x}" y1="${stateA_y}" x2="${trans_left_x}" y2="${trans_center_y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
                
                let stateB_x, stateB_y;
                
                const dx2 = stateBPos.x - trans_right_x;
                const dy2 = stateBYOffset - trans_center_y;
                const angle2 = Math.atan2(dy2, dx2);
                
                stateB_x = stateBPos.x - placeRadius * Math.cos(angle2);
                stateB_y = stateBYOffset - placeRadius * Math.sin(angle2);
                
                svgContent += `<line x1="${trans_right_x}" y1="${trans_center_y}" x2="${stateB_x}" y2="${stateB_y}" stroke="#666" stroke-width="2" marker-end="url(#arrowhead)"/>`;
                
                allXs.push(stateA_x, trans_left_x, trans_right_x, stateB_x);
                allYs.push(stateA_y, trans_center_y, stateB_y);
            }
        });
        
        // Draw states (circles)
        stateArray.forEach(state => {
            const pos = statePositions[state];
            const cy = pos.y + connectionOffsetY;
            svgContent += `<circle cx="${pos.x}" cy="${cy}" r="${placeRadius}" fill="white" stroke="#2c3e50" stroke-width="2"/>`;
            svgContent += `<text x="${pos.x}" y="${cy + 5}" text-anchor="middle" font-size="14" font-weight="bold" fill="#2c3e50">S${state}</text>`;
            allXs.push(pos.x - placeRadius, pos.x + placeRadius);
            allYs.push(cy - placeRadius, cy + placeRadius);
        });
        
        // Draw transitions (rectangles)
        Object.entries(transitionPositions).forEach(([key, pos]) => {
            const ty = pos.y + connectionOffsetY;
            svgContent += `<rect x="${pos.x - transitionWidth/2}" y="${ty - transitionHeight/2}" width="${transitionWidth}" height="${transitionHeight}" fill="#3498db" stroke="#2c3e50" stroke-width="2" rx="4"/>`;
            
            allXs.push(pos.x - transitionWidth/2, pos.x + transitionWidth/2);
            allYs.push(ty - transitionHeight/2, ty + transitionHeight/2);
            
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
        
        // Calculate final SVG dimensions
        const minX = Math.min(...allXs, 20) - 20;
        const maxX = Math.max(...allXs) + 20;
        const minY = Math.min(...allYs, 0) - 20;
        const maxY = Math.max(...allYs) + 20;
        
        const svgWidth = maxX - minX;
        const svgHeight = maxY - minY;
        
        const svg = `<svg width="${svgWidth}" height="${svgHeight}" viewBox="${minX} ${minY} ${svgWidth} ${svgHeight}" xmlns="http://www.w3.org/2000/svg">${svgContent}</svg>`;

        container.innerHTML = svg;
    } catch (err) {
        console.error('Error drawing Petri net:', err);
        if (container) {
            container.innerHTML = `<p class="text-center" style="color: var(--color-danger);">Error al generar el diagrama de la Red de Petri. Revisa la consola para más detalles.</p>`;
        }
    }
}

/**
 * Setup add-allele form handler (idempotent)
 */
function setupAddAlleleHandler() {
    const addAlleleForm = document.getElementById('add-allele-form');
    if (!addAlleleForm) return;

    // Clone to remove old listeners
    const formClone = addAlleleForm.cloneNode(true);
    addAlleleForm.parentNode.replaceChild(formClone, addAlleleForm);

    formClone.addEventListener('submit', function(e) {
        e.preventDefault();
        console.debug('addAllele form submit');

        const formData = new FormData(this);
        formData.set('char_action', 'add_allele_ajax');

        fetch('index.php?option=1', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.debug('addAllele success', data);
                    showNotification('Alelo añadido', 'success');

                    // Find geneId to refresh the section
                    const section = document.getElementById('alleles-section');
                    let geneId = section ? parseInt(section.getAttribute('data-gene-id') || '0', 10) : 0;
                    if (!geneId) {
                        const parent = formClone.closest('[data-gene-id]');
                        if (parent) geneId = parseInt(parent.getAttribute('data-gene-id') || '0', 10);
                    }

                    if (geneId) {
                        console.debug('addAllele: refreshing alleles section for gene', geneId);
                        openGene(geneId, true);
                    } else {
                        console.debug('addAllele: geneId not found, skipping refresh');
                    }

                    formClone.reset();
                } else {
                    showNotification(data.error || 'Error al añadir alelo', 'error');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                showNotification('Error de conexión', 'error');
            });
    });
}

/**
 * Initialize character UI dynamic behaviors (idempotent)
 */
function initializeCharacterUI() {
    // Setup state validation (safe to call multiple times)
    if (typeof setupStateValidation === 'function') {
        setupStateValidation();
    }

    // Set up substrates input handlers
    const substratesInput = document.getElementById('substrates-input');
    if (substratesInput) {
        // Clone to remove any old listeners
        const newInput = substratesInput.cloneNode(true);
        substratesInput.parentNode.replaceChild(newInput, substratesInput);

        let substratesTimeout;
        let prevSubstratesValue = parseInt(newInput.value || 0);

        newInput.addEventListener('focus', function() {
            prevSubstratesValue = parseInt(this.value || 0);
        });

        newInput.addEventListener('input', function() {
            clearTimeout(substratesTimeout);
            substratesTimeout = setTimeout(() => {
                const value = parseInt(newInput.value);
                const characterId = window._activeCharacterId || 0;
                if (!isNaN(value) && value >= 0 && characterId > 0) {
                    updateSubstrates(characterId, value, function(data) {
                        prevSubstratesValue = data.substrates;
                        reloadSubstrateSelectors(data.substrates);
                        // Remove deleted connections from table
                        if (data.deleted_connections && data.deleted_connections.length > 0) {
                            data.deleted_connections.forEach(function(connId) {
                                const row = document.querySelector(`tr[data-connection-id="${connId}"]`);
                                if (row) row.remove();
                            });
                            // Redraw Petri net
                            if (typeof drawPetriNet === 'function') drawPetriNet();
                        }
                    }, function(err) {
                        newInput.value = prevSubstratesValue;
                        if (typeof flashInputHighlight === 'function') flashInputHighlight();
                    });
                }
            }, 800);
        });

        newInput.addEventListener('blur', function() {
            clearTimeout(substratesTimeout);
            const value = parseInt(newInput.value);
            const characterId = window._activeCharacterId || 0;
            if (!isNaN(value) && value >= 0 && characterId > 0) {
                updateSubstrates(characterId, value, function(data) {
                    prevSubstratesValue = data.substrates;
                    reloadSubstrateSelectors(data.substrates);
                    // Remove deleted connections from table
                    if (data.deleted_connections && data.deleted_connections.length > 0) {
                        data.deleted_connections.forEach(function(connId) {
                            const row = document.querySelector(`tr[data-connection-id="${connId}"]`);
                            if (row) row.remove();
                        });
                        // Redraw Petri net
                        if (typeof drawPetriNet === 'function') drawPetriNet();
                    }
                }, function(err) {
                    newInput.value = prevSubstratesValue;
                    if (typeof flashInputHighlight === 'function') flashInputHighlight();
                });
            }
        });
    }

    // Set up add-connection form handler (use flag to prevent duplicate listeners)
    const addConnectionForm = document.getElementById('add-connection-form');
    if (addConnectionForm && !addConnectionForm._listenerAttached) {
        addConnectionForm._listenerAttached = true;

        addConnectionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const characterId = window._activeCharacterId || 0;
            const stateA = document.querySelector('input[name="state_a"]:checked')?.value;
            const transition = document.querySelector('input[name="transition"]:checked')?.value;
            const stateB = document.querySelector('input[name="state_b"]:checked')?.value;

            console.debug('Submitting new connection', { characterId, stateA, transition, stateB });

            if (stateA !== undefined && transition && stateB !== undefined && characterId > 0) {
                addConnection(characterId, parseInt(stateA), parseInt(transition), parseInt(stateB), function(data) {
                    console.debug('addConnection success callback data:', data);
                    addConnectionToTable(data.connection);
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
            } else {
                console.warn('Invalid connection form values', { characterId, stateA, transition, stateB });
            }
        });
    }

    // Set up add-allele form handler (idempotent)
    setupAddAlleleHandler();

    // Set up create-gene form handler (idempotent)
    setupCreateGeneHandler();

    // Initial reload of transitions if character is active
    reloadTransitionSelectors();
}

    /**
     * Open character via AJAX
     */
    function openCharacter(characterId) {
        const formData = new FormData();
        formData.append('char_action', 'open_character_ajax');
        formData.append('char_id', characterId);

        fetch('index.php?option=1', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Carácter abierto', 'success');
                    console.log('Character HTML received, length:', data.html ? data.html.length : 0);
                    console.log('HTML contains connections-view:', data.html ? data.html.includes('connections-view') : false);
            
                    // Remove create form if exists
                    const createFormCard = document.querySelector('.column-right .card h3');
                    if (createFormCard && createFormCard.textContent.includes('Crear Nuevo Carácter')) {
                        createFormCard.closest('.card').remove();
                    }
            
                    // Remove any existing character details to avoid duplicates
                    const existingDetails = document.querySelectorAll('.column-right .card');
                    existingDetails.forEach(card => {
                        const h3 = card.querySelector('h3');
                        if (h3 && h3.textContent.includes('Detalles del Carácter:')) {
                            card.remove();
                        }
                    });
            
                    // Insert character details HTML
                    const columnRight = document.querySelector('.column-right');
                    if (columnRight && data.html) {
                        const detailsCard = document.createElement('div');
                        detailsCard.innerHTML = data.html;
                        // Insert ALL elements from the response (main card + connections card)
                        // We need to collect all children first since inserting modifies the collection
                        const childrenToInsert = Array.from(detailsCard.children);
                        // Insert in reverse order so first element ends up first
                        for (let i = childrenToInsert.length - 1; i >= 0; i--) {
                            columnRight.insertAdjacentElement('afterbegin', childrenToInsert[i]);
                        }
                        const inserted = columnRight.firstElementChild;

                        // Store active character id for handlers
                        window._activeCharacterId = characterId;
                        inserted.setAttribute('data-active-character-id', characterId);

                        // Attach event handler to create gene form if present
                        setupCreateGeneHandler();

                        // Initial reload of transitions
                        reloadTransitionSelectors();
                        
                        // Initialize character UI handlers (for AJAX-inserted HTML)
                        if (typeof initializeCharacterUI === 'function') {
                            initializeCharacterUI();
                        }
                    }
                } else {
                    showNotification(data.error || 'Error al abrir carácter', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error de conexión', 'error');
            });
    }

/**
 * Close character via AJAX
 */
function closeCharacter() {
    const formData = new FormData();
    formData.append('char_action', 'close_character_ajax');
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        // Try to parse as JSON and handle parse errors
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error. Response text:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        if (data.success) {
            showNotification('Carácter cerrado', 'success');
            
            // Remove character details card
            const allCards = document.querySelectorAll('.column-right .card');
            allCards.forEach(card => {
                const h3 = card.querySelector('h3');
                if (h3 && h3.textContent.includes('Detalles del Carácter:')) {
                    card.remove();
                }
            });

            // If the connections view is open, hide it and reset the toggle button and petri diagram
            const connectionsView = document.getElementById('connections-view');
            const toggleBtn = document.getElementById('toggle-connections-btn');
            if (connectionsView && connectionsView.style.display !== 'none') {
                connectionsView.style.display = 'none';
                if (toggleBtn) toggleBtn.textContent = 'Ver Conexiones';

                // Clear petri net diagram to avoid stale visual
                const diagram = document.getElementById('petri-net-diagram');
                if (diagram) {
                    diagram.innerHTML = '<p class="text-center" style="color: var(--color-text-secondary);">No hay conexiones definidas para este carácter.</p>';
                }
            }
            
            // Insert create form HTML
            const columnRight = document.querySelector('.column-right');
            if (columnRight && data.html) {
                // Insert the create form at the top so it appears above connections
                columnRight.insertAdjacentHTML('afterbegin', data.html);
                
                // Re-attach form submit handler
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
                                setTimeout(() => {
                                    window.location.href = 'index.php?option=1';
                                }, 500);
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
            }
        } else {
            // Show validation errors in a more readable format
            if (data.error) {
                // Split by newlines to check if we have multiple errors
                const errors = data.error.split('\n').filter(e => e.trim());
                
                let message;
                if (errors.length > 1) {
                    // Multiple errors - show as list
                    message = '<strong>No se puede cerrar el carácter:</strong><ul style="margin-top: 0.5rem; margin-bottom: 0; padding-left: 1.5rem;">';
                    errors.forEach(error => {
                        message += '<li style="margin-bottom: 0.5rem;">' + escapeHtml(error) + '</li>';
                    });
                    message += '</ul>';
                } else {
                    // Single error - show with strong text
                    message = '<strong>No se puede cerrar el carácter:</strong><p style="margin-top: 0.5rem; margin-bottom: 0;">' + escapeHtml(data.error) + '</p>';
                }
                
                // Add suggestion to delete instead
                message += '<p style="margin-top: 1rem; margin-bottom: 0; font-size: 0.9rem; color: #888; font-style: italic;"><small>💡 Si no necesitas este carácter, puedes borrarlo directamente sin necesidad de cerrarlo.</small></p>';
                
                // Create and show custom error dialog
                const modal = document.getElementById('global-confirm-modal');
                if (modal) {
                    const msgEl = document.getElementById('global-confirm-message');
                    const acceptBtn = document.getElementById('global-confirm-accept');
                    const cancelBtn = document.getElementById('global-confirm-cancel');
                    const backdrop = document.getElementById('global-confirm-backdrop');
                    
                    if (msgEl && acceptBtn && cancelBtn && backdrop) {
                        msgEl.innerHTML = message;
                        acceptBtn.style.display = 'none';
                        cancelBtn.textContent = 'Entendido';
                        
                        modal.style.display = 'flex';
                        
                        function cleanup() {
                            cancelBtn.removeEventListener('click', onClose);
                            backdrop.removeEventListener('click', onClose);
                            document.removeEventListener('keydown', onKey);
                            modal.style.display = 'none';
                            acceptBtn.style.display = 'block';
                            cancelBtn.textContent = 'Cancelar';
                        }
                        
                        function onClose() { cleanup(); }
                        function onKey(e) { if (e.key === 'Escape') onClose(); }
                        
                        cancelBtn.addEventListener('click', onClose);
                        backdrop.addEventListener('click', onClose);
                        document.addEventListener('keydown', onKey);
                    } else {
                        showNotification(data.error, 'error');
                    }
                } else {
                    showNotification(data.error, 'error');
                }
            } else {
                showNotification('Error al cerrar carácter', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Fetch error in closeCharacter:', error);
        showNotification('Error de conexión: ' + error.message, 'error');
    });
}

/**
 * Delete character via AJAX
 */
function deleteCharacter(characterId, charName) {
    confirmAction('¿Estás seguro de eliminar el carácter "' + charName + '"?\n\nEsta acción no se puede deshacer y eliminará todos los genes asociados.', 'Eliminar', 'Cancelar')
    .then(ok => {
        if (!ok) return;

        const formData = new FormData();
        formData.append('char_action', 'delete_character_ajax');
        formData.append('char_id', characterId);
        
        fetch('index.php?option=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Carácter eliminado', 'success');
                
                // If the deleted character was open, close it in the UI
                if (data.wasClosed) {
                    // Remove character details card
                    const allCards = document.querySelectorAll('.column-right .card');
                    allCards.forEach(card => {
                        const h3 = card.querySelector('h3');
                        if (h3 && h3.textContent.includes('Detalles del Carácter:')) {
                            card.remove();
                        }
                    });

                    // Hide connections view if open
                    const connectionsView = document.getElementById('connections-view');
                    const toggleBtn = document.getElementById('toggle-connections-btn');
                    if (connectionsView && connectionsView.style.display !== 'none') {
                        connectionsView.style.display = 'none';
                        if (toggleBtn) toggleBtn.textContent = 'Ver Conexiones';
                        
                        const diagram = document.getElementById('petri-net-diagram');
                        if (diagram) {
                            diagram.innerHTML = '<p class="text-center" style="color: var(--color-text-secondary);">No hay conexiones definidas para este carácter.</p>';
                        }
                    }
                    
                    // Insert create form HTML if provided
                    if (data.html) {
                        const columnRight = document.querySelector('.column-right');
                        if (columnRight) {
                            columnRight.insertAdjacentHTML('afterbegin', data.html);
                            
                            // Re-attach form submit handler
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
                                            setTimeout(() => {
                                                window.location.href = 'index.php?option=1';
                                            }, 500);
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
                        }
                    }
                }
                
                // Remove row from table
                const rows = document.querySelectorAll('table tbody tr');
                rows.forEach(row => {
                    const cells = row.cells;
                    if (cells && cells[0] && cells[0].textContent == characterId) {
                        row.remove();
                    }
                });
                
                // Check if table is empty
                const tbody = document.querySelector('table tbody');
                if (tbody && tbody.children.length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = '<td colspan="4" class="text-center">No hay caracteres disponibles</td>';
                    tbody.appendChild(emptyRow);
                }
            } else {
                showNotification(data.error || 'Error al eliminar carácter', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
    });
}

/**
 * Update character properties via AJAX
 */
function updateCharacterProps(charId, visible, isPublic) {
    const formData = new FormData();
    formData.append('char_action', 'update_props_ajax');
    formData.append('char_id', charId);
    formData.append('visible', visible);
    formData.append('public', isPublic);
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Propiedades actualizadas', 'success');
        } else {
            showNotification(data.error || 'Error al actualizar propiedades', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Open gene via AJAX
 */
function openGene(geneId, silent = false) {
    const formData = new FormData();
    formData.append('char_action', 'open_gene_ajax');
    formData.append('gene_id', geneId);
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (!silent) showNotification('Gen abierto', 'success');
            
            // Reset all gene buttons to 'Abrir'
            document.querySelectorAll('[id^="gene-toggle-"]').forEach(btn => {
                btn.textContent = 'Abrir';
                btn.className = 'btn-primary btn-small';
            });
            
            // Remove existing alleles section if any
            const existingAlleles = document.getElementById('alleles-section');
            if (existingAlleles) {
                existingAlleles.remove();
            }
            
            // Insert alleles HTML inside genes-view
            const genesView = document.getElementById('genes-view');
            if (genesView && data.html) {
                    const allelesDiv = document.createElement('div');
                    allelesDiv.innerHTML = data.html;
                    genesView.appendChild(allelesDiv.firstElementChild);

                    // Attach add-allele form handler if present
                    setupAddAlleleHandler();
                }

                // Change this gene's button to 'Cerrar'
                const btn = document.getElementById('gene-toggle-' + geneId);
                console.debug('openGene: setting button state for gene', geneId, 'found btn?', !!btn);
                if (btn) {
                    btn.textContent = 'Cerrar';
                    btn.className = 'btn-secondary btn-small';
                    console.debug('openGene: button updated to Cerrar for gene', geneId);
                }
        } else {
            showNotification(data.error || 'Error al abrir gen', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Close gene via AJAX
 */
function closeGene() {
    const formData = new FormData();
    formData.append('char_action', 'close_gene_ajax');
    
    return fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Gen cerrado', 'success');

            // Try to remove alleles section by id
            let allelesSection = document.getElementById('alleles-section');
            if (allelesSection) {
                allelesSection.remove();
                console.debug('closeGene: removed alleles-section by id');
            } else {
                // Fallback: search for headings 'Gen abierto' and remove their container
                const containers = document.querySelectorAll('#genes-view, .column-right');
                containers.forEach(container => {
                    const headings = container.querySelectorAll('h4');
                    headings.forEach(h => {
                        if (h.textContent && h.textContent.trim().startsWith('Gen abierto')) {
                            const card = h.closest('div');
                            if (card) {
                                card.remove();
                                console.debug('closeGene: removed alleles section by heading');
                            }
                        }
                    });
                });
            }

            // Ensure any add-allele form is removed
            const addAlleleForm = document.getElementById('add-allele-form');
            if (addAlleleForm) {
                if (addAlleleForm.closest('div')) addAlleleForm.closest('div').remove();
                else addAlleleForm.remove();
                console.debug('closeGene: removed add-allele-form');
            }

            // Reset all gene buttons to 'Abrir'
            document.querySelectorAll('[id^="gene-toggle-"]').forEach(btn => {
                btn.textContent = 'Abrir';
                btn.className = 'btn-primary btn-small';
            });
        } else {
            showNotification(data.error || 'Error al cerrar gen', 'error');
        }
        return data;
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
        throw error;
    });
}

/**
 * Delete gene via AJAX
 */
function deleteGene(geneId, geneName) {
    confirmAction('¿Estás seguro de eliminar el gen "' + geneName + '"?\n\nEsta acción no se puede deshacer.', 'Eliminar', 'Cancelar')
    .then(ok => {
        if (!ok) return;

        // If this gene is currently open, close it first to ensure UI cleanup
        const allelesSection = document.getElementById('alleles-section');
        const openGeneId = allelesSection ? parseInt(allelesSection.getAttribute('data-gene-id') || '0', 10) : 0;
        const needToClose = openGeneId === Number(geneId);

        const proceed = needToClose ? closeGene().catch(err => { console.debug('deleteGene: closeGene failed, proceeding anyway', err); }) : Promise.resolve();
        proceed.then(() => {
            const formData = new FormData();
            formData.append('char_action', 'delete_gene_ajax');
            formData.append('gene_id', geneId);
            
            return fetch('index.php?option=1', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                console.debug('deleteGene: raw response', text);
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('deleteGene: JSON parse error', e, 'text:', text);
                    showNotification('Error de servidor: respuesta inválida', 'error');
                    return;
                }
                return data;
            })
        }).then(data => {
            if (data && data.success) {
                showNotification('Gen eliminado', 'success');
                // Remove row from table
                const rows = document.querySelectorAll('#genes-view table tbody tr');
                rows.forEach(row => {
                    const cells = row.cells;
                    if (cells && cells[0] && cells[0].textContent == geneId) {
                        row.remove();
                    }
                });
                
                // Remove alleles panel if it belongs to the deleted gene
                const allelesSection2 = document.getElementById('alleles-section');
                if (allelesSection2) {
                    const openGeneId2 = parseInt(allelesSection2.getAttribute('data-gene-id') || '0', 10);
                    if (openGeneId2 === Number(geneId)) {
                        allelesSection2.remove();
                    }
                }

                // Fallback: remove any visible 'Gen abierto' sections in #genes-view
                const genesView = document.getElementById('genes-view');
                if (genesView) {
                    genesView.querySelectorAll('h4').forEach(h => {
                        if (h.textContent && h.textContent.trim().startsWith('Gen abierto')) {
                            const container = h.closest('div');
                            if (container) container.remove();
                        }
                    });
                    // Also clear the alleles table body if present, but only inside the alleles section
                    const allelesSection = document.getElementById('alleles-section');
                    if (allelesSection) {
                        const allelesTbody = allelesSection.querySelector('table tbody');
                        if (allelesTbody) allelesTbody.innerHTML = '';
                    }
                }

                // Check if table is empty
                const tbody = document.querySelector('#genes-view table tbody');
                if (tbody && tbody.children.length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = '<td colspan="5" class="text-center">No hay genes definidos</td>';
                    tbody.appendChild(emptyRow);
                }

                // Reload transition selectors in connection form
                reloadTransitionSelectors();
            } else {
                showNotification((data && data.error) || 'Error al eliminar gen', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
    });
}

/**
 * Update substrates via AJAX
 */
function updateSubstrates(characterId, substrates, onSuccess, onError) {
    const formData = new FormData();
    formData.append('char_action', 'update_substrates_ajax');
    formData.append('char_id', characterId);
    formData.append('substrates', substrates);
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.text(); // First get as text to debug
    })
    .then(text => {
        console.log('Raw response:', text); // Debug
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showNotification('Número de sustratos actualizado', 'success');
                if (onSuccess) onSuccess(data);
            } else {
                // Server returned a failure (e.g., cannot change because there are connections)
                showNotification(data.error || 'Error al actualizar sustratos', 'error');
                if (onError) onError(data);
            }
        } catch (e) {
            console.error('JSON parse error:', e, 'Response:', text);
            showNotification('Error: respuesta inválida del servidor', 'error');
            if (onError) onError({ error: 'Error: respuesta inválida del servidor' });
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showNotification('Error de conexión', 'error');
        if (onError) onError({ error: 'Error de conexión' });
    });
}

/**
 * Add connection via AJAX
 */
function addConnection(characterId, stateA, transition, stateB, onSuccess) {
    console.debug('addConnection called', { characterId, stateA, transition, stateB });
    const formData = new FormData();
    formData.append('char_action', 'add_connection_ajax');
    formData.append('char_id', characterId);
    formData.append('state_a', stateA);
    formData.append('transition', transition);
    formData.append('state_b', stateB);
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.debug('Raw response (addConnection):', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showNotification('Conexión creada correctamente', 'success');
                if (onSuccess) onSuccess(data);
            } else {
                showNotification(data.error || 'Error al crear conexión', 'error');
            }
        } catch (e) {
            console.error('JSON parse error (addConnection):', e, 'Response text:', text);
            showNotification('Error de conexión', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Delete connection via AJAX
 */
function deleteConnection(connectionId, onSuccess) {
    const formData = new FormData();
    formData.append('char_action', 'remove_connection_ajax');
    formData.append('connection_id', connectionId);
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log('Raw response (deleteConnection):', text);
        try {
            const data = JSON.parse(text);
            if (data.success) {
                showNotification('Conexión eliminada', 'success');
                if (onSuccess) onSuccess(data);
            } else {
                showNotification(data.error || 'Error al eliminar conexión', 'error');
            }
        } catch (e) {
            console.error('JSON parse error:', e);
            console.error('Response text:', text);
            showNotification('Error de conexión', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Setup create-gene form handler (idempotent)
 */
function setupCreateGeneHandler() {
    const createGeneForm = document.getElementById('create-gene-form');
    if (!createGeneForm) return;

    // Clone to remove old listeners
    const formClone = createGeneForm.cloneNode(true);
    createGeneForm.parentNode.replaceChild(formClone, createGeneForm);

    formClone.addEventListener('submit', function(e) {
        e.preventDefault();
        const anyTypeChecked = this.querySelectorAll('input[name="gene_type[]"]:checked').length > 0;
        if (!anyTypeChecked) {
            showNotification('Selecciona al menos un tipo de cromosoma (X, Y, A o B)', 'error');
            return;
        }
        const formData = new FormData(this);
        formData.set('char_action', 'create_gene_ajax');
        fetch('index.php?option=1', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Gen creado', 'success');
                    const genesView = document.getElementById('genes-view');
                    if (genesView) genesView.style.display = 'block';

                    let table = genesView.querySelector('table');
                    let tbody = table ? table.querySelector('tbody') : null;
                    if (!tbody) {
                        table = document.createElement('table');
                        table.innerHTML = `<thead><tr><th>ID</th><th>Nombre</th><th>Cromosoma</th><th>Posición</th><th>Acciones</th></tr></thead><tbody></tbody>`;
                        genesView.appendChild(table);
                        tbody = table.querySelector('tbody');
                    }

                    const emptyRow = tbody.querySelector('td[colspan]');
                    if (emptyRow) emptyRow.closest('tr')?.remove();

                    const gene = data.gene;
                    const row = document.createElement('tr');
                    row.innerHTML = `<td>${gene.id}</td><td>${gene.name}</td><td>${gene.chromosome || ''}</td><td>${gene.position || ''}</td><td><button type="button" id="gene-toggle-${gene.id}" onclick="toggleGene(${gene.id}, this)" class="btn-primary btn-small">Abrir</button>
                                            <button type="button" onclick="deleteGene(${gene.id}, '${gene.name.replace(/'/g, "\\'")}')" class="btn-danger btn-small">Borrar</button></td>`;
                    tbody.appendChild(row);
                    formClone.reset();
                    const formContainer = document.getElementById('create-gene-form-container');
                    if (formContainer) formContainer.style.display = 'none';

                    // Reload transition selectors in connection form (which will redraw Petri net)
                    reloadTransitionSelectors();
                } else {
                    showNotification(data.error || 'Error al crear gen', 'error');
                }
            })
            .catch(error => { console.error('Error:', error); showNotification('Error de conexión', 'error'); });
    });
}

/**
 * Reload transition (gene) selectors in the connection form
 */
function reloadTransitionSelectors() {
    const characterId = window._activeCharacterId || 0;
    if (!characterId) {
        return;
    }

    // Ensure the container exists before proceeding
    const maxAttempts = 5;
    let attemptCount = 0;
    
    const tryFetch = () => {
        const container = document.getElementById('transition-container');
        if (!container && attemptCount < maxAttempts) {
            attemptCount++;
            setTimeout(tryFetch, 100);
            return;
        }
        
        if (!container) {
            return;
        }

        const formData = new FormData();
        formData.append('char_action', 'get_genes_ajax');
        formData.append('char_id', characterId);
        
        fetch('index.php?option=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.genes) {
                const container = document.getElementById('transition-container');
                if (!container) {
                    return;
                }

                let html = '';
                data.genes.forEach(gene => {
                    html += `
                        <label>
                            <input type="radio" name="transition" value="${gene.id}" required> ${gene.name}
                        </label>
                    `;
                });
                container.innerHTML = html;

            // Update the "no genes" warning if necessary
            let warning = document.getElementById('no-genes-warning');
            if (!warning) {
                // Try to find it by text if ID is missing (for backward compatibility)
                const pTags = document.querySelectorAll('#add-connection-form ~ p');
                pTags.forEach(p => {
                    if (p.textContent.includes('crear genes')) {
                        p.id = 'no-genes-warning';
                        warning = p;
                    }
                });
            }

            if (data.genes.length > 0) {
                if (warning) warning.style.display = 'none';
            } else {
                if (warning) {
                    warning.style.display = 'block';
                } else {
                    // Create it if it doesn't exist
                    const newWarning = document.createElement('p');
                    newWarning.id = 'no-genes-warning';
                    newWarning.className = 'text-center';
                    newWarning.style.color = 'var(--color-warning)';
                    newWarning.textContent = 'Primero debes crear genes para este carácter.';
                    document.getElementById('add-connection-form').after(newWarning);
                }
            }
            
            // Redraw Petri net after updating transition selectors
            if (typeof drawPetriNet === 'function') {
                drawPetriNet();
            }
            }
        })
        .catch(error => {
            console.error('Error loading genes:', error);
        });
    };
    
    // Start the fetch attempt
    tryFetch();
}

/**
 * Reload substrate selectors (radio buttons)
 */
function reloadSubstrateSelectors(numSubstrates) {
    const stateAContainer = document.getElementById('state-a-container');
    const stateBContainer = document.getElementById('state-b-container');
    
    if (!stateAContainer || !stateBContainer) return;
    
    let html = '';
    for (let i = 0; i < numSubstrates; i++) {
        html += `
            <label>
                <input type="radio" name="state_a" value="${i}" required> S${i}
            </label>
        `;
    }
    stateAContainer.innerHTML = html;
    
    html = '';
    for (let i = 0; i < numSubstrates; i++) {
        html += `
            <label>
                <input type="radio" name="state_b" value="${i}" required> S${i}
            </label>
        `;
    }
    stateBContainer.innerHTML = html;
    
    // Reset validation configuration flag before reconfiguring
    if (typeof _stateValidationConfigured !== 'undefined') {
        window._stateValidationConfigured = false;
    }
    
    // Re-apply state validation after reload (use setTimeout to ensure DOM is ready)
    if (typeof setupStateValidation === 'function') {
        setTimeout(function() {
            setupStateValidation();
        }, 0);
    }
    
    // Show/hide no-substrates message, but always show the form
    const noSubstratesMsg = document.getElementById('no-substrates-message');
    
    if (numSubstrates > 0) {
        if (noSubstratesMsg) noSubstratesMsg.style.display = 'none';
    } else {
        if (noSubstratesMsg) noSubstratesMsg.style.display = 'block';
    }
}

/**
 * Add allele via AJAX
 */
function addAllele(geneId, formData, onSuccess) {
    formData.append('char_action', 'add_allele_ajax');
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Alelo añadido correctamente', 'success');
            if (onSuccess) onSuccess(data);
        } else {
            showNotification(data.error || 'Error al añadir alelo', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Delete allele via AJAX
 */
function deleteAllele(alleleId, onSuccess) {
    if (!confirm('¿Eliminar alelo? Esta acción no se puede deshacer.')) {
        return;
    }
    
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
            if (onSuccess) onSuccess(data);
        } else {
            showNotification(data.error || 'Error al eliminar alelo', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Add character to active project via AJAX
 */
function addCharacterToProject(characterId, characterName) {
    const formData = new FormData();
    formData.append('project_action', 'add_character_to_project');
    formData.append('character_id', characterId);
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(`Carácter "${characterName}" añadido al proyecto`, 'success');
        } else {
            showNotification(data.error || 'Error al añadir carácter al proyecto', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    /* Highlight pulse for substrates input when we show a toast */
    @keyframes highlightPulse {
        0% {
            box-shadow: 0 0 0 0 rgba(255,193,7,0);
        }
        50% {
            box-shadow: 0 0 12px 4px rgba(255,193,7,0.95);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(255,193,7,0);
        }
    }

    .highlight-toast {
        animation: highlightPulse 0.9s ease-out;
        border-radius: 4px;
    }
`;
document.head.appendChild(style);

// Ensure initialization runs on DOMContentLoaded for full-page loads
window.addEventListener('DOMContentLoaded', function() {
    if (typeof initializeCharacterUI === 'function') initializeCharacterUI();
});
