-- ============================================================
-- HANÉ Women's Fashion E-Commerce Database
-- Database: hane_store
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS `hane_store` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hane_store`;

-- Drop existing tables with foreign keys cleanly
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `coupon_usage`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `cart`;
DROP TABLE IF EXISTS `wishlist`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `newsletter_subscribers`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `coupons`;
DROP TABLE IF EXISTS `settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- 1. Admins Table
-- ------------------------------------------------------------
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(180) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Users (Customers) Table
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(180) NOT NULL UNIQUE,
  `phone` VARCHAR(50) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `reset_token` VARCHAR(100) DEFAULT NULL,
  `reset_token_expiry` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Addresses Table
-- ------------------------------------------------------------
CREATE TABLE `addresses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) NOT NULL,
  `district` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(30) DEFAULT NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Categories Table
-- ------------------------------------------------------------
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Products Table
-- ------------------------------------------------------------
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NULL,
  `name` VARCHAR(180) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `description` TEXT,
  `regular_price` DECIMAL(10,2) NOT NULL,
  `sale_price` DECIMAL(10,2) DEFAULT NULL,
  `featured_image` VARCHAR(255) DEFAULT NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `is_new` TINYINT(1) DEFAULT 0,
  `is_sale` TINYINT(1) DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  INDEX `idx_cat` (`category_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Product Images Table
-- ------------------------------------------------------------
CREATE TABLE `product_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_secondary` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  INDEX `idx_prod_img` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. Product Variants Table (Stock per Color + Size!)
-- ------------------------------------------------------------
CREATE TABLE `product_variants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `color` VARCHAR(50) NOT NULL,
  `color_code` VARCHAR(20) DEFAULT '#000000',
  `size` VARCHAR(20) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `sku` VARCHAR(60) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_prod_color_size` (`product_id`, `color`, `size`),
  INDEX `idx_variant_prod` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. Wishlist Table
-- ------------------------------------------------------------
CREATE TABLE `wishlist` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_user_product` (`user_id`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. Cart Table
-- ------------------------------------------------------------
CREATE TABLE `cart` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `session_id` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cart_session` (`session_id`),
  INDEX `idx_cart_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. Cart Items Table
-- ------------------------------------------------------------
CREATE TABLE `cart_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `cart_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `variant_id` INT NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`cart_id`) REFERENCES `cart`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_cart_variant` (`cart_id`, `variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. Coupons Table
-- ------------------------------------------------------------
CREATE TABLE `coupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  `discount_value` DECIMAL(10,2) NOT NULL,
  `min_order` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `start_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `usage_limit` INT DEFAULT NULL,
  `times_used` INT DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. Orders Table
-- ------------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(180) NOT NULL,
  `phone` VARCHAR(50) NOT NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) NOT NULL,
  `district` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(30) DEFAULT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `delivery_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL,
  `coupon_code` VARCHAR(50) DEFAULT NULL,
  `payment_method` VARCHAR(60) NOT NULL DEFAULT 'Cash on Delivery',
  `payment_status` VARCHAR(30) NOT NULL DEFAULT 'Unpaid',
  `status` ENUM('Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_order_user` (`user_id`),
  INDEX `idx_order_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 13. Order Items Table
-- ------------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NULL,
  `variant_id` INT NULL,
  `product_name` VARCHAR(180) NOT NULL,
  `color` VARCHAR(50) NOT NULL,
  `size` VARCHAR(20) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `quantity` INT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14. Coupon Usage Table
-- ------------------------------------------------------------
CREATE TABLE `coupon_usage` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `coupon_id` INT NOT NULL,
  `order_id` INT NOT NULL,
  `user_id` INT NULL,
  `discount_amount` DECIMAL(10,2) NOT NULL,
  `used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`coupon_id`) REFERENCES `coupons`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 15. Reviews Table
-- ------------------------------------------------------------
CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `user_id` INT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `rating` TINYINT NOT NULL DEFAULT 5,
  `comment` TEXT NOT NULL,
  `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Approved',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_rev_prod` (`product_id`),
  INDEX `idx_rev_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 16. Newsletter Subscribers Table
-- ------------------------------------------------------------
CREATE TABLE `newsletter_subscribers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(180) NOT NULL UNIQUE,
  `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 17. Settings Table
-- ------------------------------------------------------------
CREATE TABLE `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(60) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- INITIAL SEED DATA
-- ============================================================

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'HANÉ'),
('store_tagline', 'Wear Your Elegance.'),
('store_email', 'hello@hane.lk'),
('store_phone', '+94 11 234 5678'),
('store_address', '42 Elegance Avenue, Colombo 07, Sri Lanka'),
('free_shipping_min', '5000'),
('delivery_charge', '350'),
('currency', 'Rs.'),
('low_stock_threshold', '5');

