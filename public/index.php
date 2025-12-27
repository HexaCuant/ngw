<?php

$app = require_once __DIR__ . '/../src/bootstrap.php';
$db = $app['db'];
$session = $app['session'];
$auth = $app['auth'];
$config = $app['config'];
$session = $app['session'];
/** @var \Ngw\Auth\Auth $auth */
$auth = $app['auth'];

// Get request parameters
$option = (int) ($_GET['option'] ?? 0);
$action = $_POST['action'] ?? '';

// Handle login/logout/registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        try {
            $user = $auth->authenticate($username, $password);
            if ($user) {
                $session->setUser($user);
                redirect('index.php');
            } else {
                $error = "Usuario o contraseña incorrectos";
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'request_account') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $role = $_POST['role'] ?? 'student'; // student or teacher
        
        try {
            if (empty($password)) {
                throw new \RuntimeException("La contraseña es obligatoria");
            }
            if ($password !== $passwordConfirm) {
                throw new \RuntimeException("Las contraseñas no coinciden");
            }
            if (strlen($password) < 6) {
                throw new \RuntimeException("La contraseña debe tener al menos 6 caracteres");
            }
            
            $requestModel = new \Ngw\Models\RegistrationRequest($db);
            $requestModel->create($username, $email, $reason, $password, $role);
            $success = "Solicitud enviada correctamente. Recibirás notificación cuando sea aprobada.";
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'logout') {
        $session->logout();
        redirect('index.php');
    }
}

// Load models if authenticated
$projectModel = null;
$characterModel = null;
$requestModel = null;

if ($session->isAuthenticated()) {
    $projectModel = new \Ngw\Models\Project($db);
    $characterModel = new \Ngw\Models\Character($db);
    $requestModel = new \Ngw\Models\RegistrationRequest($db);
}

