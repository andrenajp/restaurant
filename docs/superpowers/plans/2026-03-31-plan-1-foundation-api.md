# Plan 1 — Foundation : Base de données + API PHP + Auth

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mettre en place la structure PHP, la base de données MySQL complète, et tous les endpoints API REST nécessaires à l'application (menu, commandes, auth, settings).

**Architecture:** PHP 8.x avec un routeur frontal (`api/index.php`), PDO pour MySQL, JWT pour l'authentification. Chaque groupe de routes est dans son propre fichier. Aucun framework — PHP vanilla organisé en modules clairs.

**Tech Stack:** PHP 8.x · MySQL · PDO · JWT (firebase/php-jwt via Composer) · PHPUnit · Apache/Nginx

---

## Structure des fichiers

```
/
├── api/
│   ├── index.php                  # Routeur frontal — dispatch toutes les requêtes
│   ├── config/
│   │   ├── database.php           # Singleton connexion PDO
│   │   └── env.php                # Charge .env manuellement (sans lib externe)
│   ├── middleware/
│   │   └── Auth.php               # Vérifie JWT, retourne payload ou 401
│   ├── helpers/
│   │   ├── Response.php           # json_success(), json_error()
│   │   └── Validator.php          # validate_required(), validate_phone()
│   ├── routes/
│   │   ├── settings.php           # GET /api/settings (thème public)
│   │   ├── menu.php               # GET /api/categories, GET /api/products
│   │   ├── auth.php               # POST /api/auth/register, /api/auth/login
│   │   ├── orders.php             # POST /api/orders, GET /api/orders/{id}
│   │   └── admin/
│   │       ├── orders.php         # GET/PATCH /api/admin/orders
│   │       ├── products.php       # CRUD /api/admin/products
│   │       ├── categories.php     # CRUD /api/admin/categories
│   │       ├── options.php        # CRUD /api/admin/products/{id}/options
│   │       ├── delivery.php       # CRUD /api/admin/delivery-fees
│   │       └── settings.php       # GET/PUT /api/admin/settings
├── database/
│   └── schema.sql                 # Schéma complet avec données de démo
├── tests/
│   ├── bootstrap.php              # Setup PHPUnit + DB de test
│   ├── ApiTest.php                # Tests HTTP avec curl interne
│   ├── MenuTest.php               # Tests endpoints menu
│   ├── OrderTest.php              # Tests création + statut commande
│   └── AuthTest.php               # Tests register/login/JWT
├── .env.example                   # Variables requises documentées
├── .env                           # (gitignore) Variables locales
├── .htaccess                      # Rewrite vers api/index.php
└── composer.json                  # firebase/php-jwt + phpunit
```

---

## Task 1 : Setup projet + Composer

**Files:**
- Create: `composer.json`
- Create: `.env.example`
- Create: `.env`
- Create: `.htaccess`

- [ ] **Créer `composer.json`**

```json
{
  "require": {
    "firebase/php-jwt": "^6.10"
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "api/"
    }
  }
}
```

- [ ] **Créer `.env.example`**

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=restaurant
DB_USER=root
DB_PASS=

JWT_SECRET=changeme_au_moins_32_caracteres
JWT_EXPIRY=86400

STRIPE_SK=sk_test_...
STRIPE_PK=pk_test_...

TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_FROM=+33xxxxxxxxx

APP_ENV=development
APP_URL=http://localhost
```

- [ ] **Copier `.env.example` en `.env` et remplir les valeurs locales**

```bash
cp .env.example .env
```

- [ ] **Créer `.htaccess`**

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/index.php [QSA,L]
RewriteRule ^(.*)$ public/index.html [QSA,L]
```

- [ ] **Installer les dépendances**

```bash
composer install
```

Expected : dossier `vendor/` créé avec `firebase/php-jwt` et `phpunit`.

- [ ] **Commit**

```bash
git init
git add composer.json composer.lock .env.example .htaccess
git commit -m "chore: setup PHP project with Composer"
```

---

## Task 2 : Base de données — schéma complet

**Files:**
- Create: `database/schema.sql`

- [ ] **Créer `database/schema.sql`**

```sql
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
```

- [ ] **Créer la base de données**

```bash
mysql -u root -p < database/schema.sql
```

Expected : pas d'erreur, tables créées.

- [ ] **Vérifier les tables**

```bash
mysql -u root -p -e "USE restaurant; SHOW TABLES; SELECT COUNT(*) as nb_products FROM products;"
```

Expected : 8 tables listées, `nb_products = 7`.

- [ ] **Commit**

```bash
git add database/schema.sql
git commit -m "feat: add complete MySQL schema with demo data"
```

---

## Task 3 : Config + helpers de base

**Files:**
- Create: `api/config/env.php`
- Create: `api/config/database.php`
- Create: `api/helpers/Response.php`
- Create: `api/helpers/Validator.php`

