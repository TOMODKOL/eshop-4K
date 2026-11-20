<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /** Vykreslí šablonu. 'vratky:find' = app/modules/vratky/views/find.php, 'admin/home' = app/views/admin/home.php */
    public static function render(string $name, array $vars = []): string
    {
        if (str_contains($name, ':')) {
            [$mod, $tpl] = explode(':', $name, 2);
            $file = APP_ROOT . '/app/modules/' . $mod . '/views/' . $tpl . '.php';
        } else {
            $file = APP_ROOT . '/app/views/' . $name . '.php';
        }
        if (!is_file($file)) throw new \RuntimeException('Šablona nenalezena: ' . $name);
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /** Celá stránka v rozvržení 'public' (zákazník) nebo 'admin' (prodejna). */
    public static function page(string $name, array $vars = [], string $layout = 'public'): void
    {
        $vars['content'] = self::render($name, $vars);
        $vars['flash'] = Session::takeFlash();
        header('Content-Type: text/html; charset=utf-8');
        echo self::render('layout/' . $layout, $vars);
    }
}
