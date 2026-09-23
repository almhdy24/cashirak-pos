<?php
// Single source of truth for all file-system paths.
// Safe to require from any entry point (install.php, bootstrap.php, etc.)
// Every define is guarded so the file is idempotent.

if (!defined('ROOT_PATH')) {
    // __DIR__ is app/Core — 2 levels up is the project root
    define('ROOT_PATH', dirname(__DIR__, 2));
}

if (!defined('APP_PATH'))   define('APP_PATH',   ROOT_PATH . '/app');
if (!defined('CORE_PATH'))  define('CORE_PATH',  ROOT_PATH . '/app/Core');
if (!defined('VIEWS_PATH')) define('VIEWS_PATH', ROOT_PATH . '/views');

if (!defined('DATA_PATH')) {
    $envPath = (string) getenv('CASHIRAK_DATA_PATH');
    if ($envPath !== '') {
        // Installed Windows: start.bat sets this to C:\ProgramData\Cashirak POS
        define('DATA_PATH', rtrim($envPath, DIRECTORY_SEPARATOR));
    } elseif (PHP_OS_FAMILY === 'Windows' && is_dir('C:\\ProgramData\\Cashirak POS')) {
        // Fallback: ProgramData dir exists but env was not set
        define('DATA_PATH', 'C:\\ProgramData\\Cashirak POS');
    } else {
        // Development / Linux / Termux: writable data lives next to the app
        define('DATA_PATH', ROOT_PATH);
    }
}

if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', DATA_PATH . DIRECTORY_SEPARATOR . 'storage');
}
if (!defined('DB_PATH')) {
    define('DB_PATH', DATA_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'cashirak.sqlite');
}
