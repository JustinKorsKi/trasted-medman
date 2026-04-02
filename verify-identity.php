<?php
require_once 'includes/config.php';
require_once 'includes/verification-functions.php';

$pending_tx_count = 0;
if($_SESSION['role'] === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($_SESSION['role'] === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}


if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id  = $_SESSION['user_id'];
$role     = $_SESSION['role'] ?? 'buyer';
$error    = '';
$success  = '';

require_once 'includes/2fa-functions.php'; // at the top

$two_factor_enabled = false;
if($role == 'midman') {
    $two_factor_enabled = is2FAEnabled($user_id);
}

$verification = getUserVerificationStatus($user_id);
$has_pending  = hasPendingVerification($user_id);

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_verification'])) {
    if($has_pending) {
        $error = 'You already have a pending verification request. Please wait for admin review.';
    } else {
        $document_type   = mysqli_real_escape_string($conn, $_POST['document_type']);
        $document_number = mysqli_real_escape_string($conn, $_POST['document_number']);
        $upload_dir      = 'uploads/verification/';
        if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

        $document_file = '';
        if(isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
            if(in_array($ext, ['jpg','jpeg','png','pdf'])) {
                $fn = 'doc_'.$user_id.'_'.time().'.'.$ext;
                if(move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_dir.$fn)) $document_file = $upload_dir.$fn;
                else $error = 'Failed to upload document.';
            } else $error = 'Only JPG, PNG, and PDF files are allowed.';
        } else $error = 'Please upload a document.';

        $selfie_file = '';
        if(isset($_FILES['selfie_file']) && $_FILES['selfie_file']['error'] == 0) {
            $ext = strtolower(pathinfo($_FILES['selfie_file']['name'], PATHINFO_EXTENSION));
            if(in_array($ext, ['jpg','jpeg','png'])) {
                $fn = 'selfie_'.$user_id.'_'.time().'.'.$ext;
                if(move_uploaded_file($_FILES['selfie_file']['tmp_name'], $upload_dir.$fn)) $selfie_file = $upload_dir.$fn;
            }
        }

        if(empty($error) && $document_file) {
            $q = "INSERT INTO verification_requests (user_id, document_type, document_number, document_file, selfie_file, status)
                  VALUES ($user_id, '$document_type', '$document_number', '$document_file', '$selfie_file', 'pending')";
            if(mysqli_query($conn, $q)) {
                mysqli_query($conn, "UPDATE users SET verification_level='pending' WHERE id=$user_id");
                $success     = 'Documents submitted successfully! Your request is pending admin review.';
                $has_pending = true;
                $verification['verification_level'] = 'pending';
                $_POST = [];
            } else $error = 'Failed to submit. Please try again.';
        }
    }
}

