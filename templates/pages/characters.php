<?php
/**
 * Characters page template
 * @var \Ngw\Models\Character $characterModel
 * @var \Ngw\Auth\SessionManager $session
 */

$userId = $session->getUserId();
$characters = $characterModel->getAvailableCharacters($userId);
$activeCharacterId = $session->get('active_character_id');
$activeCharacter = null;

// Handle character actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $charAction = $_POST['char_action'] ?? '';

    if ($charAction === 'create' && !empty($_POST['char_name'])) {
        $name = trim($_POST['char_name']);
        $public = isset($_POST['public']);
        $visible = isset($_POST['visible']);

        try {
            $newId = $characterModel->create($name, $userId, $public, $visible);
            redirect('index.php?option=1');
        } catch (\Exception $e) {
            $error = "Error al crear carácter: " . $e->getMessage();
        }
    } elseif ($charAction === 'update_props' && !empty($_POST['char_id'])) {
        // Solo profesores y admins pueden editar propiedades
        if (!$session->isTeacher() && !$session->isAdmin()) {
            $error = "No tienes permiso para editar propiedades de caracteres";
        } else {
            $charId = (int) $_POST['char_id'];
            $char = $characterModel->getById($charId);

            // Verificar que sea propietario
            if (!$char || $char['creator_id'] !== $userId) {
                $error = "No tienes permiso para editar este carácter";
            } else {
                try {
                    $characterModel->update($charId, [
                        'visible' => isset($_POST['visible']),
                        'public' => isset($_POST['public'])
                    ]);
                    $success = "Propiedades actualizadas correctamente";
                } catch (\Exception $e) {
                    $error = "Error al actualizar: " . $e->getMessage();
                }
            }
        }
    } elseif ($charAction === 'open' && !empty($_POST['char_id'])) {
        $session->set('active_character_id', (int) $_POST['char_id']);
        // Resetear vistas al abrir un carácter
        $session->remove('show_connections');
        redirect('index.php?option=1');
    } elseif ($charAction === 'close') {
        $session->remove('active_character_id');
        $session->remove('show_connections');
        redirect('index.php?option=1');
    } elseif ($charAction === 'delete' && !empty($_POST['char_id']) && isset($_POST['confirm'])) {
        $charId = (int) $_POST['char_id'];
        if ($characterModel->isOwner($charId, $userId)) {
            $characterModel->delete($charId);
            if ($activeCharacterId === $charId) {
                $session->remove('active_character_id');
            }
        }
        redirect('index.php?option=1');
    } elseif ($charAction === 'create_gene' && $activeCharacterId && !empty($_POST['gene_name'])) {
        // Only owner or teacher/admin can add genes
        if (!$session->isTeacher() && !$session->isAdmin() && !$characterModel->isOwner($activeCharacterId, $userId)) {
            $error = "No tienes permiso para crear genes en este carácter";
        } else {
            $name = trim($_POST['gene_name']);
            $chrNum = trim($_POST['gene_chr'] ?? '');
            $pos = trim($_POST['gene_pos'] ?? '');
            $geneTypes = $_POST['gene_type'] ?? [];

            // Combine chromosome number with selected types (X, Y, A, B)
            $chr = $chrNum;
            if (!empty($geneTypes)) {
                $chr .= ' (' . implode(', ', $geneTypes) . ')';
            }

            try {
                $characterModel->addGene($activeCharacterId, $name, $chr, $pos, '');
                $success = "Gen creado correctamente";
            } catch (\Exception $e) {
                $error = "Error al crear gen: " . $e->getMessage();
            }
        }
        redirect('index.php?option=1');
    } elseif ($charAction === 'open_gene' && !empty($_POST['gene_id'])) {
        $session->set('active_gene_id', (int) $_POST['gene_id']);
        redirect('index.php?option=1');
    } elseif ($charAction === 'close_gene') {
        $session->remove('active_gene_id');
        redirect('index.php?option=1');
    } elseif ($charAction === 'delete_gene' && !empty($_POST['gene_id']) && isset($_POST['confirm'])) {
        $geneId = (int) $_POST['gene_id'];
        if ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($activeCharacterId, $userId)) {
            $characterModel->removeGene($activeCharacterId, $geneId);
        } else {
            $error = "No tienes permiso para borrar este gen";
        }
        redirect('index.php?option=1');
    } elseif ($charAction === 'add_allele' && !empty($_POST['allele_name']) && $session->get('active_gene_id')) {
        $geneId = (int) $session->get('active_gene_id');
        if ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($activeCharacterId, $userId)) {
            $name = trim($_POST['allele_name']);
            $value = $_POST['allele_value'] !== '' ? (float) $_POST['allele_value'] : null;
            $additive = isset($_POST['allele_additive']) && $_POST['allele_additive'] == '1';
            $dominance = $_POST['allele_dominance'] !== '' ? (float) $_POST['allele_dominance'] : null;
            $epistasis = isset($_POST['allele_epistasis']) ? trim($_POST['allele_epistasis']) : null;
            try {
                $characterModel->addAllele($geneId, $name, $value, $dominance, $additive, $epistasis);
                $success = "Alelo añadido";
            } catch (\Exception $e) {
                $error = "Error al añadir alelo: " . $e->getMessage();
            }
        } else {
            $error = "No tienes permiso para añadir alelos";
        }
        redirect('index.php?option=1');
    } elseif ($charAction === 'remove_allele' && !empty($_POST['allele_id']) && $session->get('active_gene_id')) {
        $geneId = (int) $session->get('active_gene_id');
        $alleleId = (int) $_POST['allele_id'];
        if ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($activeCharacterId, $userId)) {
            $characterModel->removeAllele($geneId, $alleleId);
        } else {
            $error = "No tienes permiso para eliminar alelos";
        }
        redirect('index.php?option=1');
    } elseif ($charAction === 'add_connection' && $activeCharacterId) {
        if ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($activeCharacterId, $userId)) {
            $stateA = (int) $_POST['state_a'];
            $transition = (int) $_POST['transition'];
            $stateB = (int) $_POST['state_b'];
            try {
                $characterModel->addConnection($activeCharacterId, $stateA, $transition, $stateB);
                $success = "Conexión creada correctamente";
            } catch (\Exception $e) {
                $error = "Error al crear conexión: " . $e->getMessage();
            }
        } else {
            $error = "No tienes permiso para añadir conexiones";
        }
        redirect('index.php?option=1');
    } elseif ($charAction === 'remove_connection' && !empty($_POST['connection_id']) && isset($_POST['confirm'])) {
        $connectionId = (int) $_POST['connection_id'];
        if ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($activeCharacterId, $userId)) {
            $characterModel->removeConnection($connectionId);
        } else {
            $error = "No tienes permiso para eliminar conexiones";
        }
        redirect('index.php?option=1');
    } elseif ($charAction === 'update_substrates' && $activeCharacterId) {
        if ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($activeCharacterId, $userId)) {
            $substrates = (int) $_POST['substrates'];
            $characterModel->update($activeCharacterId, ['substrates' => $substrates]);
            $success = "Número de sustratos actualizado";
            // Mantener vista de conexiones abierta
            $session->set('show_connections', true);
        } else {
            $error = "No tienes permiso para modificar sustratos";
        }
        redirect('index.php?option=1#connections');
    } elseif ($charAction === 'toggle_connections_view') {
        // Toggle del estado de vista de conexiones
        $currentState = $session->get('show_connections', false);
        $session->set('show_connections', !$currentState);
    }
}

