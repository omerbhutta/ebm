<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Application container — singleton access to shared services.
 */
final class App
{
    private static ?App $instance = null;
    private array $services = [];
    private string $basePath;
    private string $baseUrl = '';

    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
    }

    public static function boot(string $basePath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
        }
        return self::$instance;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('App not booted.');
        }
        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }

    public function viewsPath(string $path = ''): string
    {
        return $this->basePath('app' . DIRECTORY_SEPARATOR . 'Views' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }

    public function setBaseUrl(string $url): void
    {
        $this->baseUrl = rtrim($url, '/');
    }

    public function baseUrl(string $path = ''): string
    {
        return $this->baseUrl . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function bind(string $key, $value): void
    {
        $this->services[$key] = $value;
    }

    public function make(string $key)
    {
        if (!isset($this->services[$key])) {
            throw new \RuntimeException("Service '{$key}' not bound.");
        }
        $v = $this->services[$key];
        return is_callable($v) && !is_string($v) ? $v($this) : $v;
    }

    public function isInstalled(): bool
    {
        return is_file($this->configPath('installed.php'));
    }
}
