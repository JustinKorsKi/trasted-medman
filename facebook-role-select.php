<?php
require_once 'includes/config.php';

// Check if we have Facebook data in session
if(!isset($_SESSION['facebook_data'])) {
    header('Location: login.php');
    exit();
}

$facebook_data = $_SESSION['facebook_data'];
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    if($role != 'buyer' && $role != 'seller') {
        $error = 'Please select a valid role.';
    } else {
        // Generate username from name
        $name = $facebook_data['name'];
        $username = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($name));
        $base_username = $username;
        $counter = 1;

        // Make sure username is unique
        while(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'"))) {
            $username = $base_username . $counter;
            $counter++;
        }

        // Create email from Facebook ID
        $email = $facebook_data['id'] . '@facebook.local';
        
        // Generate random password
        $random_password = bin2hex(random_bytes(16));
        $hashed_password = password_hash($random_password, PASSWORD_BCRYPT);

        // Insert new user with selected role
        $query = "INSERT INTO users (
            username, email, password, full_name, role, 
            facebook_id, avatar, email_verified, is_active, created_at
        ) VALUES (
            '$username', '$email', '$hashed_password', '$name', '$role',
            '{$facebook_data['id']}', '{$facebook_data['picture']}', 1, 1, NOW()
        )";

        if(mysqli_query($conn, $query)) {
            $user_id = mysqli_insert_id($conn);
            
            // Set session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['full_name'] = $name;
            
            // Clear Facebook data from session
            unset($_SESSION['facebook_data']);
            
            $_SESSION['flash'] = ['message' => 'Welcome! Your account has been created via Facebook.', 'type' => 'success'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Failed to create account. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Role — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0d0f14;
            --surface:   #13161e;
            --surface2:  #1a1e28;
            --border:    rgba(255,255,255,0.07);
            --border2:   rgba(255,255,255,0.13);
            --gold:      #f0a500;
            --gold-dim:  rgba(240,165,0,0.14);
            --gold-glow: rgba(240,165,0,0.32);
            --teal:      #00d4aa;
            --teal-dim:  rgba(0,212,170,0.12);
            --text:      #e8eaf0;
            --text-muted:#7a7f95;
            --text-dim:  #4a4f65;
            --radius-sm: 8px;
            --radius:    14px;
            --font-head: 'Syne', sans-serif;
            --font-body: 'DM Sans', sans-serif;
        }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        .bg-glow1 {
            position: fixed; top: -100px; right: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(240,165,0,0.09) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-glow2 {
            position: fixed; bottom: -100px; left: -80px;
            width: 460px; height: 460px;
            background: radial-gradient(circle, rgba(0,212,170,0.07) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-grid {
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.022) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.022) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 40%, black 10%, transparent 100%);
            pointer-events: none; z-index: 0;
        }

        .card {
            position: relative; z-index: 1;
            width: 100%; max-width: 500px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,0.55);
            animation: fadeUp 0.55s ease forwards;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card-head {
            padding: 32px 36px 26px;
            border-bottom: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        .card-head::before {
            content: '';
            position: absolute; top: -60px; right: -60px;
            width: 260px; height: 260px;
            background: radial-gradient(circle, var(--gold-glow) 0%, transparent 65%);
            pointer-events: none;
        }

        .brand {
            display: flex; align-items: center; gap: 11px;
            text-decoration: none; margin-bottom: 22px;
        }
        .brand-icon {
            width: 34px; height: 34px; background: var(--gold); border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: #0d0f14; font-size: 14px; box-shadow: 0 0 16px var(--gold-glow);
        }
        .brand-name { font-family: var(--font-head); font-weight: 700; font-size: 0.95rem; color: var(--text); }

        .head-title {
            font-family: var(--font-head);
            font-size: 1.55rem; font-weight: 800; color: var(--text);
            line-height: 1.1; margin-bottom: 5px;
            position: relative; z-index: 1;
        }
        .head-title span { color: var(--gold); }
        .head-sub { font-size: 0.85rem; color: var(--text-muted); position: relative; z-index: 1; }

        .card-body { padding: 26px 36px 32px; }

        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            border-radius: var(--radius-sm); padding: 12px 14px;
            font-size: 0.85rem; margin-bottom: 20px; line-height: 1.5;
        }
        .alert i { font-size: 0.9rem; margin-top: 2px; flex-shrink: 0; }
        .alert-error { background: var(--red-dim); border: 1px solid rgba(255,77,109,0.22); color: var(--red); }

        .user-info {
            background: var(--surface2);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--gold-dim);
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text);
        }

        .user-email {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .role-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .role-option {
            position: relative;
        }

        .role-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .role-card {
            background: var(--surface2);
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 25px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .role-option input:checked + .role-card {
            border-color: var(--gold);
            background: var(--gold-dim);
            box-shadow: 0 0 20px var(--gold-glow);
        }

        .role-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.8rem;
        }

        .role-icon.buyer {
            background: var(--blue-dim);
            color: var(--blue);
        }

        .role-icon.seller {
            background: var(--teal-dim);
            color: var(--teal);
        }

        .role-title {
            font-family: var(--font-head);
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .role-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .btn-submit {
            width: 100%; padding: 14px 20px;
            background: var(--gold); color: #0d0f14;
            border: none; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 1rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.02em;
            box-shadow: 0 4px 20px var(--gold-glow);
            transition: all 0.24s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { background: #ffb822; transform: translateY(-2px); }

        @media (max-width: 540px) {
            .card-head, .card-body { padding-left: 22px; padding-right: 22px; }
            .role-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="bg-glow1"></div>
    <div class="bg-glow2"></div>
    <div class="bg-grid"></div>

    <div class="card">
        <div class="card-head">
            <a href="index.php" class="brand">
                <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
                <span class="brand-name">Trusted Midman</span>
            </a>
            <h1 class="head-title">Choose your <span>role</span></h1>
            <p class="head-sub">How would you like to use Trusted Midman?</p>
        </div>

        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="user-info">
                <div class="user-avatar">
                    <img src="<?php echo htmlspecialchars($facebook_data['picture']); ?>" alt="">
                </div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($facebook_data['name']); ?></div>
                    <div class="user-email">Facebook Account</div>
                </div>
            </div>

            <form method="POST" action="">
                <div class="role-grid">
                    <div class="role-option">
                        <input type="radio" name="role" id="role_buyer" value="buyer" required>
                        <label for="role_buyer" class="role-card">
                            <div class="role-icon buyer">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="role-title">Buyer</div>
                            <div class="role-desc">
                                Browse and purchase gaming items securely
                            </div>
                        </label>
                    </div>

                    <div class="role-option">
                        <input type="radio" name="role" id="role_seller" value="seller" required>
                        <label for="role_seller" class="role-card">
                            <div class="role-icon seller">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="role-title">Seller</div>
                            <div class="role-desc">
                                List and sell your gaming items
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-arrow-right"></i>
                    Continue as Selected Role
                </button>
            </form>
        </div>
    </div>
</body>
</html>