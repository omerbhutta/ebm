<?php
/** @var string $path */
use App\Core\App;
$app = App::instance();
?><a class="back-link" href="<?= htmlspecialchars($app->baseUrl($path ?? '/dashboard')) ?>">← Back</a>
