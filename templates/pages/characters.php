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

<div class="card">
    <h2>Gestión de Caracteres</h2>
    
    <?php if ($activeCharacter): ?>
        <!-- Active character details -->
        <div class="alert alert-info">
            <strong>Carácter activo:</strong> <?= e($activeCharacter['name']) ?>
            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                <input type="hidden" name="char_action" value="close">
                <button type="submit" class="btn-secondary btn-small" style="margin-left: 1rem;">Cerrar</button>
            </form>
        </div>
        
        <h3>Detalles del Carácter</h3>
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
                <td>Nombre</td>
                <td><?= e($activeCharacter['name']) ?></td>
            </tr>
            <tr>
                <td>Visible</td>
                <td><?= $activeCharacter['visible'] === 't' ? 'Sí' : 'No' ?></td>
            </tr>
            <tr>
                <td>Público</td>
                <td><?= $activeCharacter['public'] === 't' ? 'Sí' : 'No' ?></td>
            </tr>
            <tr>
                <td>Sustratos</td>
                <td><?= e($activeCharacter['sustratos'] ?? 0) ?></td>
            </tr>
        </table>
        
        <?php if (!empty($genes)): ?>
            <h3>Genes</h3>
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
                            <td><?= e($gene['idglobal']) ?></td>
                            <td><?= e($gene['name']) ?></td>
                            <td><?= e($gene['chr']) ?></td>
                            <td><?= e($gene['pos']) ?></td>
                            <td><?= e($gene['cod']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-center">No hay genes definidos para este carácter.</p>
        <?php endif; ?>
        
    <?php endif; ?>
    
    <h3>Lista de Caracteres Disponibles</h3>
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
                    <tr>
                        <td><?= e($char['id']) ?></td>
                        <td><?= e($char['name']) ?></td>
                        <td><?= $char['public'] === 't' ? 'Sí' : 'No' ?></td>
                        <td>
                            <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;">
                                <input type="hidden" name="char_action" value="open">
                                <input type="hidden" name="char_id" value="<?= e($char['id']) ?>">
                                <button type="submit" class="btn-primary btn-small">Abrir</button>
                            </form>
                            
                            <?php if ((int)$char['creatorid'] === $userId): ?>
                                <form method="post" style="display: inline; background: none; padding: 0; margin: 0; box-shadow: none;" 
                                      onsubmit="return confirm('¿Confirmar eliminación?');">
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

<div class="card">
    <h3>Crear Nuevo Carácter</h3>
    <form method="post">
        <input type="hidden" name="char_action" value="create">
        
        <div class="form-group">
            <label for="char_name">Nombre del Carácter</label>
            <input type="text" id="char_name" name="char_name" required>
        </div>
        
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
        
        <button type="submit" class="btn-success">Crear Carácter</button>
    </form>
</div>
