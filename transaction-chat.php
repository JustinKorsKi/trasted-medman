<?php
require_once 'includes/config.php';
require_once 'includes/2fa-functions.php'; // needed for 2FA status

if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id        = $_SESSION['user_id'];
$my_role        = $_SESSION['role'] ?? 'buyer';
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if(!$transaction_id) { header('Location: my-transactions.php'); exit(); }

// 2FA status for midman
$two_factor_enabled = false;
if($my_role == 'midman') {
    $two_factor_enabled = is2FAEnabled($user_id);
}

$query = "SELECT t.*, p.title,
          b.username as buyer_name, b.id as buyer_id,
          s.username as seller_name, s.id as seller_id,
          m.username as midman_name, m.id as midman_id,
          gc.id as chat_id
          FROM transactions t
          JOIN products p ON t.product_id = p.id
          JOIN users b ON t.buyer_id = b.id
          JOIN users s ON t.seller_id = s.id
          LEFT JOIN users m ON t.midman_id = m.id
          LEFT JOIN group_chats gc ON gc.transaction_id = t.id
          WHERE t.id = $transaction_id
          AND (t.buyer_id=$user_id OR t.seller_id=$user_id OR t.midman_id=$user_id)";

$result = mysqli_query($conn, $query);
if(mysqli_num_rows($result) == 0) { header('Location: my-transactions.php'); exit(); }
$transaction = mysqli_fetch_assoc($result);

if(!$transaction['chat_id'] && $transaction['midman_id']) {
    mysqli_query($conn, "INSERT INTO group_chats (transaction_id) VALUES ($transaction_id)");
    $chat_id = mysqli_insert_id($conn);
    foreach([$transaction['buyer_id'], $transaction['seller_id'], $transaction['midman_id']] as $pid)
        mysqli_query($conn, "INSERT INTO group_chat_participants (group_chat_id, user_id) VALUES ($chat_id, $pid)");
    $result = mysqli_query($conn, $query);
    $transaction = mysqli_fetch_assoc($result);
}

$upload_dir = 'uploads/chat/';
if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message    = mysqli_real_escape_string($conn, $_POST['message'] ?? '');
    $chat_id    = $transaction['chat_id'];
    $file_path  = $file_name = $file_type = '';
    $file_size  = 0;
    $post_error = '';

    if(isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $allowed   = ['jpg','jpeg','png','gif','pdf','txt','doc','docx'];
        $orig      = $_FILES['file']['name'];
        $ext       = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $file_size = $_FILES['file']['size'];
        if($file_size > 5 * 1024 * 1024) {
            $post_error = 'File too large. Maximum size is 5 MB.';
        } elseif(!in_array($ext, $allowed)) {
            $post_error = 'File type not allowed. Accepted: JPG, PNG, GIF, PDF, TXT, DOC.';
        } else {
            $new_fn = time().'_'.uniqid().'.'.$ext;
            if(move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir.$new_fn)) {
                $file_path = mysqli_real_escape_string($conn, $upload_dir.$new_fn);
                $file_name = mysqli_real_escape_string($conn, $orig);
                $file_type = $ext;
            }
        }
    }

    if(empty($post_error) && (!empty(trim($message)) || !empty($file_path))) {
        $cols = 'group_chat_id, user_id, message';
        $vals = "$chat_id, $user_id, '$message'";
        if($file_path) {
            $cols .= ', file_path, file_name, file_size, file_type';
            $vals .= ", '$file_path', '$file_name', $file_size, '$file_type'";
        }
        mysqli_query($conn, "INSERT INTO chat_messages ($cols) VALUES ($vals)");
        foreach([$transaction['buyer_id'],$transaction['seller_id'],$transaction['midman_id']] as $pid) {
            if($pid && $pid != $user_id) {
                $chk = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
                if(mysqli_num_rows($chk) > 0)
                    mysqli_query($conn, "INSERT INTO notifications (user_id,type,title,message,link)
                        VALUES ($pid,'chat','New message','New message in transaction #$transaction_id','transaction-chat.php?id=$transaction_id')");
            }
        }
    }
    header("Location: transaction-chat.php?id=$transaction_id"); exit();
}

