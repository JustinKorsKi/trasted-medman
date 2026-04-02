<?php
require_once 'includes/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header('Location: login.php'); exit();
}

$seller_id  = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$pq = mysqli_query($conn, "SELECT * FROM products WHERE id=$product_id AND seller_id=$seller_id");
if(mysqli_num_rows($pq) == 0) {
    $_SESSION['error'] = 'Product not found or you do not have permission to edit it.';
    header('Location: my-products.php'); exit();
}
$product = mysqli_fetch_assoc($pq);

$error = ''; $success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title         = mysqli_real_escape_string($conn, $_POST['title']);
    $description   = mysqli_real_escape_string($conn, $_POST['description']);
    $price         = mysqli_real_escape_string($conn, $_POST['price']);
    $game_name     = mysqli_real_escape_string($conn, $_POST['game_name']);
    $item_type     = mysqli_real_escape_string($conn, $_POST['item_type']);
    $account_level = !empty($_POST['account_level']) ? intval($_POST['account_level']) : 'NULL';
    $account_rank  = mysqli_real_escape_string($conn, $_POST['account_rank']);
    $server_region = mysqli_real_escape_string($conn, $_POST['server_region']);
    $status        = mysqli_real_escape_string($conn, $_POST['status']);

    if(empty($title)||empty($description)||empty($price)||empty($game_name)||empty($item_type)) {
        $error = 'Please fill in all required fields.';
    } elseif(!is_numeric($price)||$price<=0) {
        $error = 'Please enter a valid price.';
    } else {
        $image_path = $product['image_path'];
        if(isset($_FILES['image']) && $_FILES['image']['error']==0) {
            $allowed = ['jpg','jpeg','png','gif'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if(in_array($ext,$allowed)) {
                $upload_dir = 'uploads/';
                if(!file_exists($upload_dir)) mkdir($upload_dir,0777,true);
                $new_fn = time().'_'.uniqid().'.'.$ext;
                $target = $upload_dir.$new_fn;
                if(move_uploaded_file($_FILES['image']['tmp_name'],$target)) {
                    if(!empty($product['image_path'])&&file_exists($product['image_path'])) unlink($product['image_path']);
                    $image_path = $target;
                } else { $error='Failed to upload image.'; }
            } else { $error='Only JPG, PNG, and GIF files are allowed.'; }
        }
        if(empty($error)) {
            $al_sql = $account_level=='NULL' ? 'account_level=NULL' : "account_level=$account_level";
            $q = "UPDATE products SET title='$title',description='$description',price=$price,
                  game_name='$game_name',item_type='$item_type',$al_sql,
                  account_rank='$account_rank',server_region='$server_region',
                  status='$status',image_path='$image_path'
                  WHERE id=$product_id AND seller_id=$seller_id";
            if(mysqli_query($conn,$q)) {
                $success = 'Product updated successfully!';
                $pq = mysqli_query($conn, "SELECT * FROM products WHERE id=$product_id AND seller_id=$seller_id");
                $product = mysqli_fetch_assoc($pq);
            } else { $error = 'Failed to update: '.mysqli_error($conn); }
        }
    }
}

