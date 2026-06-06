<?php
use App\Core\App;
$app = App::instance();

/**
 * Windowed pagination — fewer buttons, first/prev/next/last.
 * Required: $pager = ['page'=>int,'pages'=>int,'base'=>[...query...]]
 */
$cur   = (int)($pager['page'] ?? 1);
$total = (int)($pager['pages'] ?? 1);
$base  = (array)($pager['base'] ?? []);
$path  = (string)($pager['path'] ?? '');

if ($total <= 1) return;

function eb_pager_url($app, $path, $base, $p) {
    $q = array_merge($base, ['page' => $p]);
    return $app->baseUrl($path . '?' . http_build_query($q));
}

// Build window: 1, [cur-1, cur, cur+1], last — with ellipsis
$win = [];
$win[] = 1;
if ($cur - 1 > 1) $win[] = $cur - 1;
if ($cur > 1 && $cur < $total) $win[] = $cur;
if ($cur + 1 < $total) $win[] = $cur + 1;
$win[] = $total;
$win = array_values(array_unique(array_filter($win, fn($x) => $x >= 1 && $x <= $total)));
sort($win);

$rendered = [];
$prev = 0;
foreach ($win as $p) {
    if ($prev && $p - $prev > 1) $rendered[] = ['gap', $prev + 1];
    $rendered[] = ['page', $p];
    $prev = $p;
}
?>
<nav class="pager" aria-label="Pagination">
  <a class="pager__link pager__link--edge <?= $cur <= 1 ? 'is-disabled' : '' ?>"
     href="<?= $cur > 1 ? htmlspecialchars(eb_pager_url($app, $path, $base, 1)) : '#' ?>"
     aria-label="First page" <?= $cur <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>«</a>
  <a class="pager__link pager__link--edge <?= $cur <= 1 ? 'is-disabled' : '' ?>"
     href="<?= $cur > 1 ? htmlspecialchars(eb_pager_url($app, $path, $base, $cur - 1)) : '#' ?>"
     aria-label="Previous page" <?= $cur <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>‹</a>

  <?php foreach ($rendered as $item): ?>
    <?php if ($item[0] === 'gap'): ?>
      <span class="pager__gap" aria-hidden="true">…</span>
    <?php else:
        $p = $item[1];
        $isCur = ($p === $cur);
    ?>
      <a class="pager__link <?= $isCur ? 'is-current' : '' ?>"
         href="<?= htmlspecialchars(eb_pager_url($app, $path, $base, $p)) ?>"
         aria-current="<?= $isCur ? 'page' : 'false' ?>"><?= $p ?></a>
    <?php endif; ?>
  <?php endforeach; ?>

  <a class="pager__link pager__link--edge <?= $cur >= $total ? 'is-disabled' : '' ?>"
     href="<?= $cur < $total ? htmlspecialchars(eb_pager_url($app, $path, $base, $cur + 1)) : '#' ?>"
     aria-label="Next page" <?= $cur >= $total ? 'aria-disabled="true" tabindex="-1"' : '' ?>>›</a>
  <a class="pager__link pager__link--edge <?= $cur >= $total ? 'is-disabled' : '' ?>"
     href="<?= $cur < $total ? htmlspecialchars(eb_pager_url($app, $path, $base, $total)) : '#' ?>"
     aria-label="Last page" <?= $cur >= $total ? 'aria-disabled="true" tabindex="-1"' : '' ?>>»</a>
</nav>
