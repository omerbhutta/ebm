<?php
/**
 * Backward-compatibility shim for /check.php
 *
 * Older sending integrations may already call /check.php directly.
 * Forward those requests into the new ApiController without breaking the URL contract.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$req = new App\Core\Request();
$controller = new App\Controllers\ApiController();
$controller->check($req);