if ($activeCharacterId) {
    $activeCharacter = $characterModel->getById($activeCharacterId);
    $genes = $characterModel->getGenes($activeCharacterId);
    $activeGeneId = $session->get('active_gene_id');
    $activeGene = $activeGeneId ? $characterModel->getGeneById((int) $activeGeneId) : null;
    $alleles = $activeGene ? $characterModel->getAlleles((int) $activeGeneId) : [];
    $connections = $characterModel->getConnections($activeCharacterId);
    $showConnections = $session->get('show_connections', false);
}
?>

<h2>Gestión de Caracteres</h2>

<div class="two-column-layout">
    <!-- Columna izquierda: Lista de caracteres -->
    <div class="column-left">
        <div class="card">
            <h3>Lista de Caracteres</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Público</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($characters)) : ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay caracteres disponibles</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($characters as $char) : ?>
                            <tr <?= $activeCharacterId === (int)$char['id'] ? 'style="background-color: var(--color-surface-light);"' : '' ?>>
                                <td><?= e($char['id']) ?></td>
                                <td><?= e($char['name']) ?></td>
                                <td><?= $char['is_public'] == 1 ? 'Sí' : 'No' ?></td>
                                <td>
                                    <button type="button" onclick="openCharacter(<?= e($char['id']) ?>)" class="btn-primary btn-small">Abrir</button>
                                    
                                    <?php if ((int)$char['creator_id'] === $userId) : ?>
                                        <button type="button" onclick="deleteCharacter(<?= e($char['id']) ?>, '<?= e(addslashes($char['name'])) ?>')" class="btn-danger btn-small">Borrar</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Columna derecha: Crear nuevo carácter y detalles -->
    <div class="column-right">
        <?php if (!$activeCharacter) : ?>
        <div class="card">
            <h3>Crear Nuevo Carácter</h3>
            <form method="post" id="create-character-form">
                <input type="hidden" name="char_action" value="create">
                
                <div class="form-group">
                    <label for="char_name">Nombre del Carácter</label>
                    <input type="text" id="char_name" name="char_name" required>
                </div>
                
                <?php if ($session->isTeacher() || $session->isAdmin()) : ?>
                    <div class="form-group form-inline">
                        <label>
                            <input type="checkbox" name="visible">
                            Visible
                        </label>
                        <label style="margin-left: 1rem;">
                            <input type="checkbox" name="public">
                            Público
                        </label>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-success">Crear Carácter</button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($activeCharacter) : ?>
            <div class="card">
                <h3>Detalles del Carácter: <?= e($activeCharacter['name']) ?></h3>
                
                <!-- Botones principales -->
                <div style="margin-bottom: 1.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <?php if ($session->isTeacher() || $session->isAdmin()) : ?>
                        <button type="button" onclick="updateCharacterProps(<?= $activeCharacter['id'] ?>, <?= $activeCharacter['is_visible'] ?>, <?= $activeCharacter['is_public'] ?>)" class="btn-success">Guardar cambios</button>
                    <?php endif; ?>
                    
                    <button type="button" onclick="closeCharacter()" class="btn-secondary">Cerrar carácter</button>
                    
                    <button type="button" class="btn-primary" id="toggle-genes-btn" onclick="toggleGenesView()">
                        Ver Genes
                    </button>
                    
                    <button type="button" class="btn-primary" id="toggle-connections-btn" onclick="toggleConnectionsView()">
                        <?= $showConnections ? 'Ocultar Conexiones' : 'Ver Conexiones' ?>
                    </button>
                    
                    <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                        <button type="button" class="btn-success" onclick="document.getElementById('create-gene-form').style.display = document.getElementById('create-gene-form').style.display === 'none' ? 'block' : 'none';">
                            Crear nuevo gen
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Tabla de Genes (oculta por defecto) -->
                <div id="genes-view" style="display: none; margin-top: 1.5rem;">
                    <?php if (!empty($genes)) : ?>
                        <h4>Genes del carácter</h4>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Cromosoma</th>
                                    <th>Posición</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($genes as $gene) : ?>
                                    <tr>
                                        <td><?= e($gene['id']) ?></td>
                                        <td><?= e($gene['name']) ?></td>
                                        <td><?= e($gene['chromosome']) ?></td>
                                        <td><?= e($gene['position']) ?></td>
                                        <td>
                                            <button type="button" onclick="openGene(<?= e($gene['id']) ?>)" class="btn-primary btn-small">Abrir</button>

                                            <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                                <button type="button" onclick="deleteGene(<?= e($gene['id']) ?>, '<?= e(addslashes($gene['name'])) ?>')" class="btn-danger btn-small">Borrar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p class="text-center">No hay genes definidos para este carácter.</p>
                    <?php endif; ?>
                </div>

                <!-- Formulario de crear gen (oculto por defecto) -->
                <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                    <div id="create-gene-form" style="display: none; margin-top: 1.5rem;">
                        <h4>Crear nuevo gen</h4>
                        <form method="post" id="create-gene-form">
                            <input type="hidden" name="char_action" value="create_gene">
                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" name="gene_name" required>
                            </div>
                            <div class="form-group">
                                <label>Cromosoma nº</label>
                                <input type="text" name="gene_chr" placeholder="Ej: 1, 2, 3...">
                            </div>
                            <div class="form-group">
                                <label>Tipo de cromosoma</label>
                                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                    <label>
                                        <input type="checkbox" name="gene_type[]" value="X"> X
                                    </label>
                                    <label>
                                        <input type="checkbox" name="gene_type[]" value="Y"> Y
                                    </label>
                                    <label>
                                        <input type="checkbox" name="gene_type[]" value="A"> A
                                    </label>
                                    <label>
                                        <input type="checkbox" name="gene_type[]" value="B"> B
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Posición</label>
                                <input type="text" name="gene_pos">
                            </div>
                            <button type="submit" class="btn-success btn-small">Crear Gen</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Vista de alelos de un gen abierto -->
                <?php if ($activeGene) : ?>
                    <div style="margin-top: 1.5rem;">
                        <h4>Gen abierto: <?= e($activeGene['name']) ?></h4>

                        <div style="margin: .5rem 0 1rem 0;">
                            <button type="button" onclick="closeGene()" class="btn-secondary btn-small">Cerrar Gen</button>
                        </div>

                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Valor</th>
                                    <th>Aditivo</th>
                                    <th>Dominancia</th>
                                    <th>Epistasis</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($alleles)) : ?>
                                    <?php foreach ($alleles as $al) : ?>
                                        <tr>
                                            <td><?= e($al['id']) ?></td>
                                            <td><?= e($al['name']) ?></td>
                                            <td><?= e($al['value']) ?></td>
                                            <td><?= e((int)$al['additive'] === 1 ? 'Sí' : 'No') ?></td>
                                            <td><?= e($al['dominance']) ?></td>
                                            <td><?= e($al['epistasis']) ?></td>
                                            <td>
                                                <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                                    <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" class="delete-allele-form">
                                                        <input type="hidden" name="char_action" value="remove_allele">
                                                        <input type="hidden" name="allele_id" value="<?= e($al['id']) ?>">
                                                        <button type="submit" class="btn-danger btn-small">Eliminar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No hay alelos definidos</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                            <form method="post" id="add-allele-form" style="margin-top: 1rem;">
                                <input type="hidden" name="char_action" value="add_allele">
                                <h5>Añadir nuevo alelo</h5>
                                <div class="form-group">
                                    <label>Nombre</label>
                                    <input type="text" name="allele_name" required>
                                </div>
                                <div class="form-group">
                                    <label>Valor</label>
                                    <input type="text" name="allele_value">
                                </div>
                                <div class="form-group">
                                    <label>Aditivo</label>
                                    <label><input type="checkbox" name="allele_additive" value="1"> Sí</label>
                                </div>
                                <div class="form-group">
                                    <label>Dominancia</label>
                                    <input type="text" name="allele_dominance">
                                </div>
                                <div class="form-group">
                                    <label>Epistasis</label>
                                    <input type="text" name="allele_epistasis">
                                </div>
                                <button type="submit" class="btn-success btn-small">Añadir Alelo</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sección de Conexiones -->
            <a name="connections"></a>
            <div id="connections-view" style="display: <?= $showConnections ? 'block' : 'none' ?>; margin-top: 1.5rem;" class="card">
                <h4>Conexiones del Carácter</h4>
                
                <!-- Mostrar conexiones existentes -->
                <?php if (!empty($connections)) : ?>
                    <table id="connections-table" style="width: 100%; margin-bottom: 1rem;">
                        <thead>
                            <tr>
                                <th>Estado A</th>
                                <th>Gen (Transición)</th>
                                <th>Estado B</th>
                                <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                    <th>Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($connections as $conn) : ?>
                                <?php
                                    // Get gene name for transition
                                    $transGene = $characterModel->getGeneById((int)$conn['transition']);
                                    $geneName = $transGene ? $transGene['name'] : 'Gen #' . $conn['transition'];
                                ?>
                                <tr>
                                    <td>S<?= e($conn['state_a']) ?></td>
                                    <td><?= e($geneName) ?></td>
                                    <td>S<?= e($conn['state_b']) ?></td>
                                    <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                        <td>
                                            <button onclick="deleteConnectionRow(this, <?= e($conn['id']) ?>)" class="btn-danger btn-small">Borrar</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="text-center">No hay conexiones definidas para este carácter.</p>
                <?php endif; ?>

                <!-- Formulario para añadir conexiones -->
                <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                    <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-top: 1rem;">
                        <h5>Añadir nueva conexión</h5>
                        
                        <!-- Update substrates form -->
                        <form method="post" id="substrates-form" style="margin-bottom: 1rem;">
                            <input type="hidden" name="char_action" value="update_substrates">
                            <div class="form-group">
                                <label>Número de sustratos: 
                                    <input type="number" 
                                           id="substrates-input" 
                                           name="substrates" 
                                           value="<?= e($activeCharacter['substrates'] ?? 0) ?>" 
                                           min="0" 
                                           max="20"
                                           style="width: 80px;">
                                </label>
                                <small style="color: var(--color-text-secondary); margin-left: 0.5rem;">
                                    (Se actualiza automáticamente)
                                </small>
                            </div>
                        </form>

                        <?php 
                        $numSubstrates = (int)($activeCharacter['substrates'] ?? 0);
                        if ($numSubstrates > 0 && !empty($genes)) : 
                        ?>
                            <form method="post" id="add-connection-form-actual">
                                <input type="hidden" name="char_action" value="add_connection">
                                
                                <div class="form-group">
                                    <label>Estado inicial (S):</label>
                                    <div id="state-a-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php for ($i = 0; $i < $numSubstrates; $i++) : ?>
                                            <label>
                                                <input type="radio" name="state_a" value="<?= $i ?>" required> S<?= $i ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Gen (Transición):</label>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php foreach ($genes as $gene) : ?>
                                            <label>
                                                <input type="radio" name="transition" value="<?= e($gene['id']) ?>" required> <?= e($gene['name']) ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Estado final (S):</label>
                                    <div id="state-b-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php for ($i = 0; $i < $numSubstrates; $i++) : ?>
                                            <label>
                                                <input type="radio" name="state_b" value="<?= $i ?>" required> S<?= $i ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <button type="submit" class="btn-success">Guardar Conexión</button>
                            </form>
                        <?php elseif ($numSubstrates === 0) : ?>
                            <p id="no-substrates-message" class="text-center" style="color: var(--color-warning);">Primero debes establecer el número de sustratos.</p>
                        <?php elseif (empty($genes)) : ?>
                            <p class="text-center" style="color: var(--color-warning);">Primero debes crear genes para este carácter.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
