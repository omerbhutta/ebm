<?php
/** @var \App\Core\App $app */
/** @var string $title */
/** @var string $body */

use App\Core\App;
use App\Core\Settings;
use App\Core\Auth;

$app = App::instance();
$appName = (string)Settings::get('app_name', 'Email Bounce Monitor');
$appTag  = (string)Settings::get('app_tagline', 'Block bad addresses, keep delivery clean.');
$footerText = (string)Settings::get('footer_text', 'Powered by E-Services 360');
$footerUrl  = (string)Settings::get('footer_url', 'https://eservices360.com');
$themeDefault = (string)Settings::get('theme', 'dark');
$themeToggle  = ((string)Settings::get('theme_toggle_enabled', '1')) === '1';
$loginToggle  = ((string)Settings::get('login_toggle_enabled', '1')) === '1';
$assetBase    = $app->baseUrl('/assets');
$csrf         = \App\Core\Csrf::token();
$currentUser  = $_SESSION['user'] ?? [];
$displayName  = ucfirst($currentUser['role'] ?? 'User');
$displayRole  = ucfirst($currentUser['role'] ?? 'viewer');
$displayInitial = strtoupper(substr($displayName, 0, 1));
?><!doctype html>
<html lang="en" data-theme="<?= htmlspecialchars($themeDefault) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="referrer" content="strict-origin-when-cross-origin">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars(($title ?? '') . ' · ' . $appName) ?></title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%2306b6d4'/%3E%3Ctext x='16' y='22' font-size='18' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='700'%3EE%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= htmlspecialchars($assetBase) ?>/css/app.css">
</head>
<body class="layout-app" data-theme-toggle="<?= $themeToggle ? '1' : '0' ?>" data-login-toggle="<?= $loginToggle ? '1' : '0' ?>" data-csrf="<?= htmlspecialchars($csrf) ?>" data-base-url="<?= htmlspecialchars($app->baseUrl()) ?>">

<header class="navbar" role="banner">
  <a class="navbar__brand" href="<?= htmlspecialchars($app->baseUrl('/dashboard')) ?>">
    <span class="navbar__logo" aria-hidden="true">
      <svg viewBox="0 0 32 32" width="26" height="26"><rect width="32" height="32" rx="7" fill="var(--accent)"/><text x="16" y="22" text-anchor="middle" font-size="18" fill="white" font-family="sans-serif" font-weight="700">E</text></svg>
    </span>
    <span class="navbar__title">
      <strong><?= htmlspecialchars($appName) ?></strong>
      <small><?= htmlspecialchars($appTag) ?></small>
    </span>
  </a>

  <nav class="navbar__nav" aria-label="Primary navigation">
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/dashboard')) ?>" data-match="/dashboard">
      <span class="icon">▦</span><span>Dashboard</span>
    </a>
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/bounces')) ?>" data-match="/bounces">
      <span class="icon">✉</span><span>Bounce Log</span>
    </a>
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/suppression')) ?>" data-match="/suppression">
      <span class="icon">⊘</span><span>Suppression</span>
    </a>
    <?php if (Auth::isAdmin()): ?>
    <span class="navbar__sep" aria-hidden="true"></span>
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/admin')) ?>" data-match="/admin" data-match-exact="1">
      <span class="icon">⚙</span><span>Overview</span>
    </a>
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes')) ?>" data-match="/admin/mailboxes">
      <span class="icon">📬</span><span>Mailboxes</span>
    </a>
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/admin/graph')) ?>" data-match="/admin/graph">
      <span class="icon">🔌</span><span>Graph</span>
    </a>
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/admin/system')) ?>" data-match="/admin/system">
      <span class="icon">🛠</span><span>System</span>
    </a>
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/admin/security')) ?>" data-match="/admin/security">
      <span class="icon">🔒</span><span>Security</span>
    </a>
    <a class="navbar__link" href="<?= htmlspecialchars($app->baseUrl('/admin/logs')) ?>" data-match="/admin/logs">
      <span class="icon">📋</span><span>Logs</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="navbar__right">
    <?php if ($themeToggle): ?>
    <button class="iconbtn" id="themeToggle" aria-label="Toggle theme" title="Toggle dark/light">
      <span class="theme-icon-dark">☾</span><span class="theme-icon-light">☀</span>
    </button>
    <?php endif; ?>
    <div class="navbar__user" title="<?= htmlspecialchars($displayName) ?> (<?= htmlspecialchars($displayRole) ?>)">
      <span class="avatar" aria-hidden="true"><?= htmlspecialchars($displayInitial) ?></span>
      <span class="navbar__user-meta">
        <strong><?= htmlspecialchars($displayName) ?></strong>
        <small><?= htmlspecialchars($displayRole) ?></small>
      </span>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/logout')) ?>">Sign out</a>
  </div>
</header>

<main class="main" id="main" tabindex="-1">
  <div class="main__inner">
    <?php if (!empty($_flash)): ?>
      <div class="flash-stack" role="status" aria-live="polite">
        <?php foreach ($_flash as $f):
          $ft = htmlspecialchars($f['type'] ?? 'info');
          $fm = $f['message'] ?? ''; ?>
          <div class="flash flash--<?= $ft ?>"><?= $fm ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?= $body ?? '' ?>
  </div>
  <footer class="footer">
    <?php if ($footerUrl !== ''): ?>
      <a href="<?= htmlspecialchars($footerUrl) ?>" rel="noopener noreferrer" target="_blank"><?= htmlspecialchars($footerText) ?></a>
    <?php else: ?>
      <span><?= htmlspecialchars($footerText) ?></span>
    <?php endif; ?>
  </footer>
</main>

<div id="flash-container" class="flash-container" aria-live="polite" aria-atomic="true"></div>

<script src="<?= htmlspecialchars($assetBase) ?>/js/app.js" defer></script>
</body>
</html>
