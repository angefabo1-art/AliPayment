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

// Check if an admin account already exists in admins table
$admin_exists = getRow($pdo, "SELECT id FROM admins LIMIT 1");

// If admin already exists, deny access
if ($admin_exists) {
    $error_message = 'Un compte administrateur existe déjà. La création d\'un nouveau compte administrateur n\'est pas autorisée.';
    $can_register = false;
} else {
    $can_register = true;
}

// Handle admin registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_register) {
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    
    // Validate inputs
    $errors = [];
    
    if (empty($nom)) {
        $errors[] = 'Le nom est requis.';
    } elseif (strlen($nom) < 2) {
        $errors[] = 'Le nom doit contenir au moins 2 caractères.';
    }
    
    if (empty($prenom)) {
        $errors[] = 'Le prénom est requis.';
    } elseif (strlen($prenom) < 2) {
        $errors[] = 'Le prénom doit contenir au moins 2 caractères.';
    }
    
    if (empty($email)) {
        $errors[] = 'L\'adresse email est requise.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Veuillez entrer une adresse email valide.';
    } else {
        // Check if email already exists in admins table
        $email_exists = getRow($pdo, "SELECT id FROM admins WHERE email = ?", [$email]);
        if ($email_exists) {
            $errors[] = 'Cette adresse email est déjà utilisée.';
        }
    }
    
    if (empty($password)) {
        $errors[] = 'Le mot de passe est requis.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins une lettre majuscule.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
    }
    
    if (empty($password_confirm)) {
        $errors[] = 'Veuillez confirmer votre mot de passe.';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    
    if (empty($telephone)) {
        $errors[] = 'Le numéro de téléphone est requis.';
    } elseif (!preg_match('/^[+]?[0-9\s\-()]{7,}$/', $telephone)) {
        $errors[] = 'Veuillez entrer un numéro de téléphone valide.';
    }
    
    if (!empty($errors)) {
        $error_message = implode(' ', $errors);
    } else {
        // Verify one more time that no admin exists (double check)
        $admin_check = getRow($pdo, "SELECT id FROM admins LIMIT 1");
        
        if ($admin_check) {
            $error_message = 'Un compte administrateur existe déjà. La création n\'est pas autorisée.';
        } else {
            // Hash the password using bcrypt
            $hashed_password = hashPassword($password);
            
            // Generate unique login from email (remove domain)
            $login = substr($email, 0, strpos($email, '@'));
            // Ensure login is unique by adding random suffix if needed
            $login_check = getRow($pdo, "SELECT id FROM admins WHERE login = ?", [$login]);
            if ($login_check) {
                $login = $login . '_' . uniqid();
            }
            
            // Insert admin account into admins table
            $stmt = executeQuery(
                $pdo,
                "INSERT INTO admins (nom, prenom, email, login, password, role, is_active, created_at) 
                 VALUES (?, ?, ?, ?, ?, 'admin', TRUE, NOW())",
                [$nom, $prenom, $email, $login, $hashed_password]
            );
            
            if ($stmt) {
                $success_message = 'Compte administrateur créé avec succès! Redirection vers la page de connexion...';
                error_log('Nouveau compte administrateur créé: ' . json_encode([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'telephone' => $telephone,
                    'creation_date' => date('Y-m-d H:i:s')
                ]));
                
                // Prevent further registrations by disabling the form
                $can_register = false;
                
                // Redirect after 3 seconds
                header('Refresh: 3; url=logadmin.php');
            } else {
                $error_message = 'Erreur lors de la création du compte administrateur. Veuillez réessayer.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inscription Administrateur - Alipayement">
    <title>Inscription Admin - Alipayement</title>
    
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

        /* Registration Section */
        .register-section {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
            padding-top: 80px;
            padding-bottom: 40px;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }

        .register-card-header {
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .register-card-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .register-card-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .register-card-header p {
            font-size: 0.95rem;
            opacity: 0.95;
            margin: 0;
        }

        .register-card-body {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 20px;
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

        .btn-admin-register {
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

        .btn-admin-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(220, 53, 69, 0.3);
            color: white;
        }

        .btn-admin-register:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
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

        .login-link {
            text-align: center;
            margin-bottom: 0;
            font-size: 0.95rem;
            color: #6C757D;
        }

        .login-link a {
            color: #DC3545;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            text-decoration: underline;
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

        .row-cols-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .register-card-header {
                padding: 30px 20px;
            }

            .register-card-header i {
                font-size: 2.5rem;
            }

            .register-card-header h2 {
                font-size: 1.5rem;
            }

            .register-card-body {
                padding: 30px 20px;
            }

            .row-cols-2 {
                grid-template-columns: 1fr;
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
                        <a class="nav-link" href="logadmin.php">
                            <i class="fas fa-shield-alt me-1"></i>Connexion Admin
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

    <!-- Registration Section -->
    <section class="register-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-10">
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

                    <!-- Registration Card -->
                    <div class="register-card">
                        <!-- Card Header -->
                        <div class="register-card-header">
                            <i class="fas fa-user-shield"></i>
                            <h2>Création Compte Admin</h2>
                            <p>Créez votre compte administrateur sécurisé</p>
                        </div>

                        <!-- Card Body -->
                        <div class="register-card-body">
                            <?php if (!$can_register): ?>
                                <!-- Admin Already Exists Warning -->
                                <div class="alert alert-danger" role="alert">
                                    <i class="fas fa-lock"></i>
                                    <div>
                                        <strong>Accès refusé</strong><br>
                                        Un compte administrateur existe déjà. Veuillez utiliser la page de connexion.
                                    </div>
                                </div>
                                <a href="logadmin.php" class="btn btn-admin-register w-100">
                                    <i class="fas fa-sign-in-alt"></i>Accéder à la Connexion Admin
                                </a>
                            <?php else: ?>
                                <form id="adminRegisterForm" method="POST" novalidate>
                                    <div class="row-cols-2">
                                        <!-- Nom Field -->
                                        <div class="form-group">
                                            <label for="nom" class="form-label">
                                                <i class="fas fa-user"></i>Nom
                                            </label>
                                            <input type="text" class="form-control" id="nom" name="nom"
                                                   placeholder="Dupont" required>
                                            <div class="invalid-feedback">
                                                Veuillez entrer votre nom.
                                            </div>
                                        </div>

                                        <!-- Prenom Field -->
                                        <div class="form-group">
                                            <label for="prenom" class="form-label">
                                                <i class="fas fa-user"></i>Prénom
                                            </label>
                                            <input type="text" class="form-control" id="prenom" name="prenom"
                                                   placeholder="Jean" required>
                                            <div class="invalid-feedback">
                                                Veuillez entrer votre prénom.
                                            </div>
                                        </div>
                                    </div>

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

                                    <!-- Telephone Field -->
                                    <div class="form-group">
                                        <label for="telephone" class="form-label">
                                            <i class="fas fa-phone"></i>Téléphone
                                        </label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone"
                                               placeholder="+237 6XX XXX XXX" required>
                                        <div class="invalid-feedback">
                                            Veuillez entrer un numéro de téléphone valide.
                                        </div>
                                    </div>

                                    <!-- Password Field -->
                                    <div class="form-group">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock"></i>Mot de passe
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password" name="password"
                                                   placeholder="Min 8 caractères (majuscule + chiffre)" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">
                                            Veuillez entrer un mot de passe valide.
                                        </div>
                                        <small class="text-muted">Min 8 caractères, au moins 1 majuscule et 1 chiffre</small>
                                    </div>

                                    <!-- Confirm Password Field -->
                                    <div class="form-group">
                                        <label for="password_confirm" class="form-label">
                                            <i class="fas fa-lock"></i>Confirmer mot de passe
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                                                   placeholder="Confirmez votre mot de passe" required>
                                            <button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="invalid-feedback">
                                            Veuillez confirmer votre mot de passe.
                                        </div>
                                    </div>

                                    <!-- Register Button -->
                                    <button type="submit" class="btn btn-admin-register w-100 mb-3">
                                        <i class="fas fa-user-plus"></i>Créer le Compte Administrateur
                                    </button>

                                    <!-- Divider -->
                                    <div class="divider">
                                        <span>ou</span>
                                    </div>

                                    <!-- Login Link -->
                                    <p class="login-link">
                                        Déjà inscrit? 
                                        <a href="logadmin.php">Se connecter</a>
                                    </p>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="text-center text-white py-4" style="background: rgba(0, 0, 0, 0.2); margin-top: 40px;">
        <p class="mb-0">&copy; 2024 Alipayement - Tous droits réservés</p>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordField.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });

        // Toggle password confirmation visibility
        document.getElementById('togglePasswordConfirm')?.addEventListener('click', function() {
            const passwordField = document.getElementById('password_confirm');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordField.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });

        // Form validation
        const form = document.getElementById('adminRegisterForm');
        if (form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        }
    </script>
</body>
</html>
