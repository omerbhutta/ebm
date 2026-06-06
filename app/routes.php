<?php
/**
 * Routes — all application routes.
 * Available helpers:
 *   $router->get('/path', [Controller::class, 'method']);
 *   $router->post('/path', ...);
 *   $router->any('/path', ...);
 *   $router->group(['prefix' => '/admin'], function ($r) { ... });
 *
 * URL parameters: /bounces/{id}  → $controller->method($req, $id)
 */

declare(strict_types=1);

use App\Core\App;
use App\Core\Router;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\InstallService;

use App\Controllers\InstallController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\BounceController;
use App\Controllers\SuppressionController;
use App\Controllers\ApiController;

use App\Controllers\Admin\AdminController;
use App\Controllers\Admin\MailboxController;
use App\Controllers\Admin\GraphController;
use App\Controllers\Admin\SystemController;
use App\Controllers\Admin\SecurityController;
use App\Controllers\Admin\LogController;

/** @var Router $router */

// ----------------------------------------------------------
// If app is NOT installed, force install wizard for ALL requests
// (except for static assets which the .htaccess passes through).
// ----------------------------------------------------------
if (!App::instance()->isInstalled() || !InstallService::isLocked()) {
    $router->get('/',                   [InstallController::class, 'welcome']);
    $router->get('/install',            [InstallController::class, 'welcome']);
    $router->any('/install/requirements', [InstallController::class, 'requirements']);
    $router->any('/install/database',   [InstallController::class, 'database']);
    $router->any('/install/graph',      [InstallController::class, 'graph']);
    $router->any('/install/security',   [InstallController::class, 'security']);
    $router->any('/install/finish',     [InstallController::class, 'finish']);
    $router->get('/install/reset',      [InstallController::class, 'reset']);

    // API endpoint should still respond with a sensible error, not a render
    $router->any('/api/check', function (Request $req) {
        Response::json(['error' => 'Application is not installed. Run /install first.'], 503);
    });
    $router->any('/check.php', function (Request $req) {
        Response::json(['error' => 'Application is not installed. Run /install first.'], 503);
    });

    // Anything else → installer
    $router->any('/{any:.*}', function (Request $req) {
        Response::redirect(App::instance()->baseUrl('/install'));
    });
    return;
}

// ----------------------------------------------------------
// Block install routes after installation (idempotent guard).
// ----------------------------------------------------------
$router->any('/install',                fn(Request $r) => Response::redirect(App::instance()->baseUrl('/dashboard')));
$router->any('/install/{any:.*}',       fn(Request $r) => Response::redirect(App::instance()->baseUrl('/dashboard')));

// ----------------------------------------------------------
// Public auth
// ----------------------------------------------------------
$router->get ('/',         fn(Request $r) => Response::redirect(App::instance()->baseUrl('/dashboard')));
$router->get ('/login',        [AuthController::class, 'showLogin']);
$router->post('/login',        [AuthController::class, 'login']);
$router->get ('/admin/login',  [AuthController::class, 'showAdminLogin']);
$router->post('/admin/login',  [AuthController::class, 'adminLogin']);
$router->any ('/logout',       [AuthController::class, 'logout']);

// ----------------------------------------------------------
// Viewer area
// ----------------------------------------------------------
$router->get ('/dashboard',           [DashboardController::class, 'index']);
$router->get ('/bounces',             [BounceController::class, 'index']);
$router->get ('/bounces/details',     [BounceController::class, 'details']);

$router->get ('/suppression',         [SuppressionController::class, 'index']);
$router->post('/suppression/add',     [SuppressionController::class, 'add']);
$router->post('/suppression/remove',  [SuppressionController::class, 'remove']);
$router->post('/suppression/clear',           [SuppressionController::class, 'clear']);
$router->post('/suppression/reset-counts',    [SuppressionController::class, 'resetCounts']);
$router->post('/suppression/purge-ndrs',      [SuppressionController::class, 'purgeNdrs']);

// ----------------------------------------------------------
// Admin area
// ----------------------------------------------------------
$router->get ('/admin',                       [AdminController::class, 'index']);

$router->get ('/admin/mailboxes',             [MailboxController::class, 'index']);
$router->post('/admin/mailboxes/store',       [MailboxController::class, 'store']);
$router->post('/admin/mailboxes/update',      [MailboxController::class, 'update']);
$router->post('/admin/mailboxes/toggle',      [MailboxController::class, 'toggle']);
$router->post('/admin/mailboxes/destroy',     [MailboxController::class, 'destroy']);
$router->post('/admin/mailboxes/test',        [MailboxController::class, 'test']);
$router->post('/admin/mailboxes/clear-cache', [MailboxController::class, 'clearCache']);

$router->get ('/admin/graph',                 [GraphController::class, 'index']);
$router->post('/admin/graph/update',          [GraphController::class, 'update']);

$router->get ('/admin/system',                [SystemController::class, 'index']);
$router->post('/admin/system/update',         [SystemController::class, 'update']);
$router->post('/admin/system/flush-cache',    [SystemController::class, 'flushCache']);

$router->get ('/admin/security',              [SecurityController::class, 'index']);
$router->post('/admin/security/password',     [SecurityController::class, 'changePassword']);
$router->post('/admin/security/rotate-key',   [SecurityController::class, 'rotateApiKey']);
$router->post('/admin/security/limits',       [SecurityController::class, 'updateLimits']);

$router->get ('/admin/logs',                  [LogController::class, 'index']);
$router->post('/admin/logs/prune',            [LogController::class, 'prune']);

// ----------------------------------------------------------
// External API (used by sending systems like LIS)
// ----------------------------------------------------------
$router->any ('/api/check',                   [ApiController::class, 'check']);
$router->any ('/check.php',                   [ApiController::class, 'check']);   // backward-compat
