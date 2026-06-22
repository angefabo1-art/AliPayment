<?php
session_start();

// Include database connection
require_once __DIR__ . '/config/db.php';
global $pdo;

// Verify PDO connection is available
if (empty($GLOBALS['pdo'])) {
    die("Erreur: Connexion à la base de données non disponible.");
}

// Vérification des droits d'administrateur
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'admin') {
    // Redirect to appropriate login page
    if (isset($_SESSION['user_id'])) {
        // If regular user is logged in, log them out
        session_destroy();
        session_start();
    }
    header('Location: logadmin.php');
    exit();
}

// Gestion des actions POST (approbation, rejet)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $account_id = intval($_POST['account_id'] ?? 0);
    
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
        $reject_reason = $_POST['reject_reason'] ?? '';
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

// Fetch pending accounts from database
$pending_accounts = getRows(
    $pdo,
    "SELECT id, nom, prenom, telephone, email, adresse, activite, login, status, created_at as dateSubmission FROM users WHERE status = 'pending' ORDER BY created_at DESC"
);

if (!$pending_accounts) {
    $pending_accounts = [];
}

// Statistics
$pending_count = getRowCount($pdo, "SELECT 1 FROM users WHERE status = 'pending'");
$approved_count = getRowCount($pdo, "SELECT 1 FROM users WHERE status = 'approved'");
$rejected_count = getRowCount($pdo, "SELECT 1 FROM users WHERE status = 'rejected'");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comptes en Attente - Alipayement Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    
    <style>
        .admin-container {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 30px 0;
        }

        .admin-header {
            background: linear-gradient(135deg, #0052CC 0%, #003DA5 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 24px rgba(0, 82, 204, 0.15);
        }

        .admin-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .admin-header .stats {
            display: flex;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-item i {
            font-size: 1.5rem;
            opacity: 0.9;
        }

        .stat-item .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .pending-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #DC3545;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .pending-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .pending-card.approved {
            border-left-color: #28A745;
        }

        .applicant-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0052CC;
        }

        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-pending {
            background-color: #FFF3CD;
            color: #856404;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #6C757D;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1rem;
            color: #212529;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-approve {
            background: linear-gradient(135deg, #28A745 0%, #1E7E34 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .btn-reject {
            background: linear-gradient(135deg, #DC3545 0%, #C82333 100%);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6C757D;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        @media (max-width: 768px) {
            .admin-header {
                padding: 20px;
            }

            .admin-header h1 {
                font-size: 1.5rem;
            }

            .admin-header .stats {
                gap: 15px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #0052CC 0%, #003DA5 100%);">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-credit-card me-2"></i>Alipayement Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="pending-accounts.php">Comptes en Attente</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Utilisateurs Approuvés</a>
                    </li>
                    <li class="nav-item">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="nav-link" style="border: none; background: none;">
                                <a class="dropdown-item" href="logoutadmin.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                                </a>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Admin Container -->
    <div class="admin-container">
        <div class="container">
            <!-- Admin Header -->
            <div class="admin-header">
                <h1><i class="fas fa-tasks me-3"></i>Gestion des Demandes d'Inscription</h1>
                <div class="stats">
                    <div class="stat-item">
                        <i class="fas fa-hourglass-half"></i>
                        <div>
                            <div class="stat-number"><?php echo $pending_count; ?></div>
                            <div class="stat-label">En Attente</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="stat-number"><?php echo $approved_count; ?></div>
                            <div class="stat-label">Approuvés</div>
                        </div>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-times-circle"></i>
                        <div>
                            <div class="stat-number"><?php echo $rejected_count; ?></div>
                            <div class="stat-label">Rejetés</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Message Alert -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-title fw-bold mb-3">Filtrer les Demandes</div>
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">Tous les Statuts</option>
                            <option value="pending">En Attente</option>
                            <option value="approved">Approuvés</option>
                            <option value="rejected">Rejetés</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="activity">
                            <option value="">Tous les Secteurs</option>
                            <option value="commerce">Commerce</option>
                            <option value="services">Services</option>
                            <option value="transport">Transport</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Rechercher par nom, email ou téléphone...">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Chercher
                        </button>
                    </div>
                </form>
            </div>

            <!-- Pending Accounts List -->
            <div id="accountsList" class="accounts-list">
                <?php foreach ($pending_accounts as $account): ?>
                    <div class="pending-card <?php echo $account['status'] === 'approved' ? 'approved' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="applicant-name"><?php echo htmlspecialchars($account['prenom'] . ' ' . $account['nom']); ?></h5>
                            </div>
                            <span class="badge-status badge-pending">
                                <i class="fas fa-hourglass-half me-1"></i><?php echo ucfirst($account['status']); ?>
                            </span>
                        </div>

                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($account['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Téléphone</span>
                                <span class="info-value"><?php echo htmlspecialchars($account['telephone']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Secteur d'Activité</span>
                                <span class="info-value"><?php echo ucfirst($account['activite']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Login</span>
                                <span class="info-value"><?php echo htmlspecialchars($account['login']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Adresse</span>
                                <span class="info-value"><?php echo htmlspecialchars($account['adresse']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Date de Demande</span>
                                <span class="info-value"><?php echo $account['dateSubmission']; ?></span>
                            </div>
                        </div>

                        <div class="action-buttons">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-approve" onclick="return confirm('Approuver ce compte?');">
                                    <i class="fas fa-check"></i> Approuver
                                </button>
                            </form>

                            <button class="btn btn-reject" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $account['id']; ?>">
                                <i class="fas fa-times"></i> Rejeter
                            </button>
                        </div>
                    </div>

                    <!-- Modal de Rejet -->
                    <div class="modal fade" id="rejectModal<?php echo $account['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title">Rejeter la Demande</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <div class="mb-3">
                                            <label class="form-label">Motif du Rejet</label>
                                            <textarea class="form-control" name="reject_reason" rows="4" required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                        <button type="submit" class="btn btn-danger">Confirmer le Rejet</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>