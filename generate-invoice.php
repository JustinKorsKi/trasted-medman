<?php
require_once 'includes/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id        = $_SESSION['user_id'];
$role           = $_SESSION['role'];
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$transaction_id) { header('Location: my-transactions.php'); exit(); }

$query = "SELECT t.*,
          p.title as product_title, p.description as product_desc, p.category, p.item_type,
          b.id as buyer_id, b.username as buyer_name, b.email as buyer_email,
          b.phone as buyer_phone, b.full_name as buyer_full,
          s.id as seller_id, s.username as seller_name, s.email as seller_email,
          s.phone as seller_phone, s.full_name as seller_full,
          m.id as midman_id, m.username as midman_name, m.email as midman_email,
          m.full_name as midman_full
          FROM transactions t
          JOIN products p ON t.product_id = p.id
          JOIN users b ON t.buyer_id = b.id
          JOIN users s ON t.seller_id = s.id
          LEFT JOIN users m ON t.midman_id = m.id
          WHERE t.id = $transaction_id";

$result = mysqli_query($conn, $query);
if (mysqli_num_rows($result) == 0) { header('Location: my-transactions.php'); exit(); }
$tx = mysqli_fetch_assoc($result);

$authorized = ($tx['buyer_id'] == $user_id || $tx['seller_id'] == $user_id ||
               $tx['midman_id'] == $user_id || $role == 'admin');
if (!$authorized) { header('Location: my-transactions.php'); exit(); }

$invoice_no = 'TM-' . str_pad($transaction_id, 6, '0', STR_PAD_LEFT);
$issue_date = date('F j, Y', strtotime($tx['created_at']));
$updated    = date('F j, Y \a\t g:i A', strtotime($tx['updated_at']));
$subtotal   = (float)$tx['amount'];
$fee        = (float)$tx['service_fee'];
$total      = $subtotal + $fee;
$fee_pct    = $subtotal > 0 ? round(($fee / $subtotal) * 100) : 5;

$status_map = [
    'completed'   => ['label' => 'Completed',   'color' => '#00d4aa'],
    'cancelled'   => ['label' => 'Cancelled',   'color' => '#ff4d6d'],
    'delivered'   => ['label' => 'Delivered',   'color' => '#a064ff'],
    'in_progress' => ['label' => 'In Progress', 'color' => '#4e9fff'],
    'pending'     => ['label' => 'Pending',     'color' => '#ff9632'],
    'disputed'    => ['label' => 'Disputed',    'color' => '#ff4d6d'],
    'shipped'     => ['label' => 'Shipped',     'color' => '#a064ff'],
];
$st = $status_map[$tx['status']] ?? ['label' => ucfirst($tx['status']), 'color' => '#a89880'];

$buyer_name  = htmlspecialchars($tx['buyer_full']  ?: $tx['buyer_name']);
$seller_name = htmlspecialchars($tx['seller_full'] ?: $tx['seller_name']);
$midman_name = $tx['midman_id'] ? htmlspecialchars($tx['midman_full'] ?: $tx['midman_name']) : null;

