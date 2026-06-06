<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Parses bounce/NDR messages from Microsoft Graph payloads to extract
 * the real failed recipient addresses (not the mailbox that received the NDR).
 */
final class BounceService
{
    private const SYSTEM_HITS = ['postmaster','mailer-daemon','microsoft.com','outlook.com','office365.com','proofpoint','mimecast'];

    public static function extractFailedRecipients(array $msg, string $selfEmail): array
    {
        $found = [];
        $self  = strtolower($selfEmail);

        $add = function (string $e) use (&$found, $self) {
            $e = trim($e, ".,;:()[]\"'");
            if ($e === '') return;
            $le = strtolower($e);
            if ($le === $self) return;
            $local = explode('@', $le, 2)[0] ?? '';
            if (strlen($local) > 30 || strlen($local) < 1) return;
            if (!preg_match('/[aeiouy0-9]/i', $local)) return;
            foreach (self::SYSTEM_HITS as $s) {
                if (strpos($le, $s) !== false) return;
            }
            if (!in_array($e, $found, true)) $found[] = $e;
        };

        foreach (($msg['toRecipients'] ?? []) as $r) {
            $addr = $r['emailAddress']['address'] ?? '';
            if ($addr) $add($addr);
        }

        if (empty($found)) {
            $body = $msg['body']['content'] ?? '';
            if ($body !== '') {
                $text = strip_tags(html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $text = preg_replace('/\s+/', ' ', $text);
                $patterns = [
                    "/Your message to\s+([^\s,;]+@[^\s,;]+)\s+couldn[\xe2\x80\x99\'](?:\')?t be delivered/i",
                    "/Couldn[\xe2\x80\x99\'](?:\')?t deliver the message to the following recipients?\s*:\s*([^\n\r]+?)(?=How to Fix It|Diagnostic|You do not|$)/is",
                    '/Delivery has failed to these recipients or groups?\s*:\s*([^\n\r]+?)(?=Your message|How to Fix It|Diagnostic|You do not|$)/is',
                    '/did not reach the following recipient[^:]*:\s*([^\n\r]+?)(?=Diagnostic|You do not|$)/is',
                    '/undeliverable to the following\s*:\s*([^\n\r]+?)(?=Diagnostic|You do not|$)/is',
                ];
                foreach ($patterns as $pat) {
                    if (preg_match_all($pat, $text, $m)) {
                        foreach ($m[1] as $chunk) {
                            if (preg_match_all('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $chunk, $em)) {
                                foreach ($em[0] as $e) $add($e);
                            }
                        }
                    }
                }
            }
        }

        return $found;
    }

    public static function extractBounceReason(array $msg): string
    {
        $body = $msg['body']['content'] ?? '';
        if ($body === '') return '';
        $text = strip_tags(html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', $text);

        $patterns = [
            '/Diagnostic information.*?:\s*([^\n\r]{0,400})/i',
            '/Remote Server returned:?\s*([^\n\r]{0,300})/i',
            '/(?:5\d\d|4\d\d)\s*[\.\-:]?\s*\d(?:\.\d){0,3}\s*([^\.\n\r]{20,300})/',
            '/Reason:\s*([^\n\r]{0,300})/i',
            '/RESOLVER\.[A-Z\.]+;\s*([^\n\r]{0,300})/i',
        ];
        foreach ($patterns as $pat) {
            if (preg_match($pat, $text, $m)) {
                return trim(substr($m[1], 0, 300));
            }
        }
        $snippet = trim(substr($text, 0, 300));
        return $snippet;
    }

    public static function formatDate(?string $iso, string $format = 'M d, Y H:i'): string
    {
        if (!$iso) return '—';
        $ts = strtotime($iso);
        return $ts ? date($format, $ts) : '—';
    }

    public static function shortReason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') return '—';
        $map = [
            'User does not exist'                 => 'User unknown',
            'Mailbox does not exist'              => 'Mailbox missing',
            'Mailbox unavailable'                 => 'Mailbox unavailable',
            '550 5.1.1'                           => 'User unknown',
            '550 5.7.1'                           => 'Blocked',
            'User unknown'                        => 'User unknown',
            'Quota exceeded'                      => 'Quota full',
            'Message too large'                   => 'Too large',
            'Spam content rejected'               => 'Spam',
            '550'                                 => 'Hard bounce',
            '5.0.0'                               => 'Hard bounce',
            '4.0.0'                               => 'Soft bounce',
        ];
        foreach ($map as $needle => $label) {
            if (stripos($reason, $needle) !== false) return $label;
        }
        return mb_strimwidth($reason, 0, 40, '…');
    }
}
