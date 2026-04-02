<?php
require_once 'includes/config.php';

$pending_tx_count = 0;
if($_SESSION['role'] === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($_SESSION['role'] === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header('Location: login.php'); exit();
}

$seller_id = $_SESSION['user_id'];

$earnings = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total_transactions,
     COALESCE(SUM(amount),0) as total_revenue,
     COALESCE(SUM(service_fee),0) as total_fees_paid,
     COALESCE(AVG(amount),0) as average_sale,
     COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) as completed_revenue,
     COALESCE(SUM(CASE WHEN status='pending'   THEN amount ELSE 0 END),0) as pending_revenue,
     COUNT(CASE WHEN status='completed' THEN 1 END) as completed_count,
     COUNT(CASE WHEN status='pending'   THEN 1 END) as pending_count
     FROM transactions WHERE seller_id=$seller_id"));

$monthly_res = mysqli_query($conn,
    "SELECT DATE_FORMAT(created_at,'%Y-%m') as month,
     COUNT(*) as transaction_count,
     COALESCE(SUM(amount),0) as monthly_revenue
     FROM transactions
     WHERE seller_id=$seller_id AND status='completed'
     GROUP BY DATE_FORMAT(created_at,'%Y-%m')
     ORDER BY month DESC LIMIT 6");

$months_data = [];
while($r = mysqli_fetch_assoc($monthly_res)) $months_data[] = $r;
$months_data = array_reverse($months_data);
$max_rev = max(array_column($months_data,'monthly_revenue') ?: [0]);

$payment_methods = mysqli_query($conn,
    "SELECT COALESCE(payment_method,'Not specified') as method,
     COUNT(*) as count, COALESCE(SUM(amount),0) as total
     FROM transactions WHERE seller_id=$seller_id GROUP BY payment_method");

$recent = mysqli_query($conn,
    "SELECT t.*, p.title as product_title, p.game_name, p.image_path, u.username as buyer_name
     FROM transactions t
     JOIN products p ON t.product_id=p.id
     JOIN users u ON t.buyer_id=u.id
     WHERE t.seller_id=$seller_id ORDER BY t.created_at DESC LIMIT 10");

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
    <title>My Earnings — Trusted Midman</title>
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

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

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
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#c47d00); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px rgba(240,165,0,0.2); }
        .pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .pill-role { font-size:0.68rem; color:var(--gold); text-transform:uppercase; letter-spacing:0.09em; }
         .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }


        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .content { padding:28px 32px; flex:1; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── PAGE HEAD ── */
        .page-head { margin-bottom:22px; opacity:0; animation:fadeUp 0.4s ease forwards; }
        .page-head h1 { font-family:var(--font-head); font-size:1.8rem; font-weight:800; color:var(--text); display:flex; align-items:center; gap:12px; letter-spacing:-0.01em; }
        .ph-icon { width:40px; height:40px; background:var(--gold-dim); border:1px solid rgba(240,165,0,0.14); border-radius:11px; display:flex; align-items:center; justify-content:center; color:var(--gold); font-size:1rem; }
        .page-head p { font-size:0.84rem; color:var(--text-muted); margin-top:6px; }

        /* ── STAT GRID ── */
        .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:20px;
            position:relative; overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            transition:border-color 0.25s, transform 0.25s;
        }
        /* colored top bar */
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; }
        .stat-card.sc-gold::before   { background:linear-gradient(90deg,var(--gold),transparent); }
        .stat-card.sc-teal::before   { background:linear-gradient(90deg,var(--teal),transparent); }
        .stat-card.sc-blue::before   { background:linear-gradient(90deg,var(--blue),transparent); }
        .stat-card.sc-purple::before { background:linear-gradient(90deg,var(--purple),transparent); }
        .stat-card:hover { border-color:var(--border2); transform:translateY(-3px); box-shadow:0 12px 32px rgba(0,0,0,0.3); }
        .stat-card:nth-child(1){animation-delay:.05s;} .stat-card:nth-child(2){animation-delay:.1s;} .stat-card:nth-child(3){animation-delay:.15s;} .stat-card:nth-child(4){animation-delay:.2s;}

        .sc-icon { width:42px; height:42px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1rem; margin-bottom:14px; border:1px solid transparent; }
        .sci-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .sci-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .sci-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .sci-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }

        .sc-val { font-family:var(--font-head); font-size:1.7rem; font-weight:800; color:var(--text); line-height:1; margin-bottom:4px; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.1em; margin-bottom:9px; }
        .sc-sub { font-size:0.72rem; color:var(--text-dim); display:flex; align-items:center; gap:5px; }
        .sc-sub i { font-size:0.6rem; }

        /* ── CHARTS GRID ── */
        .charts-grid { display:grid; grid-template-columns:1fr 320px; gap:18px; margin-bottom:22px; }

        .chart-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .chart-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.15),transparent); z-index:1; }
        .chart-panel:nth-child(1){animation-delay:.25s;} .chart-panel:nth-child(2){animation-delay:.32s;}

        .chart-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1px solid var(--border); }
        .chart-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:9px; letter-spacing:-0.01em; }
        .cti { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; border:1px solid transparent; }
        .cti-gold { background:var(--gold-dim); color:var(--gold); border-color:rgba(240,165,0,0.14); }
        .cti-teal { background:var(--teal-dim); color:var(--teal); border-color:rgba(0,212,170,0.14); }
        .chart-sub { font-size:0.72rem; color:var(--text-dim); }
        .chart-body { padding:20px 20px 16px; }

        /* ── BAR CHART (improved) ── */
        .bar-wrap { display:flex; align-items:flex-end; gap:12px; height:180px; }
        .bar-col  { flex:1; display:flex; flex-direction:column; align-items:center; height:100%; justify-content:flex-end; position:relative; }
        .bar-fill {
            width:100%; max-width:50px; border-radius:6px 6px 0 0;
            background:linear-gradient(180deg, var(--gold) 0%, #d48500 100%);
            min-height:8px; transition:height 0.6s cubic-bezier(.34,1.56,.64,1);
            position:relative; cursor:pointer;
            box-shadow:0 0 0 1px rgba(240,165,0,0.3), inset 0 1px 0 rgba(255,255,255,0.1);
        }
        .bar-fill:hover { background:linear-gradient(180deg, var(--gold-lt) 0%, var(--gold) 100%); transform:scaleX(1.02); box-shadow:0 0 8px var(--gold-glow); }
        .bar-fill:hover::after {
            content:attr(data-val);
            position:absolute; top:-32px; left:50%; transform:translateX(-50%);
            background:var(--surface2); border:1px solid var(--border2);
            color:var(--text-warm); font-size:0.7rem; font-weight:700;
            padding:4px 10px; border-radius:8px; white-space:nowrap;
            box-shadow:0 4px 12px rgba(0,0,0,0.3); z-index:10;
            pointer-events:none;
        }
        .bar-lbl { font-size:0.65rem; color:var(--text-dim); margin-top:8px; text-align:center; white-space:nowrap; font-weight:500; }
        .no-data { height:160px; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:10px; color:var(--text-dim); }
        .no-data i { font-size:1.8rem; opacity:0.4; }
        .no-data span { font-size:0.85rem; }

        /* ── PAYMENT METHODS ── */
        .methods-list { display:flex; flex-direction:column; }
        .method-item { display:flex; align-items:center; justify-content:space-between; padding:13px 20px; border-bottom:1px solid var(--border); transition:background 0.2s; }
        .method-item:last-child { border-bottom:none; }
        .method-item:hover { background:var(--surface2); }
        .method-left { display:flex; align-items:center; gap:10px; }
        .method-ico { width:32px; height:32px; border-radius:8px; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.14); display:flex; align-items:center; justify-content:center; font-size:0.8rem; flex-shrink:0; }
        .method-name { font-size:0.875rem; font-weight:600; color:var(--text-warm); }
        .method-cnt  { font-size:0.7rem; color:var(--text-dim); }
        .method-total { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--gold); letter-spacing:-0.01em; }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; white-space:nowrap; letter-spacing:0.01em; }
        .btn-gold  { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 3px 14px var(--gold-glow); }
        .btn-gold:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); }
        .btn-sm { padding:7px 13px; font-size:0.82rem; }

        /* ── TABLE PANEL ── */
        .table-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease 0.38s forwards;
            position:relative;
        }
        .table-panel::before { content:''; display:block; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.18),transparent); }
        .table-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1px solid var(--border); }
        .table-head-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:9px; letter-spacing:-0.01em; }

        .earnings-table { width:100%; border-collapse:collapse; }
        .earnings-table thead tr { border-bottom:1px solid var(--border); }
        .earnings-table th { padding:11px 16px; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .earnings-table th:last-child { text-align:right; }
        .earnings-table td { padding:13px 16px; border-bottom:1px solid var(--border); font-size:0.875rem; vertical-align:middle; }
        .earnings-table tr:last-child td { border-bottom:none; }
        .earnings-table tbody tr { transition:background 0.18s; }
        .earnings-table tbody tr:hover td { background:rgba(255,180,60,0.03); }

        /* ── TABLE CELLS ── */
        .p-info  { display:flex; align-items:center; gap:11px; }
        .p-thumb { width:44px; height:36px; border-radius:8px; background:var(--surface2); border:1px solid var(--border); overflow:hidden; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1rem; flex-shrink:0; }
        .p-thumb img { width:100%; height:100%; object-fit:cover; }
        .p-name  { font-weight:600; color:var(--text-warm); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px; }
        .p-game  { font-size:0.68rem; font-weight:700; background:var(--gold-dim); color:var(--gold); padding:2px 7px; border-radius:10px; display:inline-block; margin-top:3px; border:1px solid rgba(240,165,0,0.14); }
        .buyer-ava { width:26px; height:26px; border-radius:50%; background:var(--blue-dim); color:var(--blue); border:1px solid rgba(78,159,255,0.15); font-family:var(--font-head); font-weight:700; font-size:0.6rem; display:inline-flex; align-items:center; justify-content:center; margin-right:5px; vertical-align:middle; }
        .amt     { font-family:var(--font-head); font-weight:800; font-size:0.95rem; color:var(--gold); letter-spacing:-0.01em; }
        .amt-net { font-family:var(--font-head); font-weight:800; font-size:0.95rem; color:var(--teal); letter-spacing:-0.01em; }
        .fee     { font-size:0.75rem; color:var(--red); }
        .sbadge  { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 9px; border-radius:20px; letter-spacing:0.05em; }
        .p-date  { font-size:0.78rem; color:var(--text-dim); }
        .view-btn { width:30px; height:30px; border-radius:7px; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.15); display:inline-flex; align-items:center; justify-content:center; font-size:0.72rem; text-decoration:none; transition:all 0.2s; float:right; }
        .view-btn:hover { background:var(--gold); color:#0f0c08; }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:56px 20px; }
        .empty-icon { width:68px; height:68px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.7rem; color:var(--text-dim); margin:0 auto 14px; }
        .empty h3 { font-family:var(--font-head); font-size:1.1rem; color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.84rem; color:var(--text-muted); margin-bottom:16px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1200px) { .stat-grid{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:1000px) { .charts-grid{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:640px)  { .stat-grid{grid-template-columns:1fr 1fr;} .earnings-table th:nth-child(4),.earnings-table td:nth-child(4){display:none;} }
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
            <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
            <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
            <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>Transactions</a>
            <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
            <a href="seller-earnings.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <div class="nav-label" style="margin-top:10px;">Account</div>
             <a href="apply-midman.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username'],0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($_SESSION['full_name']??$_SESSION['username']); ?></div>
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
                <span class="page-title">My Earnings</span>
            </div>
            <a href="my-sales.php" class="btn btn-ghost btn-sm"><i class="fas fa-chart-line"></i> View Sales</a>
        </header>

        <div class="content">

            <!-- PAGE HEAD -->
            <div class="page-head">
                <h1><div class="ph-icon"><i class="fas fa-coins"></i></div> My Earnings</h1>
                <p>Track your revenue, fees, and net earnings across all transactions.</p>
            </div>

            <!-- STAT GRID -->
            <div class="stat-grid">
                <div class="stat-card sc-gold">
                    <div class="sc-icon sci-gold"><i class="fas fa-dollar-sign"></i></div>
                    <div class="sc-lbl">Total Revenue</div>
                    <div class="sc-val">$<?php echo number_format($earnings['total_revenue'],2); ?></div>
                    <div class="sc-sub"><i class="fas fa-receipt"></i> <?php echo $earnings['total_transactions']; ?> total transactions</div>
                </div>
                <div class="stat-card sc-teal">
                    <div class="sc-icon sci-teal"><i class="fas fa-circle-check"></i></div>
                    <div class="sc-lbl">Completed Revenue</div>
                    <div class="sc-val">$<?php echo number_format($earnings['completed_revenue'],2); ?></div>
                    <div class="sc-sub"><i class="fas fa-check"></i> <?php echo $earnings['completed_count']; ?> completed sales</div>
                </div>
                <div class="stat-card sc-blue">
                    <div class="sc-icon sci-blue"><i class="fas fa-hourglass-half"></i></div>
                    <div class="sc-lbl">Pending Revenue</div>
                    <div class="sc-val">$<?php echo number_format($earnings['pending_revenue'],2); ?></div>
                    <div class="sc-sub"><i class="fas fa-clock"></i> <?php echo $earnings['pending_count']; ?> pending</div>
                </div>
                <div class="stat-card sc-purple">
                    <div class="sc-icon sci-purple"><i class="fas fa-chart-bar"></i></div>
                    <div class="sc-lbl">Average Sale</div>
                    <div class="sc-val">$<?php echo number_format($earnings['average_sale'],2); ?></div>
                    <div class="sc-sub"><i class="fas fa-coins"></i> per transaction</div>
                </div>
            </div>

            <!-- CHARTS -->
            <div class="charts-grid">

                <!-- BAR CHART (improved) -->
                <div class="chart-panel">
                    <div class="chart-head">
                        <div class="chart-title">
                            <div class="cti cti-gold"><i class="fas fa-calendar-alt"></i></div>
                            Monthly Earnings
                        </div>
                        <span class="chart-sub">Last 6 months · Completed only</span>
                    </div>
                    <div class="chart-body">
                        <?php if(!empty($months_data)): ?>
                        <div class="bar-wrap" id="barChart">
                            <?php foreach($months_data as $m):
                                // Height calculation: at least 8px, max 140px
                                $max_height = 140;
                                $h = $max_rev > 0 ? round(($m['monthly_revenue'] / $max_rev) * $max_height) : 8;
                                $h = max(8, min($max_height, $h));
                                $label = date("M 'y", strtotime($m['month'].'-01'));
                            ?>
                            <div class="bar-col">
                                <div class="bar-fill" style="height:<?php echo $h; ?>px;"
                                     data-val="$<?php echo number_format($m['monthly_revenue'],0); ?>"></div>
                                <span class="bar-lbl"><?php echo $label; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-chart-bar"></i>
                            <span>No completed sales data yet</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PAYMENT METHODS -->
                <div class="chart-panel">
                    <div class="chart-head">
                        <div class="chart-title">
                            <div class="cti cti-teal"><i class="fas fa-credit-card"></i></div>
                            Payment Methods
                        </div>
                    </div>
                    <?php
                    $pm_rows = [];
                    while($pm = mysqli_fetch_assoc($payment_methods)) $pm_rows[] = $pm;
                    ?>
                    <?php if(!empty($pm_rows)): ?>
                    <div class="methods-list">
                        <?php foreach($pm_rows as $pm):
                            $pm_icon = match($pm['method']) {
                                'paypal'        => 'fa-paypal',
                                'gcash'         => 'fa-mobile-screen',
                                'bank_transfer' => 'fa-building-columns',
                                default         => 'fa-credit-card'
                            };
                        ?>
                        <div class="method-item">
                            <div class="method-left">
                                <div class="method-ico"><i class="fas <?php echo $pm_icon; ?>"></i></div>
                                <div>
                                    <div class="method-name"><?php echo ucfirst(str_replace('_',' ',$pm['method'])); ?></div>
                                    <div class="method-cnt"><?php echo $pm['count']; ?> transactions</div>
                                </div>
                            </div>
                            <div class="method-total">$<?php echo number_format($pm['total'],2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="no-data" style="padding:36px 0;">
                        <i class="fas fa-credit-card"></i>
                        <span>No payment data yet</span>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- TRANSACTIONS TABLE -->
            <div class="table-panel">
                <div class="table-head">
                    <div class="table-head-title">
                        <div class="cti cti-gold"><i class="fas fa-clock-rotate-left"></i></div>
                        Recent Transactions
                    </div>
                    <a href="my-sales.php" class="btn btn-ghost btn-sm">View All</a>
                </div>

                <?php if(mysqli_num_rows($recent) > 0): ?>
                <table class="earnings-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Buyer</th>
                            <th>Amount</th>
                            <th>Fee</th>
                            <th>Net</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($r = mysqli_fetch_assoc($recent)):
                        $net = $r['amount'] - ($r['service_fee']??0);
                        $sc  = $status_cfg[$r['status']] ?? ['label'=>ucfirst($r['status']),'color'=>'var(--text-muted)','bg'=>'var(--surface2)'];
                    ?>
                    <tr>
                        <td>
                            <div class="p-info">
                                <div class="p-thumb">
                                    <?php if(!empty($r['image_path'])): ?><img src="<?php echo htmlspecialchars($r['image_path']); ?>" alt=""><?php else: ?><i class="fas fa-gamepad"></i><?php endif; ?>
                                </div>
                                <div>
                                    <div class="p-name"><?php echo htmlspecialchars($r['product_title']); ?></div>
                                    <?php if(!empty($r['game_name'])): ?><span class="p-game"><?php echo htmlspecialchars($r['game_name']); ?></span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="buyer-ava"><?php echo strtoupper(substr($r['buyer_name'],0,2)); ?></span>
                            <?php echo htmlspecialchars($r['buyer_name']); ?>
                        </td>
                        <td><div class="amt">$<?php echo number_format($r['amount'],2); ?></div></td>
                        <td><div class="fee">-$<?php echo number_format($r['service_fee']??0,2); ?></div></td>
                        <td><div class="amt-net">$<?php echo number_format($net,2); ?></div></td>
                        <td>
                            <span class="sbadge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;">
                                <?php echo $sc['label']; ?>
                            </span>
                        </td>
                        <td><span class="p-date"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></span></td>
                        <td>
                            <a href="transaction-detail.php?id=<?php echo $r['id']; ?>" class="view-btn"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fas fa-coins"></i></div>
                    <h3>No Earnings Yet</h3>
                    <p>Start selling to see your earnings appear here.</p>
                    <a href="add-product.php" class="btn btn-gold"><i class="fas fa-plus-circle"></i> List Your First Product</a>
                </div>
                <?php endif; ?>
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

    window.addEventListener('load', () => {
        // Animate bar heights from zero to their final values
        document.querySelectorAll('.bar-fill').forEach((bar, i) => {
            const targetHeight = bar.style.height;
            bar.style.height = '0px';
            setTimeout(() => { bar.style.height = targetHeight; }, 100 + i * 80);
        });
    });
</script>
</body>
</html>