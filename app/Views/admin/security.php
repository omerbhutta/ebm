<?php
use App\Core\App;
$app = App::instance();
$apiKey = (string)($s['check_api_key'] ?? '');
$apiMasked = strlen($apiKey) > 8 ? substr($apiKey, 0, 4) . str_repeat('•', max(8, strlen($apiKey) - 8)) . substr($apiKey, -4) : str_repeat('•', strlen($apiKey));
?>
<?php $subtitle = 'Passwords, API key, and rate limits.'; include __DIR__ . '/../partials/page-header.php'; ?>

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
