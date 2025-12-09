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
        
        try {
            $requestModel = new \Ngw\Models\RegistrationRequest($db);
            $requestModel->create($username, $email, $reason);
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
                
                <form method="post">
                    <div class="form-group">
                        <label for="new_username">Usuario deseado</label>
                        <input type="text" id="new_username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email (opcional)</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="reason">Motivo de la solicitud (opcional)</label>
                        <textarea id="reason" name="reason" rows="3" style="width: 100%; padding: 0.5rem; border: 1px solid #e5e7eb; border-radius: 0.25rem;"></textarea>
                    </div>
                    
                    <button type="submit" name="action" value="request_account" class="btn-success">
                        Solicitar Cuenta
                    </button>
                </form>
            </div>
            
            <div class="alert alert-info" style="max-width: 500px; margin: 0 auto;">
                <strong>Nota:</strong> Esta es la versión mejorada de GenWeb con base de datos SQLite independiente y sistema de registro con aprobación de administrador.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
