<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Simple view renderer with layouts and partials.
 *   View::render('dashboard/index', ['key' => 'value'], 'layouts/app');
 */
final class View
{
    private static array $shared = [];

    public static function share(string $key, $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        echo self::make($view, $data, $layout);
    }

    public static function make(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderFile($view, $data);
        if ($layout === null) return $content;

        $layoutData = array_merge($data, ['content' => $content, 'body' => $content]);
        return self::renderFile($layout, $layoutData);
    }

    public static function partial(string $view, array $data = []): string
    {
        return self::renderFile($view, $data);
    }

    private static function renderFile(string $view, array $data): string
    {
        $file = App::instance()->viewsPath(str_replace(['..', '\\'], '', $view) . '.php');
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        $merged = array_merge(self::$shared, $data);
        extract($merged, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } finally {
            $content = (string)ob_get_clean();
        }
        return $content;
    }

    public static function e($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(string $path = ''): string
    {
        return App::instance()->baseUrl($path);
    }

    public static function asset(string $path): string
    {
        return App::instance()->baseUrl('assets/' . ltrim($path, '/'));
    }
}
