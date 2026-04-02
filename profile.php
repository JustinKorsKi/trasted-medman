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


if(file_exists('includes/2fa-functions.php')) require_once 'includes/2fa-functions.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = intval($_SESSION['user_id']); // ensure integer
$role    = $_SESSION['role'];
$success = '';
$error   = '';

// Get 2FA status — only relevant for midman, safe for all roles
$two_factor_enabled = false;
if($role === 'midman' && function_exists('is2FAEnabled')) {
    $two_factor_enabled = is2FAEnabled($user_id);
}

// Use intval for security
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$user  = mysqli_fetch_assoc($query);

$verification = getUserVerificationStatus($user_id);
$has_pending  = hasPendingVerification($user_id);

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    if(mysqli_query($conn, "UPDATE users SET full_name='$full_name', phone='$phone', email='$email' WHERE id=$user_id")) {
        $success = 'Profile updated successfully!';
        $query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
        $user  = mysqli_fetch_assoc($query);
        $_SESSION['full_name'] = $user['full_name'];
    } else {
        $error = 'Failed to update profile.';
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    if(!password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif($new != $confirm) {
        $error = 'New passwords do not match.';
    } elseif(strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif(!preg_match('/[A-Z]/', $new)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif(!preg_match('/[0-9]/', $new)) {
        $error = 'Password must contain at least one number.';
    } else {
        $hashed = password_hash($new, PASSWORD_BCRYPT);
        if(mysqli_query($conn, "UPDATE users SET password='$hashed' WHERE id=$user_id")) {
            $success = 'Password changed successfully!';
        } else {
            $error = 'Failed to change password.';
        }
    }
}

$tx_count      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions WHERE buyer_id=$user_id OR seller_id=$user_id"))['c'] ?? 0;
$dispute_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM disputes d JOIN transactions t ON d.transaction_id=t.id WHERE t.buyer_id=$user_id OR t.seller_id=$user_id"))['c'] ?? 0;
$member_days   = (int)((time() - strtotime($user['created_at'])) / 86400);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        /* (your existing CSS – unchanged) */
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        :root {
            --bg:         #0f0c08;
            --bg2:        #0f0b07;
            --surface:    #0f0b07;
            --surface2:   #201a13;
            --surface3:   #271f16;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.24);
            --gold:       #f0a500;
            --gold-lt:    #ffbe3a;
            --gold-dim:   rgba(240,165,0,0.13);
            --gold-glow:  rgba(240,165,0,0.28);
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

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(200,100,0,0.08) 0%,transparent 65%); pointer-events:none; }
        body.role-midman .sidebar::before { background:radial-gradient(circle,rgba(120,60,200,0.09) 0%,transparent 65%); }
        body.role-admin   .sidebar::before { background:radial-gradient(circle,rgba(180,30,30,0.10) 0%,transparent 65%); }

        /* ── Role-aware logo & avatar colors ── */
        /* Default (gold) — buyers & sellers */
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

.logo-text {
    font-family: var(--font-head);
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text);
    line-height: 1.2;
    letter-spacing: -0.01em;
}
.logo-sub {
    font-size: 0.65rem;
    color: var(--accent);
    letter-spacing: 0.12em;
    text-transform: uppercase;
    display: block;
    font-family: var(--font-body);
    font-weight: 600;
}

        /* Midman — purple, white fg */
        body.role-midman .avatar-sm,
        body.role-midman .hero-avatar { background:linear-gradient(135deg,#a064ff,#7040cc) !important; color:#ffffff !important; box-shadow:0 0 20px rgba(160,100,255,0.28) !important; }
        body.role-midman .logo-sub   { color:#a064ff !important; }
        body.role-midman .user-pill-role { color:#a064ff !important; }
        body.role-midman .nav-link.active { color:#a064ff !important; background:rgba(160,100,255,0.13) !important; border-color:rgba(160,100,255,0.14) !important; }
        body.role-midman .nav-link.active::before { background:#a064ff !important; }
        body.role-midman .btn-primary { background:linear-gradient(135deg,#a064ff,#7040cc) !important; color:#fff !important; box-shadow:0 4px 18px rgba(160,100,255,0.28) !important; }
        body.role-midman .hero-role   { background:rgba(160,100,255,0.13) !important; color:#a064ff !important; border-color:rgba(160,100,255,0.14) !important; }
        body.role-midman .profile-hero::after  { background:radial-gradient(circle,rgba(160,100,255,0.10) 0%,transparent 65%) !important; }
        body.role-midman .profile-hero::before { background:linear-gradient(90deg,transparent,rgba(160,100,255,0.28),transparent) !important; }
        /* Admin — red, white fg */
        body.role-admin .avatar-sm,
        body.role-admin .hero-avatar { background:linear-gradient(135deg,#e03535,#b01e1e) !important; color:#ffffff !important; box-shadow:0 0 20px rgba(224,53,53,0.28) !important; }
        body.role-admin .logo-sub    { color:#e03535 !important; }
        body.role-admin .user-pill-role { color:#e03535 !important; }
        body.role-admin .nav-link.active { color:#e03535 !important; background:rgba(224,53,53,0.13) !important; border-color:rgba(224,53,53,0.14) !important; }
        body.role-admin .nav-link.active::before { background:#e03535 !important; }
        body.role-admin .btn-primary { background:linear-gradient(135deg,#e03535,#b01e1e) !important; color:#fff !important; box-shadow:0 4px 18px rgba(224,53,53,0.28) !important; }
        body.role-admin .hero-role   { background:rgba(224,53,53,0.13) !important; color:#e03535 !important; border-color:rgba(224,53,53,0.14) !important; }
        body.role-admin .profile-hero::after { background:radial-gradient(circle,rgba(224,53,53,0.10) 0%,transparent 65%) !important; }
        body.role-admin .profile-hero::before { background:linear-gradient(90deg,transparent,rgba(224,53,53,0.3),transparent) !important; }
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


        .logo-sub  { font-size:0.65rem; color:var(--gold); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--gold); background:var(--gold-dim); border:1px solid rgba(240,165,0,0.12); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--gold); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .avatar-sm { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#c47d00); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px rgba(240,165,0,0.2); }
        .user-pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .user-pill-role { font-size:0.68rem; color:var(--gold); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .online-dot { display:flex; align-items:center; gap:7px; font-size:0.78rem; color:var(--text-muted); }
        .online-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--teal); box-shadow:0 0 8px var(--teal); }
        .content { padding:28px 32px; flex:1; max-width:1100px; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);} }

        /* ── PROFILE HERO ── */
        .profile-hero {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:36px 40px; margin-bottom:24px;
            display:flex; align-items:center; gap:32px;
            position:relative; overflow:hidden;
            opacity:0; animation:fadeUp 0.5s ease forwards;
        }
        .profile-hero::before {
            content:''; position:absolute; top:0; left:0; right:0; height:1px;
            background:linear-gradient(90deg,transparent,rgba(240,165,0,0.3),transparent);
        }
        .profile-hero::after {
            content:''; position:absolute; top:-80px; right:-80px;
            width:320px; height:320px;
            background:radial-gradient(circle,rgba(240,130,0,0.14) 0%,transparent 65%);
            pointer-events:none;
        }

        .hero-avatar {
            width:100px; height:100px; border-radius:50%;
            background:linear-gradient(135deg,var(--gold),#c48600);
            display:flex; align-items:center; justify-content:center;
            font-family:var(--font-head); font-weight:800; font-size:2.2rem;
            color:#0f0c08; flex-shrink:0; position:relative; z-index:1;
            box-shadow:0 0 0 4px rgba(240,165,0,0.2), 0 8px 32px var(--gold-glow);
        }

        .hero-info { flex:1; min-width:0; position:relative; z-index:1; }
        .hero-name {
            font-family:var(--font-head); font-size:1.9rem; font-weight:800;
            color:var(--text); line-height:1; margin-bottom:8px; letter-spacing:-0.01em;
        }
        .hero-role {
            display:inline-flex; align-items:center; gap:6px;
            background:var(--gold-dim); color:var(--gold);
            border:1px solid rgba(240,165,0,0.22);
            font-size:0.72rem; font-weight:700; letter-spacing:0.12em;
            text-transform:uppercase; padding:4px 13px; border-radius:20px;
            margin-bottom:16px;
        }
        .hero-stats { display:flex; gap:24px; flex-wrap:wrap; }
        .hero-stat { text-align:left; }
        .hero-stat-val { font-family:var(--font-head); font-size:1.4rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .hero-stat-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:2px; }
        .hero-divider  { width:1px; height:36px; background:var(--border2); align-self:center; }

        /* verify badge */
        .verify-badge { position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; gap:6px; flex-shrink:0; cursor:pointer; }
        .verify-circle { width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.4rem; transition:transform 0.2s; border:1px solid transparent; }
        .verify-circle:hover { transform:scale(1.05); }
        .verify-circle.verified   { background:var(--teal-dim);   color:var(--teal);   box-shadow:0 0 16px rgba(0,212,170,0.18);  border-color:rgba(0,212,170,0.18); }
        .verify-circle.pending    { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.18); }
        .verify-circle.unverified { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.18); }
        .verify-circle.rejected   { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.18); }
        .verify-lbl { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }

        .verify-tooltip { position:absolute; top:100%; right:0; background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); padding:13px; min-width:210px; z-index:10; display:none; box-shadow:0 16px 40px rgba(0,0,0,0.4); }
        .verify-badge:hover .verify-tooltip { display:block; }
        .tooltip-item { font-size:0.82rem; padding:5px 0; color:var(--text-muted); }
        .tooltip-item strong { color:var(--text-warm); }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 18px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim);  color:var(--teal);  border:1px solid rgba(0,212,170,0.2); }
        .alert-error   { background:var(--red-dim);   color:#ff7090;      border:1px solid rgba(255,77,109,0.2); }
        .alert-info    { background:var(--blue-dim);  color:var(--blue);  border:1px solid rgba(78,159,255,0.2); }

        /* ── MIDMAN RATING ── */
        .midman-rating {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius); padding:24px 28px; margin-bottom:24px;
            display:flex; align-items:center; gap:24px;
            opacity:0; animation:fadeUp 0.5s 0.1s ease forwards;
            position:relative;
        }
        .midman-rating::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.2),transparent); }
        .rating-ring { width:80px; height:80px; border-radius:50%; background:var(--gold-dim); display:flex; flex-direction:column; align-items:center; justify-content:center; flex-shrink:0; border:2px solid rgba(240,165,0,0.22); }
        .ring-val { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--gold); line-height:1; letter-spacing:-0.01em; }
        .ring-max { font-size:0.65rem; color:var(--text-muted); }
        .rating-stars { display:flex; gap:4px; margin-bottom:5px; }
        .rating-stars i { color:var(--gold); font-size:0.9rem; }
        .rating-title { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); margin-bottom:6px; letter-spacing:-0.01em; }
        .rating-count { font-size:0.82rem; color:var(--text-muted); }

        /* ── FORM GRID ── */
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }

        /* ── PANELS ── */
        .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; opacity:0; animation:fadeUp 0.5s ease forwards; position:relative; }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.12),transparent); z-index:1; }
        .panel:nth-child(1){animation-delay:0.06s;} .panel:nth-child(2){animation-delay:0.12s;}

        .panel-header { display:flex; align-items:center; gap:12px; padding:17px 24px; border-bottom:1px solid var(--border); }
        .panel-header-icon { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; border:1px solid transparent; }
        .icon-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .icon-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .icon-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .icon-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .panel-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .panel-body  { padding:24px; }
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }


        /* ── FORM ELEMENTS ── */
        .form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
        .form-group:last-of-type { margin-bottom:0; }
        .form-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }
        .form-control {
            padding:11px 14px; background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); color:var(--text-warm);
            font-family:var(--font-body); font-size:0.9rem; transition:all 0.22s; width:100%; outline:none;
        }
        .form-control:focus    { border-color:var(--gold); box-shadow:0 0 0 3px rgba(240,165,0,0.1); background:var(--surface3); }
        .form-control:disabled { color:var(--text-dim); cursor:not-allowed; }
        .form-control::placeholder { color:var(--text-dim); }
        .form-hint { font-size:0.72rem; color:var(--text-dim); }

        .pw-wrap { position:relative; }
        .pw-wrap .form-control { padding-right:42px; }
        .pw-toggle { position:absolute; right:13px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-dim); transition:color 0.2s; font-size:0.85rem; padding:0; }
        .pw-toggle:hover { color:var(--text-muted); }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:8px; padding:11px 20px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.9rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; white-space:nowrap; justify-content:center; width:100%; margin-top:20px; letter-spacing:0.01em; }
        .btn-primary { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 4px 18px var(--gold-glow), 0 1px 0 rgba(255,255,255,0.1) inset; }
        .btn-primary:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); box-shadow:0 8px 26px rgba(240,165,0,0.4); }
        .btn-orange  { background:var(--orange-dim); color:var(--orange); border:1px solid rgba(255,150,50,0.2); }
        .btn-orange:hover  { background:rgba(255,150,50,0.22); transform:translateY(-2px); }
        .btn-teal    { background:var(--teal-dim);   color:var(--teal);   border:1px solid rgba(0,212,170,0.2); }
        .btn-teal:hover    { background:rgba(0,212,170,0.2);  transform:translateY(-2px); }
        .btn-purple  { background:var(--purple-dim); color:var(--purple); border:1px solid rgba(160,100,255,0.2); }
        .btn-purple:hover  { background:rgba(160,100,255,0.2); transform:translateY(-2px); }

        /* ── ACCOUNT STATUS ── */
        .status-list { display:flex; flex-direction:column; }
        .status-row { display:flex; align-items:center; justify-content:space-between; padding:14px 0; border-bottom:1px solid var(--border); }
        .status-row:last-child { border-bottom:none; }
        .status-key { display:flex; align-items:center; gap:10px; font-size:0.875rem; color:var(--text-muted); }
        .status-key i { font-size:0.8rem; width:16px; text-align:center; color:var(--text-dim); }
        .status-val { font-size:0.875rem; font-weight:600; color:var(--text-warm); }

        .pill { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; padding:4px 10px; border-radius:20px; }
        .pill-verified { background:var(--teal-dim);   color:var(--teal);   border:1px solid rgba(0,212,170,0.15); }
        .pill-pending  { background:var(--orange-dim); color:var(--orange); border:1px solid rgba(255,150,50,0.15); }
        .pill-rejected { background:var(--red-dim);    color:var(--red);    border:1px solid rgba(255,77,109,0.15); }
        .pill-earn     { font-family:var(--font-head); font-size:1rem; color:var(--teal); font-weight:800; background:none; padding:0; letter-spacing:-0.01em; }

        .verification-actions { display:flex; gap:10px; margin-top:16px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root{--sidebar-w:220px;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} .profile-hero{flex-direction:column;text-align:center;padding:28px 24px;} .hero-stats{justify-content:center;} .verify-badge{position:absolute;top:20px;right:20px;} .form-grid{grid-template-columns:1fr;} .verification-actions{flex-direction:column;} }
        @media(max-width:540px)  { .form-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body class="role-<?php echo $role; ?>">
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
     <a href="index.php" class="sidebar-logo">
    <div class="logo-icon">
        <img src="images/logowhite.png" alt="Trusted Midman" style="width:100%; height:100%; object-fit:cover;">
    </div>
    <div class="logo-text">
        Trusted Midman
        <span class="logo-sub"><?php echo $role==='admin'?'Admin Panel':'Marketplace'; ?></span>
    </div>
</a>


        <nav class="sidebar-nav">
            <?php if($role === 'seller'): ?>
                <div class="nav-label">Seller</div>
                <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
                <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> Transactions</a>
                    <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
                <div class="nav-label" style="margin-top:10px;">Account</div>
                <a href="apply-midman.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
                <a href="profile.php"          class="nav-link active"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>

            <?php elseif($role === 'buyer'): ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
                <div class="nav-label" style="margin-top:10px;">Account</div>
                <a href="apply-midman.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
                <a href="profile.php"          class="nav-link active"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>

            <?php elseif($role === 'midman'): ?>
                <div class="nav-label">Midman</div>
                <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions 
                <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
                <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> KYC Status</a>
                <div class="nav-label" style="margin-top:10px;">Security</div>
                <a href="setup-2fa.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
                    <?php if($two_factor_enabled): ?><span class="security-badge">Active</span><?php endif; ?>
                </a>
                <div class="nav-label" style="margin-top:10px;">Account</div>
                <a href="profile.php" class="nav-link active"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>

            <?php elseif($role === 'admin'): ?>
                <div class="nav-label">Overview</div>
                <a href="admin/dashboard.php"     class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="admin/charts.php"        class="nav-link"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports</a>
                <div class="nav-label" style="margin-top:10px;">Management</div>
                <a href="admin/users.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-users"></i></span> Users</a>
                <a href="admin/transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-arrows-left-right"></i></span> Transactions</a>
                <a href="admin/verifications.php" class="nav-link"><span class="nav-icon"><i class="fas fa-id-card"></i></span> KYC Verifications</a>
                <a href="admin/applications.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Midman Apps</a>
                <a href="admin/disputes.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-gavel"></i></span> Disputes</a>
                <div class="nav-label" style="margin-top:10px;">System</div>
                <a href="admin/settings.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-gear"></i></span> Settings</a>
                <a href="profile.php"             class="nav-link active"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> My Profile</a>
            <?php endif; ?>

            <a href="logout.php" class="nav-link" style="color:var(--text-dim);margin-top:6px;">
                <span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="avatar-sm"><?php echo strtoupper(substr($user['username'], 0, 2)); ?></div>
                <div>
                    <div class="user-pill-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                    <div class="user-pill-role"><?php echo ucfirst($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN (unchanged) -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">My Profile</span>
            </div>
            <div class="online-dot">Online</div>
        </header>

        <div class="content">

            <!-- PROFILE HERO -->
            <div class="profile-hero">
                <div class="hero-avatar"><?php echo strtoupper(substr($user['username'], 0, 2)); ?></div>
                <div class="hero-info">
                    <div class="hero-name"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></div>
                    <div class="hero-role">
                        <i class="fas fa-<?php echo $role=='buyer'?'bag-shopping':($role=='seller'?'store':'shield-halved'); ?>"></i>
                        <?php echo ucfirst($role); ?>
                    </div>
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-val"><?php echo $tx_count; ?></div>
                            <div class="hero-stat-lbl">Transactions</div>
                        </div>
                        <div class="hero-divider"></div>
                        <div class="hero-stat">
                            <div class="hero-stat-val"><?php echo $member_days; ?></div>
                            <div class="hero-stat-lbl">Days active</div>
                        </div>
                        <div class="hero-divider"></div>
                        <div class="hero-stat">
                            <div class="hero-stat-val"><?php echo $dispute_count; ?></div>
                            <div class="hero-stat-lbl">Disputes</div>
                        </div>
                    </div>
                </div>

                <!-- VERIFICATION BADGE -->
                <div class="verify-badge">
                    <?php
                    $verification_level = $verification['verification_level'] ?? 'unverified';
                    $circle_class = $icon = $label = '';
                    switch($verification_level) {
                        case 'verified':  $circle_class='verified';  $icon='check-circle';  $label='Verified';   break;
                        case 'pending':   $circle_class='pending';   $icon='clock';          $label='Pending';    break;
                        case 'rejected':  $circle_class='rejected';  $icon='times-circle';   $label='Rejected';   break;
                        default:          $circle_class='unverified'; $icon='id-card';        $label='Unverified';
                    }
                    ?>
                    <div class="verify-circle <?php echo $circle_class; ?>">
                        <i class="fas fa-<?php echo $icon; ?>"></i>
                    </div>
                    <div class="verify-lbl"><?php echo $label; ?></div>
                    <?php if($verification_level != 'unverified'): ?>
                    <div class="verify-tooltip">
                        <?php if($verification_level == 'verified'): ?>
                            <div class="tooltip-item"><i class="fas fa-check-circle" style="color:var(--teal);margin-right:6px;"></i><strong>Verified on:</strong> <?php echo date('M d, Y', strtotime($verification['verification_reviewed_at'] ?? $user['created_at'])); ?></div>
                        <?php elseif($verification_level == 'pending'): ?>
                            <div class="tooltip-item"><i class="fas fa-clock" style="color:var(--orange);margin-right:6px;"></i><strong>Submitted:</strong> <?php echo date('M d, Y', strtotime($verification['verification_submitted_at'] ?? $user['created_at'])); ?></div>
                            <div class="tooltip-item"><small>Review usually takes 24–48 hours.</small></div>
                        <?php elseif($verification_level == 'rejected' && $verification['verification_notes']): ?>
                            <div class="tooltip-item"><i class="fas fa-times-circle" style="color:var(--red);margin-right:6px;"></i><strong>Reason:</strong> <?php echo htmlspecialchars($verification['verification_notes']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ALERTS -->
            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if($verification_level == 'verified' && $role != 'midman'): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> Your identity is verified! You can now apply to become a midman.</div>
            <?php elseif($verification_level == 'pending'): ?>
                <div class="alert alert-info"><i class="fas fa-clock"></i> Your verification is pending review. This usually takes 24–48 hours.</div>
            <?php elseif($verification_level == 'rejected'): ?>
                <div class="alert alert-error"><i class="fas fa-times-circle"></i> Your verification was rejected. <?php echo $verification['verification_notes'] ? 'Reason: '.htmlspecialchars($verification['verification_notes']) : ''; ?></div>
            <?php endif; ?>

            <!-- MIDMAN RATING -->
            <?php if($role == 'midman'): ?>
            <?php $rating = $user['midman_rating'] ?? 0; ?>
            <div class="midman-rating">
                <div class="rating-ring">
                    <div class="ring-val"><?php echo number_format($rating, 1); ?></div>
                    <div class="ring-max">/ 5.0</div>
                </div>
                <div>
                    <div class="rating-title">Midman Reputation</div>
                    <div class="rating-stars">
                        <?php for($i=1;$i<=5;$i++): ?>
                            <i class="fas fa-star" style="color:<?php echo $i<=round($rating)?'var(--gold)':'var(--text-dim)';?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-count">Based on <?php echo $user['total_midman_ratings'] ?? 0; ?> verified ratings</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- FORMS GRID -->
            <div class="form-grid">

                <!-- Profile Information -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-header-icon icon-gold"><i class="fas fa-user-pen"></i></div>
                        <span class="panel-title">Profile Information</span>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" class="form-control" disabled>
                                <span class="form-hint">Cannot be changed</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <input type="text" value="<?php echo ucfirst($user['role']); ?>" class="form-control" disabled>
                            </div>
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-floppy-disk"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-header-icon icon-orange"><i class="fas fa-key"></i></div>
                        <span class="panel-title">Change Password</span>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <div class="pw-wrap">
                                    <input type="password" name="current_password" id="pw1" class="form-control" required placeholder="Enter current password">
                                    <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <div class="pw-wrap">
                                    <input type="password" name="new_password" id="pw2" class="form-control" required placeholder="Min 8 chars, uppercase & number">
                                    <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)"><i class="fas fa-eye"></i></button>
                                </div>
                                <span class="form-hint">Minimum 8 characters, at least one uppercase and one number</span>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password</label>
                                <div class="pw-wrap">
                                    <input type="password" name="confirm_password" id="pw3" class="form-control" required placeholder="Repeat new password">
                                    <button type="button" class="pw-toggle" onclick="togglePw('pw3',this)"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>
                            <div style="margin-bottom:16px;">
                                <div style="height:4px;background:var(--surface2);border-radius:4px;overflow:hidden;margin-top:8px;">
                                    <div id="strengthBar" style="height:100%;width:0;border-radius:4px;transition:width 0.3s,background 0.3s;"></div>
                                </div>
                                <span id="strengthLabel" style="font-size:0.7rem;color:var(--text-dim);margin-top:4px;display:block;"></span>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-orange">
                                <i class="fas fa-lock"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>

            </div>

            <!-- ACCOUNT STATUS -->
            <div class="panel" style="animation-delay:0.18s;">
                <div class="panel-header">
                    <div class="panel-header-icon icon-teal"><i class="fas fa-circle-info"></i></div>
                    <span class="panel-title">Account Details</span>
                </div>
                <div class="panel-body">
                    <div class="status-list">
                        <div class="status-row">
                            <span class="status-key"><i class="fas fa-shield-check"></i> Verification</span>
                            <?php switch($verification_level) {
                                case 'verified':  echo '<span class="pill pill-verified"><i class="fas fa-check-circle"></i> Verified</span>'; break;
                                case 'pending':   echo '<span class="pill pill-pending"><i class="fas fa-clock"></i> Pending</span>'; break;
                                case 'rejected':  echo '<span class="pill pill-rejected"><i class="fas fa-times-circle"></i> Rejected</span>'; break;
                                default:          echo '<span class="pill pill-rejected"><i class="fas fa-exclamation-circle"></i> Unverified</span>';
                            } ?>
                        </div>
                        <div class="status-row">
                            <span class="status-key"><i class="fas fa-id-badge"></i> Account Type</span>
                            <span class="status-val"><?php echo ucfirst($role); ?></span>
                        </div>
                        <div class="status-row">
                            <span class="status-key"><i class="fas fa-envelope"></i> Email</span>
                            <span class="status-val"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="status-row">
                            <span class="status-key"><i class="fas fa-calendar-days"></i> Member Since</span>
                            <span class="status-val"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                        <?php if($role == 'midman'): ?>
                        <div class="status-row">
                            <span class="status-key"><i class="fas fa-coins"></i> Total Earnings</span>
                            <?php $earn = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as t FROM earnings WHERE midman_id=$user_id AND status='paid'")); ?>
                            <span class="pill pill-earn">$<?php echo number_format($earn['t'] ?? 0, 2); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="verification-actions">
                        <?php if($verification_level != 'verified' && $verification_level != 'pending' && $role !== 'admin'): ?>
                            <a href="verify-identity.php" class="btn btn-purple" style="width:auto;margin-top:0;">
                                <i class="fas fa-id-card"></i> Verify Identity
                            </a>
                        <?php endif; ?>
                        <?php if($verification_level == 'verified' && !in_array($role, ['midman','admin'])): ?>
                            <a href="apply-midman.php" class="btn btn-teal" style="width:auto;margin-top:0;">
                                <i class="fas fa-handshake"></i> Apply as Midman
                            </a>
                        <?php endif; ?>
                        <?php if($verification_level == 'pending' && $role !== 'admin'): ?>
                            <a href="verification-status.php" class="btn btn-orange" style="width:auto;margin-top:0;">
                                <i class="fas fa-clock"></i> Check Status
                            </a>
                        <?php endif; ?>
                        <?php if($verification_level == 'rejected' && $role !== 'admin'): ?>
                            <a href="verify-identity.php" class="btn btn-purple" style="width:auto;margin-top:0;">
                                <i class="fas fa-redo"></i> Resubmit Verification
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('overlay');
    hamburger.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
    overlay.addEventListener('click',   () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });

    function togglePw(id, btn) {
        const input = document.getElementById(id);
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        btn.innerHTML = `<i class="fas fa-eye${isText ? '' : '-slash'}"></i>`;
    }

    const pw2 = document.getElementById('pw2');
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    const levels = [
        {max:0, w:'0%', bg:'var(--red)', text:''},
        {max:1, w:'25%', bg:'var(--red)', text:'Weak'},
        {max:2, w:'50%', bg:'var(--orange)', text:'Fair'},
        {max:3, w:'75%', bg:'var(--gold)', text:'Good'},
        {max:99,w:'100%',bg:'var(--teal)', text:'Strong'},
    ];
    if(pw2) {
        pw2.addEventListener('input', () => {
            const v = pw2.value;
            let score = 0;
            if(v.length >= 8) score++;
            if(/[A-Z]/.test(v)) score++;
            if(/[0-9]/.test(v)) score++;
            if(/[^A-Za-z0-9]/.test(v)) score++;
            const l = levels.find(x => score <= x.max) || levels[levels.length-1];
            bar.style.width = v ? l.w : '0%';
            bar.style.background = l.bg;
            lbl.textContent = v ? l.text : '';
            lbl.style.color = l.bg;
        });
    }
</script>
</body>
</html>