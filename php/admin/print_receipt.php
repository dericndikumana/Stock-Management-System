<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Access denied.";
    exit();
}

include '../../php/db_connect.php';

$vat_usd = 0;
$vat_zig = 0;

$is_admin = ($_SESSION['role'] ?? 'user') === 'admin';

// Determine filter type
if (isset($_GET['receipt_id'])) {
    // Normal user flow
    $receipt_id = $_GET['receipt_id'];
    $user_id = $_SESSION['user_id'];

    // Allow admin to override user_id
    if ($is_admin && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
    }

    $filter_sql = "WHERE st.receipt_id = ? AND st.user_id = ?";
    $filter_params = [$receipt_id, $user_id];
    $filter_types = "si";

} elseif ($is_admin && isset($_GET['user_id'], $_GET['created_at'])) {
    // Admin flow using grouped transaction (no receipt_id)
    $user_id = intval($_GET['user_id']);
    $created_at = $_GET['created_at'];

    $filter_sql = "WHERE st.user_id = ? AND st.created_at = ? AND st.transaction_type = 'out' AND st.deleted = 0";
    $filter_params = [$user_id, $created_at];
    $filter_types = "is";

} else {
    echo "Access denied.";
    exit();
}

// Handle VAT input form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vat_usd'], $_POST['vat_zig'])) {
    $vat_usd = floatval($_POST['vat_usd']) / 100;
    $vat_zig = floatval($_POST['vat_zig']) / 100;
} else {
    // Show VAT form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Enter VAT</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { max-width: 400px; margin: 50px auto; font-family: Arial, sans-serif; }
            .vat-form {
                padding: 20px;
                border: 1px solid #ccc;
                border-radius: 8px;
                background: #f8f9fa;
            }
        </style>
    </head>
    <body>
        <div class="vat-form">
            <h4 class="mb-3">Enter VAT Percentages</h4>
            <form method="POST">
                <?php foreach ($_GET as $key => $value): ?>
                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                <?php endforeach; ?>
                <div class="mb-3">
                    <label for="vat_usd" class="form-label">VAT for USD (%)</label>
                    <input type="number" name="vat_usd" id="vat_usd" class="form-control" required placeholder="e.g., 18" min="0" step="0.01">
                </div>
                <div class="mb-3">
                    <label for="vat_zig" class="form-label">VAT for ZIG (%)</label>
                    <input type="number" name="vat_zig" id="vat_zig" class="form-control" required placeholder="e.g., 18" min="0" step="0.01">
                </div>
                <button type="submit" class="btn btn-primary w-100">Generate Receipt</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Static company info
$company_name = "HOPELINE GROCERIES SHOP";
$company_phone = "+263 712 581 565";
$company_address = "Zimbabwe";

// Get system username
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($system_username);
$stmt->fetch();
$stmt->close();

// Get receipt meta info (customer name, created_at)
$stmt = $conn->prepare("SELECT customer_name, created_at FROM stock_transactions st $filter_sql LIMIT 1");
$stmt->bind_param($filter_types, ...$filter_params);
$stmt->execute();
$stmt->bind_result($customer_name, $created_at);
$stmt->fetch();
$stmt->close();

// Get items
$stmt = $conn->prepare("SELECT i.item_name, st.quantity, i.unit_price, i.currency, (st.quantity * i.unit_price) as subtotal 
    FROM stock_transactions st
    JOIN items i ON st.item_id = i.id
    $filter_sql");
$stmt->bind_param($filter_types, ...$filter_params);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
$totals = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
    $currency = $row['currency'];
    if (!isset($totals[$currency])) $totals[$currency] = 0;
    $totals[$currency] += $row['subtotal'];
}
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Receipt</title>
  <style>
    * { font-family: 'Courier New', monospace; font-size: 12px; }
    body { width: 80mm; margin: 0 auto; }
    .center { text-align: center; }
    .receipt { padding: 10px; }
    .line { border-top: 1px dashed #000; margin: 6px 0; }
    table { width: 100%; }
    td { vertical-align: top; }
    .totals { font-weight: bold; }
    .footer { text-align: center; margin-top: 10px; font-size: 11px; }
    img.logo { max-width: 60px; display: block; margin: 0 auto 5px auto; }
    @media print { body { width: auto; } }
  </style>
</head>
<body onload="window.print()">
<div class="receipt">
  <div class="center mt-2">
    <img src="logo.png" alt="Company Logo" style="width:100px; height:100px;">
  </div>
  <div class="center">
    <strong><?= $company_name ?></strong><br>
    <?= $company_address ?><br>
    Tel: <?= $company_phone ?><br>
  </div>

  <div class="line"></div>

  <div>
    Receipt #: <strong><?= $_GET['receipt_id'] ?? 'N/A' ?></strong><br>
    Date: <?= date('Y-m-d H:i', strtotime($created_at)) ?><br>
    Customer: <?= htmlspecialchars($customer_name) ?><br>
    Served by: <?= htmlspecialchars($system_username) ?><br>
  </div>

  <div class="line"></div>

  <table>
    <thead>
      <tr>
        <td><strong>Item</strong></td>
        <td><strong>Qty</strong></td>
        <td><strong>P</strong></td>
        <td><strong>Sub</strong></td>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['item_name']) ?></td>
        <td><?= $item['quantity'] ?></td>
        <td><?= number_format($item['unit_price'], 0) ?>/<?= $item['currency'] ?></td>
        <td><?= number_format($item['subtotal'], 0) ?>/<?= $item['currency'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="line"></div>

  <table>
    <?php foreach ($totals as $currency => $amount):
      $vat = $currency === 'USD' ? ($vat_usd * $amount) : ($currency === 'ZIG' ? $vat_zig * $amount : 0);
      $net = $amount - $vat;
    ?>
      <tr><td colspan="3">Net Total (<?= $currency ?>)</td><td><?= number_format($net, 0) ?></td></tr>
      <tr><td colspan="3">VAT <?= number_format(($currency === 'USD' ? $vat_usd : $vat_zig) * 100, 1) ?>% (<?= $currency ?>)</td><td><?= number_format($vat, 0) ?></td></tr>
      <tr class="totals"><td colspan="3">TOTAL (<?= $currency ?>)</td><td><?= number_format($amount, 0) ?></td></tr>
    <?php endforeach; ?>
  </table>

  <div class="line"></div>

  <div class="footer">
    Thank you for shopping with us!<br>
    <?= date('Y-m-d H:i') ?><br>
    Powered by Hopeline Groceries Shop
  </div>

  <div class="center mt-2">
    <p><strong>Scan QR for Company Info</strong></p>
    <img src="qrcode.png" alt="QR Code" style="width:100px; height:100px;">
  </div>
</div>
</body>
</html>
