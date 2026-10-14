<?php
/**
 * HANÉ Admin - Edit Product & Manage Variants
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    header('Location: ' . BASE_URL . 'admin/products/index.php');
    exit;
}

$pStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$pStmt->execute([$productId]);
$product = $pStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    set_flash('error', 'Product not found.');
    header('Location: ' . BASE_URL . 'admin/products/index.php');
    exit;
}

$adminTitle = 'Edit: ' . $product['name'];
$error = '';

// Handle Image Deletion
if (isset($_GET['delete_img'])) {
    $imgId = (int)$_GET['delete_img'];
    $del = $pdo->prepare("DELETE FROM product_images WHERE id = ? AND product_id = ?");
    $del->execute([$imgId, $productId]);
    set_flash('info', 'Gallery image removed.');
    header('Location: ' . BASE_URL . 'admin/products/edit.php?id=' . $productId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = slugify($_POST['slug'] ?? '' ?: $name);
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $description = trim($_POST['description'] ?? '');
    $regularPrice = (float)($_POST['regular_price'] ?? 0);
    $salePriceInput = trim($_POST['sale_price'] ?? '');
    $salePrice = ($salePriceInput !== '' && is_numeric($salePriceInput)) ? (float)$salePriceInput : null;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isNew = isset($_POST['is_new']) ? 1 : 0;
    $isSale = isset($_POST['is_sale']) ? 1 : 0;
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($name)) {
        $error = 'Product name is required.';
    } elseif ($regularPrice <= 0) {
        $error = 'Valid regular price is required.';
    } else {
        // Ensure slug uniqueness among other products
        $chk = $pdo->prepare("SELECT id FROM products WHERE slug = ? AND id != ?");
        $chk->execute([$slug, $productId]);
        if ($chk->fetch()) {
            $slug .= '-' . rand(100, 999);
        }

        $featuredImagePath = $product['featured_image'];
        if (!empty($_FILES['featured_image']['name'])) {
            $upRes = upload_image_file($_FILES['featured_image'], 'products');
            if ($upRes['success']) {
                $featuredImagePath = $upRes['path'];
            } else {
                $error = 'Featured Image: ' . $upRes['error'];
            }
        }

        if (empty($error)) {
            try {
                $pdo->beginTransaction();

                // 1. Update Product
                $uStmt = $pdo->prepare("UPDATE products SET
                    category_id = ?, name = ?, slug = ?, description = ?,
                    regular_price = ?, sale_price = ?, featured_image = ?,
                    is_featured = ?, is_new = ?, is_sale = ?, status = ?
                    WHERE id = ?");
                
                $uStmt->execute([
                    $categoryId,
                    $name,
                    $slug,
                    $description,
                    $regularPrice,
                    $salePrice,
                    $featuredImagePath,
                    $isFeatured,
                    $isNew,
                    $isSale,
                    $status,
                    $productId
                ]);

                // 2. Secondary Image
                if (!empty($_FILES['secondary_image']['name'])) {
                    $secRes = upload_image_file($_FILES['secondary_image'], 'products');
                    if ($secRes['success']) {
                        // Remove old secondary
                        $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND is_secondary = 1")->execute([$productId]);
                        $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_secondary, sort_order) VALUES (?, ?, 1, 1)")
                            ->execute([$productId, $secRes['path']]);
                    }
                }

                // 3. Extra Gallery Images
                if (!empty($_FILES['gallery_images']['name'][0])) {
                    $filesCount = count($_FILES['gallery_images']['name']);
                    for ($i = 0; $i < $filesCount; $i++) {
                        if ($_FILES['gallery_images']['error'][$i] === UPLOAD_ERR_OK) {
                            $singleFile = [
                                'name'     => $_FILES['gallery_images']['name'][$i],
                                'type'     => $_FILES['gallery_images']['type'][$i],
                                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                                'error'    => $_FILES['gallery_images']['error'][$i],
                                'size'     => $_FILES['gallery_images']['size'][$i],
                            ];
                            $gRes = upload_image_file($singleFile, 'products');
                            if ($gRes['success']) {
                                $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_secondary, sort_order) VALUES (?, ?, 0, 5)")
                                    ->execute([$productId, $gRes['path']]);
                            }
                        }
                    }
                }

                // 4. Update / Insert Variants
                if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
                    $existingVariantIds = [];

                    foreach ($_POST['variants'] as $vKey => $v) {
                        $vId = (int)($v['id'] ?? 0);
                        $vColor = trim($v['color'] ?? '');
                        $vColorCode = trim($v['color_code'] ?? '#E7B1AE');
                        $vSize = trim($v['size'] ?? 'M');
                        $vStock = max(0, (int)($v['stock'] ?? 0));
                        $vSku = trim($v['sku'] ?? '');

                        if (empty($vColor) || empty($vSize)) continue;

                        if ($vId > 0) {
                            // Update existing variant
                            $uVar = $pdo->prepare("UPDATE product_variants SET color = ?, color_code = ?, size = ?, stock = ?, sku = ? WHERE id = ? AND product_id = ?");
                            $uVar->execute([$vColor, $vColorCode, $vSize, $vStock, $vSku ?: null, $vId, $productId]);
                            $existingVariantIds[] = $vId;
                        } else {
                            // Insert new variant
                            $iVar = $pdo->prepare("INSERT INTO product_variants (product_id, color, color_code, size, stock, sku) VALUES (?, ?, ?, ?, ?, ?)");
                            $iVar->execute([$productId, $vColor, $vColorCode, $vSize, $vStock, $vSku ?: null]);
                            $existingVariantIds[] = (int)$pdo->lastInsertId();
                        }
                    }

                    // Remove variants that were deleted from form (if any)
                    if (!empty($existingVariantIds)) {
                        $placeholders = implode(',', array_fill(0, count($existingVariantIds), '?'));
                        $delParams = array_merge([$productId], $existingVariantIds);
                        $pdo->prepare("DELETE FROM product_variants WHERE product_id = ? AND id NOT IN ($placeholders)")->execute($delParams);
                    }
                }

                $pdo->commit();
                set_flash('success', "Product '{$name}' updated successfully!");
                header('Location: ' . BASE_URL . 'admin/products/index.php');
                exit;

            } catch (Exception $ex) {
                $pdo->rollBack();
                $error = 'Database update error: ' . $ex->getMessage();
            }
        }
    }
}

// Fetch current variants
$vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY color ASC, size ASC");
$vStmt->execute([$productId]);
$variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current gallery images
$imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_secondary DESC, sort_order ASC");
$imgStmt->execute([$productId]);
$galleryImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Edit Product: <?= e($product['name']) ?></h1>
        <p>Update product specifications, photography, and variant stock levels.</p>
    </div>
    <div style="display: flex; gap: 0.8rem;">
        <a class="btn btn-outline" href="<?= BASE_URL ?>product.php?id=<?= $productId ?>" target="_blank">
            <?= hane_icon('eye') ?> Preview on Store
        </a>
        <a class="btn btn-outline" href="<?= BASE_URL ?>admin/products/index.php">
            &larr; Back to Catalog
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div style="background: #fdf2f2; border-left: 4px solid #c2413a; color: #c2413a; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= BASE_URL ?>admin/products/edit.php?id=<?= $productId ?>" enctype="multipart/form-data">
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
        
        <!-- Left Column -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Basic Information</h2>

                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input class="form-control" type="text" name="name" value="<?= e($product['name']) ?>" required>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ((int)$product['category_id'] === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug / URL Key</label>
                        <input class="form-control" type="text" name="slug" value="<?= e($product['slug']) ?>" required>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="5"><?= e($product['description']) ?></textarea>
                </div>
            </div>

            <!-- Pricing -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Pricing (Rs.)</h2>
                <div class="form-row-2">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Regular Price (Rs.) *</label>
                        <input class="form-control" type="number" step="0.01" name="regular_price" value="<?= e($product['regular_price']) ?>" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Sale Price (Rs.) (Leave empty if no sale)</label>
                        <input class="form-control" type="number" step="0.01" name="sale_price" value="<?= e($product['sale_price'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Inventory Variants Table / Builder -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2 class="admin-card-title">Inventory Variants</h2>
                        <p class="muted" style="font-size: 0.82rem; margin-top: 0.2rem;">
                            Update stock per Color and Size combination. Set stock to 0 to trigger "Out of Stock".
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" id="addVariantBtn">
                        + Add Variant
                    </button>
                </div>

                <div id="variantContainer">
                    <?php foreach ($variants as $idx => $v): ?>
                        <div class="variant-row">
                            <input type="hidden" name="variants[<?= $idx ?>][id]" value="<?= $v['id'] ?>">
                            <div>
                                <input type="text" name="variants[<?= $idx ?>][color]" value="<?= e($v['color']) ?>" placeholder="Color" class="form-control" required>
                            </div>
                            <div>
                                <input type="color" name="variants[<?= $idx ?>][color_code]" value="<?= e($v['color_code'] ?: '#E7B1AE') ?>" class="form-control" style="padding: 2px; height: 38px;">
                            </div>
                            <div>
                                <select name="variants[<?= $idx ?>][size]" class="form-control" required>
                                    <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'Free Size'] as $szOption): ?>
                                        <option value="<?= $szOption ?>" <?= ($v['size'] === $szOption) ? 'selected' : '' ?>><?= $szOption ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <input type="number" name="variants[<?= $idx ?>][stock]" min="0" value="<?= (int)$v['stock'] ?>" placeholder="Stock" class="form-control" required>
                            </div>
                            <div>
                                <input type="text" name="variants[<?= $idx ?>][sku]" value="<?= e($v['sku'] ?? '') ?>" placeholder="SKU" class="form-control">
                            </div>
                            <div>
                                <button type="button" class="btn-icon btn-danger remove-variant-btn" title="Remove">&times;</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Visibility & Publish -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Visibility &amp; Badges</h2>

                <div style="display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1.5rem;">
                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="status" value="1" <?= $product['status'] ? 'checked' : '' ?>>
                        <span>Active / Live on Storefront</span>
                    </label>

                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="is_featured" value="1" <?= $product['is_featured'] ? 'checked' : '' ?>>
                        <span>Featured on Homepage</span>
                    </label>

                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="is_new" value="1" <?= $product['is_new'] ? 'checked' : '' ?>>
                        <span>Mark as New Arrival</span>
                    </label>

                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="is_sale" value="1" <?= $product['is_sale'] ? 'checked' : '' ?>>
                        <span>Mark as Promotional Sale</span>
                    </label>
                </div>

                <button class="btn btn-primary btn-full btn-lg" type="submit">
                    Save Changes
                </button>
            </div>

            <!-- Featured Image -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1rem;">Primary Featured Image</h2>
                <?php if ($product['featured_image']): ?>
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <img src="<?= get_image_url($product['featured_image']) ?>" alt="Current featured" style="max-height: 180px; margin: 0 auto; border-radius: 8px; border: 1px solid var(--admin-border);">
                    </div>
                <?php endif; ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Replace Image</label>
                    <input class="form-control" type="file" name="featured_image" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>

            <!-- Secondary Image -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1rem;">Secondary Hover Image</h2>
                <?php 
                    $secImgRow = null;
                    foreach ($galleryImages as $gi) {
                        if ($gi['is_secondary']) { $secImgRow = $gi; break; }
                    }
                ?>
                <?php if ($secImgRow): ?>
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <img src="<?= get_image_url($secImgRow['image_path']) ?>" alt="Current secondary" style="max-height: 180px; margin: 0 auto; border-radius: 8px; border: 1px solid var(--admin-border);">
                    </div>
                <?php endif; ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Replace Secondary Image</label>
                    <input class="form-control" type="file" name="secondary_image" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>

            <!-- Extra Gallery Images -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1rem;">Gallery Images</h2>
                <?php if (!empty($galleryImages)): ?>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem; margin-bottom: 1.2rem;">
                        <?php foreach ($galleryImages as $gImg): ?>
                            <div style="position: relative; border-radius: 6px; overflow: hidden; border: 1px solid var(--admin-border);">
                                <img src="<?= get_image_url($gImg['image_path']) ?>" alt="" style="width: 100%; aspect-ratio: 1; object-fit: cover;">
                                <a href="<?= BASE_URL ?>admin/products/edit.php?id=<?= $productId ?>&delete_img=<?= $gImg['id'] ?>" 
                                   onclick="return confirm('Delete this image?');"
                                   style="position: absolute; top: 4px; right: 4px; background: rgba(194,65,58,0.9); color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 14px; text-decoration: none;"
                                   title="Delete image">&times;</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Add More Images</label>
                    <input class="form-control" type="file" name="gallery_images[]" multiple accept="image/jpeg,image/png,image/webp">
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../_footer.php'; ?>
