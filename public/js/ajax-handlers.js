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
 * Reload connections table
 */
function reloadConnectionsTable(characterId, connections) {
    const tbody = document.querySelector('#connections-table tbody');
    if (!tbody) return;
    
    if (connections.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No hay conexiones definidas para este carácter.</td></tr>';
        return;
    }
    
    tbody.innerHTML = connections.map(conn => `
        <tr>
            <td>S${conn.state_a}</td>
            <td>${conn.gene_name || 'Gen #' + conn.transition}</td>
            <td>S${conn.state_b}</td>
            <td>
                <button onclick="deleteConnection(${conn.id}, () => location.reload())" 
                        class="btn-danger btn-small">Borrar</button>
            </td>
        </tr>
    `).join('');
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
