<?php
require_once 'config.php';
$db = new JsonDB();

$id = $_GET['id'] ?? '';
$type = $_GET['type'] ?? 'customer'; 
$orders = $db->read('orders');
$customers = $db->read('customers');
$all_settings = $db->read('settings');
$settings = $all_settings[0] ?? ['business_name' => 'Laundry Pro', 'address' => '-', 'phone' => '-'];

$order = null;
foreach ($orders as $o) {
    if ($o['id'] == $id) {
        $order = $o;
        break;
    }
}

if (!$order) die("Order not found");

$customer = null;
foreach ($customers as $c) {
    if ($c['id'] == $order['customer_id']) {
        $customer = $c;
        break;
    }
}
$custName = $customer['name'] ?? "Pelanggan";
$custPhone = $customer['phone'] ?? "-";

// Hitung Sisa Bayar
$paid = (int)($order['paid_amount'] ?? 0) + (int)($order['balance_used'] ?? 0);
$total = (int)$order['grand_total'];
$is_paid = ($paid >= $total);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nota #<?php echo $order['order_number']; ?></title>
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; background: white; font-family: 'Courier New', Courier, monospace; color: #000; }
        .receipt { width: 58mm; padding: 2mm 2mm 0 2mm; box-sizing: border-box; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .dashed { border-top: 1px dashed #000; margin: 8px 0; }
        .double-dashed { border-top: 2px dashed #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        .title { font-size: 24px; margin: 0; font-weight: 900; letter-spacing: 1px; }
        .subtitle { font-size: 10px; margin: 2px 0; }
        .info-row { display: flex; justify-content: space-between; font-size: 10px; margin-bottom: 2px; }
        .status-badge { border: 1px solid #000; padding: 2px 5px; display: inline-block; font-weight: bold; margin-top: 5px; font-size: 12px; }
        .item-list td { padding: 4px 0; vertical-align: top; }
        img { max-width: 100%; }
    </style>
</head>
</head>
<body onload="window.print()">
    <div class="receipt">
        <?php if ($type === 'customer'): ?>
            <!-- ================= LAYOUT NOTA PELANGGAN ================= -->
            <div class="text-center">
                <h1 class="title"><?php echo strtoupper($settings['business_name']); ?></h1>
                <p class="subtitle"><?php echo $settings['address']; ?></p>
                <p class="subtitle">Telp/WA: <?php echo $settings['phone']; ?></p>
            </div>
            
            <div class="dashed"></div>
            
            <div class="text-center" style="margin-bottom: 5px;">
                <h1 class="title"><?php echo strtoupper($custName); ?></h1>
            </div>
            
            <div class="info-row"><span>No. Nota:</span><span class="bold">#<?php echo $order['order_number']; ?></span></div>
            <div class="info-row"><span>Tanggal:</span><span><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></span></div>
            <div class="info-row"><span>No. Telp:</span><span><?php echo $custPhone; ?></span></div>
            
            <div class="text-center">
                <div class="status-badge">
                    <?php echo $is_paid ? 'LUNAS' : 'BELUM LUNAS'; ?>
                </div>
            </div>

            <div class="dashed"></div>
            
            <table class="item-list">
                <?php if(isset($order['services']) && is_array($order['services'])): ?>
                    <?php foreach($order['services'] as $s): ?>
                        <tr>
                            <td><?php echo $s['name']; ?><br>
                                <span style="font-size: 9px;"><?php echo $s['weight']; ?> x <?php echo number_format($s['price'], 0, ',', '.'); ?></span>
                            </td>
                            <td class="text-right bold">Rp <?php echo number_format($s['subtotal'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td>Layanan (<?php echo $order['weight']; ?> Kg)</td>
                        <td class="text-right bold">Rp <?php echo number_format($order['grand_total'], 0, ',', '.'); ?></td>
                    </tr>
                <?php endif; ?>
            </table>
            
            <div class="dashed"></div>

            <table>
                <tr class="bold" style="font-size: 13px;">
                    <td>TOTAL AKHIR:</td>
                    <td class="text-right">Rp <?php echo number_format($total, 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <td style="font-size: 10px;">DIBAYAR:</td>
                    <td class="text-right" style="font-size: 10px;">Rp <?php echo number_format($paid, 0, ',', '.'); ?></td>
                </tr>
                <?php if (!$is_paid): ?>
                <tr class="bold">
                    <td style="font-size: 11px;">SISA TAGIHAN:</td>
                    <td class="text-right" style="font-size: 11px;">Rp <?php echo number_format($total - $paid, 0, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
                <?php 
                $balance = (int)($customer['balance'] ?? 0);
                if ($balance > 0): 
                ?>
                <tr>
                    <td style="font-size: 10px;">DEPOSIT KEMBALIAN:</td>
                    <td class="text-right" style="font-size: 10px;">Rp <?php echo number_format($balance, 0, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <?php 
            $itemsText = [];
            if(isset($order['items_detail'])) {
                foreach($order['items_detail'] as $key => $val) {
                    if($val > 0) {
                        $name = ucwords(str_replace('_', ' ', $key));
                        $itemsText[] = "$name($val)";
                    }
                }
            }
            if(!empty($itemsText)):
            ?>
            <div style="font-size: 9px; margin-top: 10px; border: 1px solid #000; padding: 4px;">
                <span class="bold">DETAIL PAKAIAN:</span><br>
                <?php echo implode(', ', $itemsText); ?>
            </div>
            <?php endif; ?>

            <div style="font-size: 10px; margin-top: 8px;">
                <span class="bold">PARFUM:</span> <?php echo strtoupper($order['parfum'] ?? 'Standard'); ?><br><span class="bold">PETUGAS:</span> <?php echo strtoupper($order['staff_ironing'] ?? 'Admin'); ?>
            </div>

            <div class="double-dashed"></div>
            
            <div class="text-center">
                <p style="font-size: 9px; font-style: italic; margin-bottom: 10px; text-align: left;">
                    <?php echo nl2br(htmlspecialchars($settings['receipt_note'] ?? 'Terima kasih telah mempercayakan laundry Anda kepada kami.')); ?>
                </p>
                
                <?php 
                $trackLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . str_replace('print_nota.php', 'track.php', $_SERVER['REQUEST_URI']) . "?nota=" . $order['order_number'];
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($trackLink);
                ?>
                <img src="<?php echo $qrUrl; ?>" style="width: 42mm; height: 42mm; border: 1px solid #ccc;">
                <p style="font-size: 8px; margin-top: 5px;">Scan untuk cek status pesanan online</p>
                <div class="dashed"></div>
                <p style="font-size: 10px; font-weight: bold; margin-bottom: 0;">Layanan Kebersihan & Kepercayaan</p>
            </div>

        <?php else: ?>
            <!-- ================= LAYOUT LABEL KERANJANG ================= -->
            <div class="text-center">
                <h3 style="margin: 0; font-size: 14px; text-transform: uppercase;">Label Keranjang</h3>
                <div style="border-bottom: 2px solid #000; margin: 5px 0;"></div>
                
                <h1 style="margin: 5px 0; font-size: 26px; font-weight: 900;">#<?php echo $order['order_number']; ?></h1>
                <div style="font-size: 18px; font-weight: 800; background: #000; color: #fff; padding: 5px; margin: 10px 0;"><?php echo strtoupper($custName); ?></div>
                
                <div style="margin-bottom: 10px; border: 1px dashed #ccc; padding: 5px; font-size: 12px; text-align: left;">
                    <span class="bold">RINCIAN:</span><br>
                    <?php 
                    if(isset($order['items_detail'])) {
                        foreach($order['items_detail'] as $key => $val) {
                            if($val > 0) {
                                $name = ucwords(str_replace('_', ' ', $key));
                                echo "- $name: $val<br>";
                            }
                        }
                    }
                    ?>
                </div>
                <div style="font-weight: 800; font-size: 14px; margin-bottom: 15px; border-bottom: 1px solid #000; padding-bottom: 10px;">
                    PARFUM: <?php echo strtoupper($order['parfum'] ?? 'Tanpa Parfum'); ?>
                </div>

                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode("FINISH:".$order['id']); ?>" style="width: 48mm; height: 48mm;">
                <p style="margin-top: 5px; font-size: 11px; font-weight: 900; margin-bottom: 0;">SCAN UNTUK SELESAI</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
