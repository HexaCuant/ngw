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
 * Open character via AJAX
 */
function openCharacter(characterId) {
    const formData = new FormData();
    formData.append('char_action', 'open_character_ajax');
    formData.append('char_id', characterId);
    
    fetch('index.php?option=1', {
        method: 'POST',
        body: formData
    })
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
                columnRight.appendChild(detailsCard.firstElementChild);
                
                // Attach event handler to create gene form
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
                                
                                // Make sure genes view is visible
                                const genesView = document.getElementById('genes-view');
                                if (genesView) {
                                    genesView.style.display = 'block';
                                }
                                
                                // Add row to genes table
                                const tbody = document.querySelector('#genes-view table tbody');
                                if (tbody) {
                                    // Remove "no genes" message if exists
                                    const emptyMessage = tbody.querySelector('p');
                                    if (emptyMessage) {
                                        emptyMessage.closest('tr')?.remove();
                                    }
                                    
                                    // Check if tbody only has empty message row
                                    if (tbody.children.length === 0 || tbody.querySelector('td[colspan]')) {
                                        tbody.innerHTML = ''; // Clear empty state
                                    }
                                    
                                    const gene = data.gene;
                                    const row = document.createElement('tr');
                                    row.innerHTML = `
                                        <td>${gene.id}</td>
                                        <td>${gene.name}</td>
                                        <td>${gene.chromosome || ''}</td>
                                        <td>${gene.position || ''}</td>
                                        <td>
                                            <button type="button" id="gene-toggle-${gene.id}" onclick="toggleGene(${gene.id}, this)" class="btn-primary btn-small">Abrir</button>
                                            <button type="button" onclick="deleteGene(${gene.id}, '${gene.name.replace(/'/g, "\\'")}')" class="btn-danger btn-small">Borrar</button>
                                        </td>
                                    `;
                                    tbody.appendChild(row);
                                }
                                
                                // Reset and hide form
                                createGeneForm.reset();
                                const formContainer = document.getElementById('create-gene-form-container');
                                if (formContainer) {
                                    formContainer.style.display = 'none';
                                }
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
            
            // Insert create form HTML
            const columnRight = document.querySelector('.column-right');
            if (columnRight && data.html) {
                columnRight.insertAdjacentHTML('beforeend', data.html);
                
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
            showNotification(data.error || 'Error al cerrar carácter', 'error');
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
function openGene(geneId) {
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
            showNotification('Gen abierto', 'success');
            
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

                                    // Insert new allele row into table
                                    const tbody = document.querySelector('#alleles-section table tbody');
                                    if (tbody) {
                                        // Remove 'no alelos' row if present
                                        const emptyRow = tbody.querySelector('td[colspan]');
                                        if (emptyRow) emptyRow.closest('tr').remove();

                                        const allele = data.allele;
                                        const tr = document.createElement('tr');

                                        tr.innerHTML = `
                                            <td>${allele.id}</td>
                                            <td>${allele.name}</td>
                                            <td>${allele.value ?? ''}</td>
                                            <td>${allele.additive == 1 ? 'Sí' : 'No'}</td>
                                            <td>${allele.dominance ?? ''}</td>
                                            <td>${allele.epistasis ?? ''}</td>
                                        `;

                                        // Actions cell
                                        const actionsTd = document.createElement('td');
                                        const delBtn = document.createElement('button');
                                        delBtn.className = 'btn-danger btn-small';
                                        delBtn.textContent = 'Eliminar';
                                        delBtn.addEventListener('click', function() {
                                            deleteAllele(allele.id, () => tr.remove());
                                        });
                                        actionsTd.appendChild(delBtn);
                                        tr.appendChild(actionsTd);

                                        tbody.appendChild(tr);
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
function updateSubstrates(characterId, substrates, onSuccess) {
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
                showNotification(data.error || 'Error al actualizar sustratos', 'error');
            }
        } catch (e) {
            console.error('JSON parse error:', e, 'Response:', text);
            showNotification('Error: respuesta inválida del servidor', 'error');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Add connection via AJAX
 */
function addConnection(characterId, stateA, transition, stateB, onSuccess) {
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
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Conexión creada correctamente', 'success');
            if (onSuccess) onSuccess(data);
        } else {
            showNotification(data.error || 'Error al crear conexión', 'error');
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
`;
document.head.appendChild(style);
