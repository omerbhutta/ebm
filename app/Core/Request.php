<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Request abstraction.
 */
final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $post;
    private array $server;
    private array $files;
    private array $cookies;
    private ?array $jsonCache = null;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->query   = $_GET ?? [];
        $this->post    = $_POST ?? [];
        $this->server  = $_SERVER ?? [];
        $this->files   = $_FILES ?? [];
        $this->cookies = $_COOKIE ?? [];

        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Strip base directory (for subfolder installs e.g. /undeliveredemails)
        $scriptName = $this->server['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($baseDir !== '' && $baseDir !== '/' && strpos($uri, $baseDir) === 0) {
            $uri = substr($uri, strlen($baseDir));
        }
        $this->path = '/' . trim($uri, '/');
    }

    public function method(): string  { return $this->method; }
    public function path(): string    { return $this->path; }
    public function isPost(): bool    { return $this->method === 'POST'; }
    public function isGet(): bool     { return $this->method === 'GET'; }
    public function isAjax(): bool
    {
        return strtolower($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
    public function isJson(): bool
    {
        return stripos($this->server['CONTENT_TYPE'] ?? '', 'application/json') !== false;
    }

    public function query(string $key = null, $default = null)
    {
        if ($key === null) return $this->query;
        return $this->query[$key] ?? $default;
    }

    public function post(string $key = null, $default = null)
    {
        if ($key === null) return $this->post;
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        if ($this->isJson()) {
            $data = $this->json();
            if (isset($data[$key])) return $data[$key];
        }
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        $data = array_merge($this->query, $this->post);
        if ($this->isJson()) $data = array_merge($data, $this->json());
        return $data;
    }

    public function json(): array
    {
        if ($this->jsonCache !== null) return $this->jsonCache;
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return $this->jsonCache = is_array($decoded) ? $decoded : [];
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function ip(): string
    {
        foreach (['HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
            if (!empty($this->server[$k])) {
                $ip = explode(',', $this->server[$k])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return (string)($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function fullUrl(): string
    {
        $scheme = (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }
}
