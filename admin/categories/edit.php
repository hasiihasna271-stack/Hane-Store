<?php
/**
 * HANÉ Admin - Edit Category
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$catId = (int)($_GET['id'] ?? 0);
if ($catId <= 0) {
    header('Location: ' . BASE_URL . 'admin/categories/index.php');
    exit;
}

$cStmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$cStmt->execute([$catId]);
$category = $cStmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    set_flash('error', 'Category not found.');
    header('Location: ' . BASE_URL . 'admin/categories/index.php');
    exit;
}

$adminTitle = 'Edit Category: ' . $category['name'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = slugify($_POST['slug'] ?? '' ?: $name);
    $description = trim($_POST['description'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        $error = 'Category name cannot be empty.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM categories WHERE (slug = ? OR name = ?) AND id != ?");
        $chk->execute([$slug, $name, $catId]);
        if ($chk->fetch()) {
            $error = 'Another category already exists with that name or slug.';
        } else {
            $imagePath = $category['image'];
            if (!empty($_FILES['image']['name'])) {
                $upRes = upload_image_file($_FILES['image'], 'categories');
                if ($upRes['success']) {
                    $imagePath = $upRes['path'];
                } else {
                    $error = 'Image: ' . $upRes['error'];
                }
            }

            if (empty($error)) {
                $uStmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, image = ?, status = ? WHERE id = ?");
                $uStmt->execute([$name, $slug, $description, $imagePath, $status, $catId]);
                set_flash('success', "Category '{$name}' updated successfully.");
                header('Location: ' . BASE_URL . 'admin/categories/index.php');
                exit;
            }
        }
    }
}
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Edit Category: <?= e($category['name']) ?></h1>
    </div>
    <div>
        <a class="btn btn-outline" href="<?= BASE_URL ?>admin/categories/index.php">
            &larr; Back to Categories
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div style="background: #fdf2f2; border-left: 4px solid #c2413a; color: #c2413a; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-card" style="max-width: 650px;">
    <form method="post" action="<?= BASE_URL ?>admin/categories/edit.php?id=<?= $catId ?>" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label">Category Name *</label>
            <input class="form-control" type="text" name="name" value="<?= e($category['name']) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Slug</label>
            <input class="form-control" type="text" name="slug" value="<?= e($category['slug']) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="3"><?= e($category['description']) ?></textarea>
        </div>

        <?php if ($category['image']): ?>
            <div style="margin-bottom: 1rem;">
                <label class="form-label">Current Image</label>
                <img src="<?= get_image_url($category['image']) ?>" alt="" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 1px solid var(--admin-border);">
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label class="form-label">Replace Image</label>
            <input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="filter-checkbox-label">
                <input type="checkbox" name="status" value="1" <?= $category['status'] ? 'checked' : '' ?>>
                <span>Active (Visible in storefront navigation)</span>
            </label>
        </div>

        <button class="btn btn-primary" type="submit">
            Save Changes
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
