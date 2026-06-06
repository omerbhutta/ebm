<?php /** @var array $checks; @var bool $all_ok */ ?>
<div class="install-card">
    <header class="install-card-head">
        <h1>System Requirements</h1>
        <p>Verifying your server meets the minimum requirements.</p>
    </header>

    <div class="req-table">
        <?php foreach ($checks as $c): ?>
            <div class="req-row <?= $c['pass'] ? 'pass' : 'fail' ?>">
                <div class="req-status">
                    <?= $c['pass'] ? '✓' : '✗' ?>
                </div>
                <div class="req-name"><?= htmlspecialchars($c['name']) ?></div>
                <div class="req-current"><?= htmlspecialchars((string)$c['current']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!$all_ok): ?>
        <div class="install-alert install-alert-error">
            <strong>Some requirements are not met.</strong>
            Please resolve the items marked with <span style="color:#dc2626">✗</span> before continuing.
            On Windows + Laragon/WAMP, all required extensions are enabled by default — but check that the
            <code>config/</code> and <code>storage/</code> directories are writable by the web server user.
        </div>
    <?php else: ?>
        <div class="install-alert install-alert-success">
            All requirements satisfied. You can continue.
        </div>
    <?php endif; ?>

    <div class="install-actions">
        <a class="btn btn-ghost" href="<?= \App\Core\View::url('/install') ?>">← Back</a>
        <form method="post" style="display:inline">
            <button class="btn btn-primary" type="submit" <?= $all_ok ? '' : 'disabled' ?>>
                Continue →
            </button>
        </form>
    </div>
</div>
