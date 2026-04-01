# Plan 2 — Frontend Client Alpine.js

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construire le frontend mobile-first de l'application restaurant P.R.T avec Alpine.js, connecté à l'API PHP (Plan 1), avec menu, panier, checkout Stripe intégré, page suivi commande et PWA.

**Architecture:** SPA mono-page `public/index.html` avec Alpine.js pour la réactivité (store global panier + data locaux par section). Le CSS est basé sur le template Delivo déjà présent dans le projet (`delivo-food-delivery-pwa-mobile-html-template-*/Main-file/assets/`), surchargé par `public/assets/css/prt.css` pour la palette P.R.T. Les données menu/thème sont chargées depuis l'API PHP au démarrage. Le checkout est un modal multi-étapes avec widget Stripe Elements (pas de redirection). L'endpoint Stripe backend est ajouté dans `api/routes/payment.php`.

**Tech Stack:** Alpine.js 3.14 (CDN) · Stripe.js v3 (CDN Stripe) · Bootstrap 5 + CSS Delivo (copié depuis template) · PHP API REST (Plan 1)

---

## Structure des fichiers

```
public/
├── index.html              # SPA principale : menu + panier drawer + checkout modal
├── track.html              # Page suivi commande (token dans l'URL)
├── account.html            # Compte client : connexion + inscription (facultatif)
├── manifest.json           # PWA manifest
├── sw.js                   # Service Worker (cache menu + assets statiques)
└── assets/
    ├── css/
    │   ├── styles.css      # Copie du CSS Delivo (base)
    │   └── prt.css         # Variables CSS P.R.T + overrides Delivo
    ├── js/
    │   ├── api.js          # Fetch helpers : getSettings, getCategories, getProducts, etc.
    │   └── app.js          # Alpine.store('cart') + Alpine.data('checkout')
    └── vendor/
        └── bootstrap/      # Copie depuis template Delivo

api/routes/
└── payment.php             # POST /api/payment/create-intent (nouveau)

tests/
└── PaymentTest.php         # Test endpoint create-intent
```

---

## Task 1 : Setup public/ + copie assets Delivo

**Files:**
- Create: `public/` (répertoire)
- Copy: assets CSS + vendor Delivo → `public/assets/`

- [ ] **Créer la structure et copier les assets Delivo**

```bash
cd /Users/andrena/Documents/restaurent
DELIVO="delivo-food-delivery-pwa-mobile-html-template-2026-03-03-17-18-07-utc/Main-file"
mkdir -p public/assets/css public/assets/js public/assets/img
cp "$DELIVO/assets/css/styles.css" public/assets/css/styles.css
cp -r "$DELIVO/assets/vendor" public/assets/vendor
# Copier les images du projet (logo + plats)
cp logo.png public/assets/img/logo.png
cp "Boucané.png" public/assets/img/boucane.jpg 2>/dev/null || true
cp "Moule au curry avec frites maison.png" public/assets/img/moule-curry.jpg 2>/dev/null || true
cp "Roti et curry.png" public/assets/img/roti-curry.jpg 2>/dev/null || true
```

- [ ] **Créer `public/assets/css/prt.css`** — Variables P.R.T + overrides Delivo

```css
/* ===== PALETTE P.R.T ===== */
:root {
  --color-primary:    #CC0000;
  --color-accent:     #D4A017;
  --color-confirm:    #1A7A1A;
  --color-text:       #111111;
  --color-bg:         #F9F6F0;
  --color-band-1:     #111111;
  --color-band-2:     #D4A017;
  --color-band-3:     #FFFFFF;
  --color-band-4:     #1A7A1A;
}

/* Override couleurs Delivo → P.R.T */
body { background: var(--color-bg); font-family: 'Inter', sans-serif; }

/* Header rouge P.R.T */
.home-header,
.checkout-header,
.cart-header      { background: var(--color-primary) !important; }

/* Boutons CTA */
.btn-primary,
.add-to-cart-btn,
.pay-btn          { background: var(--color-primary) !important; border-color: var(--color-primary) !important; }

/* Accent or */
.price-tag,
.total-price,
.category-tab.active { color: var(--color-accent) !important; border-bottom-color: var(--color-accent) !important; }

/* ===== 4 BANDES SIGNATURE ===== */
.signature-bands {
  display: flex;
  height: 6px;
  width: 100%;
}
.signature-bands span:nth-child(1) { flex: 1; background: var(--color-band-1); }
.signature-bands span:nth-child(2) { flex: 1; background: var(--color-band-2); }
.signature-bands span:nth-child(3) { flex: 1; background: var(--color-band-3); }
.signature-bands span:nth-child(4) { flex: 1; background: var(--color-band-4); }

/* ===== TOGGLE PICK & COLLECT / LIVRAISON ===== */
.mode-toggle {
  display: flex;
  background: rgba(255,255,255,0.15);
  border-radius: 30px;
  padding: 4px;
  margin: 0 16px 12px;
}
.mode-toggle button {
  flex: 1;
  border: none;
  background: transparent;
  color: rgba(255,255,255,0.7);
  border-radius: 26px;
  padding: 8px;
  font-weight: 600;
  font-size: 13px;
  transition: all .2s;
}
.mode-toggle button.active {
  background: white;
  color: var(--color-primary);
}

/* ===== BANNIÈRE PROMO ===== */
.promo-banner {
  background: var(--color-accent);
  color: #111;
  text-align: center;
  padding: 8px 16px;
  font-weight: 700;
  font-size: 13px;
}

/* ===== ONGLETS CATÉGORIES ===== */
.category-tabs {
  display: flex;
  overflow-x: auto;
  gap: 8px;
  padding: 12px 16px;
  background: white;
  border-bottom: 1px solid #eee;
  scrollbar-width: none;
}
.category-tabs::-webkit-scrollbar { display: none; }
.category-tab {
  flex-shrink: 0;
  border: none;
  background: #f5f5f5;
  border-radius: 20px;
  padding: 8px 16px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all .2s;
  color: var(--color-text);
}
.category-tab.active {
  background: var(--color-primary);
  color: white;
}

/* ===== GRILLE PLATS ===== */
.products-grid {
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.product-card {
  background: white;
  border-radius: 14px;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
  cursor: pointer;
}
.product-card img {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: 10px;
  flex-shrink: 0;
}
.product-info { flex: 1; }
.product-name { font-weight: 700; font-size: 15px; color: var(--color-text); margin: 0 0 4px; }
.product-desc { font-size: 12px; color: #888; margin: 0 0 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.product-price { font-weight: 800; font-size: 16px; color: var(--color-primary); }
.product-add-btn {
  width: 34px; height: 34px;
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: 50%;
  font-size: 22px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  cursor: pointer;
}

/* ===== MODAL FICHE PLAT ===== */
.product-modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 1000;
  display: flex; align-items: flex-end;
}
.product-modal {
  background: white;
  border-radius: 24px 24px 0 0;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  padding: 0 0 32px;
}
.product-modal-img { width: 100%; height: 220px; object-fit: cover; }
.product-modal-body { padding: 20px 16px; }
.product-modal-name { font-size: 22px; font-weight: 800; margin: 0 0 8px; }
.product-modal-desc { color: #666; font-size: 14px; margin: 0 0 16px; }

/* Options groupes */
.option-group { margin-bottom: 16px; }
.option-group-title { font-weight: 700; font-size: 14px; margin-bottom: 8px; color: #333; }
.option-item {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 14px;
  border: 2px solid #eee;
  border-radius: 10px;
  margin-bottom: 6px;
  cursor: pointer;
  transition: all .15s;
}
.option-item.selected { border-color: var(--color-primary); background: #fff5f5; }
.option-item-label { font-size: 14px; font-weight: 600; }
.option-item-price { font-size: 13px; color: var(--color-primary); font-weight: 700; }

/* Sélecteur quantité */
.qty-selector {
  display: flex; align-items: center; gap: 16px;
  justify-content: center;
  margin: 16px 0;
}
.qty-btn {
  width: 36px; height: 36px;
  border: 2px solid var(--color-primary);
  border-radius: 50%;
  background: white;
  color: var(--color-primary);
  font-size: 20px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  font-weight: 700;
}
.qty-value { font-size: 20px; font-weight: 800; min-width: 24px; text-align: center; }

/* ===== BARRE FLOTTANTE PANIER ===== */
.cart-bar {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  background: var(--color-primary);
  color: white;
  padding: 14px 20px;
  display: flex; align-items: center; justify-content: space-between;
  cursor: pointer;
  z-index: 900;
  box-shadow: 0 -4px 20px rgba(0,0,0,.15);
}
.cart-bar-count {
  background: var(--color-accent);
  color: #111;
  border-radius: 20px;
  padding: 4px 12px;
  font-weight: 800;
  font-size: 14px;
}
.cart-bar-label { font-weight: 700; font-size: 15px; }
.cart-bar-total { font-weight: 800; font-size: 18px; }

/* ===== DRAWER PANIER ===== */
.cart-drawer-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 950;
  display: flex; align-items: flex-end;
}
.cart-drawer {
  background: white;
  border-radius: 24px 24px 0 0;
  width: 100%;
  max-height: 85vh;
  overflow-y: auto;
  padding: 0 0 100px;
}
.cart-drawer-handle {
  width: 40px; height: 4px;
  background: #ddd; border-radius: 2px;
  margin: 12px auto 16px;
}
.cart-drawer-header {
  padding: 0 16px 12px;
  border-bottom: 1px solid #eee;
  font-size: 18px; font-weight: 800;
}
.cart-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid #f5f5f5;
}
.cart-item-name { font-weight: 700; font-size: 14px; }
.cart-item-options { font-size: 12px; color: #888; }
.cart-item-price { font-weight: 800; color: var(--color-primary); }
.cart-item-qty {
  display: flex; align-items: center; gap: 8px; margin-left: auto;
}
.cart-item-qty button {
  width: 28px; height: 28px;
  border: 1px solid #ddd; border-radius: 50%;
  background: white; cursor: pointer; font-size: 16px;
}
.cart-total-section {
  padding: 16px;
  border-top: 2px solid #eee;
}
.cart-total-row {
  display: flex; justify-content: space-between;
  padding: 4px 0; font-size: 14px; color: #555;
}
.cart-total-row.grand { font-weight: 800; font-size: 18px; color: var(--color-text); }
.checkout-btn {
  width: 100%;
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: 14px;
  padding: 16px;
  font-size: 16px;
  font-weight: 800;
  margin-top: 12px;
  cursor: pointer;
}

/* ===== MODAL CHECKOUT ===== */
.checkout-modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.6);
  z-index: 980;
  display: flex; align-items: flex-end;
}
.checkout-modal {
  background: var(--color-bg);
  border-radius: 24px 24px 0 0;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  padding: 0 0 40px;
}
.checkout-modal-header {
  background: var(--color-primary);
  color: white;
  padding: 20px 16px 16px;
  border-radius: 24px 24px 0 0;
  display: flex; align-items: center; gap: 12px;
}
.checkout-modal-header h2 { margin: 0; font-size: 18px; font-weight: 800; flex: 1; }
.checkout-modal-back { background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
.checkout-steps { padding: 20px 16px; }
.step-indicator {
  display: flex; gap: 8px; margin-bottom: 20px;
}
.step-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #ddd;
}
.step-dot.active { background: var(--color-primary); width: 24px; border-radius: 4px; }
.checkout-input {
  width: 100%;
  border: 2px solid #e0e0e0;
  border-radius: 12px;
  padding: 14px 16px;
  font-size: 16px;
  background: white;
  margin-bottom: 12px;
  box-sizing: border-box;
}
.checkout-input:focus { border-color: var(--color-primary); outline: none; }
.checkout-label { font-weight: 700; font-size: 13px; color: #555; margin-bottom: 6px; display: block; text-transform: uppercase; }
.checkout-next-btn {
  width: 100%;
  background: var(--color-primary);
  color: white;
  border: none;
  border-radius: 14px;
  padding: 16px;
  font-size: 16px;
  font-weight: 800;
  cursor: pointer;
  margin-top: 8px;
}
.checkout-next-btn:disabled { background: #ccc; cursor: not-allowed; }

/* Stripe Element */
#stripe-card-element {
  border: 2px solid #e0e0e0;
  border-radius: 12px;
  padding: 14px 16px;
  background: white;
  margin-bottom: 12px;
}

/* ===== PAGE SUIVI ===== */
.track-page { padding: 20px 16px; max-width: 480px; margin: 0 auto; }
.track-header {
  background: var(--color-primary);
  color: white;
  padding: 20px 16px;
  text-align: center;
}
.track-header h1 { margin: 0; font-size: 20px; }
.track-order-num { font-size: 13px; opacity: .8; }
.timeline { margin: 24px 0; }
.timeline-step {
  display: flex; gap: 16px; align-items: flex-start;
  margin-bottom: 20px;
}
.timeline-dot {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: #ddd;
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
}
.timeline-dot.done { background: var(--color-confirm); }
.timeline-dot.active { background: var(--color-primary); animation: pulse 1.5s infinite; }
@keyframes pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(204,0,0,.4); } 50% { box-shadow: 0 0 0 8px rgba(204,0,0,0); } }
.timeline-line { width: 2px; background: #ddd; flex-grow: 1; min-height: 20px; margin-left: 15px; margin-top: -10px; margin-bottom: -10px; }
.timeline-line.done { background: var(--color-confirm); }
.timeline-label { font-weight: 700; font-size: 15px; }
.timeline-sub { font-size: 12px; color: #888; }

/* ===== COMPTE CLIENT ===== */
.account-page { padding: 20px 16px; max-width: 480px; margin: 0 auto; }
.account-form { background: white; border-radius: 16px; padding: 20px; margin-top: 16px; }

/* Utilities */
[x-cloak] { display: none !important; }
```

