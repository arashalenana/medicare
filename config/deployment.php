<?php
// Deployment Configuration

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return false;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
    return true;
}

// Load .env from project root
loadEnv(dirname(__DIR__) . '/.env');

// Environment Detection 
$is_local = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1'])
            || (isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']));

// Helper function to get configuration values with environment variable support
function get_config($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? ($_SERVER[$key] ?? $default);
    }
    return $value;
}

// Database Configuration 
define('DB_HOST',     get_config('DB_HOST', 'localhost'));
define('DB_NAME',     get_config('DB_NAME', 'family_medicare_pos'));
define('DB_USER',     get_config('DB_USER', 'root'));
define('DB_PASS',     get_config('DB_PASS', ''));
define('DB_CHARSET',  get_config('DB_CHARSET', 'utf8mb4'));

// Application Settings 
define('APP_NAME',        get_config('APP_NAME', 'Family Medicare POS'));
define('APP_TIMEZONE',    get_config('APP_TIMEZONE', 'Africa/Nairobi'));

$debug_val = get_config('APP_DEBUG');
define('APP_DEBUG',       $debug_val === 'true' || $debug_val === '1' || ($debug_val === null && $is_local));

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Set application timezone
date_default_timezone_set(APP_TIMEZONE);
