<?php
session_start();

// Include database connection
require_once __DIR__ . '/config/db.php';
global $pdo;

// Verify PDO connection is available
if (!isset($GLOBALS['pdo']) || $GLOBALS['pdo'] === null) {
    die("Erreur: Connexion à la base de données non disponible.");
}

// Vérification de la session - Admin access only
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header('Location: logadmin.php');
    exit();
}

// Récupérer le taux de change actuel
// No default rate - must be set by admin
$exchange_rate_data = getRow($pdo, "SELECT rate, updated_at FROM exchange_rates WHERE currency_from = 'RMB' AND currency_to = 'XAF' ORDER BY updated_at DESC LIMIT 1");
$current_exchange_rate = $exchange_rate_data ? floatval($exchange_rate_data['rate']) : null;
$exchange_rate_updated_at = $exchange_rate_data ? htmlspecialchars($exchange_rate_data['updated_at']) : null;

// Récupérer les détails Alipay
$alipay_data = getRow($pdo, "SELECT qr_code, alipay_number, updated_at FROM alipay_settings ORDER BY updated_at DESC LIMIT 1");
$alipay_qr_code = $alipay_data ? $alipay_data['qr_code'] : null;
$alipay_number = $alipay_data ? $alipay_data['alipay_number'] : null;

// Gestion des mises à jour
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_exchange_rate') {
        $new_rate = floatval($_POST['new_rate'] ?? 0);
        
        if ($new_rate <= 0) {
            $error_message = "Le taux de change doit être supérieur à 0.";
        } else {
            // Insert into exchange_rates table
            executeQuery(
                $pdo,
                "INSERT INTO exchange_rates (currency_from, currency_to, rate, set_by, updated_at) VALUES (?, ?, ?, ?, NOW())",
                ['RMB', 'XAF', $new_rate, $_SESSION['admin_id']]
            );
            
            $success_message = "Taux de change mis à jour avec succès: 1 RMB = " . number_format($new_rate, 2, ',', ',') . " FCFA";
            $current_exchange_rate = $new_rate;
        }
    }
    
    if ($action === 'update_alipay') {
        $alipay_number = trim($_POST['alipay_number'] ?? '');
        
        if (empty($alipay_number)) {
            $error_message = "Le numéro Alipay est requis.";
        } else {
            // Update or insert Alipay number
            $existing = getRow($pdo, "SELECT id FROM alipay_settings LIMIT 1");
            
            if ($existing) {
                executeQuery(
                    $pdo,
                    "UPDATE alipay_settings SET alipay_number = ?, updated_at = NOW() WHERE id = ?",
                    [$alipay_number, $existing['id']]
                );
            } else {
                executeQuery(
                    $pdo,
                    "INSERT INTO alipay_settings (alipay_number, updated_at) VALUES (?, NOW())",
                    [$alipay_number]
                );
            }
            
            $success_message = "Numéro Alipay mis à jour avec succès.";
        }
    }
    
    if ($action === 'update_qr_code') {
        if (isset($_FILES['qr_code_file']) && $_FILES['qr_code_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['qr_code_file'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $error_message = "Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, PDF";
            } elseif ($file['size'] > 10 * 1024 * 1024) {
                $error_message = "La taille du fichier ne doit pas dépasser 10 Mo.";
            } else {
                // Create uploads directory if it doesn't exist
                if (!is_dir('uploads/alipay')) {
                    mkdir('uploads/alipay', 0755, true);
                }
                
                $filename = 'qr_' . time() . '_' . basename($file['name']);
                $filepath = 'uploads/alipay/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Update database
                    $existing = getRow($pdo, "SELECT id FROM alipay_settings LIMIT 1");
                    
                    if ($existing) {
                        executeQuery(
                            $pdo,
                            "UPDATE alipay_settings SET qr_code = ?, updated_at = NOW() WHERE id = ?",
                            [$filename, $existing['id']]
                        );
                    } else {
                        executeQuery(
                            $pdo,
                            "INSERT INTO alipay_settings (qr_code, updated_at) VALUES (?, NOW())",
                            [$filename]
                        );
                    }
                    
                    $success_message = "QR Code Alipay mis à jour avec succès.";
                    $alipay_qr_code = $filename;
                } else {
                    $error_message = "Erreur lors de l'upload du fichier.";
                }
            }
        }
    }
}