- [ ] **Créer `api/config/env.php`**

```php
<?php
function env(string $key, string $default = ''): string {
    static $loaded = false;
    if (!$loaded) {
        $file = dirname(__DIR__, 2) . '/.env';
        if (file_exists($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                $_ENV[$k] = $v;
            }
        }
        $loaded = true;
    }
    return $_ENV[$key] ?? $default;
}
```

- [ ] **Créer `api/config/database.php`**

```php
<?php
require_once __DIR__ . '/env.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', 'localhost'),
            env('DB_PORT', '3306'),
            env('DB_NAME', 'restaurant')
        );
        $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
```

- [ ] **Créer `api/helpers/Response.php`**

```php
<?php
function json_success(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function json_error(string $message, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}
```

- [ ] **Créer `api/helpers/Validator.php`**

```php
<?php
function validate_required(array $data, array $fields): void {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            json_error("Champ requis : $field", 422);
        }
    }
}

function validate_phone(string $phone): bool {
    return (bool) preg_match('/^\+?[0-9]{8,15}$/', preg_replace('/\s/', '', $phone));
}
```

- [ ] **Créer les tests `tests/bootstrap.php`**

```php
<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/api/config/env.php';
require_once dirname(__DIR__) . '/api/config/database.php';
require_once dirname(__DIR__) . '/api/helpers/Response.php';
require_once dirname(__DIR__) . '/api/helpers/Validator.php';

// Base de test séparée
$_ENV['DB_NAME'] = 'restaurant_test';
```

- [ ] **Créer `tests/ApiTest.php`**

```php
<?php
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
    public function test_db_connects(): void {
        $pdo = db();
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function test_db_has_settings(): void {
        $stmt = db()->query('SELECT COUNT(*) as n FROM settings');
        $row = $stmt->fetch();
        $this->assertGreaterThan(0, (int) $row['n']);
    }

    public function test_validate_phone_valid(): void {
        $this->assertTrue(validate_phone('+33612345678'));
        $this->assertTrue(validate_phone('0612345678'));
    }

    public function test_validate_phone_invalid(): void {
        $this->assertFalse(validate_phone('abc'));
        $this->assertFalse(validate_phone('123'));
    }
}
```

- [ ] **Créer la DB de test et lancer les tests**

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS restaurant_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p restaurant_test < database/schema.sql
./vendor/bin/phpunit tests/ApiTest.php --bootstrap tests/bootstrap.php -v
```

Expected :
```
✓ test_db_connects
✓ test_db_has_settings
✓ test_validate_phone_valid
✓ test_validate_phone_invalid
OK (4 tests, 4 assertions)
```

- [ ] **Commit**

```bash
git add api/config/ api/helpers/ tests/
git commit -m "feat: add DB config, response helpers, validators + tests"
```

---

## Task 4 : Routeur frontal

**Files:**
- Create: `api/index.php`

- [ ] **Créer `api/index.php`**

```php
<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = preg_replace('#^/api#', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Routes publiques
if ($uri === '/settings' && $method === 'GET') {
    require __DIR__ . '/routes/settings.php';
}
elseif (preg_match('#^/categories$#', $uri) && $method === 'GET') {
    require __DIR__ . '/routes/menu.php';
}
elseif (preg_match('#^/products$#', $uri) && $method === 'GET') {
    require __DIR__ . '/routes/menu.php';
}
elseif (preg_match('#^/auth/(register|login)$#', $uri, $m) && $method === 'POST') {
    require __DIR__ . '/routes/auth.php';
}
elseif ($uri === '/orders' && $method === 'POST') {
    require __DIR__ . '/routes/orders.php';
}
elseif (preg_match('#^/orders/([a-f0-9\-]{36})$#', $uri, $m) && $method === 'GET') {
    // Suivi par tracking_token
    require __DIR__ . '/routes/orders.php';
}
// Routes admin (préfixe /admin)
elseif (str_starts_with($uri, '/admin')) {
    require_once __DIR__ . '/middleware/Auth.php';
    auth_require_role(['admin']);
    $admin_uri = preg_replace('#^/admin#', '', $uri);
    if (str_starts_with($admin_uri, '/orders'))    require __DIR__ . '/routes/admin/orders.php';
    elseif (str_starts_with($admin_uri, '/products'))  require __DIR__ . '/routes/admin/products.php';
    elseif (str_starts_with($admin_uri, '/categories')) require __DIR__ . '/routes/admin/categories.php';
    elseif (str_starts_with($admin_uri, '/delivery-fees')) require __DIR__ . '/routes/admin/delivery.php';
    elseif ($admin_uri === '/settings') require __DIR__ . '/routes/admin/settings.php';
    else json_error('Route admin non trouvée', 404);
}
// Routes cuisine
elseif (str_starts_with($uri, '/kitchen')) {
    require_once __DIR__ . '/middleware/Auth.php';
    auth_require_role(['admin', 'kitchen']);
    require __DIR__ . '/routes/admin/orders.php';
}
else {
    json_error('Route non trouvée', 404);
}
```

- [ ] **Commit**

```bash
git add api/index.php
git commit -m "feat: add PHP API router"
```

---

## Task 5 : Middleware Auth (JWT)

**Files:**
- Create: `api/middleware/Auth.php`

- [ ] **Créer `api/middleware/Auth.php`**

```php
<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once dirname(__DIR__) . '/../vendor/autoload.php';

function auth_get_payload(): ?array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!str_starts_with($header, 'Bearer ')) return null;
    $token = substr($header, 7);
    try {
        $decoded = JWT::decode($token, new Key(env('JWT_SECRET', 'secret'), 'HS256'));
        return (array) $decoded;
    } catch (\Exception $e) {
        return null;
    }
}

