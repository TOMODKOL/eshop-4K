<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Db;
use App\Core\Http;
use App\Core\Installer;
use App\Core\RateLimit;
use App\Core\Session;
use App\Core\View;

// ---- první spuštění: tabulky + první správce (chráněno instalačním klíčem = sync_token z config.php)
Http::get('/admin/instalace', function () {
    if (Installer::hasUsers()) Http::redirect('/admin');
    View::page('admin/install', ['title' => 'Instalace'], 'admin');
});
Http::post('/admin/instalace', function () {
    Session::checkCsrf();
    if (Installer::hasUsers()) Http::redirect('/admin');
    if (!RateLimit::hit('install', 10, 3600)) { http_response_code(429); exit('Příliš mnoho pokusů.'); }
    $key = (string) Config::get('sync_token', '');
    $err = null;
    if ($key === '' || !hash_equals($key, Http::input('key'))) $err = 'Instalační klíč nesouhlasí s sync_token v config.php.';
    elseif (Http::input('name') === '' || !filter_var(Http::input('email'), FILTER_VALIDATE_EMAIL)) $err = 'Vyplňte jméno a platný e-mail.';
    elseif (mb_strlen($_POST['pass'] ?? '') < 10) $err = 'Heslo musí mít aspoň 10 znaků.';
    if ($err) { Session::flash($err, 'err'); Http::redirect('/admin/instalace'); }
    Installer::run();
    Auth::createUser(Http::input('name'), Http::input('email'), (string) $_POST['pass'], 'admin');
    Session::flash('Hotovo. Teď se přihlaste.');
    Http::redirect('/admin/prihlaseni');
});

// ---- přihlášení
Http::get('/admin/prihlaseni', function () {
    if (!Installer::hasUsers()) Http::redirect('/admin/instalace');
    if (Auth::user()) Http::redirect('/admin');
    View::page('admin/login', ['title' => 'Přihlášení'], 'admin');
});
Http::post('/admin/prihlaseni', function () {
    Session::checkCsrf();
    if (!RateLimit::hit('login', 10, 900)) {
        Session::flash('Příliš mnoho pokusů. Zkuste to za 15 minut.', 'err');
        Http::redirect('/admin/prihlaseni');
    }
    if (!Auth::attempt(Http::input('email'), (string) ($_POST['pass'] ?? ''))) {
        Session::flash('Nesprávný e-mail nebo heslo.', 'err');
        Http::redirect('/admin/prihlaseni');
    }
    $to = (string) Session::get('after_login', '/admin');
    Session::forget('after_login');
    Http::redirect(str_starts_with($to, '/admin') ? $to : '/admin');
});
Http::post('/admin/odhlaseni', function () {
    Session::checkCsrf();
    Auth::logout();
    Http::redirect('/admin/prihlaseni');
});

Http::get('/admin', function () {
    Auth::require();
    if (Config::module('vratky')) Http::redirect('/admin/vratky');
    View::page('layout/message', ['title' => 'Administrace', 'text' => 'Zatím tu není zapnutý žádný modul.'], 'admin');
});

// ---- kolegové (jen správce)
Http::get('/admin/uzivatele', function () {
    Auth::require('admin');
    $users = Db::all('SELECT id, name, email, role, active, last_login FROM users ORDER BY active DESC, name');
    View::page('admin/users', ['title' => 'Kolegové', 'users' => $users, 'active' => 'users'], 'admin');
});
Http::post('/admin/uzivatele', function () {
    $me = Auth::require('admin');
    Session::checkCsrf();
    $act = Http::input('act');
    if ($act === 'add') {
        $email = mb_strtolower(Http::input('email'));
        if (Http::input('name') === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($_POST['pass'] ?? '') < 10) {
            Session::flash('Vyplňte jméno, platný e-mail a heslo aspoň 10 znaků.', 'err');
        } elseif (Db::one('SELECT id FROM users WHERE email = ?', [$email])) {
            Session::flash('Kolega s tímto e-mailem už existuje.', 'err');
        } else {
            Auth::createUser(Http::input('name'), $email, (string) $_POST['pass'], Http::input('role') === 'admin' ? 'admin' : 'staff');
            Session::flash('Kolega přidán. Heslo mu předejte osobně, ať si ho po přihlášení změní.');
        }
    } elseif ($act === 'toggle') {
        $id = (int) Http::input('id');
        if ($id === (int) $me['id']) Session::flash('Sám sebe vypnout nemůžete.', 'err');
        else { Db::exec('UPDATE users SET active = 1 - active WHERE id = ?', [$id]); Session::flash('Uloženo.'); }
    } elseif ($act === 'pass') {
        $id = (int) Http::input('id');
        if (mb_strlen($_POST['pass'] ?? '') < 10) Session::flash('Heslo musí mít aspoň 10 znaků.', 'err');
        else { Db::exec('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash((string) $_POST['pass'], PASSWORD_DEFAULT), $id]); Session::flash('Heslo změněno.'); }
    }
    Http::redirect('/admin/uzivatele');
});

// ---- vlastní heslo
Http::post('/admin/heslo', function () {
    $me = Auth::require();
    Session::checkCsrf();
    $u = Db::one('SELECT pass_hash FROM users WHERE id = ?', [$me['id']]);
    if (!password_verify((string) ($_POST['old'] ?? ''), $u['pass_hash'])) Session::flash('Současné heslo nesouhlasí.', 'err');
    elseif (mb_strlen($_POST['pass'] ?? '') < 10) Session::flash('Nové heslo musí mít aspoň 10 znaků.', 'err');
    else { Db::exec('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash((string) $_POST['pass'], PASSWORD_DEFAULT), $me['id']]); Session::flash('Heslo změněno.'); }
    Http::redirect('/admin/uzivatele/ja');
});
Http::get('/admin/uzivatele/ja', function () {
    Auth::require();
    View::page('admin/me', ['title' => 'Můj účet'], 'admin');
});
