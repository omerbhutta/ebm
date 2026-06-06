<?php
/**
 * Bootstrap — autoloader + application initialization.
 */

declare(strict_types=1);

if (!defined('EBM_BOOT')) define('EBM_BOOT', 1);

// PSR-4 autoloader for App\ namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;

    if (strpos($class, $prefix) !== 0) return;

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Boot the application container
$app = App\Core\App::boot(dirname(__DIR__));

// Determine base URL for clean links (works in subfolder installs like /undeliveredemails)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$app->setBaseUrl($baseDir);

// Ensure storage subdirs + their guards exist (idempotent)
foreach (['cache', 'logs', 'locks'] as $sub) {
    $path = $app->storagePath($sub);
    if (!is_dir($path)) @mkdir($path, 0775, true);
    $ht = $path . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
}

// Error handling
error_reporting(E_ALL);
$displayErrors = !$app->isInstalled() || (getenv('EBM_DEBUG') === '1');
ini_set('display_errors', $displayErrors ? '1' : '0');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (\Throwable $e) use ($app) {
    try {
        App\Core\Logger::error('exception', $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => substr($e->getTraceAsString(), 0, 2000),
        ]);
    } catch (\Throwable $logErr) {
        // ignore
    }
    http_response_code(500);
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        if (getenv('EBM_DEBUG') === '1' || !$app->isInstalled()) {
            echo '<pre style="background:#1f2937;color:#fca5a5;padding:20px;font:13px monospace;border-radius:8px;margin:20px">';
            echo htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) . "\n\n";
            echo htmlspecialchars($e->getFile() . ':' . $e->getLine()) . "\n\n";
            echo htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
        } else {
            if (is_file($app->viewsPath('errors/500.php'))) {
                $msg = 'An internal error occurred.';
                include $app->viewsPath('errors/500.php');
            } else {
                echo '<h1>500 — Server Error</h1>';
            }
        }
    }
    exit(1);
});

// Start session early
App\Core\Session::start();

return $app;
