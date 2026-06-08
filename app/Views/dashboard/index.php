<?php
use App\Core\App;
use App\Core\Auth;
use App\Core\Settings;
use App\Services\BounceService;
$app = App::instance();

// Format data for Chart.js — $trend uses keys: day, scanned, bounces
$trendDays = [];
$trendCounts = [];
foreach (($trend ?? []) as $t) {
    $trendDays[] = date('M d', strtotime($t['day'] ?? 'now'));
    $trendCounts[] = (int)($t['bounces'] ?? 0);
}

$mbLabels = [];
$mbCounts = [];
foreach (($mailbox_breakdown ?? []) as $mb) {
    $mbLabels[] = $mb['email'] ?? 'Unknown';
    $mbCounts[] = (int)($mb['count'] ?? 0);
}
?>
<?php $subtitle = 'Live telemetry, ingestion analytics, and automated suppression tracking.'; include __DIR__ . '/../partials/page-header.php'; ?>

<?php if (!empty($errors)): ?>
<section class="card card--error">
  <div class="card__head">
    <h3 class="card__title" style="display: flex; align-items: center; gap: 8px;">
      <span class="pulse-dot pulse-dot--danger"></span>
      Telemetry Sync Warnings
    </h3>
  </div>
  <div class="card__body">
    <ul class="muted small" style="margin-left: 20px; list-style-type: square; color: var(--danger);"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    <p class="muted small" style="margin-top: 10px;">Verify M365 authentication settings in <a href="<?= htmlspecialchars($app->baseUrl('/admin/graph')) ?>" style="text-decoration: underline; color: var(--accent);">Graph API panel</a>.</p>
  </div>
</section>
<?php endif; ?>

<!-- Futuristic Timeline Stats Widgets Grid -->
<div class="stat-grid-5">
  <div class="stat-card stat-card--accent">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <span class="stat-card__label">Today</span>
      <span style="font-size: 16px; filter: drop-shadow(0 0 6px var(--accent-glow));">⚡</span>
    </div>
    <div class="stat-card__value"><?= number_format($timeline['lifetime'] ?? 0) ?></div>
    <div class="stat-card__sub">Total suppression items</div>
  </div>

  <div class="stat-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <span class="stat-card__label">This Week</span>
      <span style="font-size: 16px; opacity: 0.8;">🗓</span>
    </div>
    <div class="stat-card__value"><?= number_format($timeline['lifetime'] ?? 0) ?></div>
    <div class="stat-card__sub">Total suppression items</div>
  </div>

  <div class="stat-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <span class="stat-card__label">This Month</span>
      <span style="font-size: 16px; opacity: 0.8;">📅</span>
    </div>
    <div class="stat-card__value"><?= number_format($timeline['lifetime'] ?? 0) ?></div>
    <div class="stat-card__sub">Total suppression items</div>
  </div>

  <div class="stat-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <span class="stat-card__label">This Year</span>
      <span style="font-size: 16px; opacity: 0.8;">📊</span>
    </div>
    <div class="stat-card__value"><?= number_format($timeline['lifetime'] ?? 0) ?></div>
    <div class="stat-card__sub">Total suppression items</div>
  </div>

  <div class="stat-card">
    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
      <span class="stat-card__label">Lifetime</span>
      <span style="font-size: 16px; opacity: 0.8;">🔒</span>
    </div>
    <div class="stat-card__value"><?= number_format($timeline['lifetime'] ?? 0) ?></div>
    <div class="stat-card__sub">Total suppression items</div>
  </div>
</div>

<!-- Graphical Analytics Section -->
<div class="grid-2" style="margin-bottom: 24px;">
  <section class="card" style="margin-bottom: 0;">
    <div class="card__head">
      <h3 class="card__title" style="display: flex; align-items: center; gap: 8px;">
        <span style="color: var(--accent);">📈</span> Bounce Ingestion Inbound Trend
      </h3>
      <span class="muted small">Last 7 Days</span>
    </div>
    <div class="card__body">
      <div style="position: relative; height: 260px; width: 100%;">
        <canvas id="bounceTrendChart"></canvas>
      </div>
    </div>
  </section>

  <section class="card" style="margin-bottom: 0;">
    <div class="card__head">
      <h3 class="card__title" style="display: flex; align-items: center; gap: 8px;">
        <span style="color: var(--accent);">🍩</span> Mailbox Bounce Distribution
      </h3>
      <span class="muted small">Share percentage</span>
    </div>
    <div class="card__body" style="display: flex; align-items: center; justify-content: center; height: 260px;">
      <div style="position: relative; height: 240px; width: 240px;">
        <canvas id="mailboxPieChart"></canvas>
      </div>
    </div>
  </section>
