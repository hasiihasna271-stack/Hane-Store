<?php
/**
 * HANÉ Admin - Customer Reviews Moderation
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$adminTitle = 'Customer Reviews';

// Handle Action (Approve / Reject / Delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $revId = (int)$_GET['id'];

    if ($action === 'approve') {
        $pdo->prepare("UPDATE reviews SET status = 'Approved' WHERE id = ?")->execute([$revId]);
        set_flash('success', 'Review approved and published.');
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE reviews SET status = 'Rejected' WHERE id = ?")->execute([$revId]);
        set_flash('info', 'Review rejected.');
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([$revId]);
        set_flash('success', 'Review deleted.');
    }
    header('Location: ' . BASE_URL . 'admin/reviews/index.php');
    exit;
}

// Fetch all reviews with product details
$sql = "
    SELECT r.*, p.name AS product_name, p.featured_image
    FROM reviews r
    JOIN products p ON p.id = r.product_id
    ORDER BY r.id DESC
";
$reviews = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Reviews Moderation</h1>
        <p>Review customer testimonials, verify ratings, and approve public visibility.</p>
    </div>
</div>

<div class="admin-card">
    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Customer</th>
                    <th>Rating</th>
                    <th>Review Content</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $rev): ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 0.7rem; align-items: center;">
                                    <img src="<?= get_image_url($rev['featured_image']) ?>" alt="" style="width: 36px; height: 48px; object-fit: cover; border-radius: 4px;">
                                    <strong><?= e($rev['product_name']) ?></strong>
                                </div>
                            </td>
                            <td><?= e($rev['customer_name']) ?></td>
                            <td>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= ($i <= (int)$rev['rating']) ? hane_icon('star') : hane_icon('star-empty') ?>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td style="max-width: 320px;">
                                <p style="font-size: 0.88rem; line-height: 1.5; color: #444; margin: 0;">
                                    <?= nl2br(e($rev['comment'])) ?>
                                </p>
                            </td>
                            <td class="muted" style="font-size: 0.8rem;"><?= date('M d, Y', strtotime($rev['created_at'])) ?></td>
                            <td>
                                <span class="status-badge <?= ($rev['status'] === 'Approved') ? 'status-delivered' : (($rev['status'] === 'Pending') ? 'status-pending' : 'status-cancelled') ?>">
                                    <?= e($rev['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <?php if ($rev['status'] !== 'Approved'): ?>
                                        <a class="btn-icon" href="<?= BASE_URL ?>admin/reviews/index.php?action=approve&id=<?= $rev['id'] ?>" title="Approve Review" style="color: var(--admin-success);">
                                            <?= hane_icon('check') ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($rev['status'] !== 'Rejected'): ?>
                                        <a class="btn-icon" href="<?= BASE_URL ?>admin/reviews/index.php?action=reject&id=<?= $rev['id'] ?>" title="Reject Review" style="color: #d97706;">
                                            &times;
                                        </a>
                                    <?php endif; ?>
                                    <a class="btn-icon btn-danger" href="<?= BASE_URL ?>admin/reviews/index.php?action=delete&id=<?= $rev['id'] ?>" onclick="return confirm('Delete this review completely?');" title="Delete Review">
                                        <?= hane_icon('trash') ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
                            No customer reviews submitted yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
