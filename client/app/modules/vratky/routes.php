<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Db;
use App\Core\Http;
use App\Core\RateLimit;
use App\Core\Session;
use App\Core\View;
use App\Modules\Vratky\Notify;
use App\Modules\Vratky\Packeta;
use App\Modules\Vratky\Photos;
use App\Modules\Vratky\Service;
use App\Modules\Vratky\Sync;

// ================= zákazník =================

Http::get('/vratky', function () {
    View::page('vratky:find', ['title' => 'Vrácení, výměna a reklamace', 'homeUrl' => '/vratky',
        'email' => (string) Session::get('vr_email', ''), 'number' => '']);
});

Http::post('/vratky', function () {
    Session::checkCsrf();
    $email = Http::input('email');
    $number = Http::input('number');
    Session::set('vr_email', $email);
    if (!RateLimit::hit('vr_find', 20, 3600)) {
        Session::flash('Příliš mnoho pokusů. Zkuste to prosím za hodinu, nebo nám zavolejte na ' . Config::get('shop.phone') . '.', 'err');
        Http::redirect('/vratky');
    }
    $o = Service::findOrder($email, $number);
    if (!$o) {
        Session::flash('Objednávku jsme nenašli. Zkontrolujte e-mail a číslo objednávky (začíná 8810…) nebo faktury.', 'err');
        Http::redirect('/vratky');
    }
    Session::set('vr_order', (int) $o['id']);
    Http::redirect('/vratky/novy');
});

Http::post('/vratky/odkaz', function () {
    Session::checkCsrf();
    $email = mb_strtolower(Http::input('email'));
    Session::set('vr_email', $email);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { Session::flash('Zadejte platný e-mail.', 'err'); Http::redirect('/vratky?zpusob=email'); }
    if (RateLimit::hit('vr_link', 5, 3600) && Service::ordersByEmail($email)) {
        $url = rtrim((string) Config::get('app_url'), '/') . '/vratky/moje/' . Service::linkToken($email);
        \App\Core\Mailer::send($email, 'Odkaz na vaše objednávky – 4 KLUCI OD KOL',
            View::render('vratky:email_link', ['url' => $url]));
    }
    // stejná odpověď vždy – neprozrazujeme, jestli e-mail u nás existuje
    Http::redirect('/vratky/odkaz-odeslan');
});

Http::get('/vratky/odkaz-odeslan', function () {
    View::page('vratky:link_sent', ['title' => 'Zkontrolujte e-mail', 'homeUrl' => '/vratky', 'email' => (string) Session::get('vr_email', '')]);
});

Http::get('/vratky/moje/{token}', function (string $token) {
    $email = Service::linkEmail($token);
    if (!$email) {
        View::page('layout/message', ['title' => 'Odkaz už neplatí', 'text' => 'Odkaz platí 24 hodin. Požádejte prosím o nový na stránce vratek.']);
        return;
    }
    Session::set('vr_link_email', $email);
    View::page('vratky:my_orders', ['title' => 'Vaše objednávky', 'homeUrl' => '/vratky', 'email' => $email,
        'orders' => Service::ordersByEmail($email)]);
});

Http::post('/vratky/vybrat', function () {
    Session::checkCsrf();
    $email = (string) Session::get('vr_link_email', '');
    $o = Service::order((int) Http::input('order'));
    if ($email === '' || !$o || mb_strtolower($o['email']) !== $email) Http::redirect('/vratky');
    Session::set('vr_order', (int) $o['id']);
    Http::redirect('/vratky/novy');
});

$loadOrder = function (): array {
    $id = (int) Session::get('vr_order', 0);
    $o = $id ? Service::order($id) : null;
    if (!$o) { Session::flash('Nejdřív najděte svou objednávku.', 'err'); Http::redirect('/vratky'); }
    return $o;
};

Http::get('/vratky/novy', function () use ($loadOrder) {
    $o = $loadOrder();
    $items = Service::items((int) $o['id']);
    View::page('vratky:form', ['title' => 'Co chcete udělat?', 'homeUrl' => '/vratky', 'order' => $o, 'items' => $items,
        'dl' => Service::deadlines($o), 'old' => Session::get('vr_old', []), 'errors' => []]);
});

