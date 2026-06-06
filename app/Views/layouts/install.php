<?php /** @var array $steps; @var int $current; @var array $_flash */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · Email Bounce Monitor</title>
<link rel="stylesheet" href="<?= \App\Core\View::asset('css/install.css') ?>">
</head>
<body class="install-body">
<div class="install-shell">
    <aside class="install-sidebar">
        <div class="install-brand">
            <div class="brand-mark">📧</div>
            <div>
                <div class="brand-name">EBM</div>
                <div class="brand-sub">Email Bounce Monitor</div>
            </div>
        </div>
        <ol class="install-steps">
            <?php foreach ($steps as $i => $s): ?>
                <li class="<?= $i < $current ? 'done' : ($i === $current ? 'active' : '') ?>">
                    <span class="step-num"><?= $i + 1 ?></span>
                    <span class="step-label"><?= htmlspecialchars($s['label']) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <div class="install-footer">
            <p>Need help? See the <a href="https://github.com/" target="_blank" rel="noopener">README</a>.</p>
            <p class="install-version">EBM v2.0.0</p>
        </div>
    </aside>
    <main class="install-main">
        <?php if (!empty($_flash)): foreach ($_flash as $f): ?>
            <div class="install-flash flash-<?= htmlspecialchars($f['type']) ?>">
                <?= $f['message'] ?>
            </div>
        <?php endforeach; endif; ?>

        <?= $content ?? '' ?>

        <footer class="install-copyright">
            <p>Powered by <a href="https://eservices360.com" target="_blank" rel="noopener">E-Services 360</a></p>
        </footer>
    </main>
</div>
</body>
</html>