// Toggle genes view visibility
const genesViewBtn = document.querySelector('button[class*="btn"]');
document.querySelectorAll('form').forEach(form => {
    const action = form.querySelector('input[name="char_action"]');
    // Removed old toggle handlers - now using direct button onclick
});

// Handle delete character confirmation with AJAX
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
                
                // Add row to characters table
                const tbody = document.querySelector('table tbody');
                if (tbody) {
                    // Remove "no characters" row if exists
                    const emptyRow = tbody.querySelector('td[colspan]');
                    if (emptyRow) {
                        emptyRow.closest('tr').remove();
                    }
                    
                    const char = data.character;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${char.id}</td>
                        <td>${char.name}</td>
                        <td>${char.is_public == 1 ? 'Sí' : 'No'}</td>
                        <td>
                            <button type="button" onclick="openCharacter(${char.id})" class="btn-primary btn-small">Abrir</button>
                            <button type="button" onclick="deleteCharacter(${char.id}, '${char.name.replace(/'/g, "\\'")}')" class="btn-danger btn-small">Borrar</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                }
                
                // Reset form
                createCharacterForm.reset();
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

// Handle delete allele via AJAX
document.querySelectorAll('.delete-allele-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('¿Eliminar alelo? Esta acción no se puede deshacer.')) {
            return false;
        }
        
        const alleleId = this.querySelector('[name="allele_id"]').value;
        const row = this.closest('tr');
        
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
                row.remove();
                
                // Check if table is now empty
                const tbody = row.closest('tbody');
                if (tbody && tbody.children.length === 0) {
                    const emptyRow = document.createElement('tr');
                    emptyRow.innerHTML = '<td colspan="5" class="text-center">No hay alelos definidos</td>';
                    tbody.appendChild(emptyRow);
                }
            } else {
                showNotification(data.error || 'Error al eliminar alelo', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error de conexión', 'error');
        });
    });
});

