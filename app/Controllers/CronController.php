<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Core\Logger;
use App\Core\Lock;
use App\Services\RefreshService;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Automated sync endpoint for external schedulers (cron, EasyCron, …).
 *
 * Auth: shared X-Cron-Token header (or ?token= / ?key= query).
 * Defaults to localhost-only; configurable via cron_local_only setting.
 * Returns minimal JSON — no bounce data is leaked.
 *
 * Schedule example (Linux crontab, every 5 minutes):
 *   every 5 min: curl -fsS -H "X-Cron-Token: $TOKEN" \
 *       http://127.0.0.1:8080/undeliveredemails/cron/refresh
 *
 * (Windows Task Scheduler — see README for the schtasks command.)
 */
final class CronController extends Controller
{
    /** Allowed skew on the local-only check (covers reverse proxies). */
    private const LOCAL_REMOTE_ADDR = ['127.0.0.1', '::1'];

    /** How long the cron window is — last 12 hours of NDRs, per requirements. */
    private const WINDOW_HOURS = 12;

    public function refresh(Request $req): void
    {
        // Lazy-generate cron token on first call so deployments work out-of-the-box.
        $token = self::token();
        if ($token === '') {
            Response::json(['ok' => false, 'error' => 'Cron token not initialised.'], 500);
        }

        $provided = (string)$req->header('X-Cron-Token');
        if ($provided === '') {
            $provided = (string)($req->input('token', '') ?: $req->input('key', ''));
        }
        if ($provided === '' || !hash_equals($token, $provided)) {
            Logger::warn('cron.bad_token', 'Cron request with bad/missing token', ['ip' => $req->ip()]);
            Response::json(['ok' => false, 'error' => 'Invalid or missing cron token.'], 401);
        }

        if (Settings::int('cron_local_only', 1) === 1 && !self::isLocal($req)) {
            Logger::warn('cron.nonlocal', 'Cron request from non-local IP rejected', ['ip' => $req->ip()]);
            Response::json(['ok' => false, 'error' => 'Cron endpoint is restricted to localhost.'], 403);
        }

        $lock = Lock::acquire('cron_refresh', 0);
        if (!$lock) {
            Response::json([
                'ok'    => false,
                'error' => 'Refresh already in progress.',
            ], 423);
        }

        try {
            // Bump the script timeout so a 12h scan across many mailboxes doesn't get killed.
            @set_time_limit(180);
            @ignore_user_abort(true);

            $since = (new DateTimeImmutable('-' . self::WINDOW_HOURS . ' hours', new DateTimeZone('UTC')));
            $result = RefreshService::run(
                forceRefresh: true,   // cron never reads the dashboard cache
                since:        $since,
                maxMessages:  100,    // per-folder cap inside the 12h window
                pageLimit:    20
            );

            Response::json([
                'ok'             => true,
                'started_at'     => $result['started_at'],
                'duration_ms'    => $result['duration_ms'],
                'window_hours'   => self::WINDOW_HOURS,
                'mailboxes'      => $result['mailboxes'],
                'messages'       => $result['messages'],
                'unique_failed'  => $result['unique_failed'],
                'added'          => $result['sync']['added'],
                'updated'        => $result['sync']['updated'],
                'suppression_total' => $result['sync']['total'],
                'mailbox_errors' => $result['mailbox_errors'],
            ]);
        } catch (\Throwable $e) {
            Logger::error('cron.exception', $e->getMessage(), ['trace' => substr($e->getTraceAsString(), 0, 800)]);
            Response::json([
                'ok'    => false,
                'error' => 'Refresh failed: ' . $e->getMessage(),
            ], 500);
        } finally {
            Lock::release($lock);
        }
    }

    /**
     * Return the configured cron token, generating a fresh one on first use.
     * Tokens are 40 hex chars (160-bit) — stored in settings, not in code.
     */
    public static function token(): string
    {
        $existing = (string)Settings::get('cron_token', '');
        if ($existing !== '') return $existing;
        $fresh = bin2hex(random_bytes(20));
        Settings::set('cron_token', $fresh);
        Logger::warn('cron.token_generated', 'A new cron token was auto-generated. Rotate it from /admin/security.');
        return $fresh;
    }

    public static function rotateToken(): string
    {
        $fresh = bin2hex(random_bytes(20));
        Settings::set('cron_token', $fresh);
        Logger::auth('cron.token_rotated', 'Cron token rotated');
        return $fresh;
    }

    private static function isLocal(Request $req): bool
    {
        $ip = $req->ip();
        if (in_array($ip, self::LOCAL_REMOTE_ADDR, true)) return true;
        // Trust X-Forwarded-For only if we explicitly say so (we don't by default).
        return false;
    }
}
