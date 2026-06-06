<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Settings;
use App\Core\Cache;
use App\Core\Logger;

/**
 * Microsoft Graph API client.
 * Pulls credentials from Settings (DB) at call time.
 */
final class GraphService
{
    private const TOKEN_CACHE_KEY = 'graph_token';
    private const TOKEN_TTL_BUFFER = 60;

    public static function getToken(?string $tenantId = null, ?string $clientId = null, ?string $clientSecret = null): ?string
    {
        $tenantId     = $tenantId     ?? (string)Settings::get('graph_tenant_id', '');
        $clientId     = $clientId     ?? (string)Settings::get('graph_client_id', '');
        $clientSecret = $clientSecret ?? (string)Settings::get('graph_client_secret', '');

        if ($tenantId === '' || $clientId === '' || $clientSecret === '') return null;

        $cacheKey = self::TOKEN_CACHE_KEY . '_' . md5($tenantId . '|' . $clientId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['token']) && ($cached['expires_at'] ?? 0) > time() + self::TOKEN_TTL_BUFFER) {
            return $cached['token'];
        }

        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $body = http_build_query([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($code !== 200) {
            Logger::error('graph.token_failed', 'Token acquisition failed', ['http' => $code, 'err' => $err, 'body' => substr((string)$resp, 0, 500)]);
            return null;
        }
        $data = json_decode((string)$resp, true);
        $token = $data['access_token'] ?? null;
        $expiresIn = (int)($data['expires_in'] ?? 3500);
        if (!$token) return null;

        Cache::put($cacheKey, [
            'token'      => $token,
            'expires_at' => time() + $expiresIn,
        ], $expiresIn - self::TOKEN_TTL_BUFFER);

        return $token;
    }

    public static function get(string $url, ?string $token = null): array
    {
        $token = $token ?? self::getToken();
        if (!$token) {
            return ['code' => 0, 'body' => ['error' => ['message' => 'No access token']]];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $code,
            'body' => json_decode((string)$resp, true) ?: [],
        ];
    }

    public static function testCredentials(string $tenantId, string $clientId, string $clientSecret): array
    {
        $url = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
        $body = http_build_query([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => 'https://graph.microsoft.com/.default',
            'grant_type'    => 'client_credentials',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        $data = json_decode((string)$resp, true);

        if ($code === 200 && !empty($data['access_token'])) {
            return ['ok' => true, 'token' => $data['access_token'], 'expires_in' => (int)($data['expires_in'] ?? 0)];
        }
        $msg = $data['error_description'] ?? $data['error'] ?? $err ?: ('HTTP ' . $code);
        if (is_array($msg)) $msg = json_encode($msg);
        return ['ok' => false, 'error' => (string)$msg, 'http' => $code];
    }

    public static function testMailboxAccess(string $email, ?string $token = null): array
    {
        $token = $token ?? self::getToken();
        if (!$token) return ['ok' => false, 'error' => 'No access token. Check Graph credentials.'];

        $url = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($email) . "/messages?\$top=1&\$select=id";
        $r = self::get($url, $token);
        if ($r['code'] === 200) {
            return ['ok' => true];
        }
        $msg = $r['body']['error']['message'] ?? ('HTTP ' . $r['code']);
        return ['ok' => false, 'error' => $msg, 'http' => $r['code']];
    }

    public static function fetchUndeliverableMessages(string $mailboxEmail, array $folders, int $pageLimit = 30): array
    {
        $token = self::getToken();
        if (!$token) return ['ok' => false, 'error' => 'Auth failed', 'messages' => []];

        $all = [];
        $errors = [];
        foreach ($folders as $label => $folderId) {
            $url = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($mailboxEmail) .
                   "/mailFolders/{$folderId}/messages?" .
                   "\$search=\"subject:undeliverable\"" .
                   "&\$select=id,subject,receivedDateTime,from,toRecipients" .
                   "&\$top=100";
            $page = 0;
            while ($url && $page < $pageLimit) {
                $page++;
                $r = self::get($url, $token);
                if ($r['code'] !== 200) {
                    $errors[] = "$label: " . substr((string)($r['body']['error']['message'] ?? 'HTTP ' . $r['code']), 0, 150);
                    break;
                }
                foreach (($r['body']['value'] ?? []) as $m) {
                    $m['__folder']  = $label;
                    $m['__mailbox'] = $mailboxEmail;
                    $all[] = $m;
                }
                $url = $r['body']['@odata.nextLink'] ?? null;
            }
        }

        return [
            'ok'       => true,
            'error'    => $errors ? implode(' | ', $errors) : null,
            'messages' => $all,
        ];
    }

    public static function fetchMessage(string $mailboxEmail, string $messageId): array
    {
        $token = self::getToken();
        if (!$token) return ['ok' => false, 'error' => 'Auth failed'];

        $url = "https://graph.microsoft.com/v1.0/users/" . rawurlencode($mailboxEmail) .
               "/messages/" . rawurlencode($messageId) .
               "?\$select=id,subject,from,toRecipients,receivedDateTime,body,internetMessageHeaders";
        $r = self::get($url, $token);
        if ($r['code'] !== 200) {
            return [
                'ok'    => false,
                'error' => $r['body']['error']['message'] ?? ('HTTP ' . $r['code']),
            ];
        }
        return ['ok' => true, 'message' => $r['body']];
    }
}
