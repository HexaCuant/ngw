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

// Disabled interaction helper and rate limiter
const disabledMsg = 'No se puede modificar el número de sustratos porque ya existen conexiones definidas para este carácter.';
let lastDisabledToast = 0;
function showDisabledToast() {
    const now = Date.now();
    if (now - lastDisabledToast > 800) {
        showNotification(disabledMsg, 'warning');
        if (typeof flashInputHighlight === 'function') flashInputHighlight();
        lastDisabledToast = now;
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
    if (typeof setupStateValidation === 'function') setupStateValidation();

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
            if (this.dataset.hasConnections === '1') {
                showDisabledToast();
                this.blur();
            }
        });

        newInput.addEventListener('click', function(e) {
            if (this.dataset.hasConnections === '1') {
                showDisabledToast();
                e.preventDefault();
                this.blur();
            }
        });

        newInput.addEventListener('input', function() {
            clearTimeout(substratesTimeout);
            substratesTimeout = setTimeout(() => {
                if (newInput.dataset.hasConnections === '1') {
                    showDisabledToast();
                    newInput.value = prevSubstratesValue;
                    return;
                }
                const value = parseInt(newInput.value);
                const characterId = window._activeCharacterId || 0;
                if (!isNaN(value) && value >= 0 && characterId > 0) {
                    updateSubstrates(characterId, value, function(data) {
                        prevSubstratesValue = data.substrates;
                        reloadSubstrateSelectors(data.substrates);
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
                }, function(err) {
                    newInput.value = prevSubstratesValue;
                    if (typeof flashInputHighlight === 'function') flashInputHighlight();
                });
            }
        });
    }

    // Set up add-connection form handler (idempotent: replace node)
    const addConnectionForm = document.getElementById('add-connection-form');
    if (addConnectionForm) {
        const newForm = addConnectionForm.cloneNode(true);
        addConnectionForm.parentNode.replaceChild(newForm, addConnectionForm);

        newForm.addEventListener('submit', function(e) {
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
                    newForm.reset();

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
                    const substratesInput2 = document.getElementById('substrates-input');
                    if (substratesInput2) substratesInput2.dataset.hasConnections = '1';
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
                        // Insert the character details at the top of the column so it appears above connections
                        const inserted = detailsCard.firstElementChild;
                        columnRight.insertAdjacentElement('afterbegin', inserted);

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
    .then(response => response.json())
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

                // Reset substrates flag if present
                const substratesInput = document.getElementById('substrates-input');
                if (substratesInput) substratesInput.dataset.hasConnections = '0';
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
            if (data.error && data.error.includes('\n')) {
                // Multiple errors - show as list
                const errors = data.error.split('\n').filter(e => e.trim());
                let message = '<strong>No se puede cerrar el carácter:</strong><ul style="margin-top: 0.5rem; margin-bottom: 0; padding-left: 1.5rem;">';
                errors.forEach(error => {
                    message += '<li style="margin-bottom: 0.5rem;">' + escapeHtml(error) + '</li>';
                });
                message += '</ul>';
                
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
                showNotification(data.error || 'Error al cerrar carácter', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
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
                        
                        const substratesInput = document.getElementById('substrates-input');
                        if (substratesInput) substratesInput.dataset.hasConnections = '0';
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

                    // Reload transition selectors in connection form
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
    console.debug('reloadTransitionSelectors: characterId=', characterId);
    if (!characterId) {
        console.warn('reloadTransitionSelectors: no characterId available');
        return;
    }

    // Ensure the container exists before proceeding
    const maxAttempts = 5;
    let attemptCount = 0;
    
    const tryFetch = () => {
        const container = document.getElementById('transition-container');
        if (!container && attemptCount < maxAttempts) {
            console.debug('reloadTransitionSelectors: container not found, retrying... attempt', attemptCount + 1);
            attemptCount++;
            setTimeout(tryFetch, 100);
            return;
        }
        
        if (!container) {
            console.error('reloadTransitionSelectors: transition-container not found in DOM after', maxAttempts, 'attempts');
            return;
        }

        const formData = new FormData();
        formData.append('char_action', 'get_genes_ajax');
        formData.append('char_id', characterId);

        console.debug('reloadTransitionSelectors: fetching genes for char', characterId);
        
        fetch('index.php?option=1', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.debug('reloadTransitionSelectors: fetch response status', response.status);
            return response.json();
        })
        .then(data => {
            console.debug('reloadTransitionSelectors: received data', data);
            if (data.success && data.genes) {
                console.debug('reloadTransitionSelectors: genes count=', data.genes.length);
                const container = document.getElementById('transition-container');
                console.debug('reloadTransitionSelectors: container found?', !!container);
                if (!container) {
                    console.error('reloadTransitionSelectors: transition-container not found in DOM');
                    return;
                }

                let html = '';
                data.genes.forEach(gene => {
                    console.debug('reloadTransitionSelectors: adding gene', gene.id, gene.name);
                    html += `
                        <label>
                            <input type="radio" name="transition" value="${gene.id}" required> ${gene.name}
                        </label>
                    `;
                });
                console.debug('reloadTransitionSelectors: updating container HTML');
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
            } else {
                console.error('reloadTransitionSelectors: data.success=', data.success, 'data.genes=', data.genes);
            }
        })
        .catch(error => {
            console.error('reloadTransitionSelectors: fetch error', error);
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
    
    // Re-apply state validation after reload
    if (typeof setupStateValidation === 'function') {
        setupStateValidation();
    }
    
    // Show/hide connection form based on substrates
    const connectionForm = document.getElementById('add-connection-form');
    const noSubstratesMsg = document.getElementById('no-substrates-message');
    
    if (numSubstrates > 0) {
        if (connectionForm) connectionForm.style.display = 'block';
        if (noSubstratesMsg) noSubstratesMsg.style.display = 'none';
    } else {
        if (connectionForm) connectionForm.style.display = 'none';
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
