<?php
require_once '../includes/config.php';
require_once '../includes/verification-functions.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

$s = [];
$s['total_users']        = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users"))['c'];
$s['buyers']             = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role='buyer'"))['c'];
$s['sellers']            = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role='seller'"))['c'];
$s['midmen']             = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role='midman'"))['c'];
$s['pending_kyc']        = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM verification_requests WHERE status='pending'"))['c'];
$s['pending_apps']       = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM midman_applications WHERE status='pending'"))['c'];
$s['total_transactions'] = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM transactions"))['c'];
$s['total_revenue']      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(service_fee),0) t FROM transactions WHERE status='completed'"))['t'];
$s['open_disputes']      = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];

$verif_stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(verification_level='verified') as verified,
     SUM(verification_level='pending') as pending,
     SUM(verification_level='rejected') as rejected
     FROM users"));

$recent_users        = mysqli_query($conn,"SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_transactions = mysqli_query($conn,
    "SELECT t.*, p.title as product_title, b.username as buyer_name, s.username as seller_name
     FROM transactions t
     JOIN products p ON t.product_id=p.id
     JOIN users b ON t.buyer_id=b.id
     JOIN users s ON t.seller_id=s.id
     ORDER BY t.created_at DESC LIMIT 5");
$recent_kyc = mysqli_query($conn,
    "SELECT vr.*, u.username, u.full_name
     FROM verification_requests vr
     JOIN users u ON vr.user_id=u.id
     WHERE vr.status='pending'
     ORDER BY vr.submitted_at DESC LIMIT 3");

$has_alerts   = $s['pending_kyc']>0 || $s['pending_apps']>0 || $s['open_disputes']>0;
$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];

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
    <title>Admin Dashboard — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        :root {
            /* ── warm dark base (same system) ── */
            --bg:         #0f0c08;
            --surface:    #0f0b07;
            --surface2:   #201a13;
            --surface3:   #271f16;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.24);

            /* ── admin red accent (replaces gold entirely) ── */
            --admin:      #e03535;
            --admin-lt:   #ff5a5a;
            --admin-dim:  rgba(224,53,53,0.12);
            --admin-glow: rgba(224,53,53,0.28);
            --admin-fg:   #ffffff;

            /* ── semantic colors ── */
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

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR — red admin accent ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(180,30,30,0.1) 0%,transparent 65%); pointer-events:none; }
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
        .logo-text { font-family:var(--font-head); font-weight:700; font-size:1.1rem; color:var(--text); line-height:1.2; letter-spacing:-0.01em; }
        .logo-sub  { font-size:0.65rem; color:var(--admin); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--admin); background:var(--admin-dim); border:1px solid rgba(224,53,53,0.14); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--admin); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.2); }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--admin),#8b1a1a); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:white; flex-shrink:0; box-shadow:0 0 10px var(--admin-glow); }
        .pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .pill-role { font-size:0.68rem; color:var(--admin); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .topbar-right { display:flex; align-items:center; gap:10px; }
        .notif-btn { width:36px; height:36px; border-radius:var(--radius-sm); background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--text-muted); cursor:pointer; position:relative; transition:all 0.2s; }
        .notif-btn:hover { border-color:rgba(224,53,53,0.25); color:var(--admin); background:var(--admin-dim); }
        .notif-dot { position:absolute; top:7px; right:7px; width:7px; height:7px; background:var(--admin); border-radius:50%; border:2px solid var(--surface); box-shadow:0 0 6px var(--admin-glow); }
        .content { padding:28px 32px; flex:1; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);  color:#ff7090;     border:1px solid rgba(255,77,109,0.22); }

        /* ── HERO ── */
        .hero {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:28px 32px; margin-bottom:22px;
            position:relative; overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
        }
        .hero::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.35),transparent); }
        .hero-glow { position:absolute; top:-60px; right:-60px; width:240px; height:240px; background:radial-gradient(circle,rgba(224,53,53,0.18) 0%,transparent 65%); pointer-events:none; }
        .hero-inner { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; position:relative; z-index:1; }
        .hero-greeting { font-size:0.78rem; font-weight:700; color:var(--admin); text-transform:uppercase; letter-spacing:0.14em; margin-bottom:5px; }
        .hero-name { font-family:var(--font-head); font-size:1.8rem; font-weight:800; color:var(--text); margin-bottom:5px; letter-spacing:-0.01em; }
        .hero-sub  { font-size:0.875rem; color:var(--text-muted); line-height:1.6; }
        .admin-badge { display:flex; align-items:center; gap:8px; padding:10px 18px; background:var(--admin-dim); border:1px solid rgba(224,53,53,0.22); border-radius:var(--radius-sm); flex-shrink:0; }
        .admin-badge i { color:var(--admin); }
        .admin-badge span { font-family:var(--font-head); font-size:0.9rem; font-weight:700; color:var(--admin); letter-spacing:-0.01em; }

        /* ── STAT GRID ── */
        .stat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:20px;
            display:flex; align-items:flex-start; gap:12px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            transition:border-color 0.25s, transform 0.25s;
            position:relative; overflow:hidden;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.25s; }
        .stat-card:hover { border-color:var(--border2); transform:translateY(-3px); }
        .stat-card:hover::before { opacity:1; }
        .stat-card:nth-child(1) { animation-delay:.05s; } .stat-card:nth-child(1)::before { background:linear-gradient(90deg,var(--admin),transparent); }
        .stat-card:nth-child(2) { animation-delay:.08s; } .stat-card:nth-child(2)::before { background:linear-gradient(90deg,var(--orange),transparent); }
        .stat-card:nth-child(3) { animation-delay:.11s; } .stat-card:nth-child(3)::before { background:linear-gradient(90deg,var(--admin),transparent); }
        .stat-card:nth-child(4) { animation-delay:.14s; } .stat-card:nth-child(4)::before { background:linear-gradient(90deg,var(--blue),transparent); }
        .stat-card:nth-child(5) { animation-delay:.17s; } .stat-card:nth-child(5)::before { background:linear-gradient(90deg,var(--teal),transparent); }
        .stat-card:nth-child(6) { animation-delay:.20s; } .stat-card:nth-child(6)::before { background:linear-gradient(90deg,var(--red),transparent); }

        .sc-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; border:1px solid transparent; }
        .si-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .si-red    { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.14); }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .si-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }

        .sc-val { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }
        .sc-sub { font-size:0.68rem; color:var(--text-dim); margin-top:5px; display:flex; align-items:center; gap:3px; flex-wrap:wrap; }
        .sc-sub.warn   { color:var(--orange); }
        .sc-sub.alert-c { color:var(--red); }

        /* ── PANELS ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden; margin-bottom:20px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.15),transparent); z-index:1; }
        .panel:nth-of-type(1){animation-delay:.22s;} .panel:nth-of-type(2){animation-delay:.27s;} .panel:nth-of-type(3){animation-delay:.32s;} .panel:nth-of-type(4){animation-delay:.37s;}

        .panel-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1px solid var(--border); }
        .panel-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; letter-spacing:-0.01em; }
        .pti { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; border:1px solid transparent; }
        .pti-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .pti-red    { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.14); }
        .pti-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .pti-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .pti-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .count-chip { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:10px; }
        .view-all { font-size:0.76rem; color:var(--admin); text-decoration:none; font-weight:600; display:flex; align-items:center; gap:4px; transition:opacity 0.2s; }
        .view-all:hover { opacity:0.75; }

        /* ── QUICK ACTIONS ── */
        .qa-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; padding:16px 20px; }
        .qa-card { display:flex; flex-direction:column; align-items:center; text-align:center; gap:8px; padding:18px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); text-decoration:none; transition:all 0.22s; position:relative; }
        .qa-card:hover { border-color:var(--admin); background:var(--admin-dim); }
        .qa-card:hover .qa-icon { background:var(--admin); color:white; }
        .qa-icon { width:40px; height:40px; border-radius:10px; background:var(--admin-dim); color:var(--admin); border:1px solid rgba(224,53,53,0.14); display:flex; align-items:center; justify-content:center; font-size:0.95rem; transition:all 0.22s; }
        .qa-label { font-family:var(--font-head); font-size:0.9rem; font-weight:700; color:var(--text-warm); letter-spacing:-0.01em; }
        .qa-sub { font-size:0.7rem; color:var(--text-dim); }
        .qa-badge { position:absolute; top:8px; right:8px; background:var(--red); color:white; font-size:0.58rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.2); }

        /* ── KYC ALERT STRIP ── */
        .kyc-strip {
            background:rgba(224,53,53,0.05); border:1px solid rgba(224,53,53,0.2);
            border-radius:var(--radius); overflow:hidden; margin-bottom:20px;
            opacity:0; animation:fadeUp 0.45s ease 0.22s forwards;
            position:relative;
        }
        .kyc-strip::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.3),transparent); }
        .kyc-strip-head { display:flex; align-items:center; justify-content:space-between; padding:13px 20px; border-bottom:1px solid rgba(224,53,53,0.12); background:rgba(224,53,53,0.04); }
        .kyc-strip-title { font-family:var(--font-head); font-size:0.92rem; font-weight:700; color:var(--admin); display:flex; align-items:center; gap:8px; letter-spacing:-0.01em; }
        .kyc-strip-badge { background:var(--admin-dim); color:var(--admin); font-size:0.62rem; font-weight:800; padding:3px 9px; border-radius:10px; border:1px solid rgba(224,53,53,0.2); }

        /* ── TABLES ── */
        .tx-table { width:100%; border-collapse:collapse; }
        .tx-table thead tr { border-bottom:1px solid var(--border); }
        .tx-table th { padding:10px 16px; font-size:0.67rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .tx-table th:last-child { text-align:right; }
        .tx-table td { padding:12px 16px; border-bottom:1px solid var(--border); font-size:0.875rem; vertical-align:middle; }
        .tx-table tr:last-child td { border-bottom:none; }
        .tx-table tbody tr { transition:background 0.18s; }
        .tx-table tbody tr:hover td { background:rgba(224,53,53,0.03); }

        .u-ava { width:32px; height:32px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.68rem; flex-shrink:0; }
        .u-name { font-weight:600; color:var(--text-warm); font-size:0.875rem; }
        .u-sub  { font-size:0.7rem; color:var(--text-dim); }

        .role-badge { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 8px; border-radius:10px; display:inline-flex; align-items:center; gap:3px; border:1px solid transparent; }
        .rb-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .rb-midman { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .rb-seller { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .rb-buyer  { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }

        .kyc-badge { font-size:0.62rem; font-weight:700; padding:3px 8px; border-radius:10px; display:inline-flex; align-items:center; gap:3px; border:1px solid transparent; }
        .kb-verified   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .kb-pending    { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .kb-unverified { background:var(--surface2);   color:var(--text-dim); border-color:var(--border2); }

        .sbadge    { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 9px; border-radius:20px; display:inline-flex; align-items:center; gap:4px; }
        .p-name    { font-weight:600; color:var(--text-warm); font-size:0.875rem; }
        .p-sub     { font-size:0.7rem; color:var(--text-dim); margin-top:1px; }
        .amount-val { font-family:var(--font-head); font-weight:800; font-size:0.95rem; color:var(--admin); letter-spacing:-0.01em; }
        .p-date    { font-size:0.74rem; color:var(--text-dim); }

        .view-btn { width:30px; height:30px; border-radius:7px; background:var(--admin-dim); color:var(--admin); border:1px solid rgba(224,53,53,0.15); display:inline-flex; align-items:center; justify-content:center; font-size:0.72rem; text-decoration:none; transition:all 0.2s; float:right; }
        .view-btn:hover { background:var(--admin); color:white; }

        /* ── TWO COL ── */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1200px) { .stat-grid{grid-template-columns:repeat(2,1fr);} .qa-grid{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:900px)  { .two-col{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:540px)  { .stat-grid{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <a href="dashboard.php" class="sidebar-logo">
        <div class="logo-icon">
            <img src="../images/logowhite.png" alt="Trusted Midman">
        </div>
        <div class="logo-text">
            Trusted Midman
            <span class="logo-sub">Admin Panel</span>
        </div>
    </a>
        <nav class="sidebar-nav">
            <div class="nav-label">Overview</div>
            <a href="dashboard.php"     class="nav-link active"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="charts.php"        class="nav-link"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports</a>

            <div class="nav-label" style="margin-top:10px;">Management</div>
            <a href="users.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-users"></i></span> Users</a>
            <a href="transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-arrows-left-right"></i></span> Transactions</a>
            <a href="verifications.php" class="nav-link">
                <span class="nav-icon"><i class="fas fa-id-card"></i></span> KYC Verifications
                <?php if($s['pending_kyc']>0): ?><span class="nav-badge"><?php echo $s['pending_kyc']; ?></span><?php endif; ?>
            </a>
            <a href="applications.php"  class="nav-link">
                <span class="nav-icon"><i class="fas fa-user-check"></i></span> Midman Apps
                <?php if($s['pending_apps']>0): ?><span class="nav-badge"><?php echo $s['pending_apps']; ?></span><?php endif; ?>
            </a>
            <a href="disputes.php"      class="nav-link">
                <span class="nav-icon"><i class="fas fa-gavel"></i></span> Disputes
                <?php if($s['open_disputes']>0): ?><span class="nav-badge"><?php echo $s['open_disputes']; ?></span><?php endif; ?>
            </a>
            

            <div class="nav-label" style="margin-top:10px;">System</div>
            <a href="settings.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-gear"></i></span> Settings</a>
            <a href="../profile.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> My Profile</a>
            <a href="../logout.php"     class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username'],0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Admin Dashboard</span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn">
                    <i class="fas fa-bell" style="font-size:0.9rem;"></i>
                    <?php if($has_alerts): ?><span class="notif-dot"></span><?php endif; ?>
                </div>
            </div>
        </header>

        <div class="content">

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- HERO -->
            <div class="hero">
                <div class="hero-glow"></div>
                <div class="hero-inner">
                    <div>
                        <div class="hero-greeting">Welcome back</div>
                        <div class="hero-name"><?php echo htmlspecialchars($display_name); ?></div>
                        <div class="hero-sub">
                            <?php
                            $issues = [];
                            if($s['pending_kyc']>0)   $issues[] = "<strong style='color:var(--admin);'>{$s['pending_kyc']}</strong> pending KYC";
                            if($s['pending_apps']>0)  $issues[] = "<strong style='color:var(--orange);'>{$s['pending_apps']}</strong> midman applications";
                            if($s['open_disputes']>0) $issues[] = "<strong style='color:var(--red);'>{$s['open_disputes']}</strong> open disputes";
                            echo count($issues) ? implode(' · ', $issues).' awaiting action.' : 'All clear — no items need attention right now.';
                            ?>
                        </div>
                    </div>
                    <div class="admin-badge">
                        <i class="fas fa-crown"></i>
                        <span>Administrator</span>
                    </div>
                </div>
            </div>

            <!-- STAT GRID -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="sc-icon si-admin"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $s['total_users']; ?></div>
                        <div class="sc-lbl">Total Users</div>
                        <div class="sc-sub">
                            <span style="color:var(--blue);"><?php echo $s['buyers']; ?> buyers</span>&nbsp;·&nbsp;
                            <span style="color:var(--teal);"><?php echo $s['sellers']; ?> sellers</span>&nbsp;·&nbsp;
                            <span style="color:var(--purple);"><?php echo $s['midmen']; ?> midmen</span>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-orange"><i class="fas fa-id-card"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $s['pending_kyc']; ?></div>
                        <div class="sc-lbl">Pending KYC</div>
                        <div class="sc-sub <?php echo $s['pending_kyc']>0?'warn':''; ?>">
                            <i class="fas fa-clock"></i> Awaiting verification
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-admin"><i class="fas fa-user-check"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $s['pending_apps']; ?></div>
                        <div class="sc-lbl">Midman Applications</div>
                        <div class="sc-sub <?php echo $s['pending_apps']>0?'warn':''; ?>">
                            <i class="fas fa-handshake"></i> Need review
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-blue"><i class="fas fa-arrows-left-right"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $s['total_transactions']; ?></div>
                        <div class="sc-lbl">Total Transactions</div>
                        <div class="sc-sub"><i class="fas fa-receipt"></i> All time</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-coins"></i></div>
                    <div>
                        <div class="sc-val">$<?php echo number_format($s['total_revenue'],2); ?></div>
                        <div class="sc-lbl">Platform Revenue</div>
                        <div class="sc-sub"><i class="fas fa-circle-check"></i> Completed fees</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-red"><i class="fas fa-gavel"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $s['open_disputes']; ?></div>
                        <div class="sc-lbl">Open Disputes</div>
                        <div class="sc-sub <?php echo $s['open_disputes']>0?'alert-c':''; ?>">
                            <i class="fas fa-<?php echo $s['open_disputes']>0?'triangle-exclamation':'circle-check'; ?>"></i>
                            <?php echo $s['open_disputes']>0?'Needs attention':'All resolved'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title"><div class="pti pti-admin"><i class="fas fa-bolt"></i></div> Quick Actions</div>
                </div>
                <div class="qa-grid">
                    <a href="verifications.php" class="qa-card">
                        <?php if($s['pending_kyc']>0): ?><span class="qa-badge"><?php echo $s['pending_kyc']; ?></span><?php endif; ?>
                        <div class="qa-icon"><i class="fas fa-id-card"></i></div>
                        <div class="qa-label">Review KYC</div>
                        <div class="qa-sub">Verify user identities</div>
                    </a>
                    <a href="applications.php" class="qa-card">
                        <?php if($s['pending_apps']>0): ?><span class="qa-badge"><?php echo $s['pending_apps']; ?></span><?php endif; ?>
                        <div class="qa-icon"><i class="fas fa-user-check"></i></div>
                        <div class="qa-label">Midman Apps</div>
                        <div class="qa-sub">Process applications</div>
                    </a>
                    <a href="disputes.php" class="qa-card">
                        <?php if($s['open_disputes']>0): ?><span class="qa-badge"><?php echo $s['open_disputes']; ?></span><?php endif; ?>
                        <div class="qa-icon"><i class="fas fa-gavel"></i></div>
                        <div class="qa-label">Handle Disputes</div>
                        <div class="qa-sub">Resolve conflicts</div>
                    </a>
                    <a href="users.php" class="qa-card">
                        <div class="qa-icon"><i class="fas fa-users"></i></div>
                        <div class="qa-label">Manage Users</div>
                        <div class="qa-sub">View all accounts</div>
                    </a>
                </div>
            </div>

            <!-- KYC ALERT STRIP -->
            <?php if(mysqli_num_rows($recent_kyc) > 0): ?>
            <div class="kyc-strip">
                <div class="kyc-strip-head">
                    <div class="kyc-strip-title"><i class="fas fa-triangle-exclamation"></i> Pending KYC Verifications</div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span class="kyc-strip-badge"><?php echo $s['pending_kyc']; ?> Pending</span>
                        <a href="verifications.php" class="view-all"><i class="fas fa-arrow-right" style="font-size:0.65rem;"></i> View All</a>
                    </div>
                </div>
                <table class="tx-table">
                    <thead><tr><th>User</th><th>Document Type</th><th>Submitted</th><th style="text-align:right;">Action</th></tr></thead>
                    <tbody>
                    <?php while($kyc = mysqli_fetch_assoc($recent_kyc)): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="u-ava" style="background:var(--admin-dim);color:var(--admin);border:1px solid rgba(224,53,53,0.14);"><?php echo strtoupper(substr($kyc['username'],0,2)); ?></div>
                                <div>
                                    <div class="u-name"><?php echo htmlspecialchars($kyc['full_name']); ?></div>
                                    <div class="u-sub">@<?php echo htmlspecialchars($kyc['username']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span style="font-size:0.84rem;color:var(--text-muted);"><?php echo ucfirst(str_replace('_',' ',$kyc['document_type'])); ?></span></td>
                        <td><span class="p-date"><?php echo date('M d, Y',strtotime($kyc['submitted_at'])); ?></span></td>
                        <td><a href="verifications.php?id=<?php echo $kyc['id']; ?>" class="view-btn"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- RECENT USERS + TRANSACTIONS -->
            <div class="two-col">

                <!-- Recent Users -->
                <div class="panel" style="margin-bottom:0;">
                    <div class="panel-head">
                        <div class="panel-title"><div class="pti pti-blue"><i class="fas fa-users"></i></div> Recent Users</div>
                        <a href="users.php" class="view-all"><i class="fas fa-arrow-right" style="font-size:0.65rem;"></i> View All</a>
                    </div>
                    <table class="tx-table">
                        <thead><tr><th>User</th><th>Role</th><th>KYC</th><th style="text-align:right;">Action</th></tr></thead>
                        <tbody>
                        <?php while($u = mysqli_fetch_assoc($recent_users)):
                            $rb = match($u['role']) { 'admin'=>'rb-admin','midman'=>'rb-midman','seller'=>'rb-seller',default=>'rb-buyer' };
                            $ri = match($u['role']) { 'admin'=>'fa-crown','midman'=>'fa-handshake','seller'=>'fa-store',default=>'fa-user' };
                            $vl = $u['verification_level'] ?? 'unverified';
                            $kc = match($vl) { 'verified'=>'kb-verified','pending'=>'kb-pending',default=>'kb-unverified' };
                            $ki = match($vl) { 'verified'=>'fa-circle-check','pending'=>'fa-clock',default=>'fa-circle-xmark' };
                        ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:9px;">
                                    <div class="u-ava" style="background:var(--surface2);color:var(--text-muted);border:1px solid var(--border2);"><?php echo strtoupper(substr($u['username'],0,2)); ?></div>
                                    <div>
                                        <div class="u-name"><?php echo htmlspecialchars($u['username']); ?></div>
                                        <div class="u-sub"><?php echo htmlspecialchars($u['full_name']??''); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="role-badge <?php echo $rb; ?>"><i class="fas <?php echo $ri; ?>" style="font-size:0.52rem;"></i> <?php echo ucfirst($u['role']); ?></span></td>
                            <td><span class="kyc-badge <?php echo $kc; ?>"><i class="fas <?php echo $ki; ?>" style="font-size:0.52rem;"></i> <?php echo ucfirst($vl); ?></span></td>
                            <td><a href="user-detail.php?id=<?php echo $u['id']; ?>" class="view-btn"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Recent Transactions -->
                <div class="panel" style="margin-bottom:0;">
                    <div class="panel-head">
                        <div class="panel-title"><div class="pti pti-teal"><i class="fas fa-arrows-left-right"></i></div> Recent Transactions</div>
                        <a href="transactions.php" class="view-all"><i class="fas fa-arrow-right" style="font-size:0.65rem;"></i> View All</a>
                    </div>
                    <table class="tx-table">
                        <thead><tr><th>Product</th><th>Amount</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                        <tbody>
                        <?php while($t = mysqli_fetch_assoc($recent_transactions)):
                            $sc = $status_cfg[$t['status']] ?? ['label'=>ucfirst($t['status']),'color'=>'var(--text-muted)','bg'=>'var(--surface2)'];
                        ?>
                        <tr>
                            <td>
                                <div class="p-name"><?php echo htmlspecialchars(substr($t['product_title'],0,22)).(strlen($t['product_title'])>22?'…':''); ?></div>
                                <div class="p-sub"><?php echo htmlspecialchars($t['buyer_name']); ?> → <?php echo htmlspecialchars($t['seller_name']); ?></div>
                            </td>
                            <td><div class="amount-val">$<?php echo number_format($t['amount'],2); ?></div></td>
                            <td>
                                <span class="sbadge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;">
                                    <?php echo $sc['label']; ?>
                                </span>
                            </td>
                            <td><a href="../transaction-detail.php?id=<?php echo $t['id']; ?>" class="view-btn"><i class="fas fa-eye"></i></a></td>
                        </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
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