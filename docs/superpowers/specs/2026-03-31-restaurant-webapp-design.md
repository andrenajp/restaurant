# Spec — Web App Restaurant P.R.T

**Date :** 2026-03-31
**Stack :** PHP 8.x · MySQL · Alpine.js · Stripe · Twilio
**Type :** Mobile-first PWA (Progressive Web App)

---

## 1. Vue d'ensemble

Application web mobile-first pour un restaurant spécialisé en cuisine **indienne et créole (Guyane / Haïti)**. Permet de passer des commandes en **Pick & Collect** ou **Livraison** avec une expérience aussi rapide qu'une borne de commande. Le compte client est entièrement facultatif.

L'application est conçue pour être **déployable pour n'importe quel restaurant** : toutes les couleurs, le logo, le nom et la configuration sont stockés en base de données et modifiables depuis l'admin sans toucher au code.

---

## 2. Architecture technique

### Stack
- **Frontend :** HTML/CSS (template Delivo adapté) + Alpine.js (réactivité légère, ~15kb)
- **Backend :** PHP 8.x — API REST JSON
- **Base de données :** MySQL
- **Paiement :** Stripe (widget intégré, pas de redirection)
- **SMS :** Twilio (obligatoire)
- **Email :** SMTP/Mailgun (facultatif)
- **PWA :** Service Worker + manifest.json (menu mis en cache, installable sur mobile)

### Structure des couches
```
Frontend Alpine.js  ──JSON/HTTPS──▶  PHP API REST  ──PDO──▶  MySQL
                                           │
                              Stripe · Twilio · Email
```

### Admin panel
Interface séparée (PHP + Bootstrap), même API backend, accès protégé par rôle.

---

## 3. Fonctionnalités

### 3.1 Application client (mobile-first)

**Page principale — une seule page**
- Toggle **Pick & Collect / Livraison** en haut
- 4 bandes colorées signature (noir, or, blanc, vert) sous le hero
- Bannière promo configurable depuis l'admin
- Onglets de filtre catégories (scroll horizontal) — catégories gérées depuis l'admin
- Liste des plats directement visible, filtrée par catégorie active
- Chaque plat : photo, nom, description courte, prix, bouton `+`

**Fiche plat (popup)**
- Photo, nom, description
- Options : groupe "Riz" (normal / +riz +1€), groupe "Sauce" (choix parmi liste)
- Sélecteur de quantité
- Bouton "Ajouter au panier — X€"

**Panier**
- Barre flottante sticky en bas : nombre d'articles + total + bouton "Voir le panier"
- Page panier : liste modifiable (quantités, suppression), total avec frais de livraison

**Checkout — friction minimale**

*Pick & Collect (2 étapes) :*
1. Téléphone → Continuer
2. Résumé + widget Stripe intégré → Payer
3. Popup post-paiement : "Votre prénom ?" (1 champ, facultatif)

*Livraison (3 étapes) :*
1. Téléphone
2. Adresse de livraison
3. Résumé + widget Stripe intégré → Payer

Si client connecté : téléphone + adresse pré-remplis → direct au paiement.