-- Admin Account (password: admin123)
INSERT INTO `admins` (`name`, `email`, `password`, `role`) VALUES
('HANÉ Admin', 'admin@hane.lk', '$2y$10$Euqd91Kfcy38hNh30HQ8gu0qqAzq8rMTqDKGxzEvkNsjkcFMRlA66', 'admin');

-- Demo Customer Account (password: customer123)
INSERT INTO `users` (`name`, `email`, `phone`, `password`) VALUES
('Aanya Senanayake', 'customer@hane.lk', '+94 77 123 4567', '$2y$10$Ihn47ELq9ouQYeEbi1wC.eMFjmf21Vz68dPi/zTw4tuTUhf0eGiEu');

-- Demo Address for Customer
INSERT INTO `addresses` (`user_id`, `full_name`, `phone`, `address_line1`, `address_line2`, `city`, `district`, `postal_code`, `is_default`) VALUES
(1, 'Aanya Senanayake', '+94 77 123 4567', 'No. 18 Lotus Grove', 'Apt 4B', 'Colombo', 'Colombo', '00700', 1);

-- Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `status`) VALUES
(1, 'Dresses', 'dresses', 'Flowing silhouettes, elegant midi & maxi styles crafted for every occasion.', 'uploads/categories/cat_dresses.jpg', 1),
(2, 'Tops', 'tops', 'Breathable linens, chic blouses, and timeless everyday essentials.', 'uploads/categories/cat_tops.jpg', 1),
(3, 'Bottoms', 'bottoms', 'Tailored trousers, wide leg fits, and relaxed linen bottoms.', 'uploads/categories/cat_bottoms.jpg', 1),
(4, 'Co-ords', 'co-ords', 'Effortlessly coordinated two-piece sets designed for modern sophistication.', 'uploads/categories/cat_coords.jpg', 1),
(5, 'New Arrivals', 'new-arrivals', 'Explore the latest runway-inspired seasonal drops.', 'uploads/categories/cat_new_arrivals.jpg', 1),
(6, 'Sale', 'sale', 'Handpicked styles at exclusive promotional prices.', 'uploads/categories/cat_sale.jpg', 1);

