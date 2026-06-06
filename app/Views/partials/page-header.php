<?php
/** @var string $title */
/** @var string $subtitle */
?><header class="page-header">
  <div>
    <h2 class="page-header__title"><?= htmlspecialchars($title ?? '') ?></h2>
    <?php if (!empty($subtitle)): ?>
      <p class="page-header__sub"><?= htmlspecialchars($subtitle) ?></p>
    <?php endif; ?>
  </div>
  <?php if (!empty($slot)): ?>
    <div class="page-header__actions"><?= $slot ?></div>
  <?php endif; ?>
</header>