function auth_require_role(array $roles): array {
    $payload = auth_get_payload();
    if (!$payload) json_error('Non authentifié', 401);
    if (!in_array($payload['role'], $roles)) json_error('Accès interdit', 403);
    return $payload;
}

function auth_make_token(int $user_id, string $role): string {
    $payload = [
        'sub'  => $user_id,
        'role' => $role,
        'iat'  => time(),
        'exp'  => time() + (int) env('JWT_EXPIRY', '86400'),
    ];
    return JWT::encode($payload, env('JWT_SECRET', 'secret'), 'HS256');
}
```

- [ ] **Créer `tests/AuthTest.php`**

```php
<?php
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase {
    public function test_make_token_returns_string(): void {
        require_once __DIR__ . '/../api/middleware/Auth.php';
        $token = auth_make_token(1, 'admin');
        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
    }

    public function test_get_payload_without_header_returns_null(): void {
        unset($_SERVER['HTTP_AUTHORIZATION']);
        require_once __DIR__ . '/../api/middleware/Auth.php';
        $this->assertNull(auth_get_payload());
    }
}
```

- [ ] **Lancer les tests**

```bash
./vendor/bin/phpunit tests/AuthTest.php --bootstrap tests/bootstrap.php -v
```

Expected : `OK (2 tests, 2 assertions)`

- [ ] **Commit**

```bash
git add api/middleware/ tests/AuthTest.php
git commit -m "feat: add JWT auth middleware"
```

---

## Task 6 : Endpoint Settings + Menu

**Files:**
- Create: `api/routes/settings.php`
- Create: `api/routes/menu.php`

- [ ] **Créer `api/routes/settings.php`**

```php
<?php
$stmt = db()->query('SELECT * FROM settings LIMIT 1');
$settings = $stmt->fetch();

// Ne pas exposer les credentials sensibles
unset($settings['id']);

json_success($settings);
```

- [ ] **Créer `api/routes/menu.php`**

```php
<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^/api#', '', $uri);

if ($uri === '/categories') {
    $stmt = db()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order');
    json_success($stmt->fetchAll());
}

if ($uri === '/products') {
    $category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;

    if ($category_id) {
        $stmt = db()->prepare(
            'SELECT p.*, GROUP_CONCAT(
                JSON_OBJECT(
                    "id", po.id,
                    "group_name", po.group_name,
                    "option_name", po.option_name,
                    "extra_price", po.extra_price,
                    "is_default", po.is_default
                ) ORDER BY po.group_name, po.id
            ) as options_raw
            FROM products p
            LEFT JOIN product_options po ON po.product_id = p.id
            WHERE p.category_id = ? AND p.is_available = 1
            GROUP BY p.id
            ORDER BY p.sort_order'
        );
        $stmt->execute([$category_id]);
    } else {
        $stmt = db()->query(
            'SELECT p.*, GROUP_CONCAT(
                JSON_OBJECT(
                    "id", po.id,
                    "group_name", po.group_name,
                    "option_name", po.option_name,
                    "extra_price", po.extra_price,
                    "is_default", po.is_default
                ) ORDER BY po.group_name, po.id
            ) as options_raw
            FROM products p
            LEFT JOIN product_options po ON po.product_id = p.id
            WHERE p.is_available = 1
            GROUP BY p.id
            ORDER BY p.category_id, p.sort_order'
        );
    }

    $products = $stmt->fetchAll();
    foreach ($products as &$p) {
        $p['options'] = $p['options_raw']
            ? json_decode('[' . $p['options_raw'] . ']', true)
            : [];
        unset($p['options_raw']);
    }
    json_success($products);
}

json_error('Route menu non trouvée', 404);
```

- [ ] **Créer `tests/MenuTest.php`**

```php
<?php
use PHPUnit\Framework\TestCase;

