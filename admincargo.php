<?php
session_start();

// Include database connection
require_once __DIR__ . '/config/db.php';
global $pdo;

// Verify PDO connection is available
if (!isset($GLOBALS['pdo']) || $GLOBALS['pdo'] === null) {
    die("Erreur: Connexion à la base de données non disponible.");
}

// Vérification de l'accès administrateur
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    // Redirect to appropriate login page
    if (isset($_SESSION['user_id'])) {
        // If regular user is logged in, log them out
        session_destroy();
        session_start();
    }
    header('Location: logadmin.php');
    exit();
}

$message = '';
$message_type = '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_activity = isset($_GET['activity']) ? trim($_GET['activity']) : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Traitement des actions (approbation, rejet)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    $account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
    
    if ($action === 'approve' && $account_id > 0) {
        // Update user status to approved in database
        $stmt = executeQuery(
            $pdo,
            "UPDATE users SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?",
            [$_SESSION['admin_id'], $account_id]
        );
        
        if ($stmt) {
            $message = 'Compte approuvé avec succès! L\'utilisateur peut maintenant se connecter.';
            $message_type = 'success';
        } else {
            $message = 'Erreur lors de l\'approbation du compte.';
            $message_type = 'danger';
        }
    } elseif ($action === 'reject' && $account_id > 0) {
        $reject_reason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';
        if (empty($reject_reason)) {
            $message = 'Veuillez fournir un motif de rejet.';
            $message_type = 'danger';
        } else {
            // Update user status to rejected in database
            $stmt = executeQuery(
                $pdo,
                "UPDATE users SET status = 'rejected', rejected_reason = ?, rejected_at = NOW(), rejected_by = ? WHERE id = ?",
                [$reject_reason, $_SESSION['admin_id'], $account_id]
            );
            
            if ($stmt) {
                $message = 'Compte rejeté avec le motif: ' . htmlspecialchars($reject_reason);
                $message_type = 'danger';
            } else {
                $message = 'Erreur lors du rejet du compte.';
                $message_type = 'danger';
            }
        }
    }
}

// Fetch all accounts from database
$query = "SELECT * FROM users";
$params = [];
$conditions = [];

// Apply filters if provided
if (!empty($filter_status)) {
    $conditions[] = "status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_activity)) {
    $conditions[] = "activite = ?";
    $params[] = $filter_activity;
}

