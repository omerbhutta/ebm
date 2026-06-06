<section class="card">
  <div class="card__head">
    <h3 class="card__title"><?= htmlspecialchars($title ?? '') ?></h3>
    <?php if (!empty($slot)): ?><div class="card__actions"><?= $slot ?></div><?php endif; ?>
  </div>
  <div class="card__body">
    <?= $slot_body ?? '' ?>
  </div>
</section>
