<?php
use App\Core\App;
use App\Core\Csrf;
$app = App::instance();
?>
<form method="post" action="<?= htmlspecialchars($action) ?>" class="inline-form" data-confirm="<?= htmlspecialchars($confirm ?? '') ?>" data-method="<?= htmlspecialchars($method ?? 'post') ?>">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
  <button type="submit" class="btn <?= htmlspecialchars($class ?? 'btn--ghost') ?> <?= htmlspecialchars($size ?? 'btn--sm') ?>">
    <?= $slot ?? 'Submit' ?>
  </button>
</form>
