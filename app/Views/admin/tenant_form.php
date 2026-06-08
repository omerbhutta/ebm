<?php
use App\Core\App;
$app = App::instance();
$isEdit = $tenant !== null;
$action = $isEdit ? htmlspecialchars($app->baseUrl('/admin/tenants/update')) : htmlspecialchars($app->baseUrl('/admin/tenants/store'));
$title  = $isEdit ? 'Edit tenant' : 'Add tenant';
?>
<?php $subtitle = $isEdit ? 'Update tenant Graph credentials and settings.' : 'Register a new tenant with Microsoft Graph credentials.'; include __DIR__ . '/../partials/page-header.php'; ?>

<section class="card">
  <div class="card__head"><h3 class="card__title"><?= $title ?></h3></div>
  <div class="card__body">
    <form method="post" action="<?= $action ?>" class="form">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$tenant['id'] ?>">
      <?php endif; ?>

      <label class="form__field">
        <span class="form__label">Tenant name <span class="muted">*</span></span>
        <input class="form__control" type="text" name="name" required maxlength="255" value="<?= htmlspecialchars($isEdit ? $tenant['name'] : '') ?>" placeholder="e.g. Client ABC Corp">
      </label>
      <label class="form__field">
        <span class="form__label">Microsoft Tenant ID (Directory ID)</span>
        <input class="form__control" type="text" name="tenant_id" value="<?= htmlspecialchars($isEdit ? $tenant['tenant_id'] : '') ?>" placeholder="00000000-0000-0000-0000-000000000000" pattern="[0-9a-fA-F\-]{36}">
        <small class="muted">Leave blank to use the global Graph credentials from Settings.</small>
      </label>
      <label class="form__field">
        <span class="form__label">Client ID (Application ID)</span>
        <input class="form__control" type="text" name="client_id" value="<?= htmlspecialchars($isEdit ? $tenant['client_id'] : '') ?>" placeholder="00000000-0000-0000-0000-000000000000" pattern="[0-9a-fA-F\-]{36}">
      </label>
      <label class="form__field">
        <span class="form__label">Client secret</span>
        <input class="form__control" type="password" name="client_secret" value="" <?= $isEdit ? '' : 'required' ?> minlength="8" autocomplete="off">
        <?php if ($isEdit): ?>
          <small class="muted">Leave blank to keep the current secret.</small>
        <?php else: ?>
          <small class="muted">Client secret for this tenant's app registration.</small>
        <?php endif; ?>
      </label>
      <label class="form__field">
        <span class="form__label">Notes (optional)</span>
        <textarea class="form__control" name="notes" rows="2" maxlength="500"><?= htmlspecialchars($isEdit ? ($tenant['notes'] ?? '') : '') ?></textarea>
      </label>

      <div class="form__row">
        <button class="btn btn--primary" type="submit"><?= $isEdit ? 'Update tenant' : 'Add tenant' ?></button>
        <a class="btn btn--ghost" href="<?= htmlspecialchars($app->baseUrl('/admin/tenants')) ?>">Cancel</a>
      </div>
    </form>
  </div>
</section>

<?php if (!$isEdit): ?>
<section class="card">
  <div class="card__head"><h3 class="card__title">Setup checklist</h3></div>
  <div class="card__body">
    <ol class="checklist">
      <li>Register an application in <a href="https://entra.microsoft.com" target="_blank" rel="noopener">Microsoft Entra admin center</a> &raquo; <em>Identity &raquo; Applications &raquo; App registrations</em>.</li>
      <li>Copy the <strong>Tenant ID</strong> (Directory ID) and <strong>Client ID</strong> (Application ID) from the Overview page.</li>
      <li>Create a <strong>Client secret</strong> in <em>Certificates &amp; secrets</em> with at least 12 months expiry &mdash; copy the <em>Value</em> (not the ID).</li>
      <li>Grant <strong>API permissions &raquo; Microsoft Graph &raquo; Application permissions &raquo; Mail.Read</strong>. Then <em>Grant admin consent</em>.</li>
      <li>Add mailboxes under this tenant &mdash; each mailbox must be in the tenant's Microsoft 365 organisation.</li>
    </ol>
  </div>
</section>
<?php endif; ?>