- [ ] **Commit**

```bash
cd /Users/andrena/Documents/restaurent
git add public/
git commit -m "feat: setup public/ directory with Delivo assets and PRT CSS"
```

---

## Task 2 : api.js + chargement thème dynamique

**Files:**
- Create: `public/assets/js/api.js`

- [ ] **Créer `public/assets/js/api.js`**

```js
const BASE = '/api';

async function apiFetch(path, options = {}) {
  const token = localStorage.getItem('auth_token');
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers['Authorization'] = 'Bearer ' + token;
  const res = await fetch(BASE + path, { ...options, headers });
  const data = await res.json();
  if (!data.success) throw new Error(data.error || 'Erreur API');
  return data.data;
}

const api = {
  getSettings:   () => apiFetch('/settings'),
  getCategories: () => apiFetch('/categories'),
  getProducts:   () => apiFetch('/products'),
  getOrder:      (token) => apiFetch('/orders/' + token),
  register:      (body) => apiFetch('/auth/register', { method: 'POST', body: JSON.stringify(body) }),
  login:         (body) => apiFetch('/auth/login',    { method: 'POST', body: JSON.stringify(body) }),
  createOrder:   (body) => apiFetch('/orders',        { method: 'POST', body: JSON.stringify(body) }),
  createIntent:  (body) => apiFetch('/payment/create-intent', { method: 'POST', body: JSON.stringify(body) }),
};
```

- [ ] **Vérifier que l'API répond** (serveur PHP doit être lancé sur port 8080)

```bash
# Vérifier que le serveur PHP est actif
curl -s http://localhost:8080/api/settings | php -r "echo json_decode(file_get_contents('php://stdin'))->success ? 'API OK' : 'API FAIL';"
# Expected: API OK
```

- [ ] **Commit**

```bash
git add public/assets/js/api.js
git commit -m "feat: add api.js fetch helpers"
```

---

## Task 3 : index.html — Shell + Menu principal

**Files:**
- Create: `public/index.html`
- Create: `public/assets/js/app.js` (Alpine.store panier)

- [ ] **Créer `public/assets/js/app.js`** — Store panier Alpine

```js
document.addEventListener('alpine:init', () => {

  // ===== STORE PANIER =====
  Alpine.store('cart', {
    items: [],  // [{id, name, price, qty, options, unitTotal}]

    get count() {
      return this.items.reduce((s, i) => s + i.qty, 0);
    },
    get subtotal() {
      return this.items.reduce((s, i) => s + i.unitTotal * i.qty, 0);
    },
    get total() {
      return this.subtotal + (window._deliveryFee || 0);
    },

    addItem(product, options, qty) {
      const key = product.id + '-' + JSON.stringify(options);
      const optExtra = options.reduce((s, o) => s + (parseFloat(o.extra_price) || 0), 0);
      const unitTotal = parseFloat(product.price) + optExtra;
      const existing = this.items.find(i => i.key === key);
      if (existing) {
        existing.qty += qty;
      } else {
        this.items.push({ key, id: product.id, name: product.name, price: product.price, qty, options, unitTotal });
      }
    },

    updateQty(key, delta) {
      const item = this.items.find(i => i.key === key);
      if (!item) return;
      item.qty = Math.max(0, item.qty + delta);
      if (item.qty === 0) this.items = this.items.filter(i => i.key !== key);
    },

    clear() { this.items = []; }
  });

});
```

- [ ] **Créer `public/index.html`** — SPA principale

