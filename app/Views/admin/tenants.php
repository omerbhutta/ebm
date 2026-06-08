<?php
use App\Core\App;
$app = App::instance();
?>
<?php $subtitle = 'Multi-tenant Graph API credentials and mailbox grouping.'; include __DIR__ . '/../partials/page-header.php'; ?>

<section class="card">
  <div class="card__head">
    <h3 class="card__title"><?= number_format(count($tenants)) ?> tenants</h3>
    <a class="btn btn--primary btn--sm" href="<?= htmlspecialchars($app->baseUrl('/admin/tenants/add')) ?>">+ Add tenant</a>
  </div>
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Tenant ID</th>
          <th>Mailboxes</th>
          <th>Status</th>
          <th>Default</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($tenants)): ?>
        <tr><td colspan="7" class="muted center">No tenants configured yet.</td></tr>
      <?php else: foreach ($tenants as $t): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($t['name']) ?></strong>
            <?php if (!empty($t['notes'])): ?><div class="muted small"><?= htmlspecialchars($t['notes']) ?></div><?php endif; ?>
          </td>
          <td class="muted small"><?= htmlspecialchars($t['tenant_id'] ?: '—') ?></td>
          <td><?= (int)($t['mailbox_count'] ?? 0) ?></td>
          <td>
            <?php if ((int)$t['is_active'] === 1): ?>
              <span class="badge badge--ok">Active</span>
            <?php else: ?>
              <span class="badge badge--warn">Paused</span>
            <?php endif; ?>
          </td>
          <td><?= (int)$t['is_default'] === 1 ? '⭐' : '' ?></td>
          <td class="muted small nowrap"><?= htmlspecialchars(\App\Services\BounceService::formatDate($t['created_at'] ?? null)) ?></td>
          <td class="t-right nowrap">
            <?php if ((int)$t['is_default'] !== 1): ?>
            <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/tenants/set-default')) ?>" class="inline-form">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn--ghost btn--sm" type="submit" title="Set as default">Set default</button>
            </form>
            <?php endif; ?>
            <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/admin/tenants/edit/' . (int)$t['id'])) ?>">Edit</a>
            <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/tenants/toggle')) ?>" class="inline-form">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn--ghost btn--sm" type="submit"><?= (int)$t['is_active'] === 1 ? 'Pause' : 'Resume' ?></button>
            </form>
            <?php if ((int)$t['is_default'] !== 1): ?>
            <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/tenants/destroy')) ?>" class="inline-form" data-confirm="Remove this tenant? Mailboxes will be unassigned.">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button class="btn btn--danger btn--sm" type="submit">Delete</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</section>
