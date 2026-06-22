<?php
session_start();

// Include database connection
require_once __DIR__ . '/config/db.php';
global $pdo;

// Verify PDO connection is available
if (empty($GLOBALS['pdo'])) {
    die("Erreur: Connexion à la base de données non disponible.");
}

// Check if admin is already logged in
if (isset($_SESSION['admin_id']) && $_SESSION['admin_role'] === 'admin') {
    header('Location: admincargo.php');
    exit();
}

// Redirect regular users to user login
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle admin login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error_message = 'Veuillez entrer votre email et mot de passe administrateur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Veuillez entrer une adresse email valide.';
    } else {
        // Query database for admin user from admins table
        $admin = getRow(
            $pdo,
            "SELECT id, nom, prenom, email, role, password, is_active FROM admins WHERE email = ?",
            [$email]
        );
        
        if (!$admin) {
            $error_message = 'Email ou mot de passe administrateur incorrect.';
        } elseif (!verifyPassword($password, $admin['password'])) {
            $error_message = 'Email ou mot de passe administrateur incorrect.';
        } elseif (!$admin['is_active']) {
            $error_message = 'Votre compte administrateur est désactivé. Contactez le super administrateur.';
        } else {
            // Admin authentication successful - Update last_login and set session variables
            executeQuery(
                $pdo,
                "UPDATE admins SET last_login = NOW() WHERE id = ?",
                [$admin['id']]
            );
            
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']);
            
            $success_message = 'Connexion administrateur réussie! Redirection...';
            header('Refresh: 2; url=admincargo.php');
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_email'])) {
    $reset_email = isset($_POST['reset_email']) ? trim($_POST['reset_email']) : '';
    
    if (!empty($reset_email) && filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
        // TODO: Send password reset email to admin
        $success_message = 'Un lien de réinitialisation a été envoyé à votre email administrateur.';
    } else {
        $error_message = 'Veuillez entrer une adresse email valide.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Connexion Administrateur - Alipayement">
    <title>Connexion Admin - Alipayement</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .admin-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        /* Navigation */
        .navbar-admin {
            background: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .navbar-admin .navbar-brand {
            color: #0052CC !important;
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
        }

        .navbar-admin .nav-link {
            color: #333 !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .navbar-admin .nav-link:hover {
            color: #0052CC !important;
        }

        /* Login Section */
        .login-section {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            padding-top: 80px;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }

        .login-card-header {
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .login-card-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .login-card-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-card-header p {
            font-size: 0.95rem;
            opacity: 0.95;
            margin: 0;
        }

        .login-card-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #212529;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: #DC3545;
            font-size: 1.1rem;
        }

        .form-control {
            border: 2px solid #E0E0E0;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #DC3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .input-group .btn {
            border: 2px solid #E0E0E0;
            border-left: none;
            border-radius: 0 10px 10px 0;
            transition: all 0.3s ease;
        }

        .input-group .btn:hover {
            border-color: #DC3545;
            color: #DC3545;
        }

        .form-check-label {
            color: #6C757D;
            font-weight: 500;
        }

        .btn-admin-login {
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
        }

        .btn-admin-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(220, 53, 69, 0.3);
            color: white;
        }

        .btn-forgot-password {
            color: #DC3545;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-forgot-password:hover {
            color: #C82333;
            text-decoration: underline;
        }

        .divider {
            position: relative;
            text-align: center;
            margin: 30px 0;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #E0E0E0;
        }

        .divider span {
            background: white;
            padding: 0 10px;
            color: #999;
            font-size: 0.9rem;
            position: relative;
        }

        .user-login-link {
            text-align: center;
            margin-bottom: 0;
            font-size: 0.95rem;
            color: #6C757D;
        }

        .user-login-link a {
            color: #0052CC;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .user-login-link a:hover {
            text-decoration: underline;
        }

        .security-info {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(200, 35, 51, 0.1) 100%);
            border-left: 4px solid #DC3545;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .security-info i {
            color: #DC3545;
            font-size: 1.3rem;
        }

        .security-info h6 {
            color: #DC3545;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .security-info small {
            color: #6C757D;
        }

        /* Alert Messages */
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #F8D7DA;
            color: #721C24;
        }

        .alert-success {
            background: #D4EDDA;
            color: #155724;
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Footer */
        .footer-admin {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 0.9rem;
            margin-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%);
            color: white;
            border: none;
            border-radius: 15px 15px 0 0;
            padding: 25px;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }

        .modal-body {
            padding: 30px;
        }

        .modal-footer {
            border-top: 1px solid #E0E0E0;
            padding: 20px 30px;
            background: #F8F9FA;
            border-radius: 0 0 15px 15px;
        }

        @media (max-width: 768px) {
            .login-card-header {
                padding: 30px 20px;
            }

            .login-card-header i {
                font-size: 2.5rem;
            }

            .login-card-header h2 {
                font-size: 1.5rem;
            }

            .login-card-body {
                padding: 30px 20px;
            }

            .admin-badge {
                font-size: 0.85rem;
                padding: 8px 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-admin">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <span style="color: #0052CC;">Ali</span><span style="color: #DC3545;">Payement</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Accueil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-1"></i>Connexion Utilisateur
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="registeradmin.php">
                            <i class="fas fa-user-shield me-1"></i>Inscription
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Admin Badge -->
    <div class="admin-badge">
        <i class="fas fa-shield-alt"></i>Espace Administrateur
    </div>

    <!-- Login Section -->
    <section class="login-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-8">
                    <!-- Display Messages -->
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i>
                            <div><?php echo htmlspecialchars($error_message); ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i>
                            <div><?php echo htmlspecialchars($success_message); ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Login Card -->
                    <div class="login-card">
                        <!-- Card Header -->
                        <div class="login-card-header">
                            <i class="fas fa-shield-alt"></i>
                            <h2>Connexion Admin</h2>
                            <p>Accédez à l&apos;espace administrateur sécurisé</p>
                        </div>

                        <!-- Card Body -->
                        <div class="login-card-body">
                            <form id="adminLoginForm" method="POST" novalidate>
                                <!-- Email Field -->
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope"></i>Email Administrateur
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           placeholder="admin@alipayement.com" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer une adresse email valide.
                                    </div>
                                </div>

                                <!-- Password Field -->
                                <div class="form-group">
                                    <label for="password" class="form-label">
                                        <i class="fas fa-lock"></i>Mot de passe
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password"
                                               placeholder="Entrez votre mot de passe" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre mot de passe.
                                    </div>
                                </div>

                                <!-- Remember Me -->
                                <div class="form-group">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                                        <label class="form-check-label" for="rememberMe">
                                            Se souvenir de moi
                                        </label>
                                    </div>
                                </div>

                                <!-- Login Button -->
                                <button type="submit" class="btn btn-admin-login w-100 mb-2">
                                    <i class="fas fa-sign-in-alt"></i>Se Connecter en tant qu&apos;Admin
                                </button>

                                <!-- Forgot Password Button -->
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <button type="button" class="btn btn-forgot-password" 
                                            data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                                        <i class="fas fa-key me-1"></i>Mot de passe oublié?
                                    </button>
                                </div>

                                <!-- Divider -->
                                <div class="divider">
                                    <span>ou</span>
                                </div>

                                <!-- User Login Link -->
                                <p class="user-login-link">
                                    Connexion utilisateur? 
                                    <a href="login.php">Cliquez ici</a>
                                </p>
                            </form>
                        </div>

                        <!-- Security Info -->
                        <div style="padding: 0 30px 30px 30px;">
                            <div class="security-info">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-lock-alt"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6>Accès Protégé</h6>
                                        <small>Seuls les administrateurs autorisés peuvent accéder à cet espace. Votre connexion est chiffrée et sécurisée.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-key me-2"></i>Réinitialiser le mot de passe
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Entrez votre adresse email administrateur pour recevoir un lien de réinitialisation.</p>
                    <form id="forgotPasswordForm" method="POST" novalidate>
                        <div class="form-group">
                            <label for="resetEmail" class="form-label fw-bold">Email Administrateur</label>
                            <input type="email" class="form-control" id="resetEmail" name="reset_email"
                                   placeholder="admin@alipayement.com" required>
                            <div class="invalid-feedback">
                                Veuillez entrer une adresse email valide.
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" form="forgotPasswordForm" class="btn btn-danger">
                        <i class="fas fa-paper-plane me-2"></i>Envoyer le lien
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script>
        // Toggle Password Visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form Validation
        const adminLoginForm = document.getElementById('adminLoginForm');
        adminLoginForm.addEventListener('submit', function(e) {
            if (!adminLoginForm.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                adminLoginForm.classList.add('was-validated');
            }
            adminLoginForm.classList.add('was-validated');
        });

        // Forgot Password Form
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        forgotPasswordForm.addEventListener('submit', function(e) {
            if (!forgotPasswordForm.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                forgotPasswordForm.classList.add('was-validated');
            }
        });
    </script>
</body>
</html>
