<?php
declare(strict_types=1);

namespace App\Core;

/** Přihlášení kolegů do administrace. Každý má vlastní účet. */
final class Auth
{
    public static function user(): ?array
    {
        $id = Session::get('uid');
        if (!$id) return null;
        static $cache = null;
        if ($cache && (int) $cache['id'] === (int) $id) return $cache;
        $cache = Db::one('SELECT id, name, email, role FROM users WHERE id = ? AND active = 1', [$id]);
        return $cache;
    }

    public static function attempt(string $email, string $pass): bool
    {
        $u = Db::one('SELECT * FROM users WHERE email = ? AND active = 1', [mb_strtolower(trim($email))]);
        if (!$u || !password_verify($pass, $u['pass_hash'])) return false;
        session_regenerate_id(true);
        Session::set('uid', (int) $u['id']);
        Db::exec('UPDATE users SET last_login = ? WHERE id = ?', [Db::now(), $u['id']]);
        return true;
    }

    public static function logout(): void
    {
        Session::forget('uid');
        session_regenerate_id(true);
    }

    public static function require(?string $role = null): array
    {
        $u = self::user();
        if (!$u) {
            Session::set('after_login', Http::path());
            Http::redirect('/admin/prihlaseni');
        }
        if ($role === 'admin' && $u['role'] !== 'admin') {
            http_response_code(403);
            View::page('layout/message', ['title' => 'Nemáte oprávnění', 'text' => 'Tuto část může upravovat jen správce.'], 'admin');
            exit;
        }
        return $u;
    }

    public static function createUser(string $name, string $email, string $pass, string $role = 'staff'): int
    {
        return Db::insert('users', [
            'name' => $name,
            'email' => mb_strtolower(trim($email)),
            'pass_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'role' => $role,
            'active' => 1,
            'created_at' => Db::now(),
        ]);
    }
}
