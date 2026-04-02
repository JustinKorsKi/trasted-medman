<?php
require_once '../includes/config.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

// ── Handle resolution ──
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resolve_dispute'])) {
    $dispute_id = intval($_POST['dispute_id']);
    $resolution = mysqli_real_escape_string($conn, $_POST['resolution']);
    $action     = $_POST['action'];

    $dispute = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM disputes WHERE id=$dispute_id"));
    mysqli_query($conn,"UPDATE disputes SET status='resolved', resolution='$resolution' WHERE id=$dispute_id");

    if($action == 'refund_buyer') {
        mysqli_query($conn,"UPDATE transactions SET status='cancelled' WHERE id={$dispute['transaction_id']}");
        $trans = mysqli_fetch_assoc(mysqli_query($conn,"SELECT product_id FROM transactions WHERE id={$dispute['transaction_id']}"));
        mysqli_query($conn,"UPDATE products SET status='available' WHERE id={$trans['product_id']}");
        $_SESSION['success'] = 'Dispute resolved: Refunded to buyer.';
    } elseif($action == 'release_seller') {
        mysqli_query($conn,"UPDATE transactions SET status='completed' WHERE id={$dispute['transaction_id']}");
        mysqli_query($conn,"UPDATE earnings SET status='paid' WHERE transaction_id={$dispute['transaction_id']}");
        $_SESSION['success'] = 'Dispute resolved: Payment released to seller.';
    } elseif($action == 'split') {
        $trans = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM transactions WHERE id={$dispute['transaction_id']}"));
        mysqli_query($conn,"INSERT INTO earnings (midman_id, transaction_id, amount, status) VALUES ({$trans['midman_id']},{$dispute['transaction_id']},{$trans['service_fee']},'paid')");
        $_SESSION['success'] = 'Dispute resolved: Payment split 50/50 between buyer and seller.';
    }
    header('Location: disputes.php'); exit();
}

// ── Stats ──
$open_count     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];
$resolved_count = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='resolved'"))['c'];
$total_count    = $open_count + $resolved_count;
$resolution_rate = $total_count > 0 ? round(($resolved_count / $total_count) * 100, 1) : 100;