Http::post('/vratky/novy', function () use ($loadOrder) {
    Session::checkCsrf();
    $o = $loadOrder();
    $items = Service::items((int) $o['id']);
    [$errors, $data] = Service::validate($o, $items, $_POST, $_FILES);
    if ($errors) {
        http_response_code(422);
        View::page('vratky:form', ['title' => 'Co chcete udělat?', 'homeUrl' => '/vratky', 'order' => $o, 'items' => $items,
            'dl' => Service::deadlines($o), 'old' => $_POST, 'errors' => $errors]);
        return;
    }
    $ret = Service::create($o, $data);
    if (Packeta::enabled() && $ret['ship_method'] !== 'osobne') {
        Packeta::createClaim($ret);
        $ret = Service::get((int) $ret['id']);
    }
    Notify::created($ret);
    Session::forget('vr_order');
    Http::redirect('/vratky/stav/' . $ret['token']);
});

Http::get('/vratky/stav/{token}', function (string $token) {
    $ret = preg_match('/^[a-f0-9]{40}$/', $token) ? Service::byToken($token) : null;
    if (!$ret) { http_response_code(404); View::page('layout/notfound', ['title' => 'Vratka nenalezena']); return; }
    View::page('vratky:status', ['title' => 'Vratka ' . $ret['no'], 'homeUrl' => '/vratky', 'ret' => $ret,
        'lines' => Service::lines((int) $ret['id']), 'events' => Service::events((int) $ret['id'], true)]);
});

// ================= administrace =================

