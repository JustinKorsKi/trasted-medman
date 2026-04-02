<?php
require_once 'includes/config.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get transaction details
$query = "SELECT t.*, p.title as product_title, p.description,
          b.username as buyer_name, b.email as buyer_email, b.full_name as buyer_full,
          s.username as seller_name, s.email as seller_email, s.full_name as seller_full,
          m.username as midman_name, m.email as midman_email
          FROM transactions t
          JOIN products p ON t.product_id = p.id
          JOIN users b ON t.buyer_id = b.id
          JOIN users s ON t.seller_id = s.id
          LEFT JOIN users m ON t.midman_id = m.id
          WHERE t.id = $transaction_id";

$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) == 0) {
    header('Location: my-transactions.php');
    exit();
}

$trans = mysqli_fetch_assoc($result);

// Check if user is authorized
if($trans['buyer_id'] != $_SESSION['user_id'] && 
   $trans['seller_id'] != $_SESSION['user_id'] && 
   $trans['midman_id'] != $_SESSION['user_id'] &&
   $_SESSION['role'] != 'admin') {
    header('Location: my-transactions.php');
    exit();
}

// Generate invoice HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $trans['id']; ?> - Trusted Midman</title>
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: #333;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #3498db;
            margin: 0;
        }
        .info-row {
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        table th {
            background-color: #f8f9fa;
        }
        .total {
            font-size: 1.2em;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #3498db;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.9em;
            color: #777;
        }
        .badge {
            background-color: #27ae60;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            display: inline-block;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
        .no-print {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Print Invoice</button>
        <button onclick="window.location.href='my-transactions.php'" class="btn">← Back</button>
    </div>
    
    <div class="invoice-box">
        <div class="header">
            <h1>TRUSTED MIDMAN</h1>
            <p>Secure Transaction Invoice</p>
        </div>
        
        <div class="info-row">
            <div>
                <strong>Invoice #:</strong> <?php echo str_pad($trans['id'], 6, '0', STR_PAD_LEFT); ?><br>
                <strong>Date:</strong> <?php echo date('F j, Y', strtotime($trans['created_at'])); ?><br>
                <strong>Status:</strong> <span class="badge"><?php echo ucfirst($trans['status']); ?></span>
            </div>
            <div>
                <strong>Transaction ID:</strong> #<?php echo $trans['id']; ?><br>
                <strong>Payment Method:</strong> Midman Escrow<br>
                <strong>Service Fee:</strong> <?php echo ($trans['service_fee'] / $trans['amount'] * 100); ?>%
            </div>
        </div>
        
        <div class="info-row">
            <div>
                <h3>Buyer Information</h3>
                <p>
                    <strong><?php echo $trans['buyer_full'] ?: $trans['buyer_name']; ?></strong><br>
                    Username: <?php echo $trans['buyer_name']; ?><br>
                    Email: <?php echo $trans['buyer_email']; ?>
                </p>
            </div>
            <div>
                <h3>Seller Information</h3>
                <p>
                    <strong><?php echo $trans['seller_full'] ?: $trans['seller_name']; ?></strong><br>
                    Username: <?php echo $trans['seller_name']; ?><br>
                    Email: <?php echo $trans['seller_email']; ?>
                </p>
            </div>
        </div>
        
        <?php if($trans['midman_name']): ?>
        <div class="info-row">
            <div>
                <h3>Midman Information</h3>
                <p>
                    <strong><?php echo $trans['midman_name']; ?></strong><br>
                    Email: <?php echo $trans['midman_email']; ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <h3>Product Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($trans['product_title']); ?></strong><br>
                        <?php echo htmlspecialchars(substr($trans['description'], 0, 100)); ?>...
                    </td>
                    <td>1</td>
                    <td>$<?php echo number_format($trans['amount'], 2); ?></td>
                    <td>$<?php echo number_format($trans['amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        
        <div class="total">
            <table style="width: 50%; float: right;">
                <tr>
                    <td>Subtotal:</td>
                    <td>$<?php echo number_format($trans['amount'], 2); ?></td>
                </tr>
                <tr>
                    <td>Service Fee (<?php echo ($trans['service_fee'] / $trans['amount'] * 100); ?>%):</td>
                    <td>$<?php echo number_format($trans['service_fee'], 2); ?></td>
                </tr>
                <tr>
                    <td><strong>Total Amount:</strong></td>
                    <td><strong>$<?php echo number_format($trans['amount'] + $trans['service_fee'], 2); ?></strong></td>
                </tr>
            </table>
            <div style="clear: both;"></div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated invoice. No signature is required.</p>
            <p>For any disputes, please contact support within 14 days.</p>
            <p>Thank you for using Trusted Midman!</p>
        </div>
    </div>
</body>
</html>