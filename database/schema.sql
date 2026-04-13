-- CREATE DATABASE IF NOT EXISTS restaurant CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE restaurant;

-- Configuration et thème restaurant
CREATE TABLE IF NOT EXISTS settings (
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
CREATE TABLE IF NOT EXISTS categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  emoji VARCHAR(10) DEFAULT NULL,
  color VARCHAR(7) DEFAULT '#CC0000',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

-- Plats
CREATE TABLE IF NOT EXISTS products (
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
CREATE TABLE IF NOT EXISTS product_options (
  id INT PRIMARY KEY AUTO_INCREMENT,
  product_id INT NOT NULL,
  group_name VARCHAR(100) NOT NULL,
  option_name VARCHAR(100) NOT NULL,
  extra_price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Utilisateurs (clients optionnels + admin + cuisine + delivery)
CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(150),
  phone VARCHAR(20) NOT NULL UNIQUE,
  email VARCHAR(150),
  password_hash VARCHAR(255) NOT NULL,
  default_address TEXT,
  role ENUM('client','kitchen','delivery','admin') NOT NULL DEFAULT 'client',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Commandes
CREATE TABLE IF NOT EXISTS orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  phone VARCHAR(20) NOT NULL,
  customer_name VARCHAR(150),
  type ENUM('pickup','delivery') NOT NULL,
  delivery_address TEXT,
  status ENUM('pending','received','in_preparation','ready','en_route','delivered','cancelled') NOT NULL DEFAULT 'pending',
  total DECIMAL(8,2) NOT NULL,
  delivery_fee DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  delivery_driver_id INT NULL,
  stripe_payment_id VARCHAR(100),
  tracking_token CHAR(36) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (delivery_driver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Lignes de commande
CREATE TABLE IF NOT EXISTS order_items (
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
CREATE TABLE IF NOT EXISTS delivery_fees (
  id INT PRIMARY KEY AUTO_INCREMENT,
  zone_name VARCHAR(100) NOT NULL,
  fee DECIMAL(8,2) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

-- Reset password (utilisé par auth.php)
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  phone VARCHAR(20) NOT NULL,
  code VARCHAR(10) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_phone_code (phone, code)
);

-- Logs admin
CREATE TABLE IF NOT EXISTS admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(50),
  target_id INT,
  details JSON,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Motifs d'annulation
CREATE TABLE IF NOT EXISTS order_cancellation_reasons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  cancelled_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (cancelled_by) REFERENCES users(id)
);

-- Horaires d'ouverture
CREATE TABLE IF NOT EXISTS opening_hours (
  id INT AUTO_INCREMENT PRIMARY KEY,
  day_of_week TINYINT NOT NULL,
  open_time TIME NULL,
  close_time TIME NULL,
  is_closed TINYINT(1) DEFAULT 0,
  UNIQUE KEY idx_day (day_of_week)
);

-- Données de démo
INSERT INTO settings (restaurant_name, color_primary, color_accent, color_band_1, color_band_2, color_band_3, color_band_4, delivery_free_above, promo_banner)
SELECT 'P.R.T', '#CC0000', '#D4A017', '#111111', '#D4A017', '#FFFFFF', '#1A7A1A', 25.00, 'Livraison offerte dès 25€'
WHERE NOT EXISTS (SELECT 1 FROM settings);

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

-- Insérer les horaires par défaut (ouverts tous les jours 11h-14h et 18h-22h)
INSERT INTO opening_hours (day_of_week, open_time, close_time, is_closed) VALUES
(0, '11:00:00', '14:00:00', 0),
(1, '11:00:00', '14:00:00', 0),
(2, '11:00:00', '14:00:00', 0),
(3, '11:00:00', '14:00:00', 0),
(4, '11:00:00', '14:00:00', 0),
(5, '11:00:00', '14:00:00', 0),
(6, '11:00:00', '14:00:00', 0);