```html
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#CC0000">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>P.R.T — Restaurant</title>
  <link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="stylesheet" href="/assets/css/prt.css">
</head>
<body x-data="appData()" x-init="init()" x-cloak>

  <!-- ===== HEADER ===== -->
  <div class="home-header" :style="'background:' + settings.color_primary">
    <div style="padding:16px 16px 8px; display:flex; align-items:center; justify-content:space-between;">
      <img :src="settings.logo_url || '/assets/img/logo.png'" alt="Logo" style="height:44px; object-fit:contain;">
      <a href="/account.html" style="color:white; text-decoration:none; font-size:13px; font-weight:600;">
        <span x-text="authUser ? authUser.name || 'Mon compte' : 'Connexion'"></span>
      </a>
    </div>

    <!-- Toggle Pick & Collect / Livraison -->
    <div class="mode-toggle">
      <button :class="mode==='pickup' ? 'active' : ''" @click="mode='pickup'; window._deliveryFee=0">
        🛍 Pick &amp; Collect
      </button>
      <button :class="mode==='delivery' ? 'active' : ''" @click="mode='delivery'">
        🛵 Livraison
      </button>
    </div>
  </div>

  <!-- 4 bandes signature -->
  <div class="signature-bands">
    <span :style="'background:'+settings.color_band_1"></span>
    <span :style="'background:'+settings.color_band_2"></span>
    <span :style="'background:'+settings.color_band_3"></span>
    <span :style="'background:'+settings.color_band_4"></span>
  </div>

  <!-- Bannière promo -->
  <div class="promo-banner" x-show="settings.promo_banner" x-text="settings.promo_banner"
       :style="'background:'+settings.color_accent"></div>

  <!-- ===== ONGLETS CATÉGORIES ===== -->
  <div class="category-tabs">
    <button class="category-tab" :class="activeCategory===null ? 'active' : ''"
            @click="activeCategory=null">🍽️ Tout</button>
    <template x-for="cat in categories" :key="cat.id">
      <button class="category-tab" :class="activeCategory===cat.id ? 'active' : ''"
              @click="activeCategory=cat.id">
        <span x-text="(cat.emoji || '') + ' ' + cat.name"></span>
      </button>
    </template>
  </div>

  <!-- ===== LISTE PLATS ===== -->
  <div class="products-grid" style="padding-bottom:80px;">
    <template x-if="loading">
      <p style="text-align:center; color:#888; padding:40px;">Chargement du menu…</p>
    </template>
    <template x-for="product in filteredProducts" :key="product.id">
      <div class="product-card" @click="openProduct(product)">
        <img :src="product.image_url || '/assets/img/logo.png'" :alt="product.name"
             onerror="this.src='/assets/img/logo.png'">
        <div class="product-info">
          <p class="product-name" x-text="product.name"></p>
          <p class="product-desc" x-text="product.description"></p>
          <p class="product-price" x-text="parseFloat(product.price).toFixed(2) + ' €'"></p>
        </div>
        <button class="product-add-btn" @click.stop="quickAdd(product)">+</button>
      </div>
    </template>
  </div>

  <!-- ===== BARRE FLOTTANTE PANIER ===== -->
  <div class="cart-bar" x-show="$store.cart.count > 0" @click="cartOpen=true" x-transition>
    <span class="cart-bar-count" x-text="$store.cart.count + ' article' + ($store.cart.count>1?'s':'')"></span>
    <span class="cart-bar-label">Voir le panier</span>
    <span class="cart-bar-total" x-text="$store.cart.subtotal.toFixed(2) + ' €'"></span>
  </div>

  <!-- ===== DRAWER PANIER ===== -->
  <div class="cart-drawer-overlay" x-show="cartOpen" @click.self="cartOpen=false" x-transition x-cloak>
    <div class="cart-drawer">
      <div class="cart-drawer-handle"></div>
      <div class="cart-drawer-header">Votre panier</div>

      <template x-for="item in $store.cart.items" :key="item.key">
        <div class="cart-item">
          <div style="flex:1;">
            <div class="cart-item-name" x-text="item.name"></div>
            <div class="cart-item-options" x-text="item.options.map(o=>o.option_name).join(', ')"></div>
            <div class="cart-item-price" x-text="(item.unitTotal * item.qty).toFixed(2) + ' €'"></div>
          </div>
          <div class="cart-item-qty">
            <button @click="$store.cart.updateQty(item.key, -1)">−</button>
            <span x-text="item.qty"></span>
            <button @click="$store.cart.updateQty(item.key, +1)">+</button>
          </div>
        </div>
      </template>

      <div class="cart-total-section">
        <div class="cart-total-row">
          <span>Sous-total</span>
          <span x-text="$store.cart.subtotal.toFixed(2) + ' €'"></span>
        </div>
        <div class="cart-total-row" x-show="mode==='delivery'">
          <span>Livraison</span>
          <span x-text="deliveryFeeLabel"></span>
        </div>
        <div class="cart-total-row grand">
          <span>Total</span>
          <span x-text="$store.cart.total.toFixed(2) + ' €'"></span>
        </div>
        <button class="checkout-btn" @click="cartOpen=false; checkoutOpen=true">
          Commander →
        </button>
      </div>
    </div>
  </div>

  <!-- ===== MODAL FICHE PLAT ===== -->
  <div class="product-modal-overlay" x-show="selectedProduct" @click.self="selectedProduct=null" x-cloak>
    <div class="product-modal" x-show="selectedProduct">
      <img class="product-modal-img"
           :src="selectedProduct?.image_url || '/assets/img/logo.png'"
           :alt="selectedProduct?.name"
           onerror="this.src='/assets/img/logo.png'">
      <div class="product-modal-body">
        <h2 class="product-modal-name" x-text="selectedProduct?.name"></h2>
        <p class="product-modal-desc" x-text="selectedProduct?.description"></p>

        <!-- Groupes d'options -->
        <template x-for="group in optionGroups" :key="group.name">
          <div class="option-group">
            <div class="option-group-title" x-text="group.name"></div>
            <template x-for="opt in group.options" :key="opt.id">
              <div class="option-item"
                   :class="isOptionSelected(opt) ? 'selected' : ''"
                   @click="toggleOption(group, opt)">
                <span class="option-item-label" x-text="opt.option_name"></span>
                <span class="option-item-price" x-show="opt.extra_price > 0"
                      x-text="'+' + parseFloat(opt.extra_price).toFixed(2) + ' €'"></span>
              </div>
            </template>
          </div>
        </template>

        <!-- Quantité -->
        <div class="qty-selector">
          <button class="qty-btn" @click="modalQty = Math.max(1, modalQty-1)">−</button>
          <span class="qty-value" x-text="modalQty"></span>
          <button class="qty-btn" @click="modalQty++">+</button>
        </div>

        <!-- Bouton ajouter -->
        <button class="checkout-btn" @click="addToCart()">
          Ajouter au panier —
          <span x-text="modalTotal.toFixed(2) + ' €'"></span>
        </button>
      </div>
    </div>
  </div>

  <!-- ===== MODAL CHECKOUT ===== -->
  <div class="checkout-modal-overlay" x-show="checkoutOpen" x-cloak>
    <div class="checkout-modal">
      <div class="checkout-modal-header" :style="'background:'+settings.color_primary">
        <button class="checkout-modal-back" @click="checkoutStep > 1 ? checkoutStep-- : checkoutOpen=false">←</button>
        <h2 x-text="mode==='pickup' ? 'Pick &amp; Collect' : 'Livraison'"></h2>
        <div class="step-indicator">
          <template x-for="n in (mode==='pickup' ? 2 : 3)" :key="n">
            <div class="step-dot" :class="n===checkoutStep ? 'active' : (n<checkoutStep ? 'done' : '')"></div>
          </template>
        </div>
      </div>

      <div class="checkout-steps">

        <!-- Étape 1 : Téléphone -->
        <div x-show="checkoutStep===1">
          <label class="checkout-label">Votre numéro de téléphone</label>
          <input class="checkout-input" type="tel" placeholder="+33 6 00 00 00 00"
                 x-model="checkoutPhone">
          <button class="checkout-next-btn"
                  :disabled="checkoutPhone.length < 8"
                  @click="checkoutStep=2">
            Continuer →
          </button>
        </div>

        <!-- Étape 2 : Adresse (livraison uniquement) -->
        <div x-show="checkoutStep===2 && mode==='delivery'">
          <label class="checkout-label">Adresse de livraison</label>
          <input class="checkout-input" type="text" placeholder="Numéro et nom de rue"
                 x-model="checkoutAddress">
          <input class="checkout-input" type="text" placeholder="Ville"
                 x-model="checkoutCity">
          <button class="checkout-next-btn"
                  :disabled="!checkoutAddress || !checkoutCity"
                  @click="checkoutStep=3">
            Continuer →
          </button>
        </div>

        <!-- Étape finale : Résumé + Stripe -->
        <div x-show="checkoutStep === (mode==='pickup' ? 2 : 3)">
          <div style="margin-bottom:16px;">
            <div style="font-weight:800; font-size:16px; margin-bottom:8px;">Récapitulatif</div>
            <template x-for="item in $store.cart.items" :key="item.key">
              <div style="display:flex; justify-content:space-between; font-size:14px; padding:4px 0;">
                <span x-text="item.qty + '× ' + item.name"></span>
                <span x-text="(item.unitTotal * item.qty).toFixed(2) + ' €'"></span>
              </div>
            </template>
            <div style="display:flex; justify-content:space-between; font-weight:800; font-size:16px; padding-top:8px; border-top:1px solid #eee; margin-top:8px;">
              <span>Total</span>
              <span x-text="$store.cart.total.toFixed(2) + ' €'"></span>
            </div>
          </div>

          <!-- Widget Stripe -->
          <div id="stripe-card-element"></div>
          <div id="stripe-error" style="color:#CC0000; font-size:13px; margin-bottom:8px;" x-text="stripeError"></div>

          <button class="checkout-next-btn" :disabled="payLoading" @click="submitPayment()">
            <span x-show="!payLoading">Payer <span x-text="$store.cart.total.toFixed(2) + ' €'"></span></span>
            <span x-show="payLoading">Traitement…</span>
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- ===== POPUP POST-PAIEMENT : prénom ===== -->
  <div style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;display:flex;align-items:center;justify-content:center;"
       x-show="postPaymentOpen" x-cloak>
    <div style="background:white;border-radius:20px;padding:24px;max-width:320px;width:90%;text-align:center;">
      <div style="font-size:40px;margin-bottom:12px;">🎉</div>
      <h3 style="margin:0 0 8px;font-size:20px;">Commande confirmée !</h3>
      <p style="color:#666;font-size:14px;margin-bottom:16px;">Un SMS de confirmation va vous être envoyé.</p>
      <label class="checkout-label">Votre prénom (facultatif)</label>
      <input class="checkout-input" type="text" placeholder="Ex: Marie" x-model="customerName">
      <button class="checkout-next-btn" @click="finalizeOrder()">
        Suivre ma commande →
      </button>
    </div>
  </div>

  <script src="/assets/js/api.js"></script>
  <script src="/assets/js/app.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

  <script>
    function appData() {
      return {
        settings:      { color_primary:'#CC0000', color_accent:'#D4A017', color_band_1:'#111', color_band_2:'#D4A017', color_band_3:'#fff', color_band_4:'#1A7A1A', promo_banner:'' },
        categories:    [],
        products:      [],
        activeCategory: null,
        mode:          'pickup',
        loading:       true,
        authUser:      null,

        // Panier UI
        cartOpen:      false,

        // Fiche plat
        selectedProduct: null,
        optionGroups:  [],
        selectedOptions: [],
        modalQty:      1,

        // Checkout
        checkoutOpen:  false,
        checkoutStep:  1,
        checkoutPhone: '',
        checkoutAddress: '',
        checkoutCity:  '',
        deliveryFeeLabel: 'Calculé à la prochaine étape',

        // Stripe
        stripe:        null,
        stripeCard:    null,
        stripeError:   '',
        payLoading:    false,
        pendingOrderToken: null,

        // Post paiement
        postPaymentOpen: false,
        customerName:  '',

        get filteredProducts() {
          const list = this.products.filter(p => p.is_available == 1);
          if (!this.activeCategory) return list;
          return list.filter(p => p.category_id == this.activeCategory);
        },

        get modalTotal() {
          if (!this.selectedProduct) return 0;
          const extra = this.selectedOptions.reduce((s, o) => s + parseFloat(o.extra_price || 0), 0);
          return (parseFloat(this.selectedProduct.price) + extra) * this.modalQty;
        },

        async init() {
          // Charger settings + menu en parallèle
          const [settings, categories, products] = await Promise.all([
            api.getSettings().catch(() => ({})),
            api.getCategories().catch(() => []),
            api.getProducts().catch(() => []),
          ]);

          // Appliquer thème dynamique via CSS vars
          if (settings.color_primary) {
            Object.assign(this.settings, settings);
            document.documentElement.style.setProperty('--color-primary', settings.color_primary);
            document.documentElement.style.setProperty('--color-accent',  settings.color_accent);
            document.documentElement.style.setProperty('--color-band-1',  settings.color_band_1);
            document.documentElement.style.setProperty('--color-band-2',  settings.color_band_2);
            document.documentElement.style.setProperty('--color-band-3',  settings.color_band_3);
            document.documentElement.style.setProperty('--color-band-4',  settings.color_band_4);
            document.querySelector('meta[name=theme-color]').content = settings.color_primary;
          }

          this.categories = categories.filter(c => c.is_active == 1);
          this.products    = products;
          this.loading     = false;

          // Charger l'utilisateur connecté (si token en localStorage)
          const token = localStorage.getItem('auth_token');
          if (token) {
            const userData = JSON.parse(localStorage.getItem('auth_user') || 'null');
            this.authUser = userData;
            if (userData?.phone) this.checkoutPhone = userData.phone;
            if (userData?.default_address) this.checkoutAddress = userData.default_address;
          }

          // Init Stripe (clé publique depuis settings)
          if (settings.stripe_pk_public) {
            this.stripe = Stripe(settings.stripe_pk_public);
          }

          // PWA Service Worker
          if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
          }
        },

        // ===== FICHE PLAT =====
        async openProduct(product) {
          this.selectedProduct = product;
          this.modalQty = 1;
          this.selectedOptions = [];
          // Charger les options du produit depuis les données déjà chargées
          const allOpts = this.products.flatMap(p => p.options || []);
          // Les options sont dans product.options si l'API les inclut
          const opts = product.options || [];
          // Grouper par group_name
          const groups = {};
          opts.forEach(o => {
            if (!groups[o.group_name]) groups[o.group_name] = { name: o.group_name, options: [] };
            groups[o.group_name].options.push(o);
            if (o.is_default == 1) this.selectedOptions.push(o);
          });
          this.optionGroups = Object.values(groups);
        },

        isOptionSelected(opt) {
          return this.selectedOptions.some(o => o.id === opt.id);
        },

        toggleOption(group, opt) {
          // Si groupe radio-like (Riz) : remplacer la sélection dans ce groupe
          // Si groupe multiple (Sauce) : toggle
          const inGroup = this.selectedOptions.filter(o => group.options.some(go => go.id === o.id));
          if (inGroup.some(o => o.id === opt.id)) {
            // Désélectionner
            this.selectedOptions = this.selectedOptions.filter(o => o.id !== opt.id);
          } else {
            // Pour Riz (groupe avec is_default), comportement radio
            if (group.options.some(o => o.is_default == 1)) {
              this.selectedOptions = this.selectedOptions.filter(o => !group.options.some(go => go.id === o.id));
            }
            this.selectedOptions.push(opt);
          }
        },

        quickAdd(product) {
          this.$store.cart.addItem(product, [], 1);
        },

        addToCart() {
          this.$store.cart.addItem(this.selectedProduct, this.selectedOptions, this.modalQty);
          this.selectedProduct = null;
          this.cartOpen = true;
        },

        // ===== CHECKOUT STRIPE =====
        async submitPayment() {
          if (!this.stripe) {
            this.stripeError = 'Stripe non configuré. Contactez le restaurant.';
            return;
          }
          this.payLoading = true;
          this.stripeError = '';

          try {
            const address = this.mode === 'delivery'
              ? this.checkoutAddress + ', ' + this.checkoutCity
              : null;

            // 1. Créer le PaymentIntent côté serveur
            const result = await api.createIntent({
              items: this.$store.cart.items,
              phone: this.checkoutPhone,
              type:  this.mode === 'pickup' ? 'pickup' : 'delivery',
              delivery_address: address,
            });

            this.pendingOrderToken = result.order_token;

            // 2. Confirmer le paiement avec Stripe.js
            if (!this.stripeCard) {
              const elements = this.stripe.elements();
              this.stripeCard = elements.create('card', {
                style: { base: { fontSize: '16px', color: '#111' } }
              });
              this.stripeCard.mount('#stripe-card-element');
            }

            const { error, paymentIntent } = await this.stripe.confirmCardPayment(
              result.client_secret,
              { payment_method: { card: this.stripeCard } }
            );

            if (error) {
              this.stripeError = error.message;
            } else if (paymentIntent.status === 'succeeded') {
              this.checkoutOpen = false;
              this.postPaymentOpen = true;
            }
          } catch (err) {
            this.stripeError = err.message;
          } finally {
            this.payLoading = false;
          }
        },

        async finalizeOrder() {
          // Enregistrer le prénom si fourni (PATCH via API)
          // Puis rediriger vers la page de suivi
          if (this.customerName && this.pendingOrderToken) {
            // On ne bloque pas si ça fail
            fetch('/api/orders/' + this.pendingOrderToken + '/name', {
              method: 'PATCH',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ customer_name: this.customerName })
            }).catch(() => {});
          }
          this.$store.cart.clear();
          this.postPaymentOpen = false;
          window.location.href = '/track.html?token=' + this.pendingOrderToken;
        },
      };
    }
  </script>
  <script src="https://js.stripe.com/v3/"></script>
</body>
</html>
```

