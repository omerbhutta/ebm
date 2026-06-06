<?php
use App\Core\App;
use App\Core\Csrf;
$app = App::instance();
$isAdmin = ($mode ?? 'viewer') === 'admin';
?>
<h2 class="auth-card__heading"><?= $isAdmin ? 'Administrator Panel' : 'Viewer Dashboard' ?></h2>

<p class="muted center small" style="margin-top: -16px; margin-bottom: 24px; line-height: 1.4;">
  <?= $isAdmin 
    ? 'Access full telemetry controls, mailbox management, and security settings.' 
    : 'Read-only access to suppression lists, bounce logs, and live monitoring metrics.' ?>
</p>

<form method="post" action="<?= htmlspecialchars($app->baseUrl($isAdmin ? '/admin/login' : '/login')) ?>" class="form" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
  <?php if (!empty($next)): ?><input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>"><?php endif; ?>
  
  <label class="form__field">
    <span class="form__label" style="letter-spacing: 0.08em;"><?= $isAdmin ? 'Admin Password' : 'Viewer Password' ?></span>
    <input class="form__control" type="password" name="password" placeholder="••••••••••••" required autofocus style="text-align: center; font-size: 16px; letter-spacing: 0.15em;">
  </label>
  
  <button type="submit" class="btn btn--primary btn--block" style="margin-top: 8px;">
    <span>Authorize Connection</span>
    <span style="font-size: 12px; opacity: 0.8;">→</span>
  </button>
</form>

<p class="auth-card__alt">
  <?php if ($isAdmin): ?>
    <a href="<?= htmlspecialchars($app->baseUrl('/login')) ?>">
      <span>← Switch to Viewer Dashboard</span>
    </a>
  <?php else: ?>
    <a href="<?= htmlspecialchars($app->baseUrl('/admin/login')) ?>">
      <span>Access Administrator Panel →</span>
    </a>
  <?php endif; ?>
</p>