// Confirm delete connection
document.querySelectorAll('.delete-connection-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!confirm('¿Eliminar conexión? Esta acción no se puede deshacer.')) {
            e.preventDefault();
            return false;
        }
    });
});

// Handle add allele form via AJAX
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
                
                // Add row to table
                const tbody = document.querySelector('#genes-view table tbody');
                if (tbody) {
                    // Remove "no alleles" row if it exists
                    const emptyRow = tbody.querySelector('td[colspan]');
                    if (emptyRow) {
                        emptyRow.closest('tr').remove();
                    }
                    
                    const allele = data.allele;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${allele.id}</td>
                        <td>${allele.name}</td>
                        <td>${allele.value !== null ? allele.value : ''}</td>
                        <td>${allele.dominance !== null ? allele.dominance : ''}</td>
                        <td>${allele.additive == 1 ? 'Sí' : 'No'}</td>
                        <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                            <td>
                                <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" class="delete-allele-form">
                                    <input type="hidden" name="char_action" value="remove_allele">
                                    <input type="hidden" name="allele_id" value="${allele.id}">
                                    <button type="submit" class="btn-danger btn-small">Eliminar</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    `;
                    tbody.appendChild(row);
                    
                    // Attach event listener to new delete button
                    const newForm = row.querySelector('.delete-allele-form');
                    if (newForm) {
                        newForm.addEventListener('submit', function(e) {
                            e.preventDefault();
                            
                            if (!confirm('¿Eliminar alelo? Esta acción no se puede deshacer.')) {
                                return false;
                            }
                            
                            const alleleId = this.querySelector('[name="allele_id"]').value;
                            const row = this.closest('tr');
                            
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
                                    row.remove();
                                    
                                    const tbody = document.querySelector('#genes-view table tbody');
                                    if (tbody && tbody.children.length === 0) {
                                        const emptyRow = document.createElement('tr');
                                        emptyRow.innerHTML = '<td colspan="5" class="text-center">No hay alelos definidos</td>';
                                        tbody.appendChild(emptyRow);
                                    }
                                } else {
                                    showNotification(data.error || 'Error al eliminar alelo', 'error');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                showNotification('Error de conexión', 'error');
                            });
                        });
                    }
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

// Handle create gene form via AJAX
const createGeneForm = document.getElementById('create-gene-form');
if (createGeneForm) {
    createGeneForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
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
                
                // Add row to genes table
                const tbody = document.querySelector('#genes-view > table tbody');
                if (tbody) {
                    // Remove "no genes" row if exists
                    const emptyRow = tbody.querySelector('td[colspan]');
                    if (emptyRow) {
                        emptyRow.closest('tr').remove();
                    }
                    
                    const gene = data.gene;
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${gene.id}</td>
                        <td>${gene.name}</td>
                        <td>${gene.chromosome}</td>
                        <td>${gene.position}</td>
                        <td>
                            <button type="button" onclick="openGene(${gene.id})" class="btn-primary btn-small">Abrir</button>
                            <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                <button type="button" onclick="deleteGene(${gene.id}, '${gene.name.replace(/'/g, "\\'")}')" class="btn-danger btn-small">Borrar</button>
                            <?php endif; ?>
                        </td>
                    `;
                    tbody.appendChild(row);
                }
                
                // Reset form
                createGeneForm.reset();
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
</script>