if (!empty($search_term)) {
    $conditions[] = "(nom LIKE ? OR prenom LIKE ? OR email LIKE ? OR telephone LIKE ?)";
    $search_param = '%' . $search_term . '%';
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY created_at DESC";

$pending_accounts = getRows($pdo, $query, $params);

// Ensure we have an array
if (!$pending_accounts) {
    $pending_accounts = [];
}

// Apply filters if no database filtering was used
$filtered_accounts = $pending_accounts;

// Statistics
$pending_count = getRowCount($pdo, "SELECT 1 FROM users WHERE status = 'pending'");
$approved_count = getRowCount($pdo, "SELECT 1 FROM users WHERE status = 'approved'");
$rejected_count = getRowCount($pdo, "SELECT 1 FROM users WHERE status = 'rejected'");
$total_count = getRowCount($pdo, "SELECT 1 FROM users");

// Administrateur actuel - À obtenir de la session
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Administrateur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Tableau de bord administrateur - Gestion des comptes en attente">
    <title>Gestion des Comptes - Alipayement Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .navbar-admin {
            background: linear-gradient(135deg, #0052CC 0%, #003DA5 100%) !important;
            box-shadow: 0 4px 12px rgba(0, 82, 204, 0.2);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
        }

        .admin-container {
            padding: 40px 0;
        }

        .admin-header {
            background: white;
            border-radius: 12px;
            padding: 35px;
            margin-bottom: 35px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            border-left: 6px solid #0052CC;
        }

        .admin-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #0052CC;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .admin-header p {
            color: #6C757D;
            font-size: 1.05rem;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-top: 4px solid #0052CC;
        }

        .stat-card.pending {
            border-top-color: #FFC107;
        }

        .stat-card.approved {
            border-top-color: #28A745;
        }

        .stat-card.rejected {
            border-top-color: #DC3545;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 0.95rem;
            color: #6C757D;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .filter-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-control, .form-select {
            border: 1.5px solid #E0E0E0;
            padding: 10px 14px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0052CC;
            box-shadow: 0 0 0 3px rgba(0, 82, 204, 0.1);
        }

        .btn-search {
            background: linear-gradient(135deg, #0052CC 0%, #003DA5 100%);
            border: none;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 82, 204, 0.3);
        }

        .account-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 5px solid #FFC107;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
        }

        .account-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .account-card.approved {
            border-left-color: #28A745;
        }

        .account-card.rejected {
            border-left-color: #DC3545;
        }

        .account-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 15px;
        }

        .applicant-info h5 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0052CC;
            margin-bottom: 8px;
        }

        .applicant-meta {
            display: flex;
            gap: 20px;
            font-size: 0.95rem;
            color: #6C757D;
            flex-wrap: wrap;
        }

        .applicant-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .badge-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            background: #FFF3CD;
            color: #856404;
        }

        .badge-status.approved {
            background: #D4EDDA;
            color: #155724;
        }

        .badge-status.rejected {
            background: #F8D7DA;
            color: #721C24;
        }

        .account-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #E0E0E0;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.85rem;
            font-weight: 700;
            color: #6C757D;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .detail-value {
            font-size: 1.05rem;
            color: #212529;
            font-weight: 500;
        }

        .detail-value a {
            color: #0052CC;
            text-decoration: none;
        }

        .detail-value a:hover {
            text-decoration: underline;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-approve, .btn-reject, .btn-view-doc {
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28A745 0%, #1E7E34 100%);
            color: white;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .btn-reject {
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%);
            color: white;
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
            color: white;
        }

        .btn-view-doc {
            background: linear-gradient(135deg, #17A2B8 0%, #138496 100%);
            color: white;
        }

        .btn-view-doc:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
            color: #6C757D;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h4 {
            font-weight: 700;
            color: #212529;
            margin-bottom: 10px;
        }

        .modal-header.danger {
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%);
            color: white;
        }

        .modal-header.danger .btn-close {
            filter: brightness(0) invert(1);
        }

        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 25px;
        }

        .alert-success {
            background: #D4EDDA;
            color: #155724;
        }

        .alert-danger {
            background: #F8D7DA;
            color: #721C24;
        }

        .alert-warning {
            background: #FFF3CD;
            color: #856404;
        }

        .cni-image-container {
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: inline-block;
        }

        .cni-image-container img {
            max-width: 400px;
            max-height: 300px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            display: block;
        }

        .cni-image-container a {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 16px;
            background: #0052CC;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .cni-image-container a:hover {
            background: #003DA5;
        }

        .footer-admin {
            background: white;
            padding: 20px 0;
            margin-top: 40px;
            border-top: 1px solid #E0E0E0;
            text-align: center;
            color: #6C757D;
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .admin-header {
                padding: 20px;
            }

            .admin-header h1 {
                font-size: 1.6rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .account-header {
                flex-direction: column;
            }

            .account-details {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons button, .action-buttons form button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-admin sticky-top">
        <div class="container">
            <a class="navbar-brand" href="admincargo.php">
                <i class="fas fa-shield-alt me-2"></i>Alipayement Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="admincargo.php">
                            <i class="fas fa-list-check me-2"></i>Comptes en Attente
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pending-accounts.php">
                            <i class="fas fa-clock me-2"></i>Demandes Pendantes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="payment_confirmation.php">
                            <i class="fas fa-credit-card me-2"></i>Confirmations Paiement
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <i class="fas fa-users me-2"></i>Utilisateurs Approuvés
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($admin_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminDropdown">
                            <li><a class="dropdown-item" href="#">Profil</a></li>
                            <li><a class="dropdown-item" href="#">Paramètres</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logoutadmin.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="admin-container">
        <div class="container">
            <!-- Admin Header -->
            <div class="admin-header">
                <h1><i class="fas fa-clipboard-list"></i>Gestion des Demandes d'Inscription</h1>
                <p>Validez et gérez les comptes utilisateurs en attente d'approbation</p>
                
                <div class="stats-grid">
                    <div class="stat-card pending">
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <div class="stat-label"><i class="fas fa-clock me-2"></i>En Attente</div>
                    </div>
                    <div class="stat-card approved">
                        <div class="stat-number"><?php echo $approved_count; ?></div>
                        <div class="stat-label"><i class="fas fa-check-circle me-2"></i>Approuvés</div>
                    </div>
                    <div class="stat-card rejected">
                        <div class="stat-number"><?php echo $rejected_count; ?></div>
                        <div class="stat-label"><i class="fas fa-times-circle me-2"></i>Rejetés</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $total_count; ?></div>
                        <div class="stat-label"><i class="fas fa-database me-2"></i>Total</div>
                    </div>
                </div>
            </div>

            <!-- Message Alert -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>Filtrer les Demandes
                </div>
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous les Statuts</option>
                            <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>En Attente</option>
                            <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>Approuvés</option>
                            <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Rejetés</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="activity" class="form-label">Secteur d'Activité</label>
                        <select class="form-select" id="activity" name="activity">
                            <option value="">Tous les Secteurs</option>
                            <option value="commerce" <?php echo $filter_activity === 'commerce' ? 'selected' : ''; ?>>Commerce</option>
                            <option value="services" <?php echo $filter_activity === 'services' ? 'selected' : ''; ?>>Services</option>
                            <option value="transport" <?php echo $filter_activity === 'transport' ? 'selected' : ''; ?>>Transport</option>
                            <option value="restauration" <?php echo $filter_activity === 'restauration' ? 'selected' : ''; ?>>Restauration</option>
                            <option value="sante" <?php echo $filter_activity === 'sante' ? 'selected' : ''; ?>>Santé</option>
                            <option value="education" <?php echo $filter_activity === 'education' ? 'selected' : ''; ?>>Éducation</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Rechercher</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Nom, email, téléphone..." value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-search w-100">
                            <i class="fas fa-search me-2"></i>Chercher
                        </button>
                    </div>
                </form>
            </div>

            <!-- Accounts List -->
            <div id="accountsList" class="accounts-list">
                <?php if (empty($filtered_accounts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>Aucun compte trouvé</h4>
                        <p>Il n'y a aucun compte correspondant à vos critères de recherche.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($filtered_accounts as $account): ?>
                        <div class="account-card <?php echo $account['status']; ?>">
                            <div class="account-header">
                                <div class="applicant-info">
                                    <h5><?php echo htmlspecialchars($account['prenom'] . ' ' . $account['nom']); ?></h5>
                                    <div class="applicant-meta">
                                        <span><i class="fas fa-envelope"></i><?php echo htmlspecialchars($account['email']); ?></span>
                                        <span><i class="fas fa-phone"></i><?php echo htmlspecialchars($account['telephone']); ?></span>
                                        <span><i class="fas fa-calendar"></i><?php echo date('d/m/Y H:i', strtotime($account['created_at'])); ?></span>
                                    </div>
                                </div>
                                <span class="badge-status <?php echo $account['status']; ?>">
                                    <i class="fas fa-<?php echo $account['status'] === 'pending' ? 'hourglass-half' : ($account['status'] === 'approved' ? 'check-circle' : 'times-circle'); ?> me-1"></i>
                                    <?php echo ucfirst($account['status'] === 'pending' ? 'En Attente' : ($account['status'] === 'approved' ? 'Approuvé' : 'Rejeté')); ?>
                                </span>
                            </div>

                            <div class="account-details">
                                <div class="detail-item">
                                    <span class="detail-label">Adresse Email</span>
                                    <span class="detail-value"><a href="mailto:<?php echo htmlspecialchars($account['email']); ?>"><?php echo htmlspecialchars($account['email']); ?></a></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Téléphone</span>
                                    <span class="detail-value"><a href="tel:<?php echo htmlspecialchars($account['telephone']); ?>"><?php echo htmlspecialchars($account['telephone']); ?></a></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Secteur d'Activité</span>
                                    <span class="detail-value"><?php echo ucfirst($account['activite']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Nom d'Utilisateur</span>
                                    <span class="detail-value"><code><?php echo htmlspecialchars($account['login']); ?></code></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Adresse</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($account['adresse']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Document CNI</span>
                                    <?php if (!empty($account['cni_document'])): ?>
                                        <div class="cni-image-container">
                                            <img src="uploads/cni/<?php echo htmlspecialchars($account['cni_document']); ?>" 
                                                 alt="Document CNI de <?php echo htmlspecialchars($account['prenom'] . ' ' . $account['nom']); ?>"
                                                 onerror="this.src='https://via.placeholder.com/400x300?text=Image+non+disponible'">
                                            <br>
                                            <a href="uploads/cni/<?php echo htmlspecialchars($account['cni_document']); ?>" 
                                               target="_blank" download>
                                                <i class="fas fa-download me-2"></i>Télécharger
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="detail-value"><i class="fas fa-times-circle me-2"></i>Aucun document fourni</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <?php if ($account['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-approve" onclick="return confirm('Êtes-vous sûr de vouloir approuver ce compte?');">
                                            <i class="fas fa-check"></i> Approuver
                                        </button>
                                    </form>

                                    <button type="button" class="btn btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $account['id']; ?>">
                                        <i class="fas fa-times"></i> Rejeter
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="btn btn-view-doc" data-bs-toggle="modal" data-bs-target="#viewDocModal<?php echo $account['id']; ?>">
                                    <i class="fas fa-file-pdf"></i> Voir CNI
                                </button>
                            </div>
                        </div>

                        <!-- Modal Rejet -->
                        <div class="modal fade" id="rejectModal<?php echo $account['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content border-0">
                                    <div class="modal-header danger">
                                        <h5 class="modal-title fw-bold">Rejeter la Demande</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" novalidate>
                                        <div class="modal-body p-4">
                                            <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <div class="mb-3">
                                                <label for="reason<?php echo $account['id']; ?>" class="form-label fw-bold">Motif du Rejet</label>
                                                <textarea class="form-control" id="reason<?php echo $account['id']; ?>" name="reject_reason" rows="4" placeholder="Expliquez le motif du rejet..." required></textarea>
                                                <small class="text-muted">Un email sera envoyé au demandeur avec ce motif.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                            <button type="submit" class="btn btn-danger fw-bold">
                                                <i class="fas fa-times me-2"></i>Confirmer le Rejet
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Voir Document -->
                        <div class="modal fade" id="viewDocModal<?php echo $account['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content border-0">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Document CNI - <?php echo htmlspecialchars($account['prenom'] . ' ' . $account['nom']); ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="text-center py-5">
                                            <i class="fas fa-file-pdf fa-5x text-danger mb-3 d-block"></i>
                                            <p class="text-muted mb-3">Fichier: <strong><?php echo htmlspecialchars($account['cni_document']); ?></strong></p>
                                            <a href="documents/<?php echo htmlspecialchars($account['cni_document']); ?>" class="btn btn-primary" target="_blank">
                                                <i class="fas fa-download me-2"></i>Télécharger
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer-admin">
        <div class="container">
            <p>&copy; 2024 Alipayement - Tableau de bord Administrateur. Tous droits réservés.</p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Gestion des confirmations
        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir approuver ce compte? L\'utilisateur pourra alors se connecter.')) {
                    e.preventDefault();
                }
            });
        });

        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    </script>
</body>
</html>
