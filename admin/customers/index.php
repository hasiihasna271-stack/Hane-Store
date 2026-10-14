<?php
/**
 * HANÉ Admin - Customers Directory
 */
require_once __DIR__ . '/../_header.php';

$adminTitle = 'Customer Accounts';

$q = trim($_GET['q'] ?? '');
$where = ["1=1"];
$params = [];

if ($q !== '') {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT u.id, u.name, u.email, u.phone, u.created_at,
           COUNT(DISTINCT o.id) AS order_count,
           COALESCE(SUM(CASE WHEN o.status != 'Cancelled' THEN o.total ELSE 0 END), 0) AS total_spent
    FROM users u
    LEFT JOIN orders o ON o.user_id = u.id
    WHERE $whereClause
    GROUP BY u.id
    ORDER BY u.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Customer Directory</h1>
        <p>View registered clientele, purchase history, and lifetime customer value.</p>
    </div>
</div>

<div class="admin-card">
    <form method="get" action="<?= BASE_URL ?>admin/customers/index.php" style="display: flex; gap: 0.8rem; margin-bottom: 1.5rem; max-width: 500px;">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search customer name, email, or phone..." class="form-control" style="background: white;">
        <button class="btn btn-outline btn-sm" type="submit">Search</button>
        <?php if ($q): ?>
            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>admin/customers/index.php">Reset</a>
        <?php endif; ?>
    </form>

    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Email Address</th>
                    <th>Phone</th>
                    <th>Joined Date</th>
                    <th>Total Orders</th>
                    <th>Lifetime Spent</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--admin-accent);"><?= e($c['name']) ?></strong>
                            </td>
                            <td><?= e($c['email']) ?></td>
                            <td><?= e($c['phone'] ?: '&mdash;') ?></td>
                            <td class="muted" style="font-size: 0.82rem;"><?= date('M d, Y', strtotime($c['created_at'])) ?></td>
                            <td>
                                <span class="badge" style="background: #FAF7F1; border: 1px solid var(--admin-border); font-weight: 600;">
                                    <?= (int)$c['order_count'] ?> orders
                                </span>
                            </td>
                            <td>
                                <strong><?= money($c['total_spent']) ?></strong>
                            </td>
                            <td>
                                <a class="btn-icon" href="<?= BASE_URL ?>admin/customers/detail.php?id=<?= $c['id'] ?>" title="View Purchase History">
                                    <?= hane_icon('eye') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
                            No registered customers found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
