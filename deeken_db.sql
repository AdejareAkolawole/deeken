-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 13, 2025 at 05:29 PM
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
(23, 1, 'Idris Ahmad Rabiu', 'Admin Address', '', '', '', '', '1234567890', 1, '2025-06-13 14:58:39', '2025-06-13 14:58:39');

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
(2, 'appliances', 'your day to day appliances', '2025-06-12 14:47:21', '2025-06-12 14:47:21');

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
(2, 2, 0, '2025-06-12 16:07:08', '2025-06-12 21:03:36'),
(3, 3, 15, '2025-06-13 09:11:00', '2025-06-13 14:58:39');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `address_id`, `payment_id`, `total`, `delivery_fee`, `delivery_fee_id`, `status`, `created_at`, `updated_at`) VALUES
(2, 1, 1, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 15:14:37', '2025-06-12 16:11:06'),
(3, 1, 2, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 15:25:14', '2025-06-12 16:11:54'),
(4, 1, 3, NULL, 5.00, 5.00, NULL, 'cancelled', '2025-06-12 15:31:26', '2025-06-12 16:11:11'),
(5, 1, 4, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 15:31:59', '2025-06-12 16:10:58'),
(6, 1, 5, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 15:33:01', '2025-06-12 16:10:50'),
(7, 1, 6, NULL, 8.00, 5.00, NULL, 'cancelled', '2025-06-12 16:07:42', '2025-06-12 16:10:44'),
(8, 1, 7, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 17:13:03', '2025-06-12 19:02:38'),
(9, 1, 8, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 17:13:56', '2025-06-12 19:02:32'),
(10, 1, 9, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 17:54:57', '2025-06-12 17:55:24'),
(11, 1, 10, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 17:55:46', '2025-06-12 19:02:23'),
(12, 1, 11, NULL, 8.00, 5.00, NULL, 'cancelled', '2025-06-12 18:01:34', '2025-06-12 19:02:10'),
(13, 1, 12, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 18:17:02', '2025-06-12 19:02:04'),
(14, 1, 13, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 18:32:45', '2025-06-12 19:01:56'),
(15, 1, 14, NULL, 8.00, 5.00, NULL, 'cancelled', '2025-06-12 19:07:48', '2025-06-12 21:06:04'),
(16, 1, 15, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-12 19:34:08', '2025-06-12 21:05:57'),
(17, 1, 16, NULL, 8.00, 5.00, NULL, 'cancelled', '2025-06-12 21:03:36', '2025-06-12 21:05:48'),
(18, 1, 17, NULL, 7.00, 5.00, NULL, 'cancelled', '2025-06-13 10:59:56', '2025-06-13 11:07:58'),
(19, 1, 18, NULL, 6.00, 5.00, NULL, 'cancelled', '2025-06-13 11:07:33', '2025-06-13 11:07:49'),
(20, 1, 19, NULL, 6.00, 5.00, NULL, 'cancelled', '2025-06-13 11:17:22', '2025-06-13 11:17:34'),
(21, 1, 20, NULL, 6.00, 5.00, NULL, 'processing', '2025-06-13 12:19:05', '2025-06-13 12:19:05'),
(22, 1, 21, NULL, 6.00, 5.00, NULL, 'processing', '2025-06-13 12:43:13', '2025-06-13 12:43:13'),
(23, 1, 22, NULL, 6.00, 5.00, NULL, 'processing', '2025-06-13 13:16:47', '2025-06-13 13:16:47'),
(24, 1, 23, NULL, 6.00, 5.00, 1, 'processing', '2025-06-13 14:58:39', '2025-06-13 14:58:39');

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

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`, `created_at`) VALUES
(5, 7, 2, 1, 3.00, '2025-06-12 16:07:42'),
(10, 12, 2, 1, 3.00, '2025-06-12 18:01:34'),
(13, 15, 2, 1, 3.00, '2025-06-12 19:07:48'),
(15, 17, 2, 1, 3.00, '2025-06-12 21:03:36'),
(16, 18, 3, 2, 1.00, '2025-06-13 10:59:56'),
(17, 19, 3, 1, 1.00, '2025-06-13 11:07:33'),
(18, 20, 3, 1, 1.00, '2025-06-13 11:17:22'),
(19, 21, 3, 1, 1.00, '2025-06-13 12:19:05'),
(20, 22, 3, 1, 1.00, '2025-06-13 12:43:13'),
(21, 23, 3, 1, 1.00, '2025-06-13 13:16:47'),
(22, 24, 3, 1, 1.00, '2025-06-13 14:58:39');

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
(2, 2, 'screw driver ++', 'PROD20771', 3.00, 'Uploads/img_684bf7eb558b4.jpg', 'Descrirvdcsxaption for screw driver ++', 0.0, 0, '2025-06-12 16:07:08', '2025-06-13 10:05:31'),
(3, 2, 'screw driver', 'PROD99562', 1.00, 'Uploads/img_684beb243492d.jpg', 'gfdsa', 0.0, 0, '2025-06-13 09:11:00', '2025-06-13 09:11:00');

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

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `rating`, `review_text`, `verified_purchase`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 5, 'nicee', 0, '2025-06-13 09:31:03', '2025-06-13 09:31:03');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
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
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `carousel_images`
--
ALTER TABLE `carousel_images`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `delivery_fees`
--
ALTER TABLE `delivery_fees`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `miscellaneous_attributes`
--
ALTER TABLE `miscellaneous_attributes`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

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
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
