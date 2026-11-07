<?php
require_once 'config.php';
$db = new JsonDB();

$nota_number = strtoupper(trim($_GET['nota'] ?? $_GET['id'] ?? ''));
$orders = $db->read('orders');
$customers = $db->read('customers');
$all_settings = $db->readFiltered('settings');

$order = null;
foreach($orders as $o) {
    if(strtoupper(trim($o['order_number'])) == $nota_number) {
        $order = $o;
        break;
    }
}

if (!$order) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Pesanan tidak ditemukan.</h2><p>Pastikan Nomor Nota atau QR Code yang Anda scan benar.</p></div>");
}

// Load Settings Toko
$settings = $all_settings[0] ?? [
    'business_name' => 'Laundry Tracking',
    'address' => '-',
    'phone' => '-'
];

// Get Customer Name
// Get Customer Info
$custName = 'Pelanggan';
$custBalance = 0;
foreach($customers as $c) {
    if($c['id'] == $order['customer_id']) { 
        $custName = $c['name']; 
        $custBalance = $c['balance'] ?? 0;
        break; 
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Digital - <?php echo $settings['business_name']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #6366f1; --secondary: #10b981; --warning: #f59e0b; --danger: #ef4444; --bg: #f8fafc; --white: #ffffff; --text: #1e293b; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg); color: var(--text); line-height: 1.6; display: flex; justify-content: center; padding: 15px; }
        .container { width: 100%; max-width: 450px; background: var(--white); border-radius: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid rgba(0,0,0,0.05); }
        .header { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 35px 20px; text-align: center; color: white; }
        .header h1 { font-size: 1.4rem; font-weight: 800; margin-bottom: 5px; }
        .header p { font-size: 0.8rem; opacity: 0.8; }
        .status-badge { display: inline-block; padding: 8px 20px; border-radius: 50px; font-weight: 800; font-size: 0.85rem; margin-top: -20px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); text-transform: uppercase; }
        .status-processing { background: var(--warning); color: white; }
        .status-completed { background: var(--secondary); color: white; }
        .content { padding: 25px; }
        .info-card { background: #f8fafc; padding: 15px; border-radius: 20px; margin-bottom: 15px; border: 1px solid #e2e8f0; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0; }
        .info-row:last-child { border: none; margin-bottom: 0; padding-bottom: 0; }
        .label { font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .value { font-weight: 700; color: var(--text); font-size: 0.9rem; }
        
        .section-title { font-size: 0.8rem; font-weight: 800; color: #64748b; margin: 20px 0 10px 5px; text-transform: uppercase; display: flex; align-items: center; gap: 8px; }
        .item-list { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; }
        .item-row { display: flex; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid #f1f5f9; }
        .item-row:last-child { border: none; }
        
        .detail-pakaian { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .pakaian-box { background: #f1f5f9; padding: 10px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; }
        .pakaian-name { font-size: 0.8rem; font-weight: 600; color: #475569; }
        .pakaian-qty { background: var(--primary); color: white; padding: 2px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($settings['business_name']); ?></h1>
        </div>

        <div style="text-align: center;">
            <div class="status-badge <?php echo ($order['status'] == 'completed') ? 'status-completed' : 'status-processing'; ?>">
                <?php echo ($order['status'] == 'completed') ? '<i class="fas fa-check-circle"></i> Pesanan Selesai' : '<i class="fas fa-sync-alt"></i> Sedang Diproses'; ?>
            </div>
        </div>

        <div class="content">
            <!-- Info Utama -->
            <div class="info-card">
                <div class="info-row"><span class="label">No. Nota</span><span class="value" style="color:var(--primary);">#<?php echo $order['order_number']; ?></span></div>
                <div class="info-row"><span class="label">Pelanggan</span><span class="value"><?php echo htmlspecialchars($custName); ?></span></div>
                <div class="info-row"><span class="label">Tgl Masuk</span><span class="value"><?php echo date('d M Y H:i', strtotime($order['order_date'])); ?></span></div>
                <div class="info-row"><span class="label">Estimasi</span><span class="value" style="color:var(--warning);"><?php echo date('d M Y', strtotime($order['est_finish_date'])); ?></span></div>
                <div class="info-row"><span class="label">Aroma Parfum</span><span class="value"><i class="fas fa-spray-can" style="color:var(--warning);"></i> <?php echo htmlspecialchars($order['parfum']); ?></span></div>
            </div>

            <!-- Rincian Layanan -->
            <h4 class="section-title"><i class="fas fa-concierge-bell"></i> Rincian Layanan</h4>
            <div class="item-list">
                <?php if(isset($order['services'])): foreach($order['services'] as $svc): ?>
                    <div class="item-row">
                        <div>
                            <div style="font-weight:700; font-size:0.85rem;"><?php echo htmlspecialchars($svc['name']); ?></div>
                            <div style="font-size:0.75rem; color:#64748b;"><?php echo $svc['weight']; ?> x Rp <?php echo number_format($svc['price'],0,',','.'); ?></div>
                        </div>
                        <div style="font-weight:800; color:var(--text);">Rp <?php echo number_format($svc['subtotal'],0,',','.'); ?></div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="item-row"><div style="font-weight:700; font-size:0.85rem;">Layanan Laundry</div><div style="font-weight:800;">Rp <?php echo number_format($order['grand_total'],0,',','.'); ?></div></div>
                <?php endif; ?>
            </div>

            <!-- Rincian Pakaian -->
            <?php 
            $items = array_filter($order['items_detail'] ?? [], function($v) { return $v > 0; });
            if(!empty($items)): 
            ?>
                <h4 class="section-title"><i class="fas fa-tshirt"></i> Detail Pakaian</h4>
                <div class="detail-pakaian">
                    <?php foreach($items as $name => $qty): ?>
                        <div class="pakaian-box">
                            <span class="pakaian-name"><?php echo ucwords(str_replace('_', ' ', $name)); ?></span>
                            <span class="pakaian-qty"><?php echo $qty; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Total Biaya -->
            <div class="info-card" style="margin-top:25px; background: #eef2ff; border: 2px solid #6366f1; border-style: dashed;">
                <?php if($order['balance_used'] > 0): ?>
                    <div class="info-row" style="border:none;"><span class="label">Potongan Saldo</span><span class="value" style="color:var(--secondary);">-Rp <?php echo number_format($order['balance_used'],0,',','.'); ?></span></div>
                <?php endif; ?>
                <div class="info-row" style="border:none; margin-top:5px; padding-top:10px; border-top:1px solid #c7d2fe;">
                    <span class="label" style="color:var(--primary); font-size:0.9rem;">Total Bayar</span>
                    <span class="value" style="font-size: 1.3rem; color: var(--primary);">Rp <?php echo number_format($order['grand_total'], 0, ',', '.'); ?></span>
                </div>
                <div style="text-align:center; margin-top:10px;"><span class="status-badge" style="background:<?php echo ($order['paid_amount'] + $order['balance_used'] >= $order['grand_total']) ? 'var(--secondary)' : 'var(--danger)'; ?>; box-shadow:none; font-size:0.7rem; padding:4px 12px;"><?php echo ($order['paid_amount'] + $order['balance_used'] >= $order['grand_total']) ? 'LUNAS' : 'BELUM LUNAS'; ?></span></div>
            </div>

            <!-- Sisa Deposit (Jika ada) -->
            <?php if($custBalance > 0): ?>
                <div class="info-card" style="margin-top:15px; background: #ecfdf5; border: 1px solid #10b981; border-radius: 20px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="background: #10b981; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 0.7rem; color: #065f46; font-weight: 700; text-transform: uppercase;">Saldo Deposit Anda</div>
                            <div style="font-size: 1.1rem; font-weight: 800; color: #065f46;">Rp <?php echo number_format($custBalance, 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    <p style="font-size: 0.7rem; color: #047857; margin-top: 10px; font-style: italic; border-top: 1px solid rgba(16, 185, 129, 0.2); padding-top: 8px;">
                        *Saldo ini dapat digunakan untuk potongan biaya pada pesanan Anda berikutnya.
                    </p>
                </div>
            <?php endif; ?>

            <p style="text-align:center; font-size:0.75rem; color:#64748b; margin-top:20px; font-style:italic; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                <?php echo nl2br(htmlspecialchars($settings['receipt_note'] ?? 'Terima kasih telah mempercayakan pakaian Anda kepada kami.')); ?>
            </p>
        </div>
    </div>
</body>
</html>
