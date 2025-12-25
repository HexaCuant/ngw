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
        <?php if ($activeProject && !empty($summaryData)) : ?>
            <button id="downloadSummary" 
                    data-project-name="<?= e($activeProject['name']) ?>"
                    data-timestamp="<?= time() ?>"
                    class="btn-secondary btn-small">Descargar HTML</button>
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
                            <th rowspan="3">Gen.</th>
                            <th rowspan="3">Tipo</th>
                            <th colspan="<?= 2 + (count($projectCharacters) * 2) ?>" class="text-center parental-header">Parentales</th>
                            <th colspan="<?= 1 + (count($projectCharacters) * 2) ?>" class="text-center generation-header">Generación</th>
                        </tr>
                        <tr>
                            <th rowspan="2" class="text-center parental-cell">Gen</th>
                            <th rowspan="2" class="text-center parental-cell">Num</th>
                            <?php foreach ($projectCharacters as $char) : ?>
                                <th colspan="2" class="text-center parental-cell"><?= e($char['name']) ?></th>
                            <?php endforeach; ?>
                            <th rowspan="2" class="text-center generation-cell">Pob.</th>
                            <?php foreach ($projectCharacters as $char) : ?>
                                <th colspan="2" class="text-center generation-cell"><?= e($char['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($projectCharacters as $char) : ?>
                                <th class="text-center parental-cell">Media</th>
                                <th class="text-center parental-cell">Var.</th>
                            <?php endforeach; ?>
                            <?php foreach ($projectCharacters as $char) : ?>
                                <th class="text-center generation-cell">Media</th>
                                <th class="text-center generation-cell">Var.</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryData as $row) : ?>
                            <tr>
                                <td><?= $row['generation_number'] ?></td>
                                <td><?= e($row['type']) ?></td>
                                
                                <!-- Parentales -->
                                <td class="text-center parental-cell"><?= $row['parental_source_gen'] ?? '-' ?></td>
                                <td class="text-center parental-cell"><?= $row['parental_count'] ?: '-' ?></td>
                                <?php foreach ($projectCharacters as $char) : ?>
                                    <?php 
                                    $charName = $char['name'];
                                    $pStats = $row['parental_stats'][$charName] ?? null;
                                    ?>
                                    <td class="text-center parental-cell"><?= $pStats ? number_format($pStats['mean'], 4) : '-' ?></td>
                                    <td class="text-center parental-cell"><?= $pStats ? number_format($pStats['variance'], 4) : '-' ?></td>
                                <?php endforeach; ?>

                                <!-- Generación -->
                                <td class="text-center generation-cell"><?= $row['population_size'] ?></td>
                                <?php foreach ($projectCharacters as $char) : ?>
                                    <?php 
                                    $charName = $char['name'];
                                    $stats = $row['stats'][$charName] ?? null;
                                    ?>
                                    <td class="text-center generation-cell"><?= $stats ? number_format($stats['mean'], 4) : '-' ?></td>
                                    <td class="text-center generation-cell"><?= $stats ? number_format($stats['variance'], 4) : '-' ?></td>
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

.parental-header {
    background-color: rgba(52, 152, 219, 0.15) !important;
    color: #3498db;
}

.generation-header {
    background-color: rgba(46, 204, 113, 0.15) !important;
    color: #2ecc71;
}

.parental-cell {
    background-color: rgba(52, 152, 219, 0.05);
}

.generation-cell {
    background-color: rgba(46, 204, 113, 0.05);
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
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
}

.mb-1 {
    margin-bottom: 1rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let autoRefreshInterval = null;
    let currentGenCount = <?= count($summaryData) ?>;

    function performRefresh() {
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
                currentGenCount = data.summary.length;
            }
        })
        .catch(error => {
            console.error('Error refreshing summary:', error);
        });
    }

    function checkForUpdates() {
        const formData = new FormData();
        formData.append('project_action', 'check_new_generations_ajax');
        
        fetch('index.php?option=0', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count !== currentGenCount) {
                performRefresh();
            }
        })
        .catch(error => {
            console.error('Error checking for updates:', error);
        });
    }

    // Check for updates every 5 seconds
    autoRefreshInterval = setInterval(checkForUpdates, 5000);

    // Clean up interval when leaving the page
    window.addEventListener('beforeunload', () => {
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    });

    const downloadBtn = document.getElementById('downloadSummary');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const table = document.querySelector('.summary-table');
            if (!table) {
                console.error('No se encontró la tabla');
                return;
            }

            const projectName = downloadBtn.getAttribute('data-project-name') || 'proyecto';
            const safeName = projectName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            
            console.log('Project name:', projectName);
            console.log('Safe name:', safeName);
            console.log('Download filename:', `resumen_${safeName}.html`);

            // Create a simple HTML structure for the table
            let html = `
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen - ${projectName}</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .parental-header { background-color: #e3f2fd; }
        .generation-header { background-color: #e8f5e9; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h1>Resumen del Proyecto: ${projectName}</h1>
    <p>Exportado el: ${new Date().toLocaleString()}</p>
    ${table.outerHTML}
</body>
</html>`;

            const blob = new Blob([html], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `resumen_${safeName}.html`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
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
                            <th rowspan="3">Gen.</th>
                            <th rowspan="3">Tipo</th>
                            <th colspan="${2 + (characters.length * 2)}" class="text-center parental-header">Parentales</th>
                            <th colspan="${1 + (characters.length * 2)}" class="text-center generation-header">Generación</th>
                        </tr>
                        <tr>
                            <th rowspan="2" class="text-center parental-cell">Gen</th>
                            <th rowspan="2" class="text-center parental-cell">Num</th>
                            ${characters.map(char => `<th colspan="2" class="text-center parental-cell">${escapeHtml(char.name)}</th>`).join('')}
                            <th rowspan="2" class="text-center generation-cell">Pob.</th>
                            ${characters.map(char => `<th colspan="2" class="text-center generation-cell">${escapeHtml(char.name)}</th>`).join('')}
                        </tr>
                        <tr>
                            ${characters.map(() => `
                                <th class="text-center parental-cell">Media</th>
                                <th class="text-center parental-cell">Var.</th>
                            `).join('')}
                            ${characters.map(() => `
                                <th class="text-center generation-cell">Media</th>
                                <th class="text-center generation-cell">Var.</th>
                            `).join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${summary.map(row => `
                            <tr>
                                <td>${row.generation_number}</td>
                                <td>${escapeHtml(row.type)}</td>
                                
                                <!-- Parentales -->
                                <td class="text-center parental-cell">${row.parental_source_gen || '-'}</td>
                                <td class="text-center parental-cell">${row.parental_count || '-'}</td>
                                ${characters.map(char => {
                                    const pStats = row.parental_stats ? row.parental_stats[char.name] : null;
                                    return `
                                        <td class="text-center parental-cell">${pStats ? pStats.mean.toFixed(4) : '-'}</td>
                                        <td class="text-center parental-cell">${pStats ? pStats.variance.toFixed(4) : '-'}</td>
                                    `;
                                }).join('')}

                                <!-- Generación -->
                                <td class="text-center generation-cell">${row.population_size}</td>
                                ${characters.map(char => {
                                    const stats = row.stats[char.name];
                                    return `
                                        <td class="text-center generation-cell">${stats ? stats.mean.toFixed(4) : '-'}</td>
                                        <td class="text-center generation-cell">${stats ? stats.variance.toFixed(4) : '-'}</td>
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