$messages = mysqli_query($conn,
    "SELECT cm.*, u.username, u.role
     FROM chat_messages cm
     JOIN users u ON cm.user_id = u.id
     WHERE cm.group_chat_id = {$transaction['chat_id']}
     ORDER BY cm.created_at ASC");

$role_colors = ['buyer'=>'var(--blue)','seller'=>'var(--teal)','midman'=>'var(--purple)'];
$role_bgs    = ['buyer'=>'var(--blue-dim)','seller'=>'var(--teal-dim)','midman'=>'var(--purple-dim)'];
$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Chat — Trusted Midman</title>
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

        html, body { height:100%; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); overflow:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; height:100vh; }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; height:100vh; flex-shrink:0; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); position:relative; }
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
        .security-badge { margin-left:auto; background:var(--teal-dim); color:var(--teal); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(0,212,170,0.15); }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px var(--accent-glow); }
        .pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .pill-role { font-size:0.68rem; color:var(--accent); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN SHELL ── */
        .main { flex:1; display:flex; flex-direction:column; min-width:0; height:100vh; overflow:hidden; }

        /* ── TOPBAR ── */
        .topbar { background:rgba(15,12,8,0.92); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 24px; height:64px; display:flex; align-items:center; justify-content:space-between; flex-shrink:0; gap:12px; }
        .topbar-left { display:flex; align-items:center; gap:12px; min-width:0; flex:1; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; flex-shrink:0; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .chat-title { font-family:var(--font-head); font-size:1rem; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:320px; letter-spacing:-0.01em; }
        .chat-sub   { font-size:0.72rem; color:var(--text-muted); margin-top:1px; }
        .topbar-right { display:flex; align-items:center; gap:10px; flex-shrink:0; }

        /* participant badges */
        .participants { display:flex; gap:5px; align-items:center; }
        .part-badge { display:inline-flex; align-items:center; gap:4px; font-size:0.65rem; font-weight:700; padding:3px 8px; border-radius:20px; border:1px solid transparent; }
        .pb-buyer  { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.15); }
        .pb-seller { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.15); }
        .pb-midman { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.15); }

        .back-link { font-size:0.78rem; color:var(--text-muted); text-decoration:none; display:flex; align-items:center; gap:5px; white-space:nowrap; transition:color 0.2s; }
        .back-link:hover { color:var(--accent); }

        /* ── MESSAGES AREA ── */
        .messages-area { flex:1; overflow-y:auto; padding:20px 24px; display:flex; flex-direction:column; gap:16px; }

        /* date divider */
        .date-divider { display:flex; align-items:center; gap:10px; margin:4px 0; }
        .date-divider::before, .date-divider::after { content:''; flex:1; height:1px; background:var(--border2); }
        .date-divider span { font-size:0.65rem; font-weight:700; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.1em; white-space:nowrap; }

        /* empty state */
        .empty-chat { flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; color:var(--text-dim); text-align:center; }
        .ec-icon { width:56px; height:56px; border-radius:50%; background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; font-size:1.4rem; margin-bottom:4px; }
        .empty-chat p   { font-size:0.84rem; }
        .empty-chat .tip { font-size:0.76rem; color:var(--text-dim); max-width:280px; line-height:1.6; }

        /* message rows */
        .msg-row { display:flex; gap:8px; align-items:flex-end; max-width:78%; }
        .msg-row.me   { margin-left:auto; flex-direction:row-reverse; }
        .msg-row.them { margin-right:auto; }

        .msg-ava { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.62rem; flex-shrink:0; margin-bottom:2px; }

        .msg-body { display:flex; flex-direction:column; gap:2px; min-width:0; }
        .msg-meta { font-size:0.65rem; color:var(--text-dim); display:flex; align-items:center; gap:5px; }
        .msg-row.me .msg-meta { justify-content:flex-end; }
        .msg-sender { font-weight:700; }

        /* bubbles */
        .msg-bubble { padding:10px 14px; border-radius:14px; font-size:0.875rem; line-height:1.6; word-break:break-word; position:relative; }
        .msg-row.me   .msg-bubble { background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); color:#0f0c08; border-radius:14px 14px 4px 14px; box-shadow:0 2px 10px var(--accent-glow); }
        .msg-row.them .msg-bubble { background:var(--surface2); border:1px solid var(--border2); color:var(--text-warm); border-radius:14px 14px 14px 4px; }

        /* file attachments */
        .file-attach { margin-top:8px; border-radius:10px; overflow:hidden; }
        .img-preview { display:block; max-width:220px; max-height:180px; border-radius:10px; cursor:pointer; transition:opacity 0.2s; object-fit:cover; }
        .img-preview:hover { opacity:0.85; }
        .file-card { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; background:rgba(0,0,0,0.18); }
        .msg-row.them .file-card { background:var(--bg); border:1px solid var(--border); }
        .fc-icon  { width:34px; height:34px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0; }
        .fc-pdf   { background:var(--red-dim);    color:var(--red); }
        .fc-doc   { background:var(--blue-dim);   color:var(--blue); }
        .fc-txt   { background:var(--teal-dim);   color:var(--teal); }
        .fc-other { background:var(--surface2);   color:var(--text-muted); }
        .fc-info  { flex:1; min-width:0; }
        .fc-name  { font-size:0.8rem; font-weight:600; color:var(--text-warm); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:160px; }
        .fc-size  { font-size:0.65rem; color:var(--text-dim); margin-top:1px; }
        .fc-dl    { width:28px; height:28px; border-radius:7px; background:rgba(255,255,255,0.08); color:inherit; display:flex; align-items:center; justify-content:center; font-size:0.72rem; text-decoration:none; flex-shrink:0; transition:background 0.2s; }
        .fc-dl:hover { background:rgba(255,255,255,0.18); }

        /* ── INPUT AREA ── */
        .input-area { padding:14px 20px 16px; border-top:1px solid var(--border); background:var(--surface); flex-shrink:0; }

        .file-pill { display:none; align-items:center; gap:8px; padding:6px 10px; background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); margin-bottom:10px; font-size:0.78rem; color:var(--text-muted); }
        .file-pill.visible { display:flex; }
        .fp-name   { flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .fp-remove { background:none; border:none; color:var(--text-dim); cursor:pointer; font-size:0.72rem; padding:2px; transition:color 0.2s; flex-shrink:0; }
        .fp-remove:hover { color:var(--red); }

        .input-row { display:flex; gap:8px; align-items:flex-end; }
        .msg-input {
            flex:1; padding:11px 14px; background:var(--surface2);
            border:1px solid var(--border); border-radius:var(--radius-sm);
            color:var(--text-warm); font-family:var(--font-body); font-size:0.9rem;
            resize:none; min-height:44px; max-height:120px; line-height:1.5;
            transition:border-color 0.2s, box-shadow 0.2s; outline:none;
        }
        .msg-input::placeholder { color:var(--text-dim); }
        .msg-input:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-dim); background:var(--surface3); }

        .attach-btn {
            width:44px; height:44px; background:var(--surface2); border:1px solid var(--border2);
            border-radius:var(--radius-sm); color:var(--text-muted);
            display:flex; align-items:center; justify-content:center;
            cursor:pointer; transition:all 0.2s; flex-shrink:0;
            position:relative; overflow:hidden;
        }
        .attach-btn:hover { border-color:var(--accent); color:var(--accent); }
        .attach-btn input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }

        .send-btn {
            width:44px; height:44px;
            background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end));
            color:#0f0c08; border:none; border-radius:var(--radius-sm);
            cursor:pointer; display:flex; align-items:center; justify-content:center;
            font-size:0.95rem; flex-shrink:0;
            transition:all 0.22s; box-shadow:0 3px 12px var(--accent-glow);
        }
        .send-btn:hover { background:linear-gradient(135deg,var(--accent-lt),var(--accent)); transform:translateY(-2px); box-shadow:0 6px 20px var(--accent-glow); }

        /* live indicator */
        .refresh-bar { padding:5px 24px; background:rgba(0,212,170,0.05); border-bottom:1px solid var(--border); display:flex; align-items:center; gap:6px; font-size:0.68rem; color:var(--text-dim); flex-shrink:0; }
        .refresh-dot { width:6px; height:6px; border-radius:50%; background:var(--teal); animation:pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;}50%{opacity:0.3;} }

        /* misc */
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }

        @media(max-width:820px) { .sidebar{position:fixed;transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .hamburger{display:flex;} }
        @media(max-width:540px) { .participants{display:none;} .chat-title{max-width:200px;} .msg-row{max-width:90%;} .messages-area{padding:14px 12px;} .input-area{padding:10px 12px 12px;} }
    </style>
</head>
<body class="role-<?php echo $my_role; ?>">
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
            <?php if($my_role === 'seller'): ?>
                <div class="nav-label">Seller</div>
                <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
                <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
                <a href="my-transactions.php"  class="nav-link  active"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales</a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php elseif($my_role === 'midman'): ?>
                <div class="nav-label">Midman</div>
                <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-handshake"></i></span>Transactions</a>
                <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
                <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> KYC Status</a>setup
                <!-- Security section for midman -->
                <div class="nav-label" style="margin-top:10px;">Security</div>
                <a href="setup-2fa.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
                    <?php if($two_factor_enabled): ?><span class="security-badge">Active</span><?php endif; ?>
                </a>
            <?php else: ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <a href="my-transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php endif; ?>

            <div class="nav-label" style="margin-top:10px;">Account</div>
            <?php if($my_role !== 'midman'): ?>
                <a href="apply-midman.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username']??'GU',0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role"><?php echo ucfirst($my_role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <div>
                    <div class="chat-title"><?php echo htmlspecialchars($transaction['title']); ?></div>
                    <div class="chat-sub">Transaction #<?php echo $transaction_id; ?></div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="participants">
                    <span class="part-badge pb-buyer"><i class="fas fa-user" style="font-size:0.55rem;"></i> <?php echo htmlspecialchars($transaction['buyer_name']); ?></span>
                    <span class="part-badge pb-seller"><i class="fas fa-store" style="font-size:0.55rem;"></i> <?php echo htmlspecialchars($transaction['seller_name']); ?></span>
                    <?php if($transaction['midman_name']): ?>
                    <span class="part-badge pb-midman"><i class="fas fa-handshake" style="font-size:0.55rem;"></i> <?php echo htmlspecialchars($transaction['midman_name']); ?></span>
                    <?php endif; ?>
                </div>
                <a href="transaction-detail.php?id=<?php echo $transaction_id; ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Details
                </a>
            </div>
        </header>

        <div class="refresh-bar">
            <span class="refresh-dot"></span>
            Live chat — refreshes every 5 seconds
        </div>

        <!-- MESSAGES -->
        <div class="messages-area" id="messagesArea">
            <?php
            $msg_count = mysqli_num_rows($messages);
            if($msg_count == 0):
            ?>
            <div class="empty-chat">
                <div class="ec-icon"><i class="fas fa-comments"></i></div>
                <p>No messages yet</p>
                <span class="tip">
                    <?php if($user_id == $transaction['buyer_id']): ?>
                        Start the conversation — or upload your payment receipt once you've sent payment.
                    <?php else: ?>
                        Be the first to send a message in this transaction.
                    <?php endif; ?>
                </span>
            </div>
            <?php else:
                $prev_date = null;
                while($msg = mysqli_fetch_assoc($messages)):
                    $is_me     = $msg['user_id'] == $user_id;
                    $role      = $msg['role'];
                    $clr       = $role_colors[$role] ?? 'var(--text-muted)';
                    $bg        = $role_bgs[$role]    ?? 'var(--surface2)';
                    $msg_date  = date('Y-m-d', strtotime($msg['created_at']));
                    $today     = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));

                    if($msg_date !== $prev_date):
                        $label = $msg_date === $today ? 'Today' : ($msg_date === $yesterday ? 'Yesterday' : date('F j, Y', strtotime($msg_date)));
            ?>
                <div class="date-divider"><span><?php echo $label; ?></span></div>
            <?php
                    endif;
                    $prev_date = $msg_date;

                    $has_file      = !empty($msg['file_path']);
                    $is_image      = $has_file && in_array($msg['file_type'] ?? '', ['jpg','jpeg','png','gif']);
                    $file_size_fmt = '';
                    if($has_file && !empty($msg['file_size'])) {
                        $file_size_fmt = $msg['file_size'] > 1048576
                            ? round($msg['file_size']/1048576,1).' MB'
                            : round($msg['file_size']/1024,1).' KB';
                    }
                    $fc_class = match($msg['file_type'] ?? '') {
                        'pdf'        => 'fc-pdf',
                        'doc','docx' => 'fc-doc',
                        'txt'        => 'fc-txt',
                        default      => 'fc-other'
                    };
                    $fc_icon = match($msg['file_type'] ?? '') {
                        'pdf'        => 'fa-file-pdf',
                        'doc','docx' => 'fa-file-word',
                        'txt'        => 'fa-file-lines',
                        default      => 'fa-paperclip'
                    };
            ?>
                <div class="msg-row <?php echo $is_me ? 'me' : 'them'; ?>">
                    <?php if(!$is_me): ?>
                    <div class="msg-ava" style="background:<?php echo $bg; ?>;color:<?php echo $clr; ?>;">
                        <?php echo strtoupper(substr($msg['username'],0,2)); ?>
                    </div>
                    <?php endif; ?>
                    <div class="msg-body">
                        <div class="msg-meta">
                            <?php if(!$is_me): ?>
                                <span class="msg-sender" style="color:<?php echo $clr; ?>"><?php echo htmlspecialchars($msg['username']); ?></span>
                                <span style="color:var(--text-dim);">·</span>
                                <span><?php echo ucfirst($msg['role']); ?></span>
                                <span style="color:var(--text-dim);">·</span>
                            <?php endif; ?>
                            <span><?php echo date('h:i A', strtotime($msg['created_at'])); ?></span>
                        </div>
                        <div class="msg-bubble">
                            <?php if(!empty($msg['message'])): ?>
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            <?php endif; ?>
                            <?php if($has_file): ?>
                            <div class="file-attach">
                                <?php if($is_image): ?>
                                    <img src="<?php echo htmlspecialchars($msg['file_path']); ?>"
                                         alt="<?php echo htmlspecialchars($msg['file_name'] ?? 'Image'); ?>"
                                         class="img-preview"
                                         onclick="window.open(this.src,'_blank')"
                                         title="Click to open full size">
                                <?php else: ?>
                                    <div class="file-card">
                                        <div class="fc-icon <?php echo $fc_class; ?>"><i class="fas <?php echo $fc_icon; ?>"></i></div>
                                        <div class="fc-info">
                                            <div class="fc-name"><?php echo htmlspecialchars($msg['file_name'] ?? 'File'); ?></div>
                                            <?php if($file_size_fmt): ?><div class="fc-size"><?php echo $file_size_fmt; ?></div><?php endif; ?>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($msg['file_path']); ?>" download class="fc-dl" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; endif; ?>
        </div>

        <!-- INPUT -->
        <div class="input-area">
            <form method="POST" enctype="multipart/form-data" id="chatForm">
                <div class="file-pill" id="filePill">
                    <i class="fas fa-paperclip" style="font-size:0.72rem;color:var(--text-dim);flex-shrink:0;"></i>
                    <span class="fp-name" id="fpName"></span>
                    <button type="button" class="fp-remove" onclick="clearFile()" title="Remove"><i class="fas fa-xmark"></i></button>
                </div>
                <div class="input-row">
                    <textarea
                        name="message"
                        id="msgInput"
                        class="msg-input"
                        placeholder="Type a message… (Enter to send, Shift+Enter for newline)"
                        rows="1"></textarea>
                    <div class="attach-btn" title="Attach file">
                        <i class="fas fa-paperclip"></i>
                        <input type="file" name="file" id="fileInput" accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.doc,.docx">
                    </div>
                    <button type="submit" class="send-btn" title="Send">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
    const ham = document.getElementById('hamburger');
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('overlay');
    ham.addEventListener('click', () => { sb.classList.toggle('open'); ov.classList.toggle('visible'); });
    ov.addEventListener('click',  () => { sb.classList.remove('open'); ov.classList.remove('visible'); });

    const area = document.getElementById('messagesArea');
    area.scrollTop = area.scrollHeight;

    const inp = document.getElementById('msgInput');
    inp.addEventListener('input', () => {
        inp.style.height = 'auto';
        inp.style.height = Math.min(inp.scrollHeight, 120) + 'px';
    });
    inp.addEventListener('keydown', e => {
        if(e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if(inp.value.trim() || document.getElementById('fileInput').files.length > 0)
                document.getElementById('chatForm').submit();
        }
    });

    const fileInput = document.getElementById('fileInput');
    const filePill  = document.getElementById('filePill');
    const fpName    = document.getElementById('fpName');

    fileInput.addEventListener('change', () => {
        if(fileInput.files.length > 0) {
            fpName.textContent = fileInput.files[0].name;
            filePill.classList.add('visible');
        } else clearFile();
    });

    function clearFile() {
        fileInput.value = '';
        filePill.classList.remove('visible');
        fpName.textContent = '';
    }

    setTimeout(() => location.reload(), 5000);
</script>
</body>
</html>