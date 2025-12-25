<?php
/**
 * Summary page template
 * @var \Ngw\Models\Project $projectModel
 * @var \Ngw\Auth\SessionManager $session
 * @var \Ngw\Database\Database $db
 */

$activeProjectId = $session->get('active_project_id');
$activeProject = null;
$summaryData = [];
$projectCharacters = [];

if ($activeProjectId) {
    $activeProject = $projectModel->getById($activeProjectId);
    
    if ($activeProject) {
        require_once __DIR__ . '/../../src/Models/Generation.php';
        $generationModel = new \Ngw\Models\Generation($db);
        $summaryData = $generationModel->getProjectSummary($activeProjectId);
        $projectCharacters = $projectModel->getCharacters($activeProjectId);
    }
}
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Resumen del Proyecto</h2>
        <?php if ($activeProject) : ?>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <label style="font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                    <input type="checkbox" id="autoRefreshSummary"> Auto-actualizar
                </label>
                <button id="refreshSummary" class="btn-secondary btn-small">Actualizar</button>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($activeProject) : ?>
        <div class="project-header-info mb-1">
            <strong>Proyecto:</strong> <?= e($activeProject['name']) ?>
        </div>

        <?php if (empty($summaryData)) : ?>
            <div class="empty-state">
                <p>No hay generaciones en este proyecto todavía.</p>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Gen.</th>
                            <th rowspan="2">Tipo</th>
                            <th rowspan="2">Pob.</th>
                            <?php foreach ($projectCharacters as $char) : ?>
                                <th colspan="2" class="text-center"><?= e($char['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($projectCharacters as $char) : ?>
                                <th class="text-center">Media</th>
                                <th class="text-center">Var.</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryData as $row) : ?>
                            <tr>
                                <td><?= $row['generation_number'] ?></td>
                                <td><?= e($row['type']) ?></td>
                                <td><?= $row['population_size'] ?></td>
                                <?php foreach ($projectCharacters as $char) : ?>
                                    <?php 
                                    $charName = $char['name'];
                                    $stats = $row['stats'][$charName] ?? null;
                                    ?>
                                    <td class="text-center"><?= $stats ? number_format($stats['mean'], 4) : '-' ?></td>
                                    <td class="text-center"><?= $stats ? number_format($stats['variance'], 4) : '-' ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <div class="empty-state">
            <p>No hay ningún proyecto activo. Por favor, abre un proyecto en la pestaña de Proyectos.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.summary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.summary-table th, .summary-table td {
    border: 1px solid var(--color-border);
    padding: 0.5rem;
}

.summary-table thead th {
    background-color: var(--color-surface-light);
    position: sticky;
    top: 0;
    z-index: 10;
}

.summary-table tbody tr:nth-child(even) {
    background-color: rgba(255, 255, 255, 0.02);
}

.summary-table tbody tr:hover {
    background-color: var(--color-surface-light);
}

.text-center {
    text-align: center;
}

.table-responsive {
    overflow-x: auto;
    max-height: 70vh;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
}

.mb-1 {
    margin-bottom: 1rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('refreshSummary');
    if (!refreshBtn) return;

    const autoRefreshCheck = document.getElementById('autoRefreshSummary');
    let autoRefreshInterval = null;

    function performRefresh(isAuto = false) {
        if (!isAuto) {
            refreshBtn.disabled = true;
            refreshBtn.textContent = 'Actualizando...';
        }
        
        const formData = new FormData();
        formData.append('project_action', 'get_project_summary_ajax');
        
        fetch('index.php?option=0', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSummaryTable(data.summary, data.characters);
                if (!isAuto) showToast('Resumen actualizado', 'success');
            } else {
                if (!isAuto) showToast('Error: ' + data.error, 'error');
            }
        })
        .catch(error => {
            if (!isAuto) showToast('Error de conexión', 'error');
        })
        .finally(() => {
            if (!isAuto) {
                refreshBtn.disabled = false;
                refreshBtn.textContent = 'Actualizar';
            }
        });
    }

    refreshBtn.addEventListener('click', () => performRefresh(false));

    if (autoRefreshCheck) {
        autoRefreshCheck.addEventListener('change', function() {
            if (this.checked) {
                // Refresh every 10 seconds if checked
                autoRefreshInterval = setInterval(() => performRefresh(true), 10000);
                performRefresh(true);
            } else {
                if (autoRefreshInterval) clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        });
    }

    function updateSummaryTable(summary, characters) {
        const container = document.querySelector('.card');
        if (!summary || summary.length === 0) {
            // Handle empty state if needed
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Gen.</th>
                            <th rowspan="2">Tipo</th>
                            <th rowspan="2">Pob.</th>
                            ${characters.map(char => `<th colspan="2" class="text-center">${escapeHtml(char.name)}</th>`).join('')}
                        </tr>
                        <tr>
                            ${characters.map(() => `
                                <th class="text-center">Media</th>
                                <th class="text-center">Var.</th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${summary.map(row => `
                            <tr>
                                <td>${row.generation_number}</td>
                                <td>${escapeHtml(row.type)}</td>
                                <td>${row.population_size}</td>
                                ${characters.map(char => {
                                    const stats = row.stats[char.name];
                                    return `
                                        <td class="text-center">${stats ? stats.mean.toFixed(4) : '-'}</td>
                                        <td class="text-center">${stats ? stats.variance.toFixed(4) : '-'}</td>
                                    `;
                                }).join('')}
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        const tableContainer = document.querySelector('.table-responsive');
        if (tableContainer) {
            tableContainer.outerHTML = html;
        } else {
            // If it was empty before
            const emptyState = document.querySelector('.empty-state');
            if (emptyState) emptyState.remove();
            const headerInfo = document.querySelector('.project-header-info');
            headerInfo.insertAdjacentHTML('afterend', html);
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
