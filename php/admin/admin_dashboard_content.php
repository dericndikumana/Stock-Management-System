<?php
include '../../php/db_connect.php';

// Total registered users
$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];

// Get total items in stock
$result = $conn->query("SELECT COUNT(*) as count FROM items");
$total_items = $result->fetch_assoc()['count'];

// Total value of stock (USD)
$usd = $conn->query("SELECT SUM(quantity * unit_price) as total FROM items WHERE currency = 'USD'")->fetch_assoc()['total'] ?? 0;

// Total value of stock (ZIG)
$zig = $conn->query("SELECT SUM(quantity * unit_price) as total FROM items WHERE currency = 'ZIG'")->fetch_assoc()['total'] ?? 0;

// Stockout USD
$usd_out = $conn->query("SELECT SUM(st.quantity * i.unit_price) as total 
    FROM stock_transactions st 
    JOIN items i ON st.item_id = i.id 
    WHERE st.transaction_type = 'out' AND i.currency = 'USD'")->fetch_assoc()['total'] ?? 0;

// Stockout ZIG
$zig_out = $conn->query("SELECT SUM(st.quantity * i.unit_price) as total 
    FROM stock_transactions st 
    JOIN items i ON st.item_id = i.id 
    WHERE st.transaction_type = 'out' AND i.currency = 'ZIG'")->fetch_assoc()['total'] ?? 0;
?>

<div class="container">
    <h4 class="mb-4">📊 Admin Dashboard Overview</h4>
    <div class="row g-3">
        <!-- Total Users -->
        <div class="col-md-4">
            <div class="card text-white bg-info shadow p-3">
                <h5>👥 Total Users</h5>
                <p class="fw-bold fs-4"><?= $total_users ?></p>
            </div>
        </div>

        <!-- Total Items -->
        <div class="col-md-4">
            <div class="card text-white bg-primary shadow p-3">
                <h5>📦 Total Items</h5>
                <p class="fw-bold fs-4"><?= $total_items ?></p>
            </div>
        </div>

        <!-- Stock Value (USD) -->
        <div class="col-md-4">
            <div class="card text-white bg-success shadow p-3">
                <h5>💰 Stock Value (USD)</h5>
                <p class="fw-bold fs-4">$<?= number_format($usd, 2) ?></p>
            </div>
        </div>

        <!-- Stock Value (ZIG) -->
        <div class="col-md-4">
            <div class="card text-white bg-warning shadow p-3">
                <h5>💰 Stock Value (ZIG)</h5>
                <p class="fw-bold fs-4"><?= number_format($zig, 2) ?> ZIG</p>
            </div>
        </div>

        <!-- Stock Out (USD) -->
        <div class="col-md-4">
            <div class="card text-white bg-danger shadow p-3">
                <h5>📤 Stock Out (USD)</h5>
                <p class="fw-bold fs-4">$<?= number_format($usd_out, 2) ?></p>
            </div>
        </div>

        <!-- Stock Out (ZIG) -->
        <div class="col-md-4">
            <div class="card text-dark bg-light shadow p-3">
                <h5>📤 Stock Out (ZIG)</h5>
                <p class="fw-bold fs-4"><?= number_format($zig_out, 2) ?> ZIG</p>
            </div>
        </div>
    </div>
</div>

<!-- System Users Table -->
<?php
$users_per_page = 5;
$page_number = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page_number - 1) * $users_per_page;

$total_users_table = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$total_pages = ceil($total_users_table / $users_per_page);

$stmt = $conn->prepare("SELECT full_name, username, role FROM users ORDER BY full_name LIMIT ?, ?");
$stmt->bind_param("ii", $offset, $users_per_page);
$stmt->execute();
$users_result = $stmt->get_result();
?>

<div class="mt-5">
    <h5>👥 System Users</h5>
    <div style="max-height: 300px; overflow-y: auto;">
        <table class="table table-bordered table-striped mt-3">
            <thead class="table-dark">
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Username</th>
                    <th>Role</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $i = $offset + 1;
                while ($row = $users_result->fetch_assoc()):
                ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= ucfirst($row['role']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <nav aria-label="User pagination" class="mt-3">
        <ul class="pagination">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page_number ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>