- [ ] **Tester dans le navigateur**

```bash
# Ouvrir http://localhost:8080/ dans le navigateur
# Vérifier :
# 1. Le menu se charge (catégories + plats)
# 2. Les couleurs PRT sont appliquées (rouge header, 4 bandes)
# 3. La bannière promo s'affiche si configurée
open http://localhost:8080/
```

- [ ] **Commit**

```bash
git add public/index.html public/assets/js/app.js
git commit -m "feat: add main SPA with Alpine.js menu, cart bar, product modal"
```

---

## Task 4 : Backend Stripe — POST /api/payment/create-intent

**Files:**
- Create: `api/routes/payment.php`
- Modify: `api/index.php` — ajouter dispatch `/payment`
- Create: `tests/PaymentTest.php`

- [ ] **Ajouter le dispatch dans `api/index.php`** — avant la ligne `admin`:

Lire `api/index.php` et trouver la ligne `elseif (str_starts_with($uri, '/admin'))`, ajouter avant :

```php
elseif ($uri === '/payment/create-intent' && $method === 'POST') {
    require __DIR__ . '/routes/payment.php';
}
```

- [ ] **Créer `api/routes/payment.php`**

```php
<?php
// POST /api/payment/create-intent
// Crée un PaymentIntent Stripe + une commande en statut 'pending'
// Retourne {client_secret, order_token}

validate_required($body, ['items', 'phone', 'type']);

if (!is_array($body['items']) || count($body['items']) === 0) {
    json_error('Panier vide', 422);
}

$valid_types = ['pickup', 'delivery'];
if (!in_array($body['type'], $valid_types)) {
    json_error('Type invalide', 422);
}
if ($body['type'] === 'delivery' && empty($body['delivery_address'])) {
    json_error('Adresse de livraison requise', 422);
}

// Calculer le total côté serveur (ne jamais faire confiance au client)
$total = 0;
$product_ids = array_unique(array_column($body['items'], 'id'));
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$stmt = db()->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND is_available=1");
$stmt->execute($product_ids);
$db_products = [];
foreach ($stmt->fetchAll() as $p) $db_products[$p['id']] = $p;

$line_items = [];
foreach ($body['items'] as $item) {
    $pid = (int) $item['id'];
    if (!isset($db_products[$pid])) json_error("Produit $pid introuvable ou indisponible", 422);
    $qty = max(1, (int) ($item['qty'] ?? 1));
    $unit_price = (float) $db_products[$pid]['price'];

    // Vérifier les options
    $options_validated = [];
    if (!empty($item['options'])) {
        $opt_ids = array_column($item['options'], 'id');
        if ($opt_ids) {
            $op = implode(',', array_fill(0, count($opt_ids), '?'));
            $ostmt = db()->prepare("SELECT * FROM product_options WHERE id IN ($op) AND product_id = ?");
            $ostmt->execute(array_merge($opt_ids, [$pid]));
            foreach ($ostmt->fetchAll() as $opt) {
                $unit_price += (float) $opt['extra_price'];
                $options_validated[] = $opt;
            }
        }
    }

    $total += $unit_price * $qty;
    $line_items[] = compact('pid', 'qty', 'unit_price', 'options_validated');
}

// Frais de livraison
$delivery_fee = 0;
if ($body['type'] === 'delivery') {
    $settings = db()->query('SELECT delivery_free_above FROM settings LIMIT 1')->fetch();
    $free_above = (float) ($settings['delivery_free_above'] ?? 0);
    // Frais fixe par défaut : premier tarif actif
    $fee_row = db()->query('SELECT fee FROM delivery_fees WHERE is_active=1 ORDER BY id LIMIT 1')->fetch();
    if ($fee_row) {
        $delivery_fee = ($free_above > 0 && $total >= $free_above) ? 0 : (float) $fee_row['fee'];
    }
    $total += $delivery_fee;
}

// Créer la commande en DB avec statut 'pending' (avant paiement)
$order_token = bin2hex(random_bytes(16));
$user_id = null;
$payload = auth_get_payload();
if ($payload) $user_id = $payload['uid'];

$stmt = db()->prepare(
    'INSERT INTO orders (user_id, phone, type, delivery_address, status, total, delivery_fee, tracking_token, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$stmt->execute([
    $user_id,
    $body['phone'],
    $body['type'],
    $body['delivery_address'] ?? null,
    'pending',
    round($total, 2),
    $delivery_fee,
    $order_token,
]);
$order_id = (int) db()->lastInsertId();

// Insérer les lignes de commande
foreach ($line_items as $li) {
    $stmt2 = db()->prepare(
        'INSERT INTO order_items (order_id, product_id, quantity, unit_price, options_json)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt2->execute([
        $order_id,
        $li['pid'],
        $li['qty'],
        $li['unit_price'],
        json_encode($li['options_validated']),
    ]);
}

// Appel API Stripe pour créer le PaymentIntent
$stripe_sk = env('STRIPE_SK');
if (!$stripe_sk) json_error('Stripe non configuré (STRIPE_SK manquant dans .env)', 503);

$amount_cents = (int) round($total * 100);

$ch = curl_init('https://api.stripe.com/v1/payment_intents');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_USERPWD        => $stripe_sk . ':',
    CURLOPT_POSTFIELDS     => http_build_query([
        'amount'   => $amount_cents,
        'currency' => 'eur',
        'metadata[order_id]'    => $order_id,
        'metadata[order_token]' => $order_token,
    ]),
]);
$stripe_response = curl_exec($ch);
$http_code       = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$stripe_data = json_decode($stripe_response, true);

if ($http_code !== 200 || empty($stripe_data['client_secret'])) {
    // Supprimer la commande pending si Stripe échoue
    db()->prepare('DELETE FROM order_items WHERE order_id=?')->execute([$order_id]);
    db()->prepare('DELETE FROM orders WHERE id=?')->execute([$order_id]);
    json_error('Erreur Stripe : ' . ($stripe_data['error']['message'] ?? 'inconnue'), 502);
}

// Stocker le payment_intent_id dans la commande
db()->prepare('UPDATE orders SET stripe_payment_id=? WHERE id=?')
    ->execute([$stripe_data['id'], $order_id]);

json_success([
    'client_secret' => $stripe_data['client_secret'],
    'order_token'   => $order_token,
    'total'         => round($total, 2),
    'delivery_fee'  => $delivery_fee,
]);
```

