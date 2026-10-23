<?php
// Header Include

// Load centralized path configuration (safe, idempotent)
if (!defined('FAMILYMEDICARE_PATHS_LOADED')) {
    require_once dirname(__FILE__) . '/../config/paths.php';
}

// Load RBAC helper functions
if (!defined('FAMILYMEDICARE_RBAC_LOADED')) {
    require_once dirname(__FILE__) . '/../config/rbac.php';
    define('FAMILYMEDICARE_RBAC_LOADED', true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#ED1C24">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Family Medicare POS</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $asset_path; ?>images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $asset_path; ?>images/favicon-16x16.png">
    <link rel="shortcut icon" href="<?php echo $asset_path; ?>images/favicon.png">

    <!-- Preload critical assets so browser fetches them immediately -->
    <link rel="preload" href="<?php echo $asset_path; ?>vendor/bootstrap/css/bootstrap.min.css" as="style">
    <link rel="preload" href="<?php echo $asset_path; ?>vendor/fontawesome/css/all.min.css" as="style">
    <link rel="preload" href="<?php echo $asset_path; ?>vendor/fontawesome/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin="anonymous">
    <link rel="preload" href="<?php echo $asset_path; ?>css/style.css" as="style">

    <!-- Bootstrap CSS -->
    <link href="<?php echo $asset_path; ?>vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome (woff2 only — fastest loading) -->
    <link rel="stylesheet" href="<?php echo $asset_path; ?>vendor/fontawesome/css/all.min.css">

    <!-- Application CSS -->
    <link rel="stylesheet" href="<?php echo $asset_path; ?>css/style.css">

    <!-- AI Assistant CSS -->
    <link rel="stylesheet" href="<?php echo $chatbot_path; ?>ai-assistant.css">

    <!-- Chart.js — defer so it doesn't block page render -->
    <script src="<?php echo $asset_path; ?>vendor/chartjs/chart.min.js" defer></script>

    <!-- Bootstrap JS — defer so it doesn't block page render -->
    <script src="<?php echo $asset_path; ?>vendor/bootstrap/js/bootstrap.bundle.min.js" defer></script>

    <!-- PWA: Manifest, meta tags, and service worker registration -->
    <?php include dirname(__FILE__) . '/pwa.php'; ?>
</head>
<body>
    <div class="wrapper d-flex align-items-stretch">