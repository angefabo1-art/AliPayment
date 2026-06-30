<?php
session_start();

// Include database connection
require_once __DIR__ . '/config/db.php';
global $pdo;

// Verify PDO connection is available
if (!isset($GLOBALS['pdo']) || $GLOBALS['pdo'] === null) {
    die("Erreur: Connexion à la base de données non disponible.");
}

// Vérification de la session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupération des données utilisateur à partir de la base de données
$user_id = $_SESSION['user_id'];
$user_data = getRow($pdo, "SELECT nom, prenom, email, telephone FROM users WHERE id = ?", [$user_id]);

if (!$user_data) {
    // User not found, destroy session and redirect
    session_destroy();
    header('Location: login.php');
    exit();
}

$user_name = htmlspecialchars($user_data['prenom'] . ' ' . $user_data['nom']);
$user_email = htmlspecialchars($user_data['email']);

// Get user balance from wallet table
$wallet = getRow($pdo, "SELECT balance_xaf, balance_rmb FROM wallet WHERE user_id = ?", [$user_id]);
$balance_xaf = $wallet ? $wallet['balance_xaf'] : 0;
$balance_rmb = $wallet ? $wallet['balance_rmb'] : 0;

// Get transaction count and volume for this user
$trans_stats = getRow($pdo, "SELECT COUNT(*) as transaction_count, COALESCE(SUM(amount), 0) as total_volume FROM transactions WHERE user_id = ? AND status = 'completed'", [$user_id]);
$transaction_count = $trans_stats['transaction_count'] ?? 0;
$total_volume = $trans_stats['total_volume'] ?? 0;

// Get exchange rate from database - Always gets the latest rate updated by admin
// The exchange_rates table is managed exclusively by administrators
// No default rate is set; the rate must be defined in the database
$exchange_rate_data = getRow(
    $pdo, 
    "SELECT rate, updated_at FROM exchange_rates WHERE currency_from = 'RMB' AND currency_to = 'XAF' ORDER BY updated_at DESC LIMIT 1"
);

if (!$exchange_rate_data) {
    $exchange_rate = null;
    $rate_last_update = null;
} else {
    $exchange_rate = floatval($exchange_rate_data['rate']);
    $rate_last_update = htmlspecialchars($exchange_rate_data['updated_at']);
}

// Gestion des actions rapides (paiement, recharge)
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'logout') {
        session_destroy();
        header('Location: login.php');
        exit();
    }