</div>

<!-- Telemetry Scan & Ingestion Rate Summary Box -->
<section class="card" style="margin-bottom: 24px;">
  <div class="card__head">
    <h3 class="card__title" style="display: flex; align-items: center; gap: 8px;">
      <span style="color: var(--accent);">📊</span> Telemetry Scan & Ingestion Rate
    </h3>
    <span class="badge badge--ok" style="font-size: 10px;">System Online</span>
  </div>
  <div class="card__body">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; align-items: center;">
      <!-- Details -->
      <div>
        <h4 style="font-size: 13px; color: var(--text-soft); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 15px;">Scanning Process Summary</h4>
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-soft); padding-bottom: 6px;">
            <span class="muted">Total Mails Scanned:</span>
            <strong style="font-family: 'Outfit'; color: var(--text); font-weight: 700;"><?= number_format(array_sum(array_column($trend, 'scanned'))) ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-soft); padding-bottom: 6px;">
            <span class="muted">Suppression Bounces Detected:</span>
            <strong style="font-family: 'Outfit'; color: var(--accent); font-weight: 700;"><?= number_format(array_sum(array_column($trend, 'bounces'))) ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-soft); padding-bottom: 6px;">
            <span class="muted">Ingestion Hit Rate:</span>
            <?php $rate = $hit_rate; ?>
            <strong style="font-family: 'Outfit'; color: var(--ok); font-weight: 700;"><?= $rate ?>%</strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding-bottom: 6px;">
            <span class="muted">Retention Window:</span>
            <strong style="font-family: 'Outfit'; color: var(--text-soft);"><?= Settings::int('retention_days', 0) > 0 ? Settings::int('retention_days') . ' Days' : 'Infinite' ?></strong>
          </div>
        </div>
      </div>
      
      <!-- Ingestion Rate Circular/Visual indicator -->
      <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
        <div style="position: relative; width: 120px; height: 120px; display: flex; align-items: center; justify-content: center;">
          <!-- SVG Progress Ring -->
          <svg width="120" height="120" viewBox="0 0 120 120" style="transform: rotate(-90deg);">
            <circle cx="60" cy="60" r="50" fill="transparent" stroke="var(--border-soft)" stroke-width="8" />
            <circle cx="60" cy="60" r="50" fill="transparent" stroke="var(--accent)" stroke-width="8" 
                    stroke-dasharray="314.16" stroke-dashoffset="<?= 314.16 - (314.16 * min(100, (int)($rate * 5)) / 100) ?>" 
                    style="filter: drop-shadow(0 0 3px var(--accent-glow)); transition: stroke-dashoffset 0.5s ease;" />
          </svg>
          <div style="position: absolute; display: flex; flex-direction: column; align-items: center;">
            <span style="font-family: 'Outfit'; font-size: 20px; font-weight: 800; color: var(--text);"><?= $rate ?>%</span>
            <span class="muted" style="font-size: 9px; text-transform: uppercase; font-weight: 600;">Hit Rate</span>
          </div>
        </div>
        <p class="muted small" style="margin-top: 10px; max-width: 250px;">Percentage of parsed emails matching undeliverable SMTP rules added to suppression records.</p>
      </div>

      <!-- Quick Metrics Telemetry Cards -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <div style="background: var(--bg-2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; text-align: center;">
          <div class="muted small" style="text-transform: uppercase; font-size: 10px; font-weight: 600;">Active Mailboxes</div>
          <div style="font-family: 'Outfit'; font-size: 24px; font-weight: 800; color: var(--text); margin-top: 4px;"><?= $active_mailboxes ?></div>
          <div class="muted small" style="font-size: 10px;">out of <?= $total_mailboxes ?> total</div>
        </div>
        <div style="background: var(--bg-2); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 12px; text-align: center;">
          <div class="muted small" style="text-transform: uppercase; font-size: 10px; font-weight: 600;">Avg Bounce/Domain</div>
          <div style="font-family: 'Outfit'; font-size: 24px; font-weight: 800; color: var(--text); margin-top: 4px;">
            <?php
              $domCount = count($top_domains) ?: 1;
              $totBncList = array_sum(array_column($top_domains, 'count'));
              echo round($totBncList / $domCount, 1);
            ?>
          </div>
          <div class="muted small" style="font-size: 10px;">For top domains</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Telemetry details tables -->
