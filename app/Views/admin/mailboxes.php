<?php
use App\Core\App;
use App\Services\BounceService;
$app = App::instance();
?>
<?php $subtitle = 'Microsoft 365 mailboxes EBM scans for NDRs.'; include __DIR__ . '/../partials/page-header.php'; ?>

<section class="card">
  <div class="card__head">
    <h3 class="card__title">Add a mailbox</h3>
  </div>
  <div class="card__body">
    <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes/store')) ?>" class="form form--inline">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <label class="form__field">
        <span class="form__label">Email address</span>
        <input class="form__control" type="email" name="email" required placeholder="bounces@example.com">
      </label>
      <label class="form__field">
        <span class="form__label">Tenant</span>
        <select class="form__control" name="tenant_id">
          <?php if (empty($tenants)): ?>
            <option value="0">Default (global credentials)</option>
          <?php else: foreach ($tenants as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; endif; ?>
        </select>
      </label>
      <label class="form__field">
        <span class="form__label">Description (optional)</span>
        <input class="form__control" type="text" name="description" maxlength="255" placeholder="e.g. Primary sending account">
      </label>
      <div class="form__field">
        <button class="btn btn--primary" type="submit">Add mailbox</button>
      </div>
    </form>
    <p class="muted small">The user must have a valid Microsoft 365 license and an Exchange Online mailbox accessible by the app registration. See <a href="<?= htmlspecialchars($app->baseUrl('/admin/graph')) ?>">Graph API</a> or <a href="<?= htmlspecialchars($app->baseUrl('/admin/tenants')) ?>">Tenants</a> to verify credentials.</p>
  </div>
</section>

<section class="card">
  <div class="card__head">
    <h3 class="card__title"><?= number_format((int)($total ?? count($mailboxes))) ?> monitored mailboxes</h3>
    <form method="get" action="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes')) ?>" class="filter-bar__row" style="margin:0;gap:8px">
      <label class="form__field" style="min-width:auto">
        <span class="form__label" style="display:inline">Per page</span>
        <select class="form__control form__control--sm" name="per" onchange="this.form.submit()">
          <?php foreach ([5,10,25,50,100] as $n): ?>
            <option value="<?= $n ?>" <?= (int)($per ?? 25) === $n ? 'selected' : '' ?>><?= $n ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  </div>
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="table">
      <thead>
        <tr>
          <th>Address</th>
          <th>Tenant</th>
          <th>Status</th>
          <th>Last sync</th>
          <th>Last error</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($mailboxes)): ?>
        <tr><td colspan="6" class="muted center">No mailboxes configured yet.</td></tr>
      <?php else: foreach ($mailboxes as $m): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($m['email']) ?></strong>
            <?php if (!empty($m['description'])): ?><div class="muted small"><?= htmlspecialchars($m['description']) ?></div><?php endif; ?>
          </td>
          <td class="muted small"><?= htmlspecialchars($m['tenant_name'] ?? 'Default') ?></td>
          <td>
            <?php if ((int)$m['is_active'] === 1): ?>
              <span class="badge badge--ok">Active</span>
            <?php else: ?>
              <span class="badge badge--warn">Paused</span>
            <?php endif; ?>
          </td>
          <td class="muted small nowrap"><?= htmlspecialchars(BounceService::formatDate($m['last_synced_at'] ?? null)) ?></td>
          <td class="muted small"><?= htmlspecialchars(mb_strimwidth((string)($m['last_error'] ?? ''), 0, 80, '…')) ?: '—' ?></td>
          <td class="t-right nowrap">
            <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes/test')) ?>" class="inline-form">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn--ghost btn--sm" type="submit" title="Test Graph connection">Test</button>
            </form>
            <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes/clear-cache')) ?>" class="inline-form">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn--ghost btn--sm" type="submit" title="Clear cached NDRs">Clear</button>
            </form>
            <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes/toggle')) ?>" class="inline-form">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn--ghost btn--sm" type="submit"><?= (int)$m['is_active'] === 1 ? 'Pause' : 'Resume' ?></button>
            </form>
            <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes/destroy')) ?>" class="inline-form" data-confirm="Permanently remove this mailbox?">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
              <button class="btn btn--danger btn--sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </div>
  <?php if (($pages ?? 1) > 1): $pager = [
    'page'  => (int)($page ?? 1),
    'pages' => (int)$pages,
    'path'  => '/admin/mailboxes',
    'base'  => ['per'=>(int)($per ?? 25)],
  ]; include __DIR__ . '/../partials/pager.php'; endif; ?>
</section>