-- Products
INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `description`, `regular_price`, `sale_price`, `featured_image`, `is_featured`, `is_new`, `is_sale`, `status`) VALUES
(1, 1, 'Floral Midi Dress', 'floral-midi-dress', 'A breathtaking floral midi dress crafted from lightweight airy georgette. Features delicate puffed sleeves, a flattering smocked bodice, and an elegant side slit. Ideal for garden soirees, brunch dates, and evening celebrations.', 5490.00, 3990.00, 'uploads/products/dress_1_main.jpg', 1, 1, 1, 1),
(2, 2, 'Linen Crop Top', 'linen-crop-top', 'Pure breathable organic linen cropped blouse featuring a sweetheart neckline and tortoiseshell front buttons. Perfectly pairs with high-waisted linen trousers or flowing midi skirts for relaxed chic styling.', 2890.00, 2490.00, 'uploads/products/top_1_main.jpg', 1, 1, 1, 1),
(3, 3, 'Sage Wide Leg Pants', 'sage-wide-leg-pants', 'Relaxed silhouette tailored trousers in an earthy sage tone. Crafted from premium breathable cotton-linen blend with deep side pockets, a structured waistband, and front pleats for effortless drape.', 3490.00, 2990.00, 'uploads/products/bottom_1_main.jpg', 1, 0, 1, 1),
(4, 4, 'Premium Linen Co-ord Set', 'premium-linen-coord-set', 'Two-piece matching ensemble featuring a boxy short-sleeve button-down shirt and relaxed drawstring wide pants. Cut from premium pre-washed linen for superior soft texture and all-day comfort.', 6490.00, 5490.00, 'uploads/products/coord_1_main.jpg', 1, 1, 1, 1),
(5, 1, 'Tiered Chiffon Maxi Dress', 'tiered-chiffon-maxi-dress', 'A dreamy tiered maxi silhouette with an empire waist, flutter sleeves, and fluid movement. Fully lined with sheer romantic overlay designed to turn heads at sunset events.', 4990.00, 4290.00, 'uploads/products/dress_2_main.jpg', 1, 1, 1, 1),
(6, 2, 'Silk Wrap Blouse', 'silk-wrap-blouse', 'Lustrous satin wrap blouse with adjustable waist sash tie and dramatic cuff sleeves. Exudes sophisticated elegance for boardroom meetings and upscale dinners alike.', 3790.00, NULL, 'uploads/products/blouse_1_main.jpg', 0, 1, 0, 1),
(7, 3, 'High-Waisted Tailored Trousers', 'high-waisted-tailored-trousers', 'Sharply pressed high-rise trousers with sleek minimal side zip closure and ankle-grazing hem. A capsule wardrobe essential that bridges office tailoring and evening glam.', 3890.00, 3190.00, 'uploads/products/trousers_1_main.jpg', 0, 0, 1, 1),
(8, 1, 'Flutter Sleeve Summer Dress', 'flutter-sleeve-summer-dress', 'Softly tailored A-line cotton sundress featuring romantic flutter sleeves, gentle gathers at the bust, and discreet in-seam pockets. Effortless, breathable elegance.', 4590.00, NULL, 'uploads/products/dress_3_main.jpg', 1, 1, 0, 1);

-- Product Images (Secondary hover images + extra gallery)
INSERT INTO `product_images` (`product_id`, `image_path`, `is_secondary`, `sort_order`) VALUES
(1, 'uploads/products/dress_1_hover.jpg', 1, 1),
(2, 'uploads/products/top_1_hover.jpg', 1, 1),
(3, 'uploads/products/bottom_1_hover.jpg', 1, 1),
(4, 'uploads/products/coord_1_hover.jpg', 1, 1),
(5, 'uploads/products/dress_2_hover.jpg', 1, 1),
(6, 'uploads/products/blouse_1_hover.jpg', 1, 1),
(7, 'uploads/products/trousers_1_hover.jpg', 1, 1),
(8, 'uploads/products/dress_3_hover.jpg', 1, 1);

-- Product Variants (STOCKS PER SIZE AND COLOR COMBINATION!)
-- Exactly implementing the user requirement example:
-- Pink + S = 5, Pink + M = 8, Pink + L = 0
-- Green + S = 3, Green + M = 4, Green + L = 7
INSERT INTO `product_variants` (`product_id`, `color`, `color_code`, `size`, `stock`, `sku`) VALUES
-- Product 1: Floral Midi Dress
(1, 'Pink', '#E7B1AE', 'S', 5, 'HANE-DR1-PNK-S'),
(1, 'Pink', '#E7B1AE', 'M', 8, 'HANE-DR1-PNK-M'),
(1, 'Pink', '#E7B1AE', 'L', 0, 'HANE-DR1-PNK-L'), -- Out of stock example!
(1, 'Green', '#163F35', 'S', 3, 'HANE-DR1-GRN-S'),
(1, 'Green', '#163F35', 'M', 4, 'HANE-DR1-GRN-M'),
(1, 'Green', '#163F35', 'L', 7, 'HANE-DR1-GRN-L'),

