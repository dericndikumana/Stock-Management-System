<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../php/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo "<div class='alert alert-danger'>Access denied.</div>";
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

$selected_user = $user_id;
$transaction_type = $_GET['type'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Admin user filter
if ($user_role === 'admin' && isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $selected_user = intval($_GET['user_id']);
}

// Get user options
$user_options = [];
if ($user_role === 'admin') {
    $result = $conn->query("SELECT id, username FROM users ORDER BY username");
    while ($row = $result->fetch_assoc()) {
        $user_options[] = $row;
    }
}

// Count total records for pagination
$count_sql = "
    SELECT COUNT(*) FROM stock_transactions st
    JOIN items i ON st.item_id = i.id
    JOIN users u ON st.user_id = u.id
    WHERE st.user_id = ?
";
$count_params = [$selected_user];
$count_types = "i";

if (in_array($transaction_type, ['in', 'out'])) {
    $count_sql .= " AND st.transaction_type = ?";
    $count_params[] = $transaction_type;
    $count_types .= "s";
}
if (!empty($start_date)) {
    $count_sql .= " AND DATE(st.created_at) >= ?";
    $count_params[] = $start_date;
    $count_types .= "s";
}
if (!empty($end_date)) {
    $count_sql .= " AND DATE(st.created_at) <= ?";
    $count_params[] = $end_date;
    $count_types .= "s";
}

$stmt_count = $conn->prepare($count_sql);
$stmt_count->bind_param($count_types, ...$count_params);
$stmt_count->execute();
$stmt_count->bind_result($total_rows);
$stmt_count->fetch();
$stmt_count->close();

$total_pages = ceil($total_rows / $limit);

// Fetch paginated data
$sql = "
    SELECT st.id, st.transaction_type, st.quantity, st.created_at, st.customer_name, 
           i.item_name, i.unit_price, i.currency, u.username
    FROM stock_transactions st
    JOIN items i ON st.item_id = i.id
    JOIN users u ON st.user_id = u.id
    WHERE st.user_id = ?
";
$params = [$selected_user];
$types = "i";

if (in_array($transaction_type, ['in', 'out'])) {
    $sql .= " AND st.transaction_type = ?";
    $params[] = $transaction_type;
    $types .= "s";
}
if (!empty($start_date)) {
    $sql .= " AND DATE(st.created_at) >= ?";
    $params[] = $start_date;
    $types .= "s";
}
if (!empty($end_date)) {
    $sql .= " AND DATE(st.created_at) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

$sql .= " ORDER BY st.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container mt-4">
    <h2 class="mb-4">📋 System Report</h2>

    <form method="GET" class="row g-3 mb-3">
        <?php if ($user_role === 'admin'): ?>
        <div class="col-md-3">
            <label class="form-label">Filter by User</label>
            <select name="user_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select User --</option>
                <?php foreach ($user_options as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $user['id'] == $selected_user ? 'selected' : '' ?>>
                        <?= htmlspecialchars($user['username']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="col-md-2">
            <label class="form-label">Type</label>
            <select name="type" class="form-select" onchange="this.form.submit()">
                <option value="all" <?= $transaction_type === 'all' ? 'selected' : '' ?>>All</option>
                <option value="in" <?= $transaction_type === 'in' ? 'selected' : '' ?>>Stock In</option>
                <option value="out" <?= $transaction_type === 'out' ? 'selected' : '' ?>>Stock Out</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="form-control" onchange="this.form.submit()">
        </div>

        <div class="col-md-2">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="form-control" onchange="this.form.submit()">
        </div>
    </form>

    <!-- print button  -->
    <?php if ($result->num_rows > 0): ?>
    <div class="mb-3">
        <button onclick="printReport()" class="btn btn-outline-primary">🖨️ Print Report</button>
    </div>
    <?php endif; ?>
    <!-- end of print button  -->

    <div id="reportTable" style="max-height: 450px; overflow-y: auto;">

        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Unit Price</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $i = $offset + 1;
            if ($result->num_rows > 0):
                while ($row = $result->fetch_assoc()):
            ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                    <td><?= number_format($row['unit_price'], 2) . '/' . htmlspecialchars($row['currency']) ?></td>
                    <td><?= ucfirst($row['transaction_type']) ?></td>
                    <td><?= $row['quantity'] ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="7" class="text-center">No records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link">Previous</span></li>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link">Next</span></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<script>
function printReport() {
    const printContent = document.getElementById("reportTable").innerHTML;

    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>System Report</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2>System Report</h2>');
    printWindow.document.write(printContent);
    printWindow.document.write('</body></html>');

    printWindow.document.close();
    printWindow.focus();

    printWindow.print();
    printWindow.close();
}
</script>
