/**
 * AJAX Handlers for Projects
 * Provides smooth updates without full page reloads
 */

/**
 * Show notification message (reuse from ajax-handlers.js)
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
 * Open project via AJAX
 */
function openProject(projectId) {
    const formData = new FormData();
    formData.append('project_action', 'open_project_ajax');
    formData.append('project_id', projectId);
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Proyecto abierto', 'success');
            
            // Hide projects table
            const projectsTable = document.querySelector('.card h3');
            if (projectsTable && projectsTable.textContent === 'Mis Proyectos') {
                const table = projectsTable.nextElementSibling;
                if (table && table.tagName === 'TABLE') {
                    projectsTable.style.display = 'none';
                    table.style.display = 'none';
                }
            }
            
            // Hide create form card
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                const h3 = card.querySelector('h3');
                if (h3 && h3.textContent === 'Crear Nuevo Proyecto') {
                    card.style.display = 'none';
                }
            });
            
            // Insert project details HTML at the top of the card
            const card = document.querySelector('.card');
            if (card && data.html) {
                const detailsDiv = document.createElement('div');
                detailsDiv.innerHTML = data.html;
                detailsDiv.id = 'active-project-details';
                
                // Insert after h2
                const h2 = card.querySelector('h2');
                if (h2) {
                    h2.after(detailsDiv);
                }
            }
        } else {
            showNotification(data.error || 'Error al abrir proyecto', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Close project via AJAX
 */
function closeProject() {
    const formData = new FormData();
    formData.append('project_action', 'close_project_ajax');
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Proyecto cerrado', 'success');
            
            // Reload page to ensure clean state
            window.location.href = 'index.php?option=2';
        } else {
            showNotification(data.error || 'Error al cerrar proyecto', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Attach event listener to create project form
 */
function attachCreateProjectFormListener() {
    const createProjectForm = document.getElementById('create-project-form');
    if (createProjectForm) {
        createProjectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.set('project_action', 'create_project_ajax');
            
            fetch('index.php?option=2', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Proyecto creado', 'success');
                    
                    // Add row to projects table
                    const tbody = document.querySelector('table tbody');
                    if (tbody) {
                        // Remove "no projects" row if exists
                        const emptyRow = tbody.querySelector('td[colspan]');
                        if (emptyRow) {
                            emptyRow.closest('tr').remove();
                        }
                        
                        const project = data.project;
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${project.id}</td>
                            <td>${project.name}</td>
                            <td>
                                <button type="button" onclick="openProject(${project.id})" class="btn-primary btn-small">Abrir</button>
                                <button type="button" onclick="deleteProject(${project.id}, '${project.name.replace(/'/g, "\\'")}')" class="btn-danger btn-small">Borrar</button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    }
                    
                    // Reset form
                    createProjectForm.reset();
                } else {
                    showNotification(data.error || 'Error al crear proyecto', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error de conexión', 'error');
            });
        });
    }
}

/**
 * Delete project via AJAX
 */
function deleteProject(projectId, projectName) {
    confirmAction(
        '¿Estás seguro de eliminar el proyecto "' + projectName + '"?\n\nEsta acción no se puede deshacer y eliminará todos los caracteres asociados al proyecto.',
        'Eliminar',
        'Cancelar'
    ).then(ok => {
        if (!ok) return;
        
        // Ask if user wants to delete the directory too
        confirmAction(
            '¿Deseas eliminar también el directorio del proyecto del servidor?\n\nEsto borrará permanentemente todos los archivos generados (individuos, cruzamientos, etc.).',
            'Sí, borrar archivos',
            'No, conservar archivos'
        ).then(deleteDir => {
            const formData = new FormData();
            formData.append('project_action', 'delete_project_ajax');
            formData.append('project_id', projectId);
            formData.append('delete_directory', deleteDir ? 'true' : 'false');
            
            fetch('index.php?option=2', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const msg = deleteDir 
                        ? 'Proyecto y archivos eliminados' 
                        : 'Proyecto eliminado (archivos conservados)';
                    showNotification(msg, 'success');
                    
                    // Remove row from table
                    const rows = document.querySelectorAll('table tbody tr');
                    rows.forEach(row => {
                        const cells = row.cells;
                        if (cells && cells[0] && cells[0].textContent == projectId) {
                            row.remove();
                        }
                    });
                    
                    // Check if table is empty
                    const tbody = document.querySelector('table tbody');
                    if (tbody && tbody.children.length === 0) {
                        const emptyRow = document.createElement('tr');
                        emptyRow.innerHTML = '<td colspan="3" class="text-center">No tienes proyectos creados</td>';
                        tbody.appendChild(emptyRow);
                    }
                    
                    // If the deleted project was the active one, reload the page
                    // to show the project list and create form
                    if (data.was_active) {
                        window.location.reload();
                    } else {
                        // Just remove the project details div if it exists
                        const detailsDiv = document.getElementById('active-project-details');
                        if (detailsDiv) {
                            detailsDiv.remove();
                        }
                    }
                } else {
                    showNotification(data.error || 'Error al eliminar proyecto', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error de conexión', 'error');
            });
        });
    });
}

/**
 * Update environment value for character in project
 */
function updateEnvironment(characterId) {
    const input = document.getElementById('env-' + characterId);
    if (!input) {
        showNotification('Error: campo de ambiente no encontrado', 'error');
        return;
    }
    
    const environment = parseFloat(input.value) || 0;
    
    const formData = new FormData();
    formData.append('project_action', 'update_environment');
    formData.append('character_id', characterId);
    formData.append('environment', Math.round(environment));
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Ambiente actualizado', 'success');
            // Mark as saved (green border)
            input.style.borderColor = 'var(--color-success, #22c55e)';
            input.style.borderWidth = '2px';
            
            // Reset border after 2 seconds
            setTimeout(() => {
                input.style.borderColor = '';
                input.style.borderWidth = '';
            }, 2000);
        } else {
            showNotification(data.error || 'Error al actualizar ambiente', 'error');
            // Mark as error (red border)
            input.style.borderColor = 'var(--color-danger, #ef4444)';
            input.style.borderWidth = '2px';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
        // Mark as error
        input.style.borderColor = 'var(--color-danger, #ef4444)';
        input.style.borderWidth = '2px';
    });
}

