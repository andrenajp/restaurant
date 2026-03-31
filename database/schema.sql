CREATE DATABASE IF NOT EXISTS restaurant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE restaurant;

-- Configuration et thème restaurant
CREATE TABLE settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  restaurant_name VARCHAR(100) NOT NULL DEFAULT 'Mon Restaurant',
  logo_url VARCHAR(255),
  color_primary VARCHAR(7) NOT NULL DEFAULT '#CC0000',
  color_accent VARCHAR(7) NOT NULL DEFAULT '#D4A017',
  color_band_1 VARCHAR(7) NOT NULL DEFAULT '#111111',
  color_band_2 VARCHAR(7) NOT NULL DEFAULT '#D4A017',
  color_band_3 VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
  color_band_4 VARCHAR(7) NOT NULL DEFAULT '#1A7A1A',
  delivery_free_above DECIMAL(8,2) DEFAULT 25.00,
  twilio_phone VARCHAR(20),
  stripe_pk_public VARCHAR(100),
  promo_banner VARCHAR(255)
);

-- Catégories (onglets filtres)
CREATE TABLE categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  emoji VARCHAR(10) DEFAULT '🍽️',
  color VARCHAR(7) DEFAULT '#CC0000',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

-- Plats
CREATE TABLE products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(8,2) NOT NULL,
  image_url VARCHAR(255),
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
);

-- Options par plat (riz, sauces, suppléments)
CREATE TABLE product_options (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  group_name VARCHAR(100) NOT NULL,
  option_name VARCHAR(100) NOT NULL,
  extra_price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Utilisateurs (clients optionnels + admin + cuisine)
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(150),
  phone VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(150),
  password_hash VARCHAR(255) NOT NULL,
  default_address TEXT,
  role ENUM('client','kitchen','admin') NOT NULL DEFAULT 'client',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Commandes
CREATE TABLE orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  phone VARCHAR(20) NOT NULL,
  customer_name VARCHAR(150),
  type ENUM('pickup','delivery') NOT NULL,
  delivery_address TEXT,
  status ENUM('received','in_preparation','ready','en_route','delivered','cancelled') NOT NULL DEFAULT 'received',
  total DECIMAL(8,2) NOT NULL,
  delivery_fee DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  stripe_payment_id VARCHAR(100),
  tracking_token CHAR(36) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Lignes de commande
CREATE TABLE order_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(8,2) NOT NULL,
  options_json JSON,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- Frais de livraison par zone
CREATE TABLE delivery_fees (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_name VARCHAR(100) NOT NULL,
  fee DECIMAL(8,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

-- Données de démo
INSERT INTO settings (restaurant_name, color_primary, color_accent, color_band_1, color_band_2, color_band_3, color_band_4, delivery_free_above, promo_banner)
VALUES ('P.R.T', '#CC0000', '#D4A017', '#111111', '#D4A017', '#FFFFFF', '#1A7A1A', 25.00, 'Livraison offerte dès 25€');

INSERT INTO categories (name, emoji, color, sort_order) VALUES
('Indien', '🍛', '#CC0000', 1),
('Créole Guyane', '🌿', '#1A7A1A', 2),
('Créole Haïti', '🥘', '#D4A017', 3),
('Boissons', '🥤', '#111111', 4);

INSERT INTO products (category_id, name, description, price, sort_order) VALUES
(1, 'Curry poulet', 'Riz basmati inclus', 12.00, 1),
(1, 'Dal lentilles', 'Naan inclus', 10.00, 2),
(1, 'Boucané massalé', 'Haricots rouges, riz créole', 13.50, 3),
(2, 'Moule au curry', 'Frites maison', 14.00, 1),
(2, 'Roti et curry', 'Sauce maison', 11.00, 2),
(4, 'Jus mangue', '33cl', 3.50, 1),
(4, 'Jus goyave', '33cl', 3.50, 2);

INSERT INTO product_options (product_id, group_name, option_name, extra_price, is_default) VALUES
(1, 'Riz', 'Normal', 0.00, 1),
(1, 'Riz', 'Riz supplémentaire', 1.00, 0),
(1, 'Sauce', 'Sans sauce', 0.00, 1),
(1, 'Sauce', 'Curry', 0.00, 0),
(1, 'Sauce', 'Massalé', 0.00, 0),
(3, 'Riz', 'Normal', 0.00, 1),
(3, 'Riz', 'Riz supplémentaire', 1.00, 0),
(3, 'Sauce', 'Massalé', 0.00, 1),
(3, 'Sauce', 'Curry', 0.00, 0);

INSERT INTO delivery_fees (zone_name, fee, is_active) VALUES
('Zone standard', 3.50, 1),
('Zone éloignée', 6.00, 1);

INSERT INTO users (name, phone, password_hash, role) VALUES
('Admin', '+33600000000', '$2y$12$placeholder_hash_admin', 'admin'),
('Cuisine', '+33600000001', '$2y$12$placeholder_hash_kitchen', 'kitchen');