class MenuTest extends TestCase {
    public function test_settings_has_required_keys(): void {
        $stmt = db()->query('SELECT * FROM settings LIMIT 1');
        $s = $stmt->fetch();
        $this->assertArrayHasKey('color_primary', $s);
        $this->assertArrayHasKey('color_band_1', $s);
        $this->assertArrayHasKey('restaurant_name', $s);
    }

    public function test_categories_returns_active_only(): void {
        $stmt = db()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order');
        $cats = $stmt->fetchAll();
        $this->assertGreaterThan(0, count($cats));
        foreach ($cats as $c) {
            $this->assertEquals(1, (int) $c['is_active']);
        }
    }

    public function test_products_have_options(): void {
        $stmt = db()->query('SELECT p.id FROM products p JOIN product_options po ON po.product_id = p.id LIMIT 1');
        $row = $stmt->fetch();
        $this->assertNotEmpty($row);
    }
}
```

- [ ] **Lancer les tests**

```bash
./vendor/bin/phpunit tests/MenuTest.php --bootstrap tests/bootstrap.php -v
```

Expected : `OK (3 tests, 5 assertions)`

- [ ] **Tester manuellement avec curl**

```bash
# Démarrer PHP built-in server
php -S localhost:8080 -t . &

curl http://localhost:8080/api/settings
# Expected: {"success":true,"data":{"restaurant_name":"P.R.T","color_primary":"#CC0000",...}}

curl http://localhost:8080/api/categories
# Expected: {"success":true,"data":[{"id":1,"name":"Indien",...},...]}`

curl http://localhost:8080/api/products
# Expected: liste de 7 plats avec leurs options
```

- [ ] **Commit**

```bash
git add api/routes/settings.php api/routes/menu.php tests/MenuTest.php
git commit -m "feat: add GET /api/settings and GET /api/products|categories"
```

---

## Task 7 : Endpoint Auth (register + login)

**Files:**
- Create: `api/routes/auth.php`

- [ ] **Créer `api/routes/auth.php`**

```php
<?php
require_once dirname(__DIR__) . '/middleware/Auth.php';

preg_match('#/(register|login)$#', $uri, $m);
$action = $m[1] ?? '';

if ($action === 'register') {
    validate_required($body, ['phone', 'password']);

    if (!validate_phone($body['phone'])) {
        json_error('Numéro de téléphone invalide', 422);
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE phone = ?');
    $stmt->execute([$body['phone']]);
    if ($stmt->fetch()) json_error('Ce numéro est déjà utilisé', 409);

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $stmt = db()->prepare(
        'INSERT INTO users (name, phone, password_hash, role) VALUES (?, ?, ?, "client")'
    );
    $stmt->execute([$body['name'] ?? null, $body['phone'], $hash]);
    $user_id = (int) db()->lastInsertId();

    json_success([
        'token' => auth_make_token($user_id, 'client'),
        'user'  => ['id' => $user_id, 'phone' => $body['phone'], 'role' => 'client'],
    ], 201);
}

if ($action === 'login') {
    validate_required($body, ['phone', 'password']);

    $stmt = db()->prepare('SELECT * FROM users WHERE phone = ?');
    $stmt->execute([$body['phone']]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($body['password'], $user['password_hash'])) {
        json_error('Identifiants invalides', 401);
    }

    json_success([
        'token' => auth_make_token($user['id'], $user['role']),
        'user'  => ['id' => $user['id'], 'phone' => $user['phone'], 'role' => $user['role']],
    ]);
}

json_error('Action auth invalide', 400);
```

- [ ] **Ajouter tests dans `tests/AuthTest.php`**

```php
public function test_register_creates_user(): void {
    $phone = '+336' . rand(10000000, 99999999);
    $stmt = db()->prepare(
        'INSERT INTO users (phone, password_hash, role) VALUES (?, ?, "client")'
    );
    $stmt->execute([$phone, password_hash('test1234', PASSWORD_BCRYPT)]);
    $id = (int) db()->lastInsertId();
    $this->assertGreaterThan(0, $id);

    // Cleanup
    db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
}

public function test_password_verify_works(): void {
    $hash = password_hash('secret123', PASSWORD_BCRYPT);
    $this->assertTrue(password_verify('secret123', $hash));
    $this->assertFalse(password_verify('wrong', $hash));
}
```

- [ ] **Lancer les tests**

```bash
./vendor/bin/phpunit tests/AuthTest.php --bootstrap tests/bootstrap.php -v
```

Expected : `OK (4 tests, 5 assertions)`

- [ ] **Tester avec curl**

```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"phone":"+33612345678","password":"test1234","name":"Test"}'
# Expected: {"success":true,"data":{"token":"eyJ...","user":{...}}}

curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"+33612345678","password":"test1234"}'
# Expected: {"success":true,"data":{"token":"eyJ...","user":{...}}}
```

- [ ] **Commit**

```bash
git add api/routes/auth.php tests/AuthTest.php
git commit -m "feat: add POST /api/auth/register and /api/auth/login"
```

---

## Task 8 : Endpoint Orders (création + suivi)

**Files:**
- Create: `api/routes/orders.php`

- [ ] **Créer `api/routes/orders.php`**

```php
<?php

// GET /api/orders/{tracking_token} — suivi public
if ($method === 'GET') {
    preg_match('#/orders/([a-f0-9\-]{36})$#', $uri, $m);
    $token = $m[1] ?? '';

    $stmt = db()->prepare(
        'SELECT o.id, o.status, o.type, o.customer_name, o.created_at,
                oi.quantity, oi.unit_price, oi.options_json,
                p.name as product_name
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN products p ON p.id = oi.product_id
         WHERE o.tracking_token = ?'
    );
    $stmt->execute([$token]);
    $rows = $stmt->fetchAll();

    if (!$rows) json_error('Commande introuvable', 404);

    $order = [
        'status'        => $rows[0]['status'],
        'type'          => $rows[0]['type'],
        'customer_name' => $rows[0]['customer_name'],
        'created_at'    => $rows[0]['created_at'],
        'items'         => array_map(fn($r) => [
            'name'        => $r['product_name'],
            'quantity'    => (int) $r['quantity'],
            'unit_price'  => (float) $r['unit_price'],
            'options'     => json_decode($r['options_json'] ?? 'null', true),
        ], $rows),
    ];

    json_success($order);
}

// POST /api/orders — création de commande
if ($method === 'POST') {
    validate_required($body, ['phone', 'type', 'items']);

    if (!in_array($body['type'], ['pickup', 'delivery'])) {
        json_error('Type invalide : pickup ou delivery', 422);
    }
    if ($body['type'] === 'delivery' && empty($body['delivery_address'])) {
        json_error('Adresse requise pour une livraison', 422);
    }
    if (!is_array($body['items']) || count($body['items']) === 0) {
        json_error('Panier vide', 422);
    }
    if (!validate_phone($body['phone'])) {
        json_error('Numéro de téléphone invalide', 422);
    }

    // Calculer le total côté serveur (ne jamais faire confiance au client)
    $total = 0.0;
    $items_validated = [];
    foreach ($body['items'] as $item) {
        if (empty($item['product_id']) || empty($item['quantity'])) {
            json_error('Item invalide dans le panier', 422);
        }
        $stmt = db()->prepare('SELECT id, price, name FROM products WHERE id = ? AND is_available = 1');
        $stmt->execute([(int) $item['product_id']]);
        $product = $stmt->fetch();
        if (!$product) json_error("Produit introuvable : {$item['product_id']}", 422);

        $unit_price = (float) $product['price'];
        // Ajouter les suppléments
        if (!empty($item['options'])) {
            foreach ($item['options'] as $opt_id) {
                $stmt2 = db()->prepare('SELECT extra_price FROM product_options WHERE id = ? AND product_id = ?');
                $stmt2->execute([(int) $opt_id, (int) $item['product_id']]);
                $opt = $stmt2->fetch();
                if ($opt) $unit_price += (float) $opt['extra_price'];
            }
        }
        $qty = max(1, (int) $item['quantity']);
        $total += $unit_price * $qty;
        $items_validated[] = [
            'product_id'  => (int) $item['product_id'],
            'quantity'    => $qty,
            'unit_price'  => $unit_price,
            'options'     => $item['options'] ?? [],
        ];
    }

    // Frais de livraison
    $delivery_fee = 0.0;
    if ($body['type'] === 'delivery') {
        $settings = db()->query('SELECT delivery_free_above FROM settings LIMIT 1')->fetch();
        $free_above = (float) ($settings['delivery_free_above'] ?? 25);
        if ($total < $free_above) {
            $fee_row = db()->query('SELECT fee FROM delivery_fees WHERE is_active = 1 LIMIT 1')->fetch();
            $delivery_fee = $fee_row ? (float) $fee_row['fee'] : 3.50;
        }
    }
    $total += $delivery_fee;

    $tracking_token = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
        mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
        mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
    );

    // Récupérer user_id si connecté
    $user_id = null;
    $payload = auth_get_payload();
    if ($payload) $user_id = $payload['sub'];

    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            'INSERT INTO orders (user_id, phone, customer_name, type, delivery_address, total, delivery_fee, tracking_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user_id,
            $body['phone'],
            $body['customer_name'] ?? null,
            $body['type'],
            $body['delivery_address'] ?? null,
            $total,
            $delivery_fee,
            $tracking_token,
        ]);
        $order_id = (int) db()->lastInsertId();

        $stmt_item = db()->prepare(
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price, options_json) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items_validated as $item) {
            $stmt_item->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                json_encode($item['options']),
            ]);
        }
        db()->commit();
    } catch (\Exception $e) {
        db()->rollBack();
        json_error('Erreur création commande', 500);
    }

    json_success([
        'order_id'       => $order_id,
        'tracking_token' => $tracking_token,
        'total'          => $total,
        'delivery_fee'   => $delivery_fee,
    ], 201);
}