// Handle AJAX requests (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['char_action']) && $session->isAuthenticated() && $characterModel) {
    $charAction = $_POST['char_action'];
    $userId = $session->getUserId();
    
    if ($charAction === 'create_character_ajax') {
        header('Content-Type: application/json');
        try {
            $name = trim($_POST['char_name']);
            $visible = isset($_POST['visible']) ? 1 : 0;
            $public = isset($_POST['public']) ? 1 : 0;
            
            // Only teachers and admins can set visibility and public flags
            if (!($session->isTeacher() || $session->isAdmin())) {
                $visible = 1;  // Default visible for students
                $public = 0;   // Default not public for students
            }
            
            $charId = $characterModel->create($name, $userId, $public, $visible);
            
            echo json_encode([
                'success' => true,
                'character' => [
                    'id' => $charId,
                    'name' => $name,
                    'is_public' => $public,
                    'is_visible' => $visible
                ]
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'delete_character_ajax') {
        header('Content-Type: application/json');
        try {
            $charId = (int)($_POST['char_id'] ?? 0);
            
            if ($charId > 0 && ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($charId, $userId))) {
                $characterModel->delete($charId);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'update_props_ajax') {
        header('Content-Type: application/json');
        try {
            $charId = (int)($_POST['char_id'] ?? 0);
            
            if ($charId > 0 && ($session->isTeacher() || $session->isAdmin())) {
                $visible = isset($_POST['visible']) ? (int)$_POST['visible'] : 0;
                $public = isset($_POST['public']) ? (int)$_POST['public'] : 0;
                
                $characterModel->update($charId, [
                    'is_visible' => $visible,
                    'is_public' => $public
                ]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'open_character_ajax') {
        header('Content-Type: application/json');
        try {
            $charId = (int) ($_POST['char_id'] ?? 0);
            if ($charId > 0) {
                $session->set('active_character_id', $charId);
                $session->set('show_connections', false);
                
                // Get character data
                $activeCharacter = $characterModel->getById($charId);
                $genes = $characterModel->getGenes($charId);
                $connections = $characterModel->getConnections($charId);
                $numSubstrates = (int)$activeCharacter['substrates'];
                
                // Render HTML for character details
                ob_start();
                ?>
                <div class="card">
                    <h3>Detalles del Carácter: <?= e($activeCharacter['name']) ?></h3>
                    
                    <div style="margin-bottom: 1.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" onclick="closeCharacter()" class="btn-secondary">Cerrar carácter</button>
                        <button type="button" class="btn-primary" id="toggle-genes-btn" onclick="toggleGenesView()">Ver Genes</button>
                        <button type="button" class="btn-primary" id="toggle-connections-btn" onclick="toggleConnectionsView()">Ver Conexiones</button>
                        <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                            <button type="button" class="btn-success" onclick="document.getElementById('create-gene-form-container').style.display = document.getElementById('create-gene-form-container').style.display === 'none' ? 'block' : 'none';">Crear nuevo gen</button>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Genes view -->
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
                                                <button type="button" id="gene-toggle-<?= e($gene['id']) ?>" onclick="toggleGene(<?= e($gene['id']) ?>, this)" class="btn-primary btn-small">Abrir</button>
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
                    
                    <!-- Connections view -->
                    <div id="connections-view" style="display: none; margin-top: 1.5rem;">
                        <h4>Conexiones del Carácter</h4>
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
                        
                        <!-- Visualización de red de Petri (siempre visible; mostrará placeholder si no hay conexiones) -->
                        <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-top: 1rem;">
                            <h5>Diagrama de Red de Petri</h5>
                            <div id="petri-net-diagram" style="width: 100%; min-height: 300px; border: 1px solid var(--color-border); border-radius: 4px; padding: 1rem; background: #fafafa; overflow-x: auto;">
                                <?php if (empty($connections)) : ?>
                                    <p id="petri-net-placeholder" class="text-center" style="color: var(--color-text-secondary);">No hay conexiones definidas para este carácter.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                            <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-top: 1rem;">
                                <h5>Añadir nueva conexión</h5>
                                <?php $hasConnectionsAjax = !empty($connections); ?>
                                <form method="post" id="substrates-form" style="margin-bottom: 1rem; display:flex; align-items:center; gap:0.5rem;">
                                    <div class="form-group" style="margin:0;">
                                        <label style="display:flex; align-items:center; gap:0.5rem;">Número de sustratos (estados)
                                            <input type="number" id="substrates-input" name="substrates" min="0" value="<?= e($numSubstrates) ?>" required style="width: 80px;" data-has-connections="<?= $hasConnectionsAjax ? '1' : '0' ?>">
                                        </label>
                                        <small style="color: var(--color-text-secondary); margin-left: 0.5rem;">(Se actualiza automáticamente)</small>
                                    </div>

                                </form>
                                
                                <?php 
                                // Render the add-connection form as in the main template: radio lists for states and radio inputs for transition
                                $numSubstratesSafe = max(0, $numSubstrates);
                                ?>
                                <form method="post" id="add-connection-form" style="display: <?= $numSubstratesSafe > 0 ? 'block' : 'none' ?>;">
                                    <input type="hidden" name="char_action" value="add_connection">

                                    <div class="form-group">
                                        <label>Estado inicial (S):</label>
                                        <div id="state-a-container" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                            <?php for ($i = 0; $i < $numSubstratesSafe; $i++) : ?>
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
                                            <?php for ($i = 0; $i < $numSubstratesSafe; $i++) : ?>
                                                <label>
                                                    <input type="radio" name="state_b" value="<?= $i ?>" required> S<?= $i ?>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn-success">Guardar Conexión</button>
                                </form>
                                <?php if ($numSubstratesSafe === 0) : ?>
                                    <p id="no-substrates-message" class="text-center" style="color: var(--color-warning);">Primero debes establecer el número de sustratos.</p>
                                    <?php if (!empty($genes)) : ?>
                                        <p class="text-center" style="color: var(--color-text-secondary);">Genes disponibles: <?= implode(', ', array_map(function($g){ return e($g['name']); }, $genes)) ?></p>
                                    <?php endif; ?>
                                <?php elseif (empty($genes)) : ?>
                                    <p class="text-center" style="color: var(--color-warning);">Primero debes crear genes para este carácter.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Create gene form -->
                    <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                        <div id="create-gene-form-container" style="display: none; margin-top: 1.5rem;">
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
                                        <label><input type="checkbox" name="gene_type[]" value="X"> X</label>
                                        <label><input type="checkbox" name="gene_type[]" value="Y"> Y</label>
                                        <label><input type="checkbox" name="gene_type[]" value="A" checked> A</label>
                                        <label><input type="checkbox" name="gene_type[]" value="B" checked> B</label>
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
                </div>
                <?php
                $html = ob_get_clean();
                
                echo json_encode(['success' => true, 'html' => $html]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de carácter inválido']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'close_character_ajax') {
        header('Content-Type: application/json');
        try {
            $session->remove('active_character_id');
            $session->remove('active_gene_id');
            $session->remove('show_connections');
            
            // Generate create form HTML
            $isTeacher = $session->isTeacher();
            $isAdmin = $session->isAdmin();
            $formHtml = '<div class="card">';
            $formHtml .= '<h3>Crear Nuevo Carácter</h3>';
            $formHtml .= '<form method="post" id="create-character-form">';
            $formHtml .= '<input type="hidden" name="char_action" value="create">';
            $formHtml .= '<div class="form-group">';
            $formHtml .= '<label for="char_name">Nombre del Carácter</label>';
            $formHtml .= '<input type="text" id="char_name" name="char_name" required>';
            $formHtml .= '</div>';
            
            if ($isTeacher || $isAdmin) {
                $formHtml .= '<div class="form-group form-inline">';
                $formHtml .= '<label><input type="checkbox" name="visible"> Visible</label>';
                $formHtml .= '<label style="margin-left: 1rem;"><input type="checkbox" name="public"> Público</label>';
                $formHtml .= '</div>';
            }
            
            $formHtml .= '<button type="submit" class="btn-success">Crear Carácter</button>';
            $formHtml .= '</form>';
            $formHtml .= '</div>';
            
            echo json_encode(['success' => true, 'html' => $formHtml]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'update_substrates_ajax') {
        header('Content-Type: application/json');
        try {
            $charId = (int) ($_POST['char_id'] ?? 0);
            if ($charId > 0 && ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($charId, $userId))) {
                // Prevent changing substrates if there are already connections defined
                $existingConnections = $characterModel->getConnections($charId);
                if (!empty($existingConnections)) {
                    echo json_encode(['success' => false, 'error' => 'No puedes cambiar el número de sustratos mientras existan conexiones definidas. Elimina primero las conexiones.']);
                    exit;
                }

                $substrates = (int) $_POST['substrates'];
                $characterModel->update($charId, ['substrates' => $substrates]);
                $session->set('show_connections', true);
                echo json_encode(['success' => true, 'substrates' => $substrates]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'add_connection_ajax') {
        header('Content-Type: application/json');
        try {
            $charId = (int) ($_POST['char_id'] ?? 0);
            if ($charId > 0 && ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($charId, $userId))) {
                $stateA = (int) $_POST['state_a'];
                $transition = (int) $_POST['transition'];
                $stateB = (int) $_POST['state_b'];
                $connId = $characterModel->addConnection($charId, $stateA, $transition, $stateB);
                
                // Get gene name
                $gene = $characterModel->getGeneById($transition);
                $geneName = $gene ? $gene['name'] : 'Gen #' . $transition;
                
                echo json_encode([
                    'success' => true,
                    'connection' => [
                        'id' => $connId,
                        'state_a' => $stateA,
                        'transition' => $transition,
                        'state_b' => $stateB,
                        'gene_name' => $geneName
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'remove_connection_ajax') {
        header('Content-Type: application/json');
        try {
            $connectionId = (int) $_POST['connection_id'];
            $activeCharacterId = (int)($session->get('active_character_id') ?? 0);
            
            // Verify permissions: teacher, admin, or character creator
            $canDelete = false;
            if ($session->isTeacher() || $session->isAdmin()) {
                $canDelete = true;
            } elseif ($activeCharacterId > 0) {
                $character = $characterModel->getById($activeCharacterId);
                if ($character && (int)$character['creator_id'] === $userId) {
                    $canDelete = true;
                }
            }
            
            if ($canDelete) {
                $characterModel->removeConnection($connectionId);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'add_allele_ajax') {
        header('Content-Type: application/json');
        try {
            $geneId = (int) ($session->get('active_gene_id') ?? 0);
            $activeCharId = (int) ($session->get('active_character_id') ?? 0);
            if ($geneId > 0 && ($session->isTeacher() || $session->isAdmin() || ($activeCharId > 0 && $characterModel->isOwner($activeCharId, $userId)))) {
                $name = trim($_POST['allele_name']);
                $value = $_POST['allele_value'] !== '' ? (float) $_POST['allele_value'] : null;
                $additive = isset($_POST['allele_additive']) && $_POST['allele_additive'] == '1';
                $dominance = $_POST['allele_dominance'] !== '' ? (float) $_POST['allele_dominance'] : null;
                $epistasis = isset($_POST['allele_epistasis']) ? trim($_POST['allele_epistasis']) : null;
                
                $alleleId = $characterModel->addAllele($geneId, $name, $value, $dominance, $additive, $epistasis);
                
                echo json_encode([
                    'success' => true,
                    'allele' => [
                        'id' => $alleleId,
                        'name' => $name,
                        'value' => $value,
                        'dominance' => $dominance,
                        'additive' => $additive ? 1 : 0,
                        'epistasis' => $epistasis
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'remove_allele_ajax') {
        header('Content-Type: application/json');
        try {
            $geneId = (int) ($session->get('active_gene_id') ?? 0);
            $alleleId = (int) $_POST['allele_id'];
            if ($geneId > 0 && ($session->isTeacher() || $session->isAdmin())) {
                $characterModel->removeAllele($geneId, $alleleId);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'open_gene_ajax') {
        header('Content-Type: application/json');
        try {
            $geneId = (int) ($_POST['gene_id'] ?? 0);
            if ($geneId > 0) {
                $session->set('active_gene_id', $geneId);
                
                // Get gene and alleles data
                $activeGene = $characterModel->getGeneById($geneId);
                $alleles = $characterModel->getAlleles($geneId);
                $activeCharacterId = $session->get('active_character_id');
                $activeCharacter = $characterModel->getById($activeCharacterId);
                
                // Render HTML for alleles section
                ob_start();
                ?>
                <div id="alleles-section" data-gene-id="<?= (int)$activeGene['id'] ?>" style="margin-top: 1.5rem;">
                    <h4>Gen abierto: <?= htmlspecialchars($activeGene['name']) ?></h4>
                    <table style="width: 100%; margin-top: 1rem;">
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
                                        <td><?= htmlspecialchars($al['id']) ?></td>
                                        <td><?= htmlspecialchars($al['name']) ?></td>
                                        <td><?= htmlspecialchars($al['value']) ?></td>
                                        <td><?= htmlspecialchars((int)$al['additive'] === 1 ? 'Sí' : 'No') ?></td>
                                        <td><?= htmlspecialchars($al['dominance']) ?></td>
                                        <td><?= htmlspecialchars($al['epistasis']) ?></td>
                                        <td>
                                            <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                                <button onclick="deleteAllele(<?= $al['id'] ?>, () => location.reload())" class="btn-danger btn-small">Eliminar</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay alelos definidos</td>
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
                <?php
                $html = ob_get_clean();
                
                echo json_encode(['success' => true, 'html' => $html]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de gen inválido']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'close_gene_ajax') {
        header('Content-Type: application/json');
        try {
            $session->remove('active_gene_id');
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'create_gene_ajax') {
        header('Content-Type: application/json');
        try {
            $charId = (int)($session->get('active_character_id') ?? 0);
            if ($charId > 0 && ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($charId, $userId))) {
                $name = trim($_POST['gene_name']);
                $chr = isset($_POST['gene_chr']) ? trim($_POST['gene_chr']) : null;
                $type = isset($_POST['gene_type']) ? implode('', $_POST['gene_type']) : '';
                $pos = isset($_POST['gene_pos']) ? trim($_POST['gene_pos']) : null;

                // Server-side validation: ensure at least one type is selected
                if (empty($type)) {
                    echo json_encode(['success' => false, 'error' => 'Selecciona al menos un tipo de cromosoma (X, Y, A o B)']);
                    exit;
                }
                
                // Note: addGene signature is (characterId, name, chromosome, position, code)
                $geneId = $characterModel->addGene($charId, $name, $chr, $pos, $type);
                
                // Build chromosome display
                $chrDisplay = '';
                if ($chr) {
                    $chrDisplay = $chr;
                    if ($type) {
                        $chrDisplay .= ' (' . $type . ')';
                    }
                } elseif ($type) {
                    $chrDisplay = $type;
                }
                
                echo json_encode([
                    'success' => true,
                    'gene' => [
                        'id' => $geneId,
                        'name' => $name,
                        'chromosome' => $chrDisplay,
                        'position' => $pos ?? ''
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'delete_gene_ajax') {
        header('Content-Type: application/json');
        try {
            $geneId = (int)($_POST['gene_id'] ?? 0);
            $charId = (int)($session->get('active_character_id') ?? 0);
            
            if ($charId > 0 && ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($charId, $userId))) {
                $characterModel->removeGene($charId, $geneId);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Handle AJAX requests for projects
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_action']) && $session->isAuthenticated() && $projectModel) {
    $projectAction = $_POST['project_action'];
    $userId = $session->getUserId();
    
    if ($projectAction === 'create_project_ajax') {
        header('Content-Type: application/json');
        try {
            $name = trim($_POST['project_name']);
            $projectId = $projectModel->create($name, $userId);
            
            echo json_encode([
                'success' => true,
                'project' => [
                    'id' => $projectId,
                    'name' => $name
                ]
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'delete_project_ajax') {
        header('Content-Type: application/json');
        try {
            $projectId = (int)($_POST['project_id'] ?? 0);
            
            if ($projectId > 0 && $projectModel->isOwner($projectId, $userId)) {
                $projectModel->delete($projectId);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'open_project_ajax') {
        header('Content-Type: application/json');
        try {
            $projectId = (int) ($_POST['project_id'] ?? 0);
            if ($projectId > 0) {
                $session->set('active_project_id', $projectId);
                
                // Get project data
                $activeProject = $projectModel->getById($projectId);
                $projectCharacters = $projectModel->getCharacters($projectId);
                
                // Render HTML for project details
                ob_start();
                ?>
                <div class="alert alert-info">
                    <strong>Proyecto activo:</strong> <?= htmlspecialchars($activeProject['name']) ?> (ID: <?= htmlspecialchars($activeProject['id']) ?>)
                    <button type="button" onclick="closeProject()" class="btn-secondary btn-small" style="margin-left: 1rem;">Cerrar Proyecto</button>
                </div>
                
                <h3>Caracteres del Proyecto</h3>
                <?php if (!empty($projectCharacters)) : ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Ambiente</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projectCharacters as $char) : ?>
                                <tr id="project-char-<?= htmlspecialchars($char['character_id']) ?>">
                                    <td><?= htmlspecialchars($char['character_id']) ?></td>
                                    <td><?= htmlspecialchars($char['name']) ?></td>
                                    <td>
                                        <input type="number" 
                                               id="env-<?= htmlspecialchars($char['character_id']) ?>" 
                                               value="<?= htmlspecialchars($char['environment']) ?>" 
                                               step="1"
                                               style="width: 100px;"
                                               oninput="markEnvironmentModified(<?= htmlspecialchars($char['character_id']) ?>)">
                                    </td>
                                    <td>
                                        <button type="button" 
                                                onclick="updateEnvironment(<?= htmlspecialchars($char['character_id']) ?>)" 
                                                class="btn-primary btn-small">Actualizar</button>
                                        <button type="button" 
                                                onclick="removeCharacterFromProject(<?= htmlspecialchars($char['character_id']) ?>, '<?= htmlspecialchars(addslashes($char['name'])) ?>')" 
                                                class="btn-danger btn-small">Borrar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p class="text-center">No hay caracteres asignados a este proyecto.</p>
                    <p class="text-center">Ve a la sección <a href="index.php?option=1">Caracteres</a> para añadir caracteres al proyecto.</p>
                <?php endif; ?>
                <?php
                $html = ob_get_clean();
                
                echo json_encode(['success' => true, 'html' => $html]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de proyecto inválido']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'close_project_ajax') {
        header('Content-Type: application/json');
        try {
            $session->remove('active_project_id');
            
            // Generate HTML for projects list and create form
            $projects = $projectModel->getUserProjects($userId);
            
            ob_start();
            ?>
            <h3>Mis Proyectos</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)) : ?>
                        <tr>
                            <td colspan="3" class="text-center">No tienes proyectos creados</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($projects as $project) : ?>
                            <tr>
                                <td><?= e($project['id']) ?></td>
                                <td><?= e($project['name']) ?></td>
                                <td>
                                    <button type="button" onclick="openProject(<?= e($project['id']) ?>)" class="btn-primary btn-small">Abrir</button>
                                    <button type="button" onclick="deleteProject(<?= e($project['id']) ?>, '<?= e(addslashes($project['name'])) ?>')" class="btn-danger btn-small">Borrar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
            $projectsHtml = ob_get_clean();
            
            ob_start();
            ?>
            <div class="card">
                <h3>Crear Nuevo Proyecto</h3>
                <form id="create-project-form">
                    <div class="form-group">
                        <label for="project_name">Nombre del Proyecto</label>
                        <input type="text" id="project_name" name="project_name" required 
                               placeholder="Ej: Simulación de población 2025">
                    </div>
                    
                    <button type="submit" class="btn-success">Crear Proyecto</button>
                </form>
            </div>
            <?php
            $formHtml = ob_get_clean();
            
            echo json_encode([
                'success' => true,
                'projectsHtml' => $projectsHtml,
                'formHtml' => $formHtml
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'add_character_to_project') {
        header('Content-Type: application/json');
        try {
            $characterId = (int)($_POST['character_id'] ?? 0);
            $projectId = $session->get('active_project_id');
            
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }
            
            if ($characterId > 0) {
                $projectModel->addCharacter($projectId, $characterId);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de carácter inválido']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'update_environment') {
        header('Content-Type: application/json');
        try {
            $characterId = (int)($_POST['character_id'] ?? 0);
            $environment = (float)($_POST['environment'] ?? 0);
            $projectId = $session->get('active_project_id');
            
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }
            
            if ($characterId > 0) {
                $projectModel->updateCharacterEnvironment($projectId, $characterId, $environment);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de carácter inválido']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'remove_character_from_project') {
        header('Content-Type: application/json');
        try {
            $characterId = (int)($_POST['character_id'] ?? 0);
            $projectId = $session->get('active_project_id');
            
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }
            
            if ($characterId > 0) {
                $projectModel->removeCharacter($projectId, $characterId);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de carácter inválido']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'create_random_generation') {
        header('Content-Type: application/json');
        
        // Prevent any output before JSON
        ob_start();
        
        try {
            $projectId = $session->get('active_project_id');
            
            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }
            
            $populationSize = (int)($_POST['population_size'] ?? 0);
            
            if ($populationSize <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'El tamaño de población debe ser mayor que 0']);
                exit;
            }
            
            // Create generation model
            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);
            
            // Get next generation number
            $generationNumber = $generationModel->getNextGenerationNumber($projectId);
            
            // Generate POC file
            $pocPath = $projectModel->generatePocFile($projectId, $populationSize, $generationNumber, 'random');
            
            // Execute gengine
            $result = $generationModel->executeGengine($projectId);
            
            if (!$result['success']) {
                ob_end_clean();
                echo json_encode([
                    'success' => false, 
                    'error' => 'Error al ejecutar gengine (código ' . $result['return_code'] . ')'
                ]);
                exit;
            }
            
            // Create generation record
            $generationModel->create($projectId, $generationNumber, $populationSize, 'random');
            // Fetch created generation metadata
            $generation = $generationModel->getByNumber($projectId, $generationNumber);
            
            // Parse output and get individuals (sorted in the model)
            $individuals = $generationModel->parseGenerationOutput($projectId, $generationNumber);

            // Reindex as list to preserve order on JSON (avoid JS reordering numeric keys)
            $individualsList = [];
            foreach ($individuals as $id => $phenotypes) {
                $individualsList[] = [
                    'id' => $id,
                    'phenotypes' => $phenotypes
                ];
            }
            
            // Calculate statistics
            $stats = $generationModel->calculateStatistics($individuals);

            // Clear any debug output
            ob_end_clean();
            
            echo json_encode([
                'success' => true,
                'generation_number' => $generationNumber,
                'type' => $generation['type'] ?? 'random',
                'created_at' => $generation['created_at'] ?? null,
                'individuals' => $individualsList,
                'population_size' => count($individuals),
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'create_cross_generation') {
        header('Content-Type: application/json');
        ob_start();

        try {
            $projectId = $session->get('active_project_id');

            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }

            $populationSize = (int)($_POST['population_size'] ?? 0);
            $generationNumber = (int)($_POST['generation_number'] ?? 0);

            if ($populationSize <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'El tamaño de población debe ser mayor que 0']);
                exit;
            }
            if ($generationNumber <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Número de generación inválido']);
                exit;
            }

            // Create generation model
            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);

            // Prevent overwriting existing generation
            $existing = $generationModel->getByNumber($projectId, $generationNumber);
            if ($existing) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Ya existe una generación con ese número']);
                exit;
            }

            // Generate POC file for cross (must have parentals added beforehand)
            // Ensure parentals exist for the target generation
            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);
            $parentals = $generationModel->getParentals($projectId, $generationNumber);
            if (empty($parentals)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay parentales asignados para la generación objetivo']);
                exit;
            }

            $pocPath = $projectModel->generatePocFile($projectId, $populationSize, $generationNumber, 'cross');

            // Execute gengine
            $result = $generationModel->executeGengine($projectId);

            if (!$result['success']) {
                ob_end_clean();
                echo json_encode([
                    'success' => false, 
                    'error' => 'Error al ejecutar gengine (código ' . $result['return_code'] . ')'
                ]);
                exit;
            }

            // Create generation record
            $generationModel->create($projectId, $generationNumber, $populationSize, 'cross');
            $generation = $generationModel->getByNumber($projectId, $generationNumber);

            // Parse output and get individuals
            $individuals = $generationModel->parseGenerationOutput($projectId, $generationNumber);

            $individualsList = [];
            foreach ($individuals as $id => $phenotypes) {
                $individualsList[] = [
                    'id' => $id,
                    'phenotypes' => $phenotypes
                ];
            }

            // Include grouped parentals for the newly created cross generation so the client can display them immediately
            $parentals = $generationModel->getParentals($projectId, $generationNumber);
            $groupedParentals = [];
            foreach ($parentals as $p) {
                $groupedParentals[$p['parent_generation_number']][] = (int)$p['individual_id'];
            }

            // Calculate statistics for current generation
            $stats = $generationModel->calculateStatistics($individuals);

            // Calculate statistics for parentals
            $parentalStats = [];
            if (!empty($groupedParentals)) {
                $parentalIndividuals = [];
                foreach ($groupedParentals as $pgNum => $ids) {
                    try {
                        $pgIndividuals = $generationModel->parseGenerationOutput($projectId, (int)$pgNum);
                        foreach ($ids as $id) {
                            if (isset($pgIndividuals[$id])) {
                                $parentalIndividuals[] = $pgIndividuals[$id];
                            }
                        }
                    } catch (\Exception $e) {
                        // Skip if parent generation file not found
                    }
                }
                if (!empty($parentalIndividuals)) {
                    $parentalStats = $generationModel->calculateStatistics($parentalIndividuals);
                }
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'generation_number' => $generationNumber,
                'type' => $generation['type'] ?? 'cross',
                'created_at' => $generation['created_at'] ?? null,
                'individuals' => $individualsList,
                'population_size' => count($individuals),
                'parentals' => $groupedParentals,
                'stats' => $stats,
                'parental_stats' => $parentalStats
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'create_multiple_crosses') {
        header('Content-Type: application/json');
        ob_start();

        try {
            $projectId = $session->get('active_project_id');
            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }

            $sourceGen = (int)($_POST['source_generation'] ?? 0);
            $numIndiv = (int)($_POST['individuals_per_cross'] ?? 0);
            $numCrosses = (int)($_POST['number_of_crosses'] ?? 0);
            $populationSize = (int)($_POST['population_size'] ?? 0);
            $crossType = ($_POST['cross_type'] ?? 'associative'); // 'associative'|'random'

            if ($sourceGen <= 0 || $numIndiv <= 0 || $numCrosses <= 0 || $populationSize <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
                exit;
            }

            require_once __DIR__ . '/../src/Models/Generation.php';
            require_once __DIR__ . '/../src/Models/Project.php';
            $generationModel = new \Ngw\Models\Generation($db);
            $projectModel = new \Ngw\Models\Project($db);

            // Ensure source exists
            $src = $generationModel->getByNumber($projectId, $sourceGen);
            if (!$src) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Generación fuente no encontrada']);
                exit;
            }

            // Get ordered individuals for source generation
            $orderedIndividuals = $generationModel->parseGenerationOutput($projectId, $sourceGen);
            $orderedIds = array_values(array_keys($orderedIndividuals));
            $availableCount = count($orderedIds);
            $totalNeeded = $numIndiv * $numCrosses;

            if ($totalNeeded > $availableCount) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay suficientes individuos únicos en la generación fuente para crear todos los cruces sin reemplazo']);
                exit;
            }

            $results = [];
            $used = [];

            // Reserve starting generation number
            $startGen = $generationModel->getNextGenerationNumber($projectId);

            for ($i = 0; $i < $numCrosses; $i++) {
                $targetGen = $startGen + $i;

                // double-check target does not exist
                $existing = $generationModel->getByNumber($projectId, $targetGen);
                if ($existing) {
                    $results[] = ['generation_number' => $targetGen, 'success' => false, 'error' => 'La generación destino ya existe'];
                    continue;
                }

                // Selection without replacement across the whole batch
                $selected = [];
                if ($crossType === 'random') {
                    // sample without replacement from remaining ids
                    $remaining = array_values(array_diff($orderedIds, $used));
                    shuffle($remaining);
                    $selected = array_slice($remaining, 0, $numIndiv);
                } else {
                    // associative: find contiguous block of length numIndiv where none are used
                    $len = count($orderedIds);
                    $candidates = [];
                    for ($s = 0; $s <= $len - $numIndiv; $s++) {
                        $slice = array_slice($orderedIds, $s, $numIndiv);
                        $ok = true;
                        foreach ($slice as $id) {
                            if (in_array($id, $used, true)) { $ok = false; break; }
                        }
                        if ($ok) $candidates[] = $s;
                    }
                    if (empty($candidates)) {
                        $results[] = ['generation_number' => $targetGen, 'success' => false, 'error' => 'No hay bloques disponibles para selección asociativa'];
                        continue;
                    }
                    $startIdx = $candidates[array_rand($candidates)];
                    $selected = array_slice($orderedIds, $startIdx, $numIndiv);
                }

                // Mark used
                foreach ($selected as $sid) $used[] = $sid;

                // Insert parentals for this child generation
                $inserted = $generationModel->addParentals($projectId, $targetGen, $sourceGen, array_map('intval', $selected));

                // Build POC and execute engine
                $poc = $projectModel->generatePocFile($projectId, $populationSize, $targetGen, 'cross');
                $execRes = $generationModel->executeGengine($projectId);
                if (!$execRes['success']) {
                    $results[] = ['generation_number' => $targetGen, 'success' => false, 'error' => 'Error al ejecutar gengine (código ' . $execRes['return_code'] . ')'];
                    continue;
                }

                // Create generation record and parse output
                $generationModel->create($projectId, $targetGen, $populationSize, 'cross');
                $generation = $generationModel->getByNumber($projectId, $targetGen);
                $individuals = $generationModel->parseGenerationOutput($projectId, $targetGen);
                $individualsList = [];
                foreach ($individuals as $id => $phenotypes) {
                    $individualsList[] = ['id' => $id, 'phenotypes' => $phenotypes];
                }

                $results[] = ['generation_number' => $targetGen, 'success' => true, 'created_at' => $generation['created_at'] ?? null, 'population_size' => count($individualsList), 'individuals' => $individualsList, 'parentals' => [$sourceGen => $selected]];
            }

            ob_end_clean();
            echo json_encode(['success' => true, 'results' => $results]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'get_generation_details') {
        header('Content-Type: application/json');
        ob_start();
        
        try {
            $projectId = $session->get('active_project_id');
            
            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }
            
            $generationNumber = (int)($_POST['generation_number'] ?? 0);
            
            if ($generationNumber <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Número de generación inválido']);
                exit;
            }
            
            // Create generation model
            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);
            
            // Get generation details
            $generation = $generationModel->getByNumber($projectId, $generationNumber);
            
            if (!$generation) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Generación no encontrada']);
                exit;
            }
            
            // Parse individuals from .dat file (sorted in model)
            $individuals = $generationModel->parseGenerationOutput($projectId, $generationNumber);

            // Reindex as list to preserve order on JSON (avoid JS reordering numeric keys)
            $individualsList = [];
            foreach ($individuals as $id => $phenotypes) {
                $individualsList[] = [
                    'id' => $id,
                    'phenotypes' => $phenotypes
                ];
            }
            
            // Optionally include parentals for a provided target generation (used in parent selection)
            $targetGenParam = isset($_POST['target_generation']) ? (int)$_POST['target_generation'] : $generationNumber;
            $parentals = $generationModel->getParentals($projectId, $targetGenParam);
            $groupedParentals = [];
            foreach ($parentals as $p) {
                $groupedParentals[$p['parent_generation_number']][] = (int)$p['individual_id'];
            }

            // Calculate statistics for current generation
            $stats = $generationModel->calculateStatistics($individuals);

            // Calculate statistics for parentals if it's a cross generation
            $parentalStats = [];
            if ($generation['type'] === 'cross' && !empty($groupedParentals)) {
                $parentalIndividuals = [];
                foreach ($groupedParentals as $pgNum => $ids) {
                    try {
                        $pgIndividuals = $generationModel->parseGenerationOutput($projectId, (int)$pgNum);
                        foreach ($ids as $id) {
                            if (isset($pgIndividuals[$id])) {
                                $parentalIndividuals[] = $pgIndividuals[$id];
                            }
                        }
                    } catch (\Exception $e) {
                        // Skip if parent generation file not found
                    }
                }
                if (!empty($parentalIndividuals)) {
                    $parentalStats = $generationModel->calculateStatistics($parentalIndividuals);
                }
            }

            ob_end_clean();
            
            echo json_encode([
                'success' => true,
                'generation_number' => $generation['generation_number'],
                'type' => $generation['type'],
                'population_size' => $generation['population_size'],
                'created_at' => $generation['created_at'],
                'individuals' => $individualsList,
                'parentals' => $groupedParentals,
                'stats' => $stats,
                'parental_stats' => $parentalStats
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'delete_generation') {
        header('Content-Type: application/json');
        ob_start();
        
        try {
            $projectId = $session->get('active_project_id');
            
            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }
            
            $generationNumber = (int)($_POST['generation_number'] ?? 0);
            
            if ($generationNumber <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Número de generación inválido']);
                exit;
            }
            
            // Create generation model
            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);
            
            // Delete generation
            $success = $generationModel->delete($projectId, $generationNumber);
            
            if (!$success) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Error al borrar la generación']);
                exit;
            }
            
            ob_end_clean();
            
            echo json_encode([
                'success' => true,
                'message' => 'Generación borrada con éxito'
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'add_parental') {
        header('Content-Type: application/json');
        ob_start();

        try {
            $projectId = $session->get('active_project_id');
            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }

            $targetGen = (int)($_POST['generation_number'] ?? 0);
            $parentGen = (int)($_POST['parent_generation_number'] ?? 0);
            $individuals = $_POST['individual_ids'] ?? [];

            if ($targetGen <= 0 || $parentGen <= 0 || empty($individuals)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
                exit;
            }

            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);

            // Ensure individuals array
            if (!is_array($individuals)) {
                $individuals = [$individuals];
            }

            $inserted = $generationModel->addParentals($projectId, $targetGen, $parentGen, array_map('intval', $individuals));
            // Disallow adding parentals if the target generation already exists (children already generated)
            $existingGen = $generationModel->getByNumber($projectId, $targetGen);
            if ($existingGen) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No se pueden agregar parentales: la generación objetivo ya existe']);
                exit;
            }

            ob_end_clean();
            echo json_encode(['success' => true, 'inserted' => $inserted]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'get_parentals') {
        header('Content-Type: application/json');
        ob_start();

        try {
            $projectId = $session->get('active_project_id');
            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }

            $targetGen = (int)($_POST['generation_number'] ?? 0);
            if ($targetGen <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Número de generación inválido']);
                exit;
            }

            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);

            $parentals = $generationModel->getParentals($projectId, $targetGen);

            // Also return whether the target generation already exists (to disable deletions)
            $existingGen = $generationModel->getByNumber($projectId, $targetGen);

            ob_end_clean();
            echo json_encode(['success' => true, 'parentals' => $parentals, 'target_exists' => $existingGen ? true : false]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'delete_parental') {
        header('Content-Type: application/json');
        ob_start();

        try {
            $projectId = $session->get('active_project_id');
            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }

            $targetGen = (int)($_POST['generation_number'] ?? 0);
            $parentGen = (int)($_POST['parent_generation_number'] ?? 0);
            $individualId = (int)($_POST['individual_id'] ?? 0);

            if ($targetGen <= 0 || $parentGen <= 0 || $individualId <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
                exit;
            }

            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);

            $ok = $generationModel->deleteParental($projectId, $targetGen, $parentGen, $individualId);

            // Disallow deleting parentals if the target generation already exists
            $existingGen = $generationModel->getByNumber($projectId, $targetGen);
            if ($existingGen) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No se pueden borrar parentales: la generación objetivo ya existe (ya se generaron individuos)']);
                exit;
            }

            ob_end_clean();
            echo json_encode(['success' => $ok]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Error al borrar parental: ' . $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'get_project_summary_ajax') {
        header('Content-Type: application/json');
        ob_start();

        try {
            $projectId = $session->get('active_project_id');
            if (!$projectId) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }

            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);
            $summary = $generationModel->getProjectSummary($projectId);
            $projectCharacters = $projectModel->getCharacters($projectId);

            ob_end_clean();
            echo json_encode([
                'success' => true, 
                'summary' => $summary,
                'characters' => $projectCharacters
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'check_new_generations_ajax') {
        header('Content-Type: application/json');
        try {
            $projectId = $session->get('active_project_id');
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }

            $sql = "SELECT COUNT(*) as count FROM generations WHERE project_id = :project_id";
            $result = $db->fetchOne($sql, ['project_id' => $projectId]);
            
            echo json_encode([
                'success' => true, 
                'count' => (int)$result['count']
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="cache-control" content="no-cache">
    <title>GenWeb NG - Sistema de Generaciones Genéticas</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <script src="js/ajax-handlers.js?v=<?= time() ?>" defer></script>
    <script src="js/project-handlers.js?v=<?= time() ?>" defer></script>
</head>
<body>
    <div class="container">
        <h1></h1>
        <div id="global-toast"></div>
        <!-- Global confirmation modal -->
        <div id="global-confirm-modal" style="display:none; position:fixed; inset:0; align-items:center; justify-content:center; z-index:10000;">
            <div id="global-confirm-backdrop" style="position:absolute; inset:0; background:rgba(0,0,0,0.5);"></div>
            <div id="global-confirm-box" style="position:relative; background:var(--color-surface); color:var(--color-text); padding:1.25rem; border-radius:8px; width: 420px; box-shadow:var(--shadow-lg); z-index:10001;">
                <div id="global-confirm-message" style="margin-bottom:1rem; white-space:pre-wrap;"></div>
                <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
                    <button id="global-confirm-cancel" class="btn-secondary btn-small">Cancelar</button>
                    <button id="global-confirm-accept" class="btn-danger btn-small">Borrar</button>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($session->isAuthenticated()): ?>
            <!-- Authenticated user interface -->
            <div class="user-info">
                <div>
                    <strong>Usuario: <?= e($session->getUsername()) ?></strong>
                    <?php if ($session->isAdmin()): ?>
                        <span style="background: #ef4444; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; margin-left: 0.5rem; font-size: 0.875rem;">ADMIN</span>
                    <?php endif; ?>
                    <label for="compactToggle" class="switch flex items-center gap-1" style="margin-left: 0.75rem;">
                        <input type="checkbox" id="compactToggle" aria-label="Modo compacto">
                        <span>Modo compacto</span>
                        <span id="compactStatus" class="switch-status off">OFF</span>
                    </label>
                </div>
                <form method="post" style="margin: 0; padding: 0; background: none; box-shadow: none;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn-secondary btn-small">Cerrar Sesión</button>
                </form>
            </div>
            
            <!-- Navigation menu -->
            <nav class="nav">
                <ul class="nav-list">
                    <li class="nav-item">
                        <a href="index.php?option=1" class="nav-link <?= $option === 1 ? 'active' : '' ?>">
                            Caracteres
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?option=2" class="nav-link <?= $option === 2 ? 'active' : '' ?>">
                            Proyectos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?option=3" class="nav-link <?= $option === 3 ? 'active' : '' ?>">
                            Generaciones
                        </a>
                    </li>
                    <?php if ($session->isAdmin()): ?>
                        <li class="nav-item">
                            <a href="index.php?option=4" class="nav-link <?= $option === 4 ? 'active' : '' ?>">
                                Admin
                                <?php 
                                $pendingCount = $requestModel->getPendingCount();
                                if ($pendingCount > 0): 
                                ?>
                                    <span style="background: #ef4444; color: white; padding: 0.25rem 0.5rem; border-radius: 0.5rem; margin-left: 0.25rem; font-size: 0.75rem;"><?= $pendingCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="index.php?option=0" class="nav-link <?= $option === 0 ? 'active' : '' ?>">
                            Resumen
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- Content area -->
            <div class="content">
                <?php
                switch ($option) {
                    case 1:
                        // Characters page
                        include __DIR__ . '/../templates/pages/characters.php';
                        break;
                    case 2:
                        // Projects page
                        include __DIR__ . '/../templates/pages/projects.php';
                        break;
                    case 3:
                        // Generations page
                        include __DIR__ . '/../templates/pages/generations.php';
                        break;
                    case 4:
                        // Admin page (only for admins)
                        if ($session->isAdmin()) {
                            include __DIR__ . '/../templates/pages/admin.php';
                        } else {
                            echo '<div class="alert alert-error">Acceso denegado</div>';
                        }
                        break;
                    case 0:
                        // Summary page
                        include __DIR__ . '/../templates/pages/summary.php';
                        break;
                    default:
                        // Dashboard
                        ?>
                        <div class="card">
                            <h2>Bienvenido a GenWeb NG</h2>
                            <p>Sistema mejorado de gestión de generaciones genéticas.</p>
                            <p>Selecciona una opción del menú para comenzar:</p>
                            <ul>
                                <li><strong>Caracteres:</strong> Gestiona caracteres genéticos y sus propiedades</li>
                                <li><strong>Proyectos:</strong> Crea y administra proyectos de simulación</li>
                                <li><strong>Generaciones:</strong> Genera y analiza poblaciones</li>
                                <?php if ($session->isAdmin()): ?>
                                    <li><strong>Admin:</strong> Gestiona solicitudes de registro y usuarios</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php
                        break;
                }
                ?>
            </div>
            
        <?php else: ?>
            <!-- Login/Register form -->
            <div class="text-right mb-1" id="ui-density-toggle">
                <label class="switch flex items-center gap-1" style="justify-content: flex-end;">
                    <input type="checkbox" id="compactToggle" aria-label="Modo compacto">
                    <span>Modo compacto</span>
                    <span id="compactStatus" class="switch-status off">OFF</span>
                </label>
            </div>
            <div class="card" style="max-width: 500px; margin: 2rem auto;">
                <h2>Identificación</h2>
                <p>Ingresa con tu usuario o solicita una nueva cuenta.</p>
                
                <form method="post">
                    <div class="form-group">
                        <label for="username">Usuario</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-row gap-1">
                        <button type="submit" name="action" value="login" class="btn-primary">
                            Entrar
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Request account form -->
            <div class="card" style="max-width: 500px; margin: 2rem auto;">
                <h3>Solicitar Cuenta Nueva</h3>
                <p>Si no tienes cuenta, solicita acceso. Un administrador revisará tu solicitud.</p>
                
                <form method="post" id="registration-form">
                    <div class="form-group">
                        <label for="new_username">Usuario deseado</label>
                        <input type="text" id="new_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email (opcional)</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Contraseña</label>
                        <input type="password" id="new_password" name="password" required minlength="6">
                        <small style="color: var(--text-muted); font-size: 0.875rem;">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">Confirmar contraseña</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6">
                        <small id="password-match-indicator" style="font-size: 0.875rem; display: none;"></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Motivo de la solicitud (opcional)</label>
                        <textarea id="reason" name="reason" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 0.25rem;"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="role">Tipo de usuario</label>
                        <select id="role" name="role" required>
                            <option value="student">Alumno</option>
                            <option value="teacher">Profesor</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="action" value="request_account" class="btn-success" id="submit-btn">
                        Solicitar Cuenta
                    </button>
                </form>
                
                <script>
                const registrationForm = document.getElementById('registration-form');
                const passwordInput = document.getElementById('new_password');
                const confirmInput = document.getElementById('password_confirm');
                const indicator = document.getElementById('password-match-indicator');
                const submitBtn = document.getElementById('submit-btn');
                
                function checkPasswordMatch() {
                    const password = passwordInput.value;
                    const confirm = confirmInput.value;
                    
                    if (confirm.length === 0) {
                        indicator.style.display = 'none';
                        submitBtn.disabled = false;
                        return false;
                    }
                    
                    indicator.style.display = 'block';
                    
                    if (password === confirm && password.length >= 6) {
                        indicator.textContent = '✓ Las contraseñas coinciden';
                        indicator.style.color = '#10b981';
                        submitBtn.disabled = false;
                        return true;
                    } else {
                        indicator.textContent = '✗ Las contraseñas no coinciden';
                        indicator.style.color = '#ef4444';
                        submitBtn.disabled = true;
                        return false;
                    }
                }
                
                // Prevent form submission if passwords don't match
                registrationForm.addEventListener('submit', function(e) {
                    const password = passwordInput.value;
                    const confirm = confirmInput.value;
                    
                    if (password !== confirm) {
                        e.preventDefault();
                        showToast('Las contraseñas no coinciden. Por favor, verifica e intenta de nuevo.', 'error');
                        return false;
                    }
                    
                    if (password.length < 6) {
                        e.preventDefault();
                        showToast('La contraseña debe tener al menos 6 caracteres.', 'error');
                        return false;
                    }
                    
                    return true;
                });
                
                passwordInput.addEventListener('input', checkPasswordMatch);
                confirmInput.addEventListener('input', checkPasswordMatch);
                </script>
            </div>
            
            <div class="alert alert-info" style="max-width: 500px; margin: 0 auto;">
                <strong>Nota:</strong> Esta es la versión mejorada de GenWeb con base de datos SQLite independiente y sistema de registro con aprobación de administrador.
            </div>
        <?php endif; ?>
    </div>
    <script>
    (function() {
        const toggle = document.getElementById('compactToggle');
        const status = document.getElementById('compactStatus');
        const root = document.documentElement; // aplica la clase al <html>
        const saved = localStorage.getItem('ngw-compact-ui') === '1';

        // Aplica preferencia guardada
        if (saved) {
            root.classList.add('compact-ui');
            if (toggle) toggle.checked = true;
        }

        function setCompact(on) {
            if (on) {
                root.classList.add('compact-ui');
                localStorage.setItem('ngw-compact-ui', '1');
            } else {
                root.classList.remove('compact-ui');
                localStorage.setItem('ngw-compact-ui', '0');
            }
        }

        function updateStatus() {
            if (!status || !toggle) return;
            const on = toggle.checked;
            status.textContent = on ? 'ON' : 'OFF';
            status.classList.toggle('on', on);
            status.classList.toggle('off', !on);
        }

        // Inicializar estado visual
        updateStatus();

        if (toggle) {
            toggle.addEventListener('change', function() {
                setCompact(toggle.checked);
                updateStatus();
            });
        }
    })();
    </script>
    <script>
    // Global showToast function used across pages (if not already defined)
    function showToast(message, type = 'success', duration = 3500) {
        const el = document.getElementById('global-toast');
        if (!el) return;
        el.textContent = message;
        el.classList.remove('toast-success', 'toast-error');
        el.classList.add(type === 'success' ? 'toast-success' : 'toast-error');
        el.style.display = 'block';

        clearTimeout(window._globalToastTimeout);
        window._globalToastTimeout = setTimeout(() => {
            el.style.display = 'none';
        }, duration);
    }
    
    // Global confirmation modal API
    function confirmAction(message, acceptLabel, cancelLabel) {
        if (typeof acceptLabel === 'undefined') acceptLabel = 'Borrar';
        if (typeof cancelLabel === 'undefined') cancelLabel = 'Cancelar';
        return new Promise(function(resolve) {
            const modal = document.getElementById('global-confirm-modal');
            const msg = document.getElementById('global-confirm-message');
            const acceptBtn = document.getElementById('global-confirm-accept');
            const cancelBtn = document.getElementById('global-confirm-cancel');
            const backdrop = document.getElementById('global-confirm-backdrop');

            if (!modal || !msg || !acceptBtn || !cancelBtn) {
                // fallback to native confirm
                resolve(window.confirm(message));
                return;
            }

            msg.textContent = message;
            acceptBtn.textContent = acceptLabel;
            cancelBtn.textContent = cancelLabel;

            function cleanup() {
                acceptBtn.removeEventListener('click', onAccept);
                cancelBtn.removeEventListener('click', onCancel);
                backdrop.removeEventListener('click', onCancel);
                document.removeEventListener('keydown', onKey);
                modal.style.display = 'none';
            }

            function onAccept() { cleanup(); resolve(true); }
            function onCancel() { cleanup(); resolve(false); }
            function onKey(e) { if (e.key === 'Escape') onCancel(); }

            acceptBtn.addEventListener('click', onAccept);
            cancelBtn.addEventListener('click', onCancel);
            backdrop.addEventListener('click', onCancel);
            document.addEventListener('keydown', onKey);

            modal.style.display = 'flex';
        });
    }
    </script>
    <script>
    // Smooth page transitions
    document.addEventListener('DOMContentLoaded', function() {
        // Add fade-in on page load
        document.body.style.opacity = '0';
        setTimeout(() => {
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            document.body.style.opacity = '1';
        }, 10);
        
        // Add fade-out on navigation links
        const navLinks = document.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Don't fade if clicking the active link
                if (this.classList.contains('active')) {
                    return;
                }
                
                e.preventDefault();
                const href = this.getAttribute('href');
                
                document.body.style.opacity = '0';
                setTimeout(() => {
                    window.location.href = href;
                }, 300);
            });
        });
    });
    </script>
</body>
</html>
