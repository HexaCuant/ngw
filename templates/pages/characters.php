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
        redirect('index.php?option=1');
    } elseif ($charAction === 'close') {
        $session->remove('active_character_id');
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
    }
}

if ($activeCharacterId) {
    $activeCharacter = $characterModel->getById($activeCharacterId);
    $genes = $characterModel->getGenes($activeCharacterId);
    $activeGeneId = $session->get('active_gene_id');
    $activeGene = $activeGeneId ? $characterModel->getGeneById((int) $activeGeneId) : null;
    $alleles = $activeGene ? $characterModel->getAlleles((int) $activeGeneId) : [];
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
                                    <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                                        <input type="hidden" name="char_action" value="open">
                                        <input type="hidden" name="char_id" value="<?= e($char['id']) ?>">
                                        <button type="submit" class="btn-primary btn-small">Abrir</button>
                                    </form>
                                    
                                    <?php if ((int)$char['creator_id'] === $userId) : ?>
                                        <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" 
                                              class="delete-character-form" data-charname="<?= e($char['name']) ?>">
                                            <input type="hidden" name="char_action" value="delete">
                                            <input type="hidden" name="char_id" value="<?= e($char['id']) ?>">
                                            <input type="hidden" name="confirm" value="1">
                                            <button type="submit" class="btn-danger btn-small">Borrar</button>
                                        </form>
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
            <form method="post">
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
                        <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                            <input type="hidden" name="char_action" value="update_props">
                            <input type="hidden" name="char_id" value="<?= $activeCharacter['id'] ?>">
                            <input type="hidden" name="visible" value="<?= $activeCharacter['is_visible'] == 1 ? 1 : 0 ?>">
                            <input type="hidden" name="public" value="<?= $activeCharacter['is_public'] == 1 ? 1 : 0 ?>">
                            <button type="submit" class="btn-success">Guardar cambios</button>
                        </form>
                    <?php endif; ?>
                    
                    <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                        <input type="hidden" name="char_action" value="close">
                        <button type="submit" class="btn-secondary">Cerrar carácter</button>
                    </form>
                    
                    <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                        <input type="hidden" name="char_action" value="toggle_genes_view">
                        <button type="submit" class="btn-primary">Ver Genes</button>
                    </form>
                    
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
                                            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                                                <input type="hidden" name="char_action" value="open_gene">
                                                <input type="hidden" name="gene_id" value="<?= e($gene['id']) ?>">
                                                <button type="submit" class="btn-primary btn-small">Abrir</button>
                                            </form>

                                            <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                                <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" class="delete-gene-form" data-genename="<?= e($gene['name']) ?>">
                                                    <input type="hidden" name="char_action" value="delete_gene">
                                                    <input type="hidden" name="gene_id" value="<?= e($gene['id']) ?>">
                                                    <input type="hidden" name="confirm" value="1">
                                                    <button type="submit" class="btn-danger btn-small">Borrar</button>
                                                </form>
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
                        <form method="post">
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
                            <form method="post" style="display:inline-block; margin:0;">
                                <input type="hidden" name="char_action" value="close_gene">
                                <button type="submit" class="btn-secondary btn-small">Cerrar Gen</button>
                            </form>
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
                            <form method="post" style="margin-top: 1rem;">
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
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle genes view visibility
const genesViewBtn = document.querySelector('button[class*="btn"]');
document.querySelectorAll('form').forEach(form => {
    const action = form.querySelector('input[name="char_action"]');
    if (action && action.value === 'toggle_genes_view') {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const genesView = document.getElementById('genes-view');
            genesView.style.display = genesView.style.display === 'none' ? 'block' : 'none';
        });
    }
});

// Handle delete character confirmation
document.querySelectorAll('.delete-character-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const charName = this.getAttribute('data-charname');
        const message = '¿Estás seguro de eliminar el carácter "' + charName + '"?\n\nEsta acción no se puede deshacer y eliminará todos los genes asociados.';
        
        if (!confirm(message)) {
            e.preventDefault();
            return false;
        }
    });
});

// Confirm delete gene
document.querySelectorAll('.delete-gene-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        const genName = this.getAttribute('data-genename');
        const message = '¿Estás seguro de eliminar el gen "' + genName + '"?\n\nEsta acción no se puede deshacer.';
        if (!confirm(message)) {
            e.preventDefault();
            return false;
        }
    });
});

// Confirm delete allele
document.querySelectorAll('.delete-allele-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!confirm('¿Eliminar alelo? Esta acción no se puede deshacer.')) {
            e.preventDefault();
            return false;
        }
    });
});
</script>