// ── Open disputes ──
$disputes = mysqli_query($conn,
    "SELECT d.*, t.amount, t.status as transaction_status, t.buyer_id,
     p.title as product_title,
     b.username as buyer_name, s.username as seller_name,
     u.username as raised_by_name
     FROM disputes d
     JOIN transactions t ON d.transaction_id = t.id
     JOIN products p ON t.product_id = p.id
     JOIN users b ON t.buyer_id = b.id
     JOIN users s ON t.seller_id = s.id
     JOIN users u ON d.raised_by = u.id
     WHERE d.status = 'open'
     ORDER BY d.created_at DESC");

// ── Sidebar badges ──
$pending_kyc   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM verification_requests WHERE status='pending'"))['c'];
$pending_apps  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM midman_applications WHERE status='pending'"))['c'];
$open_disputes = $open_count;

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
    <title>Manage Disputes — Trusted Midman Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        :root {
            --bg:         #0f0c08;
            --surface:    #0f0b07;
            --surface2:   #201a13;
            --surface3:   #271f16;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.24);
            --admin:      #e03535;
            --admin-lt:   #ff5a5a;
            --admin-dim:  rgba(224,53,53,0.12);
            --admin-glow: rgba(224,53,53,0.28);
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

        /* ── SIDEBAR ── */
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

        /* ── STAT STRIP ── */
        .stat-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:20px;
            display:flex; align-items:center; gap:14px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            transition:border-color 0.25s, transform 0.25s;
            position:relative; overflow:hidden;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.25s; }
        .stat-card:hover { border-color:var(--border2); transform:translateY(-2px); }
        .stat-card:hover::before { opacity:1; }
        .stat-card:nth-child(1){animation-delay:.04s;} .stat-card:nth-child(1)::before{background:linear-gradient(90deg,var(--red),transparent);}
        .stat-card:nth-child(2){animation-delay:.08s;} .stat-card:nth-child(2)::before{background:linear-gradient(90deg,var(--teal),transparent);}
        .stat-card:nth-child(3){animation-delay:.12s;} .stat-card:nth-child(3)::before{background:linear-gradient(90deg,var(--blue),transparent);}
        .stat-card:nth-child(4){animation-delay:.16s;} .stat-card:nth-child(4)::before{background:linear-gradient(90deg,var(--orange),transparent);}
        .sc-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; border:1px solid transparent; }
        .si-red    { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .si-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .sc-val { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }
        .sc-sub { font-size:0.68rem; margin-top:4px; display:flex; align-items:center; gap:4px; }
        .sc-sub.alert-c { color:var(--red); }
        .sc-sub.pos     { color:var(--teal); }
        .sc-sub.muted   { color:var(--text-dim); }

        /* ── DISPUTE CARDS ── */
        .dispute-card {
            background:var(--surface); border:1px solid rgba(255,77,109,0.2);
            border-radius:var(--radius-lg); overflow:hidden; margin-bottom:22px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .dispute-card::before { content:''; position:absolute; top:0; left:0; bottom:0; width:3px; background:var(--red); }
        .dispute-card::after  { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,var(--red-dim),transparent); }
        .dispute-card:nth-child(1){animation-delay:.20s;} .dispute-card:nth-child(2){animation-delay:.26s;} .dispute-card:nth-child(3){animation-delay:.32s;}

        /* card head */
        .dc-head { display:flex; align-items:center; justify-content:space-between; padding:18px 24px 18px 28px; border-bottom:1px solid var(--border); gap:16px; flex-wrap:wrap; }
        .dc-head-left {}
        .dc-id    { font-family:var(--font-head); font-size:0.88rem; font-weight:700; color:var(--red); letter-spacing:-0.01em; margin-bottom:3px; }
        .dc-title { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; margin-bottom:3px; }
        .dc-amount { font-size:0.82rem; color:var(--text-muted); }
        .dc-amount strong { font-family:var(--font-head); color:var(--text-warm); font-size:0.95rem; letter-spacing:-0.01em; }

        .open-badge { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; background:var(--red-dim); color:var(--red); border:1px solid rgba(255,77,109,0.2); border-radius:20px; font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; }

        /* two-col detail grid */
        .dc-body { display:grid; grid-template-columns:1fr 1fr; gap:0; }
        .dc-col { padding:20px 24px 20px 28px; }
        .dc-col + .dc-col { border-left:1px solid var(--border); padding-left:24px; }
        .dc-col-title { font-family:var(--font-head); font-size:0.88rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
        .dc-col-title .col-icon { width:22px; height:22px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:0.65rem; }
        .ci-red  { background:var(--red-dim);  color:var(--red);  border:1px solid rgba(255,77,109,0.14); }
        .ci-blue { background:var(--blue-dim); color:var(--blue); border:1px solid rgba(78,159,255,0.14); }

        .detail-row { display:flex; flex-direction:column; gap:3px; margin-bottom:12px; }
        .detail-row:last-child { margin-bottom:0; }
        .detail-key { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); }
        .detail-val { font-size:0.875rem; color:var(--text-muted); line-height:1.6; }
        .detail-val.em { color:var(--text-warm); font-weight:600; }

        /* party badge */
        .party-badge { display:inline-flex; align-items:center; gap:5px; font-size:0.78rem; font-weight:600; padding:3px 9px; border-radius:10px; border:1px solid transparent; }
        .pb-buyer  { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .pb-seller { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }

        /* tx status badge */
        .sbadge { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 9px; border-radius:20px; display:inline-flex; align-items:center; gap:4px; border:1px solid transparent; }

        /* resolution panel */
        .resolve-panel {
            margin:0 24px 20px 28px;
            background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
        }
        .resolve-head { display:flex; align-items:center; gap:8px; padding:13px 18px; border-bottom:1px solid var(--border); }
        .resolve-head-icon { width:24px; height:24px; border-radius:6px; background:var(--admin-dim); color:var(--admin); border:1px solid rgba(224,53,53,0.14); display:flex; align-items:center; justify-content:center; font-size:0.7rem; }
        .resolve-head-title { font-family:var(--font-head); font-size:0.92rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .resolve-body { padding:18px; display:grid; grid-template-columns:1fr 1fr; gap:14px; align-items:start; }

        /* form elements */
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }
        .form-select, .form-textarea {
            padding:10px 13px; background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius-sm); color:var(--text-warm);
            font-family:var(--font-body); font-size:0.875rem; outline:none; transition:all 0.2s;
        }
        .form-select:focus, .form-textarea:focus { border-color:var(--admin); box-shadow:0 0 0 3px var(--admin-dim); background:var(--surface3); }
        .form-select option { background:#201a13; }
        .form-textarea { resize:vertical; min-height:90px; line-height:1.6; }
        .form-textarea::placeholder { color:var(--text-dim); }

        /* resolution action cards */
        .action-options { display:flex; flex-direction:column; gap:8px; margin-bottom:0; }
        .action-opt { cursor:pointer; }
        .action-opt input[type="radio"] { display:none; }
        .action-box { display:flex; align-items:center; gap:12px; padding:11px 14px; background:var(--surface); border:2px solid var(--border); border-radius:var(--radius-sm); transition:all 0.22s; }
        .action-opt input:checked + .action-box { border-color:var(--admin); background:var(--admin-dim); }
        .action-box:hover { border-color:var(--border2); background:var(--surface3); }
        .action-radio { width:14px; height:14px; border-radius:50%; border:2px solid var(--border2); flex-shrink:0; transition:all 0.2s; }
        .action-opt input:checked + .action-box .action-radio { border-color:var(--admin); background:var(--admin); box-shadow:0 0 0 3px var(--admin-dim); }
        .action-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.75rem; flex-shrink:0; border:1px solid transparent; }
        .ai-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .ai-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .ai-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .action-name { font-size:0.84rem; font-weight:600; color:var(--text-warm); }
        .action-desc { font-size:0.72rem; color:var(--text-dim); margin-top:1px; }

        /* resolve footer */
        .resolve-footer { display:flex; align-items:center; gap:10px; padding:14px 18px; border-top:1px solid var(--border); flex-wrap:wrap; }
        .btn { display:inline-flex; align-items:center; gap:7px; padding:10px 20px; border:none; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.22s; letter-spacing:0.01em; }
        .btn-resolve { background:linear-gradient(135deg,var(--admin),#b01e1e); color:white; box-shadow:0 3px 12px var(--admin-glow); }
        .btn-resolve:hover { transform:translateY(-1px); box-shadow:0 6px 18px var(--admin-glow); }
        .btn-view   { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-view:hover   { color:var(--text-warm); border-color:var(--border3); }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:60px 24px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); opacity:0; animation:fadeUp 0.45s ease 0.2s forwards; }
        .empty-icon { width:80px; height:80px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; color:var(--text-dim); margin:0 auto 18px; }
        .empty h3 { font-family:var(--font-head); font-size:1.3rem; color:var(--text-warm); margin-bottom:8px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.875rem; color:var(--text-muted); margin-bottom:20px; }
        .btn-primary { background:linear-gradient(135deg,var(--admin),#b01e1e); color:white; box-shadow:0 3px 12px var(--admin-glow); }
        .btn-primary:hover { transform:translateY(-2px); }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1200px) { .stat-strip{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:900px)  { .dc-body{grid-template-columns:1fr;} .dc-col + .dc-col{border-left:none;border-top:1px solid var(--border);} .resolve-body{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:540px)  { .stat-strip{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — identical to admin dashboard -->
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
            <a href="dashboard.php"     class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="charts.php"        class="nav-link"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports</a>

            <div class="nav-label" style="margin-top:10px;">Management</div>
            <a href="users.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-users"></i></span> Users</a>
            <a href="transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-arrows-left-right"></i></span> Transactions</a>
            <a href="verifications.php" class="nav-link">
                <span class="nav-icon"><i class="fas fa-id-card"></i></span> KYC Verifications
                <?php if($pending_kyc>0): ?><span class="nav-badge"><?php echo $pending_kyc; ?></span><?php endif; ?>
            </a>
            <a href="applications.php"  class="nav-link">
                <span class="nav-icon"><i class="fas fa-user-check"></i></span> Midman Apps
                <?php if($pending_apps>0): ?><span class="nav-badge"><?php echo $pending_apps; ?></span><?php endif; ?>
            </a>
            <a href="disputes.php"      class="nav-link active">
                <span class="nav-icon"><i class="fas fa-gavel"></i></span> Disputes
                <?php if($open_disputes>0): ?><span class="nav-badge"><?php echo $open_disputes; ?></span><?php endif; ?>
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
                <span class="page-title">Manage Disputes</span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn">
                    <i class="fas fa-bell" style="font-size:0.9rem;"></i>
                    <?php if($open_count > 0): ?><span class="notif-dot"></span><?php endif; ?>
                </div>
            </div>
        </header>

        <div class="content">

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="stat-card">
                    <div class="sc-icon si-red"><i class="fas fa-gavel"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $open_count; ?></div>
                        <div class="sc-lbl">Open Disputes</div>
                        <div class="sc-sub <?php echo $open_count>0?'alert-c':'pos'; ?>">
                            <i class="fas fa-<?php echo $open_count>0?'triangle-exclamation':'circle-check'; ?>"></i>
                            <?php echo $open_count>0?'Need attention':'All resolved'; ?>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $resolved_count; ?></div>
                        <div class="sc-lbl">Resolved</div>
                        <div class="sc-sub pos"><i class="fas fa-check"></i> Successfully handled</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-blue"><i class="fas fa-percentage"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $resolution_rate; ?>%</div>
                        <div class="sc-lbl">Resolution Rate</div>
                        <div class="sc-sub pos"><i class="fas fa-chart-line"></i> Success rate</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-orange"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="sc-val">N/A</div>
                        <div class="sc-lbl">Avg Resolution Time</div>
                        <div class="sc-sub muted"><i class="fas fa-hourglass-half"></i> Days to resolve</div>
                    </div>
                </div>
            </div>

            <!-- DISPUTE CARDS -->
            <?php if(mysqli_num_rows($disputes) > 0):
                $idx = 0;
                while($d = mysqli_fetch_assoc($disputes)):
                    $delay = 0.20 + $idx * 0.07; $idx++;
                    $raiser_role = ($d['raised_by'] == $d['buyer_id']) ? 'Buyer' : 'Seller';
                    $sc = $status_cfg[$d['transaction_status']] ?? ['label'=>ucfirst($d['transaction_status']),'color'=>'var(--text-muted)','bg'=>'var(--surface2)'];
            ?>
            <div class="dispute-card" style="animation-delay:<?php echo $delay; ?>s">

                <!-- HEAD -->
                <div class="dc-head">
                    <div class="dc-head-left">
                        <div class="dc-id">Dispute #<?php echo $d['id']; ?></div>
                        <div class="dc-title"><?php echo htmlspecialchars($d['product_title']); ?></div>
                        <div class="dc-amount">Transaction amount: <strong>$<?php echo number_format($d['amount'],2); ?></strong></div>
                    </div>
                    <div class="open-badge"><i class="fas fa-triangle-exclamation" style="font-size:0.6rem;"></i> Open Dispute</div>
                </div>

                <!-- BODY — two columns -->
                <div class="dc-body">

                    <!-- Dispute details -->
                    <div class="dc-col">
                        <div class="dc-col-title">
                            <div class="col-icon ci-red"><i class="fas fa-file-circle-exclamation"></i></div>
                            Dispute Details
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">Raised By</div>
                            <div class="detail-val">
                                <span class="party-badge <?php echo $raiser_role=='Buyer'?'pb-buyer':'pb-seller'; ?>">
                                    <i class="fas <?php echo $raiser_role=='Buyer'?'fa-user':'fa-store'; ?>" style="font-size:0.6rem;"></i>
                                    <?php echo htmlspecialchars($d['raised_by_name']); ?> (<?php echo $raiser_role; ?>)
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">Reason</div>
                            <div class="detail-val em"><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$d['reason']))); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">Description</div>
                            <div class="detail-val"><?php echo nl2br(htmlspecialchars($d['description'])); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">Filed On</div>
                            <div class="detail-val"><?php echo date('M d, Y · g:i A', strtotime($d['created_at'])); ?></div>
                        </div>
                    </div>

                    <!-- Transaction details -->
                    <div class="dc-col">
                        <div class="dc-col-title">
                            <div class="col-icon ci-blue"><i class="fas fa-arrows-left-right"></i></div>
                            Transaction Details
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">Buyer</div>
                            <div class="detail-val em"><?php echo htmlspecialchars($d['buyer_name']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">Seller</div>
                            <div class="detail-val em"><?php echo htmlspecialchars($d['seller_name']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">Amount</div>
                            <div class="detail-val" style="font-family:var(--font-head);font-size:1.05rem;font-weight:800;color:var(--teal);letter-spacing:-0.01em;">
                                $<?php echo number_format($d['amount'],2); ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">Transaction Status</div>
                            <div class="detail-val">
                                <span class="sbadge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;">
                                    <?php echo $sc['label']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-key">View Transaction</div>
                            <div class="detail-val">
                                <a href="../transaction-detail.php?id=<?php echo $d['transaction_id']; ?>" class="btn btn-view" style="padding:6px 13px;font-size:0.78rem;">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RESOLUTION PANEL -->
                <div class="resolve-panel">
                    <div class="resolve-head">
                        <div class="resolve-head-icon"><i class="fas fa-gavel"></i></div>
                        <span class="resolve-head-title">Resolve Dispute</span>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="dispute_id" value="<?php echo $d['id']; ?>">
                        <div class="resolve-body">

                            <!-- Action selection (visual radio cards) -->
                            <div class="form-group">
                                <div class="form-label">Resolution Action</div>
                                <div class="action-options">
                                    <label class="action-opt">
                                        <input type="radio" name="action" value="refund_buyer" required>
                                        <div class="action-box">
                                            <div class="action-radio"></div>
                                            <div class="action-icon ai-blue"><i class="fas fa-rotate-left"></i></div>
                                            <div>
                                                <div class="action-name">Refund Buyer</div>
                                                <div class="action-desc">Cancel transaction, restore product</div>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="action-opt">
                                        <input type="radio" name="action" value="release_seller">
                                        <div class="action-box">
                                            <div class="action-radio"></div>
                                            <div class="action-icon ai-teal"><i class="fas fa-circle-check"></i></div>
                                            <div>
                                                <div class="action-name">Release to Seller</div>
                                                <div class="action-desc">Complete transaction, pay seller</div>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="action-opt">
                                        <input type="radio" name="action" value="split">
                                        <div class="action-box">
                                            <div class="action-radio"></div>
                                            <div class="action-icon ai-orange"><i class="fas fa-scale-balanced"></i></div>
                                            <div>
                                                <div class="action-name">Split 50/50</div>
                                                <div class="action-desc">Divide amount between both parties</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Resolution notes -->
                            <div class="form-group">
                                <label class="form-label">Resolution Notes <span style="color:var(--red);font-size:0.8rem;text-transform:none;">*</span></label>
                                <textarea name="resolution" class="form-textarea" required
                                    placeholder="Explain your decision and reasoning in detail…"></textarea>
                            </div>

                        </div>
                        <div class="resolve-footer">
                            <button type="submit" name="resolve_dispute" class="btn btn-resolve"
                                    onclick="return confirm('Resolve this dispute? This action cannot be undone.')">
                                <i class="fas fa-gavel"></i> Resolve Dispute
                            </button>
                            <a href="../transaction-detail.php?id=<?php echo $d['transaction_id']; ?>" class="btn btn-view">
                                <i class="fas fa-eye"></i> View Full Transaction
                            </a>
                        </div>
                    </form>
                </div>

            </div>
            <?php endwhile; else: ?>
            <div class="empty">
                <div class="empty-icon"><i class="fas fa-scale-balanced"></i></div>
                <h3>No Open Disputes</h3>
                <p>Great job — all disputes have been resolved.</p>
                <a href="transactions.php" class="btn btn-primary"><i class="fas fa-arrows-left-right"></i> View All Transactions</a>
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
</script>
</body>
</html>