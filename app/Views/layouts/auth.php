<?php
/** @var \App\Core\App $app */
/** @var string $title */
/** @var string $body */

use App\Core\App;
use App\Core\Settings;

$app = App::instance();
$appName = (string)Settings::get('app_name', 'Email Bounce Monitor');
$appTag  = (string)Settings::get('app_tagline', 'Block bad addresses, keep delivery clean.');
$footerText = (string)Settings::get('footer_text', 'Powered by E-Services 360');
$footerUrl  = (string)Settings::get('footer_url', 'https://eservices360.com');
$themeDefault = (string)Settings::get('theme', 'dark');
$assetBase    = $app->baseUrl('/assets');
$csrf         = \App\Core\Csrf::token();
?><!doctype html>
<html lang="en" data-theme="<?= htmlspecialchars($themeDefault) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars(($title ?? 'Sign in') . ' · ' . $appName) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%2306b6d4'/%3E%3Ctext x='16' y='22' font-size='18' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='700'%3EE%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= htmlspecialchars($assetBase) ?>/css/app.css">
</head>
<body class="layout-auth" data-csrf="<?= htmlspecialchars($csrf) ?>" data-base-url="<?= htmlspecialchars($app->baseUrl()) ?>">
  <main class="auth-shell" role="main">
    <section class="auth-card">
      <div class="auth-card__brand">
        <span class="auth-card__logo" aria-hidden="true">
          <svg viewBox="0 0 32 32" width="40" height="40"><rect width="32" height="32" rx="7" fill="var(--accent)"/><text x="16" y="22" text-anchor="middle" font-size="18" fill="white" font-family="sans-serif" font-weight="700">E</text></svg>
        </span>
        <h1><?= htmlspecialchars($appName) ?></h1>
        <p><?= htmlspecialchars($appTag) ?></p>
      </div>
      <div class="auth-card__body">
        <?php if (!empty($_flash)): ?>
          <?php foreach ($_flash as $f):
            $ft = htmlspecialchars($f['type'] ?? 'info');
            $fm = $f['message'] ?? ''; ?>
            <div class="alert alert--<?= $ft ?>" role="alert"><?= $fm ?></div>
          <?php endforeach; ?>
        <?php endif; ?>
        <?= $body ?? '' ?>
      </div>
      <div class="auth-card__foot">
        <a href="<?= htmlspecialchars($footerUrl) ?>" rel="noopener noreferrer" target="_blank"><?= htmlspecialchars($footerText) ?></a>
      </div>
    </section>
  </main>
  <script src="<?= htmlspecialchars($assetBase) ?>/js/app.js" defer></script>
</body>
</html>
