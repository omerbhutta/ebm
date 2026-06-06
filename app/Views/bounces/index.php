<?php
use App\Core\App;
use App\Services\BounceService;
$app = App::instance();
?>
<?php include __DIR__ . '/../partials/page-header.php'; ?>

<form method="get" action="<?= htmlspecialchars($app->baseUrl('/bounces')) ?>" class="filter-bar">
  <div class="filter-bar__row">
    <label class="form__field" style="flex:1 1 240px">
      <span class="form__label">Search</span>
      <input class="form__control" type="search" name="q" value="<?= htmlspecialchars($q ?? '') ?>" placeholder="Subject, sender, recipient…">
    </label>
    <label class="form__field">
      <span class="form__label">Folder</span>
      <select class="form__control" name="folder">
        <option value="">All folders</option>
        <?php foreach (($folders ?? []) as $f): ?>
          <option value="<?= htmlspecialchars($f) ?>" <?= ($folder ?? '') === $f ? 'selected' : '' ?>><?= htmlspecialchars($f) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form__field">
      <span class="form__label">Mailbox</span>
      <select class="form__control" name="mailbox">
        <option value="">All mailboxes</option>
        <?php foreach (($mailboxes ?? []) as $m): ?>
          <option value="<?= htmlspecialchars($m['email']) ?>" <?= ($mailbox ?? '') === $m['email'] ? 'selected' : '' ?>><?= htmlspecialchars($m['email']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form__field">
      <span class="form__label">Sort</span>
      <select class="form__control" name="sort">
        <option value="date_desc"     <?= ($sort ?? '')==='date_desc'?'selected':'' ?>>Newest first</option>
        <option value="date_asc"      <?= ($sort ?? '')==='date_asc'?'selected':'' ?>>Oldest first</option>
        <option value="subject_asc"   <?= ($sort ?? '')==='subject_asc'?'selected':'' ?>>Subject A→Z</option>
        <option value="subject_desc"  <?= ($sort ?? '')==='subject_desc'?'selected':'' ?>>Subject Z→A</option>
        <option value="mailbox_asc"   <?= ($sort ?? '')==='mailbox_asc'?'selected':'' ?>>Mailbox A→Z</option>
      </select>
    </label>
    <label class="form__field">
      <span class="form__label">Per page</span>
      <select class="form__control" name="per">
        <?php foreach ([10,25,50,100,200] as $n): ?>
          <option value="<?= $n ?>" <?= (int)($per_page ?? 25) === $n ? 'selected' : '' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="form__field filter-bar__actions">
      <button class="btn btn--primary" type="submit">Apply</button>
      <a class="btn btn--ghost" href="<?= htmlspecialchars($app->baseUrl('/bounces')) ?>">Reset</a>
    </div>
  </div>
  <div class="filter-bar__row">
    <div class="muted small">
      <?php
        $fromTotal = (int)($total ?? 0) > 0 ? ((($page ?? 1) - 1) * (int)($per_page ?? 25)) + 1 : 0;
        $ofTotal = ($fromTotal > 0)
          ? min((int)($fromTotal) + count($rows ?? []) - 1, (int)($total ?? 0))
          : 0;
      ?>
      Showing <?= number_format($fromTotal) ?>–<?= number_format($ofTotal) ?> of <?= number_format((int)($total ?? 0)) ?>
    </div>
  </div>
</form>

<div class="table-actions">
  <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/bounces')) ?>?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>">Export CSV</a>
  <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/bounces')) ?>?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'xls']))) ?>">Export Excel</a>
</div>

<?php if (!empty($errors)): ?>
<section class="card card--error">
  <div class="card__head"><h3 class="card__title">Sync errors</h3></div>
  <div class="card__body">
    <ul class="muted small"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <div class="card__body card__body--flush">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Received</th>
            <th>Subject</th>
            <th>Failed recipient</th>
            <th>Mailbox</th>
            <th>Folder</th>
            <th class="t-right">Body</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="muted center">No bounce records found.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr data-message-id="<?= htmlspecialchars($r['id']) ?>"
              data-mailbox="<?= htmlspecialchars($r['mailbox']) ?>"
              data-folder="<?= htmlspecialchars($r['folder']) ?>"
              data-csrf="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
            <td class="muted small nowrap"><?= htmlspecialchars(BounceService::formatDate($r['date'] ?? null)) ?></td>
            <td><strong><?= htmlspecialchars($r['subject'] ?? '(no subject)') ?></strong></td>
            <td class="truncate" style="max-width: 240px"><?= htmlspecialchars($r['failed_str'] ?? '') ?></td>
            <td class="muted small nowrap" title="<?= htmlspecialchars($r['mailbox'] ?? '') ?>"><?= htmlspecialchars($r['mailbox'] ?? '—') ?></td>
            <td class="muted small nowrap"><?= htmlspecialchars($r['folder'] ?? '—') ?></td>
            <td class="t-right nowrap">
              <button type="button" class="btn btn--ghost btn--sm js-view-body"
                      data-message-id="<?= htmlspecialchars($r['id']) ?>"
                      data-mailbox="<?= htmlspecialchars($r['mailbox']) ?>"
                      data-folder="<?= htmlspecialchars($r['folder']) ?>">
                View
              </button>
            </td>
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
  'path'  => '/bounces',
  'base'  => ['q'=>$q??'','folder'=>$folder??'','mailbox'=>$mailbox??'','sort'=>$sort??'date_desc','per'=>(int)($per_page ?? 25)],
]; include __DIR__ . '/../partials/pager.php'; endif; ?>

<dialog id="detailsDialog" class="dialog">
  <form method="dialog" class="dialog__inner">
    <header class="dialog__head">
      <h3 id="detailsTitle">Bounce details</h3>
      <button class="iconbtn" type="submit" value="close" aria-label="Close">✕</button>
    </header>
    <div class="dialog__body" id="detailsBody">
      <p class="muted">Loading…</p>
    </div>
  </form>
</dialog>
