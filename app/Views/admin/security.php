<?php
use App\Core\App;
use App\Services\BounceService;
use App\Core\Logger;
$app = App::instance();
$apiKey = (string)($s['check_api_key'] ?? '');
$apiMasked = strlen($apiKey) > 8 ? substr($apiKey, 0, 4) . str_repeat('•', max(8, strlen($apiKey) - 8)) . substr($apiKey, -4) : str_repeat('•', strlen($apiKey));

$cronToken = (string)($s['cron_token'] ?? '');
$cronTokenMasked = $cronToken === '' ? '(not generated yet)' : (substr($cronToken, 0, 6) . str_repeat('•', max(8, strlen($cronToken) - 10)) . substr($cronToken, -4));
$cronLocalOnly = (string)($s['cron_local_only'] ?? '1') === '1';

// Last cron run, parsed from the log table (most recent refresh.sync with mode=cron).
$cronLast = null;
foreach (Logger::recent(50) as $row) {
    if (($row['event'] ?? '') === 'refresh.sync' && str_contains((string)($row['message'] ?? ''), 'cron')) {
        $cronLast = $row; break;
    }
}
$cronUrl = $app->baseUrl('/cron/refresh');
$cronCurl = "*/5 * * * * curl -fsS -H \"X-Cron-Token: {$cronToken}\" {$cronUrl}";
?>
<?php $subtitle = 'Passwords, API key, cron scheduler, and rate limits.'; include __DIR__ . '/../partials/page-header.php'; ?>

<section class="grid-2">

  <section class="card">
    <div class="card__head"><h3 class="card__title">Change passwords</h3></div>
    <div class="card__body">
      <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/security/password')) ?>" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

        <label class="form__field">
          <span class="form__label">Account</span>
          <select class="form__control" name="role" required>
            <option value="admin">Administrator</option>
            <option value="viewer">Viewer</option>
          </select>
        </label>
        <label class="form__field">
          <span class="form__label">Current admin password</span>
          <input class="form__control" type="password" name="current_password" required autocomplete="off">
          <small class="muted">Re-authentication is required to change either password.</small>
        </label>
        <label class="form__field">
          <span class="form__label">New password (min 10 chars for admin, 8 for viewer)</span>
          <input class="form__control" type="password" name="new_password" required minlength="8" autocomplete="off">
        </label>
        <label class="form__field">
          <span class="form__label">Confirm new password</span>
          <input class="form__control" type="password" name="confirm_password" required minlength="8" autocomplete="off">
        </label>
        <div class="form__row">
          <button class="btn btn--primary" type="submit">Update password</button>
        </div>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h3 class="card__title">API key</h3></div>
    <div class="card__body">
      <p>This is the <code>X-Api-Key</code> your sending systems use to call <code>/api/check</code>.</p>
      <div class="api-key">
        <code id="apiKeyValue"><?= htmlspecialchars($apiMasked) ?></code>
        <button class="btn btn--ghost btn--sm" type="button" id="apiKeyReveal" data-current-key="<?= htmlspecialchars($apiKey) ?>"><?= $apiKey === '' ? '—' : 'Reveal' ?></button>
      </div>
      <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/security/rotate-key')) ?>" class="form" id="rotateKeyForm">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <label class="form__field">
          <span class="form__label">Confirm with current admin password</span>
          <input class="form__control" type="password" name="current_password" required autocomplete="off">
        </label>
        <button class="btn btn--danger" type="submit">Rotate API key</button>
      </form>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h3 class="card__title">Rate limits</h3></div>
    <div class="card__body">
      <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/security/limits')) ?>" class="form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
        <label class="form__field">
          <span class="form__label">Max login attempts per window</span>
          <input class="form__control" type="number" min="1" max="50" name="login_rate_max" value="<?= htmlspecialchars((string)($s['login_rate_max'] ?? 5)) ?>">
        </label>
        <label class="form__field">
          <span class="form__label">Window (seconds)</span>
          <input class="form__control" type="number" min="60" max="86400" name="login_rate_window" value="<?= htmlspecialchars((string)($s['login_rate_window'] ?? 900)) ?>">
        </label>
        <div class="form__row">
          <button class="btn btn--primary" type="submit">Save limits</button>
        </div>
      </form>
    </div>
  </section>

</section>

<section class="card" style="margin-top:18px">
  <div class="card__head"><h3 class="card__title">Automated sync (cron)</h3></div>
  <div class="card__body">
    <p class="muted">
      Hit <code><?= htmlspecialchars($cronUrl) ?></code> from an external scheduler to keep the
      suppression list up to date without anyone opening the dashboard. Each run scans only the
      <strong>last 12 hours</strong> of NDRs, adds any new bad addresses, and returns a short status
      JSON — it never leaks bounce content.
    </p>

    <label class="form__field">
      <span class="form__label">Cron token</span>
      <div class="api-key">
        <code id="cronTokenValue"><?= htmlspecialchars($cronTokenMasked) ?></code>
        <button class="btn btn--ghost btn--sm" type="button" id="cronTokenReveal" data-current-key="<?= htmlspecialchars($cronToken) ?>"><?= $cronToken === '' ? 'Generate' : 'Reveal' ?></button>
        <button class="btn btn--ghost btn--sm" type="button" id="cronTokenCopy" data-current-key="<?= htmlspecialchars($cronToken) ?>" <?= $cronToken === '' ? 'disabled' : '' ?>>Copy</button>
      </div>
    </label>

    <label class="form__field">
      <span class="form__label">Example crontab (runs every 5 minutes)</span>
      <textarea class="form__control" rows="3" readonly id="cronCurl"><?= htmlspecialchars($cronCurl) ?></textarea>
      <small class="muted">Replace the token with the one above. Use a task scheduler on Windows
        with the same URL and header.</small>
    </label>

    <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/security/rotate-cron')) ?>" class="form" style="margin-top:14px">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <button class="btn btn--danger" type="submit">Rotate cron token</button>
    </form>

    <form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/security/cron-policy')) ?>" class="form" style="margin-top:14px">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
      <label class="form__field form__field--row">
        <input type="checkbox" name="cron_local_only" value="1" <?= $cronLocalOnly ? 'checked' : '' ?>>
        <span>Restrict to localhost only</span>
      </label>
      <button class="btn btn--ghost" type="submit">Save policy</button>
    </form>

    <div class="form__row" style="margin-top:14px">
      <button class="btn btn--primary" type="button" id="cronTestBtn"
              data-endpoint="<?= htmlspecialchars($cronUrl) ?>"
              data-token="<?= htmlspecialchars($cronToken) ?>"
              <?= $cronToken === '' ? 'disabled' : '' ?>>
        Test now
      </button>
      <span class="muted small">Fires the endpoint from this page; response appears below.</span>
    </div>
    <pre class="muted small" id="cronTestResult" style="margin-top:8px;white-space:pre-wrap;background:var(--surface-2);padding:10px;border-radius:6px;min-height:1em"></pre>

    <div class="muted small" style="margin-top:14px">
      <strong>Last run:</strong>
      <?php if ($cronLast): ?>
        <?= htmlspecialchars(BounceService::formatDate($cronLast['created_at'] ?? null, 'Y-m-d H:i:s')) ?>
        &middot; <code><?= htmlspecialchars($cronLast['message'] ?? '') ?></code>
      <?php else: ?>
        <em>No cron run recorded yet.</em>
      <?php endif; ?>
    </div>
  </div>
</section>
