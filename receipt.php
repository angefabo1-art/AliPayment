<?php
session_start();

// Example dynamic data
$transaction_id = "TXN" . rand(100000, 999999);
$date = date("F d, Y");
$payment_method = "Mobile Money";
$customer = $_SESSION['user_name'] ?? 'Utilisateur';
$amount = "20.49";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alipayement Receipt</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>

<body>

<div class="receipt-card">
    
    <div class="status-icon">✓</div>

    <div class="header-text">
        <h2>Payment Received</h2>

        <p>
            Transaction ID:
            #<?php echo $transaction_id; ?>
        </p>
    </div>

    <div class="details-section">

        <div class="row">
            <span class="label">Date</span>

            <span class="value">
                <?php echo $date; ?>
            </span>
        </div>

        <div class="row">
            <span class="label">Payment Method</span>

            <span class="value">
                <?php echo $payment_method; ?>
            </span>
        </div>

        <div class="row">
            <span class="label">Customer</span>

            <span class="value">
                <?php echo $customer; ?>
            </span>
        </div>

        <div style="margin: 20px 0; border-bottom: 1px solid #f8f8f8;"></div>

        <div class="row">
            <span class="label">Payment</span>

            <span class="value">
                <?php echo $amount; ?> XAF
            </span>
        </div>

        <div class="total-row">
            <span class="total-label">Total Amount</span>

            <span class="total-amount">
                <?php echo $amount; ?> XAF
            </span>
        </div>

    </div>

    <div class="actions">
        <button class="btn btn-primary" onclick="window.print()">
            Download as PDF
        </button>

        <button class="btn btn-secondary"
            onclick="window.location.href='dashboard.php'">
            Back to Dashboard
        </button>
    </div>

</div>

</body>
</html>