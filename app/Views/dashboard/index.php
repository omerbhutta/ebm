<?php
use App\Core\App;
use App\Core\Auth;
use App\Services\BounceService;
$app = App::instance();
?>
<?php $subtitle = 'Snapshot of suppression, mailboxes, and recent activity.'; include __DIR__ . '/../partials/page-header.php'; ?>

<div class="stat-grid">
  <div class="stat-card stat-card--accent">
    <div class="stat-card__label">Suppressed addresses</div>
    <div class="stat-card__value"><?= number_format($total ?? 0) ?></div>
    <div class="stat-card__sub"><?= number_format($bounces ?? 0) ?> total bounce hits</div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">Added (last 24 h)</div>
    <div class="stat-card__value"><?= number_format($last24 ?? 0) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">Added (last 7 days)</div>
    <div class="stat-card__value"><?= number_format($last7 ?? 0) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-card__label">Monitored mailboxes</div>
    <div class="stat-card__value"><?= number_format($active_mailboxes ?? 0) ?><span class="stat-card__suf"> / <?= number_format($total_mailboxes ?? 0) ?></span></div>
    <div class="stat-card__sub"><?= ($total_mailboxes ?? 0) > 0 ? 'active / total' : 'none configured' ?></div>
  </div>
</div>

<div class="grid-2">
  <section class="card">
    <div class="card__head">
      <h3 class="card__title">Monitored mailboxes</h3>
      <?php if (Auth::isAdmin()): ?><a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes')) ?>">Manage</a><?php endif; ?>
    </div>
    <div class="card__body">
      <?php if (empty($mailboxes)): ?>
        <p class="muted">No mailboxes configured yet.
          <?php if (Auth::isAdmin()): ?>
            <a href="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes')) ?>">Add one now →</a>
          <?php endif; ?>
        </p>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Address</th><th>Status</th><th>Last sync</th><th class="t-right">Processed</th></tr></thead>
        <tbody>
        <?php foreach ($mailboxes as $m): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($m['email']) ?></strong>
              <?php if (!empty($m['description'])): ?><div class="muted small"><?= htmlspecialchars($m['description']) ?></div><?php endif; ?>
            </td>
            <td>
              <?php if ((int)$m['is_active'] === 1): ?>
                <span class="badge badge--ok">Active</span>
              <?php else: ?>
                <span class="badge badge--warn">Paused</span>
              <?php endif; ?>
            </td>
            <td class="muted small"><?= htmlspecialchars(BounceService::formatDate($m['last_synced_at'] ?? null)) ?></td>
            <td class="t-right"><?= number_format(0) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </section>

  <section class="card">
    <div class="card__head">
      <h3 class="card__title">Top blocked domains</h3>
      <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/suppression')) ?>">View all</a>
    </div>
    <div class="card__body">
      <?php if (empty($top_domains)): ?>
        <p class="muted">No suppression data yet — once you run a scan the top offenders will appear here.</p>
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
  <div class="card__head">
    <h3 class="card__title">Recent activity</h3>
    <?php if (Auth::isAdmin()): ?><a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/admin/logs')) ?>">All activity</a><?php endif; ?>
  </div>
  <div class="card__body">
    <?php if (empty($recent_logs)): ?>
      <p class="muted">No activity recorded yet.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Time</th><th>Level</th><th>Event</th><th>User</th><th>Detail</th></tr></thead>
        <tbody>
          <?php foreach ($recent_logs as $row): ?>
            <tr>
              <td class="muted small"><?= htmlspecialchars(BounceService::formatDate($row['created_at'] ?? null, 'Y-m-d H:i')) ?></td>
              <td><span class="badge badge--<?= htmlspecialchars($row['level'] ?? 'info') ?>"><?= htmlspecialchars(strtoupper($row['level'] ?? 'INFO')) ?></span></td>
              <td><code><?= htmlspecialchars($row['event'] ?? '') ?></code></td>
              <td><?= htmlspecialchars($row['user_label'] ?? 'system') ?></td>
              <td class="muted small"><?= htmlspecialchars(mb_strimwidth((string)($row['message'] ?? ''), 0, 80, '…')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>
