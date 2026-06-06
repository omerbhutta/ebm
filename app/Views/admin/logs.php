<?php
use App\Core\App;
use App\Services\BounceService;
$app = App::instance();
?>
<?php $subtitle = 'Audit & diagnostic events.'; include __DIR__ . '/../partials/page-header.php'; ?>

<form method="get" action="<?= htmlspecialchars($app->baseUrl('/admin/logs')) ?>" class="filter-bar">
  <div class="filter-bar__row">
    <label class="form__field">
      <span class="form__label">Level</span>
      <select class="form__control" name="level">
        <option value="">All</option>
        <?php foreach (['info','warning','error','security'] as $lvl): ?>
          <option value="<?= $lvl ?>" <?= ($level ?? '') === $lvl ? 'selected' : '' ?>><?= ucfirst($lvl) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form__field">
      <span class="form__label">Limit</span>
      <input class="form__control" type="number" name="limit" min="10" max="1000" value="<?= (int)($limit ?? 200) ?>">
    </label>
    <div class="form__field filter-bar__actions">
      <button class="btn btn--primary" type="submit">Apply</button>
    </div>
  </div>
</form>

<section class="card">
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="table">
      <thead>
        <tr><th>Time</th><th>Level</th><th>Event</th><th>User</th><th>IP</th><th>Message</th></tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="muted center">No log entries.</td></tr>
        <?php else: foreach ($logs as $l): ?>
          <tr>
            <td class="muted small nowrap"><?= htmlspecialchars(BounceService::formatDate($l['created_at'] ?? null, 'Y-m-d H:i:s')) ?></td>
            <td><span class="badge badge--<?= htmlspecialchars($l['level'] ?? 'info') ?>"><?= htmlspecialchars(strtoupper($l['level'] ?? 'INFO')) ?></span></td>
            <td><code><?= htmlspecialchars($l['event'] ?? '') ?></code></td>
            <td><?= htmlspecialchars($l['user_label'] ?? 'system') ?></td>
            <td class="muted small"><?= htmlspecialchars($l['ip'] ?? '—') ?></td>
            <td class="muted small"><?= htmlspecialchars(mb_strimwidth((string)($l['message'] ?? ''), 0, 160, '…')) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</section>

<section class="card">
  <div class="card__head"><h3 class="card__title">Maintenance</h3></div>
  <div class="card__body">
    <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/logs/prune')) ?>" class="form form--inline" data-confirm="Prune activity log entries older than the chosen age?">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <label class="form__field">
        <span class="form__label">Prune older than (days)</span>
        <input class="form__control" type="number" name="days" min="1" value="30" required>
      </label>
      <button class="btn btn--danger" type="submit">Prune</button>
    </form>
  </div>
</section>