json_error('Méthode non supportée', 405);
```

- [ ] **Créer `tests/OrderTest.php`**

```php
<?php
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase {
    private int $test_order_id = 0;

    public function test_create_order_pickup(): void {
        $token = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
        );
        $stmt = db()->prepare(
            'INSERT INTO orders (phone, type, total, delivery_fee, tracking_token) VALUES (?, "pickup", 12.00, 0, ?)'
        );
        $stmt->execute(['+33699999999', $token]);
        $id = (int) db()->lastInsertId();
        $this->assertGreaterThan(0, $id);
        $this->test_order_id = $id;

        // Cleanup
        db()->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
    }

    public function test_order_total_includes_delivery_fee(): void {
        // Livraison < seuil gratuit → frais non nuls
        $settings = db()->query('SELECT delivery_free_above FROM settings LIMIT 1')->fetch();
        $this->assertGreaterThan(0, (float) $settings['delivery_free_above']);
    }

    public function test_tracking_token_is_uuid_format(): void {
        $token = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff),
            mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000,
            mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff)
        );
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $token
        );
    }
}
```

- [ ] **Lancer les tests**

```bash
./vendor/bin/phpunit tests/OrderTest.php --bootstrap tests/bootstrap.php -v
```

Expected : `OK (3 tests, 3 assertions)`

- [ ] **Tester avec curl**

```bash
curl -X POST http://localhost:8080/api/orders \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+33612345678",
    "type": "pickup",
    "customer_name": "Jean",
    "items": [{"product_id": 1, "quantity": 2, "options": [1]}]
  }'
# Expected: {"success":true,"data":{"order_id":1,"tracking_token":"xxxx-...","total":25.00}}
```

- [ ] **Commit**

```bash
git add api/routes/orders.php tests/OrderTest.php
git commit -m "feat: add POST /api/orders and GET /api/orders/{token}"
```

---

## Task 9 : Routes Admin — Commandes + Statuts

**Files:**
- Create: `api/routes/admin/orders.php`

- [ ] **Créer `api/routes/admin/orders.php`**

```php
<?php
$admin_uri = preg_replace('#^/api/admin#', '', $uri);

// GET /api/admin/orders — liste avec filtres
if ($method === 'GET') {
    $where = [];
    $params = [];

    if (!empty($_GET['status'])) {
        $where[] = 'o.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['type'])) {
        $where[] = 'o.type = ?';
        $params[] = $_GET['type'];
    }

    $sql = 'SELECT o.*, COUNT(oi.id) as item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    json_success($stmt->fetchAll());
}

// PATCH /api/admin/orders/{id} — changer le statut
if ($method === 'PATCH') {
    preg_match('#/orders/(\d+)$#', $admin_uri, $m);
    $order_id = (int) ($m[1] ?? 0);
    if (!$order_id) json_error('ID commande requis', 422);

    validate_required($body, ['status']);
    $valid_statuses = ['received', 'in_preparation', 'ready', 'en_route', 'delivered', 'cancelled'];
    if (!in_array($body['status'], $valid_statuses)) {
        json_error('Statut invalide', 422);
    }

    $stmt = db()->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmt->execute([$body['status'], $order_id]);

    if ($stmt->rowCount() === 0) json_error('Commande introuvable', 404);

    json_success(['updated' => true, 'status' => $body['status']]);
}

json_error('Méthode non supportée pour admin/orders', 405);
```

- [ ] **Tester avec curl (avec token admin)**

```bash
# D'abord obtenir un token admin (mettre à jour le hash en DB)
mysql -u root -p -e "UPDATE restaurant.users SET password_hash = '$(php -r "echo password_hash(\"admin123\", PASSWORD_BCRYPT);")'  WHERE role = 'admin';"

TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"+33600000000","password":"admin123"}' | php -r "echo json_decode(file_get_contents('php://stdin'))->data->token;")

curl http://localhost:8080/api/admin/orders \
  -H "Authorization: Bearer $TOKEN"
# Expected: {"success":true,"data":[...]}

curl -X PATCH http://localhost:8080/api/admin/orders/1 \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status":"in_preparation"}'
# Expected: {"success":true,"data":{"updated":true,"status":"in_preparation"}}
```

- [ ] **Commit**

```bash
git add api/routes/admin/orders.php
git commit -m "feat: add admin orders API (list + status update)"
```

---

## Task 10 : Routes Admin — CRUD Produits + Catégories + Settings

**Files:**
- Create: `api/routes/admin/products.php`
- Create: `api/routes/admin/categories.php`
- Create: `api/routes/admin/settings.php`
- Create: `api/routes/admin/delivery.php`

- [ ] **Créer `api/routes/admin/products.php`**

```php
<?php
preg_match('#/products(?:/(\d+))?$#', $uri, $m);
$product_id = isset($m[1]) ? (int) $m[1] : null;