$back_link = match($role) {
    'admin'  => 'admin/transactions.php',
    'midman' => 'midman-dashboard.php',
    default  => 'my-transactions.php'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $invoice_no; ?> — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:         #0f0c08;
            --surface:    #0f0b07;
            --surface2:   #191410;
            --surface3:   #211810;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.26);
            --gold:       #f0a500;
            --gold-lt:    #ffbe3a;
            --gold-dim:   rgba(240,165,0,0.12);
            --gold-glow:  rgba(240,165,0,0.28);
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.10);
            --purple:     #a064ff;
            --purple-dim: rgba(160,100,255,0.11);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.11);
            --text:       #ffffff;
            --text-warm:  #f0e8da;
            --text-muted: #a89880;
            --text-dim:   #5a4e3a;
            --radius-sm:  10px;
            --radius:     14px;
            --radius-lg:  20px;
            --font-head:  'Barlow Condensed', sans-serif;
            --font-body:  'DM Sans', sans-serif;
        }

        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text-warm);
            min-height: 100vh;
            padding: 28px 20px 60px;
            -webkit-font-smoothing: antialiased;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── TOPBAR ── */
        .topbar {
            max-width: 900px; margin: 0 auto 24px;
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 12px;
            opacity: 0; animation: fadeUp 0.4s ease forwards;
        }
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .back-btn {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 0.84rem; color: var(--text-muted);
            text-decoration: none; padding: 9px 16px;
            border-radius: var(--radius-sm);
            background: var(--surface2); border: 1px solid var(--border2);
            transition: all 0.2s;
        }
        .back-btn:hover { color: var(--text-warm); border-color: var(--border3); }
        .topbar-title {
            font-family: var(--font-head);
            font-size: 1.1rem; font-weight: 700;
            color: var(--text); letter-spacing: -0.01em;
        }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 20px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.875rem;
            font-weight: 600; cursor: pointer; border: none;
            transition: all 0.22s;
        }
        .btn-ghost {
            background: var(--surface2); color: var(--text-muted);
            border: 1px solid var(--border2);
        }
        .btn-ghost:hover { color: var(--text-warm); border-color: var(--border3); }

        /* ── INVOICE CARD ── */
        .invoice-card {
            max-width: 900px; margin: 0 auto;
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            opacity: 0; animation: fadeUp 0.45s ease 0.05s forwards;
        }

        /* ── HEADER ── */
        .inv-head {
            background: linear-gradient(135deg, #1a1208 0%, #0f0b07 100%);
            border-bottom: 1px solid var(--border2);
            padding: 40px 48px;
            display: flex; align-items: flex-start;
            justify-content: space-between; gap: 24px;
            flex-wrap: wrap; position: relative; overflow: hidden;
        }
        .inv-head::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), var(--gold-lt), var(--gold), transparent);
        }
        .inv-head::after {
            content: '';
            position: absolute; top: -100px; right: -100px;
            width: 340px; height: 340px;
            background: radial-gradient(circle, var(--gold-glow) 0%, transparent 65%);
            pointer-events: none;
        }
        .brand { display: flex; align-items: center; gap: 16px; position: relative; z-index: 1; }
        .brand-shield {
            width: 56px; height: 56px;
            background: linear-gradient(135deg, var(--gold), #b87000);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; color: #0f0c08;
            box-shadow: 0 4px 24px var(--gold-glow); flex-shrink: 0;
        }
        .brand-name { font-family: var(--font-head); font-size: 1.5rem; font-weight: 800; color: var(--text); letter-spacing: -0.01em; line-height: 1; margin-bottom: 4px; }
        .brand-tagline { font-size: 0.72rem; color: var(--gold); letter-spacing: 0.14em; text-transform: uppercase; }

        .inv-meta { text-align: right; position: relative; z-index: 1; }
        .inv-num { font-family: var(--font-head); font-size: 2rem; font-weight: 800; color: var(--gold); letter-spacing: -0.02em; line-height: 1; margin-bottom: 12px; }
        .meta-key { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-dim); display: block; }
        .meta-val { font-size: 0.875rem; color: var(--text-muted); margin-bottom: 6px; display: block; }
        .status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 14px; border-radius: 30px;
            font-size: 0.7rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.07em;
            margin-top: 8px; border: 1px solid;
        }

        /* ── BODY ── */
        .inv-body { padding: 40px 48px; }

        .sec-label {
            font-size: 0.65rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--text-dim); margin-bottom: 14px;
            display: flex; align-items: center; gap: 10px;
        }
        .sec-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* ── PARTIES ── */
        .parties {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px; margin-bottom: 36px;
        }
        .party {
            background: var(--surface2); border: 1px solid var(--border);
            border-radius: var(--radius-sm); padding: 18px 20px;
            transition: border-color 0.2s;
        }
        .party:hover { border-color: var(--border2); }
        .party-top { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .party-ava {
            width: 36px; height: 36px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-family: var(--font-head); font-weight: 700; font-size: 0.85rem; flex-shrink: 0;
        }
        .party-role-tag { font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.09em; color: var(--text-dim); }
        .party-fullname { font-size: 0.9rem; font-weight: 600; color: var(--text-warm); margin-bottom: 3px; }
        .party-username { font-size: 0.73rem; color: var(--text-dim); margin-bottom: 8px; }
        .party-contact  { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 3px; display: flex; align-items: center; gap: 6px; }
        .party-contact i { font-size: 0.65rem; color: var(--text-dim); width: 12px; }

        .hdivider { height: 1px; background: var(--border); margin: 32px 0; }

        /* ── ITEM TABLE ── */
        .inv-table-wrap { border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; margin-bottom: 28px; }
        .inv-table { width: 100%; border-collapse: collapse; }
        .inv-table thead tr { background: var(--surface3); border-bottom: 1px solid var(--border2); }
        .inv-table th { padding: 11px 16px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-dim); text-align: left; }
        .inv-table th:last-child { text-align: right; }
        .inv-table td { padding: 18px 16px; border-bottom: 1px solid var(--border); vertical-align: top; font-size: 0.875rem; }
        .inv-table tr:last-child td { border-bottom: none; }
        .inv-table td:last-child { text-align: right; }
        .item-name { font-weight: 600; color: var(--text-warm); font-size: 0.9rem; margin-bottom: 5px; }
        .item-desc { font-size: 0.78rem; color: var(--text-muted); line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .item-tag { display: inline-block; margin-top: 8px; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; padding: 3px 9px; border-radius: 20px; background: var(--gold-dim); color: var(--gold); border: 1px solid rgba(240,165,0,0.15); }
        .item-type-tag { display: inline-block; margin-top: 5px; margin-left: 5px; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; padding: 3px 9px; border-radius: 20px; background: var(--purple-dim); color: var(--purple); border: 1px solid rgba(160,100,255,0.15); }
        .cell-muted { color: var(--text-muted); }
        .cell-amount { font-family: var(--font-head); font-size: 1rem; font-weight: 700; color: var(--text-warm); }

        /* ── TOTALS ── */
        .totals-section { display: flex; justify-content: flex-end; margin-bottom: 32px; }
        .totals-box { width: 300px; background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius-sm); overflow: hidden; }
        .total-row { display: flex; justify-content: space-between; padding: 12px 18px; border-bottom: 1px solid var(--border); }
        .total-row:last-child { border-bottom: none; }
        .total-key { font-size: 0.84rem; color: var(--text-muted); }
        .total-val { font-size: 0.875rem; font-weight: 600; color: var(--text-warm); }
        .total-row.grand { background: var(--gold-dim); border-top: 1px solid rgba(240,165,0,0.2); }
        .total-row.grand .total-key { font-family: var(--font-head); font-size: 1rem; font-weight: 700; color: var(--text); }
        .total-row.grand .total-val { font-family: var(--font-head); font-size: 1.15rem; font-weight: 800; color: var(--gold); }

        /* ── ESCROW NOTE ── */
        .escrow-box {
            background: var(--surface2); border: 1px solid var(--border);
            border-left: 3px solid var(--gold); border-radius: var(--radius-sm);
            padding: 16px 20px; display: flex; gap: 14px; align-items: flex-start;
        }
        .escrow-icon { width: 34px; height: 34px; border-radius: 9px; background: var(--gold-dim); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; border: 1px solid rgba(240,165,0,0.15); }
        .escrow-title { font-family: var(--font-head); font-size: 0.9rem; font-weight: 700; color: var(--text); margin-bottom: 4px; }
        .escrow-text  { font-size: 0.8rem; color: var(--text-muted); line-height: 1.6; }

        /* ── FOOTER ── */
        .inv-footer {
            background: var(--surface2); border-top: 1px solid var(--border2);
            padding: 22px 48px;
            display: flex; align-items: center;
            justify-content: space-between; flex-wrap: wrap; gap: 14px;
        }
        .footer-left  { font-size: 0.75rem; color: var(--text-dim); line-height: 1.7; }
        .footer-left strong { color: var(--text-muted); }
        .footer-right { text-align: right; font-size: 0.72rem; color: var(--text-dim); line-height: 1.7; }

        @media (max-width: 720px) {
            .inv-head  { padding: 28px 24px; }
            .inv-body  { padding: 28px 24px; }
            .inv-footer{ padding: 18px 24px; }
            .inv-meta  { text-align: left; }
            .inv-num   { font-size: 1.5rem; }
        }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="<?php echo $back_link; ?>" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
        <span class="topbar-title">Invoice <?php echo $invoice_no; ?></span>
    </div>
    <button class="btn btn-ghost" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