<!-- AJAX Handlers -->
<script src="js/ajax-handlers.js"></script>
<script>
// Auto-update substrates via AJAX
const substratesInputAjax = document.getElementById('substrates-input');
if (substratesInputAjax) {
    const characterId = <?= $activeCharacterId ?? 0 ?>;
    let substratesTimeout;
    
    // Remove old listeners by cloning the node
    const newInput = substratesInputAjax.cloneNode(true);
    substratesInputAjax.parentNode.replaceChild(newInput, substratesInputAjax);
    
    newInput.addEventListener('input', function() {
        clearTimeout(substratesTimeout);
        
        substratesTimeout = setTimeout(function() {
            const value = parseInt(newInput.value);
            if (!isNaN(value) && value >= 0 && characterId > 0) {
                updateSubstrates(characterId, value, function(data) {
                    reloadSubstrateSelectors(data.substrates);
                });
            }
        }, 800);
    });
    
    newInput.addEventListener('blur', function() {
        clearTimeout(substratesTimeout);
        const value = parseInt(newInput.value);
        if (!isNaN(value) && value >= 0 && characterId > 0) {
            updateSubstrates(characterId, value, function(data) {
                reloadSubstrateSelectors(data.substrates);
            });
        }
    });
}

// Handle add connection form via AJAX
const addConnectionForm = document.getElementById('add-connection-form-actual');
if (addConnectionForm) {
    addConnectionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const characterId = <?= $activeCharacterId ?? 0 ?>;
        const stateA = document.querySelector('input[name="state_a"]:checked')?.value;
        const transition = document.querySelector('input[name="transition"]:checked')?.value;
        const stateB = document.querySelector('input[name="state_b"]:checked')?.value;
        
        if (stateA !== undefined && transition && stateB !== undefined && characterId > 0) {
            addConnection(characterId, parseInt(stateA), parseInt(transition), parseInt(stateB), function(data) {
                console.log('Connection added, data:', data);
                // Add new row to connections table
                addConnectionToTable(data.connection);
                
                // Reset form
                addConnectionForm.reset();
            });
        }
    });
}

