<?php

/**
 * Main entry point - GenWeb Next Generation
 */

// Bootstrap application
$app = require_once __DIR__ . '/../src/bootstrap.php';

/** @var \Ngw\Database\Database $db */
$db = $app['db'];
/** @var \Ngw\Auth\SessionManager $session */
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
    
    if ($charAction === 'update_substrates_ajax') {
        header('Content-Type: application/json');
        try {
            $charId = (int) ($_POST['char_id'] ?? 0);
            if ($charId > 0 && ($session->isTeacher() || $session->isAdmin() || $characterModel->isOwner($charId, $userId))) {
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
            if ($geneId > 0 && ($session->isTeacher() || $session->isAdmin())) {
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
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>GenWeb NG</h1>
        
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
                        alert('Las contraseñas no coinciden. Por favor, verifica e intenta de nuevo.');
                        return false;
                    }
                    
                    if (password.length < 6) {
                        e.preventDefault();
                        alert('La contraseña debe tener al menos 6 caracteres.');
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
</body>
</html>
