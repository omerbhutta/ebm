<?php
use App\Core\App;
use App\Services\BounceService;
$app = App::instance();
?>
<?php include __DIR__ . '/../partials/page-header.php'; ?>

<form method="get" action="<?= htmlspecialchars($app->baseUrl('/suppression')) ?>" class="filter-toolbar">
  <div class="filter-toolbar__search">
    <svg class="filter-toolbar__search-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="11" cy="11" r="7"></circle>
      <path d="m21 21-4.3-4.3"></path>
    </svg>
    <input class="form__control filter-toolbar__search-input" type="search" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Search email address…" aria-label="Search suppression list">
  </div>

  <div class="filter-toolbar__per">
    <label class="filter-toolbar__per-label" for="perSelect">Show</label>
    <select class="form__control form__control--sm filter-toolbar__per-select" name="per" id="perSelect">
      <?php foreach ([10,25,50,100,200] as $n): ?>
        <option value="<?= $n ?>" <?= (int)($per ?? 50) === $n ? 'selected' : '' ?>><?= $n ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <button class="btn btn--primary btn--sm filter-toolbar__apply" type="submit">Apply</button>
  <a class="btn btn--ghost btn--sm filter-toolbar__reset" href="<?= htmlspecialchars($app->baseUrl('/suppression')) ?>">Reset</a>

  <span class="filter-toolbar__divider" aria-hidden="true"></span>

  <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/suppression')) ?>?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Export CSV</a>
  <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/suppression')) ?>?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'xls']))) ?>">Export Excel</a>

  <?php if (\App\Core\Auth::isAdmin()): ?>
  <button class="btn btn--primary btn--sm" type="button" data-modal-open="#addEmail">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
    Add
  </button>
  <?php endif; ?>
</form>

<section class="card">
  <div class="card__head">
    <h3 class="card__title"><?= number_format((int)($total ?? 0)) ?> suppressed addresses</h3>
  </div>
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Address</th>
            <th class="t-right">Bounces</th>
            <th>First seen</th>
            <th>Last seen</th>
            <?php if (\App\Core\Auth::isAdmin()): ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="<?= \App\Core\Auth::isAdmin() ? 5 : 4 ?>" class="muted center">No suppressed addresses yet.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['email']) ?></strong></td>
              <td class="t-right"><?= number_format((int)$r['bounce_count']) ?></td>
              <td class="muted small nowrap"><?= htmlspecialchars(BounceService::formatDate($r['first_seen'] ?? null, 'Y-m-d H:i')) ?></td>
              <td class="muted small nowrap"><?= htmlspecialchars(BounceService::formatDate($r['last_seen'] ?? null, 'Y-m-d H:i')) ?></td>
              <?php if (\App\Core\Auth::isAdmin()): ?>
              <td class="t-right nowrap">
                <form method="post" action="<?= htmlspecialchars($app->baseUrl('/suppression/remove')) ?>" class="inline-form" data-confirm="Remove this address from the suppression list?">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn btn--danger btn--sm" type="submit">Remove</button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<?php if (($pages ?? 1) > 1): $pager = [
  'page'  => (int)($page ?? 1),
  'pages' => (int)$pages,
  'path'  => '/suppression',
  'base'  => ['q'=>$q??'','per'=>(int)($per ?? 50)],
]; include __DIR__ . '/../partials/pager.php'; endif; ?>

<?php if (\App\Core\Auth::isAdmin()): ?>
<dialog id="addEmail" class="dialog">
  <div class="dialog__inner">
    <header class="dialog__head">
      <h3>Add to suppression</h3>
      <button class="iconbtn" type="button" data-modal-close aria-label="Close">✕</button>
    </header>
    <div class="dialog__body">
      <form method="post" action="<?= htmlspecialchars($app->baseUrl('/suppression/add')) ?>" class="form" id="addEmailForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <label class="form__field">
          <span class="form__label">Email address</span>
          <input class="form__control" type="email" name="email" required autofocus>
        </label>
        <div class="form__row t-right">
          <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
          <button type="submit" class="btn btn--primary">Add</button>
        </div>
      </form>
    </div>
  </div>
</dialog>
<?php endif; ?>

<?php if (\App\Core\Auth::isAdmin()): ?>
<section class="card">
  <div class="card__head"><h3 class="card__title">Bulk operations</h3></div>
  <div class="card__body">
    <div class="row-actions">
      <form method="post" action="<?= htmlspecialchars($app->baseUrl('/suppression/clear')) ?>" class="inline-form" data-confirm="Permanently delete ALL suppression entries? This cannot be undone.">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <button class="btn btn--danger" type="submit">Clear entire list</button>
      </form>
      <form method="post" action="<?= htmlspecialchars($app->baseUrl('/suppression/reset-counts')) ?>" class="inline-form" data-confirm="Reset bounce_count to 0 for all entries?">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <button class="btn btn--ghost" type="submit">Reset bounce counts</button>
      </form>
      <form method="post" action="<?= htmlspecialchars($app->baseUrl('/suppression/purge-ndrs')) ?>" class="inline-form" data-confirm="Delete the processed NDR cache? Suppression entries are kept.">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <button class="btn btn--ghost" type="submit">Purge NDR cache</button>
      </form>
    </div>
  </div>
</section>
<?php endif; ?>
