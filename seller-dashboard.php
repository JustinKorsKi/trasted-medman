<?php
require_once 'includes/config.php';
require_once 'includes/sidebar-data.php';

// Sidebar badge count
$pending_tx_count = 0;
if($_SESSION['role'] === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($_SESSION['role'] === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header('Location: login.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? '';

$product_count   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE seller_id = $user_id"))['c'];
$sales_stats     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c, COALESCE(SUM(amount),0) as total FROM transactions WHERE seller_id = $user_id"));
$pending_count   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions WHERE seller_id = $user_id AND status='pending'"))['c'];
$completed_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions WHERE seller_id = $user_id AND status='completed'"))['c'];

$recent_sales = mysqli_query($conn, "SELECT t.*, p.title as product_title, p.image_path, b.username as buyer_name
                                     FROM transactions t
                                     JOIN products p ON t.product_id = p.id
                                     JOIN users b ON t.buyer_id = b.id
                                     WHERE t.seller_id = $user_id
                                     ORDER BY t.created_at DESC LIMIT 5");

$my_products = mysqli_query($conn, "SELECT * FROM products WHERE seller_id = $user_id ORDER BY created_at DESC LIMIT 6");

$display_name = $full_name ?: $username;

$status_cfg = [
    'pending'     => ['label'=>'Pending',     'color'=>'var(--orange)', 'bg'=>'var(--orange-dim)'],
    'in_progress' => ['label'=>'In Progress', 'color'=>'var(--blue)',   'bg'=>'var(--blue-dim)'],
    'shipped'     => ['label'=>'Shipped',     'color'=>'var(--purple)', 'bg'=>'var(--purple-dim)'],
    'delivered'   => ['label'=>'Delivered',   'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)'],
    'completed'   => ['label'=>'Completed',   'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)'],
    'disputed'    => ['label'=>'Disputed',    'color'=>'var(--red)',    'bg'=>'var(--red-dim)'],
    'cancelled'   => ['label'=>'Cancelled',   'color'=>'var(--text-dim)','bg'=>'var(--surface2)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
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

        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text-warm);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .layout { display: flex; min-height: 100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; height: 100vh;
            z-index: 100;
            transition: transform 0.35s cubic-bezier(.77,0,.18,1);
        }

        /* warm ambient glow inside sidebar */
        .sidebar::before {
            content: '';
            position: absolute; bottom: -80px; left: -80px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(200,100,0,0.08) 0%, transparent 65%);
            pointer-events: none;
        }

        .sidebar-logo {
            display: flex; align-items: center; gap: 12px;
            padding: 26px 22px;
            text-decoration: none;
            border-bottom: 1px solid var(--border);
            position: relative; z-index: 1;
        }
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
        .logo-text {
            font-family: var(--font-head); font-weight: 700;
            font-size: 1.1rem; color: var(--text); line-height: 1.2;
            letter-spacing: -0.01em;
        }
        .logo-sub {
            font-size: 0.65rem; color: var(--gold);
            letter-spacing: 0.12em; text-transform: uppercase; display: block;
            font-family: var(--font-body); font-weight: 600;
        }

        .sidebar-nav { flex: 1; padding: 18px 10px; overflow-y: auto; position: relative; z-index: 1; }

        .nav-label {
            font-size: 0.65rem; font-weight: 700; letter-spacing: 0.14em;
            text-transform: uppercase; color: var(--text-dim);
            padding: 12px 12px 7px;
        }

        .nav-link {
            display: flex; align-items: center; gap: 11px;
            padding: 10px 13px; border-radius: var(--radius-sm);
            text-decoration: none; color: var(--text-muted);
            font-size: 0.9rem; font-weight: 500;
            margin-bottom: 2px; transition: all 0.2s;
            position: relative;
        }
        .nav-link:hover { color: var(--text-warm); background: var(--surface2); }
        .nav-link.active {
            color: var(--gold); background: var(--gold-dim);
            border: 1px solid rgba(240,165,0,0.12);
        }
        .nav-link.active::before {
            content: '';
            position: absolute; left: 0; top: 20%; bottom: 20%;
            width: 3px; background: var(--gold); border-radius: 0 3px 3px 0;
        }
        .nav-icon { width: 20px; text-align: center; font-size: 0.9rem; flex-shrink: 0; }

        .sidebar-footer { padding: 14px; border-top: 1px solid var(--border); position: relative; z-index: 1; }
        .user-pill {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
        }
        .ava {
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), #c47d00);
            display: flex; align-items: center; justify-content: center;
            font-family: var(--font-head); font-weight: 700;
            font-size: 0.85rem; color: #0f0c08; flex-shrink: 0;
            box-shadow: 0 0 10px rgba(240,165,0,0.2);
        }
        .pill-name { font-size: 0.875rem; font-weight: 500; color: var(--text-warm); }
        .pill-role { font-size: 0.68rem; color: var(--gold); text-transform: uppercase; letter-spacing: 0.09em; }

        /* ── MAIN ── */
        .main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }

        .topbar {
            position: sticky; top: 0; z-index: 50;
            background: rgba(15,12,8,0.88);
            backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--border);
            padding: 0 32px; height: 64px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .topbar-left { display: flex; align-items: center; gap: 16px; }
        .hamburger {
            display: none; background: none; border: none;
            color: var(--text-muted); font-size: 1.1rem;
            cursor: pointer; padding: 6px; border-radius: 7px;
            transition: color 0.2s;
        }
        .hamburger:hover { color: var(--text-warm); }
        .page-title {
            font-family: var(--font-head); font-size: 1.15rem;
            font-weight: 700; color: var(--text); letter-spacing: -0.01em;
        }

        .online-dot {
            display: flex; align-items: center; gap: 7px;
            font-size: 0.78rem; color: var(--text-muted);
        }
        .online-dot::before {
            content: '';
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--teal);
            box-shadow: 0 0 8px var(--teal);
        }

        .content { padding: 28px 32px; flex: 1; }

        /* ── HERO BANNER ── */
        @keyframes fadeUp { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }

        .hero {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: var(--radius-lg);
            padding: 28px 32px;
            margin-bottom: 22px;
            position: relative; overflow: hidden;
            animation: fadeUp 0.5s ease forwards;
        }
        /* gold glow top-right */
        .hero::before {
            content: '';
            position: absolute; top: -80px; right: -80px;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(240,130,0,0.16) 0%, transparent 65%);
            pointer-events: none;
        }
        /* teal glow bottom-left */
        .hero::after {
            content: '';
            position: absolute; bottom: -50px; left: 160px;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(0,212,170,0.07) 0%, transparent 65%);
            pointer-events: none;
        }
        /* glossy top line */
        .hero-line {
            position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(240,165,0,0.3), transparent);
        }

        .hero-inner {
            display: flex; align-items: center; justify-content: space-between;
            gap: 20px; flex-wrap: wrap; position: relative; z-index: 1;
        }
        .hero-greeting {
            font-size: 0.75rem; font-weight: 700; color: var(--teal);
            text-transform: uppercase; letter-spacing: 0.14em; margin-bottom: 6px;
        }
        .hero-name {
            font-family: var(--font-head);
            font-size: 1.9rem; font-weight: 800; letter-spacing: -0.01em;
            color: var(--text); line-height: 1.1; margin-bottom: 6px;
        }
        .hero-name span { color: var(--gold); }
        .hero-sub { font-size: 0.875rem; color: var(--text-muted); }
        .hero-actions { display: flex; gap: 10px; flex-shrink: 0; }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 18px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.875rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: none;
            transition: all 0.22s ease; white-space: nowrap; letter-spacing: 0.01em;
        }
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), #d48500);
            color: #0f0c08; font-weight: 700;
            box-shadow: 0 3px 14px var(--gold-glow);
        }
        .btn-gold:hover { background: linear-gradient(135deg, var(--gold-lt), var(--gold)); transform: translateY(-2px); box-shadow: 0 6px 20px var(--gold-glow); }
        .btn-ghost {
            background: var(--surface2); color: var(--text-muted);
            border: 1px solid var(--border2);
        }
        .btn-ghost:hover { color: var(--text-warm); border-color: var(--border3); }
        .btn-sm { padding: 7px 13px; font-size: 0.8rem; }

        /* ── STAT GRID ── */
        .stat-grid {
            display: grid; grid-template-columns: repeat(4,1fr);
            gap: 14px; margin-bottom: 22px;
        }

        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 18px;
            display: flex; align-items: flex-start; gap: 14px;
            position: relative; overflow: hidden;
            opacity: 0; animation: fadeUp 0.45s ease forwards;
            transition: border-color 0.25s, transform 0.25s, box-shadow 0.25s;
        }
        .stat-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            opacity: 0; transition: opacity 0.25s;
        }
        .stat-card:hover { border-color: var(--border2); transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,0.3); }
        .stat-card:hover::before { opacity: 1; }

        .stat-card:nth-child(1) { animation-delay: .05s; }
        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, var(--gold), transparent); }
        .stat-card:nth-child(2) { animation-delay: .10s; }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, var(--teal), transparent); }
        .stat-card:nth-child(3) { animation-delay: .15s; }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, var(--blue), transparent); }
        .stat-card:nth-child(4) { animation-delay: .20s; }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, var(--orange), transparent); }
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }

        .stat-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; flex-shrink: 0;
            border: 1px solid transparent;
        }
        .si-gold   { background: var(--gold-dim);   color: var(--gold);   border-color: rgba(240,165,0,0.14); }
        .si-teal   { background: var(--teal-dim);   color: var(--teal);   border-color: rgba(0,212,170,0.14); }
        .si-blue   { background: var(--blue-dim);   color: var(--blue);   border-color: rgba(78,159,255,0.14); }
        .si-orange { background: var(--orange-dim); color: var(--orange); border-color: rgba(255,150,50,0.14); }

        .stat-val {
            font-family: var(--font-head); font-size: 1.7rem; font-weight: 800;
            color: var(--text); line-height: 1; letter-spacing: -0.01em;
        }
        .stat-lbl { font-size: 0.78rem; color: var(--text-muted); margin-top: 4px; }
        .stat-sub {
            font-size: 0.68rem; color: var(--text-dim);
            margin-top: 7px; display: flex; align-items: center; gap: 4px;
        }
        .stat-sub i { font-size: 0.6rem; }

        /* ── CONTENT GRID ── */
        .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }

        /* ── PANELS ── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            opacity: 0; animation: fadeUp 0.45s ease forwards;
        }
        .panel:nth-child(1) { animation-delay: .28s; }
        .panel:nth-child(2) { animation-delay: .36s; }

        .panel-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 15px 20px; border-bottom: 1px solid var(--border);
        }
        .panel-title {
            font-family: var(--font-head); font-size: 0.95rem; font-weight: 700;
            color: var(--text); display: flex; align-items: center; gap: 9px;
            letter-spacing: -0.01em;
        }
        .panel-title-icon {
            width: 27px; height: 27px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem;
        }
        .pti-gold   { background: var(--gold-dim);   color: var(--gold); }
        .pti-teal   { background: var(--teal-dim);   color: var(--teal); }
        .panel-body { padding: 0; }

        /* ── SALES LIST ── */
        .sale-item {
            display: flex; align-items: center; gap: 13px;
            padding: 13px 20px; border-bottom: 1px solid var(--border);
            transition: background 0.2s;
        }
        .sale-item:last-child { border-bottom: none; }
        .sale-item:hover { background: var(--surface2); }

        .sale-thumb {
            width: 44px; height: 36px; border-radius: 8px;
            background: var(--surface2); border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--text-dim); font-size: 1rem; overflow: hidden; flex-shrink: 0;
        }
        .sale-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .sale-title {
            font-size: 0.875rem; font-weight: 600; color: var(--text-warm);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            max-width: 150px; margin-bottom: 2px;
        }
        .sale-meta { font-size: 0.72rem; color: var(--text-dim); }

        .sale-right { margin-left: auto; text-align: right; flex-shrink: 0; }
        .sale-amount {
            font-family: var(--font-head); font-size: 0.95rem; font-weight: 700;
            color: var(--gold); letter-spacing: -0.01em;
        }
        .sale-badge {
            font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
            padding: 2px 7px; border-radius: 10px; white-space: nowrap;
            display: inline-block; margin-top: 3px; letter-spacing: 0.05em;
        }

        /* ── PRODUCT MINI GRID ── */
        .prod-mini-grid { display: grid; grid-template-columns: 1fr 1fr; }

        .prod-mini-card {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            border-right: 1px solid var(--border);
            transition: background 0.2s;
        }
        .prod-mini-card:nth-child(even)      { border-right: none; }
        .prod-mini-card:nth-last-child(-n+2) { border-bottom: none; }
        .prod-mini-card:hover                { background: var(--surface2); }

        .pmini-thumb {
            width: 100%; height: 72px; border-radius: var(--radius-sm);
            background: var(--surface2); border: 1px solid var(--border);
            overflow: hidden; display: flex; align-items: center; justify-content: center;
            color: var(--text-dim); font-size: 1.5rem; margin-bottom: 9px;
        }
        .pmini-thumb img { width: 100%; height: 100%; object-fit: cover; }

        .pmini-title {
            font-size: 0.8rem; font-weight: 600; color: var(--text-warm);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px;
        }
        .pmini-price {
            font-family: var(--font-head); font-size: 0.9rem; font-weight: 800;
            color: var(--gold); margin-bottom: 6px; letter-spacing: -0.01em;
        }
        .pmini-footer { display: flex; align-items: center; justify-content: space-between; }
        .pmini-status {
            font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
            padding: 2px 7px; border-radius: 10px; letter-spacing: 0.05em;
        }
        .status-avail { background: var(--teal-dim);   color: var(--teal); }
        .status-sold  { background: var(--surface2);   color: var(--text-dim); }

        .pmini-actions { display: flex; gap: 4px; }
        .pmini-btn {
            width: 26px; height: 26px; border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.65rem; text-decoration: none; transition: all 0.2s;
        }
        .pmini-edit { background: var(--surface2); color: var(--text-muted); border: 1px solid var(--border); }
        .pmini-edit:hover { color: var(--gold); border-color: rgba(240,165,0,0.2); }
        .pmini-view { background: var(--gold-dim); color: var(--gold); border: 1px solid rgba(240,165,0,0.15); }
        .pmini-view:hover { background: var(--gold); color: #0f0c08; }

        /* ── EMPTY STATE ── */
        .empty { text-align: center; padding: 36px 20px; color: var(--text-muted); }
        .empty-icon {
            width: 56px; height: 56px; background: var(--surface2);
            border: 1px solid var(--border); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: var(--text-dim); margin: 0 auto 12px;
        }
        .empty h4 { font-family: var(--font-head); font-size: 1rem; color: var(--text-warm); margin-bottom: 6px; letter-spacing: -0.01em; }
        .empty p  { font-size: 0.82rem; margin-bottom: 14px; }

        /* ── MISC ── */
        .sidebar-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.65); z-index: 99;
            backdrop-filter: blur(4px);
        }
        .sidebar-overlay.visible { display: block; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track  { background: var(--bg); }
        ::-webkit-scrollbar-thumb  { background: var(--surface3); border-radius: 3px; }

        @media(max-width:1200px) { .stat-grid { grid-template-columns: repeat(2,1fr); } }
        @media(max-width:1000px) { .content-grid { grid-template-columns: 1fr; } }
        @media(max-width:820px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .hamburger { display: flex; }
            .content { padding: 20px 16px; }
        }
        @media(max-width:540px) {
            .stat-grid { grid-template-columns: repeat(2,1fr); }
            .hero-actions { flex-direction: column; width: 100%; }
        }
    </style>
</head>
<body>  
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
        <span class="logo-sub">Marketplace</span>
    </div>
</a>
        <nav class="sidebar-nav">
            <div class="nav-label">Seller</div>
            <a href="seller-dashboard.php" class="nav-link active"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
            <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
            <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>Transactions</a>
            <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
            <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <div class="nav-label" style="margin-top:10px;">Account</div>
             <a href="apply-midman.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
            <a href="profile.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"   class="nav-link" style="color:var(--text-dim);margin-top:6px;">
                <span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($username, 0, 2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role">Seller</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Seller Dashboard</span>
            </div>
            <div class="online-dot">Online</div>
        </header>

        <div class="content">

            <!-- HERO BANNER -->
            <div class="hero">
                <div class="hero-line"></div>
                <div class="hero-inner">
                    <div>
                        <div class="hero-greeting">Welcome back</div>
                        <div class="hero-name"><?php echo htmlspecialchars($display_name); ?> <span>✦</span></div>
                        <div class="hero-sub">Manage your listings and track your sales performance.</div>
                    </div>
                    <div class="hero-actions">
                        <a href="add-product.php" class="btn btn-gold"><i class="fas fa-plus"></i> Add Product</a>
                        <a href="my-products.php" class="btn btn-ghost"><i class="fas fa-box-open"></i> My Products</a>
                    </div>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-icon si-gold"><i class="fas fa-box-open"></i></div>
                    <div>
                        <div class="stat-val"><?php echo $product_count; ?></div>
                        <div class="stat-lbl">Total Listings</div>
                        <div class="stat-sub"><i class="fas fa-store"></i> Active in marketplace</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon si-teal"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="stat-val"><?php echo $completed_count; ?></div>
                        <div class="stat-lbl">Completed Sales</div>
                        <div class="stat-sub"><i class="fas fa-chart-line"></i> Successfully closed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon si-blue"><i class="fas fa-coins"></i></div>
                    <div>
                        <div class="stat-val">$<?php echo number_format($sales_stats['total'], 2); ?></div>
                        <div class="stat-lbl">Total Revenue</div>
                        <div class="stat-sub"><i class="fas fa-money-bill-wave"></i> Gross earnings</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon si-orange"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="stat-val"><?php echo $pending_count; ?></div>
                        <div class="stat-lbl">Pending Orders</div>
                        <div class="stat-sub"><i class="fas fa-hourglass-half"></i> Awaiting delivery</div>
                    </div>
                </div>
            </div>

            <!-- CONTENT GRID -->
            <div class="content-grid">

                <!-- RECENT SALES -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title">
                            <div class="panel-title-icon pti-teal"><i class="fas fa-bag-shopping"></i></div>
                            Recent Sales
                        </div>
                        <a href="my-sales.php" class="btn btn-ghost btn-sm">View All</a>
                    </div>
                    <div class="panel-body">
                        <?php if(mysqli_num_rows($recent_sales) > 0):
                            while($sale = mysqli_fetch_assoc($recent_sales)):
                                $sc = $status_cfg[$sale['status']] ?? ['label'=>ucfirst($sale['status']),'color'=>'var(--text-muted)','bg'=>'var(--surface2)'];
                        ?>
                        <div class="sale-item">
                            <div class="sale-thumb">
                                <?php if($sale['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($sale['image_path']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-gamepad"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div class="sale-title"><?php echo htmlspecialchars($sale['product_title']); ?></div>
                                <div class="sale-meta">
                                    <?php echo htmlspecialchars($sale['buyer_name']); ?> &middot;
                                    <?php echo date('M d, Y', strtotime($sale['created_at'])); ?>
                                </div>
                            </div>
                            <div class="sale-right">
                                <div class="sale-amount">$<?php echo number_format($sale['amount'], 2); ?></div>
                                <div class="sale-badge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;">
                                    <?php echo $sc['label']; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; else: ?>
                        <div class="empty">
                            <div class="empty-icon"><i class="fas fa-bag-shopping"></i></div>
                            <h4>No sales yet</h4>
                            <p>Add products to start selling!</p>
                            <a href="add-product.php" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> Add Product</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MY PRODUCTS -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="panel-title">
                            <div class="panel-title-icon pti-gold"><i class="fas fa-box-open"></i></div>
                            My Products
                        </div>
                        <a href="my-products.php" class="btn btn-ghost btn-sm">Manage All</a>
                    </div>
                    <div class="panel-body">
                        <?php if(mysqli_num_rows($my_products) > 0): ?>
                        <div class="prod-mini-grid">
                            <?php while($p = mysqli_fetch_assoc($my_products)): ?>
                            <div class="prod-mini-card">
                                <div class="pmini-thumb">
                                    <?php if($p['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-gamepad"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="pmini-title"><?php echo htmlspecialchars($p['title']); ?></div>
                                <div class="pmini-price">$<?php echo number_format($p['price'], 2); ?></div>
                                <div class="pmini-footer">
                                    <span class="pmini-status <?php echo $p['status']=='available' ? 'status-avail' : 'status-sold'; ?>">
                                        <?php echo ucfirst($p['status']); ?>
                                    </span>
                                    <div class="pmini-actions">
                                        <a href="edit-product.php?id=<?php echo $p['id']; ?>" class="pmini-btn pmini-edit" title="Edit"><i class="fas fa-pen"></i></a>
                                        <a href="product-detail.php?id=<?php echo $p['id']; ?>" class="pmini-btn pmini-view" title="View"><i class="fas fa-eye"></i></a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty">
                            <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                            <h4>No products listed</h4>
                            <p>Start by adding your first product!</p>
                            <a href="add-product.php" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> Add Product</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
    const ham = document.getElementById('hamburger');
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('overlay');
    ham.addEventListener('click', () => { sb.classList.toggle('open'); ov.classList.toggle('visible'); });
    ov.addEventListener('click',  () => { sb.classList.remove('open'); ov.classList.remove('visible'); });
</script>
</body>
</html>