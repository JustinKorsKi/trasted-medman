<?php
require_once 'includes/config.php';
if(file_exists('includes/2fa-functions.php')) require_once 'includes/2fa-functions.php';
if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$two_factor_enabled = (isset($_SESSION['role']) && $_SESSION['role']=='midman' && function_exists('is2FAEnabled')) ? is2FAEnabled($_SESSION['user_id']) : false;

$user_id        = $_SESSION['user_id'];
$role           = $_SESSION['role'];
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if(!$transaction_id) { header('Location: my-transactions.php'); exit(); }

$query = "SELECT t.*, p.title as product_title, p.description as product_description,
          p.image_path, p.category,
          b.id as buyer_id, b.username as buyer_name, b.email as buyer_email, b.phone as buyer_phone,
          s.id as seller_id, s.username as seller_name, s.email as seller_email, s.phone as seller_phone,
          m.id as midman_id, m.username as midman_name, m.email as midman_email
          FROM transactions t
          JOIN products p ON t.product_id = p.id
          JOIN users b ON t.buyer_id = b.id
          JOIN users s ON t.seller_id = s.id
          LEFT JOIN users m ON t.midman_id = m.id
          WHERE t.id = $transaction_id";

$result = mysqli_query($conn, $query);
if(mysqli_num_rows($result) == 0) { header('Location: my-transactions.php'); exit(); }
$transaction = mysqli_fetch_assoc($result);

$is_authorized = ($transaction['buyer_id']==$user_id || $transaction['seller_id']==$user_id || $transaction['midman_id']==$user_id || $role=='admin');
if(!$is_authorized) { header('Location: my-transactions.php'); exit(); }

if($_SERVER['REQUEST_METHOD']=='POST') {
    $new_status = null;
    if($role=='midman' && $transaction['midman_id']==$user_id) {
        if(isset($_POST['hold_payment']))       { mysqli_query($conn,"UPDATE transactions SET status='in_progress' WHERE id=$transaction_id"); $new_status='in_progress'; $_SESSION['success']='Payment held. Waiting for delivery.'; }
        if(isset($_POST['release_payment']))    { mysqli_query($conn,"UPDATE transactions SET status='completed'   WHERE id=$transaction_id"); mysqli_query($conn,"UPDATE earnings SET status='paid' WHERE transaction_id=$transaction_id"); $new_status='completed'; $_SESSION['success']='Payment released. Transaction completed!'; }
        if(isset($_POST['cancel_transaction'])){ mysqli_query($conn,"UPDATE transactions SET status='cancelled'   WHERE id=$transaction_id"); mysqli_query($conn,"UPDATE products SET status='available' WHERE id={$transaction['product_id']}"); $new_status='cancelled'; $_SESSION['success']='Transaction cancelled.'; }
    }
    if(isset($_POST['confirm_receipt']) && $transaction['buyer_id']==$user_id)  { mysqli_query($conn,"UPDATE transactions SET status='delivered' WHERE id=$transaction_id"); $_SESSION['success']='Receipt confirmed. Waiting for midman to release payment.'; }
    if(isset($_POST['mark_delivered'])  && $transaction['seller_id']==$user_id) { mysqli_query($conn,"UPDATE transactions SET status='shipped'   WHERE id=$transaction_id"); $_SESSION['success']='Item marked as delivered.'; }
    if($new_status || isset($_POST['confirm_receipt']) || isset($_POST['mark_delivered'])) {
        if(file_exists('includes/notifications.php')) {
            require_once 'includes/notifications.php';
            $st = $new_status ?? (isset($_POST['confirm_receipt'])?'delivered':'shipped');
            @notifyStatusChange($transaction_id, $transaction['buyer_email'],  $st, $role);
            @notifyStatusChange($transaction_id, $transaction['seller_email'], $st, $role);
            if($transaction['midman_email']) @notifyStatusChange($transaction_id, $transaction['midman_email'], $st, $role);
        }
        header("Location: transaction-detail.php?id=$transaction_id"); exit();
    }
}

$result      = mysqli_query($conn, $query);
$transaction = mysqli_fetch_assoc($result);