if ($method === 'GET' && !$product_id) {
    $stmt = db()->query('SELECT * FROM products ORDER BY category_id, sort_order');
    json_success($stmt->fetchAll());
}

if ($method === 'POST') {
    validate_required($body, ['category_id', 'name', 'price']);
    $stmt = db()->prepare(
        'INSERT INTO products (category_id, name, description, price, image_url, is_available, sort_order)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) $body['category_id'],
        $body['name'],
        $body['description'] ?? null,
        (float) $body['price'],
        $body['image_url'] ?? null,
        isset($body['is_available']) ? (int) $body['is_available'] : 1,
        (int) ($body['sort_order'] ?? 0),
    ]);
    json_success(['id' => (int) db()->lastInsertId()], 201);
}

if ($method === 'PUT' && $product_id) {
    validate_required($body, ['name', 'price']);
    $stmt = db()->prepare(
        'UPDATE products SET category_id=?, name=?, description=?, price=?, image_url=?, is_available=?, sort_order=? WHERE id=?'
    );
    $stmt->execute([
        (int) $body['category_id'],
        $body['name'],
        $body['description'] ?? null,
        (float) $body['price'],
        $body['image_url'] ?? null,
        (int) ($body['is_available'] ?? 1),
        (int) ($body['sort_order'] ?? 0),
        $product_id,
    ]);
    json_success(['updated' => true]);
}

if ($method === 'DELETE' && $product_id) {
    db()->prepare('DELETE FROM products WHERE id = ?')->execute([$product_id]);
    json_success(['deleted' => true]);
}

json_error('Route produit invalide', 405);
```

- [ ] **Créer `api/routes/admin/categories.php`**

```php
<?php
preg_match('#/categories(?:/(\d+))?$#', $uri, $m);
$cat_id = isset($m[1]) ? (int) $m[1] : null;

if ($method === 'GET') {
    json_success(db()->query('SELECT * FROM categories ORDER BY sort_order')->fetchAll());
}

if ($method === 'POST') {
    validate_required($body, ['name']);
    $stmt = db()->prepare(
        'INSERT INTO categories (name, emoji, color, sort_order, is_active) VALUES (?, ?, ?, ?, 1)'
    );
    $stmt->execute([$body['name'], $body['emoji'] ?? '🍽️', $body['color'] ?? '#CC0000', (int)($body['sort_order'] ?? 0)]);
    json_success(['id' => (int) db()->lastInsertId()], 201);
}

if ($method === 'PUT' && $cat_id) {
    validate_required($body, ['name']);
    $stmt = db()->prepare('UPDATE categories SET name=?, emoji=?, color=?, sort_order=?, is_active=? WHERE id=?');
    $stmt->execute([$body['name'], $body['emoji'] ?? '🍽️', $body['color'] ?? '#CC0000', (int)($body['sort_order'] ?? 0), (int)($body['is_active'] ?? 1), $cat_id]);
    json_success(['updated' => true]);
}

if ($method === 'DELETE' && $cat_id) {
    db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$cat_id]);
    json_success(['deleted' => true]);
}

json_error('Route catégorie invalide', 405);
```

- [ ] **Créer `api/routes/admin/settings.php`**

```php
<?php
if ($method === 'GET') {
    json_success(db()->query('SELECT * FROM settings LIMIT 1')->fetch());
}

if ($method === 'PUT') {
    $allowed = ['restaurant_name','logo_url','color_primary','color_accent',
                'color_band_1','color_band_2','color_band_3','color_band_4',
                'delivery_free_above','twilio_phone','stripe_pk_public','promo_banner'];
    $sets = [];
    $vals = [];
    foreach ($allowed as $field) {
        if (array_key_exists($field, $body)) {
            $sets[] = "$field = ?";
            $vals[] = $body[$field];
        }
    }
    if (!$sets) json_error('Aucun champ à mettre à jour', 422);
    db()->prepare('UPDATE settings SET ' . implode(', ', $sets) . ' WHERE id = 1')
        ->execute($vals);
    json_success(['updated' => true]);
}

json_error('Route settings invalide', 405);
```

- [ ] **Créer `api/routes/admin/delivery.php`**

```php
<?php
preg_match('#/delivery-fees(?:/(\d+))?$#', $uri, $m);
$fee_id = isset($m[1]) ? (int) $m[1] : null;

