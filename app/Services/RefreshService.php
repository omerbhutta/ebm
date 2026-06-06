<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Cache;
use App\Core\Settings;
use App\Core\Logger;
use DateTimeInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Single source of truth for "fetch NDRs from Graph and sync to suppression_list".
 * Used by:
 *   - DashboardController (interactive refresh, full cache)
 *   - CronController       (automated refresh, 12h window, no cache)
 *   - BounceController      (per-mailbox fetch on demand)
 */
final class RefreshService
{
    /**
     * Run a sync.
     *
     * @param bool                $forceRefresh Bypass the message cache. (Cron always does this
     *                                         because its 12h window is a strict subset of the
     *                                         all-time data held in the cache.)
     * @param DateTimeInterface|null $since      When set, the Graph query is filtered with
     *                                         $filter=receivedDateTime ge <since> and the cache is
     *                                         always bypassed. The dashboard leaves this null.
     * @param int $maxMessages  Per-mailbox cap (cron keeps it small; default 100 for dashboard).
     * @param int $pageLimit    Graph pagination cap.
     */
    public static function run(
        bool $forceRefresh = false,
        ?DateTimeInterface $since = null,
        int $maxMessages = 100,
        int $pageLimit = 30
    ): array {
        $started = microtime(true);
        $startedAt = date('c');

        $folders = json_decode((string)Settings::get('monitor_folders', '{}'), true);
        if (!is_array($folders) || empty($folders)) {
            $folders = ['Inbox' => 'inbox', 'Junk Email' => 'junkemail', 'Deleted Items' => 'deleteditems'];
        }
        $cacheTtl = Settings::int('cache_ttl', 300);

        $mailboxes = MailboxService::all(true);

        $allMessages = [];
        $ndrTuples   = [];
        $mailboxErrors = [];
        $cacheHits = $cacheMisses = 0;
        $token = null;

        // Cron mode: $since is set → no cache, time-filtered Graph query.
        $windowed = $since !== null;
        $useCache = !$windowed && !$forceRefresh;

        $filterExpr = '';
        if ($windowed) {
            $iso = (new DateTimeImmutable('@' . $since->getTimestamp()))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
            $filterExpr = "&\$filter=receivedDateTime ge {$iso}";
        }

        foreach ($mailboxes as $mb) {
            $email = (string)$mb['email'];
            $mbErrors = [];
            foreach ($folders as $label => $folderId) {
                $cacheKey = MailboxService::slug($email) . '__' . preg_replace('/[^a-z0-9]/i', '', (string)$label);
                $messages = $useCache ? Cache::get($cacheKey) : null;
                if (!is_array($messages)) {
                    $cacheMisses++;
                    if ($token === null) $token = GraphService::getToken();
                    if (!$token) { $mbErrors[] = "$label: auth failed"; continue; }

                    $messages = [];
                    $url = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($email) .
                           "/mailFolders/{$folderId}/messages?" .
                           "\$search=\"subject:undeliverable\"" .
                           "&\$select=id,subject,receivedDateTime,from,toRecipients" .
                           "&\$top=" . $maxMessages .
                           $filterExpr;
                    $page = 0;
                    while ($url && $page < $pageLimit) {
                        $page++;
                        $r = GraphService::get($url, $token);
                        if ($r['code'] !== 200) {
                            $err = $r['body']['error']['message'] ?? ('HTTP ' . $r['code']);
                            $mbErrors[] = "$label: " . substr((string)$err, 0, 150);
                            break;
                        }
                        foreach (($r['body']['value'] ?? []) as $m) {
                            // In windowed mode, Graph's $filter does most of the work, but we
                            // double-check the date so we don't pick up edge cases around the
                            // server clock vs message receivedDateTime.
                            if ($windowed && $since !== null) {
                                $rdt = strtotime((string)($m['receivedDateTime'] ?? '')) ?: 0;
                                if ($rdt < $since->getTimestamp()) continue;
                            }
                            $m['__folder']  = $label;
                            $m['__mailbox'] = $email;
                            $m['__mbDesc']  = $mb['description'] ?? '';
                            $messages[] = $m;
                            if (count($messages) >= $maxMessages) break 2;
                        }
                        $url = $r['body']['@odata.nextLink'] ?? null;
                    }
                    if ($useCache) Cache::put($cacheKey, $messages, $cacheTtl);
                } else {
                    $cacheHits++;
                    foreach ($messages as &$m) {
                        $m['__mailbox'] = $email;
                        $m['__mbDesc']  = $mb['description'] ?? '';
                    }
                    unset($m);
                }
                foreach ($messages as $m) $allMessages[$m['id']] = $m;
            }
            if ($mbErrors) {
                $msg = implode(' | ', $mbErrors);
                MailboxService::recordSync($email, $msg);
                $mailboxErrors[$email] = $msg;
            } else {
                MailboxService::recordSync($email, null);
            }
        }

        $uniqueFailed = [];
        foreach ($allMessages as $m) {
            $selfEmail = $m['__mailbox'] ?? '';
            $mid = $m['id'] ?? '';
            $failed = BounceService::extractFailedRecipients($m, $selfEmail);
            foreach ($failed as $e) {
                $uniqueFailed[strtolower($e)] = $e;
                if ($mid !== '') {
                    $ndrTuples[] = ['mailbox' => $selfEmail, 'message_id' => $mid, 'email' => $e];
                }
            }
        }

        $sync = SuppressionService::sync($ndrTuples);

        $retentionDays = Settings::int('retention_days', 0);
        $pruned = ($retentionDays > 0 && !$windowed) ? SuppressionService::pruneOlderThan($retentionDays) : 0;

        $dataSource = $windowed
            ? 'cron_window'
            : ($forceRefresh ? 'fetched' : (!empty($cacheMisses) ? 'partial' : 'cached'));

        $duration = (int)round((microtime(true) - $started) * 1000);

        $result = [
            'ok'             => true,
            'started_at'     => $startedAt,
            'duration_ms'    => $duration,
            'mode'           => $windowed ? 'cron_window' : 'full',
            'since'          => $windowed ? $since->format(DATE_ATOM) : null,
            'mailboxes'      => count($mailboxes),
            'messages'       => count($allMessages),
            'unique_failed'  => count($uniqueFailed),
            'sync'           => $sync,
            'mailbox_errors' => $mailboxErrors,
            'pruned'         => $pruned,
            'cache_hits'     => $cacheHits,
            'cache_misses'   => $cacheMisses,
            'data_source'    => $dataSource,
        ];

        Logger::info('refresh.sync', sprintf(
            '%s mb=%d msg=%d unique=%d added=%d updated=%d total=%d pruned=%d err=%d %dms',
            $windowed ? 'cron' : 'dashboard',
            $result['mailboxes'],
            $result['messages'],
            $result['unique_failed'],
            $result['sync']['added'],
            $result['sync']['updated'],
            $result['sync']['total'],
            $result['pruned'],
            count($result['mailbox_errors']),
            $result['duration_ms']
        ));

        return $result;
    }
}
