<?php
/**
 * HANÉ Admin - Add New Product
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$adminTitle = 'Add New Product';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf()) {
    $error = 'Your session has expired. Please try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        // Check unique slug
        $chk = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
        $chk->execute([$slug]);
        if ($chk->fetch()) {
            $slug .= '-' . rand(100, 999);
        }

        // Handle Featured Image Upload
        $featuredImagePath = null;
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

                $pStmt = $pdo->prepare("INSERT INTO products (
                    category_id, name, slug, description, regular_price, sale_price,
                    featured_image, is_featured, is_new, is_sale, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $pStmt->execute([
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
                    $status
                ]);

                $newProductId = (int)$pdo->lastInsertId();

                // Handle Secondary Hover Image Upload
                if (!empty($_FILES['secondary_image']['name'])) {
                    $secRes = upload_image_file($_FILES['secondary_image'], 'products');
                    if ($secRes['success']) {
                        $sImgStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_secondary, sort_order) VALUES (?, ?, 1, 1)");
                        $sImgStmt->execute([$newProductId, $secRes['path']]);
                    }
                }

                // Handle Additional Gallery Images Upload
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
                                $gStmt = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_secondary, sort_order) VALUES (?, ?, 0, ?)");
                                $gStmt->execute([$newProductId, $gRes['path'], $i + 2]);
                            }
                        }
                    }
                }

                // Handle Variants (Color + Size + Stock combination!)
                if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
                    $vInsStmt = $pdo->prepare("INSERT INTO product_variants (product_id, color, color_code, size, stock, sku) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($_POST['variants'] as $v) {
                        $vColor = trim($v['color'] ?? '');
                        $vColorCode = trim($v['color_code'] ?? '#E7B1AE');
                        $vSize = trim($v['size'] ?? 'M');
                        $vStock = max(0, (int)($v['stock'] ?? 0));
                        $vSku = trim($v['sku'] ?? '');

                        if (!empty($vColor) && !empty($vSize)) {
                            $vInsStmt->execute([$newProductId, $vColor, $vColorCode, $vSize, $vStock, $vSku ?: null]);
                        }
                    }
                } else {
                    // Fallback default variant so product is purchasable
                    $pdo->prepare("INSERT INTO product_variants (product_id, color, color_code, size, stock, sku) VALUES (?, 'Default', '#FAF7F1', 'Free Size', 10, NULL)")
                        ->execute([$newProductId]);
                }

                $pdo->commit();
                set_flash('success', "Product '{$name}' created successfully!");
                header('Location: ' . BASE_URL . 'admin/products/index.php');
                exit;

            } catch (Exception $ex) {
                $pdo->rollBack();
                $error = 'Database error: ' . $ex->getMessage();
            }
        }
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Add New Product</h1>
        <p>Define product details, photography, and variant stock.</p>
    </div>
    <div>
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

<form method="post" action="<?= BASE_URL ?>admin/products/add.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
        
        <!-- Left Column: Details, Description, Variants -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Basic Information</h2>

                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input class="form-control" type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Silk Wrap Blouse" required>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="category_id">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ((int)($_POST['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug / URL Key (Optional)</label>
                        <input class="form-control" type="text" name="slug" value="<?= e($_POST['slug'] ?? '') ?>" placeholder="leave blank to auto-generate">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="5" placeholder="Detailed product story, fabric specs, and fit notes..."><?= e($_POST['description'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Pricing -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Pricing (Rs.)</h2>
                <div class="form-row-2">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Regular Price (Rs.) *</label>
                        <input class="form-control" type="number" step="0.01" name="regular_price" value="<?= e($_POST['regular_price'] ?? '') ?>" placeholder="4990.00" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Sale Price (Rs.) (Optional)</label>
                        <input class="form-control" type="number" step="0.01" name="sale_price" value="<?= e($_POST['sale_price'] ?? '') ?>" placeholder="3990.00">
                    </div>
                </div>
            </div>

            <!-- Dynamic Product Variants (Color, Size, Stock per variant) -->
            <div class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2 class="admin-card-title">Inventory Variants</h2>
                        <p class="muted" style="font-size: 0.82rem; margin-top: 0.2rem;">
                            Stock is tracked separately per Color and Size combination (e.g. Pink + S = 5, Pink + L = 0).
                        </p>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" id="addVariantBtn">
                        + Add Variant
                    </button>
                </div>

                <div id="variantContainer">
                    <!-- Default Initial Variant Rows -->
                    <div class="variant-row">
                        <div>
                            <input type="text" name="variants[0][color]" value="Blush" placeholder="Color" class="form-control" required>
                        </div>
                        <div>
                            <input type="color" name="variants[0][color_code]" value="#E7B1AE" class="form-control" style="padding: 2px; height: 38px;">
                        </div>
                        <div>
                            <select name="variants[0][size]" class="form-control" required>
                                <option value="XS">XS</option>
                                <option value="S" selected>S</option>
                                <option value="M">M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                            </select>
                        </div>
                        <div>
                            <input type="number" name="variants[0][stock]" min="0" value="5" placeholder="Stock" class="form-control" required>
                        </div>
                        <div>
                            <input type="text" name="variants[0][sku]" placeholder="SKU" class="form-control">
                        </div>
                        <div>
                            <button type="button" class="btn-icon btn-danger remove-variant-btn" title="Remove">&times;</button>
                        </div>
                    </div>

                    <div class="variant-row">
                        <div>
                            <input type="text" name="variants[1][color]" value="Blush" placeholder="Color" class="form-control" required>
                        </div>
                        <div>
                            <input type="color" name="variants[1][color_code]" value="#E7B1AE" class="form-control" style="padding: 2px; height: 38px;">
                        </div>
                        <div>
                            <select name="variants[1][size]" class="form-control" required>
                                <option value="XS">XS</option>
                                <option value="S">S</option>
                                <option value="M" selected>M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                            </select>
                        </div>
                        <div>
                            <input type="number" name="variants[1][stock]" min="0" value="8" placeholder="Stock" class="form-control" required>
                        </div>
                        <div>
                            <input type="text" name="variants[1][sku]" placeholder="SKU" class="form-control">
                        </div>
                        <div>
                            <button type="button" class="btn-icon btn-danger remove-variant-btn" title="Remove">&times;</button>
                        </div>
                    </div>

                    <div class="variant-row">
                        <div>
                            <input type="text" name="variants[2][color]" value="Blush" placeholder="Color" class="form-control" required>
                        </div>
                        <div>
                            <input type="color" name="variants[2][color_code]" value="#E7B1AE" class="form-control" style="padding: 2px; height: 38px;">
                        </div>
                        <div>
                            <select name="variants[2][size]" class="form-control" required>
                                <option value="XS">XS</option>
                                <option value="S">S</option>
                                <option value="M">M</option>
                                <option value="L" selected>L</option>
                                <option value="XL">XL</option>
                            </select>
                        </div>
                        <div>
                            <input type="number" name="variants[2][stock]" min="0" value="0" placeholder="Stock" class="form-control" required>
                        </div>
                        <div>
                            <input type="text" name="variants[2][sku]" placeholder="SKU" class="form-control">
                        </div>
                        <div>
                            <button type="button" class="btn-icon btn-danger remove-variant-btn" title="Remove">&times;</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Images, Flags, Publish -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Publish Status & Flags -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Visibility &amp; Badges</h2>

                <div style="display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1.5rem;">
                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="status" value="1" checked>
                        <span>Active / Live on Storefront</span>
                    </label>

                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="is_featured" value="1">
                        <span>Featured on Homepage</span>
                    </label>

                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="is_new" value="1" checked>
                        <span>Mark as New Arrival</span>
                    </label>

                    <label class="filter-checkbox-label">
                        <input type="checkbox" name="is_sale" value="1">
                        <span>Mark as Promotional Sale</span>
                    </label>
                </div>

                <button class="btn btn-primary btn-full btn-lg" type="submit">
                    Publish Product
                </button>
            </div>

            <!-- Featured Image Upload -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1rem;">Primary Featured Image</h2>
                <div class="form-group">
                    <input class="form-control image-preview-input" type="file" name="featured_image" accept="image/jpeg,image/png,image/webp" data-preview="featuredPreview">
                    <small class="muted">Max size: 5MB (JPG, PNG, WebP)</small>
                </div>
                <div style="margin-top: 0.5rem; text-align: center;">
                    <img id="featuredPreview" src="" alt="Preview" style="max-height: 180px; margin: 0 auto; display: none; border-radius: 8px; border: 1px solid var(--admin-border);">
                </div>
            </div>

            <!-- Secondary Hover Image Upload -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1rem;">Secondary Hover Image</h2>
                <div class="form-group">
                    <input class="form-control image-preview-input" type="file" name="secondary_image" accept="image/jpeg,image/png,image/webp" data-preview="secondaryPreview">
                    <small class="muted">Fades in on hover in product grids</small>
                </div>
                <div style="margin-top: 0.5rem; text-align: center;">
                    <img id="secondaryPreview" src="" alt="Preview" style="max-height: 180px; margin: 0 auto; display: none; border-radius: 8px; border: 1px solid var(--admin-border);">
                </div>
            </div>

            <!-- Additional Gallery Images Upload -->
            <div class="admin-card">
                <h2 class="admin-card-title" style="margin-bottom: 1rem;">Gallery Images</h2>
                <div class="form-group" style="margin-bottom: 0;">
                    <input class="form-control" type="file" name="gallery_images[]" multiple accept="image/jpeg,image/png,image/webp">
                    <small class="muted">Select multiple images for the product detail gallery</small>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../_footer.php'; ?>
