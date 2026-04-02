<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trusted Midman — Secure Gaming Marketplac    e</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            /* warm amber-tinted dark backgrounds — like the inspo */
            --bg:         #0f0c08;
            --bg2:        #130f0a;
            --surface:    #0f0b07;
            --surface2:   #201a13;
            --surface3:   #271f16;
            --glass:      rgba(255,200,100,0.03);
            --glass2:     rgba(255,200,100,0.055);
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.14);
            --border3:    rgba(255,180,80,0.22);
            --gold:       #f0a500;
            --gold-lt:    #ffbe3a;
            --gold-dim:   rgba(240,165,0,0.15);
            --gold-glow:  rgba(240,165,0,0.32);
            --gold-glow2: rgba(240,165,0,0.1);
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.12);
            --red:        #ff4d6d;
            --red-dim:    rgba(255,77,109,0.12);
            --purple:     #a064ff;
            --purple-dim: rgba(160,100,255,0.12);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.12);
            --orange:     #ff9632;
            --orange-dim: rgba(255, 149, 50, 0.2);
            --text: #ffffff;
            --text-muted: #7a6e5a;
            --text-dim:   #453c2e;
            --text-soft:  #b8a890;
            --radius-xs:  6px;
            --radius-sm:  10px;
            --radius:     16px;
            --radius-lg:  24px;
            --radius-xl:  32px;
            --font-head:  'Barlow Condensed', sans-serif;
            --font-body:  'DM Sans', sans-serif;
            --shadow-sm:  0 2px 12px rgba(0,0,0,0.5);
            --shadow:     0 8px 32px rgba(0,0,0,0.6);
            --shadow-lg:  0 20px 60px rgba(0,0,0,0.7);
        }

        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── NOISE TEXTURE OVERLAY ── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            opacity: 0.5;
        }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            height: 66px;
            display: flex; align-items: center;
            padding: 0 clamp(24px, 5vw, 64px);
            background: rgba(15,12,8,0.82);
            backdrop-filter: blur(24px) saturate(1.4);
            -webkit-backdrop-filter: blur(24px) saturate(1.4);
            border-bottom: 1px solid var(--border);
        }

        .nav-inner {
            width: 100%; max-width: 1320px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
        }

        .nav-logo {
            display: flex; align-items: center; gap: 11px; text-decoration: none;
        }
      .nav-logo-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--gold), #e09000);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 20px var(--gold-glow), 0 4px 12px rgba(240,165,0,0.2);
    /* remove any padding or extra background */
}
.nav-logo-icon img {
    width: 80%;
    height: 80%;
    object-fit: contain;
    /* ensures the image scales without distortion */
}
        .nav-logo-text {
            font-family: var(--font-head); font-weight: 700; font-size: 1.05rem;
            color: var(--text); letter-spacing: -0.01em;
        }

        .nav-links { display: flex; align-items: center; gap: 4px; }

        .nav-link {
            padding: 8px 15px; border-radius: var(--radius-sm);
            font-size: 0.875rem; font-weight: 500; text-decoration: none;
            color: var(--text-muted); transition: all 0.2s;
            letter-spacing: 0.01em;
        }
        .nav-link:hover { color: var(--text); background: var(--glass2); }

        .nav-btn {
            padding: 9px 20px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.875rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: none;
            transition: all 0.22s ease; letter-spacing: 0.01em;
        }
        .nav-btn-ghost {
            background: var(--glass); color: var(--text-soft);
            border: 1px solid var(--border2);
        }
        .nav-btn-ghost:hover { color: var(--text); background: var(--glass2); border-color: var(--border3); }
        .nav-btn-gold {
            background: linear-gradient(135deg, var(--gold), #e09200);
            color: #0a0c10; font-weight: 700;
            box-shadow: 0 4px 18px var(--gold-glow), 0 1px 0 rgba(255,255,255,0.15) inset;
        }
        .nav-btn-gold:hover {
            background: linear-gradient(135deg, var(--gold-lt), var(--gold));
            transform: translateY(-1px);
            box-shadow: 0 6px 24px var(--gold-glow);
        }

        /* ── HERO ── */
        .hero {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            padding: 110px clamp(24px,5vw,64px) 80px;
            position: relative; overflow: hidden;
        }

        /* ambient background — warm amber/orange like inspo */
        .hero-bg { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
        .hero-bg-glow1 {
            position: absolute; top: -200px; left: -150px;
            width: 800px; height: 800px;
            background: radial-gradient(circle, rgba(255,140,20,0.22) 0%, rgba(200,80,0,0.1) 40%, transparent 65%);
        }
        .hero-bg-glow2 {
            position: absolute; bottom: -150px; right: -120px;
            width: 700px; height: 700px;
            background: radial-gradient(circle, rgba(180,70,0,0.18) 0%, rgba(240,130,0,0.07) 45%, transparent 65%);
        }
        .hero-bg-glow3 {
            position: absolute; top: 20%; left: 50%; transform: translateX(-50%);
            width: 900px; height: 500px;
            background: radial-gradient(ellipse, rgba(240,165,0,0.08) 0%, transparent 65%);
        }
        .hero-bg-grid {
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,180,60,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,180,60,0.04) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 90% 80% at 50% 50%, black 10%, transparent 100%);
        }
        .hero-bg-lines {
            position: absolute; inset: 0;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 3px,
                rgba(255,160,40,0.015) 3px,
                rgba(255,160,40,0.015) 4px
            );
        }

        .hero-inner {
            position: relative; z-index: 1;
            max-width: 900px; text-align: center; margin: 0 auto;
        }

        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
        @keyframes shimmer {
            0%   { background-position: -200% center; }
            100% { background-position:  200% center; }
        }

        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 9px;
            background: rgba(240,165,0,0.08);
            border: 1px solid rgba(240,165,0,0.2);
            color: var(--gold); font-size: 0.78rem; font-weight: 700;
            letter-spacing: 0.16em; text-transform: uppercase;
            padding: 7px 18px; border-radius: 30px; margin-bottom: 32px;
            opacity: 0; transform: translateY(10px);
            animation: fadeUp 0.6s 0.1s ease forwards;
            backdrop-filter: blur(8px);
        }
        .hero-eyebrow i { font-size: 0.75rem; }

        .hero-title {
            font-family: var(--font-head);
            font-size: clamp(2.6rem, 5.5vw, 5rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.01em;
            margin-bottom: 28px;
            opacity: 0; transform: translateY(18px);
            animation: fadeUp 0.65s 0.2s ease forwards;
        }
        .hero-title .line-plain { color: var(--text); display: block; }
        .hero-title .accent {
            background: linear-gradient(90deg, var(--gold), var(--gold-lt), var(--gold));
            background-size: 200% auto;
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 4s linear infinite;
            display: inline;
        }

        .hero-desc {
            font-size: clamp(1rem, 1.6vw, 1.15rem);
            color: var(--text-muted);
            line-height: 1.8;
            max-width: 580px; margin: 0 auto 40px;
            font-weight: 400;
            opacity: 0; transform: translateY(18px);
            animation: fadeUp 0.65s 0.3s ease forwards;
        }

        .hero-cta {
            display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;
            opacity: 0; transform: translateY(18px);
            animation: fadeUp 0.65s 0.4s ease forwards;
        }

        .btn-lg {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 14px 30px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.95rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: none;
            transition: all 0.26s ease; letter-spacing: 0.01em;
        }
        .btn-gold {
            background: linear-gradient(135deg, var(--gold), #e09200);
            color: #0a0c10; font-weight: 700;
            box-shadow: 0 6px 28px var(--gold-glow), 0 1px 0 rgba(255,255,255,0.12) inset;
        }
        .btn-gold:hover {
            background: linear-gradient(135deg, var(--gold-lt), var(--gold));
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(240,165,0,0.4);
        }
        .btn-outline {
            background: var(--glass);
            color: var(--text-soft);
            border: 1px solid var(--border2);
            backdrop-filter: blur(8px);
        }
        .btn-outline:hover {
            color: var(--text); background: var(--glass2);
            border-color: var(--border3); transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
        }

        /* hero stats */
        .hero-proof {
            margin-top: 64px;
            opacity: 0; animation: fadeUp 0.65s 0.55s ease forwards;
        }
        .proof-strip {
            display: inline-flex; align-items: center; gap: 0;
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm), 0 0 0 1px rgba(255,255,255,0.02) inset;
        }
        .proof-item {
            padding: 20px 36px; text-align: center;
            position: relative;
        }
        .proof-item + .proof-item::before {
            content: '';
            position: absolute; left: 0; top: 20%; bottom: 20%;
            width: 1px; background: var(--border2);
        }
        .proof-val {
            font-family: var(--font-head);
            font-size: 1.75rem; font-weight: 800;
            color: var(--text); line-height: 1;
            margin-bottom: 4px;
        }
        .proof-val .proof-accent { color: var(--gold); }
        .proof-lbl {
            font-size: 0.72rem; font-weight: 500;
            color: var(--text-dim); text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* ── SECTION SHARED ── */
        section { padding: clamp(72px, 9vw, 110px) clamp(24px,5vw,64px); position: relative; }
        .inner { max-width: 1260px; margin: 0 auto; }

        .sec-label {
            display: inline-flex; align-items: center; gap: 10px;
            font-size: 0.72rem; font-weight: 700; letter-spacing: 0.18em;
            text-transform: uppercase; color: var(--gold);
            margin-bottom: 14px;
        }
        .sec-label::before {
            content: ''; width: 24px; height: 2px;
            background: linear-gradient(90deg, var(--gold), transparent);
            border-radius: 2px;
        }
        .sec-title {
            font-family: var(--font-head);
            font-size: clamp(2rem, 3.8vw, 3rem);
            font-weight: 800; color: var(--text); line-height: 1.1;
            margin-bottom: 16px; letter-spacing: -0.01em;
        }
        .sec-sub {
            font-size: 1.025rem; color: var(--text-muted);
            max-width: 540px; line-height: 1.75; font-weight: 400;
        }
        .sec-head { margin-bottom: 56px; }

        /* ── HOW IT WORKS ── */
        .steps {
            background: var(--bg2);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        .steps::before {
            content: '';
            position: absolute; top: -200px; right: -200px;
            width: 600px; height: 600px; pointer-events: none;
            background: radial-gradient(circle, rgba(240,120,0,0.12) 0%, transparent 60%);
        }

        .steps-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1px;
            background: var(--border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .step {
            background: var(--surface);
            padding: 40px 32px;
            position: relative;
            transition: background 0.3s;
            overflow: hidden;
        }
        .step::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--gold), transparent);
            opacity: 0; transition: opacity 0.3s;
        }
        .step:hover { background: var(--surface2); }
        .step:hover::before { opacity: 1; }

        .step-num {
            font-family: var(--font-head);
            font-size: 5rem; font-weight: 800;
            line-height: 1;
            margin-bottom: 24px;
            background: linear-gradient(180deg, var(--border3) 0%, transparent 100%);
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: all 0.3s;
        }
        .step:hover .step-num {
            background: linear-gradient(180deg, rgba(240,165,0,0.35) 0%, transparent 100%);
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .step-icon {
            width: 46px; height: 46px; border-radius: 12px;
            background: var(--gold-dim);
            border: 1px solid rgba(240,165,0,0.15);
            color: var(--gold);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; margin-bottom: 18px;
        }
        .step-title {
            font-family: var(--font-head); font-size: 1.025rem; font-weight: 700;
            color: var(--text); margin-bottom: 12px; letter-spacing: -0.01em;
        }
        .step-desc { font-size: 0.875rem; color: var(--text-muted); line-height: 1.7; }

        /* ── FEATURES ── */
        .features { background: var(--bg); }

        .feat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .feat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 30px;
            transition: all 0.3s ease;
            position: relative; overflow: hidden;
        }
        /* glossy top edge */
        .feat-card::before {
            content: '';
            position: absolute; top: 0; left: 16px; right: 16px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border3), transparent);
        }
        /* glow corner */
        .feat-card::after {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 100px; height: 100px;
            border-radius: 50%;
            opacity: 0; transition: opacity 0.4s;
        }
        .feat-card:hover {
            border-color: var(--border2);
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.4), 0 0 0 1px rgba(255,255,255,0.04) inset;
            background: var(--surface2);
        }
        .feat-card:hover::after { opacity: 1; }
        .feat-card.fi-gold-c:hover::after  { background: radial-gradient(circle, rgba(240,165,0,0.12), transparent 70%); }
        .feat-card.fi-teal-c:hover::after  { background: radial-gradient(circle, rgba(0,212,170,0.1), transparent 70%); }
        .feat-card.fi-purple-c:hover::after { background: radial-gradient(circle, rgba(160,100,255,0.1), transparent 70%); }
        .feat-card.fi-orange-c:hover::after { background: radial-gradient(circle, rgba(255,150,50,0.1), transparent 70%); }
        .feat-card.fi-red-c:hover::after    { background: radial-gradient(circle, rgba(255,77,109,0.1), transparent 70%); }
        .feat-card.fi-blue-c:hover::after   { background: radial-gradient(circle, rgba(78,159,255,0.1), transparent 70%); }

        .feat-icon {
            width: 50px; height: 50px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-bottom: 20px;
            border: 1px solid transparent;
        }
        .fi-gold   { background: var(--gold-dim);   color: var(--gold);   border-color: rgba(240,165,0,0.12); }
        .fi-teal   { background: var(--teal-dim);   color: var(--teal);   border-color: rgba(0,212,170,0.12); }
        .fi-red    { background: var(--red-dim);    color: var(--red);    border-color: rgba(255,77,109,0.12); }
        .fi-purple { background: var(--purple-dim); color: var(--purple); border-color: rgba(160,100,255,0.12); }
        .fi-orange { background: var(--orange-dim); color: var(--orange); border-color: rgba(255,150,50,0.12); }
        .fi-blue   { background: var(--blue-dim);   color: var(--blue);   border-color: rgba(78,159,255,0.12); }

        .feat-title {
            font-family: var(--font-head); font-size: 1rem; font-weight: 700;
            color: var(--text); margin-bottom: 10px; letter-spacing: -0.01em;
        }
        .feat-desc { font-size: 0.865rem; color: var(--text-muted); line-height: 1.7; }

        /* ── ROLES ── */
        .roles {
            background: var(--bg2);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        .roles::before {
            content: '';
            position: absolute; bottom: -180px; left: -180px;
            width: 600px; height: 600px; pointer-events: none;
            background: radial-gradient(circle, rgba(200,100,0,0.1) 0%, transparent 60%);
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .role-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 38px 32px;
            display: flex; flex-direction: column;
            transition: all 0.32s ease;
            position: relative; overflow: hidden;
        }
        /* top bar accent */
        .role-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0;
            height: 3px; border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            opacity: 0.6; transition: opacity 0.3s;
        }
        /* ambient glow background */
        .role-card::after {
            content: '';
            position: absolute; top: -60px; left: -60px;
            width: 200px; height: 200px; border-radius: 50%;
            opacity: 0; transition: opacity 0.4s; pointer-events: none;
        }
        .role-card.rc-buyer::before  { background: linear-gradient(90deg, var(--blue), var(--purple)); }
        .role-card.rc-seller::before { background: linear-gradient(90deg, var(--teal), var(--blue)); }
        .role-card.rc-midman::before { background: linear-gradient(90deg, var(--gold), var(--orange)); }

        .role-card.rc-buyer::after  { background: radial-gradient(circle, rgba(78,159,255,0.08), transparent 70%); }
        .role-card.rc-seller::after { background: radial-gradient(circle, rgba(0,212,170,0.07), transparent 70%); }
        .role-card.rc-midman::after { background: radial-gradient(circle, rgba(240,165,0,0.09), transparent 70%); }

        .role-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 24px 60px rgba(0,0,0,0.45), 0 0 0 1px var(--border2) inset;
            background: var(--surface2);
            border-color: var(--border2);
        }
        .role-card:hover::before { opacity: 1; }
        .role-card:hover::after  { opacity: 1; }

        .role-badge {
            width: 62px; height: 62px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; margin-bottom: 22px;
            border: 1px solid transparent;
        }
        .rb-buyer  { background: var(--blue-dim);   color: var(--blue);   border-color: rgba(78,159,255,0.15); }
        .rb-seller { background: var(--teal-dim);   color: var(--teal);   border-color: rgba(0,212,170,0.15); }
        .rb-midman { background: var(--gold-dim);   color: var(--gold);   border-color: rgba(240,165,0,0.15); }

        .role-title {
            font-family: var(--font-head); font-size: 1.35rem; font-weight: 800;
            color: var(--text); margin-bottom: 12px; letter-spacing: -0.01em;
        }
        .role-desc {
            font-size: 0.89rem; color: var(--text-muted); line-height: 1.75;
            flex: 1; margin-bottom: 26px;
        }

        .role-perks { list-style: none; margin-bottom: 28px; display: flex; flex-direction: column; gap: 9px; }
        .role-perks li {
            font-size: 0.83rem; color: var(--text-soft);
            display: flex; align-items: center; gap: 10px;
        }
        .role-perks li i { font-size: 0.7rem; color: var(--teal); flex-shrink: 0; }

        .btn-role {
            display: inline-flex; align-items: center; gap: 8px; justify-content: center;
            padding: 12px 22px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.875rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: none;
            transition: all 0.24s ease; letter-spacing: 0.01em;
        }
        .br-buyer  { background: rgba(78,159,255,0.1);  color: var(--blue);  border: 1px solid rgba(78,159,255,0.18); }
        .br-buyer:hover  { background: rgba(78,159,255,0.18); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(78,159,255,0.15); }
        .br-seller { background: var(--teal-dim); color: var(--teal); border: 1px solid rgba(0,212,170,0.18); }
        .br-seller:hover { background: rgba(0,212,170,0.18); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,212,170,0.15); }
        .br-midman { background: var(--gold-dim); color: var(--gold); border: 1px solid rgba(240,165,0,0.18); }
        .br-midman:hover { background: rgba(240,165,0,0.2); transform: translateY(-2px); box-shadow: 0 8px 24px rgba(240,165,0,0.18); }

        /* ── CTA ── */
        .cta-section {
            background: var(--bg);
            padding: clamp(72px,9vw,110px) clamp(24px,5vw,64px);
        }

        .cta-box {
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: var(--radius-xl);
            padding: clamp(48px, 7vw, 88px);
            text-align: center;
            position: relative; overflow: hidden;
        }
        .cta-box::before {
            content: '';
            position: absolute; top: -100px; left: 50%; transform: translateX(-50%);
            width: 600px; height: 360px;
            background: radial-gradient(ellipse, rgba(240,130,0,0.14) 0%, transparent 65%);
            pointer-events: none;
        }
        .cta-box::after {
            content: '';
            position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(240,165,0,0.3), transparent);
        }
        .cta-title {
            font-family: var(--font-head);
            font-size: clamp(2rem, 3.8vw, 3.2rem);
            font-weight: 800; color: var(--text);
            margin-bottom: 16px; position: relative; z-index: 1;
            letter-spacing: -0.01em;
        }
        .cta-title .accent {
            background: linear-gradient(90deg, var(--gold), var(--gold-lt), var(--gold));
            background-size: 200% auto;
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 4s linear infinite;
        }
        .cta-sub {
            font-size: 1.025rem; color: var(--text-muted);
            max-width: 520px; margin: 0 auto 40px;
            line-height: 1.75; position: relative; z-index: 1;
        }
        .cta-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; position: relative; z-index: 1; }

        /* ── FOOTER ── */
        footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: clamp(48px,6vw,80px) clamp(24px,5vw,64px) 32px;
        }

        .footer-inner { max-width: 1260px; margin: 0 auto; }

        .footer-top {
            display: grid;
            grid-template-columns: 1.5fr repeat(3, 1fr);
            gap: 48px;
            margin-bottom: 48px;
        }

        .footer-brand-logo {
            display: flex; align-items: center; gap: 11px;
            margin-bottom: 16px; text-decoration: none;
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
        .footer-brand-name { font-family: var(--font-head); font-weight: 700; font-size: 1rem; color: var(--text); }
        .footer-brand-desc { font-size: 0.845rem; color: var(--text-muted); line-height: 1.7; margin-bottom: 22px; }

        .footer-socials { display: flex; gap: 10px; }
        .social-btn {
            width: 36px; height: 36px;
            background: var(--glass2); border: 1px solid var(--border);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-muted); text-decoration: none; font-size: 0.85rem;
            transition: all 0.22s;
        }
        .social-btn:hover {
            color: var(--gold); border-color: rgba(240,165,0,0.25);
            background: var(--gold-dim);
            transform: translateY(-2px);
        }

        .footer-col-title {
            font-family: var(--font-head);
            font-size: 0.78rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--text-soft); margin-bottom: 18px;
        }
        .footer-links { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .footer-links a {
            font-size: 0.855rem; color: var(--text-muted); text-decoration: none;
            transition: color 0.2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .footer-links a:hover { color: var(--text); }
        .footer-links a:hover::before {
            content: '';
            display: inline-block; width: 12px; height: 1px;
            background: var(--gold); border-radius: 1px;
            transition: all 0.2s;
        }

        .footer-bottom {
            border-top: 1px solid var(--border);
            padding-top: 28px;
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
        }
        .footer-bottom p { font-size: 0.8rem; color: var(--text-dim); }
        .footer-shield { display: flex; align-items: center; gap: 8px; font-size: 0.78rem; color: var(--text-dim); }
        .footer-shield i { color: var(--gold); }

        /* ── SCROLLBAR ── */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--border2); }

        /* ── RESPONSIVE ── */
        @media (max-width: 1024px) {
            .feat-grid   { grid-template-columns: repeat(2, 1fr); }
            .roles-grid  { grid-template-columns: repeat(2, 1fr); }
            .footer-top  { grid-template-columns: 1fr 1fr; }
            .steps-row   { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .steps-row   { grid-template-columns: 1fr; }
            .feat-grid   { grid-template-columns: 1fr; }
            .roles-grid  { grid-template-columns: 1fr; }
            .footer-top  { grid-template-columns: 1fr; }
            .proof-strip { flex-wrap: wrap; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="nav-inner">
     <a href="index.php" class="nav-logo">
    <div class="nav-logo-icon">
        <img src="images/logoblack.png" alt="Trusted Midman">
    </div>
    <span class="nav-logo-text">Trusted Midman</span>
</a>
        <div class="nav-links">
            <a href="#how"      class="nav-link">How It Works</a>
            <a href="#features" class="nav-link">Features</a>
            <a href="products.php" class="nav-link">Marketplace</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="buyer-dashboard.php" class="nav-btn nav-btn-ghost" style="margin-left:10px;">Dashboard</a>
                <a href="products.php"         class="nav-btn nav-btn-gold"  style="margin-left:6px;">Browse</a>
            <?php else: ?>
                <a href="login.php"    class="nav-btn nav-btn-ghost" style="margin-left:10px;">Sign In</a>
                <a href="register.php" class="nav-btn nav-btn-gold"  style="margin-left:6px;">Get Started</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg">
        <div class="hero-bg-glow1"></div>
        <div class="hero-bg-glow2"></div>
        <div class="hero-bg-glow3"></div>
        <div class="hero-bg-grid"></div>
        <div class="hero-bg-lines"></div>
    </div>
    <div class="hero-inner">
        <div class="hero-eyebrow">
            <i class="fas fa-shield-halved"></i> Secure Gaming Marketplace
        </div>
        <h1 class="hero-title">
            <span class="line-plain">Trade Games Safely.</span>
            <span class="accent">Zero Risk,</span> Every Time.
        </h1>
        <p class="hero-desc">
            Trusted Midman holds your payment securely until you've confirmed delivery. No scams, no stress — just safe peer-to-peer gaming transactions backed by verified middlemen.
        </p>
        <div class="hero-cta">
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="buyer-dashboard.php" class="btn-lg btn-gold"><i class="fas fa-gauge-high"></i> Go to Dashboard</a>
                <a href="products.php"         class="btn-lg btn-outline"><i class="fas fa-store"></i> Browse Products</a>
            <?php else: ?>
                <a href="register.php" class="btn-lg btn-gold"><i class="fas fa-user-plus"></i> Get Started Free</a>
                <a href="products.php" class="btn-lg btn-outline"><i class="fas fa-eye"></i> Explore Marketplace</a>
            <?php endif; ?>
        </div>
        <div class="hero-proof">
            <div class="proof-strip">
                <div class="proof-item">
                    <div class="proof-val">12<span class="proof-accent">K+</span></div>
                    <div class="proof-lbl">Transactions</div>
                </div>
                <div class="proof-item">
                    <div class="proof-val">$2.<span class="proof-accent">4M</span></div>
                    <div class="proof-lbl">Secured</div>
                </div>
                <div class="proof-item">
                    <div class="proof-val">4.9<span class="proof-accent">★</span></div>
                    <div class="proof-lbl">Avg. Rating</div>
                </div>
                <div class="proof-item">
                    <div class="proof-val"><span class="proof-accent">0%</span></div>
                    <div class="proof-lbl">Fraud Rate</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="steps" id="how">
    <div class="inner">
        <div class="sec-head">
            <div class="sec-label">Process</div>
            <h2 class="sec-title">How It Works</h2>
            <p class="sec-sub">Four simple steps that keep every transaction safe and transparent.</p>
        </div>
        <div class="steps-row">
            <div class="step">
                <div class="step-num">01</div>
                <div class="step-icon"><i class="fas fa-magnifying-glass"></i></div>
                <div class="step-title">Buyer Finds Product</div>
                <p class="step-desc">Browse verified listings, compare prices, read reviews, and pick the perfect item with full confidence.</p>
            </div>
            <div class="step">
                <div class="step-num">02</div>
                <div class="step-icon" style="background:var(--teal-dim);color:var(--teal);border-color:rgba(0,212,170,0.15);"><i class="fas fa-lock"></i></div>
                <div class="step-title">Midman Secures Payment</div>
                <p class="step-desc">A trusted Midman holds your funds in escrow. No money moves until you confirm you're satisfied.</p>
            </div>
            <div class="step">
                <div class="step-num">03</div>
                <div class="step-icon" style="background:var(--purple-dim);color:var(--purple);border-color:rgba(160,100,255,0.15);"><i class="fas fa-box-open"></i></div>
                <div class="step-title">Seller Delivers</div>
                <p class="step-desc">The seller delivers the item or account. You can track progress and communicate throughout.</p>
            </div>
            <div class="step">
                <div class="step-num">04</div>
                <div class="step-icon" style="background:var(--teal-dim);color:var(--teal);border-color:rgba(0,212,170,0.15);"><i class="fas fa-circle-check"></i></div>
                <div class="step-title">Safe Release</div>
                <p class="step-desc">You confirm receipt. The Midman releases payment. Both parties rated. Everyone wins.</p>
            </div>
        </div>
    </div>
</section>

<!-- FEATURES -->
<section class="features" id="features">
    <div class="inner">
        <div class="sec-head">
            <div class="sec-label">Platform</div>
            <h2 class="sec-title">Why Choose Us?</h2>
            <p class="sec-sub">Built specifically for gaming transactions — every feature designed to protect your trade.</p>
        </div>
        <div class="feat-grid">
            <div class="feat-card fi-gold-c">
                <div class="feat-icon fi-gold"><i class="fas fa-shield-halved"></i></div>
                <div class="feat-title">Escrow Protection</div>
                <p class="feat-desc">Funds are held securely by a verified Midman and only released once you confirm receipt. No payment risk, ever.</p>
            </div>
            <div class="feat-card fi-teal-c">
                <div class="feat-icon fi-teal"><i class="fas fa-user-check"></i></div>
                <div class="feat-title">Verified Middlemen</div>
                <p class="feat-desc">All Midmen undergo background checks and earn reputation scores. Only trusted professionals handle your transactions.</p>
            </div>
            <div class="feat-card fi-purple-c">
                <div class="feat-icon fi-purple"><i class="fas fa-store"></i></div>
                <div class="feat-title">Gaming Marketplace</div>
                <p class="feat-desc">Browse accounts, currencies, skins, and boosting services. Every listing is reviewed for quality and authenticity.</p>
            </div>
            <div class="feat-card fi-orange-c">
                <div class="feat-icon fi-orange"><i class="fas fa-star"></i></div>
                <div class="feat-title">Transparent Ratings</div>
                <p class="feat-desc">Real reviews from real transactions. Build reputation over time and see exactly who you're trading with.</p>
            </div>
            <div class="feat-card fi-red-c">
                <div class="feat-icon fi-red"><i class="fas fa-scale-balanced"></i></div>
                <div class="feat-title">Dispute Resolution</div>
                <p class="feat-desc">If something goes wrong, our impartial dispute team steps in immediately. Buyers and sellers are always protected.</p>
            </div>
            <div class="feat-card fi-blue-c">
                <div class="feat-icon fi-blue"><i class="fas fa-mobile-screen"></i></div>
                <div class="feat-title">Works Everywhere</div>
                <p class="feat-desc">Fully responsive across desktop, tablet, and mobile. Manage your trades from anywhere, any time.</p>
            </div>
        </div>
    </div>
</section>

<!-- ROLES -->
<section class="roles" id="roles">
    <div class="inner">
        <div class="sec-head">
            <div class="sec-label">Community</div>
            <h2 class="sec-title">Find Your Role</h2>
            <p class="sec-sub">Whether you're buying, selling, or mediating — there's a place for you here.</p>
        </div>
        <div class="roles-grid">
            <div class="role-card rc-buyer">
                <div class="role-badge rb-buyer"><i class="fas fa-bag-shopping"></i></div>
                <div class="role-title">Buyer</div>
                <p class="role-desc">Shop with full confidence knowing your payment is protected until delivery is confirmed.</p>
                <ul class="role-perks">
                    <li><i class="fas fa-check-circle"></i> Payment held securely in escrow</li>
                    <li><i class="fas fa-check-circle"></i> Browse verified gaming listings</li>
                    <li><i class="fas fa-check-circle"></i> Dispute protection on every order</li>
                    <li><i class="fas fa-check-circle"></i> Rate sellers after each transaction</li>
                </ul>
                <a href="products.php" class="btn-role br-buyer"><i class="fas fa-search"></i> Browse Products</a>
            </div>
            <div class="role-card rc-seller">
                <div class="role-badge rb-seller"><i class="fas fa-store"></i></div>
                <div class="role-title">Seller</div>
                <p class="role-desc">List your gaming accounts, items, or services and reach thousands of verified buyers safely.</p>
                <ul class="role-perks">
                    <li><i class="fas fa-check-circle"></i> Guaranteed payment on delivery</li>
                    <li><i class="fas fa-check-circle"></i> Verified buyer protection</li>
                    <li><i class="fas fa-check-circle"></i> Build seller reputation & ratings</li>
                    <li><i class="fas fa-check-circle"></i> Low platform fees</li>
                </ul>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="add-product.php" class="btn-role br-seller"><i class="fas fa-plus"></i> Start Selling</a>
                <?php else: ?>
                    <a href="register.php" class="btn-role br-seller"><i class="fas fa-user-plus"></i> Register as Seller</a>
                <?php endif; ?>
            </div>
            <div class="role-card rc-midman">
                <div class="role-badge rb-midman"><i class="fas fa-handshake"></i></div>
                <div class="role-title">Midman</div>
                <p class="role-desc">Earn fees by facilitating secure transactions. Become a trusted intermediary in the community.</p>
                <ul class="role-perks">
                    <li><i class="fas fa-check-circle"></i> Earn per-transaction fees</li>
                    <li><i class="fas fa-check-circle"></i> Build a verified reputation</li>
                    <li><i class="fas fa-check-circle"></i> Manage dispute resolution</li>
                    <li><i class="fas fa-check-circle"></i> Flexible schedule</li>
                </ul>
                <a href="apply-midman.php" class="btn-role br-midman"><i class="fas fa-certificate"></i> Apply Now</a>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="inner">
        <div class="cta-box">
            <h2 class="cta-title">Ready to Trade <span class="accent">Without Risk?</span></h2>
            <p class="cta-sub">Join thousands of gamers who trust Trusted Midman for safe, stress-free peer-to-peer transactions.</p>
            <div class="cta-btns">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="buyer-dashboard.php" class="btn-lg btn-gold"><i class="fas fa-gauge-high"></i> Go to Dashboard</a>
                    <a href="products.php"         class="btn-lg btn-outline"><i class="fas fa-store"></i> Browse Products</a>
                <?php else: ?>
                    <a href="register.php" class="btn-lg btn-gold"><i class="fas fa-rocket"></i> Start Your Journey</a>
                    <a href="products.php" class="btn-lg btn-outline"><i class="fas fa-eye"></i> Explore First</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-top">
            <div class="footer-brand">
                <a href="index.php" class="footer-brand-logo">
                    <div class="footer-brand-icon"><i class="fas fa-shield-halved"></i></div>
                    <span class="footer-brand-name">Trusted Midman</span>
                </a>
                <p class="footer-brand-desc">Your trusted partner for secure peer-to-peer gaming transactions. We protect buyers, sellers, and midmen alike.</p>
                <div class="footer-socials">
                    <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-x-twitter"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-btn"><i class="fab fa-discord"></i></a>
                </div>
            </div>
            <div>
                <div class="footer-col-title">Platform</div>
                <ul class="footer-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products.php">Marketplace</a></li>
                    <li><a href="apply-midman.php">Become a Midman</a></li>
                    <li><a href="register.php">Create Account</a></li>
                    <li><a href="login.php">Sign In</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-col-title">Support</div>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Safety Guide</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">FAQs</a></li>
                    <li><a href="#">Report Abuse</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-col-title">Legal</div>
                <ul class="footer-links">
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                    <li><a href="#">Dispute Policy</a></li>
                    <li><a href="#">Compliance</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Trusted Midman. All rights reserved.</p>
            <div class="footer-shield"><i class="fas fa-shield-halved"></i> Secured &amp; Protected</div>
        </div>
    </div>
</footer>

<script>
    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const t = document.querySelector(a.getAttribute('href'));
            if(t) { e.preventDefault(); t.scrollIntoView({ behavior:'smooth', block:'start' }); }
        });
    });

    // Scroll-triggered reveal
    const io = new IntersectionObserver(entries => {
        entries.forEach(en => {
            if(en.isIntersecting) {
                en.target.style.opacity = '1';
                en.target.style.transform = 'translateY(0)';
                io.unobserve(en.target);
            }
        });
    }, { threshold: 0.08 });

    document.querySelectorAll('.feat-card, .role-card, .step').forEach((el, i) => {
        el.style.cssText += `opacity:0;transform:translateY(24px);transition:opacity 0.55s ${i*0.07}s ease,transform 0.55s ${i*0.07}s ease;`;
        io.observe(el);
    });

    // Subtle parallax on hero glows
    document.addEventListener('mousemove', e => {
        const x = (e.clientX / window.innerWidth - 0.5) * 20;
        const y = (e.clientY / window.innerHeight - 0.5) * 20;
        const g1 = document.querySelector('.hero-bg-glow1');
        const g2 = document.querySelector('.hero-bg-glow2');
        if(g1) g1.style.transform = `translate(${x * 0.4}px, ${y * 0.4}px)`;
        if(g2) g2.style.transform = `translate(${-x * 0.3}px, ${-y * 0.3}px)`;
    });
</script>
</body>
</html>