<?php /** @var array $data; @var array $errors */ ?>
<div class="install-card">
    <header class="install-card-head">
        <h1>Database Configuration</h1>
        <p>Enter your MySQL/MariaDB credentials. The database will be created if it doesn't exist.</p>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="install-alert install-alert-error">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="install-form">
        <div class="form-grid two">
            <div class="form-field">
                <label for="host">Database Host</label>
                <input id="host" name="host" type="text" value="<?= htmlspecialchars($data['host']) ?>" required>
                <small>Usually <code>localhost</code> or <code>127.0.0.1</code></small>
            </div>
            <div class="form-field">
                <label for="port">Port</label>
                <input id="port" name="port" type="number" value="<?= htmlspecialchars((string)$data['port']) ?>" required>
            </div>
        </div>

        <div class="form-field">
            <label for="name">Database Name</label>
            <input id="name" name="name" type="text" value="<?= htmlspecialchars($data['name']) ?>" required>
            <small>If it doesn't exist, the installer will create it.</small>
        </div>

        <div class="form-grid two">
            <div class="form-field">
                <label for="user">Database User</label>
                <input id="user" name="user" type="text" value="<?= htmlspecialchars($data['user']) ?>" required autocomplete="off">
            </div>
            <div class="form-field">
                <label for="pass">Database Password</label>
                <input id="pass" name="pass" type="password" value="<?= htmlspecialchars($data['pass']) ?>" autocomplete="new-password">
                <small>Leave blank if no password.</small>
            </div>
        </div>

        <div class="install-actions">
            <a class="btn btn-ghost" href="<?= \App\Core\View::url('/install/requirements') ?>">← Back</a>
            <button class="btn btn-secondary" type="submit" name="action" value="test">Test Connection</button>
            <button class="btn btn-primary" type="submit" name="action" value="save">Save &amp; Continue →</button>
        </div>
    </form>
</div>
