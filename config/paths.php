<?php
/**
 * Family Medicare POS - Path and URL Configuration
 * Robust path detection for local and production environments
 */

if (!defined('PHARMALYNX_PATHS_LOADED')) {
    define('PHARMALYNX_PATHS_LOADED', true);

    // Get the script name (e.g., /medicare/index.php or /index.php)
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    
    // Get the directory of the script
    $script_dir = dirname($script_name);
    
    // Check if we are in a subfolder named 'pages' or 'api'
    $is_subpage = (strpos($script_name, '/pages/') !== false) || (strpos($script_name, '/api/') !== false);
    $is_deep_subpage = (strpos($script_name, '/pages/api/') !== false);

    // Determine the root directory of the application relative to the domain root
    if ($is_deep_subpage) {
        $app_root = dirname(dirname($script_dir));
    } elseif ($is_subpage) {
        $app_root = dirname($script_dir);
    } else {
        $app_root = $script_dir;
    }
    
    // Normalize app_root
    $app_root = rtrim(str_replace('\\', '/', $app_root), '/');
    if (empty($app_root)) $app_root = '';

    // Relative path helpers for assets and links
    if ($is_deep_subpage) {
        $base_path    = '../../';
    } elseif ($is_subpage) {
        $base_path    = '../';
    } else {
        $base_path    = '';
    }

    $asset_path   = $base_path . 'assets/';
    $chatbot_path = $base_path . 'ai-assistant/';

    // Base URL construction
    $is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;
    
    $protocol = ($is_localhost) ? 'http' : (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
        ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || 
        ($_SERVER['SERVER_PORT'] ?? '') == 443 ? 'https' : 'http'
    );
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = $protocol . '://' . $host . $app_root . '/';

    // Filesystem helpers
    $fs_root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . $app_root . '/';
}
?>
