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
    }
}

if ($activeCharacterId) {
    $activeCharacter = $characterModel->getById($activeCharacterId);
    $genes = $characterModel->getGenes($activeCharacterId);
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
                    <?php if (empty($characters)): ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay caracteres disponibles</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($characters as $char): ?>
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
                                    
                                    <?php if ((int)$char['creator_id'] === $userId): ?>
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
        <?php if (!$activeCharacter): ?>
        <div class="card">
            <h3>Crear Nuevo Carácter</h3>
            <form method="post">
                <input type="hidden" name="char_action" value="create">
                
                <div class="form-group">
                    <label for="char_name">Nombre del Carácter</label>
                    <input type="text" id="char_name" name="char_name" required>
                </div>
                
                <?php if ($session->isTeacher() || $session->isAdmin()): ?>
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
        
        <?php if ($activeCharacter): ?>
            <div class="card">
                <h3>Detalles del Carácter</h3>
                
                <div class="alert alert-info">
                    <strong><?= e($activeCharacter['name']) ?></strong>
                    <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                        <input type="hidden" name="char_action" value="close">
                        <button type="submit" class="btn-secondary btn-small" style="margin-left: 1rem;">Cerrar</button>
                    </form>
                </div>
                
                <?php if ($session->isTeacher() || $session->isAdmin()): ?>
                    <!-- Formulario editable para profesores/admins -->
                    <form method="post">
                        <input type="hidden" name="char_action" value="update_props">
                        <input type="hidden" name="char_id" value="<?= $activeCharacter['id'] ?>">
                        
                        <table>
                            <tr>
                                <th>Propiedad</th>
                                <th>Valor</th>
                            </tr>
                            <tr>
                                <td>ID</td>
                                <td><?= e($activeCharacter['id']) ?></td>
                            </tr>
                            <tr>
                                <td>Visible</td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="visible" <?= $activeCharacter['is_visible'] == 1 ? 'checked' : '' ?>>
                                        Sí
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>Público</td>
                                <td>
                                    <label>
                                        <input type="checkbox" name="public" <?= $activeCharacter['is_public'] == 1 ? 'checked' : '' ?>>
                                        Sí
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <td>Sustratos</td>
                                <td><?= e($activeCharacter['substrates'] ?? 0) ?></td>
                            </tr>
                        </table>
                        
                        <button type="submit" class="btn-success btn-small" style="margin-top: 1rem;">Guardar cambios</button>
                    </form>
                <?php else: ?>
                    <!-- Vista solo lectura para alumnos -->
                    <table>
                        <tr>
                            <th>Propiedad</th>
                            <th>Valor</th>
                        </tr>
                        <tr>
                            <td>ID</td>
                            <td><?= e($activeCharacter['id']) ?></td>
                        </tr>
                        <tr>
                            <td>Sustratos</td>
                            <td><?= e($activeCharacter['substrates'] ?? 0) ?></td>
                        </tr>
                    </table>
                <?php endif; ?>
                
                <?php if (!empty($genes)): ?>
                    <h4 style="margin-top: 1rem;">Genes</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Cromosoma</th>
                                <th>Posición</th>
                                <th>Código</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($genes as $gene): ?>
                                <tr>
                                    <td><?= e($gene['id']) ?></td>
                                    <td><?= e($gene['name']) ?></td>
                                    <td><?= e($gene['chromosome']) ?></td>
                                    <td><?= e($gene['position']) ?></td>
                                    <td><?= e($gene['code']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center">No hay genes definidos para este carácter.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
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
</script>