$dispute_q   = mysqli_query($conn, "SELECT * FROM disputes WHERE transaction_id=$transaction_id");
$has_dispute = mysqli_num_rows($dispute_q) > 0;
$dispute     = $has_dispute ? mysqli_fetch_assoc($dispute_q) : null;

$has_rated = false;
if($transaction['status']=='completed' && $transaction['midman_id']) {
    $rc = mysqli_query($conn,"SELECT * FROM reviews WHERE transaction_id=$transaction_id AND reviewer_id=$user_id AND midman_rating IS NOT NULL");
    $has_rated = mysqli_num_rows($rc) > 0;
}

$status_map = [
    'pending'     => ['label'=>'Pending',     'icon'=>'fa-clock',              'color'=>'var(--orange)', 'bg'=>'var(--orange-dim)'],
    'in_progress' => ['label'=>'In Progress', 'icon'=>'fa-arrows-rotate',      'color'=>'var(--blue)',   'bg'=>'var(--blue-dim)'],
    'shipped'     => ['label'=>'Shipped',     'icon'=>'fa-box',                'color'=>'var(--purple)', 'bg'=>'var(--purple-dim)'],
    'delivered'   => ['label'=>'Delivered',   'icon'=>'fa-truck-ramp-box',     'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)'],
    'completed'   => ['label'=>'Completed',   'icon'=>'fa-circle-check',       'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)'],
    'disputed'    => ['label'=>'Disputed',    'icon'=>'fa-triangle-exclamation','color'=>'var(--red)',   'bg'=>'var(--red-dim)'],
    'cancelled'   => ['label'=>'Cancelled',   'icon'=>'fa-ban',                'color'=>'var(--text-dim)','bg'=>'var(--surface2)'],
];
$st = $status_map[$transaction['status']] ?? ['label'=>ucfirst($transaction['status']),'icon'=>'fa-circle','color'=>'var(--text-muted)','bg'=>'var(--surface2)'];

$steps_all  = ['pending','in_progress','shipped','delivered','completed'];
$step_order = array_flip($steps_all);
$cur_step   = $step_order[$transaction['status']] ?? -1;

$back_link = match($role) { 'midman'=>'midman-dashboard.php', 'admin'=>'admin/transactions.php', default=>'my-transactions.php' };
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction #<?php echo $transaction_id; ?> — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
    <?php
