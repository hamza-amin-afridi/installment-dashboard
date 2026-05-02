<?php
/**
 * Database Configuration
 * Update DB_HOST, DB_NAME, DB_USER, DB_PASS before deployment.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'oneclick2trip_installment');  // cPanel DB name
define('DB_USER', 'oneclick2trip_installment');  // cPanel DB username
define('DB_PASS', 'Ha1994181@');                           // ← PASTE YOUR PASSWORD HERE
define('DB_CHARSET', 'utf8mb4');

// -------------------------------------------------------
// Application Constants
// -------------------------------------------------------
define('APP_NAME',     'MotoLease Pro');
define('APP_VERSION',  '1.0.0');
define('CURRENCY',     '₨');          // PKR symbol
define('CURRENCY_CODE','PKR');
define('TIMEZONE',     'Asia/Karachi');
define('UPLOAD_DIR',   __DIR__ . '/../uploads/motorcycles/');
define('UPLOAD_URL',   'uploads/motorcycles/');
define('BASE_URL',     '');           // e.g. '/installment-dashboard' if in subdirectory

// Set timezone globally
date_default_timezone_set(TIMEZONE);

// -------------------------------------------------------
// PDO Connection (singleton pattern)
// -------------------------------------------------------
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Never expose raw error in production
            error_log('DB Connection failed: ' . $e->getMessage());
            die('<div style="padding:2rem;font-family:sans-serif;color:#ff4757;background:#1a1d2e;text-align:center;">
                    <h2>🔌 Database Connection Error</h2>
                    <p>Please check your database configuration in <code>config/db.php</code></p>
                 </div>');
        }
    }
    return $pdo;
}