if ($method === 'GET') {
    json_success(db()->query('SELECT * FROM delivery_fees ORDER BY id')->fetchAll());
}
if ($method === 'POST') {
    validate_required($body, ['zone_name', 'fee']);
    $stmt = db()->prepare('INSERT INTO delivery_fees (zone_name, fee, is_active) VALUES (?, ?, 1)');
    $stmt->execute([$body['zone_name'], (float) $body['fee']]);
    json_success(['id' => (int) db()->lastInsertId()], 201);
}
if ($method === 'PUT' && $fee_id) {
    validate_required($body, ['zone_name', 'fee']);
    $stmt = db()->prepare('UPDATE delivery_fees SET zone_name=?, fee=?, is_active=? WHERE id=?');
    $stmt->execute([$body['zone_name'], (float) $body['fee'], (int)($body['is_active'] ?? 1), $fee_id]);
    json_success(['updated' => true]);
}
if ($method === 'DELETE' && $fee_id) {
    db()->prepare('DELETE FROM delivery_fees WHERE id = ?')->execute([$fee_id]);
    json_success(['deleted' => true]);
}

json_error('Route delivery-fees invalide', 405);
```

- [ ] **Tester le CRUD produits avec curl**

```bash
# Créer un produit
curl -X POST http://localhost:8080/api/admin/products \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"category_id":1,"name":"Test plat","price":9.99}'
# Expected: {"success":true,"data":{"id":8},"code":201}

# Modifier le thème
curl -X PUT http://localhost:8080/api/admin/settings \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"color_primary":"#AA0000","promo_banner":"Nouveau : livraison offerte !"}'
# Expected: {"success":true,"data":{"updated":true}}
```

- [ ] **Commit**

```bash
git add api/routes/admin/
git commit -m "feat: add admin CRUD for products, categories, settings, delivery fees"
```

---

## Task 10b : Route Admin — Options produit

**Files:**
- Create: `api/routes/admin/options.php`

- [ ] **Ajouter le dispatch dans `api/index.php`** — dans le bloc admin, après la ligne `categories` :

```php
elseif (str_starts_with($admin_uri, '/options'))   require __DIR__ . '/routes/admin/options.php';
```

- [ ] **Créer `api/routes/admin/options.php`**

```php
<?php
// /api/admin/options?product_id=X  — GET toutes les options d'un plat
// /api/admin/options                — POST créer une option
// /api/admin/options/{id}           — DELETE supprimer une option

preg_match('#/options(?:/(\d+))?$#', $uri, $m);
$opt_id = isset($m[1]) ? (int) $m[1] : null;

if ($method === 'GET') {
    $product_id = isset($_GET['product_id']) ? (int) $_GET['product_id'] : null;
    if (!$product_id) json_error('product_id requis', 422);
    $stmt = db()->prepare('SELECT * FROM product_options WHERE product_id = ? ORDER BY group_name, id');
    $stmt->execute([$product_id]);
    json_success($stmt->fetchAll());
}

if ($method === 'POST') {
    validate_required($body, ['product_id', 'group_name', 'option_name']);
    $stmt = db()->prepare(
        'INSERT INTO product_options (product_id, group_name, option_name, extra_price, is_default) VALUES (?,?,?,?,?)'
    );
    $stmt->execute([
        (int) $body['product_id'],
        $body['group_name'],
        $body['option_name'],
        (float) ($body['extra_price'] ?? 0),
        (int) ($body['is_default'] ?? 0),
    ]);
    json_success(['id' => (int) db()->lastInsertId()], 201);
}

if ($method === 'DELETE' && $opt_id) {
    db()->prepare('DELETE FROM product_options WHERE id = ?')->execute([$opt_id]);
    json_success(['deleted' => true]);
}

json_error('Route options invalide', 405);
```

- [ ] **Commit**

```bash
git add api/routes/admin/options.php api/index.php
git commit -m "feat: add admin CRUD for product options"
```

---

## Task 11 : Vérification finale + nettoyage

- [ ] **Lancer tous les tests**

```bash
./vendor/bin/phpunit tests/ --bootstrap tests/bootstrap.php -v
```

Expected : tous verts, 0 failures.

- [ ] **Vérifier que toutes les routes répondent**

```bash
# Routes publiques
curl -s http://localhost:8080/api/settings | php -r "echo json_decode(file_get_contents('php://stdin'))->success ? 'OK' : 'FAIL';"
curl -s http://localhost:8080/api/categories | php -r "echo json_decode(file_get_contents('php://stdin'))->success ? 'OK' : 'FAIL';"
curl -s http://localhost:8080/api/products | php -r "echo json_decode(file_get_contents('php://stdin'))->success ? 'OK' : 'FAIL';"

# Route 401 sans token
curl -s http://localhost:8080/api/admin/orders | php -r "echo json_decode(file_get_contents('php://stdin'))->error === 'Non authentifié' ? 'OK' : 'FAIL';"
```

- [ ] **Commit final**

```bash
git add .
git commit -m "feat: complete Plan 1 — PHP API REST foundation"
```
