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
$option = (int) ($_GET['option'] ?? 1); // Default to characters panel
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
        $assignedTeacher = !empty($_POST['assigned_teacher']) ? (int)$_POST['assigned_teacher'] : null;
        
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
            
            // Students must select a teacher
            if ($role === 'student' && empty($assignedTeacher)) {
                throw new \RuntimeException("Los alumnos deben seleccionar un profesor");
            }
            
            $requestModel = new \Ngw\Models\RegistrationRequest($db);
            $requestModel->create($username, $email, $reason, $password, $role, $assignedTeacher);
            $success = "Solicitud enviada correctamente. Podrás entrar con tu usuario y contraseña cuando sea aprobada.";
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'logout') {
        $session->logout();
        redirect('index.php');
    } elseif ($action === 'reset_password' && $session->isAuthenticated()) {
        // Reset password for a user (admin/teacher)
        header('Content-Type: application/json');
        try {
            $targetUserId = (int) ($_POST['user_id'] ?? 0);
            $newPassword = trim($_POST['new_password'] ?? '');
            
            if ($targetUserId <= 0 || empty($newPassword)) {
                throw new \RuntimeException("Datos incompletos");
            }
            
            $auth->resetPassword($targetUserId, $newPassword, $session->getUserId());
            echo json_encode(['success' => true, 'message' => 'Contraseña reseteada correctamente']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    } elseif ($action === 'change_password' && $session->isAuthenticated()) {
        // Change own password
        header('Content-Type: application/json');
        try {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            $forcedChange = $session->mustChangePassword();
            
            if (empty($newPassword)) {
                throw new \RuntimeException("La nueva contraseña no puede estar vacía");
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new \RuntimeException("Las contraseñas no coinciden");
            }
            
            $auth->changePassword($session->getUserId(), $currentPassword, $newPassword, $forcedChange);
            $session->clearMustChangePassword();
            
            echo json_encode(['success' => true, 'message' => 'Contraseña cambiada correctamente']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Load models if authenticated
$projectModel = null;
$characterModel = null;
$requestModel = null;
$projectGroupModel = null;

// Get teachers for registration form (available for non-authenticated users)
$teachers = $auth->getTeachers();

if ($session->isAuthenticated()) {
    $projectModel = new \Ngw\Models\Project($db);
    $characterModel = new \Ngw\Models\Character($db);
    $requestModel = new \Ngw\Models\RegistrationRequest($db);
    $projectGroupModel = new \Ngw\Models\ProjectGroup($db);
}

/**
 * Helper function to generate create character form HTML
 */
function generateCreateCharacterFormHtml($session): string
{
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
    
    return $formHtml;
}

/**
 * Generate character details HTML (used by both page load and AJAX)
 */
function generateCharacterDetailsHtml($characterModel, $session, $charId, $userId): string
{
    $activeCharacter = $characterModel->getById($charId);
    $genes = $characterModel->getGenes($charId);
    $connections = $characterModel->getConnections($charId);
    $numSubstrates = (int)$activeCharacter['substrates'];
    
    ob_start();
    include __DIR__ . '/../templates/partials/character_details.php';
    return ob_get_clean();
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
                // If deleting the active character, close it first
                $activeCharId = $session->get('active_character_id');
                $wasClosed = false;
                if ($activeCharId && (int)$activeCharId === $charId) {
                    $wasClosed = true;
                    $session->remove('active_character_id');
                    $session->remove('active_gene_id');
                    $session->remove('show_connections');
                }
                
                $characterModel->delete($charId);
                
                // If character was open, return the create form HTML
                $responseData = ['success' => true, 'wasClosed' => $wasClosed];
                if ($wasClosed) {
                    $responseData['html'] = generateCreateCharacterFormHtml($session);
                }
                echo json_encode($responseData);
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
                    'visible' => $visible,
                    'public' => $public
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
                $genes = $characterModel->getGenes($charId);
                $session->set('show_connections', !empty($genes));
                
                // Use the helper function to generate HTML
                $html = generateCharacterDetailsHtml($characterModel, $session, $charId, $userId);
                
                echo json_encode(['success' => true, 'html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
            // Get active character ID
            $characterId = $session->get('active_character_id');
            
            if (!$characterId) {
                echo json_encode(['success' => false, 'error' => 'No hay carácter activo']);
                exit;
            }
            
            // Validate character completion before closing
            $validationErrors = $characterModel->validateCharacterCompletion($characterId);
            
            if (!empty($validationErrors)) {
                // Return validation errors
                echo json_encode(['success' => false, 'error' => implode("\n", $validationErrors)]);
                exit;
            }
            
            // Close the character (validation passed)
            $session->remove('active_character_id');
            $session->remove('active_gene_id');
            $session->remove('show_connections');
            $session->remove('show_genes');
            
            // Generate create form HTML using helper
            $formHtml = generateCreateCharacterFormHtml($session);
            
            // Return characterId so frontend can update the table row
            echo json_encode(['success' => true, 'html' => $formHtml, 'characterId' => $characterId]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'force_close_character_ajax') {
        header('Content-Type: application/json');
        try {
            // Get active character ID
            $characterId = $session->get('active_character_id');
            
            if (!$characterId) {
                echo json_encode(['success' => false, 'error' => 'No hay carácter activo']);
                exit;
            }
            
            // Force close the character without validation
            $session->remove('active_character_id');
            $session->remove('active_gene_id');
            $session->remove('show_connections');
            $session->remove('show_genes');
            
            // Generate create form HTML using helper
            $formHtml = generateCreateCharacterFormHtml($session);
            
            echo json_encode(['success' => true, 'html' => $formHtml, 'characterId' => $characterId]);
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
                $substrates = (int) $_POST['substrates'];
                
                // Delete connections that use substrates >= new number
                $existingConnections = $characterModel->getConnections($charId);
                $deletedConnections = [];
                foreach ($existingConnections as $conn) {
                    if ($conn['state_a'] >= $substrates || $conn['state_b'] >= $substrates) {
                        $characterModel->removeConnection($conn['id']);
                        $deletedConnections[] = $conn['id'];
                    }
                }

                $characterModel->update($charId, ['substrates' => $substrates]);
                $session->set('show_connections', true);
                echo json_encode([
                    'success' => true, 
                    'substrates' => $substrates,
                    'deleted_connections' => $deletedConnections
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($charAction === 'add_connection') {
        try {
            $charId = (int) ($_POST['character_id'] ?? $activeCharacterId ?? 0);
            if ($charId > 0 && ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($charId, $userId))) {
                $stateA = (int) ($_POST['state_a'] ?? 0);
                $transition = (int) ($_POST['transition'] ?? 0);
                $stateB = (int) ($_POST['state_b'] ?? 0);
                
                if ($transition > 0) {
                    $characterModel->addConnection($charId, $stateA, $transition, $stateB);
                    $success = "Conexión creada correctamente";
                    redirect('index.php?option=1');
                } else {
                    $error = "Debes seleccionar un gen válido";
                }
            } else {
                $error = "No tienes permiso para crear conexiones";
            }
        } catch (\Exception $e) {
            $error = "Error al crear conexión: " . $e->getMessage();
        }
    } elseif ($charAction === 'add_connection_ajax') {
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
                
                // Calculate the stored dominance value to return
                $dominanceToSend = null;
                if ($dominance !== null) {
                    $dominanceBase = (int) $dominance;
                    
                    if ($additive) {
                        // Additive: 4th digit = 1
                        $dominanceToSend = 1000 + $dominanceBase;
                    } elseif ($epistasis !== null && $epistasis !== '' && is_numeric($epistasis)) {
                        // Epistasis: 4th digit = epistasis_input + 1
                        $epistasisDigit = (int) $epistasis + 1;
                        $dominanceToSend = ($epistasisDigit * 1000) + $dominanceBase;
                    } else {
                        $dominanceToSend = $dominanceBase;
                    }
                }

                echo json_encode([
                    'success' => true,
                    'allele' => [
                        'id' => $alleleId,
                        'name' => $name,
                        'value' => $value,
                        'dominance' => $dominanceToSend,
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
            $activeCharId = (int) ($session->get('active_character_id') ?? 0);

            // Allow deletion if user is teacher/admin or owner of the active character
            if ($geneId > 0 && ($session->isTeacher() || $session->isAdmin() || ($activeCharId > 0 && $characterModel->isOwner($activeCharId, $userId)))) {
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
                    <h4>Alelos del gen: <?= htmlspecialchars($activeGene['name']) ?></h4>
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
                                    <?php 
                                        // Extract display dominance (base value, 0-999)
                                        // Stored format: modifier*1000 + base (modifier: 0=none, 1=additive, >2=epistasis)
                                        $displayDominance = $al['dominance'];
                                        if ($displayDominance !== null) {
                                            $storedValue = (int)$displayDominance;
                                            $displayDominance = $storedValue % 1000; // Get base value (last 3 digits)
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($al['id']) ?></td>
                                        <td><?= htmlspecialchars($al['name']) ?></td>
                                        <td><?= htmlspecialchars($al['value'] ?? '') ?></td>
                                        <td><?= htmlspecialchars((int)$al['additive'] === 1 ? 'Sí' : 'No') ?></td>
                                        <td><?= htmlspecialchars($displayDominance) ?></td>
                                        <td><?= htmlspecialchars($al['epistasis'] ?? '') ?></td>
                                        <td>
                                            <?php if ($session->isTeacher() || $session->isAdmin() || (int)$activeCharacter['creator_id'] === $userId) : ?>
                                                <button onclick="deleteAllele(<?= $al['id'] ?>, () => openGene(<?= (int)$activeGene['id'] ?>, true))" class="btn-danger btn-small">Eliminar</button>
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
                                <label><input type="checkbox" name="allele_additive" id="allele_additive" value="1"> Sí</label>
                            </div>
                            <div class="form-group">
                                <label>Dominancia (0-999)</label>
                                <input type="number" name="allele_dominance" min="0" max="999">
                            </div>
                            <div class="form-group">
                                <label>Epistasis</label>
                                <input type="number" name="allele_epistasis" id="allele_epistasis" min="1">
                                <small style="display: block; color: #666;">Se sumará 1 al valor introducido para asegurar que sea >2</small>
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
    elseif ($charAction === 'get_genes_ajax') {
        header('Content-Type: application/json');
        try {
            $charId = (int)($_POST['char_id'] ?? 0);
            if ($charId > 0) {
                $genes = $characterModel->getGenes($charId);
                echo json_encode(['success' => true, 'genes' => $genes]);
            } else {
                echo json_encode(['success' => false, 'error' => 'ID de carácter inválido']);
            }
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
                
                // Fetch the created gene to get the formatted chromosome display
                $newGene = $characterModel->getGeneById($geneId);
                $chrDisplay = '';
                if ($newGene['chromosome']) {
                    $chrDisplay = $newGene['chromosome'];
                    if ($newGene['code']) {
                        $chrDisplay .= ' (' . $newGene['code'] . ')';
                    }
                } elseif ($newGene['code']) {
                    $chrDisplay = $newGene['code'];
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

// Handle CSV download via GET (so links can open in new tab)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['project_action']) && $_GET['project_action'] === 'download_generation_csv') {
    try {
        $projectId = $session->get('active_project_id');

        if (!$projectId) {
            http_response_code(400);
            echo 'No hay proyecto activo';
            exit;
        }

        $generationNumber = (int)($_GET['generation_number'] ?? $_GET['generationNumber'] ?? 0);
        $decimal = $_GET['decimal'] ?? 'dot';

        if ($generationNumber <= 0) {
            http_response_code(400);
            echo 'Número de generación inválido';
            exit;
        }

        if (!in_array($decimal, ['dot', 'comma'])) {
            http_response_code(400);
            echo 'Separador decimal inválido';
            exit;
        }

        // Determine projects folder and candidate filenames
        $cfg = parse_ini_file(__DIR__ . '/../config/config.ini');
        $projectsPath = $cfg['PROJECTS_PATH'] ?? '/var/www/proyectosNGengine';
        $projectFolder = rtrim($projectsPath, '/') . '/' . $projectId;

        // Filenames observed in project output
        $fileDot = $projectFolder . '/' . $projectId . '_' . $generationNumber . '_datos.csv';
        $fileComma = $projectFolder . '/' . $projectId . '_' . $generationNumber . '_datos_coma.csv';

        $targetFile = ($decimal === 'comma') ? $fileComma : $fileDot;

        // If a specific filename was requested, validate and try to serve it
        if (!empty($_GET['file'])) {
            $requested = basename($_GET['file']);
            $candidate = $projectFolder . '/' . $requested;
            $realProjectFolder = realpath($projectFolder);
            $realCandidate = realpath($candidate);
            if ($realCandidate && $realProjectFolder && strpos($realCandidate, $realProjectFolder) === 0 && is_file($realCandidate)) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $requested . '"');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($realCandidate);
                exit;
            }
            // If requested file not found, continue to other fallbacks
        }

        if (file_exists($targetFile)) {
            // Serve existing file directly
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="generation_' . $generationNumber . '_decimal_' . $decimal . '.csv"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            readfile($targetFile);
            exit;
        }

        // If not found, fall back to dynamic CSV generation (semicolon columns)
        require_once __DIR__ . '/../src/Models/Generation.php';
        $generationModel = new \Ngw\Models\Generation($db);

        $generation = $generationModel->getByNumber($projectId, $generationNumber);
        if (!$generation) {
            http_response_code(404);
            echo 'Generación no encontrada';
            exit;
        }

        $individuals = $generationModel->parseGenerationOutput($projectId, $generationNumber);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="generation_' . $generationNumber . '_decimal_' . $decimal . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $sepChar = ';';

        // Output header
        echo "Individual_ID";
        if (!empty($individuals)) {
            $firstPhenotypes = reset($individuals);
            for ($i = 0; $i < count($firstPhenotypes); $i++) {
                echo $sepChar . "Phenotype_" . ($i + 1);
            }
        }
        echo "\n";

        // Output data
        foreach ($individuals as $id => $phenotypes) {
            echo $id;
            foreach ($phenotypes as $phenotype) {
                $value = (string)$phenotype;
                if ($decimal === 'comma') {
                    $value = str_replace('.', ',', $value);
                }
                echo $sepChar . $value;
            }
            echo "\n";
        }
        exit;
    } catch (\Exception $e) {
        http_response_code(500);
        echo 'Error: ' . $e->getMessage();
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
            $groupId = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? (int)$_POST['group_id'] : null;
            
            // If groupId is provided, verify ownership
            if ($groupId !== null && !$projectGroupModel->isOwner($groupId, $userId)) {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso para usar este grupo']);
                exit;
            }
            
            $projectId = $projectModel->create($name, $userId, '', $groupId);
            
            echo json_encode([
                'success' => true,
                'project' => [
                    'id' => $projectId,
                    'name' => $name,
                    'group_id' => $groupId
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
            $deleteDirectory = ($_POST['delete_directory'] ?? 'false') === 'true';
            
            if ($projectId > 0 && $projectModel->isOwner($projectId, $userId)) {
                // Close project if it's the active one before deleting
                $activeProjectId = $session->get('active_project_id');
                if ($activeProjectId && (int)$activeProjectId === $projectId) {
                    $session->delete('active_project_id');
                }
                
                $projectModel->delete($projectId, $deleteDirectory);
                echo json_encode(['success' => true, 'was_active' => ($activeProjectId && (int)$activeProjectId === $projectId)]);
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
                                                onclick="openAlleleFrequencies(<?= htmlspecialchars($char['character_id']) ?>, '<?= htmlspecialchars(addslashes($char['name'])) ?>')" 
                                                class="btn-secondary btn-small">Frecuencias</button>
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
                // Check if character is complete before adding to project
                if (!$characterModel->isComplete($characterId)) {
                    echo json_encode(['success' => false, 'error' => 'No se puede añadir un carácter incompleto al proyecto. Completa el carácter primero.']);
                    exit;
                }
                
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
    elseif ($projectAction === 'get_allele_frequencies') {
        header('Content-Type: application/json');
        try {
            $characterId = (int)($_POST['character_id'] ?? 0);
            $projectId = $session->get('active_project_id');
            
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }
            
            if ($characterId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de carácter inválido']);
                exit;
            }
            
            // Get genes and alleles for this character
            $characterModel = new \Ngw\Models\Character($db);
            $genes = $characterModel->getGenes($characterId);
            
            // Get current frequencies for this project
            $frequencies = $projectModel->getAlleleFrequencies($projectId, $characterId);
            
            // Build response with genes and alleles
            $genesData = [];
            foreach ($genes as $gene) {
                $alleles = $characterModel->getAlleles($gene['id']);
                $allelesData = [];
                
                foreach ($alleles as $allele) {
                    $freq = $frequencies[$allele['id']] ?? null;
                    
                    // Extract base dominance (last 3 digits) for display
                    $displayDominance = $allele['dominance'];
                    if ($displayDominance !== null) {
                        $displayDominance = (int)$displayDominance % 1000;
                    }
                    
                    $allelesData[] = [
                        'id' => $allele['id'],
                        'name' => $allele['name'],
                        'value' => $allele['value'],
                        'dominance' => $displayDominance,
                        'frequency' => $freq
                    ];
                }
                
                $genesData[] = [
                    'id' => $gene['id'],
                    'name' => $gene['name'],
                    'chromosome' => $gene['chromosome'],
                    'position' => $gene['position'],
                    'alleles' => $allelesData
                ];
            }
            
            echo json_encode(['success' => true, 'genes' => $genesData]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'save_allele_frequencies') {
        header('Content-Type: application/json');
        try {
            $characterId = (int)($_POST['character_id'] ?? 0);
            $projectId = $session->get('active_project_id');
            
            if (!$projectId) {
                echo json_encode(['success' => false, 'error' => 'No hay proyecto activo']);
                exit;
            }
            
            if ($characterId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de carácter inválido']);
                exit;
            }
            
            $frequenciesJson = $_POST['frequencies'] ?? '[]';
            $frequencies = json_decode($frequenciesJson, true);
            
            if (!is_array($frequencies)) {
                echo json_encode(['success' => false, 'error' => 'Datos de frecuencias inválidos']);
                exit;
            }
            
            // Validate frequencies by gene
            $characterModel = new \Ngw\Models\Character($db);
            $genes = $characterModel->getGenes($characterId);
            
            foreach ($genes as $gene) {
                $alleles = $characterModel->getAlleles($gene['id']);
                $alleleIds = array_column($alleles, 'id');
                $sum = 0;
                
                foreach ($frequencies as $freq) {
                    if (in_array($freq['allele_id'], $alleleIds)) {
                        $sum += $freq['frequency'];
                    }
                }
                
                if (abs($sum - 1.0) > 0.01) {
                    echo json_encode([
                        'success' => false, 
                        'error' => "La suma de frecuencias del gen '{$gene['name']}' es {$sum}, debe ser 1"
                    ]);
                    exit;
                }
            }
            
            // Save frequencies
            $projectModel->saveAlleleFrequencies($projectId, $frequencies);
            
            echo json_encode(['success' => true]);
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
            try {
                $pocPath = $projectModel->generatePocFile($projectId, $populationSize, $generationNumber, 'random');
            } catch (\RuntimeException $e) {
                ob_end_clean();
                echo json_encode([
                    'success' => false, 
                    'error' => $e->getMessage()
                ]);
                exit;
            }
            
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

            try {
                $pocPath = $projectModel->generatePocFile($projectId, $populationSize, $generationNumber, 'cross');
            } catch (\RuntimeException $e) {
                ob_end_clean();
                echo json_encode([
                    'success' => false, 
                    'error' => $e->getMessage()
                ]);
                exit;
            }

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

            // Check for pre-generated CSV files and provide download URLs when present
            $cfg = parse_ini_file(__DIR__ . '/../config/config.ini');
            $projectsPath = $cfg['PROJECTS_PATH'] ?? '/var/www/proyectosNGengine';
            $projectFolder = rtrim($projectsPath, '/') . '/' . $projectId;
            $csvDotPath = $projectFolder . '/' . $projectId . '_' . $generationNumber . '_datos.csv';
            $csvCommaPath = $projectFolder . '/' . $projectId . '_' . $generationNumber . '_datos_coma.csv';

            $csvDotExists = file_exists($csvDotPath);
            $csvCommaExists = file_exists($csvCommaPath);

            $csvDotUrl = $csvDotExists ? ('index.php?option=2&project_action=download_generation_csv&generation_number=' . $generationNumber . '&decimal=dot&file=' . rawurlencode(basename($csvDotPath))) : null;
            $csvCommaUrl = $csvCommaExists ? ('index.php?option=2&project_action=download_generation_csv&generation_number=' . $generationNumber . '&decimal=comma&file=' . rawurlencode(basename($csvCommaPath))) : null;

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
                'parental_stats' => $parentalStats,
                'csv_dot_exists' => $csvDotExists,
                'csv_comma_exists' => $csvCommaExists,
                'csv_dot_url' => $csvDotUrl,
                'csv_comma_url' => $csvCommaUrl
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
                try {
                    $poc = $projectModel->generatePocFile($projectId, $populationSize, $targetGen, 'cross');
                } catch (\RuntimeException $e) {
                    $results[] = ['generation_number' => $targetGen, 'success' => false, 'error' => $e->getMessage()];
                    continue;
                }
                
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
    elseif ($projectAction === 'create_serial_cross') {
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
            $truncationPercent = (int)($_POST['truncation_percent'] ?? 10);
            $truncationDirection = ($_POST['truncation_direction'] ?? 'top'); // 'top' or 'bottom'
            $populationSize = (int)($_POST['population_size'] ?? 0);

            if ($sourceGen <= 0 || $populationSize <= 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos']);
                exit;
            }

            if ($truncationPercent < 1 || $truncationPercent > 100) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Truncamiento debe estar entre 1 y 100']);
                exit;
            }

            require_once __DIR__ . '/../src/Models/Generation.php';
            require_once __DIR__ . '/../src/Models/Project.php';
            $generationModel = new \Ngw\Models\Generation($db);
            $projectModel = new \Ngw\Models\Project($db);

            // Ensure source generation exists
            $src = $generationModel->getByNumber($projectId, $sourceGen);
            if (!$src) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Generación fuente no encontrada']);
                exit;
            }

            // Get ordered individuals for source generation (already sorted by phenotype)
            $orderedIndividuals = $generationModel->parseGenerationOutput($projectId, $sourceGen);
            $orderedIds = array_keys($orderedIndividuals);
            $total = count($orderedIds);

            if ($total === 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'La generación fuente no tiene individuos']);
                exit;
            }

            // Calculate how many individuals to select based on truncation
            $selectCount = max(1, (int)round(($truncationPercent / 100) * $total));

            // Select individuals based on direction
            if ($truncationDirection === 'top') {
                // Top = first N (highest phenotype values, since data is sorted descending)
                $selectedIds = array_slice($orderedIds, 0, $selectCount);
            } else {
                // Bottom = last N (lowest phenotype values)
                $selectedIds = array_slice($orderedIds, -$selectCount);
            }

            // Get next generation number
            $targetGen = $generationModel->getNextGenerationNumber($projectId);

            // Check target doesn't exist
            $existing = $generationModel->getByNumber($projectId, $targetGen);
            if ($existing) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'La generación destino ya existe']);
                exit;
            }

            // Add selected individuals as parentals for the new generation
            $generationModel->addParentals($projectId, $targetGen, $sourceGen, array_map('intval', $selectedIds));

            // Build POC and execute engine
            try {
                $poc = $projectModel->generatePocFile($projectId, $populationSize, $targetGen, 'cross');
            } catch (\RuntimeException $e) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }

            $execRes = $generationModel->executeGengine($projectId);
            if (!$execRes['success']) {
                ob_end_clean();
                echo json_encode(['success' => false, 'error' => 'Error al ejecutar gengine (código ' . $execRes['return_code'] . ')']);
                exit;
            }

            // Create generation record and parse output
            $generationModel->create($projectId, $targetGen, $populationSize, 'cross');
            $generation = $generationModel->getByNumber($projectId, $targetGen);
            $individuals = $generationModel->parseGenerationOutput($projectId, $targetGen);

            // Calculate mean of first phenotype for stop condition check
            $mean = 0;
            if (!empty($individuals)) {
                $sum = 0;
                foreach ($individuals as $phenotypes) {
                    // Get first phenotype value
                    $firstVal = reset($phenotypes);
                    $sum += $firstVal;
                }
                $mean = $sum / count($individuals);
            }

            $individualsList = [];
            foreach ($individuals as $id => $phenotypes) {
                $individualsList[] = ['id' => $id, 'phenotypes' => $phenotypes];
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'generation_number' => $targetGen,
                'population_size' => count($individualsList),
                'created_at' => $generation['created_at'] ?? null,
                'mean' => $mean,
                'parentals_count' => count($selectedIds),
                'individuals' => $individualsList
            ]);
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

            // Check for pre-generated CSV files and provide download URLs when present
            $cfg = parse_ini_file(__DIR__ . '/../config/config.ini');
            $projectsPath = $cfg['PROJECTS_PATH'] ?? '/var/www/proyectosNGengine';
            $projectFolder = rtrim($projectsPath, '/') . '/' . $projectId;
            $csvDotPath = $projectFolder . '/' . $projectId . '_' . $generation['generation_number'] . '_datos.csv';
            $csvCommaPath = $projectFolder . '/' . $projectId . '_' . $generation['generation_number'] . '_datos_coma.csv';

            $csvDotExists = file_exists($csvDotPath);
            $csvCommaExists = file_exists($csvCommaPath);

            $csvDotUrl = $csvDotExists ? ('index.php?option=2&project_action=download_generation_csv&generation_number=' . $generation['generation_number'] . '&decimal=dot&file=' . rawurlencode(basename($csvDotPath))) : null;
            $csvCommaUrl = $csvCommaExists ? ('index.php?option=2&project_action=download_generation_csv&generation_number=' . $generation['generation_number'] . '&decimal=comma&file=' . rawurlencode(basename($csvCommaPath))) : null;

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
                'parental_stats' => $parentalStats,
                'csv_dot_exists' => $csvDotExists,
                'csv_comma_exists' => $csvCommaExists,
                'csv_dot_url' => $csvDotUrl,
                'csv_comma_url' => $csvCommaUrl
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'download_generation_csv') {
        try {
            $projectId = $session->get('active_project_id');

            if (!$projectId) {
                http_response_code(400);
                echo 'No hay proyecto activo';
                exit;
            }

            // Accept parameters from POST first, fallback to GET
            // Debug: log incoming POST for troubleshooting
            if (function_exists('error_log')) {
                error_log('[download_generation_csv] POST: ' . var_export($_POST, true));
                error_log('[download_generation_csv] GET: ' . var_export($_GET, true));
                error_log('[download_generation_csv] session active_project_id: ' . var_export($session->get('active_project_id'), true));
            }
            // Also append to temp debug file for easier inspection
            @file_put_contents('/tmp/ngw_download_debug.log', "POST: " . var_export($_POST, true) . "\nGET: " . var_export($_GET, true) . "\nactive_project_id: " . var_export($session->get('active_project_id'), true) . "\n---\n", FILE_APPEND);
            $generationNumber = (int)($_POST['generation_number'] ?? $_POST['generationNumber'] ?? $_GET['generation_number'] ?? $_GET['generationNumber'] ?? 0);
            $decimal = $_POST['decimal'] ?? $_GET['decimal'] ?? 'dot';

            if ($generationNumber <= 0) {
                http_response_code(400);
                echo 'Número de generación inválido';
                exit;
            }

            if (!in_array($decimal, ['dot', 'comma'])) {
                http_response_code(400);
                echo 'Separador decimal inválido';
                exit;
            }

            // Determine projects folder and candidate filenames
            $cfg = parse_ini_file(__DIR__ . '/../config/config.ini');
            $projectsPath = $cfg['PROJECTS_PATH'] ?? '/var/www/proyectosNGengine';
            $projectFolder = rtrim($projectsPath, '/') . '/' . $projectId;

            // Filenames observed in project output
            $fileDot = $projectFolder . '/' . $projectId . '_' . $generationNumber . '_datos.csv';
            $fileComma = $projectFolder . '/' . $projectId . '_' . $generationNumber . '_datos_coma.csv';

            $targetFile = ($decimal === 'comma') ? $fileComma : $fileDot;

            // If a specific filename was requested, validate and try to serve it
            if (!empty($_GET['file'])) {
                $requested = basename($_GET['file']);
                $candidate = $projectFolder . '/' . $requested;
                $realProjectFolder = realpath($projectFolder);
                $realCandidate = realpath($candidate);
                if ($realCandidate && $realProjectFolder && strpos($realCandidate, $realProjectFolder) === 0 && is_file($realCandidate)) {
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $requested . '"');
                    header('Cache-Control: no-cache, no-store, must-revalidate');
                    header('Pragma: no-cache');
                    header('Expires: 0');
                    readfile($realCandidate);
                    exit;
                }
                // If requested file not found, continue to other fallbacks
            }

            if (file_exists($targetFile)) {
                // Serve existing file directly
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="generation_' . $generationNumber . '_decimal_' . $decimal . '.csv"');
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($targetFile);
                exit;
            }

            // If not found, fall back to dynamic CSV generation (semicolon columns)
            require_once __DIR__ . '/../src/Models/Generation.php';
            $generationModel = new \Ngw\Models\Generation($db);

            $generation = $generationModel->getByNumber($projectId, $generationNumber);
            if (!$generation) {
                http_response_code(404);
                echo 'Generación no encontrada';
                exit;
            }

            $individuals = $generationModel->parseGenerationOutput($projectId, $generationNumber);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="generation_' . $generationNumber . '_decimal_' . $decimal . '.csv"');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            $sepChar = ';';

            // Output header
            echo "Individual_ID";
            if (!empty($individuals)) {
                $firstPhenotypes = reset($individuals);
                for ($i = 0; $i < count($firstPhenotypes); $i++) {
                    echo $sepChar . "Phenotype_" . ($i + 1);
                }
            }
            echo "\n";

            // Output data
            foreach ($individuals as $id => $phenotypes) {
                echo $id;
                foreach ($phenotypes as $phenotype) {
                    $value = (string)$phenotype;
                    if ($decimal === 'comma') {
                        $value = str_replace('.', ',', $value);
                    }
                    echo $sepChar . $value;
                }
                echo "\n";
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo 'Error: ' . $e->getMessage();
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
    // Project Groups handlers
    elseif ($projectAction === 'get_groups') {
        header('Content-Type: application/json');
        try {
            $groups = $projectGroupModel->getUserGroups($userId);
            $ungroupedCount = $projectGroupModel->countUngroupedProjects($userId);
            
            echo json_encode([
                'success' => true,
                'groups' => $groups,
                'ungroupedCount' => $ungroupedCount,
                'colors' => \Ngw\Models\ProjectGroup::getAvailableColors()
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'create_group') {
        header('Content-Type: application/json');
        try {
            $name = trim($_POST['group_name'] ?? '');
            $color = trim($_POST['color'] ?? '#6366f1');
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'El nombre del grupo es obligatorio']);
                exit;
            }
            
            $groupId = $projectGroupModel->create($name, $userId, $color);
            
            echo json_encode([
                'success' => true,
                'group' => [
                    'id' => $groupId,
                    'name' => $name,
                    'color' => $color,
                    'project_count' => 0
                ]
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'update_group') {
        header('Content-Type: application/json');
        try {
            $groupId = (int)($_POST['group_id'] ?? 0);
            $name = trim($_POST['group_name'] ?? '');
            $color = trim($_POST['color'] ?? null);
            
            if ($groupId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de grupo inválido']);
                exit;
            }
            
            if (!$projectGroupModel->isOwner($groupId, $userId)) {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso para editar este grupo']);
                exit;
            }
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'El nombre del grupo es obligatorio']);
                exit;
            }
            
            $projectGroupModel->update($groupId, $name, $color);
            
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'delete_group') {
        header('Content-Type: application/json');
        try {
            $groupId = (int)($_POST['group_id'] ?? 0);
            
            if ($groupId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de grupo inválido']);
                exit;
            }
            
            if (!$projectGroupModel->isOwner($groupId, $userId)) {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso para borrar este grupo']);
                exit;
            }
            
            $projectGroupModel->delete($groupId);
            
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'move_project_to_group') {
        header('Content-Type: application/json');
        try {
            $projectId = (int)($_POST['project_id'] ?? 0);
            $groupId = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? (int)$_POST['group_id'] : null;
            
            if ($projectId <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de proyecto inválido']);
                exit;
            }
            
            if (!$projectModel->isOwner($projectId, $userId)) {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso para mover este proyecto']);
                exit;
            }
            
            // If groupId is provided, verify ownership
            if ($groupId !== null && !$projectGroupModel->isOwner($groupId, $userId)) {
                echo json_encode(['success' => false, 'error' => 'No tienes permiso para usar este grupo']);
                exit;
            }
            
            $projectModel->setGroup($projectId, $groupId);
            
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    elseif ($projectAction === 'get_projects_by_group') {
        header('Content-Type: application/json');
        try {
            // groupId: -1 = all, null = ungrouped, number = specific group
            $groupId = $_POST['group_id'] ?? '-1';
            
            if ($groupId === '-1' || $groupId === '') {
                $groupId = -1;
            } elseif ($groupId === 'null' || $groupId === '0') {
                $groupId = null;
            } else {
                $groupId = (int)$groupId;
            }
            
            $projects = $projectModel->getUserProjectsByGroup($userId, $groupId);
            
            echo json_encode([
                'success' => true,
                'projects' => $projects
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
    <script>
        // Apply theme immediately to prevent flash of wrong theme
        (function() {
            var theme = localStorage.getItem('ngw-theme');
            if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
            // Also apply compact mode if saved
            if (localStorage.getItem('ngw-compact-ui') === '1') {
                document.documentElement.classList.add('compact-ui');
            }
        })();
    </script>
    <link rel="stylesheet" href="css/style.css?v=<?= time() ?>">
    <script src="js/ajax-handlers.js?v=<?= time() ?>" defer></script>
    <script src="js/project-handlers.js?v=<?= time() ?>" defer></script>
</head>
<body>
    <div class="container">
        <h1><a href="ngw/" class="brand-link" title="Ir al directorio ngw">GenWeb</a></h1>
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
                    <?php
                        $userRole = $session->getRole() ?? 'student';
                        $roleLabel = $userRole === 'teacher' ? 'Profesor' : ($userRole === 'admin' ? 'Admin' : 'Alumno');
                    ?>
                    <strong>Usuario: <?= e($session->getUsername()) ?> (<?= e($roleLabel) ?>)</strong>
                    <?php if ($session->isAdmin()): ?>
                        <span style="background: #ef4444; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; margin-left: 0.5rem; font-size: 0.875rem;">ADMIN</span>
                    <?php endif; ?>
                    <span style="display: inline-flex; gap: 0.75rem; margin-left: 0.75rem;">
                        <label for="themeToggle" class="switch flex items-center gap-1">
                            <input type="checkbox" id="themeToggle" aria-label="Tema claro">
                            <span>☀️ Claro</span>
                            <span id="themeStatus" class="switch-status off">OFF</span>
                        </label>
                        <label for="compactToggle" class="switch flex items-center gap-1">
                            <input type="checkbox" id="compactToggle" aria-label="Modo compacto">
                            <span>Modo compacto</span>
                            <span id="compactStatus" class="switch-status off">OFF</span>
                        </label>
                    </span>
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
                    <?php if ($session->isAdmin() || $session->isTeacher()): ?>
                        <li class="nav-item">
                            <a href="index.php?option=4" class="nav-link <?= $option === 4 ? 'active' : '' ?>">
                                <?= $session->isAdmin() ? 'Admin' : 'Mis Alumnos' ?>
                                <?php 
                                if ($session->isAdmin()) {
                                    $pendingCount = $requestModel->getPendingCount();
                                } else {
                                    // Count only requests assigned to this teacher
                                    $pendingCount = $db->fetchOne("SELECT COUNT(*) as count FROM registration_requests WHERE status = 'pending' AND assigned_teacher_id = :teacher_id", ['teacher_id' => $session->getUserId()])['count'] ?? 0;
                                }
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
                        // Admin page (for admins and teachers)
                        if ($session->isAdmin() || $session->isTeacher()) {
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
            <div class="mb-1" id="ui-density-toggle" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <label class="switch flex items-center gap-1">
                    <input type="checkbox" id="themeToggle" aria-label="Tema claro">
                    <span>☀️ Claro</span>
                    <span id="themeStatus" class="switch-status off">OFF</span>
                </label>
                <label class="switch flex items-center gap-1">
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
                    
                    <p class="text-muted" style="font-size: 0.9em; margin-top: 1rem;">
                        Si has olvidado tu contraseña, contacta con tu profesor.
                    </p>
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
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
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

                    <div class="form-group" id="teacher-selector">
                        <label for="assigned_teacher">Mi profesor</label>
                        <?php if (!empty($teachers)) : ?>
                            <select id="assigned_teacher" name="assigned_teacher">
                                <option value="">-- Selecciona tu profesor --</option>
                                <?php foreach ($teachers as $teacher) : ?>
                                    <option value="<?= $teacher['id'] ?>"><?= e($teacher['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: var(--text-muted); font-size: 0.875rem;">Selecciona al profesor responsable de tu solicitud</small>
                        <?php else : ?>
                            <p style="color: var(--warning-color);">No hay profesores disponibles en este momento. Contacta con administración.</p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" name="action" value="request_account" class="btn-success" id="submit-btn">
                        Solicitar Cuenta
                    </button>
                </form>
                
                <script>
                // Show teacher selector only for students
                document.getElementById('role').addEventListener('change', function() {
                    const selector = document.getElementById('teacher-selector');
                    if (this.value === 'student') {
                        selector.style.display = 'block';
                    } else {
                        selector.style.display = 'none';
                    }
                });
                
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
                    
                    // Check if student has selected a teacher
                    const roleSelect = document.getElementById('role');
                    const teacherSelect = document.getElementById('assigned_teacher');
                    if (roleSelect.value === 'student' && teacherSelect && !teacherSelect.value) {
                        e.preventDefault();
                        showToast('Los alumnos deben seleccionar un profesor.', 'error');
                        return false;
                    }
                    
                    return true;
                });
                
                passwordInput.addEventListener('input', checkPasswordMatch);
                confirmInput.addEventListener('input', checkPasswordMatch);
                </script>
            </div>
        <?php endif; ?>
    </div>
    <script>
    (function() {
        // === Theme Toggle ===
        const themeToggle = document.getElementById('themeToggle');
        const themeStatus = document.getElementById('themeStatus');
        const html = document.documentElement;
        const savedTheme = localStorage.getItem('ngw-theme');

        // Aplica preferencia guardada o detecta preferencia del sistema
        if (savedTheme === 'light') {
            html.setAttribute('data-theme', 'light');
            if (themeToggle) themeToggle.checked = true;
        } else if (savedTheme === 'dark') {
            html.removeAttribute('data-theme');
            if (themeToggle) themeToggle.checked = false;
        }
        // Si no hay preferencia guardada, usa el tema oscuro por defecto

        function setTheme(light) {
            if (light) {
                html.setAttribute('data-theme', 'light');
                localStorage.setItem('ngw-theme', 'light');
            } else {
                html.removeAttribute('data-theme');
                localStorage.setItem('ngw-theme', 'dark');
            }
        }

        function updateThemeStatus() {
            if (!themeStatus || !themeToggle) return;
            const on = themeToggle.checked;
            themeStatus.textContent = on ? 'ON' : 'OFF';
            themeStatus.classList.toggle('on', on);
            themeStatus.classList.toggle('off', !on);
        }

        // Inicializar estado visual
        updateThemeStatus();

        if (themeToggle) {
            themeToggle.addEventListener('change', function() {
                setTheme(themeToggle.checked);
                updateThemeStatus();
            });
        }

        // === Compact Mode Toggle ===
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
    
    <?php if ($session->isAuthenticated() && $session->mustChangePassword()): ?>
    <!-- Modal de cambio de contraseña obligatorio -->
    <div id="force-password-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 10000;">
        <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 400px; width: 90%;">
            <h3 style="margin-top: 0; color: var(--color-danger);">Cambio de Contraseña Requerido</h3>
            <p>Tu contraseña ha sido reseteada. Debes establecer una nueva contraseña para continuar.</p>
            <form id="force-change-password-form">
                <div class="form-group">
                    <label for="new_password">Nueva contraseña</label>
                    <input type="password" id="new_password" name="new_password" required minlength="4">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="4">
                </div>
                <div id="password-error" style="color: var(--color-danger); margin-bottom: 1rem; display: none;"></div>
                <button type="submit" class="btn-success" style="width: 100%;">Guardar Nueva Contraseña</button>
            </form>
        </div>
    </div>
    <script>
    document.getElementById('force-change-password-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const newPass = document.getElementById('new_password').value;
        const confirmPass = document.getElementById('confirm_password').value;
        const errorDiv = document.getElementById('password-error');
        
        if (newPass !== confirmPass) {
            errorDiv.textContent = 'Las contraseñas no coinciden';
            errorDiv.style.display = 'block';
            return;
        }
        
        if (newPass.length < 4) {
            errorDiv.textContent = 'La contraseña debe tener al menos 4 caracteres';
            errorDiv.style.display = 'block';
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'change_password');
        formData.append('current_password', '');
        formData.append('new_password', newPass);
        formData.append('confirm_password', confirmPass);
        
        fetch('index.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('force-password-modal').style.display = 'none';
                    if (typeof showNotification === 'function') {
                        showNotification('Contraseña cambiada correctamente', 'success');
                    }
                } else {
                    errorDiv.textContent = data.error || 'Error al cambiar la contraseña';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(err => {
                errorDiv.textContent = 'Error de conexión';
                errorDiv.style.display = 'block';
            });
    });
    </script>
    <?php endif; ?>
    
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
