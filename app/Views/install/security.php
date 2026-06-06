<?php /** @var array $data; @var array $errors */ ?>
<div class="install-card">
    <header class="install-card-head">
        <h1>Security &amp; Application</h1>
        <p>Choose your application name and set strong passwords for the two access levels.</p>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="install-alert install-alert-error">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="install-form" autocomplete="off">
        <div class="form-field">
            <label for="app_name">Application Name</label>
            <input id="app_name" name="app_name" type="text" maxlength="120"
                   value="<?= htmlspecialchars($data['app_name']) ?>" required>
            <small>Shown in the header and browser title.</small>
        </div>

        <fieldset class="install-fieldset">
            <legend>👁️ Viewer Access</legend>
            <p class="fieldset-desc">Used by people who only need to see bounce records and the suppression list.</p>
            <div class="form-grid two">
                <div class="form-field">
                    <label for="viewer_password">Viewer Password</label>
                    <input id="viewer_password" name="viewer_password" type="password" minlength="8" required>
                    <small>At least 8 characters.</small>
                </div>
                <div class="form-field">
                    <label for="viewer_confirm">Confirm Viewer Password</label>
                    <input id="viewer_confirm" name="viewer_confirm" type="password" minlength="8" required>
                </div>
            </div>
        </fieldset>

        <fieldset class="install-fieldset">
            <legend>🛠️ Administrator Access</legend>
            <p class="fieldset-desc">Used by administrators to manage mailboxes, change Graph credentials, and modify settings.</p>
            <div class="form-grid two">
                <div class="form-field">
                    <label for="admin_password">Admin Password</label>
                    <input id="admin_password" name="admin_password" type="password" minlength="10" required>
                    <small>At least 10 characters, and different from the viewer password.</small>
                </div>
                <div class="form-field">
                    <label for="admin_confirm">Confirm Admin Password</label>
                    <input id="admin_confirm" name="admin_confirm" type="password" minlength="10" required>
                </div>
            </div>
        </fieldset>

        <div class="install-alert install-alert-info">
            <strong>Save these passwords now</strong> — they are hashed in the database and cannot be recovered.
            They can be changed later from the Admin panel.
        </div>

        <div class="install-actions">
            <a class="btn btn-ghost" href="<?= \App\Core\View::url('/install/graph') ?>">← Back</a>
            <button class="btn btn-primary" type="submit">Complete Installation →</button>
        </div>
    </form>
</div>
