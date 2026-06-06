<?php
use App\Core\App;
$app = App::instance();
?>
<?php $subtitle = 'System overview & quick links.'; include __DIR__ . '/../partials/page-header.php'; ?>

<div class="stat-grid">
  <div class="stat-card stat-card--accent">
    <div class="stat-card__label">Suppressed addresses</div>
    <div class="stat-card__value"><?= number_format($supp_total) ?></div>
    <div class="stat-card__sub"><?= number_format($supp_bounces) ?> total bounces across all addresses</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">New (24 h)</div>
    <div class="stat-card__value"><?= number_format($supp_added_24h) ?></div>
    <div class="stat-card__sub">first seen in the last 24 h</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">New (7 d)</div>
    <div class="stat-card__value"><?= number_format($supp_added_7d) ?></div>
    <div class="stat-card__sub">first seen in the last 7 days</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">Mailboxes</div>
    <div class="stat-card__value"><?= number_format($mailboxes_active) ?><span class="stat-card__suf"> / <?= number_format($mailboxes_total) ?></span></div>
    <div class="stat-card__sub"><?= number_format($mailboxes_paused) ?> paused</div>
  </div>
</div>

<div class="grid-2">
  <section class="card">
    <div class="card__head"><h3 class="card__title">Quick links</h3></div>
    <div class="card__body">
      <ul class="quick-links">
        <li><a href="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes')) ?>">📬 Mailboxes <span class="muted small">add, pause, remove</span></a></li>
        <li><a href="<?= htmlspecialchars($app->baseUrl('/admin/graph')) ?>">🔌 Graph API <span class="muted small">tenant & credentials</span></a></li>
        <li><a href="<?= htmlspecialchars($app->baseUrl('/admin/system')) ?>">🛠 System <span class="muted small">name, theme, retention</span></a></li>
        <li><a href="<?= htmlspecialchars($app->baseUrl('/admin/security')) ?>">🔒 Security <span class="muted small">passwords, API key</span></a></li>
        <li><a href="<?= htmlspecialchars($app->baseUrl('/admin/logs')) ?>">📋 Activity log <span class="muted small">audit & diagnostics</span></a></li>
      </ul>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h3 class="card__title">Top blocked domains</h3></div>
    <div class="card__body">
      <?php if (empty($top_domains)): ?>
        <p class="muted">No data yet.</p>
      <?php else: ?>
        <ol class="rank-list">
          <?php foreach ($top_domains as $d): ?>
            <li>
              <span class="rank-list__domain">@<?= htmlspecialchars($d['domain']) ?></span>
              <span class="rank-list__bar"><span style="width: <?= min(100, (int)($d['pct'] ?? 0)) ?>%"></span></span>
              <span class="rank-list__count"><?= number_format((int)($d['count'] ?? 0)) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="card">
  <div class="card__head"><h3 class="card__title">Recent activity</h3></div>
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="table">
      <thead><tr><th>Time</th><th>Level</th><th>Event</th><th>User</th><th>Detail</th></tr></thead>
      <tbody>
        <?php if (empty($recent_logs)): ?>
          <tr><td colspan="5" class="muted center">No activity recorded yet.</td></tr>
        <?php else: foreach ($recent_logs as $l): ?>
          <tr>
            <td class="muted small nowrap"><?= htmlspecialchars(\App\Services\BounceService::formatDate($l['created_at'] ?? null, 'Y-m-d H:i')) ?></td>
            <td><span class="badge badge--<?= htmlspecialchars($l['level'] ?? 'info') ?>"><?= htmlspecialchars(strtoupper($l['level'] ?? 'INFO')) ?></span></td>
            <td><code><?= htmlspecialchars($l['event'] ?? '') ?></code></td>
            <td><?= htmlspecialchars($l['user_label'] ?? 'system') ?></td>
            <td class="muted small"><?= htmlspecialchars(mb_strimwidth((string)($l['message'] ?? ''), 0, 100, '…')) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</section>