-- Product 2: Linen Crop Top
(2, 'Warm Cream', '#FAF7F1', 'S', 10, 'HANE-TP1-CRM-S'),
(2, 'Warm Cream', '#FAF7F1', 'M', 12, 'HANE-TP1-CRM-M'),
(2, 'Warm Cream', '#FAF7F1', 'L', 4, 'HANE-TP1-CRM-L'),
(2, 'Soft Beige', '#E8D9CB', 'S', 6, 'HANE-TP1-BGE-S'),
(2, 'Soft Beige', '#E8D9CB', 'M', 8, 'HANE-TP1-BGE-M'),
(2, 'Soft Beige', '#E8D9CB', 'L', 5, 'HANE-TP1-BGE-L'),

-- Product 3: Sage Wide Leg Pants
(3, 'Sage Green', '#A9B29A', 'S', 8, 'HANE-BT1-SGE-S'),
(3, 'Sage Green', '#A9B29A', 'M', 6, 'HANE-BT1-SGE-M'),
(3, 'Sage Green', '#A9B29A', 'L', 2, 'HANE-BT1-SGE-L'),
(3, 'Charcoal', '#222222', 'S', 5, 'HANE-BT1-CHR-S'),
(3, 'Charcoal', '#222222', 'M', 7, 'HANE-BT1-CHR-M'),
(3, 'Charcoal', '#222222', 'L', 3, 'HANE-BT1-CHR-L'),

-- Product 4: Premium Linen Co-ord Set
(4, 'Warm Cream', '#FAF7F1', 'S', 4, 'HANE-CD1-CRM-S'),
(4, 'Warm Cream', '#FAF7F1', 'M', 6, 'HANE-CD1-CRM-M'),
(4, 'Warm Cream', '#FAF7F1', 'L', 3, 'HANE-CD1-CRM-L'),
(4, 'Soft Blush', '#E7B1AE', 'S', 3, 'HANE-CD1-BLS-S'),
(4, 'Soft Blush', '#E7B1AE', 'M', 5, 'HANE-CD1-BLS-M'),
(4, 'Soft Blush', '#E7B1AE', 'L', 2, 'HANE-CD1-BLS-L'),

-- Product 5: Tiered Chiffon Maxi Dress
(5, 'Soft Blush', '#E7B1AE', 'S', 6, 'HANE-DR2-BLS-S'),
(5, 'Soft Blush', '#E7B1AE', 'M', 7, 'HANE-DR2-BLS-M'),
(5, 'Soft Blush', '#E7B1AE', 'L', 4, 'HANE-DR2-BLS-L'),
(5, 'Dark Green', '#163F35', 'S', 5, 'HANE-DR2-DGN-S'),
(5, 'Dark Green', '#163F35', 'M', 4, 'HANE-DR2-DGN-M'),
(5, 'Dark Green', '#163F35', 'L', 3, 'HANE-DR2-DGN-L'),

-- Product 6: Silk Wrap Blouse
(6, 'Soft Beige', '#E8D9CB', 'S', 9, 'HANE-BL1-BGE-S'),
(6, 'Soft Beige', '#E8D9CB', 'M', 10, 'HANE-BL1-BGE-M'),
(6, 'Soft Beige', '#E8D9CB', 'L', 5, 'HANE-BL1-BGE-L'),
(6, 'White', '#FFFFFF', 'S', 12, 'HANE-BL1-WHT-S'),
(6, 'White', '#FFFFFF', 'M', 8, 'HANE-BL1-WHT-M'),
(6, 'White', '#FFFFFF', 'L', 6, 'HANE-BL1-WHT-L'),

-- Product 7: High-Waisted Tailored Trousers
(7, 'Charcoal', '#222222', 'S', 7, 'HANE-TR1-CHR-S'),
(7, 'Charcoal', '#222222', 'M', 8, 'HANE-TR1-CHR-M'),
(7, 'Charcoal', '#222222', 'L', 4, 'HANE-TR1-CHR-L'),
(7, 'Warm Cream', '#FAF7F1', 'S', 5, 'HANE-TR1-CRM-S'),
(7, 'Warm Cream', '#FAF7F1', 'M', 6, 'HANE-TR1-CRM-M'),
(7, 'Warm Cream', '#FAF7F1', 'L', 2, 'HANE-TR1-CRM-L'),

