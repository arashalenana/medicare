<?php
// Database Configuration and Connection 

// Load deployment configuration
if (!defined('DB_HOST')) {
    require_once dirname(__FILE__) . '/deployment.php';
}

// Establish PDO connection
if (!isset($conn)) {
    try {
        $dsn  = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $conn = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Synchronize Timezone for both PHP and MySQL to East African Time (EAT)
        date_default_timezone_set('Africa/Nairobi');
        
        
        // Set MySQL session timezone to match PHP timezone 
        $conn->exec("SET time_zone = '+03:00'");

    } catch (PDOException $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            die('<div style="background:#1a1a1a;color:#ff4444;padding:20px;font-family:monospace;border-left:4px solid #ED1C24;">'
                . '<strong>Database Connection Error:</strong><br>'
                . htmlspecialchars($e->getMessage())
                . '<br><br><small><strong>Fix for XAMPP:</strong><br>'
                . '1. Open phpMyAdmin and create a database named "family_medicare"<br>'
                . '2. Import "database/medicare_enhanced.sql" into that database<br>'
                . '3. Check ".env" or "config/deployment.php" for credentials.</small>'
                . '</div>');
        }
        $conn = null;
    }
}

// Helper Functions 

// Sanitize user input
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

// Format amount as Kenyan Shillings
if (!function_exists('formatKSh')) {
    function formatKSh($amount) {
        return "KSh " . number_format((float)$amount, 2);
    }
}
?>
