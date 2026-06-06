<?php
/**
 * Email Bounce Monitor (EBM) — Front Controller
 *
 * All HTTP requests flow through this file via the .htaccess rewrite rule.
 *
 *  - Static assets are served directly by Apache (rewrite condition).
 *  - On first visit, the user is sent through the installation wizard.
 *  - After installation, routes are dispatched to the appropriate controller.
 *
 * Powered by E-Services 360 — https://eservices360.com
 */

declare(strict_types=1);

$app = require __DIR__ . '/app/bootstrap.php';

$router = new App\Core\Router();
require __DIR__ . '/app/routes.php';

$request = new App\Core\Request();
$router->dispatch($request);