- [ ] **Vérifier que la colonne `tracking_token` existe dans `orders`** — si non, l'ajouter:

```bash
mysql -u andrena --default-character-set=utf8mb4 -e "SHOW COLUMNS FROM restaurant.orders LIKE 'tracking_token';"
# Si vide, ajouter la colonne :
mysql -u andrena --default-character-set=utf8mb4 -e "ALTER TABLE restaurant.orders ADD COLUMN tracking_token VARCHAR(64) UNIQUE AFTER stripe_payment_id;"
mysql -u andrena --default-character-set=utf8mb4 -e "ALTER TABLE restaurant.orders ADD COLUMN status ENUM('pending','received','in_preparation','ready','en_route','delivered','cancelled') NOT NULL DEFAULT 'received' AFTER tracking_token;" 2>/dev/null || true
# Mettre aussi à jour la DB de test
mysql -u andrena --default-character-set=utf8mb4 restaurant_test -e "ALTER TABLE orders ADD COLUMN tracking_token VARCHAR(64) UNIQUE AFTER stripe_payment_id;" 2>/dev/null || true
```

**Note:** Vérifier aussi dans `database/schema.sql` si `tracking_token` est déjà là. Si non, l'ajouter dans `orders` : `tracking_token VARCHAR(64) UNIQUE NULL` après `stripe_payment_id`.

- [ ] **Créer `tests/PaymentTest.php`**