// Récupérer les paiements en attente
$pending_payments = executeQuery(
    $pdo,
    "SELECT id, user_id, amount, recipient_email, description, created_at FROM transactions WHERE status = 'pending' ORDER BY created_at DESC LIMIT 20"
);
$pending_payments = is_array($pending_payments) ? $pending_payments : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Confirmation de Paiement Admin - Alipayement">
    <title>Confirmation de Paiement - Alipayement</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #0052CC;
            --danger: #DC3545;
            --success: #28A745;
            --warning: #FFC107;
            --dark: #1F2937;
            --light: #F3F4F6;
        }

        body {
            background-color: var(--light);
            font-family: 'Inter', sans-serif;
        }

        .bg-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, #003d99 100%);
        }

        .rounded-lg {
            border-radius: 12px;
        }

        .qr-preview {
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            min-height: 250px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .exchange-rate-display {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 82, 204, 0.05);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="admincargo.php">
                <span class="text-primary">Ali</span><span class="text-danger">Payement</span> <span class="badge bg-warning text-dark ms-2">Admin</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>Administrateur
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Paramètres</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logoutadmin.php"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
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
                                    <i class="fas fa-shield-alt text-white" style="font-size: 2rem;"></i>
                                </div>
                                <h5 class="mt-3 mb-1">Admin Panel</h5>
                                <small class="text-muted">Gestion des paiements</small>
                            </div>

                            <hr>

                            <nav class="nav flex-column">
                                <a href="#alipay" class="nav-link active rounded-lg mb-2" onclick="switchTab('alipay', event)">
                                    <i class="fas fa-qrcode me-2 text-primary"></i>Alipay QR Code
                                </a>
                                <a href="#exchange" class="nav-link rounded-lg mb-2" onclick="switchTab('exchange', event)">
                                    <i class="fas fa-exchange-alt me-2 text-danger"></i>Taux de Change
                                </a>
                                <a href="#payments" class="nav-link rounded-lg mb-2" onclick="switchTab('payments', event)">
                                    <i class="fas fa-credit-card me-2 text-primary"></i>Paiements
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="col-lg-9 col-md-8">
                    <!-- Success/Error Messages -->
                    <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show rounded-lg mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-lg mb-4" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Alipay QR Code Tab -->
                    <div id="alipay-content" class="tab-content active">
                        <h2 class="mb-4 fw-bold">Gestion Alipay QR Code</h2>

                        <div class="alert alert-info alert-dismissible fade show rounded-lg" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Informations importantes:</strong> Cette page permet de gérer le QR code Alipay et le numéro Alipay du fournisseur que les clients verront lors du paiement.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>

                        <div class="row mb-4">
                            <!-- QR Code Upload Section -->
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm border-0 rounded-lg">
                                    <div class="card-header bg-light border-bottom">
                                        <h5 class="mb-0 fw-bold">
                                            <i class="fas fa-qrcode me-2"></i>QR Code Alipay du Fournisseur
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <form method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="update_qr_code">
                                            
                                            <div class="mb-4">
                                                <label for="qrcodeUpload" class="form-label fw-bold">Télécharger le QR Code</label>
                                                <div class="input-group">
                                                    <input type="file" class="form-control" id="qrcodeUpload" name="qr_code_file" accept="image/*,.pdf">
                                                    <label class="input-group-text" for="qrcodeUpload">
                                                        <i class="fas fa-upload me-2"></i>Parcourir
                                                    </label>
                                                </div>
                                                <small class="text-muted d-block mt-2">
                                                    Formats acceptés: JPG, PNG, GIF, PDF (Max: 10 Mo)
                                                </small>
                                            </div>

                                            <div class="qr-preview" id="qrcodePreview">
                                                <?php if (!empty($alipay_qr_code)): ?>
                                                    <img src="uploads/alipay/<?php echo htmlspecialchars($alipay_qr_code); ?>" alt="QR Code Alipay">
                                                <?php else: ?>
                                                    <div class="text-center text-muted">
                                                        <i class="fas fa-qrcode" style="font-size: 3rem; opacity: 0.3;"></i>
                                                        <p class="mt-3">Aucun QR code téléchargé</p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <button type="submit" class="btn btn-primary w-100 mt-4">
                                                <i class="fas fa-save me-2"></i>Enregistrer le QR Code
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Alipay Number Section -->
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm border-0 rounded-lg">
                                    <div class="card-header bg-light border-bottom">
                                        <h5 class="mb-0 fw-bold">
                                            <i class="fas fa-hashtag me-2"></i>Numéro Alipay du Fournisseur
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_alipay">
                                            
                                            <div class="mb-4">
                                                <label for="alipayNumber" class="form-label fw-bold">Numéro Alipay Chinois</label>
                                                <input type="text" class="form-control form-control-lg" id="alipayNumber" name="alipay_number" placeholder="Ex: 1234567890123456" value="<?php echo htmlspecialchars($alipay_number ?? ''); ?>">
                                                <small class="text-muted d-block mt-2">
                                                    Le numéro Alipay unique du fournisseur
                                                </small>
                                            </div>

                                            <div class="stat-card bg-light mb-4">
                                                <div class="text-center">
                                                    <small class="text-muted d-block mb-2">NUMÉRO ACTUELLEMENT ENREGISTRÉ</small>
                                                    <h5 class="fw-bold text-primary" id="currentAlipayNumber">
                                                        <?php echo !empty($alipay_number) ? htmlspecialchars($alipay_number) : 'Aucun numéro enregistré'; ?>
                                                    </h5>
                                                </div>
                                            </div>

                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-save me-2"></i>Enregistrer le Numéro
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Exchange Rate Tab -->
                    <div id="exchange-content" class="tab-content" style="display: none;">
                        <h2 class="mb-4 fw-bold">Gestion du Taux de Change</h2>

                        <div class="alert alert-warning alert-dismissible fade show rounded-lg" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Attention:</strong> Seul l'administrateur peut modifier le taux de change. Cette modification affectera tous les clients en temps réel.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>

                        <div class="row">
                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm border-0 rounded-lg">
                                    <div class="card-header bg-light border-bottom">
                                        <h5 class="mb-0 fw-bold">
                                            <i class="fas fa-exchange-alt me-2"></i>Taux Actuel
                                        </h5>
                                    </div>
                                    <div class="card-body p-4 text-center">
                                        <?php if ($current_exchange_rate !== null): ?>
                                        <small class="text-muted d-block mb-3">CONVERSION</small>
                                        <div class="exchange-rate-display mb-3">
                                            <span id="displayExchangeRate"><?php echo $current_exchange_rate; ?></span> <small class="text-muted" style="font-size: 1.2rem;">XAF/RMB</small>
                                        </div>
                                        <p class="text-muted mb-0">1 RMB = <strong id="rmbToXaf"><?php echo number_format($current_exchange_rate, 2, ',', ','); ?></strong> FCFA</p>
                                        <?php if ($exchange_rate_updated_at): ?>
                                            <hr>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-clock me-1"></i>Mis à jour le <?php echo date('d/m/Y à H:i', strtotime($exchange_rate_updated_at)); ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <div class="alert alert-danger mb-0">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            <strong>Aucun taux défini</strong>
                                            <p class="mb-0 mt-2 small">Le taux de change n'a pas été configuré. Veuillez le définir ci-dessous.</p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6 mb-4">
                                <div class="card shadow-sm border-0 rounded-lg">
                                    <div class="card-header bg-light border-bottom">
                                        <h5 class="mb-0 fw-bold">
                                            <i class="fas fa-edit me-2"></i>Modifier le Taux
                                        </h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_exchange_rate">
                                            
                                            <div class="mb-4">
                                                <label for="newExchangeRate" class="form-label fw-bold">Nouveau Taux de Change</label>
                                                <div class="input-group input-group-lg">
                                                    <input type="number" class="form-control" id="newExchangeRate" name="new_rate" placeholder="Ex: 6" min="0.01" step="0.01" required>
                                                    <span class="input-group-text">XAF/RMB</span>
                                                </div>
                                                <small class="text-muted d-block mt-2">
                                                    Entrez le nouveau taux de change (1 RMB = X FCFA)
                                                </small>
                                            </div>

                                            <?php if ($current_exchange_rate !== null): ?>
                                            <div class="alert alert-info alert-sm" role="alert">
                                                <i class="fas fa-calculator me-2"></i>
                                                <strong>Aperçu:</strong> 100 RMB = <span id="previewXaf">-</span> FCFA
                                            </div>
                                            <?php else: ?>
                                            <div class="alert alert-warning alert-sm" role="alert">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <strong>Premier taux:</strong> Ce sera le premier taux de change défini
                                            </div>
                                            <?php endif; ?>

                                            <button type="submit" class="btn btn-primary w-100 btn-lg">
                                                <i class="fas fa-save me-2"></i>Mettre à Jour le Taux
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments Tab -->
                    <div id="payments-content" class="tab-content" style="display: none;">
                        <h2 class="mb-4 fw-bold">Paiements en Attente</h2>

                        <div class="card shadow-sm border-0 rounded-lg">
                            <div class="card-header bg-light border-bottom">
                                <h5 class="mb-0 fw-bold">
                                    <i class="fas fa-credit-card me-2"></i>Transactions Pendantes
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Date</th>
                                                <th>Montant (XAF)</th>
                                                <th>Bénéficiaire</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($pending_payments) > 0): ?>
                                                <?php foreach ($pending_payments as $payment): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                                                    <td><strong><?php echo number_format($payment['amount'], 0, ',', ','); ?> XAF</strong></td>
                                                    <td><?php echo htmlspecialchars($payment['recipient_email'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($payment['description'] ?? '-'); ?></td>
                                                    <td>
                                                        <a href="payment_confirmation.php?transaction_id=<?php echo htmlspecialchars($payment['id']); ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-eye me-1"></i>Voir
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        <i class="fas fa-inbox me-2"></i>Aucun paiement en attente
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function switchTab(tabName, event) {
            event.preventDefault();
            
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remove active class from all links
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Show selected tab
            const tabId = tabName + '-content';
            const tabElement = document.getElementById(tabId);
            if (tabElement) {
                tabElement.style.display = 'block';
            }
            
            // Add active class to clicked link
            event.target.closest('.nav-link').classList.add('active');
        }

        // QR Code Preview
        const qrcodeFile = document.getElementById('qrcodeUpload');
        const qrcodePreview = document.getElementById('qrcodePreview');

        if (qrcodeFile) {
            qrcodeFile.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let previewHTML = '<div class="text-center">';
                        if (file.type.startsWith('image/')) {
                            previewHTML += '<img src="' + e.target.result + '" style="max-width: 100%; max-height: 200px;" class="rounded">';
                        } else {
                            previewHTML += '<p class="text-success"><i class="fas fa-file-pdf me-2" style="font-size: 3rem;"></i></p>';
                        }
                        previewHTML += '</div>';
                        qrcodePreview.innerHTML = previewHTML;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Exchange Rate Preview
        const newExchangeRateInput = document.getElementById('newExchangeRate');
        const previewXaf = document.getElementById('previewXaf');

        if (newExchangeRateInput) {
            newExchangeRateInput.addEventListener('input', function() {
                const rate = parseFloat(this.value) || 0;
                previewXaf.textContent = Math.floor(100 * rate).toLocaleString('fr-FR');
            });
        }
    </script>
</body>
</html>