// --- AJOUT FAPSHI ---
if ($action === 'fapshi_initiate') {
    require_once 'fapshi_helper.php';
    
    $amount = intval($_POST['amount'] ?? 0);
    $email = $user_email; // Utilise l'email de l'utilisateur connecté
    $externalId = 'PAY-' . time(); // ID unique pour cette tentative
    $description = "Paiement Alipayement - Utilisateur: " . $user_id;

    if ($amount < 100) {
        $error_message = "Le montant minimum est de 100 FCFA.";
    } else {
        $result = createFapshiPaymentLink($amount, $email, $user_id, $externalId, $description);
        
        if ($result['success']) {
            // Redirection immédiate vers Fapshi
            header("Location: " . $result['link']);
            exit();
        } else {
            $error_message = "Erreur Fapshi : " . $result['message'];
        }
    }
}
// --- FIN AJOUT FAPSHI ---
    
    if ($action === 'make_payment') {
        $amount_xaf = intval($_POST['amount_xaf'] ?? 0);
        $recipient_email = trim($_POST['recipient_email'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validate inputs
        if ($amount_xaf <= 0) {
            $error_message = "Le montant doit être supérieur à 0.";
        } elseif ($amount_xaf > $balance_xaf) {
            $error_message = "Solde insuffisant pour effectuer ce paiement.";
        } elseif (empty($recipient_email)) {
            $error_message = "L'email du bénéficiaire est requis.";
        } else {
            // Insert transaction
            $transaction_id = 'TXN' . strtoupper(uniqid());
            executeQuery(
                $pdo,
                "INSERT INTO transactions (user_id, recipient_email, amount, description, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())",
                [$user_id, $recipient_email, $amount_xaf, $description]
            );
            
            // Update wallet balance
            executeQuery(
                $pdo,
                "UPDATE users SET balance_xaf = balance_xaf - ? WHERE id = ?",
                [$amount_xaf, $user_id]
            );
            
            $success_message = "Paiement de " . number_format($amount_xaf, 0, ',', ',') . " FCFA soumis avec succès. ID Transaction: " . $transaction_id;
            
            // Refresh balance
            $wallet = getRow($pdo, "SELECT balance_xaf, balance_rmb FROM users WHERE id = ?", [$user_id]);
            $balance_xaf = $wallet ? $wallet['balance_xaf'] : 0;
            $balance_rmb = $wallet ? $wallet['balance_rmb'] : 0;
        }
    }
    
    if ($action === 'recharge_balance') {
        // Check if exchange rate is available
        if ($exchange_rate === null) {
            $error_message = "Le taux de change n'est pas disponible. Veuillez contacter l'administrateur.";
        } else {
            $amount_rmb = floatval($_POST['amount_rmb'] ?? 0);
            $amount_xaf = intval($amount_rmb * $exchange_rate);
            
            // Validate input
            if ($amount_rmb <= 0) {
                $error_message = "Le montant de recharge doit être supérieur à 0.";
            } else {
            // Insert recharge transaction
            executeQuery(
                $pdo,
                "INSERT INTO transactions (user_id, amount, description, status, type, created_at) VALUES (?, ?, ?, 'completed', 'recharge', NOW())",
                [$user_id, $amount_xaf, 'Recharge de solde']
            );
            
            // Update wallet balance
            executeQuery(
                $pdo,
                "UPDATE users SET balance_xaf = balance_xaf + ?, balance_rmb = balance_rmb + ? WHERE id = ?",
                [$amount_xaf, $amount_rmb, $user_id]
            );
            
            $success_message = "Recharge de " . number_format($amount_rmb, 2, ',', ',') . " RMB (≈ " . number_format($amount_xaf, 0, ',', ',') . " FCFA) effectuée avec succès.";
            
            // Refresh balance
            $wallet = getRow($pdo, "SELECT balance_xaf, balance_rmb FROM users WHERE id = ?", [$user_id]);
            $balance_xaf = $wallet ? $wallet['balance_xaf'] : 0;
            $balance_rmb = $wallet ? $wallet['balance_rmb'] : 0;
            }
        }
    }
}

// Récupération des transactions avec pagination
$page = intval($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$transactions_result = executeQuery(
    $pdo,
    "SELECT id, recipient_email, amount, description, status, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$user_id, $per_page, $offset]
);
$transactions = is_array($transactions_result) ? $transactions_result : [];

$total_transactions = getRow($pdo, "SELECT COUNT(*) as count FROM transactions WHERE user_id = ?", [$user_id]);
$total_count = $total_transactions['count'] ?? 0;
$total_pages = ceil($total_count / $per_page);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tableau de bord Alipayement">
    <title>Tableau de Bord - Alipayement</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        /* Animation pour la mise à jour du taux */
        #exchange-rate-container.updated {
            animation: pulse 0.5s ease-in-out;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }
            50% {
                box-shadow: 0 0 12px rgba(0, 82, 204, 0.3);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <span class="text-primary">Ali</span><span class="text-danger">Payement</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>Utilisateur
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Paramètres</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit" class="dropdown-item text-danger" style="border: none; background: none; padding: 0.25rem 1rem;">
                                        <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="pt-5 mt-3">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 col-md-4 mb-4">
                    <div class="card shadow-sm border-0 rounded-lg sticky-top" style="top: 100px;">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <div class="avatar-lg rounded-circle bg-gradient d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                    <i class="fas fa-user text-white" style="font-size: 2rem;"></i>
                                </div>
                                <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($user_name); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($user_email); ?></small>
                            </div>

                            <hr>

                            <nav class="nav flex-column">
                                <a href="#dashboard" class="nav-link active rounded-lg mb-2" onclick="switchTab('dashboard', event)">
                                    <i class="fas fa-th-large me-2 text-primary"></i>Tableau de Bord
                                </a>
                                <a href="#transactions" class="nav-link rounded-lg mb-2" onclick="switchTab('transactions', event)">
                                    <i class="fas fa-exchange-alt me-2 text-danger"></i>Transactions
                                </a>
                                <a href="#wallet" class="nav-link rounded-lg mb-2" onclick="switchTab('wallet', event)">
                                    <i class="fas fa-wallet me-2 text-primary"></i>Portefeuille
                                </a>
                                <a href="#settings" class="nav-link rounded-lg mb-2" onclick="switchTab('settings', event)">
                                    <i class="fas fa-cog me-2 text-danger"></i>Paramètres
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="col-lg-9 col-md-8">
                    <!-- Dashboard Tab -->
                    <div id="dashboard-content" class="tab-content active">
                        <h2 class="mb-4 fw-bold">Tableau de Bord</h2>

                        <!-- Status Alert -->
                        <div class="alert alert-success alert-dismissible fade show rounded-lg" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Compte Actif!</strong> Votre compte a été validé par l'administrateur.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>

                        <!-- Success Message -->
                        <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show rounded-lg" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Error Message -->
                        <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show rounded-lg" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <!-- Stats Row -->
                        <div class="row mb-4">
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card shadow-sm border-0 rounded-lg overflow-hidden">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <small class="text-muted fw-bold d-block mb-2">SOLDE (FCFA)</small>
                                                <h3 class="text-primary fw-bold"><?php echo number_format($balance_xaf, 0, ',', ','); ?></h3>
                                                <small class="text-muted d-block mt-2">≈ <span><?php echo number_format($balance_rmb, 0, ',', ','); ?></span> RMB</small>
                                            </div>
                                            <div class="bg-primary bg-opacity-10 rounded-lg p-3">
                                                <i class="fas fa-wallet text-primary" style="font-size: 1.5rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card shadow-sm border-0 rounded-lg overflow-hidden">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <small class="text-muted fw-bold d-block mb-2">TRANSACTIONS</small>
                                                <h3 class="text-danger fw-bold"><?php echo $transaction_count; ?></h3>
                                            </div>
                                            <div class="bg-danger bg-opacity-10 rounded-lg p-3">
                                                <i class="fas fa-exchange-alt text-danger" style="font-size: 1.5rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card shadow-sm border-0 rounded-lg overflow-hidden">
                                    <div class="card-body p-4">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <small class="text-muted fw-bold d-block mb-2">VOLUME TOTAL</small>
                                                <h3 class="text-primary fw-bold"><?php echo number_format($total_volume, 0, ',', ','); ?> XAF</h3>
                                            </div>
                                            <div class="bg-primary bg-opacity-10 rounded-lg p-3">
                                                <i class="fas fa-chart-line text-primary" style="font-size: 1.5rem;"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="fw-bold mb-3">Actions Rapides</h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary rounded-lg" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                        <i class="fas fa-plus me-2"></i>Effectuer un Paiement
                                    </button>
                                    <button class="btn btn-outline-primary rounded-lg" data-bs-toggle="modal" data-bs-target="#rechargeModal">
                                        <i class="fas fa-arrow-down me-2"></i>Recharger Solde
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Exchange Rate Info Card -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <?php if ($exchange_rate !== null): ?>
                                <div class="card shadow-sm border-0 rounded-lg bg-light" id="exchange-rate-container">
                                    <div class="card-body d-flex justify-content-between align-items-center p-3">
                                        <div>
                                            <small class="text-muted fw-bold d-block mb-1">TAUX DE CHANGE</small>
                                            <h6 class="mb-0">1 RMB = <span class="text-primary fw-bold" id="exchange-rate-value"><?php echo number_format($exchange_rate, 2, ',', ','); ?> FCFA</span></h6>
                                        </div>
                                        <small class="text-muted text-end" id="rate-last-update">
                                            <i class="fas fa-sync-alt me-1"></i>
                                            <?php if ($rate_last_update): ?>
                                                Mis à jour le <?php echo date('d/m/Y à H:i', strtotime($rate_last_update)); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning alert-dismissible fade show rounded-lg" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Attention:</strong> Aucun taux de change n'a été défini pour le moment. Veuillez contacter l'administrateur pour mettre à jour le taux de change.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Transactions -->
                        <div class="card shadow-sm border-0 rounded-lg">
                            <div class="card-header bg-light border-bottom">
                                <h5 class="mb-0 fw-bold">Transactions Récentes</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Montant</th>
                                                <th>Bénéficiaire</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (is_array($transactions) && count($transactions) > 0): ?>
                                                <?php foreach ($transactions as $trans): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></td>
                                                    <td>
                                                        <?php if (!empty($trans['description']) && strpos($trans['description'], 'Recharge') !== false): ?>
                                                            <i class="fas fa-arrow-down text-primary me-2"></i>Recharge
                                                        <?php else: ?>
                                                            <i class="fas fa-arrow-up text-danger me-2"></i>Envoi
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo number_format($trans['amount'], 0, ',', ','); ?> XAF</td>
                                                    <td><?php echo htmlspecialchars($trans['recipient_email'] ?? 'Solde'); ?></td>
                                                    <td>
                                                        <?php 
                                                            if ($trans['status'] === 'completed') {
                                                                echo '<span class="badge bg-success">Réussi</span>';
                                                            } elseif ($trans['status'] === 'pending') {
                                                                echo '<span class="badge bg-warning">En Attente</span>';
                                                            } else {
                                                                echo '<span class="badge bg-danger">Échoué</span>';
                                                            }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        <i class="fas fa-inbox me-2"></i>Aucune transaction pour le moment
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-light border-top">
                                <nav aria-label="Transaction pagination">
                                    <ul class="pagination mb-0 justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1">Première</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Précédente</a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Première</span>
                                            </li>
                                            <li class="page-item disabled">
                                                <span class="page-link">Précédente</span>
                                            </li>
                                        <?php endif; ?>

                                        <?php 
                                            $start = max(1, $page - 2);
                                            $end = min($total_pages, $page + 2);
                                            
                                            for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Suivante</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $total_pages; ?>">Dernière</a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Suivante</span>
                                            </li>
                                            <li class="page-item disabled">
                                                <span class="page-link">Dernière</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transactions Tab -->
                    <div id="transactions-content" class="tab-content" style="display: none;">
                        <h2 class="mb-4 fw-bold">Historique des Transactions</h2>
                        
                        <div class="card shadow-sm border-0 rounded-lg">
                            <div class="card-body p-4">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Type</th>
                                                <th>Bénéficiaire</th>
                                                <th>Montant</th>
                                                <th>Statut</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (is_array($transactions) && count($transactions) > 0): ?>
                                                <?php foreach ($transactions as $trans): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($trans['created_at'])); ?></td>
                                                    <td>
                                                        <?php if (!empty($trans['description']) && strpos($trans['description'], 'Recharge') !== false): ?>
                                                            <i class="fas fa-arrow-down text-primary me-2"></i>Recharge
                                                        <?php else: ?>
                                                            <i class="fas fa-arrow-up text-danger me-2"></i>Envoi
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($trans['recipient_email'] ?? 'Solde'); ?></td>
                                                    <td><?php echo number_format($trans['amount'], 0, ',', ','); ?> XAF</td>
                                                    <td>
                                                        <?php 
                                                            if ($trans['status'] === 'completed') {
                                                                echo '<span class="badge bg-success">Réussi</span>';
                                                            } elseif ($trans['status'] === 'pending') {
                                                                echo '<span class="badge bg-warning">En Attente</span>';
                                                            } else {
                                                                echo '<span class="badge bg-danger">Échoué</span>';
                                                            }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        <i class="fas fa-inbox me-2"></i>Aucune transaction pour le moment
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-light border-top">
                                <nav aria-label="Transaction pagination">
                                    <ul class="pagination mb-0 justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="dashboard.php?page=1">Première</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="dashboard.php?page=<?php echo $page - 1; ?>">Précédente</a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Première</span>
                                            </li>
                                            <li class="page-item disabled">
                                                <span class="page-link">Précédente</span>
                                            </li>
                                        <?php endif; ?>

                                        <?php 
                                            $start = max(1, $page - 2);
                                            $end = min($total_pages, $page + 2);
                                            
                                            for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="dashboard.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="dashboard.php?page=<?php echo $page + 1; ?>">Suivante</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="dashboard.php?page=<?php echo $total_pages; ?>">Dernière</a>
                                            </li>
                                        <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">Suivante</span>
                                            </li>
                                            <li class="page-item disabled">
                                                <span class="page-link">Dernière</span>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Wallet Tab -->
                    <div id="wallet-content" class="tab-content" style="display: none;">
                        <h2 class="mb-4 fw-bold">Mon Portefeuille</h2>
                        
                        <div class="row">
                            <div class="col-md-8 mb-4">
                                <div class="card shadow-sm border-0 rounded-lg bg-gradient text-white p-5">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <small class="opacity-75">Solde Actuel</small>
                                            <h2 class="fw-bold mt-2"><?php echo number_format($balance_xaf, 0, ',', ','); ?> XAF</h2>
                                        </div>
                                        <i class="fas fa-credit-card" style="font-size: 2.5rem; opacity: 0.5;"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 mb-4">
                                <div class="card shadow-sm border-0 rounded-lg">
                                    <div class="card-body p-4">
                                        <h6 class="fw-bold mb-3">Numéro MTN</h6>
                                        <p class="text-muted mb-3">+237 6XX XXX XXX</p>
                                        <button class="btn btn-primary btn-sm w-100">Vérifier</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                                <div class="card shadow-sm border-0 rounded-lg">
                                    <div class="card-body p-4">
                                        <h6 class="fw-bold mb-3">Numéro orange</h6>
                                        <p class="text-muted mb-3">+237 6XX XXX XXX</p>
                                        <button class="btn btn-primary btn-sm w-100">Vérifier</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <button class="btn btn-primary btn-lg w-100 rounded-lg">
                                    <i class="fas fa-arrow-down me-2"></i>Recharger Solde
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <button class="btn btn-outline-primary btn-lg w-100 rounded-lg">
                                    <i class="fas fa-arrow-up me-2"></i>Retirer Argent
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div id="settings-content" class="tab-content" style="display: none;">
                        <h2 class="mb-4 fw-bold">Paramètres</h2>
                        
                        <div class="card shadow-sm border-0 rounded-lg mb-4">
                            <div class="card-header bg-light border-bottom">
                                <h5 class="mb-0 fw-bold">Paramètres du Compte</h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST">
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Nom Complet</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_name); ?>" disabled>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Email</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Téléphone</label>
                                        <input type="tel" class="form-control" value="+237 6XX XXX XXX" disabled>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-credit-card me-2 text-primary"></i>Effectuer un Paiement
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Payment Method Selection -->
                    <div class="mb-4">
                        <h6 class="fw-bold mb-3">Sélectionnez le mode de paiement</h6>
                        <div class="row g-3">
                            <!-- MTN Mobile Money -->
                            <div class="col-md-6">
                                <input type="radio" name="paymentMethod" id="mtnMethod" value="mtn" class="form-check-input" checked>
                                <label for="mtnMethod" class="payment-option p-3 d-block rounded-lg border cursor-pointer" style="cursor: pointer;">
                                    <i class="fas fa-mobile-alt me-2 text-primary" style="font-size: 1.5rem;"></i>
                                    <strong>MTN Mobile Money</strong>
                                    <small class="text-muted d-block mt-1">Paiement via MTN</small>
                                </label>
                            </div>

                             <!-- Orange Money -->
                            <div class="col-md-6">
                                <input type="radio" name="paymentMethod" id="orangeMethod" value="mtn" class="form-check-input" checked>
                                <label for="orangeMethod" class="payment-option p-3 d-block rounded-lg border cursor-pointer" style="cursor: pointer;">
                                    <i class="fas fa-mobile-alt me-2 text-primary" style="font-size: 1.5rem;"></i>
                                    <strong>Orange Money</strong>
                                    <small class="text-muted d-block mt-1">Paiement via Orange</small>
                                </label>
                            </div>


                            <!-- QR Code Alipay -->
                            <div class="col-md-6">
                                <input type="radio" name="paymentMethod" id="qrcodeMethod" value="qrcode" class="form-check-input">
                                <label for="qrcodeMethod" class="payment-option p-3 d-block rounded-lg border cursor-pointer" style="cursor: pointer;">
                                    <i class="fas fa-qrcode me-2 text-danger" style="font-size: 1.5rem;"></i>
                                    <strong>QR Code Alipay</strong>
                                    <small class="text-muted d-block mt-1">Télécharger QR code</small>
                                </label>
                            </div>

                            <!-- Numéro Alipay -->
                            <div class="col-md-6">
                                <input type="radio" name="paymentMethod" id="alipayMethod" value="alipay" class="form-check-input">
                                <label for="alipayMethod" class="payment-option p-3 d-block rounded-lg border cursor-pointer" style="cursor: pointer;">
                                    <i class="fas fa-hashtag me-2 text-success" style="font-size: 1.5rem;"></i>
                                    <strong>Numéro Alipay</strong>
                                    <small class="text-muted d-block mt-1">Numéro chinois Alipay</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Amount -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Montant à Payer</label>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label for="paymentAmount" class="form-label small text-muted">FCFA</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="paymentAmount" placeholder="Ex: 10000" min="1">
                                    <span class="input-group-text">XAF</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="paymentRMB" class="form-label small text-muted">RMB (Automatique)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="paymentRMB" placeholder="0" min="0" readonly style="background-color: #f8f9fa;">
                                    <span class="input-group-text">RMB</span>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Solde actuel: <strong id="currentBalance"><?php echo number_format($balance_xaf, 0, ',', ','); ?> XAF</strong></small>
                    </div>

                    <!-- MTN Method Details -->
                    <div id="mtnDetails" class="mb-4">
                        <label for="mtnNumber" class="form-label fw-bold">
                            <i class="fas fa-mobile-alt me-2"></i>Votre Numéro MTN
                        </label>
                        <input type="tel" class="form-control" id="mtnNumber" placeholder="+237 6XX XXX XXX" pattern="\+237[0-9]{9}">
                        <small class="text-muted d-block mt-2">Le paiement sera envoyé à: <strong>+237 677862573</strong> (Isaga verinyu )</small>
                        <small class="text-info d-block mt-1">
                            <i class="fas fa-lock me-1"></i>Le numéro du destinataire est sécurisé
                        </small>
                    </div>

                    <!-- Orange Method Details -->
                    <div id="orangeDetails" class="mb-4">
                        <label for="ornageNumber" class="form-label fw-bold">
                            <i class="fas fa-mobile-alt me-2"></i>Votre Numéro Orange
                        </label>
                        <input type="tel" class="form-control" id="orangeNumber" placeholder="+237 6XX XXX XXX" pattern="\+237[0-9]{9}">
                        <small class="text-muted d-block mt-2">Le paiement sera envoyé à: <strong>+237 658411505</strong> (Cecile Chantal)</small>
                        <small class="text-info d-block mt-1">
                            <i class="fas fa-lock me-1"></i>Le numéro du destinataire est sécurisé
                        </small>
                    </div>

                    <!-- QR Code Method Details -->
                    <div id="qrcodeDetails" class="mb-4" style="display: none;">
                        <label class="form-label fw-bold">
                            <i class="fas fa-qrcode me-2"></i>Télécharger votre QR Code Alipay
                        </label>
                        <div class="input-group">
                            <input type="file" class="form-control" id="qrcodeFile" accept="image/*,.pdf">
                            <label class="input-group-text" for="qrcodeFile">
                                <i class="fas fa-upload me-2"></i>Parcourir
                            </label>
                        </div>
                        <small class="text-muted d-block mt-2">
                            Formats acceptés: JPG, PNG, PDF (Max: 5 Mo)
                        </small>
                        <div id="qrcodePreview" class="mt-3"></div>
                        <p class="text-warning mt-3 small">
                            <i class="fas fa-exclamation-circle me-2"></i>Les fonds seront reçus par l'administrateur qui se chargera de régler le fournisseur.
                        </p>
                    </div>

                    <!-- Alipay Number Details -->
                    <div id="alipayDetails" class="mb-4" style="display: none;">
                        <label for="alipayNumber" class="form-label fw-bold">
                            <i class="fas fa-hashtag me-2"></i>Numéro Alipay Chinois
                        </label>
                        <input type="text" class="form-control" id="alipayNumber" placeholder="Ex: 1234567890">
                        <small class="text-muted d-block mt-2">Entrez le numéro Alipay du fournisseur</small>
                        <p class="text-warning mt-3 small">
                            <i class="fas fa-exclamation-circle me-2"></i>Les fonds seront reçus par l'administrateur qui se chargera de régler le fournisseur.
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="confirmPaymentBtn">
                        <i class="fas fa-check me-2"></i>Confirmer le Paiement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recharge Modal -->
    <div class="modal fade" id="rechargeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-arrow-down me-2 text-primary"></i>Recharger Solde
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Recharge Method -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Mode de Recharge</label>
                        <div class="card border rounded-lg p-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-mobile-alt me-3 text-primary" style="font-size: 2rem;"></i>
                                <div>
                                    <h6 class="mb-1">MTN Mobile Money</h6>
                                    <small class="text-muted">Rechargez via MTN</small>
                                </div>
                                <div>
                                    <h7 class="mb-1">Orange Money</h7>
                                    <small class="text-muted">Rechargez via Orange</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recharge Amount -->
                    <div class="mb-4">
                        <label for="rechargeAmount" class="form-label fw-bold">Montant à Recharger</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="rechargeAmount" placeholder="Ex: 10000" min="1">
                            <span class="input-group-text">XAF</span>
                        </div>
                        <small class="text-muted d-block mt-2">Solde actuel: <strong id="rechargeCurrentBalance"><?php echo number_format($balance_xaf, 0, ',', ','); ?> XAF</strong></small>
                    </div>

                    <!-- MTN Number for Recharge -->
                    <div class="mb-4">
                        <label for="rechargeMtnNumber" class="form-label fw-bold">Votre Numéro MTN</label>
                        <input type="tel" class="form-control" id="rechargeMtnNumber" placeholder="+237 6XX XXX XXX" pattern="\+237[0-9]{9}">
                        <small class="text-muted d-block mt-2">À partir duquel vous allez effectuer le paiement</small>
                    </div>

                    <!-- Orange Number for Recharge -->
                    <div class="mb-4">
                        <label for="rechargeOrangeNumber" class="form-label fw-bold">Votre Numéro Orange</label>
                        <input type="tel" class="form-control" id="rechargeOrangeNumber" placeholder="+237 6XX XXX XXX" pattern="\+237[0-9]{9}">
                        <small class="text-muted d-block mt-2">À partir duquel vous allez effectuer le paiement</small>
                    </div>

                    <!-- Balance Info -->
                    <div class="alert alert-info alert-sm" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nouveau solde:</strong> <span id="projectedBalance"><?php echo number_format($balance_xaf, 0, ',', ','); ?> XAF</span>
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="confirmRechargeBtn">
                        <i class="fas fa-arrow-down me-2"></i>Recharger
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tabName, event) {
            event.preventDefault();
            
            // Masquer tous les onglets
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Retirer la classe active de tous les liens
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Afficher l'onglet sélectionné
            const tabId = tabName + '-content';
            const tabElement = document.getElementById(tabId);
            if (tabElement) {
                tabElement.style.display = 'block';
            }
            
            // Ajouter la classe active au lien cliqué
            event.target.closest('.nav-link').classList.add('active');
        }

        // Payment Modal - Payment Method Selection
        const paymentMethods = document.querySelectorAll('input[name="paymentMethod"]');
        const mtnDetails = document.getElementById('mtnDetails');
        const orangeDetails = document.getElementById('orangeDetails');
        const qrcodeDetails = document.getElementById('qrcodeDetails');
        const alipayDetails = document.getElementById('alipayDetails');

        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                mtnDetails.style.display = 'none';
                qrcodeDetails.style.display = 'none';
                alipayDetails.style.display = 'none';

                if (this.value === 'mtn') {
                    mtnDetails.style.display = 'block';
                } else if (this.value === 'qrcode') {
                    qrcodeDetails.style.display = 'block';
                } else if (this.value === 'alipay') {
                    alipayDetails.style.display = 'block';
                }
            });
        });

        // Payment Amount Calculation
        const paymentAmountInput = document.getElementById('paymentAmount');
        const paymentRMBInput = document.getElementById('paymentRMB');
        const exchangeRate = <?php echo $exchange_rate; ?>;

        if (paymentAmountInput) {
            paymentAmountInput.addEventListener('input', function() {
                const amount = parseInt(this.value) || 0;
                const rmb = (amount / exchangeRate).toFixed(2);
                paymentRMBInput.value = rmb;
            });
        }

        // QR Code File Preview
        const qrcodeFile = document.getElementById('qrcodeFile');
        const qrcodePreview = document.getElementById('qrcodePreview');

        if (qrcodeFile) {
            qrcodeFile.addEventListener('change', function(e) {
                qrcodePreview.innerHTML = '';
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const preview = document.createElement('div');
                        preview.className = 'text-center';
                        if (file.type.startsWith('image/')) {
                            preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 100%; max-height: 300px;" class="rounded">';
                        } else {
                            preview.innerHTML = '<p class="text-success"><i class="fas fa-file-pdf me-2"></i>Fichier PDF chargé</p>';
                        }
                        qrcodePreview.appendChild(preview);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Confirm Payment Button
const confirmPaymentBtn = document.getElementById('confirmPaymentBtn');
if (confirmPaymentBtn) {
    confirmPaymentBtn.addEventListener('click', function() {
        const amount = document.getElementById('paymentAmount').value;
        const method = document.querySelector('input[name="paymentMethod"]:checked').value;

        if (!amount || amount < 100) {
            alert("Veuillez entrer un montant valide (min 100 FCFA).");
            return;
        }

        // Si c'est un paiement mobile money (MTN/Orange), on utilise Fapshi
        if (method === 'mtn' || method === 'orange') {
            // Créer un formulaire dynamique pour envoyer les données en POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'dashboard.php';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'fapshi_initiate';
            form.appendChild(actionInput);

            const amountInput = document.createElement('input');
            amountInput.type = 'hidden';
            amountInput.name = 'amount';
            amountInput.value = amount;
            form.appendChild(amountInput);

            document.body.appendChild(form);
            form.submit();
        } else {
            // Pour les autres méthodes (QR Code, etc.), gardez votre logique actuelle
            alert("Cette méthode de paiement sera traitée manuellement par l'administrateur.");
            // ... votre logique existante ...
        }
    });
}

        // Recharge Amount Calculation
        const rechargeAmountInput = document.getElementById('rechargeAmount');
        const projectedBalance = document.getElementById('projectedBalance');
        const currentBalance = <?php echo $balance_xaf; ?>;

        if (rechargeAmountInput) {
            rechargeAmountInput.addEventListener('input', function() {
                const amount = parseInt(this.value) || 0;
                const newBalance = currentBalance + amount;
                projectedBalance.textContent = newBalance.toLocaleString('fr-FR') + ' XAF';
            });
        }

        // Confirm Recharge Button
        const confirmRechargeBtn = document.getElementById('confirmRechargeBtn');
        if (confirmRechargeBtn) {
            confirmRechargeBtn.addEventListener('click', function() {
                const amount = document.getElementById('rechargeAmount').value;
                const mtnNumber = document.getElementById('rechargeMtnNumber').value;

                if (!amount || amount <= 0) {
                    alert('Veuillez entrer un montant valide');
                    return;
                }

                if (!mtnNumber) {
                    alert('Veuillez entrer votre numéro MTN');
                    return;
                }

                // Redirect to receipt page
        window.location.href = "receipt.php";
            });
        }
    </script>
    
    <!-- Script pour la mise à jour en temps réel du taux de change -->
    <script src="js/exchange_rate_realtime.js"></script>
</body>
</html>
