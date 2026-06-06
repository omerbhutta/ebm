<?php
use App\Core\App;
$app = App::instance();
$monitorFolders = (string)($s['monitor_folders'] ?? json_encode(['Inbox'=>'inbox','Junk Email'=>'junkemail']));
?>
<?php $subtitle = 'Branding, theme, retention, and cache.'; include __DIR__ . '/../partials/page-header.php'; ?>

<form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/system/update')) ?>" class="form">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">

  <section class="card">
    <div class="card__head"><h3 class="card__title">Branding</h3></div>
    <div class="card__body">
      <label class="form__field">
        <span class="form__label">Application name</span>
        <input class="form__control" type="text" name="app_name" value="<?= htmlspecialchars($s['app_name'] ?? 'Email Bounce Monitor') ?>" required maxlength="120">
      </label>
      <label class="form__field">
        <span class="form__label">Tagline</span>
        <input class="form__control" type="text" name="app_tagline" value="<?= htmlspecialchars($s['app_tagline'] ?? '') ?>" maxlength="200">
      </label>
      <label class="form__field">
        <span class="form__label">Footer text</span>
        <input class="form__control" type="text" name="footer_text" value="<?= htmlspecialchars($s['footer_text'] ?? 'Powered by E-Services 360') ?>" maxlength="200">
      </label>
      <label class="form__field">
        <span class="form__label">Footer URL</span>
        <input class="form__control" type="url" name="footer_url" value="<?= htmlspecialchars($s['footer_url'] ?? 'https://eservices360.com') ?>" maxlength="255">
      </label>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h3 class="card__title">Theme</h3></div>
    <div class="card__body">
      <div class="form__field form__field--row">
        <span class="form__label">Default theme</span>
        <label class="radio"><input type="radio" name="theme" value="dark" <?= ($s['theme'] ?? 'dark') === 'dark' ? 'checked' : '' ?>> Dark</label>
        <label class="radio"><input type="radio" name="theme" value="light" <?= ($s['theme'] ?? 'dark') === 'light' ? 'checked' : '' ?>> Light</label>
      </div>
      <label class="form__field form__field--row">
        <input type="checkbox" name="theme_toggle_enabled" value="1" <?= ($s['theme_toggle_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
        <span>Allow users to toggle the theme</span>
      </label>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h3 class="card__title">Mailbox scanning</h3></div>
    <div class="card__body">
      <label class="form__field">
        <span class="form__label">Monitor folders (JSON)</span>
        <textarea class="form__control" name="monitor_folders" rows="3" placeholder='{"Inbox":"inbox","Junk Email":"junkemail"}'><?= htmlspecialchars($monitorFolders) ?></textarea>
        <small class="muted">Map a human label to a Graph folder ID. Common well-known names: <code>inbox</code>, <code>junkemail</code>, <code>deleteditems</code>. Use the Graph Explorer to find others.</small>
      </label>
    </div>
  </section>

  <section class="card">
    <div class="card__head"><h3 class="card__title">Performance &amp; retention</h3></div>
    <div class="card__body">
      <div class="grid-2">
        <label class="form__field">
          <span class="form__label">Cache TTL (seconds)</span>
          <input class="form__control" type="number" min="0" name="cache_ttl" value="<?= htmlspecialchars((string)($s['cache_ttl'] ?? 300)) ?>">
        </label>
        <label class="form__field">
          <span class="form__label">Session lifetime (seconds)</span>
          <input class="form__control" type="number" min="300" name="session_ttl" value="<?= htmlspecialchars((string)($s['session_ttl'] ?? 28800)) ?>">
        </label>
        <label class="form__field">
          <span class="form__label">Retention days for processed NDRs (0 = keep forever)</span>
          <input class="form__control" type="number" min="0" name="retention_days" value="<?= htmlspecialchars((string)($s['retention_days'] ?? 0)) ?>">
        </label>
      </div>
    </div>
  </section>

  <div class="form__row">
    <button class="btn btn--primary" type="submit">Save settings</button>
  </div>
</form>

<form method="post" action="<?= htmlspecialchars($app->baseUrl('/admin/system/flush-cache')) ?>" class="inline-form" data-confirm="Flush ALL cache files?">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
  <button class="btn btn--ghost" type="submit">Flush cache</button>
</form>