// Add connection row to table dynamically
function addConnectionToTable(connection) {
    console.log('Adding connection to table:', connection);
    let tbody = document.querySelector('#connections-table tbody');
    
    // If table doesn't exist or shows "no connections" message, create/update it
    if (!tbody) {
        // Create table structure if it doesn't exist
        const tableContainer = document.querySelector('#connections-view');
        if (tableContainer) {
            const noConnectionsMsg = tableContainer.querySelector('p.text-center');
            if (noConnectionsMsg) {
                noConnectionsMsg.remove();
            }
            
            const table = document.createElement('table');
            table.id = 'connections-table';
            table.style.width = '100%';
            table.style.marginBottom = '1rem';
            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Estado A</th>
                        <th>Gen (Transición)</th>
                        <th>Estado B</th>
                        <?php if ($session->isTeacher() || $session->isAdmin() || (int)($activeCharacter['creator_id'] ?? 0) === $userId) : ?>
                            <th>Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody></tbody>
            `;
            
            const addConnectionSection = document.querySelector('#connections-view > div[style*="border-top"]');
            if (addConnectionSection) {
                tableContainer.insertBefore(table, addConnectionSection);
            } else {
                tableContainer.appendChild(table);
            }
            
            tbody = table.querySelector('tbody');
        }
    } else {
        // Remove "no connections" row if it exists
        const noConnRow = tbody.querySelector('td[colspan]');
        if (noConnRow) {
            noConnRow.closest('tr').remove();
        }
    }
    
    if (tbody) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>S${connection.state_a}</td>
            <td>${connection.gene_name || 'Gen #' + connection.transition}</td>
            <td>S${connection.state_b}</td>
            <?php if ($session->isTeacher() || $session->isAdmin() || (int)($activeCharacter['creator_id'] ?? 0) === $userId) : ?>
                <td>
                    <button onclick="deleteConnectionRow(this, ${connection.id})" 
                            class="btn-danger btn-small">Borrar</button>
                </td>
            <?php endif; ?>
        `;
        tbody.appendChild(row);
        console.log('Row added to tbody, total rows:', tbody.children.length);
    } else {
        console.error('tbody not found!');
    }
}

// Toggle views without page reload
function toggleGenesView() {
    const genesView = document.getElementById('genes-view');
    if (genesView) {
        genesView.style.display = genesView.style.display === 'none' ? 'block' : 'none';
    }
}

function toggleConnectionsView() {
    const connectionsView = document.getElementById('connections-view');
    const btn = document.getElementById('toggle-connections-btn');
    
    if (connectionsView) {
        const isVisible = connectionsView.style.display !== 'none';
        connectionsView.style.display = isVisible ? 'none' : 'block';
        
        if (btn) {
            btn.textContent = isVisible ? 'Ver Conexiones' : 'Ocultar Conexiones';
        }
    }
}

// Disable same state selection (prevent state_a == state_b)
function setupStateValidation() {
    const stateAInputs = document.querySelectorAll('input[name="state_a"]');
    const stateBInputs = document.querySelectorAll('input[name="state_b"]');
    
    if (stateAInputs.length === 0 || stateBInputs.length === 0) return;
    
    // When state_a is selected, disable the same value in state_b
    stateAInputs.forEach(function(radioA) {
        radioA.addEventListener('change', function() {
            if (this.checked) {
                const selectedValue = this.value;
                
                // Re-enable all state_b inputs first
                stateBInputs.forEach(function(radioB) {
                    const label = radioB.closest('label');
                    radioB.disabled = false;
                    if (label) {
                        label.style.opacity = '1';
                        label.style.cursor = 'pointer';
                    }
                });
                
                // Disable the matching state_b
                stateBInputs.forEach(function(radioB) {
                    if (radioB.value === selectedValue) {
                        radioB.disabled = true;
                        radioB.checked = false; // Uncheck if it was selected
                        const label = radioB.closest('label');
                        if (label) {
                            label.style.opacity = '0.4';
                            label.style.cursor = 'not-allowed';
                        }
                    }
                });
            }
        });
    });
    
    // When state_b is selected, disable the same value in state_a
    stateBInputs.forEach(function(radioB) {
        radioB.addEventListener('change', function() {
            if (this.checked) {
                const selectedValue = this.value;
                
                // Re-enable all state_a inputs first
                stateAInputs.forEach(function(radioA) {
                    const label = radioA.closest('label');
                    radioA.disabled = false;
                    if (label) {
                        label.style.opacity = '1';
                        label.style.cursor = 'pointer';
                    }
                });
                
                // Disable the matching state_a
                stateAInputs.forEach(function(radioA) {
                    if (radioA.value === selectedValue) {
                        radioA.disabled = true;
                        radioA.checked = false; // Uncheck if it was selected
                        const label = radioA.closest('label');
                        if (label) {
                            label.style.opacity = '0.4';
                            label.style.cursor = 'not-allowed';
                        }
                    }
                });
            }
        });
    });
}

// Initialize state validation on page load
setupStateValidation();

// Delete connection and remove row from table
function deleteConnectionRow(button, connectionId) {
    if (!confirm('¿Estás seguro de que deseas borrar esta conexión?')) {
        return;
    }
    
    const row = button.closest('tr');
    
    deleteConnection(connectionId, function(data) {
        // Remove the row from the table
        row.remove();
        
        // Check if table is now empty
        const tbody = document.querySelector('#connections-table tbody');
        if (tbody && tbody.children.length === 0) {
            // Replace table with "no connections" message
            const table = document.getElementById('connections-table');
            if (table) {
                const noConnMsg = document.createElement('p');
                noConnMsg.className = 'text-center';
                noConnMsg.textContent = 'No hay conexiones definidas para este carácter.';
                table.replaceWith(noConnMsg);
            }
        }
    });
}
</script>