$vl     = $verification['verification_level'] ?? 'unverified';
$vl_cfg = [
    'verified'   => ['icon'=>'fa-circle-check', 'color'=>'var(--teal)',       'bg'=>'var(--teal-dim)',   'border'=>'rgba(0,212,170,0.2)',  'label'=>'Verified',             'msg'=>'Your identity has been verified. You can now apply to become a midman.'],
    'pending'    => ['icon'=>'fa-clock',         'color'=>'var(--gold)',       'bg'=>'var(--gold-dim)',   'border'=>'rgba(240,165,0,0.2)',  'label'=>'Pending Review',       'msg'=>'Your documents are being reviewed. This usually takes 24–48 hours.'],
    'rejected'   => ['icon'=>'fa-circle-xmark',  'color'=>'var(--red)',        'bg'=>'var(--red-dim)',    'border'=>'rgba(255,77,109,0.2)', 'label'=>'Verification Rejected','msg'=>$verification['verification_notes'] ?? 'Your documents were rejected. Please resubmit with valid documents.'],
    'unverified' => ['icon'=>'fa-id-card',        'color'=>'var(--text-muted)','bg'=>'var(--surface2)',   'border'=>'var(--border2)',       'label'=>'Not Verified',         'msg'=>'Verify your identity to unlock midman privileges and build trust on the platform.'],
];
$vc = $vl_cfg[$vl] ?? $vl_cfg['unverified'];

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
$back_link    = match($role) { 'midman'=>'midman-dashboard.php', 'seller'=>'seller-dashboard.php', default=>'buyer-dashboard.php' };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Verification — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        :root {
            --bg:         #0f0c08;
            --bg2:        #130f0a;
            --surface:    #0f0b07;
            --surface2:   #201a13;
            --surface3:   #271f16;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.24);
            
            /* Accent colors – default gold for buyers/sellers */
            --accent:       #f0a500;
            --accent-lt:    #ffbe3a;
            --accent-dim:   rgba(240,165,0,0.13);
            --accent-glow:  rgba(240,165,0,0.28);
            --gradient-start: #f0a500;
            --gradient-end:   #d4920a;
            
            /* Semantic colors */
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.11);
            --red:        #ff4d6d;
            --red-dim:    rgba(255,77,109,0.12);
            --orange:     #ff9632;
            --orange-dim: rgba(255,150,50,0.12);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.12);
            --purple:     #a064ff;
            --purple-dim: rgba(160,100,255,0.12);
            --purple-glow:rgba(160,100,255,0.25);
            
            --text:       #ffffff;
            --text-warm:  #f0e8da;
            --text-muted: #a89880;
            --text-dim:   #5a4e3a;
            --radius-sm:  10px;
            --radius:     14px;
            --radius-lg:  20px;
            --sidebar-w:  260px;
            --font-head:  'Barlow Condensed', sans-serif;
            --font-body:  'DM Sans', sans-serif;
        }

        /* Midman override – switch gold to purple */
        body.role-midman {
            --accent:       var(--purple);
            --accent-lt:    #be8fff;
            --accent-dim:   rgba(160,100,255,0.12);
            --accent-glow:  rgba(160,100,255,0.28);
            --gradient-start: #a064ff;
            --gradient-end:   #7040cc;
        }

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(200,100,0,0.08) 0%,transparent 65%); pointer-events:none; }
        .sidebar-logo { display:flex; align-items:center; gap:12px; padding:26px 22px; text-decoration:none; border-bottom:1px solid var(--border); position:relative; z-index:1; }
        
        .logo-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.logo-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.footer-brand-icon {
    width: 34px;
    height: 34px;
    background: linear-gradient(135deg, var(--gold), #e09000);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 14px var(--gold-glow2);
}
.footer-brand-icon img {
    width: 80%;
    height: 80%;
    object-fit: contain;
}

        .logo-text { font-family:var(--font-head); font-weight:700; font-size:1.1rem; color:var(--text); line-height:1.2; letter-spacing:-0.01em; }
        .logo-sub  { font-size:0.65rem; color:var(--accent); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--accent); background:var(--accent-dim); border:1px solid rgba(240,165,0,0.12); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--accent); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px var(--accent-glow); }
        .pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .pill-role { font-size:0.68rem; color:var(--accent); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .back-link { font-size:0.82rem; color:var(--text-muted); text-decoration:none; display:flex; align-items:center; gap:6px; transition:color 0.2s; }
        .back-link:hover { color:var(--accent); }
        .content { padding:28px 32px; flex:1; max-width:860px; }
         .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }


        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim);  color:var(--teal);  border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);   color:#ff7090;      border:1px solid rgba(255,77,109,0.22); }
        .alert-info    { background:var(--blue-dim);  color:var(--blue);  border:1px solid rgba(78,159,255,0.22); }

        /* ── STATUS HERO ── */
        .status-hero {
            background:var(--surface); border:1px solid <?php echo $vc['border']; ?>;
            border-radius:var(--radius-lg); padding:28px 32px; margin-bottom:24px;
            position:relative; overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
        }
        .status-hero::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent-glow),transparent); }
        .status-hero::after  { content:''; position:absolute; top:-50px; right:-50px; width:180px; height:180px; background:radial-gradient(circle,<?php echo $vc['bg']; ?> 0%,transparent 65%); pointer-events:none; opacity:0.8; }

        .sh-inner { display:flex; align-items:center; gap:20px; flex-wrap:wrap; position:relative; z-index:1; }
        .sh-icon  { width:60px; height:60px; border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0; background:<?php echo $vc['bg']; ?>; color:<?php echo $vc['color']; ?>; border:1px solid <?php echo $vc['border']; ?>; }
        .sh-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.12em; color:<?php echo $vc['color']; ?>; margin-bottom:4px; }
        .sh-title { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--text); line-height:1.1; margin-bottom:5px; letter-spacing:-0.01em; }
        .sh-msg   { font-size:0.875rem; color:var(--text-muted); max-width:480px; line-height:1.6; }
        .sh-cta   { margin-left:auto; flex-shrink:0; }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; letter-spacing:0.01em; }
        .btn-accent { background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); color:#0f0c08; font-weight:700; box-shadow:0 3px 14px var(--accent-glow); }
        .btn-accent:hover { background:linear-gradient(135deg,var(--accent-lt),var(--accent)); transform:translateY(-2px); }
        .btn-ghost  { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); }
        .btn-block  { width:100%; justify-content:center; }

        /* ── REQUIREMENTS ── */
        .req-panel { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:16px 20px; margin-bottom:22px; }
        .req-title { font-family:var(--font-head); font-size:0.88rem; font-weight:700; color:var(--text); margin-bottom:12px; display:flex; align-items:center; gap:7px; letter-spacing:-0.01em; }
        .req-title i { color:var(--teal); }
        .req-list { list-style:none; display:flex; flex-direction:column; gap:8px; }
        .req-list li { display:flex; align-items:center; gap:9px; font-size:0.82rem; color:var(--text-muted); }
        .req-list li i { color:var(--teal); font-size:0.7rem; width:14px; text-align:center; flex-shrink:0; }

        /* ── FORM PANEL ── */
        .form-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease 0.15s forwards;
            position:relative;
        }
        .form-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent-dim),transparent); z-index:1; }
        .form-panel-head { display:flex; align-items:center; gap:10px; padding:16px 22px; border-bottom:1px solid var(--border); }
        .fph-icon  { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.78rem; background:var(--accent-dim); color:var(--accent); border:1px solid rgba(240,165,0,0.14); }
        .fph-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .form-body { padding:24px 22px; }

        /* ── FORM ELEMENTS ── */
        .form-row   { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:18px; }
        .form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:18px; }
        .form-group:last-child { margin-bottom:0; }

        label { font-size:0.75rem; font-weight:700; color:var(--text-muted); letter-spacing:0.08em; text-transform:uppercase; }
        .optional { font-size:0.68rem; color:var(--text-dim); margin-left:4px; font-weight:400; text-transform:none; letter-spacing:0; }

        input[type="text"],
        input[type="email"],
        select {
            width:100%; padding:11px 14px; background:var(--surface2);
            border:1px solid var(--border); border-radius:var(--radius-sm);
            color:var(--text-warm); font-family:var(--font-body); font-size:0.9rem;
            transition:all 0.22s; outline:none;
        }
        input:focus, select:focus {
            border-color:var(--accent);
            box-shadow:0 0 0 3px var(--accent-dim);
            background:var(--surface3);
        }
        select option { background:#201a13; }

        /* ── FILE UPLOAD ── */
        .file-zone {
            border:2px dashed var(--border2); border-radius:var(--radius);
            padding:28px 20px; text-align:center; cursor:pointer;
            transition:all 0.25s; position:relative;
        }
        .file-zone:hover, .file-zone.dragover { border-color:var(--accent); background:var(--accent-dim); }
        .fz-icon  { font-size:2rem; color:var(--text-dim); margin-bottom:8px; transition:color 0.2s; }
        .file-zone:hover .fz-icon { color:var(--accent); }
        .fz-title { font-size:0.875rem; font-weight:600; color:var(--text-muted); margin-bottom:3px; }
        .fz-sub   { font-size:0.72rem; color:var(--text-dim); }
        .fz-name  { margin-top:10px; font-size:0.8rem; font-weight:600; color:var(--teal); display:none; }
        .fz-name.visible { display:block; }
        input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }

        /* ── CHECKBOX ── */
        .check-label { display:flex; align-items:flex-start; gap:10px; cursor:pointer; font-size:0.84rem; color:var(--text-muted); line-height:1.6; }
        input[type="checkbox"] { width:16px; height:16px; accent-color:var(--accent); flex-shrink:0; margin-top:3px; cursor:pointer; }

        /* ── PENDING NOTICE ── */
        .pending-notice {
            background:var(--surface); border:1px solid var(--accent-dim);
            border-radius:var(--radius); padding:32px 24px; text-align:center;
            opacity:0; animation:fadeUp 0.45s ease 0.15s forwards;
            position:relative;
        }
        .pending-notice::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent-glow),transparent); }
        .pn-icon  { width:60px; height:60px; border-radius:50%; background:var(--accent-dim); border:1px solid rgba(240,165,0,0.2); color:var(--accent); display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin:0 auto 16px; }
        .pn-title { font-family:var(--font-head); font-size:1.2rem; font-weight:800; color:var(--text); margin-bottom:8px; letter-spacing:-0.01em; }
        .pn-sub   { font-size:0.875rem; color:var(--text-muted); line-height:1.7; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:820px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:600px) { .form-row{grid-template-columns:1fr;} .sh-cta{margin-left:0;width:100%;} }
    </style>
