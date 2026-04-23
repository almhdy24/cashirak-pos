<?php
// Bootstrap file - manual autoloader for Cashirak V2

spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = '';
    $base_dir = __DIR__ . '/';
    
    // Map namespaces to directories
    $namespace_map = [
        'Core\\' => 'Core/',
        'Models\\' => 'Models/',
        'Repositories\\' => 'Repositories/',
        'Services\\' => 'Services/',
        'Middleware\\' => 'Middleware/',
    ];
    
    foreach ($namespace_map as $namespace => $dir) {
        $len = strlen($namespace);
        if (strncmp($namespace, $class, $len) === 0) {
            $relative_class = substr($class, $len);
            $file = $base_dir . $dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
                return true;
            }
        }
    }
    
    return false;
});

// Load Core classes explicitly
require_once __DIR__ . '/Core/DB.php';
require_once __DIR__ . '/Core/Session.php';
require_once __DIR__ . '/Core/Security.php';
require_once __DIR__ . '/Core/Auth.php';

// Load Models
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Models/Item.php';
require_once __DIR__ . '/Models/Order.php';
require_once __DIR__ . '/Models/OrderItem.php';
require_once __DIR__ . '/Models/Shift.php';

// Load Repositories
require_once __DIR__ . '/Repositories/OrderRepository.php';

// Load Services
require_once __DIR__ . '/Services/OrderService.php';
require_once __DIR__ . '/Services/ShiftService.php';

// Load Middleware
require_once __DIR__ . '/Middleware/AuthMiddleware.php';
