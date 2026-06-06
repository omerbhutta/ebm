<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Base controller — shared helpers.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data['_settings'] = Settings::all();
        $data['_auth'] = Auth::user();
        $data['_flash'] = Flash::pull();
        if ($layout !== null && empty($data['title'])) {
            $data['title'] = self::titleFromTemplate($template);
        }
        View::render($template, $data, $layout);
    }

    private static function titleFromTemplate(string $template): string
    {
        $base = basename(str_replace('\\', '/', $template));
        $base = preg_replace('/[._-]+/', ' ', $base);
        $base = trim(ucwords($base));
        return $base;
    }

    protected function redirect(string $path): void
    {
        Response::redirect(App::instance()->baseUrl($path));
    }

    protected function back(string $fallback = '/'): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref !== '') {
            Response::redirect($ref);
        }
        $this->redirect($fallback);
    }

    protected function json($data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function abort(int $code, string $message = ''): void
    {
        if ($code === 404) Response::notFound($message ?: 'Not Found');
        Response::serverError($message ?: 'Error');
    }

    protected function verifyCsrf(Request $req): void
    {
        Csrf::check($req);
    }

    protected function validate(array $data, array $rules): Validator
    {
        return Validator::make($data, $rules);
    }
}
