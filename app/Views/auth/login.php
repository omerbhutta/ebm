<?php
use App\Core\App;
use App\Core\Csrf;
$app = App::instance();
$isAdmin = ($mode ?? 'viewer') === 'admin';
?>
<h2 class="auth-card__heading"><?= $isAdmin ? 'Administrator sign in' : 'Sign in' ?></h2>
<form method="post" action="<?= htmlspecialchars($app->baseUrl($isAdmin ? '/admin/login' : '/login')) ?>" class="form" autocomplete="off">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
  <?php if (!empty($next)): ?><input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>"><?php endif; ?>
  <label class="form__field">
    <span class="form__label"><?= $isAdmin ? 'Admin password' : 'Password' ?></span>
    <input class="form__control" type="password" name="password" required autofocus>
  </label>
  <button type="submit" class="btn btn--primary btn--block">Sign in</button>
</form>
<p class="auth-card__alt">
  <?php if ($isAdmin): ?>
    <a href="<?= htmlspecialchars($app->baseUrl('/login')) ?>">← Viewer sign in</a>
  <?php else: ?>
    <a href="<?= htmlspecialchars($app->baseUrl('/admin/login')) ?>">Administrator sign in →</a>
  <?php endif; ?>
</p>
