/**
 * pwa-install.js
 * À inclure dans toutes tes pages HTML :
 *   <script src="/assets/js/pwa-install.js" defer></script>
 *
 * Gère :
 *  - Android/Chrome  : bannière native via beforeinstallprompt
 *  - iOS/Safari      : popup manuel "Ajouter à l'écran d'accueil"
 *  - Desktop Chrome  : bouton d'installation
 */

(function () {
    // Ne rien faire si déjà installée en mode standalone
    if (
        window.matchMedia("(display-mode: standalone)").matches ||
        window.navigator.standalone === true
    ) {
        return;
    }

    let deferredPrompt = null;

    // ── Injection du CSS de la bannière ──────────────────────────────────────
    const style = document.createElement("style");
    style.textContent = `
        #pwa-banner {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: #1a1a1a;
            color: #f0f0f0;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.4);
            font-family: "Segoe UI", system-ui, sans-serif;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(100%); opacity: 0; }
            to   { transform: translateY(0);    opacity: 1; }
        }
        #pwa-banner img {
            width: 48px; height: 48px;
            border-radius: 10px;
            flex-shrink: 0;
        }
        #pwa-banner-text {
            flex: 1;
            min-width: 0;
        }
        #pwa-banner-text strong {
            display: block;
            font-size: 14px;
            color: #fff;
        }
        #pwa-banner-text span {
            font-size: 12px;
            color: #aaa;
        }
        #pwa-banner-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        #pwa-btn-install {
            background: #cc0000;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
        }
        #pwa-btn-install:hover { opacity: 0.88; }
        #pwa-btn-dismiss {
            background: transparent;
            color: #888;
            border: 1px solid #444;
            border-radius: 8px;
            padding: 8px 10px;
            font-size: 13px;
            cursor: pointer;
        }
        #pwa-btn-dismiss:hover { color: #ccc; border-color: #666; }

        /* iOS : instructions spéciales */
        #pwa-ios-hint {
            font-size: 12px;
            color: #aaa;
            margin-top: 4px;
            line-height: 1.4;
        }
        #pwa-ios-hint svg {
            vertical-align: middle;
            margin: 0 2px;
        }
    `;
    document.head.appendChild(style);

    // ── Création de la bannière ──────────────────────────────────────────────
    function createBanner(isIos) {
        const banner = document.createElement("div");
        banner.id = "pwa-banner";

        const iosHint = isIos
            ? `<div id="pwa-ios-hint">
                Appuyez sur
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="2"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>
                puis <strong style="color:#fff">« Sur l'écran d'accueil »</strong>
               </div>`
            : "";

        banner.innerHTML = `
            <img src="/assets/img/apple-touch-icon.png" onerror="this.src='/assets/img/icon-192.png'" alt="P.R.T" />
            <div id="pwa-banner-text">
                <strong>P.R.T — Restaurant</strong>
                <span>Installez l'app pour commander rapidement</span>
                ${iosHint}
            </div>
            <div id="pwa-banner-actions">
                ${
                    isIos
                        ? `<button id="pwa-btn-dismiss">✕</button>`
                        : `<button id="pwa-btn-install">Installer</button>
                       <button id="pwa-btn-dismiss">✕</button>`
                }
            </div>
        `;

        document.body.appendChild(banner);

        // Fermer la bannière
        document
            .getElementById("pwa-btn-dismiss")
            .addEventListener("click", () => {
                banner.remove();
                // Ne plus afficher pendant 7 jours
                localStorage.setItem(
                    "pwa-dismissed",
                    Date.now() + 7 * 24 * 3600 * 1000,
                );
            });

        // Bouton installer (Android/Desktop seulement)
        if (!isIos) {
            document
                .getElementById("pwa-btn-install")
                .addEventListener("click", async () => {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    deferredPrompt = null;
                    banner.remove();
                    if (outcome === "accepted") {
                        localStorage.setItem("pwa-installed", "1");
                    }
                });
        }
    }

    // ── Vérifie si on doit afficher la bannière (pas déjà fermée récemment) ──
    function shouldShow() {
        const dismissed = localStorage.getItem("pwa-dismissed");
        if (dismissed && Date.now() < parseInt(dismissed)) return false;
        return true;
    }

    // ── Détection iOS ────────────────────────────────────────────────────────
    const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

    // ── Android / Desktop Chrome : écoute l'événement natif ─────────────────
    window.addEventListener("beforeinstallprompt", (e) => {
        e.preventDefault(); // empêche la mini-barre native de Chrome
        deferredPrompt = e;

        if (shouldShow()) {
            // Légère pause pour ne pas bloquer le chargement de la page
            setTimeout(() => createBanner(false), 2000);
        }
    });

    // ── iOS Safari : pas d'événement, bannière manuelle ──────────────────────
    if (isIos && isSafari && shouldShow()) {
        setTimeout(() => createBanner(true), 2000);
    }

    // ── Nettoyer si l'app vient d'être installée ─────────────────────────────
    window.addEventListener("appinstalled", () => {
        deferredPrompt = null;
        const banner = document.getElementById("pwa-banner");
        if (banner) banner.remove();
        localStorage.setItem("pwa-installed", "1");
    });
})();