Http::get('/admin/vratky', function () {
    Auth::require();
    $stav = Http::input('stav');
    $q = Http::input('q');
    $where = [];
    $p = [];
    if ($stav !== '' && isset(Service::STATUS[$stav])) { $where[] = 'r.status = ?'; $p[] = $stav; }
    elseif ($stav === '') { $where[] = "r.status NOT IN ('vyrizeno','zamitnuto')"; }
    if ($q !== '') { $where[] = '(r.no LIKE ? OR r.email LIKE ? OR r.customer_name LIKE ? OR o.order_no LIKE ? OR o.invoice_no LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
    $rows = Db::all('SELECT r.*, o.order_no, o.invoice_no FROM returns r LEFT JOIN orders o ON o.id = r.order_id' .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY r.id DESC LIMIT 200', $p);
    foreach ($rows as &$r) $r['lines'] = Service::lines((int) $r['id']);
    $counts = [];
    foreach (Db::all('SELECT status, COUNT(*) c FROM returns GROUP BY status') as $c) $counts[$c['status']] = (int) $c['c'];
    $lastSync = Db::one('SELECT * FROM sync_log ORDER BY id DESC LIMIT 1');
    View::page('vratky:admin_list', ['title' => 'Vratky', 'active' => 'vratky', 'rows' => $rows, 'counts' => $counts,
        'stav' => $stav, 'q' => $q, 'lastSync' => $lastSync], 'admin');
});

// ---- nová reklamace zadaná prodejnou (zboží z prodejny i z e-shopu)
Http::get('/admin/vratky/nova', function () {
    Auth::require();
    $q = Http::input('q'); $od = Http::input('od'); $do = Http::input('do');
    View::page('vratky:admin_new', ['title' => 'Nová reklamace', 'active' => 'vratky', 'q' => $q, 'od' => $od, 'do' => $do,
        'rows' => Service::searchOrders($q, $od, $do), 'searched' => ($q !== '' || $od !== '' || $do !== '')], 'admin');
});

$adminClaimPage = function (array $o, array $old, array $errors): void {
    $items = Service::items((int) $o['id']);
    View::page('vratky:admin_new_form', ['title' => 'Nová reklamace', 'active' => 'vratky', 'order' => $o, 'items' => $items,
        'dl' => Service::deadlines($o), 'old' => $old, 'errors' => $errors], 'admin');
};

Http::get('/admin/vratky/nova/{id}', function (string $id) use ($adminClaimPage) {
    Auth::require();
    $o = Service::order((int) $id);
    if (!$o) Http::redirect('/admin/vratky/nova');
    $adminClaimPage($o, [], []);
});

Http::post('/admin/vratky/nova/{id}', function (string $id) use ($adminClaimPage) {
    $u = Auth::require();
    Session::checkCsrf();
    $o = Service::order((int) $id);
    if (!$o) Http::redirect('/admin/vratky/nova');
    $items = Service::items((int) $o['id']);
    [$errors, $data] = Service::validateAdmin($o, $items, $_POST, $_FILES);
    if ($errors) { http_response_code(422); $adminClaimPage($o, $_POST, $errors); return; }
    $ret = Service::create($o, $data, $u);
    if (Packeta::enabled() && $ret['ship_method'] !== 'osobne') {
        Packeta::createClaim($ret);
        $ret = Service::get((int) $ret['id']);
    }
    if (!empty($_POST['notify'])) Notify::created($ret, true);
    Session::flash('Reklamace ' . $ret['no'] . ' založena.' . (!empty($_POST['notify']) && $ret['email'] !== '' ? ' Zákazník dostal potvrzení e-mailem.' : ''));
    Http::redirect('/admin/vratky/' . $ret['no']);
});

Http::get('/admin/vratky/foto/{id}', function (string $id) {
    Auth::require();
    Photos::output((int) $id);
});

Http::get('/admin/vratky/{no}', function (string $no) {
    Auth::require();
    $ret = Service::byNo($no);
    if (!$ret) { http_response_code(404); View::page('layout/notfound', ['title' => 'Vratka nenalezena'], 'admin'); return; }
    $lines = Service::lines((int) $ret['id']);
    foreach ($lines as &$l) $l['photos'] = Photos::forLine((int) $l['id']);
    View::page('vratky:admin_detail', ['title' => 'Vratka ' . $ret['no'], 'active' => 'vratky', 'ret' => $ret,
        'order' => $ret['order_id'] ? Service::order((int) $ret['order_id']) : null,
        'lines' => $lines, 'events' => Service::events((int) $ret['id'])], 'admin');
});

Http::post('/admin/vratky/{no}', function (string $no) {
    $u = Auth::require();
    Session::checkCsrf();
    $ret = Service::byNo($no);
    if (!$ret) Http::redirect('/admin/vratky');
    $act = Http::input('act');
    if ($act === 'packeta') {
        if (Packeta::createClaim($ret)) {
            $ret = Service::get((int) $ret['id']);
            Notify::created($ret);
            Session::flash('Podací kód vytvořen a odeslán zákazníkovi.');
        } else {
            Session::flash('Kód se nepodařilo vytvořit. ' . (Service::get((int) $ret['id'])['packeta_error'] ?? 'Zásilkovna není zapnutá.'), 'err');
        }
        Http::redirect('/admin/vratky/' . $no);
    }
    $status = Http::input('status');
    if ($status !== '' && !isset(Service::STATUS[$status])) $status = '';
    $note = mb_substr(Http::input('note'), 0, 3000);
    $notify = !empty($_POST['notify']);
    if ($status === '' && $note === '') { Session::flash('Vyberte nový stav nebo napište poznámku.', 'err'); Http::redirect('/admin/vratky/' . $no); }
    Service::setStatus($ret, $status === $ret['status'] ? '' : $status, $note, $notify, (int) $u['id']);
    if ($notify && $ret['email'] !== '') {
        Notify::statusChanged(Service::get((int) $ret['id']), $note);
        Session::flash('Uloženo a zákazník dostal e-mail.');
    } else {
        Session::flash('Uloženo.');
    }
    Http::redirect('/admin/vratky/' . $no);
});

// ================= API pro pomocníka (Pohoda) =================

Http::post('/api/sync/objednavky', function () {
    $token = (string) Config::get('sync_token', '');
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $given = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : (string) ($_SERVER['HTTP_X_SYNC_TOKEN'] ?? '');
    if ($token === '' || !hash_equals($token, trim($given))) Http::json(['ok' => false, 'error' => 'unauthorized'], 401);
    $raw = (string) file_get_contents('php://input');
    if (strlen($raw) > 20 * 1024 * 1024) Http::json(['ok' => false, 'error' => 'too large'], 413);
    $data = json_decode($raw, true);
    if (!is_array($data)) Http::json(['ok' => false, 'error' => 'invalid json'], 400);
    $res = Sync::import($data);
    Http::json(['ok' => true] + $res);
});