</div>

<div class="invoice-card">

    <!-- HEADER -->
    <div class="inv-head">
        <div class="brand">
            <div class="brand-shield"><i class="fas fa-shield-halved"></i></div>
            <div>
                <div class="brand-name">TRUSTED MIDMAN</div>
                <div class="brand-tagline">Secure Escrow · Marketplace</div>
            </div>
        </div>
        <div class="inv-meta">
            <div class="inv-num"><?php echo $invoice_no; ?></div>
            <span class="meta-key">Issue Date</span>
            <span class="meta-val"><?php echo $issue_date; ?></span>
            <span class="meta-key">Last Updated</span>
            <span class="meta-val"><?php echo $updated; ?></span>
            <div>
                <span class="status-pill" style="color:<?php echo $st['color']; ?>;background:<?php echo $st['color']; ?>1a;border-color:<?php echo $st['color']; ?>44;">
                    <i class="fas fa-circle" style="font-size:0.45rem;"></i> <?php echo $st['label']; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- BODY -->
    <div class="inv-body">

        <div class="sec-label">Transaction Parties</div>
        <div class="parties">
            <div class="party">
                <div class="party-top">
                    <div class="party-ava" style="background:var(--blue-dim);color:var(--blue);"><?php echo strtoupper(substr($tx['buyer_name'],0,2)); ?></div>
                    <div class="party-role-tag">Buyer</div>
                </div>
                <div class="party-fullname"><?php echo $buyer_name; ?></div>
                <div class="party-username">@<?php echo htmlspecialchars($tx['buyer_name']); ?></div>
                <div class="party-contact"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($tx['buyer_email']); ?></div>
                <?php if ($tx['buyer_phone']): ?><div class="party-contact"><i class="fas fa-phone"></i><?php echo htmlspecialchars($tx['buyer_phone']); ?></div><?php endif; ?>
            </div>
            <div class="party">
                <div class="party-top">
                    <div class="party-ava" style="background:var(--teal-dim);color:var(--teal);"><?php echo strtoupper(substr($tx['seller_name'],0,2)); ?></div>
                    <div class="party-role-tag">Seller</div>
                </div>
                <div class="party-fullname"><?php echo $seller_name; ?></div>
                <div class="party-username">@<?php echo htmlspecialchars($tx['seller_name']); ?></div>
                <div class="party-contact"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($tx['seller_email']); ?></div>
                <?php if ($tx['seller_phone']): ?><div class="party-contact"><i class="fas fa-phone"></i><?php echo htmlspecialchars($tx['seller_phone']); ?></div><?php endif; ?>
            </div>
            <?php if ($midman_name): ?>
            <div class="party">
                <div class="party-top">
                    <div class="party-ava" style="background:var(--purple-dim);color:var(--purple);"><?php echo strtoupper(substr($tx['midman_name'],0,2)); ?></div>
                    <div class="party-role-tag">Midman</div>
                </div>
                <div class="party-fullname"><?php echo $midman_name; ?></div>
                <div class="party-username">@<?php echo htmlspecialchars($tx['midman_name']); ?></div>
                <div class="party-contact"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($tx['midman_email']); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="hdivider"></div>

        <div class="sec-label">Item / Service</div>
        <div class="inv-table-wrap">
            <table class="inv-table">
                <thead>
                    <tr>
                        <th style="width:50%;">Description</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="item-name"><?php echo htmlspecialchars($tx['product_title']); ?></div>
                            <?php if ($tx['product_desc']): ?>
                            <div class="item-desc"><?php echo htmlspecialchars(mb_substr($tx['product_desc'],0,140)).(mb_strlen($tx['product_desc'])>140?'…':''); ?></div>
                            <?php endif; ?>
                            <?php if ($tx['category']): ?><span class="item-tag"><?php echo htmlspecialchars($tx['category']); ?></span><?php endif; ?>
                            <?php if ($tx['item_type']): ?><span class="item-type-tag"><?php echo ucfirst($tx['item_type']); ?></span><?php endif; ?>
                        </td>
                        <td><span class="cell-muted"><?php echo $tx['category'] ? htmlspecialchars($tx['category']) : '—'; ?></span></td>
                        <td><span class="cell-muted"><?php echo $tx['item_type'] ? ucfirst($tx['item_type']) : '—'; ?></span></td>
                        <td><span class="cell-muted">1</span></td>
                        <td><span class="cell-amount">$<?php echo number_format($subtotal,2); ?></span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="totals-section">
            <div class="totals-box">
                <div class="total-row">
                    <span class="total-key">Subtotal</span>
                    <span class="total-val">$<?php echo number_format($subtotal,2); ?></span>
                </div>
                <div class="total-row">
                    <span class="total-key">Midman Fee (<?php echo $fee_pct; ?>%)</span>
                    <span class="total-val">$<?php echo number_format($fee,2); ?></span>
                </div>
                <div class="total-row grand">
                    <span class="total-key">Total</span>
                    <span class="total-val">$<?php echo number_format($total,2); ?></span>
                </div>
            </div>
        </div>

        <div class="escrow-box">
            <div class="escrow-icon"><i class="fas fa-lock"></i></div>
            <div>
                <div class="escrow-title">Secured via Midman Escrow</div>
                <div class="escrow-text">
                    This transaction was protected by the Trusted Midman escrow system. The Midman held the buyer's
                    payment and released it to the seller only after both parties confirmed the trade was complete.
                    The service fee of <strong style="color:var(--text-warm);">$<?php echo number_format($fee,2); ?></strong>
                    compensates the Midman for facilitating this secure transaction.
                    This is a system-generated invoice — no physical signature required.
                </div>
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <div class="inv-footer">
        <div class="footer-left">
            <strong>Trusted Midman Marketplace</strong><br>
            Notre Dame of Marbel University · BSCS Capstone Project<br>
            For disputes, contact support within 14 days of completion.
        </div>
        <div class="footer-right">
            Generated <?php echo date('F j, Y \a\t g:i A'); ?><br>
            Transaction #<?php echo $transaction_id; ?> · <?php echo $invoice_no; ?>
        </div>
    </div>

</div>
</body>
</html>