-- Product 8: Flutter Sleeve Summer Dress
(8, 'Dark Green', '#163F35', 'S', 4, 'HANE-DR3-DGN-S'),
(8, 'Dark Green', '#163F35', 'M', 5, 'HANE-DR3-DGN-M'),
(8, 'Dark Green', '#163F35', 'L', 3, 'HANE-DR3-DGN-L'),
(8, 'Soft Blush', '#E7B1AE', 'S', 6, 'HANE-DR3-BLS-S'),
(8, 'Soft Blush', '#E7B1AE', 'M', 6, 'HANE-DR3-BLS-M'),
(8, 'Soft Blush', '#E7B1AE', 'L', 1, 'HANE-DR3-BLS-L');

-- Coupons
INSERT INTO `coupons` (`code`, `discount_type`, `discount_value`, `min_order`, `start_date`, `expiry_date`, `usage_limit`, `times_used`, `status`) VALUES
('ELEGANCE10', 'percentage', 10.00, 3000.00, '2026-01-01', '2026-12-31', 500, 2, 1),
('HANE500', 'fixed', 500.00, 5000.00, '2026-01-01', '2026-12-31', 200, 1, 1),
('WELCOME15', 'percentage', 15.00, 2000.00, '2026-01-01', '2026-12-31', 1000, 5, 1),
('EXPIRED20', 'percentage', 20.00, 2000.00, '2025-01-01', '2025-12-31', 100, 0, 1);

-- Reviews
INSERT INTO `reviews` (`product_id`, `user_id`, `customer_name`, `rating`, `comment`, `status`) VALUES
(1, 1, 'Aanya S.', 5, 'The fabric feels so luxurious and the fit is absolutely divine! Received so many compliments at a friend’s wedding reception.', 'Approved'),
(1, NULL, 'Dilini P.', 5, 'Impressed by the quality and attention to detail. Fast delivery and lovely packaging!', 'Approved'),
(2, 1, 'Aanya S.', 4, 'Very breathable linen, perfect for warm tropical afternoons. Loved the cream tone.', 'Approved'),
(4, NULL, 'Kavindi M.', 5, 'Best co-ord set I have ever purchased. Effortless elegance, wears like a dream.', 'Approved');

-- Sample Initial Orders
INSERT INTO `orders` (`order_number`, `user_id`, `customer_name`, `email`, `phone`, `address_line1`, `address_line2`, `city`, `district`, `postal_code`, `subtotal`, `discount`, `delivery_charge`, `total`, `coupon_code`, `payment_method`, `payment_status`, `status`) VALUES
('HANE-2026-000101', 1, 'Aanya Senanayake', 'customer@hane.lk', '+94 77 123 4567', 'No. 18 Lotus Grove', 'Apt 4B', 'Colombo', 'Colombo', '00700', 6480.00, 648.00, 0.00, 5832.00, 'ELEGANCE10', 'Cash on Delivery', 'Unpaid', 'Delivered'),
('HANE-2026-000102', 1, 'Aanya Senanayake', 'customer@hane.lk', '+94 77 123 4567', 'No. 18 Lotus Grove', 'Apt 4B', 'Colombo', 'Colombo', '00700', 3990.00, 0.00, 350.00, 4340.00, NULL, 'Cash on Delivery', 'Unpaid', 'Confirmed');

-- Sample Order Items
INSERT INTO `order_items` (`order_id`, `product_id`, `variant_id`, `product_name`, `color`, `size`, `price`, `quantity`, `subtotal`) VALUES
(1, 1, 1, 'Floral Midi Dress', 'Pink', 'S', 3990.00, 1, 3990.00),
(1, 2, 7, 'Linen Crop Top', 'Warm Cream', 'M', 2490.00, 1, 2490.00),
(2, 1, 4, 'Floral Midi Dress', 'Green', 'S', 3990.00, 1, 3990.00);

-- Wishlist item for test user
INSERT INTO `wishlist` (`user_id`, `product_id`) VALUES
(1, 3),
(1, 4);

-- Newsletter Subscriber
INSERT INTO `newsletter_subscribers` (`email`) VALUES
('customer@hane.lk'),
('style@elegance.com');
