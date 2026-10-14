<?php
/**
 * HANÉ Customer Storefront Footer
 */
?>
</main>

<div class="container">
    <!-- Newsletter Section (Join the HANÉ Circle) -->
    <section class="newsletter-section" aria-label="Newsletter Subscription">
        <div class="newsletter-grid">
            <div class="newsletter-text">
                <h3>Join the HANÉ Circle</h3>
                <p>Be the first to preview new season collections, enjoy private sale invitations, and receive curated style inspiration.</p>
            </div>
            <div>
                <form class="newsletter-form" id="newsletterForm" method="post" action="<?= BASE_URL ?>ajax.php?action=newsletter_subscribe">
                    <input class="newsletter-input" type="email" name="email" placeholder="Enter your email address..." required aria-label="Email address for newsletter">
                    <button class="btn btn-primary" type="submit">
                        Subscribe
                    </button>
                </form>
            </div>
        </div>
    </section>
</div>

<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="logo" href="<?= BASE_URL ?>index.php">HANÉ</a>
                <p><?= e(get_setting('store_tagline', 'Wear Your Elegance.')) ?> Thoughtfully designed women's silhouettes made for modern everyday poise.</p>
                <div style="margin-top: 1.2rem; font-size: 0.85rem; color: rgba(250, 247, 241, 0.7);">
                    <div><?= e(get_setting('store_address', '42 Elegance Avenue, Colombo 07')) ?></div>
                    <div style="margin-top: 0.3rem;"><?= e(get_setting('store_phone', '+94 11 234 5678')) ?> &bull; <?= e(get_setting('store_email', 'hello@hane.lk')) ?></div>
                </div>
            </div>

            <div class="footer-col">
                <h4>Collections</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>shop.php?cat=dresses">Dresses &amp; Gowns</a></li>
                    <li><a href="<?= BASE_URL ?>shop.php?cat=tops">Linen &amp; Silk Tops</a></li>
                    <li><a href="<?= BASE_URL ?>shop.php?cat=bottoms">Tailored Bottoms</a></li>
                    <li><a href="<?= BASE_URL ?>shop.php?cat=co-ords">Co-ord Sets</a></li>
                    <li><a href="<?= BASE_URL ?>shop.php?new=1">New Arrivals</a></li>
                    <li><a href="<?= BASE_URL ?>shop.php?sale=1">Season Sale</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Customer Care</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>cart.php">Shopping Bag</a></li>
                    <li><a href="<?= BASE_URL ?>wishlist.php">Saved Wishlist</a></li>
                    <li><a href="<?= BASE_URL ?>account/orders.php">Track Order</a></li>
                    <li><a href="<?= BASE_URL ?>account/addresses.php">Delivery Addresses</a></li>
                    <li><a href="<?= BASE_URL ?>shop.php">Size Chart &amp; Fit</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Our Promise</h4>
                <ul>
                    <li style="color: rgba(250,247,241,0.85); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                        <?= hane_icon('truck') ?> Island-wide Delivery
                    </li>
                    <li style="color: rgba(250,247,241,0.85); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                        <?= hane_icon('shield') ?> 100% Secure Checkout
                    </li>
                    <li style="color: rgba(250,247,241,0.85); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                        <?= hane_icon('return') ?> 7-Day Hassle-free Returns
                    </li>
                    <li style="color: rgba(250,247,241,0.85); font-size: 0.88rem; display: flex; align-items: center; gap: 0.5rem;">
                        <?= hane_icon('headset') ?> Dedicated Support
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div>
                &copy; <?= date('Y') ?> <strong><?= e(get_setting('store_name', 'HANÉ')) ?></strong>. All rights reserved. &bull; Wear Your Elegance.
            </div>
            <div>
                Cash on Delivery &bull; Bank Transfer &bull; Premium Quality
            </div>
        </div>
    </div>
</footer>

<!-- Main Client Script -->
<script src="<?= ASSET_PATH ?>js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