```php
<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/../api/helpers/Response.php';
require_once __DIR__ . '/../api/helpers/Validator.php';
require_once __DIR__ . '/../api/config/env.php';

use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    private function insertCategory(): int {
        $pdo = db();
        $pdo->exec("INSERT INTO categories (name, sort_order, is_active) VALUES ('TestCat',1,1)");
        return (int) $pdo->lastInsertId();
    }

    private function insertProduct(int $cat_id): int {
        $pdo = db();
        $pdo->prepare("INSERT INTO products (category_id, name, price, is_available) VALUES (?,?,?,1)")
            ->execute([$cat_id, 'TestProduit', '12.50']);
        return (int) $pdo->lastInsertId();
    }

    public function test_create_intent_validates_empty_items(): void
    {
        $result = $this->callEndpoint([
            'items' => [],
            'phone' => '+33600000001',
            'type'  => 'pickup',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('vide', strtolower($result['error']));
    }

    public function test_create_intent_validates_type(): void
    {
        $cat_id  = $this->insertCategory();
        $prod_id = $this->insertProduct($cat_id);

        $result = $this->callEndpoint([
            'items' => [['id' => $prod_id, 'qty' => 1, 'options' => []]],
            'phone' => '+33600000002',
            'type'  => 'invalid_type',
        ]);
        $this->assertFalse($result['success']);
    }

    public function test_create_intent_requires_address_for_delivery(): void
    {
        $cat_id  = $this->insertCategory();
        $prod_id = $this->insertProduct($cat_id);

        $result = $this->callEndpoint([
            'items'            => [['id' => $prod_id, 'qty' => 1, 'options' => []]],
            'phone'            => '+33600000003',
            'type'             => 'delivery',
            'delivery_address' => '',
        ]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('adresse', strtolower($result['error']));
    }

    /**
     * Helper : simule l'appel à l'endpoint en chargeant le fichier PHP directement.
     * Ne teste pas Stripe (STRIPE_SK vide en test) — teste uniquement la validation.
     */
    private function callEndpoint(array $body): array
    {
        $GLOBALS['_pdo'] = null;
        // Définir les variables globales attendues par le routeur
        global $method, $uri;
        $method = 'POST';
        $uri    = '/payment/create-intent';

        $GLOBALS['_test_body'] = $body;

        ob_start();
        try {
            // Surcharge de json_success / json_error pour capturer sans exit
            // Note: On capture via output buffering + exception
            require __DIR__ . '/../api/routes/payment.php';
        } catch (\Throwable $e) {
            // json_error/json_success appellent exit — capturer le output
        }
        $output = ob_get_clean();
        return json_decode($output ?: '{"success":false,"error":"no output"}', true)
            ?? ['success' => false, 'error' => 'parse error'];
    }
}
```

**Note sur les tests PaymentTest:** Les tests de validation s'exécutent sans appel réel à Stripe (STRIPE_SK='' en environnement de test). Les chemins d'erreur (validation items vide, type invalide, adresse manquante) se déclenchent avant l'appel Stripe — ce sont eux qui sont testés.

- [ ] **Lancer les tests (sans Stripe)**

```bash
cd /Users/andrena/Documents/restaurent
./vendor/bin/phpunit tests/PaymentTest.php --bootstrap tests/bootstrap.php -v
```

Expected : 3 tests, 3+ assertions, tous verts (les cas d'erreur de validation ne font pas d'appel Stripe).

- [ ] **Commit**

```bash
git add api/routes/payment.php api/index.php tests/PaymentTest.php
git commit -m "feat: add POST /api/payment/create-intent endpoint"
```

---

## Task 5 : Page suivi commande — track.html

**Files:**
- Create: `public/track.html`

- [ ] **Créer `public/track.html`**

```html
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#CC0000">
  <title>Suivi commande — P.R.T</title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="stylesheet" href="/assets/css/prt.css">
</head>
<body x-data="trackData()" x-init="init()">

  <div class="track-header">
    <h1>Suivi de commande</h1>
    <div class="track-order-num" x-show="order" x-text="'Commande #' + order?.id"></div>
  </div>

  <!-- 4 bandes signature -->
  <div class="signature-bands">
    <span style="background:#111"></span>
    <span style="background:#D4A017"></span>
    <span style="background:#fff;border:1px solid #eee"></span>
    <span style="background:#1A7A1A"></span>
  </div>

  <div class="track-page">

    <!-- Chargement -->
    <div x-show="loading" style="text-align:center;padding:40px;color:#888;">
      Chargement de votre commande…
    </div>

    <!-- Erreur -->
    <div x-show="!loading && !order" style="text-align:center;padding:40px;">
      <div style="font-size:48px;margin-bottom:12px;">😕</div>
      <p style="color:#888;">Commande introuvable.<br>Vérifiez le lien dans votre SMS.</p>
    </div>

    <!-- Timeline -->
    <div class="timeline" x-show="order">

      <template x-for="(step, index) in steps" :key="step.status">
        <div>
          <div class="timeline-step">
            <div class="timeline-dot"
                 :class="stepClass(step.status)"
                 x-text="stepIcon(step.status)">
            </div>
            <div>
              <div class="timeline-label" x-text="step.label"></div>
              <div class="timeline-sub" x-text="step.sub"></div>
            </div>
          </div>
          <div class="timeline-line" x-show="index < steps.length - 1"
               :class="isDone(step.status) ? 'done' : ''"></div>
        </div>
      </template>

    </div>

    <!-- Détail commande -->
    <div x-show="order" style="background:white;border-radius:14px;padding:16px;margin-top:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
      <div style="font-weight:800;font-size:16px;margin-bottom:12px;">Votre commande</div>
      <template x-for="item in (order?.items || [])" :key="item.id">
        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:14px;">
          <span x-text="item.quantity + '× ' + item.product_name"></span>
          <span style="color:#CC0000;font-weight:700;"
                x-text="(item.unit_price * item.quantity).toFixed(2) + ' €'"></span>
        </div>
      </template>
      <div style="border-top:1px solid #eee;margin-top:8px;padding-top:8px;display:flex;justify-content:space-between;font-weight:800;">
        <span>Total</span>
        <span x-text="parseFloat(order?.total || 0).toFixed(2) + ' €'"></span>
      </div>
    </div>

    <a href="/" style="display:block;text-align:center;margin-top:20px;color:#CC0000;font-weight:700;text-decoration:none;">
      ← Retour au menu
    </a>
  </div>

  <script src="/assets/js/api.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
  <script>
    function trackData() {
      return {
        order:   null,
        loading: true,

        get steps() {
          const type = this.order?.type;
          const base = [
            { status: 'received',       label: 'Commande reçue',      sub: 'Le restaurant a bien reçu votre commande.' },
            { status: 'in_preparation', label: 'En préparation',       sub: 'Votre commande est en cours de préparation.' },
            { status: 'ready',          label: type === 'delivery' ? 'Prête — en route' : 'Prête à récupérer', sub: type === 'delivery' ? 'Le livreur est en route.' : 'Venez récupérer votre commande.' },
          ];
          if (type === 'delivery') {
            base.push({ status: 'delivered', label: 'Livrée', sub: 'Bon appétit !' });
          } else {
            base.push({ status: 'delivered', label: 'Récupérée', sub: 'Bon appétit !' });
          }
          return base;
        },

        statusOrder: ['pending','received','in_preparation','ready','en_route','delivered'],

        isDone(status) {
          const current = this.order?.status || 'received';
          return this.statusOrder.indexOf(status) <= this.statusOrder.indexOf(current);
        },

        stepClass(status) {
          const current = this.order?.status || 'received';
          if (status === current) return 'active';
          if (this.isDone(status)) return 'done';
          return '';
        },

        stepIcon(status) {
          const icons = { received:'📋', in_preparation:'🍳', ready:'✅', en_route:'🛵', delivered:'🎉' };
          return icons[status] || '⭕';
        },

        async init() {
          const params = new URLSearchParams(location.search);
          const token = params.get('token');
          if (!token) { this.loading = false; return; }

          try {
            this.order = await api.getOrder(token);
          } catch (e) {
            this.order = null;
          } finally {
            this.loading = false;
          }

          // Polling toutes les 15 secondes si la commande n'est pas terminée
          if (this.order && !['delivered', 'cancelled'].includes(this.order.status)) {
            setInterval(async () => {
              try {
                this.order = await api.getOrder(token);
              } catch(e) {}
            }, 15000);
          }
        }
      };
    }
  </script>
</body>
</html>
```

- [ ] **Vérifier que `GET /api/orders/{token}` retourne les items avec le nom du produit**

Lire `api/routes/orders.php` et vérifier que la requête inclut `product_name`. Si ce n'est pas le cas, modifier la query pour faire un JOIN sur `products`:

```php
// Dans api/routes/orders.php, la route GET /api/orders/{token}
// La requête doit ressembler à :
$items_stmt = db()->prepare(
    'SELECT oi.*, p.name as product_name
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = ?'
);
```

- [ ] **Tester la page suivi**

