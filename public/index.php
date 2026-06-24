<?php
/**
 * Randevu — Front Controller / Router
 */
declare(strict_types=1);

$root = dirname(__DIR__);
if (!file_exists($root . '/config.php')) {
    http_response_code(500);
    exit('Missing config.php — copy config.example.php to config.php.');
}
$config = require $root . '/config.php';
$GLOBALS['config'] = $config;
date_default_timezone_set('Europe/Istanbul');

// PSR-4-ish autoloader (App\ -> src/)
spl_autoload_register(function (string $class) use ($root) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $rel  = substr($class, strlen($prefix));
    $file = $root . '/src/' . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($file)) require $file;
});
require $root . '/src/functions.php';

use App\Core\Database;
use App\Core\Tenant;
use App\Core\View;
use App\Core\Csrf;
use App\Controllers\BookingController;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$uri    = '/' . trim($uri, '/');

Database::init($config['db']);
View::init($root . '/src/Views');

$ctx          = Tenant::resolve($config, $uri);
$cookieSecure = (bool) ($config['app']['cookie_secure'] ?? true);

// Sınıf başına ayrı oturum çerezi (diğer uygulamaların çerezleriyle çakışmaz)
$sessName = match ($ctx['class']) {
    'shop'  => 'rv_book',
    'panel' => 'rv_panel',
    'sys'   => 'rv_sys',
    default => null,
};
if ($sessName !== null) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $cookieSecure,
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_name($sessName);
    session_start();
}

View::$shared = [
    'config'     => $config,
    'ctx'        => $ctx,
    'shop'       => $ctx['shop'],
    'csrf_token' => $sessName !== null ? Csrf::token() : '',
];

// Temel güvenlik başlıkları
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    switch ($ctx['class']) {
        case 'shop':
            Csrf::verifyOrAbort();
            (new BookingController())->route($ctx['path'], $method);
            break;

        case 'panel':
            Csrf::verifyOrAbort();
            (new \App\Controllers\PanelController())->route($ctx['path'], $method);
            break;

        case 'sys':
            View::render('sys/soon', [], 'none');
            break;

        case 'home':
            View::render('home', [], 'none');
            break;

        case 'api':
            View::json(['ok' => true, 'service' => 'randevu']);
            break;

        default:
            http_response_code(404);
            View::render('errors/404', [], 'none');
    }
} catch (\Throwable $e) {
    http_response_code(500);
    error_log('[randevu] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if ($config['app']['debug'] ?? false) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "ERR: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine();
    } else {
        echo 'Sunucu hatası.';
    }
}
