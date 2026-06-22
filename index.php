<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Alipayement - Plateforme de paiement sécurisée et fiable">
    <title>Alipayement - Plateforme de Paiement</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    
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
<body>
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
                        <a class="nav-link active" href="index.php">Accueil</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Inscription</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section pt-5 mt-5">
        <div class="container">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="welcome-block p-5 rounded-lg shadow-lg bg-gradient">
                        <h1 class="display-4 fw-bold text-white mb-4">Welcome Alipayement</h1>
                        <p class="lead text-white mb-4">
                            La plateforme de paiement sécurisée et fiable pour vos transactions en ligne au Cameroun et dans toute l'Afrique.
                        </p>
                        <div class="d-flex gap-3">
                            <a href="login.php" class="btn btn-primary btn-lg">Se Connecter</a>
                            <a href="register.php" class="btn btn-outline-light btn-lg">S'Inscrire</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="features-list">
                        <div class="feature-item mb-4 p-4 border-start border-primary">
                            <h5 class="fw-bold text-primary mb-2">Sécurité Maximale</h5>
                           <b> <p class="text-muted">Vos données sont protégées par les normes de sécurité internationales les plus strictes.</p></b>
                        </div>
                        <div class="feature-item mb-4 p-4 border-start border-danger">
                            <h5 class="fw-bold text-danger mb-2">Transactions Rapides</h5>
                            <b><p class="text-muted">Effectuez vos paiements en quelques secondes, 24h/24, 7j/7.</p></b>
                        </div>
                        <div class="feature-item mb-4 p-4 border-start border-primary">
                            <h5 class="fw-bold text-primary mb-2">Support Client</h5>
                            <b><p class="text-muted">Une équipe disponible pour vous aider à tout moment.</p></b>
                        </div>
                        <div class="feature-item p-4 border-start border-danger">
                            <h5 class="fw-bold text-danger mb-2">Frais Compétitifs</h5></div>
                           <b> <p class="text-muted">Les meilleurs taux de commission du marché pour vos transactions.</p></b>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-5 mt-5">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="fw-bold mb-3">Alipayement</h5>
                    <p class="text-muted">Votre partenaire de confiance pour les paiements en ligne en Afrique.</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="fw-bold mb-3">Liens Utiles</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">À Propos</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Conditions d'Utilisation</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Politique de Confidentialité</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3">Contact</h5>
                    <p class="text-muted">Email: support@alipayement.com</p>
                    <p class="text-muted">Tél: +237 6XX XXX XXX</p>
                </div>
            </div>
            <hr class="bg-secondary">
            <div class="text-center text-muted">
                <p>&copy; 2024 Alipayement. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
