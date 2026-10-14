<?php
/**
 * Shared Customer Account Navigation Sidebar
 */
$currPage = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside style="background: white; border: 1px solid var(--color-line); border-radius: var(--radius-lg); padding: 1.5rem; height: fit-content; box-shadow: var(--shadow-sm);">
    <div style="display: flex; align-items: center; gap: 0.9rem; padding-bottom: 1.2rem; border-bottom: 1px solid var(--color-line); margin-bottom: 1.2rem;">
        <div style="width: 44px; height: 44px; border-radius: 50%; background: var(--color-blush); color: var(--color-green); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem;">
            <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
        </div>
        <div style="overflow: hidden;">
            <strong style="display: block; font-size: 1rem; color: var(--color-green); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= e($currentUser['name'] ?? 'Valued Customer') ?>
            </strong>
            <span class="muted" style="font-size: 0.8rem;"><?= e($currentUser['email'] ?? '') ?></span>
        </div>
    </div>

    <nav style="display: flex; flex-direction: column; gap: 0.4rem;">
        <a href="<?= BASE_URL ?>account/profile.php" 
           style="display: flex; align-items: center; gap: 0.8rem; padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.92rem; font-weight: 500; <?= ($currPage === 'profile.php') ? 'background: var(--color-green); color: white;' : 'color: var(--color-charcoal);' ?>">
            <?= hane_icon('user') ?> Profile &amp; Security
        </a>

        <a href="<?= BASE_URL ?>account/orders.php" 
           style="display: flex; align-items: center; gap: 0.8rem; padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.92rem; font-weight: 500; <?= ($currPage === 'orders.php' || $currPage === 'order-details.php') ? 'background: var(--color-green); color: white;' : 'color: var(--color-charcoal);' ?>">
            <?= hane_icon('box') ?> My Orders
        </a>

        <a href="<?= BASE_URL ?>account/addresses.php" 
           style="display: flex; align-items: center; gap: 0.8rem; padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.92rem; font-weight: 500; <?= ($currPage === 'addresses.php') ? 'background: var(--color-green); color: white;' : 'color: var(--color-charcoal);' ?>">
            <?= hane_icon('truck') ?> Saved Addresses
        </a>

        <a href="<?= BASE_URL ?>wishlist.php" 
           style="display: flex; align-items: center; gap: 0.8rem; padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.92rem; font-weight: 500; color: var(--color-charcoal);">
            <?= hane_icon('heart') ?> Wishlist
        </a>

        <hr style="border: 0; border-top: 1px solid var(--color-line); margin: 0.8rem 0;">

        <a href="<?= BASE_URL ?>logout.php" 
           style="display: flex; align-items: center; gap: 0.8rem; padding: 0.75rem 1rem; border-radius: var(--radius-sm); font-size: 0.92rem; font-weight: 500; color: var(--color-danger);">
            <?= hane_icon('logout') ?> Sign Out
        </a>
    </nav>
</aside>
