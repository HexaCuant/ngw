// Helper to render parentals summary (used by generations page)
function renderGenerationParentals(groupedParentals) {
    const container = document.getElementById('generationParentals');
    if (!container) return;
    if (!groupedParentals || Object.keys(groupedParentals).length === 0) {
        container.innerHTML = '';
        return;
    }

    let html = '<div class="card"><h4>Parentales</h4>';
    html += '<table style="width:100%; margin-top:10px;"><thead><tr><th>Generación origen</th><th>Individuos</th></tr></thead><tbody>';

    const parents = Object.keys(groupedParentals).sort((a,b)=>Number(a)-Number(b));
    for (const pg of parents) {
        const ids = groupedParentals[pg] || [];
        html += '<tr>';
        html += '<td>' + escapeHtml(pg) + '</td>';
        html += '<td>' + escapeHtml(ids.join(', ')) + '</td>';
        html += '</tr>';
    }

    html += '</tbody></table></div>';
    container.innerHTML = html;
}
