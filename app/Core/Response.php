<?php
declare(strict_types=1);

namespace App\Core;

/**
 * HTTP Response helper.
 */
final class Response
{
    public static function redirect(string $url, int $status = 302): void
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function text(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
        exit;
    }

    public static function download(string $body, string $filename, string $mime = 'application/octet-stream'): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($body));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $body;
        exit;
    }

    public static function notFound(string $message = 'Not Found'): void
    {
        http_response_code(404);
        if (is_file(\App\Core\App::instance()->viewsPath('errors/404.php'))) {
            $msg = $message;
            require \App\Core\App::instance()->viewsPath('errors/404.php');
        } else {
            echo $message;
        }
        exit;
    }

    public static function serverError(string $message = 'Server Error'): void
    {
        http_response_code(500);
        if (is_file(\App\Core\App::instance()->viewsPath('errors/500.php'))) {
            $msg = $message;
            require \App\Core\App::instance()->viewsPath('errors/500.php');
        } else {
            echo $message;
        }
        exit;
    }
}