// ... (the exact PHP code from the user, unchanged) ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ... unchanged head content ... -->
    <style>
        /* All CSS, with modifications for purple midman */
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
            --purple:     #a064ff;
            --purple-dim: rgba(160,100,255,0.12);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.12);
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

            /* ── role accent — gold default (buyer / seller) ── */
            --accent:         #f0a500;
            --accent-lt:      #ffbe3a;
            --accent-dim:     rgba(240,165,0,0.13);
            --accent-glow:    rgba(240,165,0,0.28);
            --gradient-start: #f0a500;
            --gradient-end:   #d4920a;
            --accent-fg:      #0f0c08;
        }

        /* ── midman override — all sidebar chrome switches to purple ── */
        body.role-midman {
            --accent:         #a064ff;
            --accent-lt:      #be8fff;
            --accent-dim:     rgba(160,100,255,0.13);
            --accent-glow:    rgba(160,100,255,0.28);
            --gradient-start: #a064ff;
            --gradient-end:   #7040cc;
            --accent-fg:      #ffffff;
        }

        /* ── admin override — red ── */
        body.role-admin {
            --accent:         #e03535;
            --accent-lt:      #ff5a5a;
            --accent-dim:     rgba(224,53,53,0.13);
            --accent-glow:    rgba(224,53,53,0.28);
            --gradient-start: #e03535;
            --gradient-end:   #b01e1e;
            --accent-fg:      #ffffff;
        }

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(200,100,0,0.08) 0%,transparent 65%); pointer-events:none; }
        body.role-midman .sidebar::before { background:radial-gradient(circle,rgba(120,60,200,0.09) 0%,transparent 65%); }
        body.role-admin   .sidebar::before { background:radial-gradient(circle,rgba(180,30,30,0.10) 0%,transparent 65%); }
       
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
        .nav-link.active { color:var(--accent); background:var(--accent-dim); border:1px solid rgba(255,255,255,0.06); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--accent); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:var(--accent-fg); flex-shrink:0; box-shadow:0 0 10px var(--accent-glow); }
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
        .content { padding:28px 32px; flex:1; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim);  color:var(--teal);  border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);   color:#ff7090;      border:1px solid rgba(255,77,109,0.22); }

        /* ── STATUS HERO ── */
        .status-hero {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:28px; margin-bottom:22px;
            position:relative; overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
        }
        .status-hero::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent-glow),transparent); }
        .status-hero::after  { content:''; position:absolute; top:-60px; right:-60px; width:200px; height:200px; background:radial-gradient(circle,var(--accent-glow) 0%,transparent 65%); pointer-events:none; }

        .status-top { display:flex; align-items:center; gap:16px; margin-bottom:24px; position:relative; z-index:1; }
        .status-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; border:1px solid transparent; }
        .status-label { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--text); letter-spacing:-0.01em; }
        .status-sub   { font-size:0.78rem; color:var(--text-muted); margin-top:2px; }

        /* progress steps */
        .progress-steps { display:flex; align-items:center; gap:0; position:relative; z-index:1; }
        .pstep { display:flex; flex-direction:column; align-items:center; gap:6px; flex:1; position:relative; }
        .pstep-circle { width:32px; height:32px; border-radius:50%; border:2px solid var(--border2); display:flex; align-items:center; justify-content:center; font-size:0.72rem; color:var(--text-dim); background:var(--surface2); position:relative; z-index:1; transition:all 0.3s; }
        .pstep.done    .pstep-circle { border-color:var(--teal); background:var(--teal-dim); color:var(--teal); }
        .pstep.current .pstep-circle { border-color:var(--accent); background:var(--accent-dim); color:var(--accent); box-shadow:0 0 0 4px var(--accent-dim); }
        .pstep-label { font-size:0.65rem; color:var(--text-dim); text-align:center; line-height:1.3; }
        .pstep.done .pstep-label, .pstep.current .pstep-label { color:var(--text-muted); }
        .pstep-line { position:absolute; top:16px; left:50%; right:-50%; height:2px; background:var(--border2); z-index:0; }
        .pstep.done .pstep-line { background:var(--teal); }
        .pstep:last-child .pstep-line { display:none; }

        /* ── GRID ── */
        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px; }
        .detail-grid.triple { grid-template-columns:1fr 1fr 1fr; }

        /* ── PANELS ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden; margin-bottom:18px;
            opacity:0; transform:translateY(10px); animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent-dim),transparent); z-index:1; }
        .panel:nth-child(2){animation-delay:.05s;} .panel:nth-child(3){animation-delay:.10s;} .panel:nth-child(4){animation-delay:.15s;} .panel:nth-child(5){animation-delay:.20s;} .panel:nth-child(6){animation-delay:.25s;}

        .panel-head { display:flex; align-items:center; gap:10px; padding:15px 20px; border-bottom:1px solid var(--border); }
        .ph-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.78rem; border:1px solid transparent; }
        .ph-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .ph-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .ph-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .ph-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .ph-red    { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.14); }
        .ph-title  { font-family:var(--font-head); font-size:0.92rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .panel-body { padding:18px 20px; }

        /* ── USER CARDS ── */
        .user-card-row { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
        .uca { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.9rem; flex-shrink:0; border:1px solid transparent; }
        .uc-name { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .uc-sub  { font-size:0.72rem; color:var(--text-muted); margin-top:1px; }
        .info-row { display:flex; align-items:center; gap:8px; font-size:0.84rem; color:var(--text-muted); margin-bottom:6px; }
        .info-row i { font-size:0.72rem; color:var(--text-dim); width:14px; }
        .info-row a { color:var(--accent); text-decoration:none; transition:opacity 0.2s; }
        .info-row a:hover { opacity:0.8; }

        /* ── PRODUCT ── */
        .product-row { display:flex; gap:14px; align-items:flex-start; }
        .prod-thumb { width:70px; height:70px; background:var(--surface2); border:1px solid var(--border); border-radius:10px; overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.5rem; }
        .prod-thumb img { width:100%; height:100%; object-fit:cover; }
        .prod-title { font-family:var(--font-head); font-size:1rem; font-weight:700; color:var(--text); margin-bottom:4px; letter-spacing:-0.01em; }
        .prod-desc  { font-size:0.82rem; color:var(--text-muted); line-height:1.6; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
        .prod-cat   { font-size:0.68rem; background:var(--accent-dim); color:var(--accent); border:1px solid var(--accent-dim); padding:2px 8px; border-radius:10px; display:inline-block; margin-top:5px; }

        /* ── PAYMENT ── */
        .pay-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--border); font-size:0.875rem; }
        .pay-row:last-child { border-bottom:none; }
        .pay-key { color:var(--text-muted); }
        .pay-val { font-weight:600; color:var(--text-warm); }
        .pay-total .pay-key { font-family:var(--font-head); font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .pay-total .pay-val { font-family:var(--font-head); font-size:1.15rem; color:var(--accent); letter-spacing:-0.01em; }

        /* midman stars */
        .midman-stars { display:flex; gap:3px; margin:4px 0; }
        .midman-stars i { font-size:0.78rem; }

        /* ── BUTTONS ── */
        .actions-row { display:flex; flex-wrap:wrap; gap:10px; }
        .btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; letter-spacing:0.01em; }
        .btn-gold   { background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); color:var(--accent-fg); font-weight:700; box-shadow:0 3px 14px var(--accent-glow); }
        .btn-gold:hover   { background:linear-gradient(135deg,var(--accent-lt),var(--accent)); transform:translateY(-2px); }
        .btn-teal   { background:var(--teal-dim);   color:var(--teal);   border:1px solid rgba(0,212,170,0.2); }
        .btn-teal:hover   { background:rgba(0,212,170,0.2); transform:translateY(-2px); }
        .btn-blue   { background:var(--blue-dim);   color:var(--blue);   border:1px solid rgba(78,159,255,0.2); }
        .btn-blue:hover   { background:rgba(78,159,255,0.22); transform:translateY(-2px); }
        .btn-red    { background:var(--red-dim);    color:var(--red);    border:1px solid rgba(255,77,109,0.2); }
        .btn-red:hover    { background:rgba(255,77,109,0.22); transform:translateY(-2px); }
        .btn-orange { background:var(--orange-dim); color:var(--orange); border:1px solid rgba(255,150,50,0.2); }
        .btn-orange:hover { background:rgba(255,150,50,0.22); transform:translateY(-2px); }
        .btn-ghost  { background:var(--surface2);   color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover  { color:var(--text-warm); border-color:var(--border3); }

        /* ── RATE CTA ── */
        .rate-cta { background:var(--surface); border:1px solid var(--accent-dim); border-radius:var(--radius); padding:28px; text-align:center; position:relative; overflow:hidden; margin-bottom:18px; }
        .rate-cta::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent-glow),transparent); }
        .rate-cta::after  { content:''; position:absolute; inset:0; background:radial-gradient(ellipse at 50% 0%,var(--accent-dim) 0%,transparent 65%); pointer-events:none; }
        .rate-cta-inner   { position:relative; z-index:1; }
        .rate-stars { display:flex; justify-content:center; gap:5px; font-size:1.6rem; color:var(--accent); margin-bottom:12px; }
        .rate-cta-title { font-family:var(--font-head); font-size:1.2rem; font-weight:800; color:var(--text); margin-bottom:6px; letter-spacing:-0.01em; }
        .rate-cta-sub   { font-size:0.84rem; color:var(--text-muted); margin-bottom:18px; }
        .already-rated-box { background:var(--teal-dim); border:1px solid rgba(0,212,170,0.2); border-radius:var(--radius-sm); padding:12px 16px; display:inline-flex; align-items:center; gap:8px; font-size:0.84rem; color:var(--teal); }

        /* ── DISPUTE PANEL ── */
        .dispute-panel { background:var(--surface); border:1px solid rgba(255,77,109,0.22); border-radius:var(--radius); overflow:hidden; margin-bottom:18px; }
        .dispute-head  { background:var(--red-dim); padding:14px 20px; display:flex; align-items:center; gap:8px; }
        .dispute-head-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--red); letter-spacing:-0.01em; }
        .dispute-body  { padding:18px 20px; }
        .disp-row  { display:flex; gap:10px; font-size:0.875rem; margin-bottom:8px; }
        .disp-key  { color:var(--text-muted); font-weight:600; min-width:100px; }
        .disp-val  { color:var(--text-warm); }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root{--sidebar-w:220px;} }
        @media(max-width:900px)  { .detail-grid,.detail-grid.triple{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
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
            <?php if($role === 'seller'): ?>
                <div class="nav-label">Seller</div>
                <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
                <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
                <a href="my-transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales</a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php elseif($role === 'midman'): ?>
                <div class="nav-label">Midman</div>
                <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions</a>
                <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
                <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> KYC Status</a>
                <div class="nav-label" style="margin-top:10px;">Security</div>
                <a href="setup-2fa.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
                    <?php if($two_factor_enabled): ?><span style="margin-left:auto;background:var(--teal-dim);color:var(--teal);font-size:0.6rem;font-weight:800;padding:2px 7px;border-radius:10px;border:1px solid rgba(0,212,170,0.15);">Active</span><?php endif; ?>
                </a>
            <?php elseif($role === 'buyer'): ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <a href="my-transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>
            <?php elseif ($role === 'admin'): ?>
                <div class="nav-label">Overview</div>
                <a href="admin/dashboard.php"     class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="admin/charts.php"        class="nav-link"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports</a>
                <div class="nav-label" style="margin-top:10px;">Management</div>
                <a href="admin/users.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-users"></i></span> Users</a>
                <a href="admin/transactions.php"  class="nav-link  active"><span class="nav-icon"><i class="fas fa-arrows-left-right"></i></span> Transactions</a>
                <a href="admin/verifications.php" class="nav-link"><span class="nav-icon"><i class="fas fa-id-card"></i></span> KYC Verifications</a>
                <a href="admin/applications.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Midman Apps</a>
                <a href="admin/disputes.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-gavel"></i></span> Disputes</a>
                <div class="nav-label" style="margin-top:10px;">System</div>
                <a href="admin/settings.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-gear"></i></span> Settings</a>
                <a href="profile.php"             class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> My Profile</a>
            <?php endif; ?>
            <?php if($role !== 'admin'): ?>
            <div class="nav-label" style="margin-top:10px;">Account</div>
            <?php if($role === 'seller' || $role === 'buyer'): ?>
                <a href="apply-midman.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <?php endif; ?>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username']??'GU',0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($_SESSION['full_name']??$_SESSION['username']??'Guest'); ?></div>
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
                <span class="page-title">Transaction </span> <!--#<?php echo $transaction_id; ?> -->
            </div>
            <!-- <a href="<?php echo $back_link; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Back
            </a> -->
        </header>

        <div class="content">

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- STATUS HERO -->
            <div class="status-hero">
                <div class="status-top">
                    <div class="status-icon" style="background:<?php echo $st['bg']; ?>;color:<?php echo $st['color']; ?>;border-color:<?php echo $st['color']; ?>22;">
                        <i class="fas <?php echo $st['icon']; ?>"></i>
                    </div>
                    <div>
                        <div class="status-label" style="color:<?php echo $st['color']; ?>"><?php echo $st['label']; ?></div>
                        <div class="status-sub">
                            Created <?php echo date('F j, Y · g:i A', strtotime($transaction['created_at'])); ?>
                            &nbsp;·&nbsp; Transaction #<?php echo $transaction['id']; ?>
                        </div>
                    </div>
                    <?php if($transaction['status']!='disputed' && $transaction['status']!='cancelled'): ?>
                        <a href="transaction-chat.php?id=<?php echo $transaction_id; ?>" class="btn btn-teal" style="margin-left:auto;">
                            <i class="fas fa-comments"></i> Open Chat
                        </a>
                    <?php endif; ?>
                </div>

                <?php if(!in_array($transaction['status'],['disputed','cancelled'])): ?>
                <div class="progress-steps">
                    <?php
                    $step_labels = ['Pending','In Progress','Shipped','Delivered','Completed'];
                    $step_icons  = ['fa-clock','fa-lock','fa-box','fa-truck-ramp-box','fa-circle-check'];
                    foreach($steps_all as $idx => $sval):
                        $cls = $idx < $cur_step ? 'done' : ($idx == $cur_step ? 'current' : '');
                    ?>
                    <div class="pstep <?php echo $cls; ?>">
                        <div class="pstep-circle">
                            <?php if($idx < $cur_step): ?>
                                <i class="fas fa-check" style="font-size:0.7rem;"></i>
                            <?php else: ?>
                                <i class="fas <?php echo $step_icons[$idx]; ?>"></i>
                            <?php endif; ?>
                        </div>
                        <div class="pstep-label"><?php echo $step_labels[$idx]; ?></div>
                        <div class="pstep-line"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- PARTIES GRID -->
            <div class="detail-grid<?php echo $transaction['midman_id'] ? ' triple' : ''; ?>">

                <div class="panel" style="margin-bottom:0;">
                    <div class="panel-head"><div class="ph-icon ph-blue"><i class="fas fa-user"></i></div><span class="ph-title">Buyer</span></div>
                    <div class="panel-body">
                        <div class="user-card-row">
                            <div class="uca" style="background:var(--blue-dim);color:var(--blue);border-color:rgba(78,159,255,0.15);"><?php echo strtoupper(substr($transaction['buyer_name'],0,2)); ?></div>
                            <div><div class="uc-name"><?php echo htmlspecialchars($transaction['buyer_name']); ?></div><div class="uc-sub">Buyer</div></div>
                        </div>
                        <div class="info-row"><i class="fas fa-envelope"></i><a href="mailto:<?php echo htmlspecialchars($transaction['buyer_email']); ?>"><?php echo htmlspecialchars($transaction['buyer_email']); ?></a></div>
                        <?php if($transaction['buyer_phone']): ?>
                        <div class="info-row"><i class="fas fa-phone"></i><?php echo htmlspecialchars($transaction['buyer_phone']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel" style="margin-bottom:0;">
                    <div class="panel-head"><div class="ph-icon ph-teal"><i class="fas fa-store"></i></div><span class="ph-title">Seller</span></div>
                    <div class="panel-body">
                        <div class="user-card-row">
                            <div class="uca" style="background:var(--teal-dim);color:var(--teal);border-color:rgba(0,212,170,0.15);"><?php echo strtoupper(substr($transaction['seller_name'],0,2)); ?></div>
                            <div><div class="uc-name"><?php echo htmlspecialchars($transaction['seller_name']); ?></div><div class="uc-sub">Seller</div></div>
                        </div>
                        <div class="info-row"><i class="fas fa-envelope"></i><a href="mailto:<?php echo htmlspecialchars($transaction['seller_email']); ?>"><?php echo htmlspecialchars($transaction['seller_email']); ?></a></div>
                        <?php if($transaction['seller_phone']): ?>
                        <div class="info-row"><i class="fas fa-phone"></i><?php echo htmlspecialchars($transaction['seller_phone']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if($transaction['midman_id']): ?>
                <div class="panel" style="margin-bottom:0;">
                    <div class="panel-head"><div class="ph-icon ph-purple"><i class="fas fa-handshake"></i></div><span class="ph-title">Midman</span></div>
                    <div class="panel-body">
                        <div class="user-card-row">
                            <div class="uca" style="background:var(--purple-dim);color:var(--purple);border-color:rgba(160,100,255,0.15);"><?php echo strtoupper(substr($transaction['midman_name'],0,2)); ?></div>
                            <div><div class="uc-name"><?php echo htmlspecialchars($transaction['midman_name']); ?></div><div class="uc-sub">Midman</div></div>
                        </div>
                        <div class="info-row"><i class="fas fa-envelope"></i><a href="mailto:<?php echo htmlspecialchars($transaction['midman_email']); ?>"><?php echo htmlspecialchars($transaction['midman_email']); ?></a></div>
                        <?php
                        $mrd = mysqli_fetch_assoc(mysqli_query($conn,"SELECT midman_rating, total_midman_ratings FROM users WHERE id={$transaction['midman_id']}"));
                        if($mrd && $mrd['total_midman_ratings'] > 0):
                        ?>
                        <div class="info-row" style="margin-top:6px;align-items:flex-start;flex-direction:column;gap:4px;">
                            <div style="font-size:0.7rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:.08em;">Midman Rating</div>
                            <div class="midman-stars">
                                <?php for($i=1;$i<=5;$i++): ?>
                                    <i class="fas fa-star" style="color:<?php echo $i<=round($mrd['midman_rating'])?'var(--gold)':'var(--text-dim)';?>"></i>
                                <?php endfor; ?>
                                <span style="font-size:0.75rem;color:var(--text-muted);margin-left:4px;"><?php echo number_format($mrd['midman_rating'],1); ?>/5 (<?php echo $mrd['total_midman_ratings']; ?>)</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- PRODUCT + PAYMENT -->
            <div class="detail-grid" style="margin-top:18px;">
                <div class="panel" style="margin-bottom:0;">
                    <div class="panel-head"><div class="ph-icon ph-gold"><i class="fas fa-box-open"></i></div><span class="ph-title">Product</span></div>
                    <div class="panel-body">
                        <div class="product-row">
                            <div class="prod-thumb">
                                <?php if($transaction['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($transaction['image_path']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-gamepad"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="prod-title"><?php echo htmlspecialchars($transaction['product_title']); ?></div>
                                <div class="prod-desc"><?php echo htmlspecialchars($transaction['product_description']); ?></div>
                                <?php if($transaction['category']): ?>
                                    <span class="prod-cat"><?php echo htmlspecialchars($transaction['category']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel" style="margin-bottom:0;">
                    <div class="panel-head"><div class="ph-icon ph-teal"><i class="fas fa-coins"></i></div><span class="ph-title">Payment</span></div>
                    <div class="panel-body">
                        <div class="pay-row"><span class="pay-key">Product Price</span><span class="pay-val">$<?php echo number_format($transaction['amount'],2); ?></span></div>
                        <div class="pay-row"><span class="pay-key">Service Fee (5%)</span><span class="pay-val">$<?php echo number_format($transaction['service_fee'],2); ?></span></div>
                        <div class="pay-row pay-total"><span class="pay-key">Total</span><span class="pay-val">$<?php echo number_format($transaction['amount']+$transaction['service_fee'],2); ?></span></div>
                    </div>
                </div>
            </div>

            <!-- ACTIONS -->
            <?php if(!in_array($transaction['status'],['completed','cancelled'])): ?>
            <div class="panel" style="margin-top:18px;">
                <div class="panel-head"><div class="ph-icon ph-gold"><i class="fas fa-bolt"></i></div><span class="ph-title">Actions</span></div>
                <div class="panel-body">
                    <div class="actions-row">
                        <?php if($transaction['midman_id']): ?>
                            <a href="transaction-chat.php?id=<?php echo $transaction_id; ?>" class="btn btn-teal">
                                <i class="fas fa-comments"></i> Group Chat
                            </a>
                        <?php endif; ?>
                        <?php if($role=='midman' && $transaction['midman_id']==$user_id): ?>
                            <?php if($transaction['status']=='pending'): ?>
                                <form method="POST" style="display:contents;">
                                    <button type="submit" name="hold_payment" class="btn btn-gold" onclick="return confirm('Hold payment for this transaction?')">
                                        <i class="fas fa-lock"></i> Hold Payment
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if($transaction['status']=='delivered'): ?>
                                <form method="POST" style="display:contents;">
                                    <button type="submit" name="release_payment" class="btn btn-gold" onclick="return confirm('Release payment to seller?')">
                                        <i class="fas fa-coins"></i> Release Payment
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if(in_array($transaction['status'],['pending','in_progress'])): ?>
                                <form method="POST" style="display:contents;">
                                    <button type="submit" name="cancel_transaction" class="btn btn-red" onclick="return confirm('Cancel this transaction?')">
                                        <i class="fas fa-ban"></i> Cancel
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if($role=='seller' && $transaction['seller_id']==$user_id && $transaction['status']=='in_progress'): ?>
                            <form method="POST" style="display:contents;">
                                <button type="submit" name="mark_delivered" class="btn btn-blue" onclick="return confirm('Mark item as delivered?')">
                                    <i class="fas fa-box"></i> Mark Delivered
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if($role=='buyer' && $transaction['buyer_id']==$user_id && $transaction['status']=='shipped'): ?>
                            <form method="POST" style="display:contents;">
                                <button type="submit" name="confirm_receipt" class="btn btn-teal" onclick="return confirm('Confirm receipt of item?')">
                                    <i class="fas fa-circle-check"></i> Confirm Receipt
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if(!$has_dispute && (($role=='buyer' && $transaction['buyer_id']==$user_id) || ($role=='seller' && $transaction['seller_id']==$user_id))): ?>
                            <a href="raise-dispute.php?transaction_id=<?php echo $transaction_id; ?>" class="btn btn-orange">
                                <i class="fas fa-triangle-exclamation"></i> Raise Dispute
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- RATE CTA -->
            <?php if($transaction['status']=='completed' && $transaction['midman_id'] && in_array($role,['buyer','seller'])): ?>
            <div class="rate-cta" style="margin-top:18px;">
                <div class="rate-cta-inner">
                    <div class="rate-stars">★★★★★</div>
                    <div class="rate-cta-title">Rate Your Midman</div>
                    <div class="rate-cta-sub">How was your experience with <?php echo htmlspecialchars($transaction['midman_name']); ?>? Your feedback helps the community.</div>
                    <?php if(!$has_rated): ?>
                        <a href="rate-midman.php?id=<?php echo $transaction_id; ?>" class="btn btn-gold" style="margin:0 auto;">
                            <i class="fas fa-star"></i> Leave a Rating
                        </a>
                    <?php else: ?>
                        <div class="already-rated-box">
                            <i class="fas fa-circle-check"></i> You've already rated this midman — thank you!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- DISPUTE INFO -->
            <?php if($has_dispute): ?>
            <div class="dispute-panel" style="margin-top:18px;">
                <div class="dispute-head"><i class="fas fa-triangle-exclamation" style="color:var(--red);"></i><span class="dispute-head-title">Dispute Details</span></div>
                <div class="dispute-body">
                    <div class="disp-row"><span class="disp-key">Raised By</span><span class="disp-val"><?php echo $dispute['raised_by']==$transaction['buyer_id']?'Buyer':'Seller'; ?></span></div>
                    <div class="disp-row"><span class="disp-key">Reason</span><span class="disp-val"><?php echo htmlspecialchars($dispute['reason']); ?></span></div>
                    <div class="disp-row"><span class="disp-key">Description</span><span class="disp-val"><?php echo nl2br(htmlspecialchars($dispute['description'])); ?></span></div>
                    <div class="disp-row"><span class="disp-key">Status</span><span class="disp-val" style="color:var(--red);font-weight:700;"><?php echo ucfirst($dispute['status']); ?></span></div>
                    <?php if($role=='admin'): ?>
                        <a href="admin/disputes.php?id=<?php echo $dispute['id']; ?>" class="btn btn-red" style="margin-top:10px;"><i class="fas fa-gavel"></i> Manage Dispute</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if($role === 'admin'): ?>
                <a href="admin/disputes.php" class="btn btn-ghost" style="margin-top:4px;">
                    <i class="fas fa-arrow-left"></i> Back to Disputes
                </a>
            <?php else: ?>
                <a href="my-transactions.php" class="btn btn-ghost" style="margin-top:4px;">
                    <i class="fas fa-arrow-left"></i> Back to Transactions
                </a>
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