```bash
# Créer une commande de test et récupérer son token
TOKEN_ORDER=$(mysql -u andrena --default-character-set=utf8mb4 -s -e "SELECT tracking_token FROM restaurant.orders LIMIT 1;")
echo "Token: $TOKEN_ORDER"
# Ouvrir: http://localhost:8080/track.html?token=$TOKEN_ORDER
open "http://localhost:8080/track.html?token=$TOKEN_ORDER"
```

- [ ] **Commit**

```bash
git add public/track.html
git commit -m "feat: add order tracking page with timeline"
```

---

## Task 6 : Endpoint PATCH /api/orders/{token}/name + mise à jour statut 'received'

**Files:**
- Modify: `api/routes/orders.php`
- Modify: `api/routes/payment.php` (webhook Stripe ou confirmation)

**Note sur le flux paiement Stripe :**
Actuellement, la commande est créée en statut `'pending'`. Quand le frontend confirme que le paiement a réussi (`paymentIntent.status === 'succeeded'`), il faudrait passer la commande en `'received'`. Le plus simple sans webhook est de créer un endpoint de confirmation.

- [ ] **Ajouter dans `api/routes/orders.php`** — deux nouveaux cas :

Lire le fichier `api/routes/orders.php` pour voir la structure existante, puis ajouter après la route GET existante :

```php
// PATCH /api/orders/{token}/name — enregistrer le prénom (facultatif, sans auth)
if ($method === 'PATCH' && preg_match('#/orders/([a-f0-9]+)/name$#', $uri, $m)) {
    $token = $m[1];
    $name = trim($body['customer_name'] ?? '');
    if ($name) {
        $stmt = db()->prepare('UPDATE orders SET customer_name=? WHERE tracking_token=?');
        $stmt->execute([$name, $token]);
    }
    json_success(['updated' => true]);
}

// PATCH /api/orders/{token}/confirm — confirmer le paiement (appelé par frontend après Stripe success)
if ($method === 'PATCH' && preg_match('#/orders/([a-f0-9]+)/confirm$#', $uri, $m)) {
    $token = $m[1];
    $stmt = db()->prepare(
        "UPDATE orders SET status='received' WHERE tracking_token=? AND status='pending'"
    );
    $stmt->execute([$token]);
    json_success(['confirmed' => true]);
}
```

- [ ] **Mettre à jour `finalizeOrder()` dans `public/index.html`** — appeler `/confirm` avant de rediriger :

Dans `public/index.html`, remplacer la méthode `finalizeOrder()` :

```js
async finalizeOrder() {
  if (this.pendingOrderToken) {
    // Confirmer la commande (passer de 'pending' à 'received')
    await fetch('/api/orders/' + this.pendingOrderToken + '/confirm', { method: 'PATCH' })
      .catch(() => {});
    // Enregistrer le prénom si fourni
    if (this.customerName) {
      await fetch('/api/orders/' + this.pendingOrderToken + '/name', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ customer_name: this.customerName })
      }).catch(() => {});
    }
  }
  this.$store.cart.clear();
  this.postPaymentOpen = false;
  window.location.href = '/track.html?token=' + this.pendingOrderToken;
},
```

- [ ] **Lancer tous les tests**

```bash
cd /Users/andrena/Documents/restaurent
./vendor/bin/phpunit tests/ --bootstrap tests/bootstrap.php -v
```

Expected : tous verts.

- [ ] **Commit**

```bash
git add api/routes/orders.php public/index.html
git commit -m "feat: add order name + confirm endpoints, finalize payment flow"
```

---

## Task 7 : PWA — manifest.json + Service Worker

**Files:**
- Create: `public/manifest.json`
- Create: `public/sw.js`

- [ ] **Créer `public/manifest.json`**

```json
{
  "name": "P.R.T — Restaurant",
  "short_name": "P.R.T",
  "description": "Commandez vos plats indiens et créoles en Pick & Collect ou Livraison",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#F9F6F0",
  "theme_color": "#CC0000",
  "orientation": "portrait",
  "icons": [
    {
      "src": "/assets/img/logo.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "/assets/img/logo.png",
      "sizes": "512x512",
      "type": "image/png"
    }
  ]
}
```

- [ ] **Créer `public/sw.js`** — Service Worker avec cache menu

```js
const CACHE_NAME = 'prt-v1';
const STATIC_ASSETS = [
  '/',
  '/assets/css/styles.css',
  '/assets/css/prt.css',
  '/assets/vendor/bootstrap/css/bootstrap.min.css',
  '/assets/js/api.js',
  '/assets/js/app.js',
  '/assets/img/logo.png',
];

// Installation : mise en cache des assets statiques
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .then(() => self.skipWaiting())
  );
});

// Activation : supprimer les anciens caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

// Fetch : cache-first pour assets statiques, network-first pour l'API
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);

  // API : toujours réseau (pas de cache)
  if (url.pathname.startsWith('/api')) return;

  // Assets statiques : cache-first
  event.respondWith(
    caches.match(event.request).then(cached => {
      if (cached) return cached;
      return fetch(event.request).then(response => {
        // Mettre en cache les nouvelles ressources statiques
        if (response.ok && event.request.method === 'GET') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
        }
        return response;
      });
    })
  );
});
```

- [ ] **Vérifier que le manifest et le SW sont servis** (le serveur PHP doit servir les fichiers statiques depuis public/)

Vérifier que `api/index.php` ou la config Apache gère les fichiers statiques depuis `public/`. Adapter si nécessaire.

Si le PHP built-in server (`php -S localhost:8080 api/index.php`) ne sert pas les fichiers de `public/`, lancer un serveur qui sert depuis la racine du projet :

```bash
# Depuis la racine du projet, le serveur doit servir public/ pour les fichiers statiques
# et router /api/* vers api/index.php
# Pour le développement, utiliser deux serveurs ou un router:
cd /Users/andrena/Documents/restaurent
php -S localhost:8080 -t public/ router.php &
```

Créer `router.php` à la racine:

```php
<?php
// router.php — serveur de développement
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Routes API → dispatcher vers api/index.php
if (str_starts_with($uri, '/api')) {
    // Ajuster le REQUEST_URI pour que api/index.php le reçoive correctement
    $_SERVER['REQUEST_URI'] = $uri;
    require __DIR__ . '/api/index.php';
    return;
}

// Fichiers statiques → servir depuis public/
$file = __DIR__ . '/public' . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // Servir le fichier statique directement
}

// Toutes les autres routes → index.html (SPA)
require __DIR__ . '/public/index.html';
```

- [ ] **Relancer le serveur avec le router**

```bash
# Arrêter l'ancien serveur PHP si lancé sur 8080
pkill -f "php -S localhost:8080" 2>/dev/null || true
sleep 1
cd /Users/andrena/Documents/restaurent
php -S localhost:8080 router.php &
sleep 1
# Vérifier que l'API fonctionne encore
curl -s http://localhost:8080/api/settings | php -r "echo json_decode(file_get_contents('php://stdin'))->success ? 'API OK' : 'API FAIL';"
# Vérifier que les fichiers statiques sont servis
curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/assets/css/prt.css
# Expected: 200
```

- [ ] **Commit**

```bash
git add public/manifest.json public/sw.js router.php
git commit -m "feat: add PWA manifest, Service Worker, and dev router"
```

---

## Task 8 : Compte client — account.html

**Files:**
- Create: `public/account.html`

- [ ] **Créer `public/account.html`**

