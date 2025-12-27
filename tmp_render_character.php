<?php
$app = require __DIR__ . '/src/bootstrap.php';
$db = $app['db'];
$session = $app['session'];

$charId = $argv[1] ?? 8;
$charId = (int)$charId;

$characterModel = new \Ngw\Models\Character($db);
$activeCharacter = $characterModel->getById($charId);
$genes = $activeCharacter ? $characterModel->getGenes($charId) : [];
$connections = $activeCharacter ? $characterModel->getConnections($charId) : [];
$numSubstrates = (int)($activeCharacter['substrates'] ?? 0);

ob_start();
?>
<div class="card">
    <h3>Detalles del Carácter: <?= e($activeCharacter['name'] ?? 'N/A') ?></h3>
    <div id="connections-view" style="display:block; margin-top:1.5rem;">
        <h4>Conexiones del Carácter</h4>
        <?php if (!empty($connections)) : ?>
            <table id="connections-table"><thead><tr><th>Estado A</th><th>Gen (Transición)</th><th>Estado B</th></tr></thead><tbody>
            <?php foreach ($connections as $conn) : ?>
                <?php $transGene = $characterModel->getGeneById((int)$conn['transition']); $geneName = $transGene ? $transGene['name'] : 'Gen #' . $conn['transition']; ?>
                <tr><td>S<?= e($conn['state_a']) ?></td><td><?= e($geneName) ?></td><td>S<?= e($conn['state_b']) ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        <?php else: ?>
            <p class="text-center">No hay conexiones definidas para este carácter.</p>
        <?php endif; ?>

        <div style="border-top:1px solid #ccc; padding-top:1rem; margin-top:1rem;">
            <h5>Añadir nueva conexión</h5>
            <form id="substrates-form" style="margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
                <div class="form-group" style="margin:0;">
                    <label style="display:flex; align-items:center; gap:0.5rem;">Número de sustratos:
                        <input type="number" id="substrates-input" name="substrates" min="0" value="<?= e($numSubstrates) ?>" required style="width:80px;">
                    </label>
                    <small style="color:#666; margin-left:0.5rem;">(Se actualiza automáticamente)</small>
                </div>

            </form>

            <form method="post" id="add-connection-form" style="display: <?= $numSubstrates > 0 ? 'block' : 'none' ?>;">
                <input type="hidden" name="char_action" value="add_connection">
                <div class="form-group"><label>Estado inicial</label><div id="state-a-container">
                <?php for ($i=0;$i<max(0,$numSubstrates);$i++): ?>
                    <label><input type="radio" name="state_a" value="<?= $i ?>" <?= $numSubstrates>0 ? 'required' : '' ?>> S<?= $i ?></label>
                <?php endfor; ?>
                </div></div>
                <div class="form-group"><label>Gen (transición)</label>
                    <select name="transition" <?= (!empty($genes) && $numSubstrates>0) ? 'required' : '' ?>>
                        <?php if (!empty($genes)) : ?>
                            <?php foreach ($genes as $g): ?>
                                <option value="<?= e($g['id']) ?>"><?= e($g['name']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option disabled>No hay genes</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group"><label>Estado final</label><div id="state-b-container">
                <?php for ($i=0;$i<max(0,$numSubstrates);$i++): ?>
                    <label><input type="radio" name="state_b" value="<?= $i ?>" <?= $numSubstrates>0 ? 'required' : '' ?>> S<?= $i ?></label>
                <?php endfor; ?>
                </div></div>
                <button type="submit" class="btn-success" <?= $numSubstrates===0 ? 'disabled' : '' ?>>Guardar Conexión</button>
            </form>

            <?php if ($numSubstrates === 0) : ?>
                <p id="no-substrates-message" class="text-center" style="color:#b35;">Primero debes establecer el número de sustratos.</p>
                <?php if (!empty($genes)) : ?>
                    <p class="text-center" style="color:#666;">Genes disponibles: <?= implode(', ', array_map(function($g){ return e($g['name']); }, $genes)) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$html = ob_get_clean();

// Print some diagnostics and the HTML for inspection
echo "--- DIAGNOSTICS ---\n";
echo "Character ID: $charId\n";
echo "Num substr: $numSubstrates\n";
echo "Genes count: " . count($genes) . "\n";
echo "Connections count: " . count($connections) . "\n";
echo "\n--- HTML SNIPPET ---\n";
echo $html;
