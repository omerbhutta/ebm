<?php
use App\Core\App;
$app = App::instance();
?>
<?php $subtitle = 'Microsoft Graph credentials (application permissions, client-credentials flow).'; include __DIR__ . '/../partials/page-header.php'; ?>

<section class="card">
  <div class="card__head"><h3 class="card__title">Microsoft Graph credentials</h3></div>
  <div class="card__body">
    <p class="muted">These values come from an <strong>App registration</strong> in Microsoft Entra admin center. EBM uses the <code>client_credentials</code> flow with <code>Mail.Read</code> application permission to scan each mailbox.</p>
    <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/graph/update')) ?>" class="form">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

      <label class="form__field">
        <span class="form__label">Tenant ID (Directory ID)</span>
        <input class="form__control" type="text" name="tenant_id" value="<?= htmlspecialchars($tenant_id) ?>" required pattern="[0-9a-fA-F\-]{36}">
      </label>
      <label class="form__field">
        <span class="form__label">Client ID (Application ID)</span>
        <input class="form__control" type="text" name="client_id" value="<?= htmlspecialchars($client_id) ?>" required pattern="[0-9a-fA-F\-]{36}">
      </label>
      <label class="form__field">
        <span class="form__label">Client secret</span>
        <input class="form__control" type="password" name="client_secret" value="<?= htmlspecialchars($client_secret) ?>" required minlength="8" autocomplete="off">
        <small class="muted">Secrets are stored in the <code>settings</code> table. Treat them as production credentials.</small>
      </label>
      <div class="form__row">
        <button class="btn btn--primary" type="submit" name="action" value="save">Save credentials</button>
        <button class="btn btn--ghost" type="submit" name="action" value="test">Test &amp; save</button>
      </div>
    </form>
  </div>
</section>

<section class="card">
  <div class="card__head"><h3 class="card__title">Setup checklist</h3></div>
  <div class="card__body">
    <ol class="checklist">
      <li>Register an application in <a href="https://entra.microsoft.com" target="_blank" rel="noopener">Microsoft Entra admin center</a> &raquo; <em>Identity &raquo; Applications &raquo; App registrations</em>.</li>
      <li>Copy <strong>Tenant ID</strong> and <strong>Client ID</strong> above.</li>
      <li>Create a <strong>Client secret</strong> in <em>Certificates &amp; secrets</em> with at least 12 months expiry.</li>
      <li>Grant <strong>API permissions &raquo; Microsoft Graph &raquo; Application permissions &raquo; Mail.Read</strong>. Then <em>Grant admin consent</em>.</li>
      <li>For each monitored mailbox, ensure the application has access (Exchange Online admin can run <code>Add-MailboxPermission</code> if needed, though Mail.Read is typically tenant-wide).</li>
    </ol>
  </div>
</section>