/**
 * Mark environment field as modified
 */
function markEnvironmentModified(characterId) {
    const input = document.getElementById('env-' + characterId);
    if (input) {
        // Mark as modified (orange border)
        input.style.borderColor = 'var(--color-warning, #f59e0b)';
        input.style.borderWidth = '2px';
    }
}

/**
 * Remove character from project
 */
function removeCharacterFromProject(characterId, characterName) {
    if (!confirm('¿Estás seguro de eliminar el carácter "' + characterName + '" del proyecto?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('project_action', 'remove_character_from_project');
    formData.append('character_id', characterId);
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Carácter eliminado del proyecto', 'success');
            
            // Remove row from table
            const row = document.getElementById('project-char-' + characterId);
            if (row) {
                row.remove();
            }
            
            // Check if table is empty
            const tbody = document.querySelector('#active-project-details table tbody');
            if (tbody && tbody.children.length === 0) {
                // Show empty message
                const tableContainer = tbody.closest('table').parentElement;
                if (tableContainer) {
                    tableContainer.innerHTML = '<p class="text-center">No hay caracteres asignados a este proyecto.</p><p class="text-center">Ve a la sección <a href="index.php?option=1">Caracteres</a> para añadir caracteres al proyecto.</p>';
                }
            }
        } else {
            showNotification(data.error || 'Error al eliminar carácter del proyecto', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Open allele frequencies panel for a character (inline, not modal)
 */
function openAlleleFrequencies(characterId, characterName) {
    // If clicking on same character that's already open, close it
    const existingSection = document.getElementById('allele-frequencies-section');
    if (existingSection && existingSection.dataset.characterId == characterId) {
        closeAlleleFrequencies();
        return;
    }
    
    const formData = new FormData();
    formData.append('project_action', 'get_allele_frequencies');
    formData.append('character_id', characterId);
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlleleFrequenciesSection(characterId, characterName, data.genes);
        } else {
            showNotification(data.error || 'Error al obtener frecuencias', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Show inline section with allele frequencies (below characters table)
 */
function showAlleleFrequenciesSection(characterId, characterName, genes) {
    // Remove existing section if any
    closeAlleleFrequencies();
    
    // Build content
    let genesHtml = '';
    
    if (genes.length === 0) {
        genesHtml = '<p>Este carácter no tiene genes definidos.</p>';
    } else {
        genes.forEach(gene => {
            const totalAlleles = gene.alleles.length;
            const defaultFreq = totalAlleles > 0 ? (1 / totalAlleles).toFixed(4) : 0.5;
            
            genesHtml += `
                <div class="gene-frequencies" style="margin-bottom: 1rem;">
                    <h4 style="margin: 0.5rem 0;">Gen: ${escapeHtml(gene.name)} (ID: ${gene.id})</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.25rem 0;">
                        Cromosoma: ${escapeHtml(gene.chromosome || '-')} | Posición: ${escapeHtml(gene.position || '-')}
                    </p>
                    <table>
                        <thead>
                            <tr>
                                <th>ID Alelo</th>
                                <th>Nombre</th>
                                <th>Valor</th>
                                <th>Dominancia</th>
                                <th>Frecuencia</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            gene.alleles.forEach(allele => {
                const freq = allele.frequency !== null ? allele.frequency : defaultFreq;
                genesHtml += `
                    <tr>
                        <td>${allele.id}</td>
                        <td>${escapeHtml(allele.name)}</td>
                        <td>${allele.value}</td>
                        <td>${allele.dominance}</td>
                        <td>
                            <input type="number" 
                                   class="allele-freq-input" 
                                   data-allele-id="${allele.id}"
                                   data-gene-id="${gene.id}"
                                   value="${freq}" 
                                   step="0.01" 
                                   min="0" 
                                   max="1"
                                   style="width: 80px;"
                                   onchange="validateGeneFrequencies(${gene.id})">
                        </td>
                    </tr>
                `;
            });
            
            genesHtml += `
                        </tbody>
                    </table>
                    <div id="gene-freq-status-${gene.id}" style="margin-top: 0.25rem; font-size: 0.85rem;"></div>
                </div>
            `;
        });
    }
    
    // Create section element
    const section = document.createElement('div');
    section.id = 'allele-frequencies-section';
    section.dataset.characterId = characterId;
    section.innerHTML = `
        <h3>Frecuencias Alélicas - ${escapeHtml(characterName)}</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
            Define las frecuencias de cada alelo para la generación aleatoria inicial. 
            La suma de frecuencias de todos los alelos de cada gen debe ser 1.
        </p>
        
        <form id="allele-frequencies-form" data-character-id="${characterId}">
            ${genesHtml}
            
            <div id="frequencies-error" style="color: var(--color-danger); margin-bottom: 1rem; display: none;"></div>
            
            <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                <button type="submit" class="btn-success">Guardar Frecuencias</button>
                <button type="button" class="btn-secondary" onclick="closeAlleleFrequencies()">Cerrar</button>
            </div>
        </form>
    `;
    
    // Find insertion point - after the characters table in active-project-details
    const projectDetails = document.getElementById('active-project-details');
    if (projectDetails) {
        projectDetails.appendChild(section);
    } else {
        // Fallback: append to first .card
        const card = document.querySelector('.card');
        if (card) {
            card.appendChild(section);
        }
    }
    
    // Form submit handler
    document.getElementById('allele-frequencies-form').addEventListener('submit', function(e) {
        e.preventDefault();
        saveAlleleFrequencies(characterId);
    });
    
    // Validate all genes initially
    genes.forEach(gene => validateGeneFrequencies(gene.id));
    
    // Scroll to section
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Validate that frequencies for a gene sum to 1
 */
function validateGeneFrequencies(geneId) {
    const inputs = document.querySelectorAll(`.allele-freq-input[data-gene-id="${geneId}"]`);
    let sum = 0;
    
    inputs.forEach(input => {
        sum += parseFloat(input.value) || 0;
    });
    
    const statusDiv = document.getElementById(`gene-freq-status-${geneId}`);
    if (statusDiv) {
        if (Math.abs(sum - 1) < 0.0001) {
            statusDiv.innerHTML = '<span style="color: var(--color-success);">✓ Suma = 1.0 (correcto)</span>';
            statusDiv.dataset.valid = 'true';
        } else {
            statusDiv.innerHTML = `<span style="color: var(--color-danger);">⚠ Suma = ${sum.toFixed(4)} (debe ser 1.0)</span>`;
            statusDiv.dataset.valid = 'false';
        }
    }
}

/**
 * Save allele frequencies
 */
function saveAlleleFrequencies(characterId) {
    // Check all genes are valid
    const statusDivs = document.querySelectorAll('[id^="gene-freq-status-"]');
    let allValid = true;
    
    statusDivs.forEach(div => {
        if (div.dataset.valid !== 'true') {
            allValid = false;
        }
    });
    
    if (!allValid) {
        const errorDiv = document.getElementById('frequencies-error');
        errorDiv.textContent = 'La suma de frecuencias de cada gen debe ser exactamente 1. Revisa los valores marcados en rojo.';
        errorDiv.style.display = 'block';
        return;
    }
    
    // Collect all frequencies
    const frequencies = [];
    const inputs = document.querySelectorAll('.allele-freq-input');
    
    inputs.forEach(input => {
        frequencies.push({
            allele_id: parseInt(input.dataset.alleleId),
            frequency: parseFloat(input.value)
        });
    });
    
    const formData = new FormData();
    formData.append('project_action', 'save_allele_frequencies');
    formData.append('character_id', characterId);
    formData.append('frequencies', JSON.stringify(frequencies));
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Frecuencias guardadas correctamente', 'success');
            // Keep section open to show saved state
        } else {
            const errorDiv = document.getElementById('frequencies-error');
            errorDiv.textContent = data.error || 'Error al guardar frecuencias';
            errorDiv.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
    });
}

/**
 * Close allele frequencies section
 */
function closeAlleleFrequencies() {
    const section = document.getElementById('allele-frequencies-section');
    if (section) section.remove();
}

// Note: escapeHtml function is defined in ajax-handlers.js which is loaded before this file
