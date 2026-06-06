<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Auth;
use App\Services\MailboxService;
use App\Services\SuppressionService;
use App\Services\GraphService;
use App\Services\BounceService;
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
        $folders = json_decode((string)Settings::get('monitor_folders', '{}'), true);
        if (!is_array($folders) || empty($folders)) {
            $folders = ['Inbox' => 'inbox', 'Junk Email' => 'junkemail', 'Deleted Items' => 'deleteditems'];
        }
        $cacheTtl = Settings::int('cache_ttl', 300);

        $mailboxes = MailboxService::all(true);
        $allMessages = [];
        $errors = [];
        $cacheHits = $cacheMisses = [];
        $token = null;

        foreach ($mailboxes as $mb) {
            $email = $mb['email'];
            $mbErrors = [];
            foreach ($folders as $label => $folderId) {
                $cacheKey = MailboxService::slug($email) . '__' . preg_replace('/[^a-z0-9]/i','',$label);
                $messages = $forceRefresh ? null : Cache::get($cacheKey);
                if (!is_array($messages)) {
                    $cacheMisses[] = "$email/$label";
                    if ($token === null) $token = GraphService::getToken();
                    if (!$token) { $mbErrors[] = "$label: auth failed"; continue; }

                    $messages = [];
                    $url = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($email) .
                           "/mailFolders/{$folderId}/messages?" .
                           "\$search=\"subject:undeliverable\"" .
                           "&\$select=id,subject,receivedDateTime,from,toRecipients" .
                           "&\$top=100";
                    $page = 0;
                    while ($url && $page < 30) {
                        $page++;
                        $r = GraphService::get($url, $token);
                        if ($r['code'] !== 200) {
                            $err = $r['body']['error']['message'] ?? ('HTTP ' . $r['code']);
                            $mbErrors[] = "$label: " . substr((string)$err, 0, 150);
                            break;
                        }
                        foreach (($r['body']['value'] ?? []) as $m) {
                            $m['__folder']  = $label;
                            $m['__mailbox'] = $email;
                            $m['__mbDesc']  = $mb['description'] ?? '';
                            $messages[] = $m;
                        }
                        $url = $r['body']['@odata.nextLink'] ?? null;
                    }
                    Cache::put($cacheKey, $messages, $cacheTtl);
                } else {
                    $cacheHits[] = "$email/$label";
                    foreach ($messages as &$m) {
                        $m['__mailbox'] = $email;
                        $m['__mbDesc']  = $mb['description'] ?? '';
                    }
                    unset($m);
                }
                foreach ($messages as $m) $allMessages[$m['id']] = $m;
            }
            if ($mbErrors) {
                MailboxService::recordSync($email, implode(' | ', $mbErrors));
                $errors[] = "<strong>" . htmlspecialchars($email) . ":</strong> " . htmlspecialchars(implode(' | ', $mbErrors));
            } else {
                MailboxService::recordSync($email, null);
            }
        }

        $messages = array_values($allMessages);
        usort($messages, fn($a, $b) => strcmp((string)($b['receivedDateTime'] ?? ''), (string)($a['receivedDateTime'] ?? '')));

        // Build rows + sync to suppression list
        $rows = [];
        $uniqueFailed = [];
        $ndrTuples = [];
        $domains = [];
        $domainCounts = [];
        $last24h = 0;
        $now = time();

        foreach ($messages as $m) {
            $selfEmail = $m['__mailbox'] ?? '';
            $mid = $m['id'] ?? '';
            $failed = BounceService::extractFailedRecipients($m, $selfEmail);
            $rows[] = [
                'id'       => $mid,
                'subject'  => $m['subject'] ?? '(no subject)',
                'folder'   => $m['__folder'] ?? '',
                'date'     => $m['receivedDateTime'] ?? '',
                'from'     => $m['from']['emailAddress']['address'] ?? '',
                'failed'   => $failed,
                'mailbox'  => $selfEmail,
            ];
            foreach ($failed as $e) {
                $eLow = strtolower($e);
                $uniqueFailed[$eLow] = $e;
                $dom = strtolower(explode('@', $e, 2)[1] ?? '');
                if ($dom !== '') {
                    $domains[$dom] = true;
                    if (!isset($domainCounts[$dom])) $domainCounts[$dom] = ['total' => 0, 'emails' => []];
                    $domainCounts[$dom]['total']++;
                    $domainCounts[$dom]['emails'][$eLow] = $e;
                }
                if ($mid !== '') {
                    $ndrTuples[] = ['mailbox' => $selfEmail, 'message_id' => $mid, 'email' => $e];
                }
            }
            if (!empty($m['receivedDateTime']) && strtotime($m['receivedDateTime']) > $now - 86400) $last24h++;
        }

        $syncResult = ['added' => 0, 'updated' => 0, 'total' => SuppressionService::count()];
        $syncError = null;
        try {
            $syncResult = SuppressionService::sync($ndrTuples);
        } catch (\Throwable $e) {
            $syncError = $e->getMessage();
            Logger::error('suppression.sync_failed', $e->getMessage());
        }

        // Retention purge (configurable)
        $retentionDays = Settings::int('retention_days', 0);
        if ($retentionDays > 0) SuppressionService::pruneOlderThan($retentionDays);

        // Cache age
        $oldestAge = null;
        foreach ($folders as $label => $_) {
            foreach ($mailboxes as $mb) {
                $cacheKey = MailboxService::slug($mb['email']) . '__' . preg_replace('/[^a-z0-9]/i','',$label);
                $age = Cache::age($cacheKey);
                if ($age !== null && ($oldestAge === null || $age > $oldestAge)) $oldestAge = $age;
            }
        }
        $dataSource = $forceRefresh ? 'fetched' : (!empty($cacheMisses) ? 'partial' : 'cached');

        $stats = SuppressionService::stats();
        uasort($domainCounts, fn($a, $b) => $b['total'] <=> $a['total']);

        // Top domains for the dashboard card
        $totalBounceAll = array_sum(array_column($domainCounts, 'total')) ?: 1;
        $topDomains = [];
        $i = 0;
        foreach ($domainCounts as $dom => $info) {
            if ($i++ >= 8) break;
            $topDomains[] = [
                'domain' => $dom,
                'count'  => (int)$info['total'],
                'pct'    => (int)round(((int)$info['total'] / $totalBounceAll) * 100),
            ];
        }

        // Aliases used by the dashboard view
        $totalMailboxes = count($mailboxes);
        $activeMailboxes = 0;
        foreach ($mailboxes as $m) if ((int)$m['is_active'] === 1) $activeMailboxes++;

        $recentLogs = Logger::recent(8);

        $this->view('dashboard/index', [
            'rows'              => $rows,
            'messages_count'    => count($rows),
            'unique_count'      => count($uniqueFailed),
            'domains_count'     => count($domains),
            'last_24h'          => $last24h,
            'last_7d'           => (int)($stats['summary']['last_7d'] ?? 0),
            'last24'            => $last24h,
            'last7'             => (int)($stats['summary']['last_7d'] ?? 0),
            'mailboxes'         => $mailboxes,
            'active_mailboxes'  => $activeMailboxes,
            'total_mailboxes'   => $totalMailboxes,
            'total'             => (int)($stats['summary']['total'] ?? 0),
            'bounces'           => (int)($stats['summary']['total_bounces'] ?? 0),
            'top_domains'       => $topDomains,
            'unique_failed'     => array_values($uniqueFailed),
            'domain_counts'     => array_map(fn($d, $info) => ['domain' => $d, 'count' => $info['total'], 'emails' => array_values($info['emails'])], array_keys($domainCounts), $domainCounts),
            'errors'            => $errors,
            'cache_age'         => $oldestAge,
            'data_source'       => $dataSource,
            'cache_misses'      => $cacheMisses,
            'sync_result'       => $syncResult,
            'sync_error'        => $syncError,
            'recent_logs'       => $recentLogs,
            'supp_total'        => (int)($stats['summary']['total'] ?? 0),
            'supp_bounces'      => (int)($stats['summary']['total_bounces'] ?? 0),
            'supp_last_sync'    => $stats['last_sync'] ?? null,
            'supp_domains'      => SuppressionService::domains(),
        ]);
    }
}
