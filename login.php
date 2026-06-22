<?php
session_start();

// Include database connection
require_once __DIR__ . '/config/db.php';
global $pdo;

// Verify PDO connection is available
if (empty($GLOBALS['pdo'])) {
    die("Erreur: Connexion à la base de données non disponible.");
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error_message = 'Veuillez entrer votre email et mot de passe.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Veuillez entrer une adresse email valide.';
    } else {
        // Query database for user
        $user = getRow($pdo, "SELECT id, login, email, status, password FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            $error_message = 'Email ou mot de passe incorrect.';
        } elseif (!verifyPassword($password, $user['password'])) {
            $error_message = 'Email ou mot de passe incorrect.';
        } elseif ($user['status'] === 'pending') {
            $error_message = 'Votre compte est en attente d\'approbation. Veuillez patienter qu\'un administrateur valide votre inscription.';
        } elseif ($user['status'] === 'rejected') {
            $error_message = 'Votre demande d\'inscription a été rejetée. Contactez l\'administrateur pour plus d\'informations.';
        } else {
            // User is approved - Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_login'] = $user['login'];
            
            // Update last_login timestamp
            executeQuery(
                $pdo,
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            $success_message = 'Connexion réussie! Redirection vers le tableau de bord...';
            header('Refresh: 2; url=dashboard.php');
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('reset_email', $_POST)) {
    $reset_email = !empty($_POST['reset_email']) ? trim($_POST['reset_email']) : '';
    
    if (!empty($reset_email) && filter_var($reset_email, FILTER_VALIDATE_EMAIL)) {
        // TODO: Send password reset email
        $success_message = 'Un lien de réinitialisation a été envoyé à votre email.';
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
    <meta name="description" content="Connexion à Alipayement - Plateforme de paiement">
    <title>Connexion - Alipayement</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
</head>
<style>
    body {
    background-image: url('logo.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    min-height: 100vh;
}
</style>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <span class="text-primary">Ali</span><span class="text-danger">Payement</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Inscription</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="login-section d-flex align-items-center justify-content-center min-vh-100 pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-8">
                    <!-- Display Messages -->
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card shadow-lg border-0 rounded-lg overflow-hidden">
                        <!-- Card Header -->
                        <div class="card-header bg-gradient py-4 text-center">
                            <h2 class="text-white mb-0 fw-bold">Connexion</h2>
                            <p class="text-white-50 mb-0 small mt-2">Accédez à votre compte Alipayement</p>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body p-5">
                            <form id="loginForm" method="POST" novalidate>
                                <!-- Email Field -->
                                <div class="mb-4">
                                    <label for="email" class="form-label fw-bold text-dark mb-2">
                                        <i class="fas fa-envelope me-2 text-primary"></i>Email
                                    </label>
                                    <input type="email" class="form-control form-control-lg" id="email" name="email"
                                           placeholder="votre@email.com" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer une adresse email valide.
                                    </div>
                                </div>

                                <!-- Password Field -->
                                <div class="mb-4">
                                    <label for="password" class="form-label fw-bold text-dark mb-2">
                                        <i class="fas fa-lock me-2 text-danger"></i>Mot de passe
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg" id="password" name="password"
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
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                                        <label class="form-check-label" for="rememberMe">
                                            Se souvenir de moi
                                        </label>
                                    </div>
                                </div>

                                <!-- Login Button -->
                                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold rounded-lg mb-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Se Connecter
                                </button>

                                <!-- Forgot Password Button -->
                                <button type="button" class="btn btn-link btn-lg w-100 text-danger fw-bold mb-4" 
                                        data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                                    <i class="fas fa-key me-2"></i>Mot de passe oublié?
                                </button>

                                <!-- Divider -->
                                <hr class="my-4">

                                <!-- Sign Up Link -->
                                <p class="text-center text-muted mb-3">
                                    Pas encore inscrit? 
                                    <a href="register.php" class="fw-bold text-primary text-decoration-none">Créer un compte</a>
                                </p>

                                <!-- Admin Login Link -->
                                <p class="text-center text-muted mb-0">
                                    Administrateur? 
                                    <a href="logadmin.php" class="fw-bold text-danger text-decoration-none">Connexion Admin</a>
                                </p>
                            </form>
                        </div>
                    </div>

                    <!-- Security Info -->
                    <div class="alert alert-info mt-4 rounded-lg" role="alert">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-shield-alt text-primary fa-lg"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="alert-heading">Connexion Sécurisée</h6>
                                <small>Vos données de connexion sont chiffrées et sécurisées.</small>
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
            <div class="modal-content border-0 rounded-lg shadow-lg">
                <div class="modal-header bg-gradient border-0 py-4">
                    <h5 class="modal-title fw-bold text-white">Réinitialiser le mot de passe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted mb-3">Entrez votre adresse email pour recevoir un lien de réinitialisation.</p>
                    <form id="forgotPasswordForm" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="resetEmail" class="form-label fw-bold">Email</label>
                            <input type="email" class="form-control form-control-lg" id="resetEmail" name="reset_email"
                                   placeholder="votre@email.com" required>
                            <div class="invalid-feedback">
                                Veuillez entrer une adresse email valide.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                            Envoyer le lien de réinitialisation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container text-center">
            <p class="text-muted mb-0">&copy; 2024 Alipayement. Tous droits réservés.</p>
        </div>
    </footer>

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
        const loginForm = document.getElementById('loginForm');
        loginForm.addEventListener('submit', function(e) {
            if (!loginForm.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                loginForm.classList.add('was-validated');
            }
            loginForm.classList.add('was-validated');
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
