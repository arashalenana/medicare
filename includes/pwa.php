<?php
/**
 * Family Medicare POS - PWA Registration & Meta Tags
 * Robust PWA path detection
 */

// Load paths if not already loaded
if (!defined('PHARMALYNX_PATHS_LOADED')) {
    require_once dirname(__FILE__) . '/../config/paths.php';
}

// $base_url and $app_root are now available from paths.php
$base_url_pwa = $base_url;
$root_path = $app_root;
$cache_bust = '?v=2.0.' . date('Ymd');
?>

<!-- PWA: Web App Manifest -->
<link rel="manifest" href="<?php echo $base_url_pwa; ?>manifest.json?v=<?php echo time(); ?>" crossorigin="use-credentials">

<!-- PWA: Theme Color -->
<meta name="theme-color" content="#8b0000">
<meta name="msapplication-TileColor" content="#8b0000">
<meta name="msapplication-TileImage" content="<?php echo $base_url_pwa; ?>assets/icons/icon-144x144.png?v=<?php echo date('Ymd'); ?>">

<!-- PWA: Apple / iOS Support -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Family Medicare">
<link rel="apple-touch-icon" href="<?php echo $base_url_pwa; ?>assets/icons/apple-touch-icon.png?v=<?php echo date('Ymd'); ?>">
<link rel="apple-touch-icon" sizes="152x152" href="<?php echo $base_url_pwa; ?>assets/icons/icon-152x152.png?v=<?php echo date('Ymd'); ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo $base_url_pwa; ?>assets/icons/apple-touch-icon.png?v=<?php echo date('Ymd'); ?>">
<link rel="apple-touch-icon" sizes="167x167" href="<?php echo $base_url_pwa; ?>assets/icons/icon-152x152.png?v=<?php echo date('Ymd'); ?>">

<!-- PWA: Mobile Optimization -->
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="Family Medicare POS">
<meta name="format-detection" content="telephone=no">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">

<!-- PWA: Service Worker Registration -->
<script>
(function() {
    'use strict';
    
    if ('serviceWorker' in navigator) {
        var swUrl = '<?php echo $base_url_pwa; ?>sw.js?v=' + new Date().getTime();
        var swScope = '<?php echo $root_path; ?>/';

        window.addEventListener('load', function() {
            navigator.serviceWorker.register(swUrl, { scope: swScope })
                .then(function(registration) {
                    console.log('[PWA] Service Worker registered');
                })
                .catch(function(error) {
                    console.error('[PWA] Registration failed:', error);
                });
        });
    }
})();

// PWA: Install Prompt Logic
(function() {
    'use strict';
    
    let deferredPrompt = null;
    const INSTALLED_KEY = 'pwa-app-installed-v2';
    
    function isAppInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches || 
               window.navigator.standalone === true || 
               localStorage.getItem(INSTALLED_KEY) === 'true';
    }
    
    function createInstallBanner() {
        if (document.getElementById('pwa-install-banner')) return;
        
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.style.cssText = `
            display: none;
            position: fixed;
            bottom: 16px;
            right: 16px;
            width: 320px;
            background: #1a1a1a;
            border: 1px solid #8b0000;
            border-radius: 12px;
            padding: 16px;
            z-index: 9999;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            color: white;
            font-family: sans-serif;
        `;
        
        banner.innerHTML = `
            <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 12px;">
                <img src="<?php echo $base_url_pwa; ?>assets/icons/icon-72x72.png" style="width: 48px; border-radius: 8px;">
                <div style="font-weight: bold;">Install Family Medicare</div>
            </div>
            <div style="font-size: 14px; margin-bottom: 16px; color: #ccc;">Install this app on your home screen for quick access and offline use.</div>
            <div style="display: flex; gap: 8px;">
                <button id="pwa-install-btn" style="flex: 1; background: #8b0000; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer;">Install</button>
                <button id="pwa-later-btn" style="flex: 1; background: #333; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer;">Later</button>
            </div>
        `;
        document.body.appendChild(banner);
        
        document.getElementById('pwa-install-btn').onclick = () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(res => {
                    if (res.outcome === 'accepted') localStorage.setItem(INSTALLED_KEY, 'true');
                    banner.style.display = 'none';
                });
            }
        };
        document.getElementById('pwa-later-btn').onclick = () => banner.style.display = 'none';
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        if (!isAppInstalled()) {
            createInstallBanner();
            setTimeout(() => {
                const banner = document.getElementById('pwa-install-banner');
                if (banner) banner.style.display = 'block';
            }, 3000);
        }
    });

    window.addEventListener('appinstalled', () => {
        localStorage.setItem(INSTALLED_KEY, 'true');
        const banner = document.getElementById('pwa-install-banner');
        if (banner) banner.style.display = 'none';
    });
})();
</script>