```html
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="theme-color" content="#CC0000">
  <title>Mon compte — P.R.T</title>
  <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="stylesheet" href="/assets/css/prt.css">
</head>
<body x-data="accountData()" x-init="init()">

  <div class="track-header" style="background:#CC0000;">
    <div style="display:flex;align-items:center;gap:12px;">
      <a href="/" style="color:white;font-size:24px;text-decoration:none;">←</a>
      <h1 style="margin:0;font-size:18px;" x-text="authUser ? 'Mon compte' : 'Connexion'"></h1>
    </div>
  </div>

  <div class="signature-bands">
    <span style="background:#111"></span><span style="background:#D4A017"></span>
    <span style="background:#fff;border:1px solid #eee"></span><span style="background:#1A7A1A"></span>
  </div>

  <div class="account-page">

    <!-- Utilisateur connecté -->
    <div x-show="authUser">
      <div style="background:white;border-radius:16px;padding:20px;text-align:center;margin-bottom:16px;">
        <div style="font-size:48px;margin-bottom:8px;">👤</div>
        <div style="font-weight:800;font-size:18px;" x-text="authUser?.name || authUser?.phone"></div>
        <div style="color:#888;font-size:14px;" x-text="authUser?.phone"></div>
      </div>

      <div style="background:white;border-radius:16px;padding:16px;margin-bottom:16px;">
        <div style="font-weight:800;font-size:16px;margin-bottom:12px;">Mes dernières commandes</div>
        <template x-if="orders.length === 0">
          <p style="color:#888;font-size:14px;">Aucune commande pour l'instant.</p>
        </template>
        <template x-for="order in orders" :key="order.id">
          <a :href="'/track.html?token=' + order.tracking_token"
             style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #f5f5f5;text-decoration:none;color:inherit;">
            <div>
              <div style="font-weight:700;font-size:14px;" x-text="'Commande #' + order.id"></div>
              <div style="font-size:12px;color:#888;" x-text="order.created_at"></div>
            </div>
            <div style="text-align:right;">
              <div style="font-weight:800;color:#CC0000;" x-text="parseFloat(order.total).toFixed(2) + ' €'"></div>
              <div style="font-size:11px;color:#888;" x-text="order.status"></div>
            </div>
          </a>
        </template>
      </div>

      <button @click="logout()"
              style="width:100%;background:white;border:2px solid #CC0000;color:#CC0000;border-radius:14px;padding:14px;font-weight:800;cursor:pointer;">
        Se déconnecter
      </button>
    </div>

    <!-- Formulaire connexion/inscription -->
    <div x-show="!authUser">

      <!-- Tabs Connexion / Inscription -->
      <div style="display:flex;gap:0;margin-bottom:20px;background:#f0f0f0;border-radius:12px;padding:4px;">
        <button style="flex:1;padding:10px;border:none;border-radius:10px;font-weight:700;cursor:pointer;"
                :style="tab==='login' ? 'background:#CC0000;color:white;' : 'background:transparent;color:#555;'"
                @click="tab='login'">Connexion</button>
        <button style="flex:1;padding:10px;border:none;border-radius:10px;font-weight:700;cursor:pointer;"
                :style="tab==='register' ? 'background:#CC0000;color:white;' : 'background:transparent;color:#555;'"
                @click="tab='register'">Inscription</button>
      </div>

      <div class="account-form">
        <div x-show="errorMsg" style="background:#fff5f5;border:1px solid #CC0000;border-radius:8px;padding:10px;margin-bottom:12px;color:#CC0000;font-size:13px;" x-text="errorMsg"></div>

        <template x-if="tab==='register'">
          <div>
            <label class="checkout-label">Nom (facultatif)</label>
            <input class="checkout-input" type="text" placeholder="Votre prénom" x-model="form.name">
          </div>
        </template>

        <label class="checkout-label">Téléphone</label>
        <input class="checkout-input" type="tel" placeholder="+33 6 00 00 00 00" x-model="form.phone">

        <label class="checkout-label">Mot de passe</label>
        <input class="checkout-input" type="password" placeholder="••••••••" x-model="form.password">

        <button class="checkout-next-btn" :disabled="loading" @click="submit()">
          <span x-show="!loading" x-text="tab==='login' ? 'Se connecter' : 'Créer mon compte'"></span>
          <span x-show="loading">Chargement…</span>
        </button>
      </div>

    </div>
  </div>

  <script src="/assets/js/api.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
  <script>
    function accountData() {
      return {
        authUser: null,
        orders:   [],
        tab:      'login',
        form:     { phone: '', password: '', name: '' },
        loading:  false,
        errorMsg: '',

        init() {
          const user = localStorage.getItem('auth_user');
          if (user) {
            this.authUser = JSON.parse(user);
            this.loadOrders();
          }
        },

        async loadOrders() {
          try {
            this.orders = await apiFetch('/orders/mine');
          } catch(e) { this.orders = []; }
        },

        async submit() {
          this.loading = true; this.errorMsg = '';
          try {
            const endpoint = this.tab === 'login' ? 'login' : 'register';
            const result = await api[endpoint]({
              phone:    this.form.phone,
              password: this.form.password,
              name:     this.form.name || undefined,
            });
            localStorage.setItem('auth_token', result.token);
            localStorage.setItem('auth_user',  JSON.stringify(result.user));
            this.authUser = result.user;
            this.loadOrders();
          } catch(e) {
            this.errorMsg = e.message;
          } finally {
            this.loading = false;
          }
        },

        logout() {
          localStorage.removeItem('auth_token');
          localStorage.removeItem('auth_user');
          this.authUser = null;
          this.orders = [];
        }
      };
    }
  </script>
</body>
</html>
```

- [ ] **Ajouter `GET /api/orders/mine`** dans `api/routes/orders.php` — liste des commandes de l'utilisateur connecté:

```php
// GET /api/orders/mine — historique commandes (auth requise)
if ($method === 'GET' && $uri === '/orders/mine') {
    $payload = auth_get_payload();
    if (!$payload) json_error('Non authentifié', 401);
    $stmt = db()->prepare(
        'SELECT id, type, status, total, tracking_token, created_at FROM orders WHERE user_id=? ORDER BY created_at DESC LIMIT 20'
    );
    $stmt->execute([$payload['uid']]);
    json_success($stmt->fetchAll());
}
```

- [ ] **Lancer tous les tests**

```bash
cd /Users/andrena/Documents/restaurent
./vendor/bin/phpunit tests/ --bootstrap tests/bootstrap.php -v
```

- [ ] **Commit**

```bash
git add public/account.html api/routes/orders.php
git commit -m "feat: add account page (login/register/history) and GET /api/orders/mine"
```

---

## Task 9 : Vérification finale frontend

- [ ] **Tester le flux complet dans le navigateur**

```bash
# Relancer le serveur si nécessaire
pkill -f "php -S localhost:8080" 2>/dev/null; sleep 1
cd /Users/andrena/Documents/restaurent && php -S localhost:8080 router.php &
sleep 1
open http://localhost:8080/
```

Checklist manuelle :
- [ ] Menu se charge (catégories + plats)
- [ ] Onglets filtrent les plats
- [ ] Tap sur un plat → popup modal s'ouvre
- [ ] Options sélectionnables dans la fiche plat
- [ ] Ajout au panier → barre flottante apparaît
- [ ] Tap barre flottante → drawer panier s'ouvre
- [ ] Quantités modifiables dans le panier
- [ ] Bouton Commander → modal checkout s'ouvre
- [ ] Étapes du checkout naviguent correctement (Pick & Collect = 2 étapes, Livraison = 3)
- [ ] Page suivi commande charge correctement: `http://localhost:8080/track.html?token=TOKEN`
- [ ] Page compte: formulaire connexion/inscription
- [ ] PWA: le manifest est accessible à `http://localhost:8080/manifest.json`

- [ ] **Vérifier routes API**

```bash
curl -s http://localhost:8080/api/settings  | php -r "echo json_decode(file_get_contents('php://stdin'))->success ? 'settings OK' : 'FAIL';"
curl -s http://localhost:8080/api/categories | php -r "echo json_decode(file_get_contents('php://stdin'))->success ? 'categories OK' : 'FAIL';"
curl -s http://localhost:8080/api/products  | php -r "echo json_decode(file_get_contents('php://stdin'))->success ? 'products OK' : 'FAIL';"
```

- [ ] **Lancer tous les tests PHPUnit**

```bash
./vendor/bin/phpunit tests/ --bootstrap tests/bootstrap.php -v
```

Expected : tous verts (14+ tests).

- [ ] **Commit final Plan 2**

```bash
git add .
git commit -m "feat: complete Plan 2 — frontend client Alpine.js"
```

---

## Notes importantes pour le sous-agent

### Variable `tracking_token` dans la table `orders`

Le schema initial de Plan 1 avait `orders.stripe_payment_id`. Il faut s'assurer que `tracking_token` existe aussi. Vérifier dans `database/schema.sql` et dans la DB live. Si absent :

```bash
mysql -u andrena --default-character-set=utf8mb4 -e \
  "ALTER TABLE restaurant.orders ADD COLUMN IF NOT EXISTS tracking_token VARCHAR(64) UNIQUE AFTER stripe_payment_id;"
mysql -u andrena --default-character-set=utf8mb4 restaurant_test -e \
  "ALTER TABLE orders ADD COLUMN IF NOT EXISTS tracking_token VARCHAR(64) UNIQUE AFTER stripe_payment_id;" 2>/dev/null || true
```

### Stripe en développement

Pour tester le paiement sans clé Stripe réelle, utiliser les clés de test Stripe (disponibles sur dashboard.stripe.com). Ajouter dans `.env`:
```
STRIPE_SK=sk_test_...
```
Et dans `database/settings` via l'admin: `stripe_pk_public = pk_test_...`

Carte de test: `4242 4242 4242 4242` — exp: n'importe quelle date future — CVV: n'importe quel 3 chiffres.

### Options dans GET /api/products

Pour que la fiche plat affiche les options, `GET /api/products` doit retourner les options pour chaque produit. Vérifier dans `api/routes/menu.php` que les options sont incluses (JSON agrégé ou sous-tableau). Si non, adapter la requête SQL pour inclure les options.