<div class="grid-2" style="margin-bottom: 24px;">
  <section class="card" style="margin-bottom: 0;">
    <div class="card__head">
      <h3 class="card__title" style="display: flex; align-items: center; gap: 8px;">
        <span style="color: var(--accent);">📊</span> Monitored mailboxes
      </h3>
      <?php if (Auth::isAdmin()): ?><a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes')) ?>">Manage</a><?php endif; ?>
    </div>
    <div class="card__body card__body--flush">
      <?php if (empty($mailboxes)): ?>
        <div style="padding: 30px; text-align: center;">
          <p class="muted">No mailboxes configured yet.</p>
          <?php if (Auth::isAdmin()): ?>
            <a href="<?= htmlspecialchars($app->baseUrl('/admin/mailboxes')) ?>" class="btn btn--primary btn--sm" style="margin-top: 12px;">Add Mailbox</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
      <table class="table">
        <thead><tr><th>Address</th><th>Status</th><th>Last sync</th></tr></thead>
        <tbody>
        <?php foreach ($mailboxes as $m): ?>
          <tr>
            <td>
              <strong style="font-family: 'Outfit', sans-serif; font-size: 13.5px;"><?= htmlspecialchars($m['email']) ?></strong>
              <?php if (!empty($m['description'])): ?><div class="muted small" style="margin-top: 2px;"><?= htmlspecialchars($m['description']) ?></div><?php endif; ?>
            </td>
            <td>
              <?php if ((int)$m['is_active'] === 1): ?>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <span class="pulse-dot pulse-dot--ok"></span>
                  <span class="badge badge--ok" style="font-size: 10px; padding: 1px 6px;">Active</span>
                </div>
              <?php else: ?>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <span class="pulse-dot pulse-dot--danger"></span>
                  <span class="badge badge--warn" style="font-size: 10px; padding: 1px 6px;">Paused</span>
                </div>
              <?php endif; ?>
            </td>
            <td class="muted small"><?= htmlspecialchars(BounceService::formatDate($m['last_synced_at'] ?? null)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </section>

  <section class="card" style="margin-bottom: 0;">
    <div class="card__head">
      <h3 class="card__title" style="display: flex; align-items: center; gap: 8px;">
        <span style="color: var(--accent);">🔥</span> Top blocked domains
      </h3>
      <a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/suppression')) ?>">View all</a>
    </div>
    <div class="card__body">
      <?php if (empty($top_domains)): ?>
        <div style="padding: 20px; text-align: center;">
          <p class="muted">No suppression data yet.</p>
        </div>
      <?php else: ?>
        <ol class="rank-list">
          <?php foreach ($top_domains as $d): ?>
            <li>
              <span class="rank-list__domain">@<?= htmlspecialchars($d['domain']) ?></span>
              <span class="rank-list__bar"><span style="width: <?= min(100, (int)($d['pct'] ?? 0)) ?>%"></span></span>
              <span class="rank-list__count"><?= number_format((int)($d['count'] ?? 0)) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>
  </section>
</div>

<section class="card" style="margin-bottom: 0;">
  <div class="card__head">
    <h3 class="card__title" style="display: flex; align-items: center; gap: 8px;">
      <span style="color: var(--accent);">📡</span> Live Telemetry Log
    </h3>
    <?php if (Auth::isAdmin()): ?><a class="btn btn--ghost btn--sm" href="<?= htmlspecialchars($app->baseUrl('/admin/logs')) ?>">All activity</a><?php endif; ?>
  </div>
  <div class="card__body card__body--flush">
    <?php if (empty($recent_logs)): ?>
      <div style="padding: 30px; text-align: center;">
        <p class="muted">No activity recorded yet.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Time</th><th>Level</th><th>Event</th><th>User</th><th>Detail</th></tr></thead>
          <tbody>
            <?php foreach ($recent_logs as $row): ?>
              <tr>
                <td class="muted small"><?= htmlspecialchars(BounceService::formatDate($row['created_at'] ?? null, 'Y-m-d H:i')) ?></td>
                <td>
                  <?php if (strtolower($row['level'] ?? '') === 'error' || strtolower($row['level'] ?? '') === 'danger'): ?>
                    <span class="badge badge--danger" style="font-size: 10px; padding: 1px 6px;"><?= htmlspecialchars(strtoupper($row['level'] ?? 'INFO')) ?></span>
                  <?php elseif (strtolower($row['level'] ?? '') === 'warning'): ?>
                    <span class="badge badge--warn" style="font-size: 10px; padding: 1px 6px;"><?= htmlspecialchars(strtoupper($row['level'] ?? 'INFO')) ?></span>
                  <?php else: ?>
                    <span class="badge badge--info" style="font-size: 10px; padding: 1px 6px;"><?= htmlspecialchars(strtoupper($row['level'] ?? 'INFO')) ?></span>
                  <?php endif; ?>
                </td>
                <td><code style="color: var(--accent-2); font-weight: 600;"><?= htmlspecialchars($row['event'] ?? '') ?></code></td>
                <td style="font-weight: 500;"><?= htmlspecialchars($row['user_label'] ?? 'system') ?></td>
                <td class="muted small"><?= htmlspecialchars(mb_strimwidth((string)($row['message'] ?? ''), 0, 100, '…')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Chart.js configuration scripts -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  // Check if Chart.js is loaded
  if (typeof Chart === 'undefined') return;

  function getThemeColors() {
    const isDark = document.documentElement.getAttribute("data-theme") === "dark" || !document.documentElement.getAttribute("data-theme");
    return {
      isDark: isDark,
      textColor: isDark ? "#9ca3af" : "#4a5568",
      gridColor: isDark ? "#2d2d2d" : "#edf2f7",
      borderColor: isDark ? "#222222" : "#ffffff",
      tooltipBg: isDark ? "rgba(34, 34, 34, 0.95)" : "rgba(255, 255, 255, 0.95)",
      tooltipBorder: isDark ? "rgba(255, 255, 255, 0.1)" : "rgba(0, 0, 0, 0.1)",
      titleColor: isDark ? "#ffffff" : "#1a202c",
      bodyColor: isDark ? "#f3f4f6" : "#4a5568"
    };
  }

  let colors = getThemeColors();

  // Trend Chart
  const trendCtx = document.getElementById('bounceTrendChart').getContext('2d');
  
  const gradientOrange = trendCtx.createLinearGradient(0, 0, 0, 260);
  gradientOrange.addColorStop(0, 'rgba(255, 74, 23, 0.35)');
  gradientOrange.addColorStop(0.5, 'rgba(255, 74, 23, 0.15)');
  gradientOrange.addColorStop(1, 'rgba(255, 74, 23, 0.0)');

  const gradientBlue = trendCtx.createLinearGradient(0, 0, 0, 260);
  gradientBlue.addColorStop(0, 'rgba(59, 130, 246, 0.2)');
  gradientBlue.addColorStop(0.5, 'rgba(59, 130, 246, 0.05)');
  gradientBlue.addColorStop(1, 'rgba(59, 130, 246, 0.0)');

  // Extract arrays from trend data
  const trendLabels = <?= json_encode(array_column($trend, 'day')) ?>.map(d => {
    const dt = new Date(d);
    return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' });
  });
  const trendScanned = <?= json_encode(array_column($trend, 'scanned')) ?>;
  const trendBounces = <?= json_encode(array_column($trend, 'bounces')) ?>;

  const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
      labels: trendLabels,
      datasets: [
        {
          label: 'Total Mails Scanned',
          data: trendScanned,
          borderColor: '#3b82f6',
          backgroundColor: gradientBlue,
          borderWidth: 2.5,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#3b82f6',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 1.5,
          pointRadius: 3.5,
          pointHoverRadius: 6
        },
        {
          label: 'Bounces Ingested',
          data: trendBounces,
          borderColor: '#ff4a17',
          backgroundColor: gradientOrange,
          borderWidth: 3,
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#ff4a17',
          pointBorderColor: '#ffffff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 7
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { 
          display: true,
          position: 'top',
          labels: {
            color: colors.textColor,
            font: { family: 'Plus Jakarta Sans', size: 11, weight: '600' },
            boxWidth: 12,
            padding: 10
          }
        },
        tooltip: {
          backgroundColor: colors.tooltipBg,
          titleColor: colors.titleColor,
          bodyColor: colors.bodyColor,
          borderColor: colors.isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)',
          borderWidth: 1,
          padding: 12,
          cornerRadius: 8,
          titleFont: { family: 'Outfit', size: 12, weight: 'bold' },
          bodyFont: { family: 'Plus Jakarta Sans', size: 12 },
          mode: 'index',
          intersect: false
        }
      },
      scales: {
        x: {
          grid: { color: colors.gridColor, drawBorder: false },
          ticks: { color: colors.textColor, font: { family: 'Plus Jakarta Sans', size: 11, weight: '500' } }
        },
        y: {
          grid: { color: colors.gridColor, drawBorder: false },
          ticks: { color: colors.textColor, font: { family: 'Plus Jakarta Sans', size: 11, weight: '500' }, precision: 0 }
        }
      }
    }
  });

  // Mailbox breakdown Chart
  const pieCtx = document.getElementById('mailboxPieChart').getContext('2d');
  const mailboxChart = new Chart(pieCtx, {
    type: 'doughnut',
    data: {
      labels: <?= json_encode($mbLabels) ?>,
      datasets: [{
        data: <?= json_encode($mbCounts) ?>,
        backgroundColor: [
          '#ff4a17', // Accent Orange
          '#3b82f6', // Info Blue
          '#a855f7', // Purple
          '#0d9488', // Teal
          '#e11d48'  // Rose
        ],
        borderWidth: colors.isDark ? 2 : 1,
        borderColor: colors.borderColor,
        hoverOffset: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      plugins: {
        legend: {
          display: true,
          position: 'bottom',
          labels: {
            color: colors.textColor,
            font: { family: 'Plus Jakarta Sans', size: 11, weight: '500' },
            boxWidth: 8,
            boxHeight: 8,
            padding: 15,
            usePointStyle: true,
            pointStyle: 'circle'
          }
        },
        tooltip: {
          backgroundColor: colors.tooltipBg,
          titleColor: colors.titleColor,
          bodyColor: colors.bodyColor,
          borderColor: colors.tooltipBorder,
          borderWidth: 1,
          padding: 10,
          cornerRadius: 8,
          titleFont: { family: 'Outfit', size: 12, weight: 'bold' },
          bodyFont: { family: 'Plus Jakarta Sans', size: 12 }
        }
      }
    }
  });

  // Listen to live client-side theme changes and update charts immediately
  const observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      if (mutation.attributeName === 'data-theme') {
        const c = getThemeColors();
        
        // Update Trend Line scales, ticks, and legend
        trendChart.options.scales.x.grid.color = c.gridColor;
        trendChart.options.scales.x.ticks.color = c.textColor;
        trendChart.options.scales.y.grid.color = c.gridColor;
        trendChart.options.scales.y.ticks.color = c.textColor;
        trendChart.options.plugins.legend.labels.color = c.textColor;
        trendChart.options.plugins.tooltip.backgroundColor = c.tooltipBg;
        trendChart.options.plugins.tooltip.titleColor = c.titleColor;
        trendChart.options.plugins.tooltip.bodyColor = c.bodyColor;
        trendChart.options.plugins.tooltip.borderColor = c.isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)';
        trendChart.update();

        // Update Doughnut legend and borders
        mailboxChart.options.plugins.legend.labels.color = c.textColor;
        mailboxChart.options.plugins.tooltip.backgroundColor = c.tooltipBg;
        mailboxChart.options.plugins.tooltip.titleColor = c.titleColor;
        mailboxChart.options.plugins.tooltip.bodyColor = c.bodyColor;
        mailboxChart.options.plugins.tooltip.borderColor = c.tooltipBorder;
        mailboxChart.data.datasets[0].borderWidth = c.isDark ? 2 : 1;
        mailboxChart.data.datasets[0].borderColor = c.borderColor;
        mailboxChart.update();
      }
    });
  });

  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
});
</script>
