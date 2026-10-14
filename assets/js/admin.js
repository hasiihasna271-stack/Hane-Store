/**
 * HANÉ Admin Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Sidebar Toggle on Mobile
    const hamburger = document.getElementById('adminHamburger');
    const sidebar = document.getElementById('adminSidebar');

    if (hamburger && sidebar) {
        hamburger.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });

        // Close sidebar if click outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });
    }

    // 2. Dynamic Product Variant Row Builder
    const variantContainer = document.getElementById('variantContainer');
    const addVariantBtn = document.getElementById('addVariantBtn');

    if (variantContainer && addVariantBtn) {
        addVariantBtn.addEventListener('click', () => {
            const index = Date.now();
            const row = document.createElement('div');
            row.className = 'variant-row';
            row.innerHTML = `
                <div>
                    <input type="text" name="variants[${index}][color]" placeholder="Color (e.g. Blush)" class="form-control" required>
                </div>
                <div>
                    <input type="color" name="variants[${index}][color_code]" value="#E7B1AE" class="form-control" style="padding: 2px; height: 38px;">
                </div>
                <div>
                    <select name="variants[${index}][size]" class="form-control" required>
                        <option value="XS">XS</option>
                        <option value="S" selected>S</option>
                        <option value="M">M</option>
                        <option value="L">L</option>
                        <option value="XL">XL</option>
                    </select>
                </div>
                <div>
                    <input type="number" name="variants[${index}][stock]" min="0" value="5" placeholder="Stock" class="form-control" required>
                </div>
                <div>
                    <input type="text" name="variants[${index}][sku]" placeholder="SKU" class="form-control">
                </div>
                <div>
                    <button type="button" class="btn-icon btn-danger remove-variant-btn" title="Remove">&times;</button>
                </div>
            `;
            variantContainer.appendChild(row);

            row.querySelector('.remove-variant-btn').addEventListener('click', () => {
                row.remove();
            });
        });

        // Delegate existing remove buttons
        variantContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-variant-btn')) {
                const row = e.target.closest('.variant-row');
                if (row) row.remove();
            }
        });
    }

    // 3. Image Upload Instant Preview
    document.querySelectorAll('.image-preview-input').forEach(input => {
        input.addEventListener('change', () => {
            const previewId = input.getAttribute('data-preview');
            const previewImg = document.getElementById(previewId);
            if (previewImg && input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        });
    });

    // 4. Auto-submit status update selects with confirmation
    document.querySelectorAll('.auto-submit-status').forEach(select => {
        select.addEventListener('change', () => {
            const newStatus = select.value;
            if (confirm(`Are you sure you want to change order status to "${newStatus}"?`)) {
                select.form.submit();
            } else {
                select.value = select.getAttribute('data-original');
            }
        });
    });
});
