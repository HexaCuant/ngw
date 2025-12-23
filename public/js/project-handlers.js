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
    if (!confirm('¿Estás seguro de eliminar el proyecto "' + projectName + '"?\n\nEsta acción no se puede deshacer y eliminará todos los caracteres asociados al proyecto.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('project_action', 'delete_project_ajax');
    formData.append('project_id', projectId);
    
    fetch('index.php?option=2', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Proyecto eliminado', 'success');
            
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
            
            // Close project details if this was the active project
            const detailsDiv = document.getElementById('active-project-details');
            if (detailsDiv) {
                detailsDiv.remove();
            }
        } else {
            showNotification(data.error || 'Error al eliminar proyecto', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error de conexión', 'error');
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
