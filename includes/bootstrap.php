<?php
/**
 * Vikao bootstrap — every page and API endpoint starts here.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$cfgFile = APP_ROOT . '/config/config.php';
if (!is_file($cfgFile)) $cfgFile = APP_ROOT . '/config/config.sample.php';
$GLOBALS['__config'] = require $cfgFile;

function config(string $key, $default = null) {
    $val = $GLOBALS['__config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($val) || !array_key_exists($part, $val)) return $default;
        $val = $val[$part];
    }
    return $val;
}

date_default_timezone_set(config('app.timezone', 'Africa/Nairobi'));
mb_internal_encoding('UTF-8');

if (config('app.env') === 'production') {
    ini_set('display_errors', '0');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_name('vikao_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/catalog.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/services/notify.php';
require APP_ROOT . '/includes/services/listings.php';
require APP_ROOT . '/includes/services/media.php';
require APP_ROOT . '/includes/services/orders.php';
require APP_ROOT . '/includes/services/chat.php';
