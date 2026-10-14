/**
 * HANÉ Women's Fashion E-Commerce - Main Client Script
 */

document.addEventListener('DOMContentLoaded', () => {
    // ----------------------------------------------------
    // 1. Mobile Menu Drawer
    // ----------------------------------------------------
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileDrawer = document.getElementById('mobileDrawer');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const closeDrawerBtn = document.getElementById('closeDrawerBtn');

    function toggleMobileMenu(open) {
        if (mobileDrawer && mobileOverlay) {
            mobileDrawer.classList.toggle('open', open);
            mobileOverlay.classList.toggle('open', open);
            document.body.style.overflow = open ? 'hidden' : '';
        }
    }

    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', () => toggleMobileMenu(true));
    }
    if (closeDrawerBtn) {
        closeDrawerBtn.addEventListener('click', () => toggleMobileMenu(false));
    }
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', () => toggleMobileMenu(false));
    }

    // ----------------------------------------------------
    // 2. Header Search Toggle
    // ----------------------------------------------------
    const searchToggleBtn = document.getElementById('searchToggleBtn');
    const searchBarDrawer = document.getElementById('searchBarDrawer');
    if (searchToggleBtn && searchBarDrawer) {
        searchToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            searchBarDrawer.classList.toggle('open');
            if (searchBarDrawer.classList.contains('open')) {
                const input = searchBarDrawer.querySelector('input');
                if (input) input.focus();
            }
        });
    }

    // ----------------------------------------------------
    // 3. Dynamic Toast Notification System
    // ----------------------------------------------------
    window.showToast = function(type, message) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-msg">${message}</div>
            <button class="toast-close" type="button" aria-label="Close">&times;</button>
        `;

        container.appendChild(toast);

        toast.querySelector('.toast-close').addEventListener('click', () => {
            toast.remove();
        });

        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 4000);
    };

    // Auto-dismiss initial flash toasts rendered from PHP
    document.querySelectorAll('.toast').forEach(toast => {
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => toast.remove());
        }
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 4500);
    });

    // ----------------------------------------------------
    // 4. Product Gallery Thumbnails
    // ----------------------------------------------------
    const mainGalleryImg = document.getElementById('mainGalleryImg');
    const galleryThumbs = document.querySelectorAll('.thumb-item');
    if (mainGalleryImg && galleryThumbs.length) {
        galleryThumbs.forEach(thumb => {
            thumb.addEventListener('click', () => {
                galleryThumbs.forEach(t => t.classList.remove('active'));
                thumb.classList.add('active');
                const newSrc = thumb.getAttribute('data-full-img');
                if (newSrc) {
                    mainGalleryImg.src = newSrc;
                }
            });
        });
    }

    // ----------------------------------------------------
    // 5. Product Details Variant Selection & Real Stock Logic
    // ----------------------------------------------------
    const productVariantsDataEl = document.getElementById('productVariantsData');
    if (productVariantsDataEl) {
        let variants = [];
        try {
            variants = JSON.parse(productVariantsDataEl.textContent);
        } catch (e) {
            console.error('Failed to parse variant JSON', e);
        }

        const colorBtns = document.querySelectorAll('.color-swatch-btn');
        const sizeBtns = document.querySelectorAll('.size-select-btn');
        const stockNotice = document.getElementById('stockStatusNotice');
        const variantIdInput = document.getElementById('selectedVariantId');
        const addToCartBtn = document.getElementById('addToCartBtn');
        const buyNowBtn = document.getElementById('buyNowBtn');
        const qtyInput = document.getElementById('productQtyInput');
        const selectedColorNameEl = document.getElementById('selectedColorName');
        const selectedSizeNameEl = document.getElementById('selectedSizeName');

        let selectedColor = null;
        let selectedSize = null;

        // Auto select first available combination if available
        if (variants.length > 0) {
            const firstInStock = variants.find(v => parseInt(v.stock, 10) > 0) || variants[0];
            selectedColor = firstInStock.color;
            selectedSize = firstInStock.size;
        }

        function updateVariantUI() {
            // Highlight color button
            colorBtns.forEach(btn => {
                const c = btn.getAttribute('data-color');
                btn.classList.toggle('selected', c === selectedColor);
            });
            if (selectedColorNameEl) selectedColorNameEl.textContent = selectedColor || 'None';

            // Filter sizes for selected color
            sizeBtns.forEach(btn => {
                const s = btn.getAttribute('data-size');
                btn.classList.toggle('selected', s === selectedSize);

                // Find variant for this color and size
                const match = variants.find(v => v.color === selectedColor && v.size === s);
                const stock = match ? parseInt(match.stock, 10) : 0;

                if (stock <= 0) {
                    btn.classList.add('out-of-stock');
                    btn.title = 'Out of Stock';
                } else {
                    btn.classList.remove('out-of-stock');
                    btn.title = `${stock} in stock`;
                }
            });
            if (selectedSizeNameEl) selectedSizeNameEl.textContent = selectedSize || 'None';

            // Find current selected variant
            const currentVariant = variants.find(v => v.color === selectedColor && v.size === selectedSize);

            if (currentVariant) {
                const stock = parseInt(currentVariant.stock, 10);
                variantIdInput.value = currentVariant.id;

                if (stock <= 0) {
                    stockNotice.className = 'stock-status-notice out-of-stock';
                    stockNotice.innerHTML = '<strong>Out of Stock</strong> &mdash; This size is currently sold out.';
                    if (addToCartBtn) {
                        addToCartBtn.disabled = true;
                        addToCartBtn.textContent = 'Out of Stock';
                    }
                    if (buyNowBtn) buyNowBtn.disabled = true;
                    if (qtyInput) qtyInput.max = 0;
                } else {
                    if (stock <= 3) {
                        stockNotice.className = 'stock-status-notice low-stock';
                        stockNotice.innerHTML = `<strong>Only ${stock} left</strong> &mdash; Order soon.`;
                    } else {
                        stockNotice.className = 'stock-status-notice in-stock';
                        stockNotice.innerHTML = `<strong>In Stock</strong> &mdash; (${stock} items available)`;
                    }

                    if (addToCartBtn) {
                        addToCartBtn.disabled = false;
                        addToCartBtn.textContent = 'Add to Cart';
                    }
                    if (buyNowBtn) buyNowBtn.disabled = false;
                    if (qtyInput) {
                        qtyInput.max = stock;
                        if (parseInt(qtyInput.value, 10) > stock) {
                            qtyInput.value = stock;
                        }
                    }
                }
            } else {
                variantIdInput.value = '';
                stockNotice.className = 'stock-status-notice out-of-stock';
                stockNotice.textContent = 'Please select an available color and size.';
                if (addToCartBtn) addToCartBtn.disabled = true;
                if (buyNowBtn) buyNowBtn.disabled = true;
            }
        }

        // Color clicks
        colorBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                selectedColor = btn.getAttribute('data-color');
                // Check if current size is valid for new color, if not, pick first available size
                const availableForColor = variants.filter(v => v.color === selectedColor && parseInt(v.stock, 10) > 0);
                if (availableForColor.length > 0 && !availableForColor.some(v => v.size === selectedSize)) {
                    selectedSize = availableForColor[0].size;
                }
                updateVariantUI();
            });
        });

        // Size clicks
        sizeBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                selectedSize = btn.getAttribute('data-size');
                updateVariantUI();
            });
        });

        // Initialize UI
        updateVariantUI();
    }

    // ----------------------------------------------------
    // 6. Quantity Stepper Controls
    // ----------------------------------------------------
    document.querySelectorAll('.qty-stepper').forEach(stepper => {
        const minusBtn = stepper.querySelector('.qty-minus');
        const plusBtn = stepper.querySelector('.qty-plus');
        const input = stepper.querySelector('.qty-input');

        if (minusBtn && plusBtn && input) {
            minusBtn.addEventListener('click', () => {
                let val = parseInt(input.value, 10) || 1;
                if (val > 1) {
                    input.value = val - 1;
                    input.dispatchEvent(new Event('change'));
                }
            });
            plusBtn.addEventListener('click', () => {
                let val = parseInt(input.value, 10) || 1;
                let max = parseInt(input.max, 10) || 999;
                if (val < max) {
                    input.value = val + 1;
                    input.dispatchEvent(new Event('change'));
                } else {
                    window.showToast('info', `Maximum available stock is ${max}.`);
                }
            });
        }
    });

    // ----------------------------------------------------
    // 7. Size Guide Modal
    // ----------------------------------------------------
    const sizeGuideBtn = document.getElementById('openSizeGuideBtn');
    const sizeGuideModal = document.getElementById('sizeGuideModal');
    const closeSizeGuideBtn = document.getElementById('closeSizeGuideBtn');

    if (sizeGuideBtn && sizeGuideModal) {
        sizeGuideBtn.addEventListener('click', (e) => {
            e.preventDefault();
            sizeGuideModal.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
    }
    if (closeSizeGuideBtn && sizeGuideModal) {
        closeSizeGuideBtn.addEventListener('click', () => {
            sizeGuideModal.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
    if (sizeGuideModal) {
        sizeGuideModal.addEventListener('click', (e) => {
            if (e.target === sizeGuideModal) {
                sizeGuideModal.classList.remove('open');
                document.body.style.overflow = '';
            }
        });
    }

    // ----------------------------------------------------
    // 8. Wishlist Toggle Quick Action (AJAX)
    // ----------------------------------------------------
    document.querySelectorAll('.wishlist-toggle-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            const productId = btn.getAttribute('data-product-id');
            if (!productId) return;

            try {
                const res = await fetch('ajax.php?action=toggle_wishlist', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `product_id=${encodeURIComponent(productId)}`
                });
                const data = await res.json();
                if (data.success) {
                    btn.classList.toggle('active', data.in_wishlist);
                    const countBadge = document.getElementById('wishlistCountBadge');
                    if (countBadge) {
                        countBadge.textContent = data.count;
                    }
                    window.showToast('success', data.message);
                } else {
                    window.showToast('error', data.message || 'Error updating wishlist.');
                }
            } catch (err) {
                console.error(err);
                window.showToast('error', 'Network error. Please try again.');
            }
        });
    });

    // ----------------------------------------------------
    // 9. Newsletter Subscription (AJAX)
    // ----------------------------------------------------
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const emailInput = newsletterForm.querySelector('input[type="email"]');
            if (!emailInput || !emailInput.value) return;

            try {
                const res = await fetch('ajax.php?action=newsletter_subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `email=${encodeURIComponent(emailInput.value)}`
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast('success', data.message);
                    emailInput.value = '';
                } else {
                    window.showToast('error', data.message || 'Failed to subscribe.');
                }
            } catch (err) {
                console.error(err);
                window.showToast('error', 'Network error. Please try again.');
            }
        });
    }

    // ----------------------------------------------------
    // 10. Accordion Toggles
    // ----------------------------------------------------
    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', () => {
            const body = header.nextElementSibling;
            const icon = header.querySelector('svg');
            if (body) {
                const isOpen = body.style.display === 'block';
                body.style.display = isOpen ? 'none' : 'block';
                if (icon) {
                    icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                }
            }
        });
    });
});
