<?php
/**
 * HANÉ Admin - Categories Management
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$adminTitle = 'Categories';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $name = trim($_POST['name'] ?? '');
    $slug = slugify($_POST['slug'] ?? '' ?: $name);
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $error = 'Category name is required.';
    } else {
        // Check duplicate
        $chk = $pdo->prepare("SELECT id FROM categories WHERE slug = ? OR name = ?");
        $chk->execute([$slug, $name]);
        if ($chk->fetch()) {
            $error = 'A category with this name or slug already exists.';
        } else {
            $imagePath = null;
            if (!empty($_FILES['image']['name'])) {
                $upRes = upload_image_file($_FILES['image'], 'categories');
                if ($upRes['success']) {
                    $imagePath = $upRes['path'];
                } else {
                    $error = 'Image: ' . $upRes['error'];
                }
            }

            if (empty($error)) {
                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, image, status) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$name, $slug, $description, $imagePath]);
                set_flash('success', "Category '{$name}' created successfully.");
                header('Location: ' . BASE_URL . 'admin/categories/index.php');
                exit;
            }
        }
    }
}

// Fetch categories with product counts
$sql = "
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id 
    GROUP BY c.id 
    ORDER BY c.name ASC
";
$categories = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Category Management</h1>
        <p>Organize collections and storefront navigation sections.</p>
    </div>
</div>

<?php if ($error): ?>
    <div style="background: #fdf2f2; border-left: 4px solid #c2413a; color: #c2413a; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 2rem;">
    <!-- Add Category Card -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Add New Category</h2>
        <form method="post" action="<?= BASE_URL ?>admin/categories/index.php" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">Category Name *</label>
                <input class="form-control" type="text" name="name" placeholder="e.g. Resort Wear" required>
            </div>

            <div class="form-group">
                <label class="form-label">Slug / Key (Optional)</label>
                <input class="form-control" type="text" name="slug" placeholder="e.g. resort-wear">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="3" placeholder="Category story / highlights..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Category Image</label>
                <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <small class="muted">Square photo recommended (e.g. 600x600px)</small>
            </div>

            <button class="btn btn-primary btn-full" type="submit" name="add_category">
                Create Category
            </button>
        </form>
    </div>

    <!-- Category List Card -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Current Categories</h2>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Slug</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 0.8rem; align-items: center;">
                                    <img src="<?= get_image_url($cat['image']) ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid var(--admin-border);">
                                    <div>
                                        <strong><?= e($cat['name']) ?></strong>
                                        <div class="muted" style="font-size: 0.75rem;"><?= e(substr($cat['description'] ?? '', 0, 45)) ?>...</div>
                                    </div>
                                </div>
                            </td>
                            <td><code><?= e($cat['slug']) ?></code></td>
                            <td>
                                <a href="<?= BASE_URL ?>admin/products/index.php?category_id=<?= $cat['id'] ?>" style="text-decoration: underline; font-weight: 600; color: var(--admin-accent);">
                                    <?= (int)$cat['product_count'] ?> products
                                </a>
                            </td>
                            <td>
                                <span class="status-badge <?= $cat['status'] ? 'status-delivered' : 'status-cancelled' ?>">
                                    <?= $cat['status'] ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a class="btn-icon" href="<?= BASE_URL ?>admin/categories/edit.php?id=<?= $cat['id'] ?>" title="Edit">
                                        <?= hane_icon('dashboard') ?>
                                    </a>
                                    <a class="btn-icon btn-danger" href="<?= BASE_URL ?>admin/categories/delete.php?id=<?= $cat['id'] ?>" onclick="return confirm('Are you sure you want to delete category <?= addslashes($cat['name']) ?>?');" title="Delete">
                                        <?= hane_icon('trash') ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
