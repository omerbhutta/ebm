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
 *
 * Multi-tenant: mailboxes are grouped by tenant_id. Each tenant authenticates
 * with its own Graph credentials. Tokens are cached per-tenant.
 */
final class RefreshService
{
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

        $allMailboxes = MailboxService::all(true);

        $allMessages = [];
        $ndrTuples   = [];
        $mailboxErrors = [];
        $cacheHits = $cacheMisses = 0;

        $windowed = $since !== null;
        $useCache = !$windowed && !$forceRefresh;

        $filterExpr = '';
        $searchExpr = '$search="subject:undeliverable'
            . ' OR subject:%22Delivery Status%22'
            . ' OR subject:%22delivery failure%22'
            . ' OR subject:%22mail delivery%22'
            . ' OR subject:%22returned mail%22'
            . ' OR subject:undelivered'
            . ' OR subject:%22non-delivery%22'
            . ' OR subject:%22failure notice%22"';
        if ($windowed) {
            $iso = (new DateTimeImmutable('@' . $since->getTimestamp()))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
            // Cron mode: use only $filter (no $search) to catch ALL messages
            // in the time window. BounceService handles NDR detection.
            $searchExpr = '';
            $filterExpr = "&\$filter=receivedDateTime ge {$iso}";
        }

        // Group mailboxes by tenant
        $tenants = [];
        foreach ($allMailboxes as $mb) {
            $tid = (int)($mb['tenant_id'] ?? 0);
            if (!isset($tenants[$tid])) {
                $tenants[$tid] = [];
            }
            $tenants[$tid][] = $mb;
        }

        $tenantTokens = [];

        foreach ($tenants as $tid => $mboxes) {
            // Resolve tenant credentials
            if ($tid > 0) {
                $tenant = TenantService::find($tid);
                if (!$tenant || !(int)$tenant['is_active']) {
                    foreach ($mboxes as $mb) {
                        $mailboxErrors[$mb['email']] = 'Tenant inactive or not found';
                        MailboxService::recordSync($mb['email'], 'Tenant inactive or not found', $tid);
                    }
                    continue;
                }
                $token = GraphService::getToken(
                    (string)$tenant['tenant_id'],
                    (string)$tenant['client_id'],
                    (string)$tenant['client_secret']
                );
                if (!$token) {
                    foreach ($mboxes as $mb) {
                        $mailboxErrors[$mb['email']] = 'Tenant auth failed';
                        MailboxService::recordSync($mb['email'], 'Tenant auth failed', $tid);
                    }
                    continue;
                }
                $tenantTokens[$tid] = $token;
            } else {
                // tenant_id = 0: use the default tenant's credentials
                $default = TenantService::getDefault();
                if ($default && (int)$default['is_active']) {
                    $token = GraphService::getToken(
                        (string)$default['tenant_id'],
                        (string)$default['client_id'],
                        (string)$default['client_secret']
                    );
                }
                if (empty($token)) {
                    // Last resort: global settings fallback (pre-migration compat)
                    $token = GraphService::getToken();
                }
                if (!$token) {
                    foreach ($mboxes as $mb) {
                        $mailboxErrors[$mb['email']] = 'auth failed (no credentials)';
                    }
                    continue;
                }
                $tenantTokens[0] = $token;
            }
            $token = $tenantTokens[$tid];

            foreach ($mboxes as $mb) {
                $email = (string)$mb['email'];
                $mbErrors = [];
                foreach ($folders as $label => $folderId) {
                    $cacheKey = MailboxService::slug($email) . '__' . preg_replace('/[^a-z0-9]/i', '', (string)$label);
                    $messages = $useCache ? Cache::get($cacheKey) : null;
                    if (!is_array($messages)) {
                        $cacheMisses++;
                        $messages = [];
                        $url = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($email) .
                               "/mailFolders/{$folderId}/messages?" .
                               $searchExpr .
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
                    MailboxService::recordSync($email, $msg, $tid);
                    $mailboxErrors[$email] = $msg;
                } else {
                    MailboxService::recordSync($email, null, $tid);
                }
            }
        }

        $uniqueFailed = [];
        $bounceMsgKeys = [];
        foreach ($allMessages as $m) {
            $selfEmail = $m['__mailbox'] ?? '';
            $mid = $m['id'] ?? '';
            $failed = BounceService::extractFailedRecipients($m, $selfEmail);
            $hadFailure = false;
            foreach ($failed as $e) {
                $uniqueFailed[strtolower($e)] = $e;
                $hadFailure = true;
                if ($mid !== '') {
                    $ndrTuples[] = ['mailbox' => $selfEmail, 'message_id' => $mid, 'email' => $e];
                }
            }
            if ($hadFailure && $mid !== '') {
                $bounceMsgKeys[$selfEmail . '|' . $mid] = true;
            }
        }
        $bounceMessageCount = count($bounceMsgKeys);

        if (!$windowed) {
            ScanStats::recordToday(
                count($allMessages),
                count($uniqueFailed),
                $bounceMessageCount
            );
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
            'tenants'        => count($tenants),
            'mailboxes'      => count($allMailboxes),
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
            '%s tenants=%d mb=%d msg=%d unique=%d added=%d updated=%d total=%d pruned=%d err=%d %dms',
            $windowed ? 'cron' : 'dashboard',
            $result['tenants'],
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
