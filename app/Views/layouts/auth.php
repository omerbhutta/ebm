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
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%23ff4a17'/%3E%3Ctext x='16' y='22' font-size='18' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='700'%3EE%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="<?= htmlspecialchars($assetBase) ?>/css/app.css">
</head>
<body class="layout-auth" data-csrf="<?= htmlspecialchars($csrf) ?>" data-base-url="<?= htmlspecialchars($app->baseUrl()) ?>">
<div class="bg-orbs" aria-hidden="true">
  <div class="bg-orb bg-orb--1"></div>
  <div class="bg-orb bg-orb--2"></div>
  <div class="bg-orb bg-orb--3"></div>
</div>
    <main class="auth-shell" role="main">
    <div class="auth-info-panel">
      <!-- Email tracking flow animation — positioned as background -->
      <div class="email-flow-svg-bg" aria-hidden="true">
        <svg viewBox="0 0 400 120" class="email-flow-svg">
          <defs>
            <linearGradient id="orangeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#ff4a17" />
              <stop offset="100%" stop-color="#ff6d43" />
            </linearGradient>
            <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
              <feGaussianBlur stdDeviation="3" result="blur" />
              <feComposite in="SourceGraphic" in2="blur" operator="over" />
            </filter>
          </defs>
          
          <!-- Path Lines -->
          <path d="M 60 55 L 180 55" stroke="var(--border)" stroke-width="2" stroke-dasharray="6,4" />
          <path d="M 220 55 L 340 55" stroke="var(--border)" stroke-width="2" stroke-dasharray="6,4" />
          
          <!-- Animated pulse/packets -->
          <circle r="5" fill="url(#orangeGrad)" filter="url(#glow)">
            <animateMotion dur="3s" repeatCount="indefinite" path="M 60 55 L 180 55" />
          </circle>
          
          <circle r="5" fill="#3b82f6" filter="url(#glow)">
            <animateMotion dur="3s" begin="1.5s" repeatCount="indefinite" path="M 220 55 L 340 55" />
          </circle>
          
          <!-- Nodes -->
          <circle cx="50" cy="55" r="16" fill="var(--bg-2)" stroke="var(--border)" stroke-width="2" />
          <text x="50" y="60" text-anchor="middle" font-size="14">✉</text>
          <text x="50" y="90" text-anchor="middle" font-size="10" fill="var(--text-soft)" font-family="Outfit" font-weight="600">Outbound</text>
          
          <circle cx="200" cy="55" r="18" fill="var(--surface-hi)" stroke="var(--accent)" stroke-width="2" style="filter: drop-shadow(0 0 6px var(--accent-glow));" />
          <text x="200" y="60" text-anchor="middle" font-size="14" fill="var(--accent)">⊘</text>
          <text x="200" y="90" text-anchor="middle" font-size="10" fill="var(--text-soft)" font-family="Outfit" font-weight="600">Bounce Filter</text>
          
          <circle cx="350" cy="55" r="16" fill="var(--bg-2)" stroke="var(--border)" stroke-width="2" />
          <text x="350" y="60" text-anchor="middle" font-size="14">📡</text>
          <text x="350" y="90" text-anchor="middle" font-size="10" fill="var(--text-soft)" font-family="Outfit" font-weight="600">EBM DB</text>
        </svg>
      </div>

      <h2 class="auth-info-title">Email Bounce Monitor</h2>
      <p class="auth-info-desc">A self-hosted enterprise platform that connects to Microsoft 365 mailboxes to track bounce hits, build automatic suppression records, and protect domain sending reputations.</p>
      
      <ul class="auth-feature-list">
        <li class="auth-feature-item">
          <span class="auth-feature-icon" aria-hidden="true">📡</span>
          <div class="auth-feature-content">
            <h4>Microsoft Graph Telemetry</h4>
            <p>Connects securely to Exchange Online to automate mailbox scans without storing user passwords.</p>
          </div>
        </li>
        <li class="auth-feature-item">
          <span class="auth-feature-icon" aria-hidden="true">⊘</span>
          <div class="auth-feature-content">
            <h4>Automated Suppression</h4>
            <p>Automatically populates, indexes, and prunes bad email addresses and domains in real time.</p>
          </div>
        </li>
        <li class="auth-feature-item">
          <span class="auth-feature-icon" aria-hidden="true">🔌</span>
          <div class="auth-feature-content">
            <h4>High-performance REST API</h4>
            <p>Easily query from external mailing scripts or CRM systems prior to executing campaigns.</p>
          </div>
        </li>
      </ul>
    </div>

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