**Page suivi commande**
- Timeline visuelle : Reçue → En préparation → Prête/En route → Livrée
- Accessible via lien dans le SMS de confirmation
- Pas besoin de compte pour consulter (token unique dans l'URL)

**Compte client (facultatif)**
- Inscription/connexion par téléphone + mot de passe
- Avantage : pré-remplissage téléphone + adresse au checkout
- Historique des commandes

---

### 3.2 Vue Préparateur (cuisine)

Interface simplifiée, optimisée tablette, accès par login dédié.

**Kanban 3 colonnes :**
- **En attente** : nouvelles commandes — bouton "Commencer"
- **En préparation** : en cours — bouton "Prête"
- **Prêtes** : attente retrait/livreur

**Actions :**
- "Commencer" → statut passe à "en_preparation"
- "Prête" → statut passe à "prete" + SMS automatique envoyé au client

Chaque carte affiche : numéro de commande, type (pick & collect / livraison), liste des plats + options.

---

### 3.3 Admin Panel (gérant)

Accès protégé, interface desktop/tablette.

| Section | Fonctionnalités |
|---|---|
| **Commandes** | Vue temps réel, filtre par statut/type, changement de statut, détail commande |
| **Menu** | Ajouter/modifier/supprimer plats, photo, prix, disponibilité on/off |
| **Catégories** | Nom, emoji, couleur, ordre d'affichage, actif/inactif |
| **Options** | Groupes d'options par plat (riz, sauces…), prix supplémentaires |
| **Livraison** | Zones avec tarifs, seuil livraison gratuite |
| **Thème** | Couleur principale, couleur accent, 4 bandes (hex), logo, fond |
| **Bannière** | Texte promo affiché sur l'accueil |
| **Réglages** | Nom restaurant, Stripe (clé pub), Twilio, email SMTP |
| **Stats** | CA par jour/semaine/mois, nombre de commandes, plats populaires |

---

## 4. Base de données

### Tables

**`settings`** — Configuration restaurant + thème
`id · restaurant_name · logo_url · color_primary · color_accent · color_band_1/2/3/4 · delivery_free_above · twilio_phone · stripe_pk_public · promo_banner`

> ⚠️ Seule la clé publique Stripe (`stripe_pk_public`) est en base. La clé secrète (`STRIPE_SK`) et les credentials Twilio sont dans le fichier `.env` côté serveur, jamais en base.

**`categories`**
`id · name · emoji · color · sort_order · is_active`

**`products`**
`id · category_id · name · description · price · image_url · is_available · sort_order`

**`product_options`**
`id · product_id · group_name · option_name · extra_price · is_default`

**`orders`**
`id · user_id (nullable) · phone · customer_name · type (pickup/delivery) · delivery_address · status · total · delivery_fee · stripe_payment_id · created_at`

Statuts : `received` → `in_preparation` → `ready` → `en_route` (livraison uniquement) → `delivered`

**`order_items`**
`id · order_id · product_id · quantity · unit_price · options_json`

**`users`**
`id · name · phone · email · password_hash · default_address · role (client/admin/kitchen) · created_at`

**`delivery_fees`**
`id · zone_name · fee · is_active`

---

## 5. Identité visuelle

### Palette P.R.T
| Rôle | Couleur | Hex |
|---|---|---|
| Principale | Rouge | `#CC0000` |
| Accent | Or/Doré | `#D4A017` |
| Confirmation | Vert | `#1A7A1A` |
| Textes | Noir | `#111111` |
| Fond | Crème | `#F9F6F0` |

### 4 bandes signature (sous le hero, en footer, séparateurs)
`#111111` · `#D4A017` · `#FFFFFF` · `#1A7A1A`

Toutes les couleurs sont **stockées en base** dans `settings` — modifiables depuis l'admin pour un redéploiement sur un autre restaurant.

---

## 6. Notifications

| Événement | Canal |
|---|---|
| Commande reçue (client) | SMS Twilio (obligatoire) + email (facultatif) |
| Commande prête / en route (client) | SMS Twilio automatique |
| Nouvelle commande (cuisine) | Rafraîchissement polling toutes les 15s |

---

## 7. PWA

- `manifest.json` : nom, icône, couleur thème, `display: standalone`
- Service Worker : mise en cache du menu et des assets statiques
- Installable sur écran d'accueil Android/iOS

---

## 8. Sécurité

- Mots de passe hashés (bcrypt)
- Tokens JWT pour les sessions API
- Accès admin/cuisine vérifié côté serveur sur chaque requête
- Paiement géré intégralement par Stripe (aucune donnée carte côté serveur)
- Page de suivi accessible via token unique (UUID) dans l'URL, sans authentification

---

## 9. Rôles utilisateurs

| Rôle | Accès |
|---|---|
| `client` | App mobile, historique commandes |
| `kitchen` | Vue préparateur uniquement |
| `admin` | Tout le panel + vue préparateur |

---

## 10. Multi-restaurant

Pour déployer sur un autre restaurant :
1. Créer une nouvelle entrée dans `settings` (couleurs, logo, nom, clés API)
2. Importer le menu dans `categories` + `products`
3. Aucune modification de code nécessaire
