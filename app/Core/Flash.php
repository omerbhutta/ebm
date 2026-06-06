<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Flash messages — survive one redirect via the session.
 */
final class Flash
{
    public const SUCCESS = 'success';
    public const ERROR   = 'error';
    public const INFO    = 'info';
    public const WARNING = 'warning';

    public static function set(string $type, string $message): void
    {
        Session::start();
        $bag = Session::get('_flash', []);
        if (!is_array($bag)) $bag = [];
        $bag[] = ['type' => $type, 'message' => $message];
        Session::set('_flash', $bag);
    }

    public static function success(string $message): void { self::set(self::SUCCESS, $message); }
    public static function error(string $message): void   { self::set(self::ERROR, $message); }
    public static function info(string $message): void    { self::set(self::INFO, $message); }
    public static function warning(string $message): void { self::set(self::WARNING, $message); }

    public static function pull(): array
    {
        Session::start();
        $bag = Session::get('_flash', []);
        Session::forget('_flash');
        return is_array($bag) ? $bag : [];
    }
}
