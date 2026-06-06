<?php /** @var bool $success; @var array $errors; @var string $login_url; @var string $admin_url; @var ?bool $locked */ ?>
<div class="install-card">
    <?php if (!empty($success)): ?>
        <div class="install-hero">
            <div class="hero-emoji"><?= !empty($locked) ? '🔒' : '🎉' ?></div>
            <h1><?= !empty($locked) ? 'EBM is already installed' : 'Installation Complete' ?></h1>
            <p class="hero-tagline">
                <?= !empty($locked)
                    ? 'The installer is locked. Sign in below, or delete <code>config/installed.php</code> and <code>storage/locks/install.lock</code> to re-run the wizard.'
                    : 'Your Email Bounce Monitor is ready to use. The installer has been locked for security.'
                ?>
            </p>
        </div>

        <div class="finish-grid">
            <a class="finish-card" href="<?= htmlspecialchars($login_url) ?>">
                <div class="finish-emoji">👁️</div>
                <h3>Viewer Login</h3>
                <p>Browse bounce records and the suppression list.</p>
                <span class="finish-link">Open dashboard →</span>
            </a>
            <a class="finish-card" href="<?= htmlspecialchars($admin_url) ?>">
                <div class="finish-emoji">🛠️</div>
                <h3>Admin Login</h3>
                <p>Manage mailboxes, settings, and access.</p>
                <span class="finish-link">Open admin panel →</span>
            </a>
        </div>

        <?php if (empty($locked)): ?>
        <div class="install-alert install-alert-info" style="margin-top:24px">
            <strong>Next steps:</strong>
            <ol style="margin:8px 0 0 18px">
                <li>Sign in to the admin panel with the password you just set.</li>
                <li>Add the mailboxes you want to monitor under <em>Mailboxes</em>.</li>
                <li>(Optional) Customise the application name, footer, and theme under <em>System Settings</em>.</li>
                <li>Copy your <em>API key</em> from <em>Settings → API</em> to integrate with your sending system.</li>
            </ol>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="install-hero">
            <div class="hero-emoji">⚠️</div>
            <h1>Installation Failed</h1>
            <p class="hero-tagline">Something went wrong while finishing the installation.</p>
        </div>
        <div class="install-alert install-alert-error">
            <?php foreach ($errors ?? [] as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="install-actions">
            <a class="btn btn-ghost" href="<?= \App\Core\View::url('/install/reset') ?>">Reset Wizard</a>
            <a class="btn btn-primary" href="<?= \App\Core\View::url('/install/security') ?>">Try Again</a>
        </div>
    <?php endif; ?>
</div>
