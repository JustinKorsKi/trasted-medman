<?php
require_once 'includes/config.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect to role-specific dashboard
$role = $_SESSION['role'];

switch($role) {
    case 'buyer':
        header('Location: buyer-dashboard.php');
        break;
    case 'seller':
        header('Location: seller-dashboard.php');
        break;
    case 'midman':
        header('Location: midman-dashboard.php');
        break;
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    default:
        header('Location: login.php');
        break;
}
exit();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Trusted Midman</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    Trusted Midman
                </a>
            </div>
            <nav class="sidebar-nav">
                <ul class="sidebar-menu">
                    <li>
                        <a href="dashboard.php" class="active">
                            <span class="sidebar-menu-icon"><i class="fas fa-home"></i></span>
                            Dashboard
                        </a>
                    </li>
                    <?php if($role == 'buyer'): ?>
                        <li>
                            <a href="products.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-shopping-cart"></i></span>
                                Browse Products
                            </a>
                        </li>
                        <li>
                            <a href="my-transactions.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-receipt"></i></span>
                                My Transactions
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <span class="sidebar-menu-icon"><i class="fas fa-credit-card"></i></span>
                                Payments
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <span class="sidebar-menu-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                Disputes
                            </a>
                        </li>
                    <?php elseif($role == 'seller'): ?>
                        <li>
                            <a href="products.php?my=1">
                                <span class="sidebar-menu-icon"><i class="fas fa-box"></i></span>
                                My Listings
                            </a>
                        </li>
                        <li>
                            <a href="add-product.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-plus-circle"></i></span>
                                Add Product
                            </a>
                        </li>
                        <li>
                            <a href="my-transactions.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-shopping-bag"></i></span>
                                Orders
                            </a>
                        </li>
                        <li>
                            <a href="earnings.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-dollar-sign"></i></span>
                                Earnings
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <span class="sidebar-menu-icon"><i class="fas fa-chart-line"></i></span>
                                Reports
                            </a>
                        </li>
                    <?php elseif($role == 'midman'): ?>
                        <li>
                            <a href="midman-dashboard.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-handshake"></i></span>
                                Assigned Transactions
                            </a>
                        </li>
                        <li>
                            <a href="earnings.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-wallet"></i></span>
                                Earnings
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <span class="sidebar-menu-icon"><i class="fas fa-gavel"></i></span>
                                Disputes
                            </a>
                        </li>
                        <li>
                            <a href="verify-identity.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-user-check"></i></span>
                                KYC Status
                            </a>
                        </li>
                        <li>
                            <a href="#">
                                <span class="sidebar-menu-icon"><i class="fas fa-shield-alt"></i></span>
                                Security
                            </a>
                        </li>
                    <?php elseif($role == 'admin'): ?>
                        <li>
                            <a href="admin/dashboard.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                                Admin Panel
                            </a>
                        </li>
                        <li>
                            <a href="admin/users.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-users"></i></span>
                                Users
                            </a>
                        </li>
                        <li>
                            <a href="admin/applications.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-user-check"></i></span>
                                Midman Verification
                            </a>
                        </li>
                        <li>
                            <a href="admin/transaction.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-exchange-alt"></i></span>
                                Transactions
                            </a>
                        </li>
                        <li>
                            <a href="admin/dispute.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-balance-scale"></i></span>
                                Disputes
                            </a>
                        </li>
                        <li>
                            <a href="admin/settings.php">
                                <span class="sidebar-menu-icon"><i class="fas fa-cog"></i></span>
                                Settings
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a href="profile.php">
                            <span class="sidebar-menu-icon"><i class="fas fa-user"></i></span>
                            Profile
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <span class="sidebar-menu-icon"><i class="fas fa-sign-out-alt"></i></span>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navbar -->
            <header class="top-navbar">
                <div class="navbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Dashboard</h1>
                </div>
                <div class="navbar-right">
                    <button class="notification-btn">
                        <i class="fas fa-bell notification-icon"></i>
                        <span class="notification-badge"></span>
                    </button>
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name"><?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?></div>
                            <div class="user-role"><?php echo ucfirst($role); ?></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Area -->
            <div class="content-area">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <?php if($role == 'seller'): ?>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo $stats['products']; ?></div>
                            <div class="stat-card-label">Products Listed</div>
                            <div class="stat-card-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Active listings
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo $stats['sales']['count'] ?? 0; ?></div>
                            <div class="stat-card-label">Total Sales</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                            <div class="stat-card-value">$<?php echo number_format($stats['sales']['total'] ?? 0, 2); ?></div>
                            <div class="stat-card-label">Revenue</div>
                            <div class="stat-card-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Lifetime earnings
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo $stats['pending']; ?></div>
                            <div class="stat-card-label">Pending Orders</div>
                            <div class="stat-card-change">
                                <i class="fas fa-clock"></i>
                                Awaiting action
                            </div>
                        </div>
                        
                    <?php elseif($role == 'buyer'): ?>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo $stats['purchases']['count'] ?? 0; ?></div>
                            <div class="stat-card-label">Active Transactions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo $stats['pending']; ?></div>
                            <div class="stat-card-label">Pending Actions</div>
                            <div class="stat-card-change">
                                <i class="fas fa-exclamation-triangle"></i>
                                Need attention
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo $stats['purchases']['count'] ?? 0; ?></div>
                            <div class="stat-card-label">Completed Purchases</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-wallet"></i>
                                </div>
                            </div>
                            <div class="stat-card-value">$<?php echo number_format($stats['purchases']['total'] ?? 0, 2); ?></div>
                            <div class="stat-card-label">Total Spent</div>
                            <div class="stat-card-change positive">
                                <i class="fas fa-arrow-up"></i>
                                All purchases
                            </div>
                        </div>
                        
                    <?php elseif($role == 'midman'): ?>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-handshake"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo $stats['assigned']; ?></div>
                            <div class="stat-card-label">Assigned Transactions</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                            </div>
                            <div class="stat-card-value"><?php echo $stats['pending']; ?></div>
                            <div class="stat-card-label">Pending Approvals</div>
                            <div class="stat-card-change">
                                <i class="fas fa-exclamation-triangle"></i>
                                Need review
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-check-double"></i>
                                </div>
                            </div>
                            <div class="stat-card-value">
                                <?php
                                $completed_today = mysqli_query($conn, "SELECT COUNT(*) as count FROM transactions WHERE midman_id = $user_id AND status = 'completed' AND DATE(created_at) = CURDATE()");
                                echo mysqli_fetch_assoc($completed_today)['count'];
                                ?>
                            </div>
                            <div class="stat-card-label">Completed Today</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-card-header">
                                <div class="stat-card-icon">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                            <div class="stat-card-value">$<?php 
                                $monthly_earnings = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM earnings WHERE midman_id = $user_id AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                                echo number_format(mysqli_fetch_assoc($monthly_earnings)['total'], 2); 
                            ?></div>
                            <div class="stat-card-label">Earnings This Month</div>
                            <div class="stat-card-change positive">
                                <i class="fas fa-arrow-up"></i>
                                Monthly income
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Quick Actions</h2>
                    </div>
                    <div class="quick-actions">
                        <?php if($role == 'seller'): ?>
                            <a href="add-product.php" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <div class="quick-action-title">Create New Listing</div>
                                <div class="quick-action-desc">Add a new product to sell</div>
                            </a>
                            <a href="products.php?my=1" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-list"></i>
                                </div>
                                <div class="quick-action-title">View My Products</div>
                                <div class="quick-action-desc">Manage your listings</div>
                            </a>
                            <a href="my-transactions.php" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="quick-action-title">View Sales</div>
                                <div class="quick-action-desc">Track your earnings</div>
                            </a>
                        <?php elseif($role == 'buyer'): ?>
                            <a href="products.php" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="quick-action-title">Browse Products</div>
                                <div class="quick-action-desc">Find items to buy</div>
                            </a>
                            <a href="my-transactions.php" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-receipt"></i>
                                </div>
                                <div class="quick-action-title">View Transactions</div>
                                <div class="quick-action-desc">Track your purchases</div>
                            </a>
                            <a href="raise-dispute.php" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-gavel"></i>
                                </div>
                                <div class="quick-action-title">Raise Dispute</div>
                                <div class="quick-action-desc">Report an issue</div>
                            </a>
                        <?php elseif($role == 'midman'): ?>
                            <a href="midman-dashboard.php" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div class="quick-action-title">Assigned Transactions</div>
                                <div class="quick-action-desc">View your cases</div>
                            </a>
                            <a href="earnings.php" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="quick-action-title">My Earnings</div>
                                <div class="quick-action-desc">Check your income</div>
                            </a>
                            <a href="apply-midman.php" class="quick-action-card">
                                <div class="quick-action-icon">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <div class="quick-action-title">KYC Status</div>
                                <div class="quick-action-desc">Verify identity</div>
                            </a>
                        <?php endif; ?>
                        <a href="profile.php" class="quick-action-card">
                            <div class="quick-action-icon">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <div class="quick-action-title">Edit Profile</div>
                            <div class="quick-action-desc">Update your information</div>
                        </a>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Transactions</h2>
                        <div class="card-actions">
                            <a href="my-transactions.php" class="btn btn-secondary btn-sm">View All</a>
                        </div>
                    </div>
                    <div class="table-container">
                        <?php if(mysqli_num_rows($recent) > 0): ?>
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1;
                                    while($trans = mysqli_fetch_assoc($recent)): 
                                        $txn_id = 'TXN-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
                                    ?>
                                        <tr>
                                            <td><strong><?php echo $txn_id; ?></strong></td>
                                            <td><?php echo htmlspecialchars($trans['product_title']); ?></td>
                                            <td><strong>$<?php echo number_format($trans['amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="badge badge-<?php echo $trans['status']; ?>">
                                                    <?php 
                                                    $status_text = ucfirst($trans['status']);
                                                    if($trans['status'] == 'in_progress') $status_text = 'In Progress';
                                                    echo $status_text; 
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($trans['created_at'])); ?></td>
                                            <td>
                                                <a href="transaction-detail.php?id=<?php echo $trans['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i>
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php 
                                    $counter++;
                                    endwhile; 
                                    ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center p-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No transactions yet.</p>
                                <?php if($role == 'buyer'): ?>
                                    <a href="products.php" class="btn btn-primary mt-2">Start Shopping</a>
                                <?php elseif($role == 'seller'): ?>
                                    <a href="add-product.php" class="btn btn-primary mt-2">Add Your First Product</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebar = document.getElementById('sidebar');
        
        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 1024) {
                if (!sidebar.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    </script>
</body>
</html>