</head>
<body class="role-<?php echo $role; ?>">
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — dynamic per role -->
    <aside class="sidebar" id="sidebar">
       
<a href="index.php" class="sidebar-logo">
    <div class="logo-icon">
        <img src="images/logowhite.png" alt="Trusted Midman" style="width:100%; height:100%; object-fit:cover;">
    </div>
    <div class="logo-text">
        Trusted Midman
        <span class="logo-sub">Marketplace</span>
    </div>
</a>

         <nav class="sidebar-nav">
            <?php if($role === 'midman'): ?>
                <div class="nav-label">Midman</div>
            <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span>Dashboard</a>
<a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
<a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span>Earnings</a>
<a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>
<a href="verify-identity.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-user-check"></i></span>KYC Status</a>


<!-- Security section for midman -->
<div class="nav-label" style="margin-top:10px;">Security</div>
<a href="setup-2fa.php" class="nav-link">
    <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
    <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
    <?php if($two_factor_enabled): ?><span class="security-badge">Active</span><?php endif; ?>
</a>

<div class="nav-label" style="margin-top:10px;">Account</div>
<a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span>Profile</a>

            <?php elseif($role === 'seller'): ?>
            <div class="nav-label">Seller</div>
            <a href="seller-dashboard.php"   class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span>Dashboard</a>
            <a href="my-products.php"        class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span>My Products</a>
            <a href="add-gaming-product.php" class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span>Add Product</a>
            <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
            <a href="seller-earnings.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span>Earnings</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <div class="nav-label" style="margin-top:12px;">Account</div>
            <a href="apply-midman.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
            <a href="profile.php"            class="nav-link active"><span class="nav-icon"><i class="fas fa-user-circle"></i></span>Profile</a>
            <?php else: ?>
            <div class="nav-label">Buyer</div>
            <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span>Dashboard</a>
            <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span>Browse Products</a>
            <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>My Purchases</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>
            <div class="nav-label" style="margin-top:12px;">Account</div>
            <a href="apply-midman.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
            <a href="profile.php"          class="nav-link active"><span class="nav-icon"><i class="fas fa-user-circle"></i></span>Profile</a>
            <?php endif; ?>
            <a href="logout.php" class="nav-link" style="color:var(--text-dim);margin-top:8px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span>Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username']??'GU',0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role"><?php echo ucfirst($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Identity Verification</span>
            </div>
            <a href="<?php echo $back_link; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </header>

        <div class="content">

            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <!-- STATUS HERO -->
            <div class="status-hero">
                <div class="sh-inner">
                    <div class="sh-icon"><i class="fas <?php echo $vc['icon']; ?>"></i></div>
                    <div>
                        <div class="sh-label"><?php echo $vc['label']; ?></div>
                        <div class="sh-title">KYC / Identity Verification</div>
                        <div class="sh-msg"><?php echo htmlspecialchars($vc['msg']); ?></div>
                    </div>
                    <?php if($vl === 'verified' && $role !== 'midman'): ?>
<div class="sh-cta">
    <a href="apply-midman.php" class="btn btn-accent"><i class="fas fa-handshake"></i> Apply as Midman</a>
</div>
<?php elseif($vl === 'verified' && $role === 'midman'): ?>
<div class="sh-cta">
    <a href="midman-dashboard.php" class="btn btn-accent"><i class="fas fa-gauge-high"></i> Midman Dashboard</a>
</div>
<?php endif; ?>
                </div>
            </div>

            <!-- PENDING STATE -->
            <?php if($has_pending && $vl !== 'verified'): ?>
            <div class="pending-notice">
                <div class="pn-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="pn-title">Awaiting Admin Review</div>
                <div class="pn-sub">Your documents have been submitted and are currently being reviewed.<br>This process typically takes 24–48 hours. We'll notify you once it's done.</div>
            </div>

            <!-- FORM STATE -->
            <?php elseif($vl !== 'verified'): ?>
            <div class="form-panel">
                <div class="form-panel-head">
                    <div class="fph-icon"><i class="fas fa-upload"></i></div>
                    <span class="fph-title">Submit Verification Documents</span>
                </div>
                <div class="form-body">

                    <!-- Requirements -->
                    <div class="req-panel">
                        <div class="req-title"><i class="fas fa-circle-info"></i> Document Requirements</div>
                        <ul class="req-list">
                            <li><i class="fas fa-check"></i> Valid government-issued ID — Passport, Driver's License, or National ID</li>
                            <li><i class="fas fa-check"></i> Clear, readable photo with all four corners visible</li>
                            <li><i class="fas fa-check"></i> No blurry, cropped, or dark images</li>
                            <li><i class="fas fa-check"></i> Selfie holding your ID is optional but speeds up review</li>
                            <li><i class="fas fa-check"></i> Supported formats: JPG, PNG, PDF — max 5 MB each</li>
                        </ul>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">

                        <div class="form-row">
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="document_type">Document Type</label>
                                <select name="document_type" id="document_type" required>
                                    <option value="">Select type…</option>
                                    <option value="passport"        <?php echo ($_POST['document_type']??'')==='passport'       ?'selected':''; ?>>Passport</option>
                                    <option value="drivers_license" <?php echo ($_POST['document_type']??'')==='drivers_license'?'selected':''; ?>>Driver's License</option>
                                    <option value="national_id"     <?php echo ($_POST['document_type']??'')==='national_id'    ?'selected':''; ?>>National ID</option>
                                    <option value="other"           <?php echo ($_POST['document_type']??'')==='other'          ?'selected':''; ?>>Other Government ID</option>
                                </select>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label for="document_number">Document Number</label>
                                <input type="text" name="document_number" id="document_number"
                                       placeholder="e.g. A12345678"
                                       value="<?php echo htmlspecialchars($_POST['document_number']??''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>ID Document <span style="color:var(--red);margin-left:2px;text-transform:none;font-size:0.8rem;">*</span></label>
                            <div class="file-zone" id="docZone">
                                <input type="file" name="document_file" id="document_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                <div class="fz-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                                <div class="fz-title">Click to upload or drag &amp; drop</div>
                                <div class="fz-sub">JPG, PNG, PDF — max 5 MB</div>
                                <div class="fz-name" id="docName"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Selfie with ID <span class="optional">(optional — recommended)</span></label>
                            <div class="file-zone" id="selfieZone">
                                <input type="file" name="selfie_file" id="selfie_file" accept=".jpg,.jpeg,.png">
                                <div class="fz-icon"><i class="fas fa-camera"></i></div>
                                <div class="fz-title">Upload a selfie holding your ID</div>
                                <div class="fz-sub">JPG or PNG — helps speed up verification</div>
                                <div class="fz-name" id="selfieName"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="check-label">
                                <input type="checkbox" name="agree_terms" required>
                                I confirm that the information provided is accurate and all documents are authentic. I understand that submitting false documents may result in account suspension.
                            </label>
                        </div>

                        <button type="submit" name="submit_verification" class="btn btn-accent btn-block" style="margin-top:4px;">
                            <i class="fas fa-paper-plane"></i> Submit for Verification
                        </button>

                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
    const ham = document.getElementById('hamburger');
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('overlay');
    ham.addEventListener('click', () => { sb.classList.toggle('open'); ov.classList.toggle('visible'); });
    ov.addEventListener('click',  () => { sb.classList.remove('open'); ov.classList.remove('visible'); });

    function bindFileZone(inputId, nameId, zoneId) {
        const input  = document.getElementById(inputId);
        const nameEl = document.getElementById(nameId);
        const zone   = document.getElementById(zoneId);
        if(!input) return;
        input.addEventListener('change', () => {
            const f = input.files[0];
            if(f) {
                nameEl.textContent = f.name;
                nameEl.classList.add('visible');
                zone.style.borderColor = 'var(--teal)';
            }
        });
        zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop',      () => zone.classList.remove('dragover'));
    }
    bindFileZone('document_file', 'docName',    'docZone');
    bindFileZone('selfie_file',   'selfieName', 'selfieZone');
</script>
</body>
</html>