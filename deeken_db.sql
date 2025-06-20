-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 20, 2025 at 06:08 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `deeken_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `street_address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `full_name`, `street_address`, `city`, `state`, `country`, `postal_code`, `phone`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 15:14:37', '2025-06-12 15:14:37'),
(2, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 15:25:14', '2025-06-12 15:25:14'),
(3, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 15:31:26', '2025-06-12 15:31:26'),
(4, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 15:31:59', '2025-06-12 15:31:59'),
(5, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 15:33:01', '2025-06-12 15:33:01'),
(6, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 16:07:42', '2025-06-12 16:07:42'),
(7, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 17:13:03', '2025-06-12 17:13:03'),
(8, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 17:13:55', '2025-06-12 17:13:55'),
(9, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 17:54:57', '2025-06-12 17:54:57'),
(10, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 17:55:46', '2025-06-12 17:55:46'),
(11, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 18:01:34', '2025-06-12 18:01:34'),
(12, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 18:17:02', '2025-06-12 18:17:02'),
(13, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 18:32:45', '2025-06-12 18:32:45'),
(14, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 19:07:48', '2025-06-12 19:07:48'),
(15, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 19:34:08', '2025-06-12 19:34:08'),
(16, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-12 21:03:36', '2025-06-12 21:03:36'),
(17, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-13 10:59:56', '2025-06-13 10:59:56'),
(18, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-13 11:07:33', '2025-06-13 11:07:33'),
(19, 1, 'Yahaya Onusagba Sabdat', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-13 11:17:22', '2025-06-13 11:17:22'),
(20, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-13 12:19:05', '2025-06-13 12:19:05'),
(21, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-13 12:43:13', '2025-06-13 12:43:13'),
(22, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-13 13:16:47', '2025-06-13 13:16:47'),
(23, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-13 14:58:39', '2025-06-13 14:58:39'),
(24, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-19 19:09:43', '2025-06-19 19:09:43'),
(25, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-19 19:20:25', '2025-06-19 19:20:25');

-- --------------------------------------------------------

--
-- Table structure for table `carousel_images`
--

CREATE TABLE `carousel_images` (
  `id` int(11) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` text NOT NULL,
  `image` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'All Category', 'Default category for all products', '2025-06-11 22:16:24', '2025-06-11 22:16:24'),
(2, 'appliances', 'your day to day appliances', '2025-06-12 14:47:21', '2025-06-12 14:47:21'),
(3, 'home kits', 'regular home kits', '2025-06-15 18:11:25', '2025-06-15 18:11:25'),
(4, 'ddcc', 'dcdc', '2025-06-20 01:09:07', '2025-06-20 01:09:07');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_fees`
--

CREATE TABLE `delivery_fees` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `fee` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_fees`
--

INSERT INTO `delivery_fees` (`id`, `name`, `fee`, `min_order_amount`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Standard Delivery', 5.00, NULL, 'Default delivery fee for all orders', 1, '2025-06-13 11:22:01', '2025-06-13 11:22:01');

-- --------------------------------------------------------

--
-- Table structure for table `hero_section`
--

CREATE TABLE `hero_section` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `button_text` varchar(100) NOT NULL,
  `main_image` varchar(255) DEFAULT NULL,
  `sparkle_image_1` varchar(255) DEFAULT NULL,
  `sparkle_image_2` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hero_section`
--

INSERT INTO `hero_section` (`id`, `title`, `description`, `button_text`, `main_image`, `sparkle_image_1`, `sparkle_image_2`, `created_at`, `updated_at`) VALUES
(1, 'FIND PRODUCTS THAT MATCHES YOUR PREFERENCE', 'Browse through our diverse range of meticulously arranged kits and appliances', 'Shop Now', 'https://via.placeholder.com/150', 'https://via.placeholder.com/50', 'https://via.placeholder.com/50', '2025-06-17 23:14:54', '2025-06-20 03:21:31');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) UNSIGNED NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_id`, `stock_quantity`, `created_at`, `updated_at`) VALUES
(5, 5, 12, '2025-06-20 01:11:05', '2025-06-20 01:11:05');

-- --------------------------------------------------------

--
-- Table structure for table `miscellaneous_attributes`
--

CREATE TABLE `miscellaneous_attributes` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) UNSIGNED NOT NULL,
  `attribute` enum('new_arrival','featured','trending') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `type` enum('order_received','ready_to_ship','shipped') NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `type`, `order_id`, `is_read`, `created_at`) VALUES
(1, 1, 'Order Received and Ready to Ship. Package will be delivered in 2 days.', 'shipped', 23, 1, '2025-06-19 01:35:57'),
(2, 1, 'Order Received and Ready to Ship. Package will be delivered in 12 days.', 'shipped', 26, 0, '2025-06-20 01:11:57');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `address_id` int(11) UNSIGNED NOT NULL,
  `payment_id` int(11) UNSIGNED DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) NOT NULL,
  `delivery_fee_id` int(11) UNSIGNED DEFAULT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estimated_delivery_days` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `address_id`, `payment_id`, `total`, `delivery_fee`, `delivery_fee_id`, `status`, `created_at`, `updated_at`, `estimated_delivery_days`) VALUES
(2, 1, 1, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 15:14:37', '2025-06-12 16:11:06', NULL),
(3, 1, 2, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 15:25:14', '2025-06-12 16:11:54', NULL),
(4, 1, 3, NULL, 5.00, 5.00, NULL, 'cancelled', '2025-06-12 15:31:26', '2025-06-12 16:11:11', NULL),
(5, 1, 4, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 15:31:59', '2025-06-12 16:10:58', NULL),
(6, 1, 5, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 15:33:01', '2025-06-12 16:10:50', NULL),
(7, 1, 6, NULL, 8.00, 5.00, NULL, 'cancelled', '2025-06-12 16:07:42', '2025-06-12 16:10:44', NULL),
(8, 1, 7, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 17:13:03', '2025-06-12 19:02:38', NULL),
(9, 1, 8, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 17:13:56', '2025-06-12 19:02:32', NULL),
(10, 1, 9, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 17:54:57', '2025-06-12 17:55:24', NULL),
(11, 1, 10, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 17:55:46', '2025-06-12 19:02:23', NULL),
(12, 1, 11, NULL, 8.00, 5.00, NULL, 'cancelled', '2025-06-12 18:01:34', '2025-06-12 19:02:10', NULL),
(13, 1, 12, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 18:17:02', '2025-06-12 19:02:04', NULL),
(14, 1, 13, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 18:32:45', '2025-06-12 19:01:56', NULL),
(15, 1, 14, NULL, 8.00, 5.00, NULL, 'cancelled', '2025-06-12 19:07:48', '2025-06-12 21:06:04', NULL),
(16, 1, 15, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 19:34:08', '2025-06-12 21:05:57', NULL),
(17, 1, 16, NULL, 8.00, 5.00, NULL, 'cancelled', '2025-06-12 21:03:36', '2025-06-12 21:05:48', NULL),
(18, 1, 17, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-13 10:59:56', '2025-06-13 11:07:58', NULL),
(19, 1, 18, NULL, 6.00, 5.00, NULL, 'cancelled', '2025-06-13 11:07:33', '2025-06-13 11:07:49', NULL),
(20, 1, 19, NULL, 6.00, 5.00, NULL, 'cancelled', '2025-06-13 11:17:22', '2025-06-13 11:17:34', NULL),
(21, 1, 20, NULL, 6.00, 5.00, NULL, 'shipped', '2025-06-13 12:19:05', '2025-06-17 22:10:26', NULL),
(22, 1, 21, NULL, 6.00, 5.00, NULL, 'processing', '2025-06-13 12:43:13', '2025-06-13 12:43:13', NULL),
(23, 1, 22, NULL, 6.00, 5.00, NULL, 'shipped', '2025-06-13 13:16:47', '2025-06-19 01:35:57', 2),
(24, 1, 23, NULL, 6.00, 5.00, 1, 'processing', '2025-06-13 14:58:39', '2025-06-13 14:58:39', NULL),
(25, 1, 24, NULL, 51.00, 5.00, 1, 'processing', '2025-06-19 19:09:44', '2025-06-19 19:09:44', NULL),
(26, 1, 25, NULL, 50.00, 5.00, 1, 'shipped', '2025-06-19 19:20:25', '2025-06-20 01:11:57', 12);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(11) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('credit_card','paypal','bank_transfer') NOT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) UNSIGNED NOT NULL,
  `category_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `rating` decimal(3,1) DEFAULT 0.0,
  `featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `sku`, `price`, `image`, `description`, `rating`, `featured`, `created_at`, `updated_at`) VALUES
(5, 3, 'iron', 'PROD84282', 121.00, 'Uploads/img_6854b5293e06a.jpg', 'eejn', 0.0, 1, '2025-06-20 01:11:05', '2025-06-20 03:23:31');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) UNSIGNED NOT NULL,
  `product_id` int(11) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text NOT NULL,
  `verified_purchase` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `static_pages`
--

CREATE TABLE `static_pages` (
  `page_key` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `meta_description` varchar(255) NOT NULL,
  `sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`sections`)),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `static_pages`
--

INSERT INTO `static_pages` (`page_key`, `title`, `description`, `meta_description`, `sections`, `updated_at`) VALUES
('about', 'About Deeken', 'Deeken is your one-stop shop for stylish clothing that matches your personality. We offer a diverse range of meticulously crafted garments for men and women, designed to bring out your individuality.', 'Learn about Deeken, your destination for stylish and high-quality clothing for men and women.', '[{\"heading\":\"Our Mission\",\"text\":\"To provide high-quality, fashionable clothing that empowers our customers to express their unique style.\"},{\"heading\":\"Our Story\",\"text\":\"Founded in 2020, Deeken started with a vision to redefine fashion accessibility. We’ve grown into a trusted brand with over 30,000 happy customers.\"}]', '2025-06-20 01:58:16'),
('blog', 'Deeken Blog', 'Stay updated with the latest fashion trends, styling tips, and Deeken news.', 'Read Deeken’s blog for fashion trends, styling tips, and brand updates.', '[{\"heading\":\"Fashion Trends\",\"text\":\"Explore the latest in fashion and how to style our collections.\"},{\"heading\":\"Style Guides\",\"text\":\"Tips and tricks to elevate your wardrobe.\"}]', '2025-06-20 01:58:16'),
('care-instructions', 'Care Instructions', 'Learn how to care for your Deeken garments to keep them looking great.', 'Follow Deeken’s care instructions to maintain the quality of your clothing.', '[{\"heading\":\"Washing Instructions\",\"text\":\"Machine wash cold, tumble dry low, or follow garment-specific tags.\"},{\"heading\":\"Storage Tips\",\"text\":\"Store in a cool, dry place away from direct sunlight.\"}]', '2025-06-20 01:58:16'),
('careers', 'Careers at Deeken', 'Join our team and be part of a dynamic fashion brand that values creativity and innovation.', 'Explore career opportunities at Deeken and join our innovative fashion team.', '[{\"heading\":\"Open Positions\",\"text\":\"We’re always looking for talented individuals. Check our careers page for current openings.\"},{\"heading\":\"Why Work With Us\",\"text\":\"Competitive salaries, creative work environment, and opportunities for growth.\"}]', '2025-06-20 01:58:16'),
('contact', 'Contact Us', 'We’re here to help! Reach out to us for any inquiries, feedback, or support.', 'Contact Deeken for support, inquiries, or feedback. We’re here to assist you!', '[{\"heading\":\"Get in Touch\",\"text\":\"Email: support@deeken.com<br>Phone: +1 (800) 123-4567<br>Address: 123 Fashion Ave, New York, NY 10001\"},{\"heading\":\"Customer Service Hours\",\"text\":\"Monday - Friday: 9 AM - 6 PM<br>Saturday: 10 AM - 4 PM<br>Sunday: Closed\"}]', '2025-06-20 01:58:16'),
('faq', 'Frequently Asked Questions', 'Find answers to common questions about your account, deliveries, orders, and payments.', 'Find answers to frequently asked questions about accounts, deliveries, orders, and payments at Deeken.', '[{\"id\":\"account\",\"heading\":\"Account\",\"text\":\"How to create an account, update your profile, and reset your password.\"},{\"id\":\"delivery\",\"heading\":\"Manage Deliveries\",\"text\":\"Track your order, update shipping details, or request a return.\"},{\"id\":\"orders\",\"heading\":\"Orders\",\"text\":\"Check order status, cancel orders, or modify existing orders.\"},{\"id\":\"payments\",\"heading\":\"Payments\",\"text\":\"We accept credit cards, bank transfers, and mobile payments.\"}]', '2025-06-20 01:58:16'),
('privacy', 'Privacy Policy', 'We value your privacy. Learn how we collect, use, and protect your data.', 'Understand Deeken’s privacy policy and how we protect your data.', '[{\"heading\":\"Data Collection\",\"text\":\"We collect personal information to process orders and improve your experience.\"},{\"heading\":\"Data Protection\",\"text\":\"Your data is secured with industry-standard encryption.\"}]', '2025-06-20 01:58:16'),
('shipping', 'Delivery Details', 'Learn about our shipping policies, delivery times, and costs.', 'Discover Deeken’s shipping options, delivery times, and policies.', '[{\"heading\":\"Shipping Options\",\"text\":\"Standard Shipping: 5-7 business days ($5)<br>Express Shipping: 2-3 business days ($15)<br>Free shipping on orders over $50.\"},{\"heading\":\"International Shipping\",\"text\":\"Available to over 50 countries. Rates and times vary by location.\"}]', '2025-06-20 01:58:16'),
('size-guide', 'Size Guide', 'Find the perfect fit with our comprehensive size guide.', 'Use Deeken’s size guide to find the perfect fit for men’s and women’s clothing.', '[{\"heading\":\"Men’s Sizes\",\"text\":\"Detailed charts for shirts, pants, and jackets.\"},{\"heading\":\"Women’s Sizes\",\"text\":\"Guides for dresses, tops, and skirts.\"}]', '2025-06-20 01:58:16'),
('support', 'Customer Support', 'Our dedicated support team is here to assist you with any questions or issues.', 'Get help from Deeken’s customer support team for orders, returns, and more.', '[{\"heading\":\"How We Help\",\"text\":\"From order tracking to returns, we’re here to ensure a smooth shopping experience.\"},{\"heading\":\"Contact Support\",\"text\":\"Reach us via email at support@deeken.com or call +1 (800) 123-4567.\"}]', '2025-06-20 01:58:16'),
('terms', 'Terms & Conditions', 'Read our terms and conditions to understand our policies on purchases, returns, and more.', 'Review Deeken’s terms and conditions for purchases, returns, and user policies.', '[{\"heading\":\"Purchase Terms\",\"text\":\"All sales are final unless otherwise stated. Returns accepted within 30 days.\"},{\"heading\":\"User Conduct\",\"text\":\"Users must not misuse our website or services.\"}]', '2025-06-20 01:58:16');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `address` text NOT NULL,
  `phone` varchar(20) NOT NULL,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `full_name`, `profile_picture`, `address`, `phone`, `is_admin`, `created_at`, `updated_at`) VALUES
(1, 'admin@deeken.com', '$2y$10$MmJc/dCV8UgXgsB5YCIppuMseCZkbCBuW.uDaI5BdG5ooJJr8DYDe', 'Idris Ahmad Rabiu', NULL, 'Admin Address', '1234567890', 1, '2025-06-10 22:43:23', '2025-06-13 12:19:05');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `carousel_images`
--
ALTER TABLE `carousel_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id_product_id` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `delivery_fees`
--
ALTER TABLE `delivery_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `hero_section`
--
ALTER TABLE `hero_section`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_id` (`product_id`);

--
-- Indexes for table `miscellaneous_attributes`
--
ALTER TABLE `miscellaneous_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_attribute` (`product_id`,`attribute`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `orders_ibfk_4` (`delivery_fee_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `token` (`token`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `static_pages`
--
ALTER TABLE `static_pages`
  ADD PRIMARY KEY (`page_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `carousel_images`
--
ALTER TABLE `carousel_images`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `delivery_fees`
--
ALTER TABLE `delivery_fees`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hero_section`
--
ALTER TABLE `hero_section`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `miscellaneous_attributes`
--
ALTER TABLE `miscellaneous_attributes`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `miscellaneous_attributes`
--
ALTER TABLE `miscellaneous_attributes`
  ADD CONSTRAINT `miscellaneous_attributes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`),
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`delivery_fee_id`) REFERENCES `delivery_fees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
