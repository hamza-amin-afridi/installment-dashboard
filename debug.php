<?php
/**
 * TEMPORARY DEBUG FILE — DELETE AFTER USE!
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<pre style="font-family:monospace;padding:20px;background:#111;color:#0f0;">';
echo "PHP Version: " . phpversion() . "\n\n";

// Test DB connection
$host = 'localhost';
$db   = 'oneclick2trip_installment';
$user = 'oneclick2trip_installment';
$pass = 'Ha1994181@';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    echo "✅ DB Connection: SUCCESS\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found (" . count($tables) . "):\n";
    foreach ($tables as $t) echo "  - $t\n";

    $required = ['users','motorcycles','leasing_plans','customers','guarantors','leases','payments','activity_logs'];
    $missing  = array_diff($required, $tables);
    if ($missing) {
        echo "\n❌ MISSING TABLES:\n";
        foreach ($missing as $m) echo "  - $m\n";
        echo "\n⚠️  You need to re-import database/schema.sql in phpMyAdmin!\n";
    } else {
        echo "\n✅ All required tables exist!\n";
    }
} catch (PDOException $e) {
    echo "❌ DB Connection FAILED:\n" . $e->getMessage() . "\n";
}

echo "\n\n--- File Paths ---\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "config/db.php exists: " . (file_exists(__DIR__ . '/config/db.php') ? 'YES' : 'NO') . "\n";
echo "includes/auth.php exists: " . (file_exists(__DIR__ . '/includes/auth.php') ? 'YES' : 'NO') . "\n";
echo "assets/css/main.css exists: " . (file_exists(__DIR__ . '/assets/css/main.css') ? 'YES' : 'NO') . "\n";

echo '</pre>';
echo '<p style="color:red;font-family:sans-serif;padding:20px;"><strong>⚠️ DELETE debug.php after use!</strong></p>';
