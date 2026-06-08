<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Auth;
use App\Services\MailboxService;
use App\Services\SuppressionService;
use App\Services\RefreshService;
use App\Core\Cache;
use App\Core\Logger;

/**
 * Main dashboard — summary cards + recent activity.
 */
final class DashboardController extends Controller
{
    public function index(Request $req): void
    {
        Auth::requireViewer();

        $forceRefresh = $req->query('refresh') === '1';

        // Heavy lifting (Graph fetch + suppression sync) lives in RefreshService.
        $sync = RefreshService::run($forceRefresh);

        $mailboxes = MailboxService::all(true);
        $recentLogs = Logger::recent(8);

        // Top domains: derive from cache or fall back to a fresh fetch on cache miss.
        $topDomains = self::topDomains($forceRefresh);
        $suppStats  = SuppressionService::stats();

        $this->view('dashboard/index', [
            'rows'              => [],
            'messages_count'    => $sync['messages'],
            'unique_count'      => $sync['unique_failed'],
            'domains_count'     => count($topDomains),
            'last_24h'          => $sync['messages'],
            'last_7d'           => (int)($suppStats['summary']['seen_7d'] ?? 0),
            'last24'            => $sync['messages'],
            'last7'             => (int)($suppStats['summary']['seen_7d'] ?? 0),
            'mailboxes'         => $mailboxes,
            'active_mailboxes'  => count(array_filter($mailboxes, fn($m) => (int)$m['is_active'] === 1)),
            'total_mailboxes'   => count($mailboxes),
            'total'             => (int)($suppStats['summary']['total'] ?? 0),
            'bounces'           => (int)($suppStats['summary']['total_bounces'] ?? 0),
            'top_domains'       => $topDomains,
            'errors'            => self::formatErrors($sync['mailbox_errors']),
            'cache_age'         => self::oldestCacheAge(),
            'data_source'       => $sync['data_source'],
            'cache_misses'      => $sync['cache_misses'],
            'sync_result'       => $sync['sync'],
            'sync_error'        => null,
            'recent_logs'       => $recentLogs,
            'supp_total'        => (int)($suppStats['summary']['total'] ?? 0),
            'supp_bounces'      => (int)($suppStats['summary']['total_bounces'] ?? 0),
            'supp_last_sync'    => $suppStats['last_sync'] ?? null,
            'supp_domains'      => SuppressionService::domains(),
            'timeline'          => $suppStats['timeline'] ?? ['today' => 0, 'week' => 0, 'month' => 0, 'year' => 0, 'lifetime' => 0],
            'trend'             => $suppStats['trend'] ?? [],
            'hit_rate'          => $suppStats['hit_rate'] ?? 0.0,
            'mailbox_breakdown' => $suppStats['mailbox_breakdown'] ?? [],
        ]);
    }

    private static function topDomains(bool $forceRefresh): array
    {
        $folders = json_decode((string)Settings::get('monitor_folders', '{}'), true) ?: [];
        if (empty($folders)) $folders = ['Inbox' => 'inbox'];
        $counts = [];
        foreach (MailboxService::all(true) as $mb) {
            foreach ($folders as $label => $folderId) {
                $cacheKey = MailboxService::slug($mb['email']) . '__' . preg_replace('/[^a-z0-9]/i', '', (string)$label);
                $msgs = $forceRefresh ? null : Cache::get($cacheKey);
                if (!is_array($msgs)) continue;
                foreach ($msgs as $m) {
                    $failed = \App\Services\BounceService::extractFailedRecipients($m, $mb['email']);
                    foreach ($failed as $e) {
                        $dom = strtolower(explode('@', $e, 2)[1] ?? '');
                        if ($dom !== '') $counts[$dom] = ($counts[$dom] ?? 0) + 1;
                    }
                }
            }
        }
        arsort($counts);
        $total = array_sum($counts) ?: 1;
        $i = 0; $out = [];
        foreach ($counts as $dom => $c) {
            if ($i++ >= 8) break;
            $out[] = ['domain' => $dom, 'count' => $c, 'pct' => (int)round(($c / $total) * 100)];
        }
        return $out;
    }

    private static function formatErrors(array $byMailbox): array
    {
        $out = [];
        foreach ($byMailbox as $email => $msg) {
            $out[] = '<strong>' . htmlspecialchars($email) . ':</strong> ' . htmlspecialchars($msg);
        }
        return $out;
    }

    private static function oldestCacheAge(): ?int
    {
        $age = null;
        $folders = json_decode((string)Settings::get('monitor_folders', '{}'), true) ?: [];
        if (empty($folders)) $folders = ['Inbox' => 'inbox'];
        foreach (MailboxService::all(true) as $mb) {
            foreach ($folders as $label => $_) {
                $key = MailboxService::slug($mb['email']) . '__' . preg_replace('/[^a-z0-9]/i', '', (string)$label);
                $a = Cache::age($key);
                if ($a !== null && ($age === null || $a > $age)) $age = $a;
            }
        }
        return $age;
    }
}
