<?php
require_once 'includes/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header('Location: login.php');
    exit();
}

$user_id   = $_SESSION['user_id'];
$username  = $_SESSION['username'];
$full_name = $_SESSION['full_name'];

$purchases       = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM transactions WHERE buyer_id = $user_id");
$purchase_stats  = mysqli_fetch_assoc($purchases);
$pending_count   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM transactions WHERE buyer_id = $user_id AND status = 'pending'"))['count'];
$completed_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM transactions WHERE buyer_id = $user_id AND status = 'completed'"))['count'];

$recent = mysqli_query($conn, "SELECT t.*, p.title as product_title, p.image_path, s.username as seller_name
                                FROM transactions t
                                JOIN products p ON t.product_id = p.id
                                JOIN users s ON t.seller_id = s.id
                                WHERE t.buyer_id = $user_id
                                ORDER BY t.created_at DESC LIMIT 5");

$products = mysqli_query($conn, "SELECT p.*, u.username as seller_name
                                 FROM products p
                                 JOIN users u ON p.seller_id = u.id
                                 WHERE p.status = 'available'
                                 ORDER BY p.created_at DESC LIMIT 6");

$disputed      = mysqli_query($conn, "SELECT COUNT(*) as count FROM transactions t JOIN disputes d ON t.id = d.transaction_id WHERE t.buyer_id = $user_id");
$disputed_count = mysqli_fetch_assoc($disputed)['count'];
$saved_amount   = $purchase_stats['total'] * 0.05;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Dashboard — Trusted Midman</title>
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

        .logo-text { font-family:var(--font-head); font-weight:700; font-size:1.1rem; color:var(--text); line-height:1.2; letter-spacing:-0.01em; }
        .logo-sub  { font-size:0.65rem; color:var(--gold); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-section-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--gold); background:var(--gold-dim); border:1px solid rgba(240,165,0,0.12); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--gold); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#c47d00); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px rgba(240,165,0,0.2); }
        .user-pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .user-pill-role { font-size:0.68rem; color:var(--gold); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .topbar-right { display:flex; align-items:center; gap:10px; }
        .topbar-btn { width:36px; height:36px; background:var(--surface2); border:1px solid var(--border); border-radius:9px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .topbar-btn:hover { color:var(--gold); border-color:rgba(240,165,0,0.25); background:var(--gold-dim); }
        .online-dot { display:flex; align-items:center; gap:7px; font-size:0.78rem; color:var(--text-muted); margin-left:4px; }
        .online-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--teal); box-shadow:0 0 8px var(--teal); }
        .content { padding:32px; flex:1; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);} }
        @keyframes spin { to{transform:rotate(360deg);} }

        /* ── HERO ── */
        .hero {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:40px 48px; margin-bottom:28px;
            position:relative; overflow:hidden;
        }
        .hero::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.3),transparent); }
        .hero-glow1 { position:absolute; top:-60px; right:-60px; width:320px; height:320px; background:radial-gradient(circle,rgba(240,130,0,0.16) 0%,transparent 65%); pointer-events:none; }
        .hero-glow2 { position:absolute; bottom:-80px; left:30%; width:260px; height:260px; background:radial-gradient(circle,rgba(0,212,170,0.07) 0%,transparent 65%); pointer-events:none; }
        .hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; gap:24px; flex-wrap:wrap; }
        .hero-text  { flex:1; min-width:280px; }

        .hero-eyebrow {
            font-size:0.75rem; font-weight:700; letter-spacing:0.16em;
            text-transform:uppercase; color:var(--gold); margin-bottom:12px;
            display:flex; align-items:center; gap:8px;
        }
        .hero-eyebrow::before { content:''; width:24px; height:2px; background:var(--gold); border-radius:2px; }

        .hero-title {
            font-family:var(--font-head); font-size:2.6rem; font-weight:800;
            color:var(--text); line-height:1.08; margin-bottom:14px; letter-spacing:-0.01em;
        }
        .hero-title span { color:var(--gold); }

        .hero-desc { color:var(--text-muted); font-size:0.95rem; line-height:1.75; max-width:520px; margin-bottom:28px; }

        .hero-actions { display:flex; gap:12px; flex-wrap:wrap; }

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
        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:8px; padding:11px 22px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.9rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.25s ease; letter-spacing:0.01em; }
        .btn-primary { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 4px 20px var(--gold-glow); }
        .btn-primary:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); box-shadow:0 8px 28px rgba(240,165,0,0.4); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); background:var(--surface3); transform:translateY(-2px); }

        /* hero badge */
        .hero-badge { flex-shrink:0; width:130px; height:130px; background:var(--surface2); border:1px solid var(--border2); border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; position:relative; }
        .hero-badge::before { content:''; position:absolute; inset:-6px; border-radius:50%; border:2px dashed rgba(240,165,0,0.25); animation:spin 20s linear infinite; }
        .hero-badge-icon  { font-size:2.2rem; color:var(--gold); }
        .hero-badge-label { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.12em; color:var(--text-muted); }

        /* ── STATS ROW ── */
        .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }

        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:22px 24px;
            position:relative; overflow:hidden;
            transition:border-color 0.25s, transform 0.25s;
            opacity:0; transform:translateY(16px); animation:fadeUp 0.5s ease forwards;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.25s; }
        .stat-card:hover { border-color:var(--border2); transform:translateY(-3px); }
        .stat-card:hover::before { opacity:1; }
        .stat-card:nth-child(1) { animation-delay:0.05s; } .stat-card:nth-child(1)::before { background:linear-gradient(90deg,var(--gold),transparent); }
        .stat-card:nth-child(2) { animation-delay:0.12s; } .stat-card:nth-child(2)::before { background:linear-gradient(90deg,var(--teal),transparent); }
        .stat-card:nth-child(3) { animation-delay:0.19s; } .stat-card:nth-child(3)::before { background:linear-gradient(90deg,var(--orange),transparent); }
        .stat-card:nth-child(4) { animation-delay:0.26s; } .stat-card:nth-child(4)::before { background:linear-gradient(90deg,var(--purple),transparent); }

        .stat-card-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .stat-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; border:1px solid transparent; }
        .stat-icon-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .stat-icon-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .stat-icon-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .stat-icon-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }

        .stat-trend { font-size:0.72rem; font-weight:600; padding:3px 8px; border-radius:20px; display:flex; align-items:center; gap:4px; }
        .stat-trend-up  { background:rgba(0,212,170,0.1); color:var(--teal); }
        .stat-trend-neu { background:var(--surface2); color:var(--text-dim); border:1px solid var(--border); }
        .stat-value { font-family:var(--font-head); font-size:2rem; font-weight:800; color:var(--text); line-height:1; margin-bottom:4px; letter-spacing:-0.01em; }
        .stat-label { font-size:0.8rem; color:var(--text-muted); }

        /* ── QUICK ACTIONS ── */
        .quick-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
        .qa-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:20px; text-align:center;
            text-decoration:none; color:inherit; transition:all 0.25s ease;
            position:relative; overflow:hidden;
        }
        .qa-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.25s; }
        .qa-card:nth-child(1)::before { background:linear-gradient(90deg,var(--gold),transparent); }
        .qa-card:nth-child(2)::before { background:linear-gradient(90deg,var(--teal),transparent); }
        .qa-card:nth-child(3)::before { background:linear-gradient(90deg,var(--red),transparent); }
        .qa-card:nth-child(4)::before { background:linear-gradient(90deg,var(--purple),transparent); }
        .qa-card:hover { transform:translateY(-4px); box-shadow:0 16px 40px rgba(0,0,0,0.35); border-color:var(--border2); }
        .qa-card:hover::before { opacity:1; }
        .qa-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; margin:0 auto 14px; border:1px solid transparent; }
        .qa-icon-1 { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .qa-icon-2 { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .qa-icon-3 { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.14); }
        .qa-icon-4 { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .qa-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); margin-bottom:5px; letter-spacing:-0.01em; }
        .qa-desc  { font-size:0.77rem; color:var(--text-muted); }

        /* ── INSIGHTS ── */
        .insights-strip { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:28px; }
        .insight-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:24px 28px;
            display:flex; align-items:center; gap:20px;
            transition:border-color 0.25s, transform 0.25s;
            position:relative;
        }
        .insight-card::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.15),transparent); }
        .insight-card:hover { border-color:var(--border2); transform:translateY(-2px); }
        .insight-icon-wrap { width:56px; height:56px; border-radius:14px; background:var(--gold-dim); display:flex; align-items:center; justify-content:center; font-size:1.4rem; color:var(--gold); flex-shrink:0; border:1px solid rgba(240,165,0,0.14); }
        .insight-icon-wrap.teal { background:var(--teal-dim); color:var(--teal); border-color:rgba(0,212,170,0.14); }
        .insight-val { font-family:var(--font-head); font-size:1.9rem; font-weight:800; color:var(--text); line-height:1; margin-bottom:5px; letter-spacing:-0.01em; }
        .insight-lbl { font-size:0.84rem; color:var(--text-muted); }

        /* ── TWO-COL ── */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; }

        /* ── PANELS ── */
        .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; position:relative; }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.12),transparent); z-index:1; }
        .panel-header { display:flex; align-items:center; justify-content:space-between; padding:17px 22px; border-bottom:1px solid var(--border); }
        .panel-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:10px; letter-spacing:-0.01em; }
        .panel-title-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.14); }
        .panel-link { font-size:0.78rem; font-weight:600; color:var(--gold); text-decoration:none; display:flex; align-items:center; gap:4px; transition:gap 0.2s; }
        .panel-link:hover { gap:8px; }
        .panel-body { padding:18px 22px; }

        /* ── PURCHASE LIST ── */
        .purchase-item { display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid var(--border); transition:transform 0.2s; }
        .purchase-item:last-child { border-bottom:none; }
        .purchase-item:hover { transform:translateX(4px); }
        .purchase-img { width:44px; height:44px; border-radius:var(--radius-sm); background:var(--surface2); border:1px solid var(--border); flex-shrink:0; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.1rem; overflow:hidden; }
        .purchase-img img { width:100%; height:100%; object-fit:cover; }
        .purchase-info { flex:1; min-width:0; }
        .purchase-name { font-size:0.875rem; font-weight:600; color:var(--text-warm); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:3px; }
        .purchase-meta { font-size:0.75rem; color:var(--text-muted); }
        .purchase-right { text-align:right; flex-shrink:0; }
        .purchase-amount { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--gold); margin-bottom:4px; letter-spacing:-0.01em; }
        .badge { display:inline-block; font-size:0.65rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; padding:2px 8px; border-radius:20px; }
        .badge-completed { background:var(--teal-dim);  color:var(--teal);  border:1px solid rgba(0,212,170,0.14); }
        .badge-pending   { background:var(--gold-dim);  color:var(--gold);  border:1px solid rgba(240,165,0,0.14); }
        .badge-disputed  { background:var(--red-dim);   color:var(--red);   border:1px solid rgba(255,77,109,0.14); }

        /* ── PRODUCT GRID ── */
        .product-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
        .product-card { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; text-decoration:none; color:inherit; transition:all 0.25s ease; display:block; }
        .product-card:hover { border-color:rgba(240,165,0,0.22); transform:translateY(-4px); box-shadow:0 12px 32px rgba(0,0,0,0.4); }
        .product-thumb { width:100%; height:80px; background:var(--surface); display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.5rem; overflow:hidden; }
        .product-thumb img { width:100%; height:100%; object-fit:cover; transition:transform 0.3s; }
        .product-card:hover .product-thumb img { transform:scale(1.06); }
        .product-info  { padding:10px 12px; }
        .product-name  { font-size:0.8rem; font-weight:600; color:var(--text-warm); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:4px; }
        .product-price { font-family:var(--font-head); font-size:0.95rem; font-weight:800; color:var(--gold); letter-spacing:-0.01em; }
        .product-seller { font-size:0.68rem; color:var(--text-dim); margin-top:2px; }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:40px 24px; color:var(--text-muted); }
        .empty i  { font-size:2.5rem; margin-bottom:14px; opacity:0.3; display:block; color:var(--text-dim); }
        .empty h4 { color:var(--text-warm); font-family:var(--font-head); font-size:1.05rem; margin-bottom:8px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.85rem; margin-bottom:18px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root{--sidebar-w:220px;} .stats-row{grid-template-columns:repeat(2,1fr);} .quick-row{grid-template-columns:repeat(2,1fr);} .product-grid{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .sidebar-overlay.visible{display:block;} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} .hero{padding:28px 24px;} .hero-title{font-size:2rem;} .hero-badge{display:none;} .two-col{grid-template-columns:1fr;} .insights-strip{grid-template-columns:1fr;} }
        @media(max-width:540px)  { .stats-row{grid-template-columns:1fr;} .quick-row{grid-template-columns:repeat(2,1fr);} .product-grid{grid-template-columns:repeat(2,1fr);} }
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
            <div class="nav-section-label">Buyer</div>
            <a href="buyer-dashboard.php" class="nav-link active"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="products.php" class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
            <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>
            <div class="nav-section-label" style="margin-top:10px;">Account</div>
            <a href="apply-midman.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php" class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="avatar"><?php echo strtoupper(substr($username, 0, 2)); ?></div>
                <div>
                    <div class="user-pill-name"><?php echo htmlspecialchars($full_name ?? $username); ?></div>
                    <div class="user-pill-role">Buyer</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Dashboard</span>
            </div>
            <div class="topbar-right">
                <a href="products.php"       class="topbar-btn" title="Browse Products"><i class="fas fa-search" style="font-size:0.85rem;"></i></a>
                <a href="my-transactions.php" class="topbar-btn" title="My Orders"><i class="fas fa-bag-shopping" style="font-size:0.85rem;"></i></a>
                <div class="online-dot">Online</div>
            </div>
        </header>

        <div class="content">

            <!-- HERO -->
            <div class="hero">
                <div class="hero-glow1"></div>
                <div class="hero-glow2"></div>
                <div class="hero-inner">
                    <div class="hero-text">
                        <div class="hero-eyebrow">Buyer Portal</div>
                        <h1 class="hero-title">
                            Welcome back,<br>
                            <span><?php echo htmlspecialchars($full_name ?? $username); ?></span>
                        </h1>
                        <p class="hero-desc">
                            Your trusted marketplace for secure gaming transactions. Every purchase is protected by our midman escrow — your money stays safe until you're satisfied.
                        </p>
                        <div class="hero-actions">
                            <a href="products.php"        class="btn btn-primary"><i class="fas fa-search"></i> Explore Products</a>
                            <a href="my-transactions.php" class="btn btn-ghost"><i class="fas fa-bag-shopping"></i> My Orders</a>
                        </div>
                    </div>
                    <div class="hero-badge">
                        <div class="hero-badge-icon"><i class="fas fa-shield-halved"></i></div>
                        <div class="hero-badge-label">Protected</div>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="quick-row">
                <a href="products.php" class="qa-card">
                    <div class="qa-icon qa-icon-1"><i class="fas fa-magnifying-glass"></i></div>
                    <div class="qa-title">Browse</div>
                    <div class="qa-desc">Find gaming items</div>
                </a>
                <a href="my-transactions.php" class="qa-card">
                    <div class="qa-icon qa-icon-2"><i class="fas fa-bag-shopping"></i></div>
                    <div class="qa-title">My Orders</div>
                    <div class="qa-desc">Track purchases</div>
                </a>
                <a href="raise-dispute.php" class="qa-card">
                    <div class="qa-icon qa-icon-3"><i class="fas fa-scale-balanced"></i></div>
                    <div class="qa-title">Disputes</div>
                    <div class="qa-desc">Resolve issues</div>
                </a>
                <a href="profile.php" class="qa-card">
                    <div class="qa-icon qa-icon-4"><i class="fas fa-user-gear"></i></div>
                    <div class="qa-title">Settings</div>
                    <div class="qa-desc">Manage account</div>
                </a>
            </div>

            <!-- STATS -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-icon stat-icon-gold"><i class="fas fa-bag-shopping"></i></div>
                        <span class="stat-trend stat-trend-up"><i class="fas fa-arrow-trend-up"></i> All-time</span>
                    </div>
                    <div class="stat-value"><?php echo $purchase_stats['count']; ?></div>
                    <div class="stat-label">Total Purchases</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-icon stat-icon-teal"><i class="fas fa-dollar-sign"></i></div>
                        <span class="stat-trend stat-trend-up"><i class="fas fa-wallet"></i> Lifetime</span>
                    </div>
                    <div class="stat-value">$<?php echo number_format($purchase_stats['total'], 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-icon stat-icon-orange"><i class="fas fa-hourglass-half"></i></div>
                        <span class="stat-trend stat-trend-neu"><i class="fas fa-clock"></i> Active</span>
                    </div>
                    <div class="stat-value"><?php echo $pending_count; ?></div>
                    <div class="stat-label">Pending Orders</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top">
                        <div class="stat-icon stat-icon-purple"><i class="fas fa-circle-check"></i></div>
                        <span class="stat-trend stat-trend-up"><i class="fas fa-check"></i> Done</span>
                    </div>
                    <div class="stat-value"><?php echo $completed_count; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>

            <!-- INSIGHTS -->
            <div class="insights-strip">
                <div class="insight-card">
                    <div class="insight-icon-wrap"><i class="fas fa-piggy-bank"></i></div>
                    <div>
                        <div class="insight-val">$<?php echo number_format($saved_amount, 2); ?></div>
                        <div class="insight-lbl">Estimated savings via Midman Protection</div>
                    </div>
                </div>
                <div class="insight-card">
                    <div class="insight-icon-wrap teal"><i class="fas fa-handshake"></i></div>
                    <div>
                        <div class="insight-val"><?php echo $disputed_count; ?></div>
                        <div class="insight-lbl">Disputes successfully resolved</div>
                    </div>
                </div>
            </div>

            <!-- RECENT + TRENDING -->
            <div class="two-col">

                <!-- Recent Purchases -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <div class="panel-title-icon"><i class="fas fa-clock-rotate-left"></i></div>
                            Recent Purchases
                        </div>
                        <a href="my-transactions.php" class="panel-link">View all <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="panel-body">
                        <?php if(mysqli_num_rows($recent) > 0): ?>
                            <?php while($p = mysqli_fetch_assoc($recent)): ?>
                            <div class="purchase-item">
                                <div class="purchase-img">
                                    <?php if($p['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-image"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="purchase-info">
                                    <div class="purchase-name"><?php echo htmlspecialchars($p['product_title']); ?></div>
                                    <div class="purchase-meta">
                                        From <?php echo htmlspecialchars($p['seller_name']); ?> &bull;
                                        <?php echo date('M d, Y', strtotime($p['created_at'])); ?>
                                    </div>
                                </div>
                                <div class="purchase-right">
                                    <div class="purchase-amount">$<?php echo number_format($p['amount'], 2); ?></div>
                                    <span class="badge badge-<?php echo $p['status']=='completed'?'completed':($p['status']=='disputed'?'disputed':'pending'); ?>">
                                        <?php echo ucfirst(str_replace('_',' ',$p['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty">
                                <i class="fas fa-bag-shopping"></i>
                                <h4>No purchases yet</h4>
                                <p>Browse the marketplace to find your first item.</p>
                                <a href="products.php" class="btn btn-primary" style="font-size:0.82rem;padding:9px 18px;">
                                    <i class="fas fa-search"></i> Browse Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Trending Products -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <div class="panel-title-icon"><i class="fas fa-fire"></i></div>
                            Trending Products
                        </div>
                        <a href="products.php" class="panel-link">Browse all <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="panel-body">
                        <?php if(mysqli_num_rows($products) > 0): ?>
                        <div class="product-grid">
                            <?php while($prod = mysqli_fetch_assoc($products)): ?>
                            <a href="product-detail.php?id=<?php echo $prod['id']; ?>" class="product-card">
                                <div class="product-thumb">
                                    <?php if($prod['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($prod['image_path']); ?>" alt="<?php echo htmlspecialchars($prod['title']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-gamepad"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($prod['title']); ?></div>
                                    <div class="product-price">$<?php echo number_format($prod['price'], 2); ?></div>
                                    <div class="product-seller">by <?php echo htmlspecialchars($prod['seller_name']); ?></div>
                                </div>
                            </a>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty">
                            <i class="fas fa-store"></i>
                            <h4>No products listed</h4>
                            <p>Check back soon for new listings.</p>
                        </div>
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
    function openSidebar()  { sidebar.classList.add('open');    overlay.classList.add('visible'); }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('visible'); }
    hamburger.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
    overlay.addEventListener('click', closeSidebar);
</script>
</body>
</html>