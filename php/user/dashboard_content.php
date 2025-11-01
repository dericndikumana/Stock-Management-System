<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../php/db_connect.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Determine filter scope based on role
$where = ($role === 'admin') ? '1' : 'user_id = ' . intval($user_id);

// Total number of items in stock
$total_items_query = $conn->query("SELECT COUNT(*) as total FROM items WHERE $where");
$total_items = $total_items_query->fetch_assoc()['total'] ?? 0;

// Total value of items in stock by currency
$stock_value_query = $conn->query("SELECT currency, SUM(quantity * unit_price) as total FROM items WHERE $where GROUP BY currency");
$stock_values = [];
while ($row = $stock_value_query->fetch_assoc()) {
    $stock_values[$row['currency']] = $row['total'];
}

// Total stockout value by currency
$stockout_query = $conn->prepare("SELECT i.currency, SUM(st.quantity * i.unit_price) as total
    FROM stock_transactions st
    JOIN items i ON st.item_id = i.id
    WHERE st.transaction_type = 'out' AND st.deleted = 0 AND st.user_id = ?
    GROUP BY i.currency");
$stockout_query->bind_param("i", $user_id);
$stockout_query->execute();
$stockout_result = $stockout_query->get_result();
$stockout_values = [];
while ($row = $stockout_result->fetch_assoc()) {
    $stockout_values[$row['currency']] = $row['total'];
}
$stockout_query->close();

?>

<div class="container mt-4">
  <h4>👋 Welcome, <?= htmlspecialchars($_SESSION['full_name']); ?>!</h4>
  <p>This is your <?= htmlspecialchars($role) ?> dashboard. You can manage your own items, stock, and generate receipts here.</p>

  <div class="row mt-4">
    <!-- Total Items -->
    <div class="col-md-3">
      <div class="card text-white bg-primary mb-3">
        <div class="card-body">
          <h5 class="card-title">📦 Total Items</h5>
          <p class="card-text fs-4"><?= number_format($total_items) ?></p>
        </div>
      </div>
    </div>

    <!-- Stock Value (USD/ZIG) -->
    <?php foreach (["USD", "ZIG"] as $curr): ?>
    <div class="col-md-3">
      <div class="card text-white bg-success mb-3">
        <div class="card-body">
          <h5 class="card-title">💰 Stock Value (<?= $curr ?>)</h5>
          <p class="card-text fs-5"><?= number_format($stock_values[$curr] ?? 0, 2) ?> <?= $curr ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Stockout Value (USD/ZIG) -->
    <?php foreach (["USD", "ZIG"] as $curr): ?>
    <div class="col-md-3">
      <div class="card text-white bg-danger mb-3">
        <div class="card-body">
          <h5 class="card-title">📤 Stock Out (<?= $curr ?>)</h5>
          <p class="card-text fs-5"><?= number_format($stockout_values[$curr] ?? 0, 2) ?> <?= $curr ?></p>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- View Report Button -->
    <div class="col-md-3">
      <div class="card bg-dark text-white mb-3">
        <div class="card-body text-center">
          <h5 class="card-title">🧾 System Report</h5>
          <a href="receipt_history.php" class="btn btn-light mt-2">View Reports</a>
        </div>
      </div>
    </div>
  </div>

  Chart Section
  <div class="bg-white p-3 mt-4 rounded shadow">
    <h5 class="mb-3">📊 Stock Flow (Demo)</h5>
    <canvas id="flowChart" height="100"></canvas>
  </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('flowChart').getContext('2d');
const flowChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [
            {
                label: 'Stock In ($)',
                data: [1200, 1900, 3000, 500, 2000, 3000],
                backgroundColor: 'rgba(54, 162, 235, 0.7)'
            },
            {
                label: 'Stock Out ($)',
                data: [800, 1500, 2500, 400, 1800, 2200],
                backgroundColor: 'rgba(255, 99, 132, 0.7)'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Monthly Stock Flow (USD)'
            }
        }
    }
});
</script>
