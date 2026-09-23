<?php
/**
 * Cashirak POS – Bootstrap File
 */

define('ROOT_PATH',  __DIR__);
define('APP_PATH',   ROOT_PATH . '/app');
define('CORE_PATH',  APP_PATH  . '/Core');
define('VIEWS_PATH', ROOT_PATH . '/views');

// DATA_PATH, STORAGE_PATH, DB_PATH — env-aware, ProgramData-aware
require_once CORE_PATH . '/paths.php';

// ── Version ──────────────────────────────────────────────────────────────────

require_once APP_PATH . '/version.php';

// ── Error logging — always write to STORAGE_PATH/logs ────────────────────────

$_logDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($_logDir)) @mkdir($_logDir, 0777, true);
@ini_set('error_log', $_logDir . DIRECTORY_SEPARATOR . 'php_errors.log');
unset($_logDir);

// ── Installation check ───────────────────────────────────────────────────────

if (!file_exists(STORAGE_PATH . '/installed.lock')) {
    if (file_exists(ROOT_PATH . '/public/install.php')) {
        header('Location: /install.php');
        exit;
    }
    die('النظام غير مثبت. يرجى تشغيل معالج التثبيت.');
}

// ── SPL Autoloader ───────────────────────────────────────────────────────────

spl_autoload_register(function (string $class): bool {
    $map = [
        'Core\\'         => CORE_PATH    . '/',
        'Models\\'       => APP_PATH . '/Models/',
        'Repositories\\' => APP_PATH . '/Repositories/',
        'Services\\'     => APP_PATH . '/Services/',
        'Middleware\\'   => APP_PATH . '/Middleware/',
        'ElmahdiPay\\'   => APP_PATH . '/ElmahdiPay/',
    ];

    foreach ($map as $ns => $dir) {
        $len = strlen($ns);
        if (strncmp($ns, $class, $len) === 0) {
            $file = $dir . str_replace('\\', '/', substr($class, $len)) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }
    return false;
});

// ── Core classes ─────────────────────────────────────────────────────────────

require_once CORE_PATH . '/DB.php';
require_once CORE_PATH . '/Session.php';
require_once CORE_PATH . '/Security.php';
require_once CORE_PATH . '/Auth.php';
require_once CORE_PATH . '/Settings.php';
require_once CORE_PATH . '/License.php';

// ── Models ───────────────────────────────────────────────────────────────────

require_once APP_PATH . '/Models/User.php';
require_once APP_PATH . '/Models/Item.php';
require_once APP_PATH . '/Models/Order.php';
require_once APP_PATH . '/Models/OrderItem.php';
require_once APP_PATH . '/Models/Shift.php';
require_once APP_PATH . '/Models/Category.php';

// ── Repositories & Services ──────────────────────────────────────────────────

require_once APP_PATH . '/Repositories/OrderRepository.php';
require_once APP_PATH . '/Services/OrderService.php';
require_once APP_PATH . '/Services/ShiftService.php';

// ── Middleware ───────────────────────────────────────────────────────────────

require_once APP_PATH . '/Middleware/AuthMiddleware.php';

// ── Helpers ──────────────────────────────────────────────────────────────────

if (file_exists(APP_PATH . '/helpers.php')) {
    require_once APP_PATH . '/helpers.php';
}

// ── Session ──────────────────────────────────────────────────────────────────

\Core\Session::start();

// ── License check ────────────────────────────────────────────────────────────

\Core\License::requireActive();

// ── Done ─────────────────────────────────────────────────────────────────────

define('APP_BOOTSTRAPPED', true);