$games = ['League of Legends','Valorant','Mobile Legends','PUBG Mobile','Call of Duty Mobile','Genshin Impact','Dota 2','CS:GO','Fortnite','Apex Legends','Roblox','Minecraft','FIFA Mobile','Other'];
$item_types = ['account'=>['icon'=>'fa-gamepad','label'=>'Game Account'],'currency'=>['icon'=>'fa-coins','label'=>'In-game Currency'],'item'=>['icon'=>'fa-box-open','label'=>'Item / Skin'],'service'=>['icon'=>'fa-bolt','label'=>'Boosting Service']];
$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        /* ----- GLOBAL & VARIABLES (same as seller dashboard) ----- */
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

        /* ── SIDEBAR (same as dashboard) ── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
            position: fixed; top: 0; left: 0; height: 100vh;
            z-index: 100;
            transition: transform 0.35s cubic-bezier(.77,0,.18,1);
        }

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

        /* ── MAIN CONTENT ── */
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

        /* ── ANIMATIONS ── */
        @keyframes fadeUp { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }

        /* ── PANELS (same as dashboard) ── */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            opacity: 0;
            animation: fadeUp 0.45s ease forwards;
            margin-bottom: 20px;
        }
        .panel-head {
            display: flex; align-items: center; gap: 10px;
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
        .panel-body { padding: 20px; }

        /* Icons for panel titles (matches dashboard) */
        .pti-gold   { background: var(--gold-dim);   color: var(--gold); }
        .pti-teal   { background: var(--teal-dim);   color: var(--teal); }
        .pti-blue   { background: var(--blue-dim);   color: var(--blue); }
        .pti-purple { background: var(--purple-dim); color: var(--purple); }

        /* ── FORM ELEMENTS (styled to match dashboard) ── */
        .field { margin-bottom: 18px; }
        .field:last-child { margin-bottom: 0; }
        .field-label {
            display: block; font-size: 0.78rem; font-weight: 600;
            color: var(--text-muted); margin-bottom: 7px;
            letter-spacing: 0.03em;
        }
        .req { color: var(--red); margin-left: 2px; }
        .field-input, .field-select, .field-textarea {
            width: 100%;
            padding: 11px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-warm);
            font-family: var(--font-body);
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .field-input::placeholder, .field-textarea::placeholder {
            color: var(--text-dim);
        }
        .field-input:focus, .field-select:focus, .field-textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-dim);
        }
        .field-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23a89880' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }
        .field-select option { background: var(--surface2); }
        .field-textarea { resize: vertical; min-height: 110px; line-height: 1.6; }
        .field-hint {
            font-size: 0.72rem;
            color: var(--text-dim);
            margin-top: 5px;
        }
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        /* Type cards (radio buttons styled) */
        .type-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .type-card {
            position: relative;
        }
        .type-card input {
            position: absolute;
            opacity: 0;
            width: 0;
        }
        .type-label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        .type-label:hover {
            border-color: var(--border2);
            color: var(--text-warm);
        }
        .type-ico {
            width: 26px; height: 26px; border-radius: 7px;
            background: var(--surface);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.72rem;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .type-card input:checked + .type-label {
            border-color: var(--gold);
            background: var(--gold-dim);
            color: var(--gold);
        }
        .type-card input:checked + .type-label .type-ico {
            background: var(--gold);
            color: #0f0c08;
        }

        /* Status toggle */
        .status-toggle {
            display: flex;
            gap: 8px;
        }
        .st-card {
            position: relative;
            flex: 1;
        }
        .st-card input {
            position: absolute;
            opacity: 0;
            width: 0;
        }
        .st-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface2);
            cursor: pointer;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            transition: all 0.2s;
        }
        .st-card input:checked + .st-label.available {
            border-color: var(--teal);
            background: var(--teal-dim);
            color: var(--teal);
        }
        .st-card input:checked + .st-label.sold {
            border-color: var(--text-dim);
            background: var(--surface2);
            color: var(--text-warm);
        }

        /* Price prefix */
        .price-wrap {
            position: relative;
        }
        .price-prefix {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gold);
            font-family: var(--font-head);
            font-weight: 700;
        }
        .price-wrap .field-input {
            padding-left: 26px;
        }

        /* Image area */
        .img-current {
            border: 1px dashed var(--border2);
            border-radius: var(--radius-sm);
            padding: 16px;
            text-align: center;
            margin-bottom: 14px;
            background: var(--surface2);
        }
        .img-current img {
            width: 100%;
            max-height: 160px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .img-no {
            padding: 24px;
            color: var(--text-dim);
            text-align: center;
        }
        .img-no i {
            font-size: 2rem;
            display: block;
            margin-bottom: 8px;
        }
        .img-caption {
            font-size: 0.72rem;
            color: var(--text-dim);
            margin-top: 8px;
        }

        .upload-zone {
            border: 2px dashed var(--border2);
            border-radius: var(--radius-sm);
            padding: 24px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: var(--gold);
            background: var(--gold-dim);
        }
        .upload-zone input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        .upload-icon {
            font-size: 1.5rem;
            color: var(--text-dim);
            margin-bottom: 6px;
        }
        .upload-text {
            font-size: 0.82rem;
            color: var(--text-muted);
        }
        .upload-hint {
            font-size: 0.7rem;
            color: var(--text-dim);
            margin-top: 3px;
        }
        #newPreviewWrap {
            display: none;
            margin-top: 12px;
        }
        #newPreviewWrap img {
            width: 100%;
            max-height: 130px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        /* Preview card */
        .preview-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px;
            margin-bottom: 14px;
        }
        .pc-thumb {
            width: 100%;
            height: 80px;
            border-radius: 8px;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--text-dim);
            overflow: hidden;
            margin-bottom: 10px;
        }
        .pc-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .pc-title {
            font-family: var(--font-head);
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 3px;
        }
        .pc-game {
            font-size: 0.68rem;
            font-weight: 700;
            background: var(--gold-dim);
            color: var(--gold);
            padding: 2px 7px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 8px;
        }
        .pc-price {
            font-family: var(--font-head);
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--gold);
        }

        /* Danger zone */
        .danger-zone {
            background: var(--red-dim);
            border: 1px solid rgba(255,77,109,0.2);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-top: 14px;
        }
        .dz-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--red);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .dz-sub {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 12px;
        }

        /* Buttons (same as dashboard) */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 7px; padding: 11px 18px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.875rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: none;
            transition: all 0.22s ease; white-space: nowrap;
        }
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), #d48500);
            color: #0f0c08; font-weight: 700;
            box-shadow: 0 3px 14px var(--gold-glow);
            width: 100%;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, var(--gold-lt), var(--gold));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--gold-glow);
        }
        .btn-ghost {
            background: var(--surface2);
            color: var(--text-muted);
            border: 1px solid var(--border2);
            width: 100%;
            margin-top: 8px;
        }
        .btn-ghost:hover {
            color: var(--text-warm);
            border-color: var(--border3);
        }
        .btn-red {
            background: var(--red-dim);
            color: var(--red);
            border: 1px solid rgba(255,77,109,0.25);
            width: 100%;
            margin-top: 6px;
        }
        .btn-red:hover {
            background: var(--red);
            color: white;
        }
        .btn-sm {
            padding: 7px 13px;
            font-size: 0.8rem;
            width: auto;
        }

        /* Alerts */
        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
        .alert-success {
            background: var(--teal-dim);
            border: 1px solid rgba(0,212,170,0.22);
            color: var(--teal);
        }
        .alert-error {
            background: var(--red-dim);
            border: 1px solid rgba(255,77,109,0.22);
            color: var(--red);
        }

        /* Layout for form columns */
        .form-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 22px;
            align-items: start;
        }

        /* Overlay & responsiveness */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 99;
            backdrop-filter: blur(4px);
        }
        .sidebar-overlay.visible { display: block; }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track  { background: var(--bg); }
        ::-webkit-scrollbar-thumb  { background: var(--surface3); border-radius: 3px; }

        @media(max-width:1000px) {
            .form-layout { grid-template-columns: 1fr; }
        }
        @media(max-width:820px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .hamburger { display: flex; }
            .content { padding: 20px 16px; }
        }
        @media(max-width:540px) {
            .field-row { grid-template-columns: 1fr; }
            .type-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

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
            <a href="my-products.php" class="nav-link active"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
            <a href="add-product.php" class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
            <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> Transactions</a>
            <a href="my-sales.php" class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales</a>
            <a href="seller-earnings.php" class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
            <a href="raise-dispute.php" class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <div class="nav-label" style="margin-top:10px;">Account</div>
            <a href="apply-midman.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php" class="nav-link" style="color:var(--text-dim);margin-top:6px;">
                <span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role">Seller</div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Edit Product</span>
            </div>
            <div class="online-dot">Online</div>
        </header>

        <div class="content">

            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
            <div class="form-layout">

                <!-- LEFT COLUMN -->
                <div>
                    <!-- BASIC INFO -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <div class="panel-title-icon pti-gold"><i class="fas fa-pen"></i></div>
                                Basic Information
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="field">
                                <label class="field-label">Game <span class="req">*</span></label>
                                <select name="game_name" class="field-select" id="selGame" required>
                                    <option value="">Select a game…</option>
                                    <?php foreach($games as $g): ?>
                                    <option value="<?php echo $g; ?>" <?php echo $product['game_name']==$g?'selected':''; ?>><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label class="field-label">Item Type <span class="req">*</span></label>
                                <div class="type-grid" id="itemTypeGrid">
                                    <?php foreach($item_types as $val=>$t): ?>
                                    <div class="type-card">
                                        <input type="radio" name="item_type" value="<?php echo $val; ?>" id="type_<?php echo $val; ?>" <?php echo $product['item_type']==$val?'checked':''; ?> required>
                                        <label class="type-label" for="type_<?php echo $val; ?>">
                                            <div class="type-ico"><i class="fas <?php echo $t['icon']; ?>"></i></div>
                                            <?php echo $t['label']; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="field">
                                <label class="field-label" for="titleInput">Listing Title <span class="req">*</span></label>
                                <input type="text" name="title" id="titleInput" class="field-input" value="<?php echo htmlspecialchars($product['title']); ?>" required>
                            </div>

                            <div class="field">
                                <label class="field-label">Description <span class="req">*</span></label>
                                <textarea name="description" class="field-textarea" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- GAMING DETAILS (Account Details) -->
                    <div class="panel" id="accountDetailsPanel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <div class="panel-title-icon pti-blue"><i class="fas fa-sliders"></i></div>
                                Account Details <span style="font-size:0.7rem; color:var(--text-dim); margin-left:4px;">(optional)</span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="field-row">
                                <div class="field">
                                    <label class="field-label">Account Level</label>
                                    <input type="number" name="account_level" class="field-input" value="<?php echo htmlspecialchars($product['account_level']??''); ?>" placeholder="e.g., 30">
                                </div>
                                <div class="field">
                                    <label class="field-label">Rank</label>
                                    <input type="text" name="account_rank" class="field-input" value="<?php echo htmlspecialchars($product['account_rank']??''); ?>" placeholder="e.g., Diamond">
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-label">Server / Region</label>
                                <input type="text" name="server_region" class="field-input" value="<?php echo htmlspecialchars($product['server_region']??''); ?>" placeholder="e.g., NA, EU, SEA">
                                <div class="field-hint">Specify the server or region of the account/item.</div>
                            </div>
                        </div>
                    </div>

                    <!-- PRICE & STATUS -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <div class="panel-title-icon pti-teal"><i class="fas fa-tag"></i></div>
                                Price & Status
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="field-row">
                                <div class="field">
                                    <label class="field-label">Price (USD) <span class="req">*</span></label>
                                    <div class="price-wrap">
                                        <span class="price-prefix">$</span>
                                        <input type="number" name="price" id="priceInput" class="field-input" step="0.01" min="0.01" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Listing Status</label>
                                    <div class="status-toggle">
                                        <div class="st-card">
                                            <input type="radio" name="status" id="st_avail" value="available" <?php echo $product['status']=='available'?'checked':''; ?>>
                                            <label class="st-label available" for="st_avail"><i class="fas fa-circle-check"></i> Available</label>
                                        </div>
                                        <div class="st-card">
                                            <input type="radio" name="status" id="st_sold" value="sold" <?php echo $product['status']=='sold'?'checked':''; ?>>
                                            <label class="st-label sold" for="st_sold"><i class="fas fa-ban"></i> Sold</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div>
                    <!-- IMAGE -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <div class="panel-title-icon pti-purple"><i class="fas fa-image"></i></div>
                                Product Image
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="img-current">
                                <?php if(!empty($product['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="Current image" id="currentImg">
                                    <div class="img-caption">Current image — upload a new one to replace it</div>
                                <?php else: ?>
                                    <div class="img-no"><i class="fas fa-image"></i><span>No image uploaded</span></div>
                                <?php endif; ?>
                            </div>
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="image" accept="image/*" id="imgInput">
                                <div class="upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                                <div class="upload-text">Click or drag to replace</div>
                                <div class="upload-hint">JPG, PNG, GIF — max 2MB</div>
                            </div>
                            <div id="newPreviewWrap">
                                <img id="newPreviewImg" src="" alt="New image preview">
                                <div class="img-caption" style="margin-top:6px;text-align:center;">New image preview</div>
                            </div>
                        </div>
                    </div>

                    <!-- LIVE PREVIEW -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="panel-title">
                                <div class="panel-title-icon pti-gold"><i class="fas fa-eye"></i></div>
                                Listing Preview
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="preview-card">
                                <div class="pc-thumb" id="pcThumb">
                                    <?php if(!empty($product['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="" id="pcImg">
                                    <?php else: ?>
                                        <i class="fas fa-gamepad" id="pcPlaceholder"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="pc-title" id="pcTitle"><?php echo htmlspecialchars($product['title']); ?></div>
                                <?php if(!empty($product['game_name'])): ?>
                                <div class="pc-game" id="pcGame"><?php echo htmlspecialchars($product['game_name']); ?></div>
                                <?php else: ?>
                                <div class="pc-game" id="pcGame" style="display:none;"></div>
                                <?php endif; ?>
                                <div class="pc-price" id="pcPrice">$<?php echo number_format($product['price'],2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <button type="submit" class="btn btn-gold"><i class="fas fa-floppy-disk"></i> Save Changes</button>
                    <a href="my-products.php" class="btn btn-ghost"><i class="fas fa-xmark"></i> Cancel</a>

                    <!-- DANGER ZONE -->
                    <div class="danger-zone">
                        <div class="dz-title"><i class="fas fa-triangle-exclamation"></i> Danger Zone</div>
                        <div class="dz-sub">Deleting this product is permanent and cannot be undone.</div>
                        <a href="my-products.php?delete=<?php echo $product['id']; ?>" class="btn btn-red"
                           onclick="return confirm('Delete this product permanently? This cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete Product
                        </a>
                    </div>
                </div>

            </div>
            </form>
        </div>
    </main>
</div>

<script>
    const ham=document.getElementById('hamburger'),sb=document.getElementById('sidebar'),ov=document.getElementById('overlay');
    ham.addEventListener('click',()=>{sb.classList.toggle('open');ov.classList.toggle('visible');});
    ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('visible');});

    // image preview
    const imgInput=document.getElementById('imgInput');
    imgInput.addEventListener('change',()=>{
        if(imgInput.files&&imgInput.files[0]){
            const r=new FileReader();
            r.onload=e=>{
                document.getElementById('newPreviewImg').src=e.target.result;
                document.getElementById('newPreviewWrap').style.display='block';
                // update card preview too
                let pcThumb=document.getElementById('pcThumb');
                let existing=document.getElementById('pcImg');
                if(existing){ existing.src=e.target.result; }
                else {
                    let ph=document.getElementById('pcPlaceholder');
                    if(ph) ph.remove();
                    let img=document.createElement('img');
                    img.id='pcImg'; img.src=e.target.result; img.alt='';
                    img.style.cssText='width:100%;height:100%;object-fit:cover;';
                    pcThumb.appendChild(img);
                }
            };
            r.readAsDataURL(imgInput.files[0]);
        }
    });

    // drag style
    const uz=document.getElementById('uploadZone');
    uz.addEventListener('dragover',e=>{e.preventDefault();uz.classList.add('dragover');});
    uz.addEventListener('dragleave',()=>uz.classList.remove('dragover'));
    uz.addEventListener('drop',e=>{e.preventDefault();uz.classList.remove('dragover');});

    // live preview updates
    const titleEl=document.getElementById('titleInput');
    const priceEl=document.getElementById('priceInput');
    const gameEl =document.getElementById('selGame');

    function updatePreview(){
        document.getElementById('pcTitle').textContent=titleEl.value||'Listing title…';
        const p=parseFloat(priceEl.value);
        document.getElementById('pcPrice').textContent=isNaN(p)?'$—':'$'+p.toFixed(2);
        const g=gameEl.value;
        const pg=document.getElementById('pcGame');
        if(g){pg.style.display='inline-block';pg.textContent=g;}else{pg.style.display='none';}
    }
    titleEl.addEventListener('input',updatePreview);
    priceEl.addEventListener('input',updatePreview);
    gameEl.addEventListener('change',updatePreview);

    // Toggle Account Details panel based on selected Item Type
    const accountPanel = document.getElementById('accountDetailsPanel');
    const itemTypeRadios = document.querySelectorAll('input[name="item_type"]');

    function toggleAccountDetails() {
        let selected = document.querySelector('input[name="item_type"]:checked');
        if (selected && selected.value === 'account') {
            accountPanel.style.display = 'block';
        } else {
            accountPanel.style.display = 'none';
        }
    }

    // initial check
    toggleAccountDetails();

    // add event listeners to each radio
    itemTypeRadios.forEach(radio => {
        radio.addEventListener('change', toggleAccountDetails);
    });
</script>
</body>
</html>