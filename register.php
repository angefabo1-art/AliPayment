<?php
session_start();

// Include database connection
require_once __DIR__ . '/config/db.php';
global $pdo;

// Verify PDO connection is available
if (!isset($GLOBALS['pdo']) || $GLOBALS['pdo'] === null) {
    die("Erreur: Connexion à la base de données non disponible.");
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $telephone = isset($_POST['telephone']) ? trim($_POST['telephone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $adresse = isset($_POST['adresse']) ? trim($_POST['adresse']) : '';
    $activite = isset($_POST['activite']) ? trim($_POST['activite']) : '';
    $login = isset($_POST['login']) ? trim($_POST['login']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirmPassword']) ? $_POST['confirmPassword'] : '';
    
    // Validation patterns
    $telephonePattern = '/^\+237\d{9}$/';
    $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{8,}$/';
    $loginPattern = '/^[a-zA-Z0-9_-]{4,}$/';
    
    // Validate all fields
    $errors = [];
    
    if (empty($nom)) $errors[] = 'Le nom est requis.';
    if (empty($prenom)) $errors[] = 'Le prénom est requis.';
    if (!preg_match($telephonePattern, $telephone)) $errors[] = 'Le numéro de téléphone est invalide.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'L\'email est invalide.';
    if (empty($adresse)) $errors[] = 'L\'adresse est requise.';
    if (empty($activite)) $errors[] = 'Veuillez sélectionner une activité.';
    if (!preg_match($loginPattern, $login)) $errors[] = 'Le nom d\'utilisateur doit contenir au moins 4 caractères.';
    if (!preg_match($passwordPattern, $password)) $errors[] = 'Le mot de passe ne respecte pas les critères de sécurité.';
    if ($password !== $confirmPassword) $errors[] = 'Les mots de passe ne correspondent pas.';
    
    // Handle file upload
    if (!isset($_FILES['cni']) || $_FILES['cni']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Veuillez télécharger une copie de votre carte d\'identité.';
    } else {
        $file = $_FILES['cni'];
        $maxSize = 5 * 1024 * 1024; // 5 MB
        $validMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
        
        if ($file['size'] > $maxSize) {
            $errors[] = 'Le fichier dépasse la taille maximale de 5 Mo.';
        } elseif (!in_array($file['type'], $validMimes)) {
            $errors[] = 'Format de fichier non autorisé. Utilisez PDF, JPG, PNG ou GIF.';
        }
    }
    
    if (!empty($errors)) {
        $error_message = implode(' ', $errors);
    } else {
        // Hash the password using bcrypt
        $hashed_password = hashPassword($password);
        
        // Create upload directory if it doesn't exist
        $upload_dir = __DIR__ . '/uploads/cni/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename for CNI document
        $file_ext = pathinfo($_FILES['cni']['name'], PATHINFO_EXTENSION);
        $cni_filename = uniqid('cni_') . '_' . time() . '.' . $file_ext;
        $cni_path = $upload_dir . $cni_filename;
        
        // Move uploaded file
        if (move_uploaded_file($_FILES['cni']['tmp_name'], $cni_path)) {
            // Insert user registration into database with status='pending'
            $stmt = executeQuery(
                $pdo,
                "INSERT INTO users (nom, prenom, telephone, email, adresse, activite, login, password, cni_document, status, balance_xaf, balance_rmb, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0.00, 0.00, NOW())",
                [$nom, $prenom, $telephone, $email, $adresse, $activite, $login, $hashed_password, $cni_filename]
            );
            
            if ($stmt) {
                $success_message = 'Inscription soumise avec succès! Un email de confirmation a été envoyé. Votre compte sera activé après approbation par un administrateur. Redirection vers la page de connexion...';
                
                // Log the registration
                error_log('Nouvelle inscription (Statut: PENDING): ' . json_encode([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'telephone' => $telephone,
                    'email' => $email,
                    'adresse' => $adresse,
                    'activite' => $activite,
                    'login' => $login,
                    'cni_file' => $cni_filename,
                    'status' => 'pending',
                    'registration_date' => date('Y-m-d H:i:s')
                ]));
                
                // Redirect after 3 seconds
                header('Refresh: 3; url=login.php');
            } else {
                $error_message = 'Erreur lors de l\'enregistrement. Veuillez réessayer.';
            }
        } else {
            $error_message = 'Erreur lors de l\'upload du fichier. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Inscription à Alipayement - Plateforme de paiement">
    <title>Inscription - Alipayement</title>
    
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
    background-image: url('logo.png');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    min-height: 100vh;
}

.register-section {
    background: rgba(0, 0, 0, 0.3);
    min-height: 100vh;
}

.card {
    background-color: rgba(255, 255, 255, 0.95);
}
</style>
</head>
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
                        <a class="nav-link" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="register.php">Inscription</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Register Section -->
    <section class="register-section d-flex align-items-center justify-content-center py-5 pt-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
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
                            <h2 class="text-white mb-0 fw-bold">Créer un Compte</h2>
                            <p class="text-white-50 mb-0 small mt-2">Rejoignez la plateforme Alipayement</p>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body p-5">
                            <!-- Alert Info -->
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Important:</strong> Votre compte sera activé après validation par l'administrateur.
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>

                            <form id="registerForm" method="POST" enctype="multipart/form-data" novalidate>
                                <!-- Row 1: Nom et Prénom -->
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label for="nom" class="form-label fw-bold text-dark mb-2">
                                            <i class="fas fa-user me-2 text-primary"></i>Nom
                                        </label>
                                        <input type="text" class="form-control form-control-lg" id="nom" name="nom"
                                               placeholder="Entrez votre nom" required>
                                        <div class="invalid-feedback">
                                            Veuillez entrer votre nom.
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <label for="prenom" class="form-label fw-bold text-dark mb-2">
                                            <i class="fas fa-user me-2 text-danger"></i>Prénom
                                        </label>
                                        <input type="text" class="form-control form-control-lg" id="prenom" name="prenom"
                                               placeholder="Entrez votre prénom" required>
                                        <div class="invalid-feedback">
                                            Veuillez entrer votre prénom.
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 2: Téléphone et Email -->
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <label for="telephone" class="form-label fw-bold text-dark mb-2">
                                            <i class="fas fa-phone me-2 text-primary"></i>Téléphone
                                        </label>
                                        <input type="tel" class="form-control form-control-lg" id="telephone" name="telephone"
                                               placeholder="+237 6XX XXX XXX" required>
                                        <small class="text-muted d-block mt-2">
                                            Format: +237XXXXXXXXX
                                        </small>
                                        <div class="invalid-feedback">
                                            Veuillez entrer un numéro de téléphone valide.
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <label for="email" class="form-label fw-bold text-dark mb-2">
                                            <i class="fas fa-envelope me-2 text-danger"></i>Adresse Email
                                        </label>
                                        <input type="email" class="form-control form-control-lg" id="email" name="email"
                                               placeholder="votre@email.com" required>
                                        <div class="invalid-feedback">
                                            Veuillez entrer une adresse email valide.
                                        </div>
                                    </div>
                                </div>

                                <!-- Row 3: Adresse -->
                                <div class="mb-4">
                                    <label for="adresse" class="form-label fw-bold text-dark mb-2">
                                        <i class="fas fa-map-marker-alt me-2 text-primary"></i>Adresse Complète
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="adresse" name="adresse"
                                           placeholder="Ex: Rue de la Paix, Douala" required>
                                    <div class="invalid-feedback">
                                        Veuillez entrer votre adresse.
                                    </div>
                                </div>

                                <!-- Row 4: Activité -->
                                <div class="mb-4">
                                    <label for="activite" class="form-label fw-bold text-dark mb-2">
                                        <i class="fas fa-briefcase me-2 text-danger"></i>Activité/Secteur d'Activité
                                    </label>
                                    <select class="form-select form-select-lg" id="activite" name="activite" required>
                                        <option value="">-- Sélectionnez votre activité --</option>
                                        <option value="commerce">Commerce</option>
                                        <option value="services">Services</option>
                                        <option value="transport">Transport</option>
                                        <option value="restauration">Restauration</option>
                                        <option value="sante">Santé</option>
                                        <option value="education">Éducation</option>
                                        <option value="immobilier">Immobilier</option>
                                        <option value="technologie">Technologie</option>
                                        <option value="agriculture">Agriculture</option>
                                        <option value="autre">Autre</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Veuillez sélectionner une activité.
                                    </div>
                                </div>

                                <!-- Row 5: Carte Nationale d'Identité -->
                                <div class="mb-4">
                                    <label for="cni" class="form-label fw-bold text-dark mb-2">
                                        <i class="fas fa-id-card me-2 text-primary"></i>Carte Nationale d'Identité (Image ou PDF)
                                    </label>
                                    <div class="input-group">
                                        <input type="file" class="form-control form-control-lg" id="cni" name="cni"
                                               accept=".pdf,.jpg,.jpeg,.png,.gif" required>
                                        <label class="input-group-text" for="cni">
                                            <i class="fas fa-upload me-2"></i>Parcourir
                                        </label>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Formats acceptés: PDF, JPG, PNG, GIF (Max: 5 Mo)
                                    </small>
                                    <div id="cniPreview" class="mt-3"></div>
                                    <div class="invalid-feedback d-block">
                                        Veuillez télécharger une copie de votre carte d'identité.
                                    </div>
                                </div>

                                <!-- Row 6: Login -->
                                <div class="mb-4">
                                    <label for="login" class="form-label fw-bold text-dark mb-2">
                                        <i class="fas fa-user-circle me-2 text-danger"></i>Nom d'Utilisateur (Login)
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="login" name="login"
                                           placeholder="Entrez votre nom d'utilisateur" minlength="4" required>
                                    <small class="text-muted d-block mt-2">
                                        Minimum 4 caractères (lettres, chiffres, tirets)
                                    </small>
                                    <div class="invalid-feedback">
                                        Veuillez entrer un nom d'utilisateur valide (min. 4 caractères).
                                    </div>
                                </div>

                                <!-- Row 7: Mot de Passe -->
                                <div class="mb-4">
                                    <label for="password" class="form-label fw-bold text-dark mb-2">
                                        <i class="fas fa-lock me-2 text-primary"></i>Mot de Passe
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg" id="password" name="password"
                                               placeholder="Entrez votre mot de passe" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        Minimum 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre, 1 symbole
                                    </small>
                                    <div class="invalid-feedback d-block">
                                        Le mot de passe doit contenir au minimum 8 caractères, une majuscule, une minuscule, un chiffre et un symbole.
                                    </div>

                                    <!-- Password Strength Indicator -->
                                    <div class="mt-2">
                                        <div class="progress" style="height: 5px;">
                                            <div id="passwordStrength" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                                        </div>
                                        <small id="strengthText" class="text-muted d-block mt-1">Force du mot de passe</small>
                                    </div>
                                </div>

                                <!-- Row 8: Confirmer Mot de Passe -->
                                <div class="mb-4">
                                    <label for="confirmPassword" class="form-label fw-bold text-dark mb-2">
                                        <i class="fas fa-check-circle me-2 text-danger"></i>Confirmer le Mot de Passe
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg" id="confirmPassword" name="confirmPassword"
                                               placeholder="Confirmez votre mot de passe" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="invalid-feedback">
                                        Les mots de passe ne correspondent pas.
                                    </div>
                                </div>

                                <!-- Terms & Conditions -->
                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="termsAccept" name="termsAccept" required>
                                        <label class="form-check-label" for="termsAccept">
                                            J'accepte les <a href="#" class="text-primary text-decoration-none fw-bold">conditions d'utilisation</a> 
                                            et la <a href="#" class="text-primary text-decoration-none fw-bold">politique de confidentialité</a>
                                        </label>
                                        <div class="invalid-feedback">
                                            Veuillez accepter les conditions d'utilisation.
                                        </div>
                                    </div>
                                </div>

                                <!-- Register Button -->
                                <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold rounded-lg mb-3">
                                    <i class="fas fa-user-plus me-2"></i>Soumettre ma Demande d'Inscription
                                </button>

                                <!-- Divider -->
                                <hr class="my-4">

                                <!-- Login Link -->
                                <p class="text-center text-muted mb-0">
                                    Vous avez déjà un compte? 
                                    <a href="login.php" class="fw-bold text-primary text-decoration-none">Se connecter</a>
                                </p>
                            </form>
                        </div>
                    </div>

                    <!-- Verification Info -->
                    <div class="alert alert-info mt-4 rounded-lg" role="alert">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-double text-primary fa-lg"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="alert-heading">Validation Admin</h6>
                                <small>Après votre inscription, un administrateur validera votre compte dans les 24h.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
        function setupPasswordToggle(buttonId, inputId) {
            document.getElementById(buttonId).addEventListener('click', function() {
                const passwordInput = document.getElementById(inputId);
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
        }

        setupPasswordToggle('togglePassword', 'password');
        setupPasswordToggle('toggleConfirmPassword', 'confirmPassword');

        // Password Strength Checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            if (password.length >= 8) strength += 20;
            if (password.length >= 12) strength += 10;
            if (/[a-z]/.test(password)) strength += 15;
            if (/[A-Z]/.test(password)) strength += 15;
            if (/[0-9]/.test(password)) strength += 20;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 20;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 30) {
                strengthBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Force du mot de passe: Faible';
                strengthText.className = 'text-danger d-block mt-1';
            } else if (strength < 60) {
                strengthBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Force du mot de passe: Moyen';
                strengthText.className = 'text-warning d-block mt-1';
            } else if (strength < 85) {
                strengthBar.className = 'progress-bar bg-info';
                strengthText.textContent = 'Force du mot de passe: Bon';
                strengthText.className = 'text-info d-block mt-1';
            } else {
                strengthBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Force du mot de passe: Excellent';
                strengthText.className = 'text-success d-block mt-1';
            }
        });

        // Telephone Number Formatter
        document.getElementById('telephone').addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (!value.startsWith('237')) {
                value = '237' + value.slice(-9);
            } else {
                value = value.slice(0, 12);
            }
            
            if (value.length > 3) {
                this.value = '+' + value;
            }
        });

        // Handle CNI File Upload with Preview
        document.getElementById('cni').addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('cniPreview');
            preview.innerHTML = '';
            
            if (file) {
                const maxSize = 5 * 1024 * 1024; // 5 MB
                const validExtensions = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
                
                if (file.size > maxSize) {
                    alert('Le fichier est trop volumineux. Maximum 5 Mo autorisé.');
                    this.value = '';
                    return;
                }
                
                if (!validExtensions.includes(file.type)) {
                    alert('Format de fichier non autorisé. Utilisez PDF, JPG, PNG ou GIF.');
                    this.value = '';
                    return;
                }
                
                // Create preview
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview CNI" style="max-width: 200px; max-height: 150px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf') {
                    preview.innerHTML = '<div class="alert alert-info"><i class="fas fa-file-pdf me-2 text-danger"></i>Fichier PDF chargé: ' + file.name + '</div>';
                }
            }
        });

        // Form Validation
        const registerForm = document.getElementById('registerForm');
        registerForm.addEventListener('submit', function(e) {
            if (registerForm.checkValidity() === false) {
                e.preventDefault();
                e.stopPropagation();
            }
            registerForm.classList.add('was-validated');
        });
    </script>
</body>
</html>
