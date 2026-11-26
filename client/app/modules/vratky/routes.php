<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Db;
use App\Core\Http;
use App\Core\RateLimit;
use App\Core\Session;
use App\Core\View;
use App\Modules\Vratky\Eshop;
use App\Modules\Vratky\GpWebpay;
use App\Modules\Vratky\Invoices;
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
    Session::forget('vr_old');
    Http::redirect('/vratky/novy');
});

// odkaz z účtu zákazníka na e-shopu: /vratky/objednavka?obj=8810…&email=… → rovnou formulář (stejné údaje jako ruční hledání)
Http::get('/vratky/objednavka', function () {
    $email = trim((string) ($_GET['email'] ?? ''));
    $number = trim((string) ($_GET['obj'] ?? ''));
    $o = RateLimit::hit('vr_find', 20, 3600) ? Service::findOrder($email, $number) : null;
    if (!$o) {
        Session::set('vr_email', $email);
        Session::flash('Objednávku se nepodařilo otevřít automaticky. Zadejte prosím e-mail a číslo objednávky nebo faktury.', 'err');
        Http::redirect('/vratky');
    }
    Session::set('vr_email', $email);
    Session::set('vr_order', (int) $o['id']);
    Session::forget('vr_old');
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
        View::page('layout/message', ['title' => 'Odkaz už neplatí', 'text' => 'Odkaz platí 24 hodin. Požádejte prosím o nový.']);
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
    Session::forget('vr_old');
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
    $items = Service::items((int) $o['id'], true);
    $old = (array) Session::get('vr_old', []);
    // návrat z e-shopu: zákazník vybral zboží tlačítkem „Vyměnit za tento produkt“
    $swap = (int) ($_GET['swap'] ?? 0);
    if ($swap && in_array($swap, array_map(fn($i) => (int) $i['id'], $items), true) && Eshop::productUrl((string) ($_GET['url'] ?? ''))) {
        $old['act'][$swap] = 'vymenit';
        $old['swap_kind'][$swap] = 'jine';
        $old['other_url'][$swap] = Eshop::productUrl((string) $_GET['url']);
        $old['other_pick'][$swap] = mb_substr((string) ($_GET['size'] ?? ''), 0, 40);
        $old['other_qty'][$swap] = max(1, min(20, (int) ($_GET['qty'] ?? 1)));
        Session::set('vr_old', $old);
    }
    View::page('vratky:form', ['title' => 'Co chcete udělat?', 'homeUrl' => '/vratky', 'order' => $o, 'items' => $items,
        'dl' => Service::deadlines($o), 'old' => $old, 'errors' => [], 'start' => 1]);
});

// rozpracovaný formulář před odchodem na e-shop (výběr zboží k výměně)
Http::post('/vratky/koncept', function () use ($loadOrder) {
    Session::checkCsrf();
    $loadOrder();
    $keep = [];
    foreach (['act', 'qty', 'reason', 'size_to', 'swap_kind', 'other_url', 'other_pick', 'other_qty', 'defect', 'ship', 'account', 'phone', 'note', 'pay_method'] as $k) {
        if (isset($_POST[$k])) $keep[$k] = $_POST[$k];
    }
    Session::set('vr_old', $keep);
    http_response_code(204);
});

// výměna za jiné zboží: hledání na e-shopu (HTML kousek formuláře)
Http::get('/vratky/hledat', function () use ($loadOrder) {
    $loadOrder();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $q = trim((string) ($_GET['q'] ?? ''));
    $res = RateLimit::hit('vr_search', 120, 3600) ? Eshop::search($q) : null;
    echo View::render('vratky:search_results', ['res' => $res, 'id' => (int) ($_GET['id'] ?? 0), 'q' => $q]);
});

// výměna za jiné zboží: načte zboží z e-shopu podle odkazu (HTML kousek formuláře)
Http::get('/vratky/zbozi', function () use ($loadOrder) {
    $loadOrder();
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $url = trim((string) ($_GET['url'] ?? ''));
    $p = RateLimit::hit('vr_prod', 60, 3600) ? Eshop::product($url) : null;
    echo View::render('vratky:other_product', ['p' => $p, 'id' => (int) ($_GET['id'] ?? 0), 'sel' => '', 'url' => $url, 'oqty' => 1]);
});

Http::post('/vratky/novy', function () use ($loadOrder) {
    Session::checkCsrf();
    $o = $loadOrder();
    $items = Service::items((int) $o['id'], true);
    [$errors, $data] = Service::validate($o, $items, $_POST, $_FILES);
    if ($errors) {
        http_response_code(422);
        View::page('vratky:form', ['title' => 'Co chcete udělat?', 'homeUrl' => '/vratky', 'order' => $o, 'items' => $items,
            'dl' => Service::deadlines($o), 'old' => $_POST, 'errors' => $errors]);
        return;
    }
    $ret = Service::create($o, $data);
    Session::forget('vr_old');
    if (Packeta::enabled() && Service::usesPacketa((string) $ret['ship_method'])) {
        Packeta::createClaim($ret);
        $ret = Service::get((int) $ret['id']);
    }
    Notify::created($ret);
    Session::forget('vr_order');
    Http::redirect('/vratky/stav/' . $ret['token']);
});

Http::get('/vratky/stav/{token}', function (string $token) {
    $ret = preg_match('/^[a-f0-9]{40}$/', $token) ? Service::byToken($token) : null;
    if (!$ret) { http_response_code(404); View::page('layout/notfound', ['title' => 'Stránka nenalezena']); return; }
    View::page('vratky:status', ['title' => Service::title($ret, Service::lines((int) $ret['id'])), 'homeUrl' => '/vratky', 'ret' => $ret,
        'lines' => Service::lines((int) $ret['id']), 'events' => Service::events((int) $ret['id'], true)]);
});

// QR Platba k doplatku výměny (PNG – web i e-mail)
Http::get('/vratky/qr/{token}', function (string $token) {
    $ret = preg_match('/^[a-f0-9]{40}$/', $token) ? Service::byToken($token) : null;
    $spd = $ret ? Service::spayd($ret) : '';
    if ($spd === '' || !function_exists('imagecreatetruecolor')) { http_response_code(404); exit; }
    $o = new \chillerlan\QRCode\QROptions(['outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG, 'imageBase64' => false, 'scale' => 8,
        'eccLevel' => \chillerlan\QRCode\QRCode::ECC_M, 'addQuietzone' => true, 'quietzoneSize' => 2]);
    header('Content-Type: image/png');
    header('Cache-Control: private, max-age=3600');
    echo (new \chillerlan\QRCode\QRCode($o))->render($spd);
    exit;
});

// Doplatek výměny kartou – přesměrování na GP webpay (odkaz ze stránky stavu i z e-mailu)
Http::get('/vratky/platba/{token}', function (string $token) {
    $ret = preg_match('/^[a-f0-9]{40}$/', $token) ? Service::byToken($token) : null;
    if (!$ret) { http_response_code(404); View::page('layout/notfound', ['title' => 'Stránka nenalezena']); return; }
    if (!empty($ret['zf_paid'])) { Session::flash('Doplatek je už zaplacený, děkujeme.'); Http::redirect('/vratky/stav/' . $token); }
    if (!GpWebpay::canPay($ret) || !RateLimit::hit('vr_card', 20, 3600)) {
        Session::flash('Kartou teď zaplatit nejde. Zaplaťte prosím převodem podle údajů níže.', 'err');
        Http::redirect('/vratky/stav/' . $token);
    }
    try { $url = GpWebpay::createPaymentUrl($ret); }
    catch (\Throwable $e) { error_log('GP webpay: ' . $e->getMessage()); Session::flash('Platební bránu se nepodařilo otevřít. Zaplaťte prosím převodem, nebo to zkuste později.', 'err'); Http::redirect('/vratky/stav/' . $token); }
    header('Cache-Control: no-store');
    header('Location: ' . $url, true, 303);
    exit;
});

// Návrat z GP webpay (GP posílá výsledek jako GET, případně POST)
$cardReturn = function () {
    $r = GpWebpay::handleResponse($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);
    Session::flash($r['message'], $r['ok'] ? 'ok' : 'err');
    if ($r['token']) Http::redirect('/vratky/stav/' . $r['token']);
    View::page('layout/message', ['title' => 'Platba kartou', 'text' => $r['message']]);
};
Http::get('/vratky/platba-vysledek', $cardReturn);
Http::post('/vratky/platba-vysledek', $cardReturn);

// kontrola zboží z e-mailu prodejně (podepsaný odkaz, bez přihlášení; GET jen ukáže potvrzení – kvůli náhledům odkazů v poště)
$checkPage = function (string $no, ?string $done = null, ?string $err = null) {
    $ret = Service::byNo($no);
    $e = (string) ($_REQUEST['e'] ?? ''); $s = (string) ($_REQUEST['s'] ?? ''); $a = ($_REQUEST['a'] ?? '') === 'problem' ? 'problem' : 'ok';
    if (!$ret || !Service::checkValid($no, (int) $e, $s)) { http_response_code(403); View::page('layout/message', ['title' => 'Odkaz neplatí', 'text' => 'Odkaz už neplatí. Otevřete prosím vratku v administraci.']); return; }
    View::page('vratky:check', ['title' => 'Kontrola zboží', 'ret' => $ret, 'lines' => Service::lines((int) $ret['id']), 'a' => $a, 'e' => $e, 's' => $s, 'done' => $done, 'err' => $err]);
};
Http::get('/vratky/kontrola/{no}', fn(string $no) => $checkPage($no));
Http::post('/vratky/kontrola/{no}', function (string $no) use ($checkPage) {
    $ret = Service::byNo($no);
    if (!$ret || !Service::checkValid($no, (int) Http::input('e'), Http::input('s'))) { $checkPage($no); return; }
    if (Http::input('a') === 'problem') {
        $why = mb_substr(trim(Http::input('why')), 0, 2000);
        if ($why === '') { $checkPage($no, null, 'Napište, co je špatně.'); return; }
        if (Http::input('what') === 'zamitnout' && $ret['status'] !== 'zamitnuto') {
            Service::setStatus($ret, 'zamitnuto', $why, true, null);
            Notify::statusChanged(Service::get((int) $ret['id']), $why);
            $checkPage($no, 'Zamítnuto. Zákazník dostal e-mail s důvodem.');
        } else {
            Service::setStatus($ret, '', 'Problém se zbožím: ' . $why, false, null);
            $checkPage($no, 'Poznamenáno. Vratka čeká na vás v administraci.');
        }
        return;
    }
    $err = Service::checkOk($ret, null);
    $r = Service::get((int) $ret['id']);
    $checkPage($no, $err ? null : (!empty($r['zf_no']) && empty($r['zf_paid']) ? 'Zapsáno. Doklad se vystaví sám, až přijde doplatek.' : 'Zapsáno. Doklad se vystaví v Pohodě ' . Service::pohodaEta() . '.'), $err);
});

// ================= administrace =================

Http::get('/admin/vratky', function () {
    Auth::require();
    // tři oddělené přehledy: reklamace / vratky / výměny (smíšená žádost patří k „nejtěžší“ části)
    $TYP = "CASE WHEN EXISTS (SELECT 1 FROM return_lines l WHERE l.return_id = r.id AND l.action = 'reklamovat') THEN 'reklamace'"
        . " WHEN EXISTS (SELECT 1 FROM return_lines l WHERE l.return_id = r.id AND l.action = 'vymenit') THEN 'vymena' ELSE 'vratka' END";
    $typ = Http::input('typ');
    if (!isset(Service::TYPY[$typ])) $typ = (string) Session::get('vratky_typ', 'reklamace');
    if (!isset(Service::TYPY[$typ])) $typ = 'reklamace';
    Session::set('vratky_typ', $typ);
    $stav = Http::input('stav');
    $q = Http::input('q');
    $FILTR = [
        'proplatit' => "r.doc_type = 'odd' AND r.doc_state = 'hotovo' AND r.status NOT IN ('vyrizeno','zamitnuto')",
        'doplatek'  => "r.zf_no IS NOT NULL AND r.zf_no <> '' AND r.zf_paid = 0 AND r.status NOT IN ('vyrizeno','zamitnuto')",
        ''          => "r.status NOT IN ('vyrizeno','zamitnuto')",
    ];
    $where = [];
    $p = [];
    if ($q === '') { $where[] = "($TYP) = ?"; $p[] = $typ; }    // hledá se napříč všemi typy a stavy
    if ($q === '' || $stav !== '') {
        if (isset($FILTR[$stav])) $where[] = $FILTR[$stav];
        elseif (isset(Service::STATUS[$stav])) { $where[] = 'r.status = ?'; $p[] = $stav; }
    }
    if ($q !== '') { $where[] = '(r.no LIKE ? OR r.pohoda_no LIKE ? OR r.email LIKE ? OR r.customer_name LIKE ? OR o.order_no LIKE ? OR o.invoice_no LIKE ?)'; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%", "%$q%", "%$q%"); }
    $rows = Db::all("SELECT r.*, o.order_no, o.invoice_no, ($TYP) AS typ FROM returns r LEFT JOIN orders o ON o.id = r.order_id" .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY r.id DESC LIMIT 200', $p);
    foreach ($rows as &$r) $r['lines'] = Service::lines((int) $r['id']);
    unset($r);
    // počty: po typech a stavech + zvláštní dlaždice
    $counts = [];
    foreach (array_keys(Service::TYPY) as $t) $counts[$t] = ['' => 0, 'proplatit' => 0, 'doplatek' => 0];
    foreach (Db::all("SELECT ($TYP) t, r.status, COUNT(*) c FROM returns r GROUP BY t, r.status") as $c) {
        $counts[$c['t']][$c['status']] = (int) $c['c'];
        if (!in_array($c['status'], ['vyrizeno', 'zamitnuto'], true)) $counts[$c['t']][''] += (int) $c['c'];
    }
    foreach (['proplatit', 'doplatek'] as $f) {
        foreach (Db::all("SELECT ($TYP) t, COUNT(*) c FROM returns r WHERE {$FILTR[$f]} GROUP BY t") as $c) $counts[$c['t']][$f] = (int) $c['c'];
    }
    $lastSync = Db::one('SELECT * FROM sync_log ORDER BY id DESC LIMIT 1');
    View::page('vratky:admin_list', ['title' => Service::TYPY[$typ], 'active' => 'vratky', 'rows' => $rows, 'counts' => $counts,
        'typ' => $typ, 'stav' => $stav, 'q' => $q, 'lastSync' => $lastSync], 'admin');
});

// hromadně: peníze odeslány (po zadání příkazů v bance) → Vyřízeno + e-mail zákazníkovi
Http::post('/admin/vratky/odeslano', function () {
    $u = Auth::require();
    Session::checkCsrf();
    $n = 0;
    foreach ((array) ($_POST['ids'] ?? []) as $id) {
        $r = Service::get((int) $id);
        if (!$r || $r['doc_type'] !== 'odd' || $r['doc_state'] !== 'hotovo' || in_array($r['status'], ['vyrizeno', 'zamitnuto'], true)) continue;
        $note = 'Peníze ' . money(Service::payout($r)) . ' jsme poslali na účet ' . $r['bank_account'] . '. Na účtu by měly být do 1–2 pracovních dnů.';
        Service::setStatus($r, 'vyrizeno', $note, true, (int) $u['id']);
        Notify::statusChanged(Service::get((int) $r['id']), $note);
        $n++;
    }
    Session::flash($n ? 'Hotovo: ' . $n . ' × Vyřízeno, zákazníci dostali e-mail.' : 'Nic jste neoznačili.', $n ? 'ok' : 'err');
    Http::redirect('/admin/vratky?stav=proplatit');
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
    View::page('vratky:admin_detail', ['title' => Service::words($lines)['N'] . ' ' . $ret['no'], 'active' => 'vratky', 'ret' => $ret,
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
    if ($act === 'doklad') {
        $err = Service::checkOk($ret, (int) $u['id'], (array) ($_POST['new_code'] ?? []));
        $r = Service::get((int) $ret['id']);
        Session::flash($err ?? (!empty($r['zf_no']) && empty($r['zf_paid']) ? 'Zapsáno – doklad se vystaví sám, až přijde doplatek.' : 'Doklad se vystaví v Pohodě ' . Service::pohodaEta() . '.'), $err ? 'err' : 'ok');
        Http::redirect('/admin/vratky/' . $no);
    }
    if ($act === 'problem' && !in_array($ret['status'], ['vyrizeno', 'zamitnuto'], true)) {
        $why = mb_substr(trim(Http::input('why')), 0, 2000);
        if ($why === '') { Session::flash('Napište, co je špatně.', 'err'); Http::redirect('/admin/vratky/' . $no); }
        if (Http::input('what') === 'zamitnout') {
            Service::setStatus($ret, 'zamitnuto', $why, true, (int) $u['id']);
            if ($ret['email'] !== '') Notify::statusChanged(Service::get((int) $ret['id']), $why);
            Session::flash($ret['email'] !== '' ? 'Zamítnuto. Zákazník dostal e-mail s důvodem.' : 'Zamítnuto. Zákazník nemá e-mail – zavolejte mu.');
        } else {
            Service::setStatus($ret, '', 'Problém se zbožím: ' . $why, false, (int) $u['id']);
            Session::flash('Poznamenáno. Až se se zákazníkem domluvíte, potvrďte zboží nebo vratku zamítněte.');
        }
        Http::redirect('/admin/vratky/' . $no);
    }
    if ($act === 'postovne' && ($ret['doc_state'] ?? '') !== 'hotovo' && !in_array($ret['status'], ['vyrizeno', 'zamitnuto'], true)) {
        $on = !$ret['postage_applies'];
        $p = Config::get('vratky.return_postage');
        Db::exec('UPDATE returns SET postage_applies = ?, postage = ?, updated_at = ? WHERE id = ?', [$on ? 1 : 0, $on && $p !== null ? (float) $p : null, Db::now(), $ret['id']]);
        Service::setStatus($ret, '', $on ? 'Poštovné za vrácení se odečte.' : 'Poštovné za vrácení se neodečítá.', false, (int) $u['id']);
        Session::flash($on ? 'Poštovné se odečte.' : 'Poštovné se neodečítá – vrací se celá částka.');
        Http::redirect('/admin/vratky/' . $no);
    }
    if ($act === 'smazat_test') {
        if (trim(Http::input('potvrzeni')) !== $ret['no']) {
            Session::flash('Pro smazání opište číslo ' . $ret['no'] . '.', 'err');
            Http::redirect('/admin/vratky/' . $no);
        }
        $r = Service::deleteTest($ret, (int) $u['id']);
        if ($r['error']) { Session::flash($r['error'], 'err'); Http::redirect('/admin/vratky/' . $no); }
        Session::flash($ret['no'] . ' je smazaná' . ($r['pohoda'] ? '. V Pohodě ještě smažte: ' . implode(', ', $r['pohoda']) . '.' : '.'));
        Http::redirect('/admin/vratky');
    }
    if ($act === 'doklad_znovu' && ($ret['doc_state'] ?? '') === 'chyba') {
        Db::exec("UPDATE returns SET doc_state = '', doc_error = NULL WHERE id = ?", [$ret['id']]);
        $err = Service::requestDoc(Service::get((int) $ret['id']), (array) ($_POST['new_code'] ?? []), (int) $u['id']);
        Session::flash($err ?? 'Zkusí se to znovu ' . Service::pohodaEta() . '.', $err ? 'err' : 'ok');
        Http::redirect('/admin/vratky/' . $no);
    }
    $status = Http::input('status');
    if ($status !== '' && !isset(Service::STATUS[$status])) $status = '';
    $note = mb_substr(Http::input('note'), 0, 3000);
    $notify = !empty($_POST['notify']);
    if ($status === 'zamitnuto' && $status !== $ret['status'] && $note === '') {
        Session::flash('Napište důvod zamítnutí – zákazník ho dostane v e-mailu.', 'err');
        Http::redirect('/admin/vratky/' . $no);
    }
    if (($status === '' || $status === $ret['status']) && $note === '') {
        // stejný stav bez poznámky = nic nového (i opakované kliknutí na Uložit) → žádný další e-mail
        Session::flash('Nic se nezměnilo – stav už je „' . (Service::STATUS[$ret['status']] ?? $ret['status']) . '“. E-mail se znovu neposlal.', 'err');
        Http::redirect('/admin/vratky/' . $no);
    }
    Service::setStatus($ret, $status === $ret['status'] ? '' : $status, $note, $notify, (int) $u['id']);
    if ($notify && $ret['email'] !== '') {
        Notify::statusChanged(Service::get((int) $ret['id']), $note);
        Session::flash('Uloženo a zákazník dostal e-mail.');
    } else {
        Session::flash('Uloženo.');
    }
    Http::redirect('/admin/vratky/' . $no);
});


// faktura / potvrzení objednávky v PDF z účtu na e-shopu: /faktura?c=…&email=…, /objednavka-pdf?c=…&email=…
// c = číslo faktury nebo objednávky, email = e-mail z objednávky. PDF tiskne z Pohody pomocník (faktury-pdf.ps1).
$pdfDoklad = function (string $kind) {
    $limit = RateLimit::hit('doklad_pdf', 60, 3600);
    if ($kind === 'zalohy') {
        $no = $limit ? (string) Invoices::findAdvance(Http::input('c'), Http::input('email')) : '';
    } else {
        $o = $limit ? Invoices::find(Http::input('c'), Http::input('email')) : null;
        $no = $o ? (string) ($kind === 'objednavky' ? $o['order_no'] : $o['invoice_no']) : '';
        // čerstvá objednávka bez faktury: portál ji zná jen z PDF, které poslal pomocník (s e-mailem odběratele)
        if ($kind === 'objednavky' && $no === '' && $limit) $no = (string) Invoices::findOrderSidecar(Http::input('c'), Http::input('email'));
        // daňový doklad k objednávce: faktura, nebo prodejka (objednávka vyzvednutá a zaplacená na prodejně)
        if ($kind === 'faktury' && $no !== '') $kind = Invoices::kindOf($no);
    }
    $co = ['objednavky' => 'Objednávku', 'zalohy' => 'Zálohovou fakturu', 'prodejky' => 'Prodejku'][$kind] ?? 'Fakturu';
    if ($no === '') {
        http_response_code(404);
        View::page('layout/message', ['title' => $co . ' jsme nenašli',
            'text' => 'Zkontrolujte prosím, že jste přihlášení na e-shopu pod e-mailem z objednávky, nebo nám zavolejte na ' . Config::get('shop.phone') . '.']);
        return;
    }
    if (!Invoices::has($no, $kind) && Invoices::skipped($no, $kind)) {
        View::page('layout/message', ['title' => 'Doklad není k dispozici online',
            'text' => 'Tento doklad online nenabízíme. Rádi vám ho pošleme – zavolejte nám na ' . Config::get('shop.phone') . '.']);
        return;
    }
    if (!Invoices::has($no, $kind)) {
        View::page('layout/message', ['title' => $co . ' připravujeme',
            'text' => (['objednavky' => 'Potvrzení objednávky č. ', 'zalohy' => 'Zálohovou fakturu č. ', 'prodejky' => 'Prodejku č. '][$kind] ?? 'Fakturu č. ') . $no . ' právě připravujeme ve formátu PDF. Zkuste to prosím za pár minut znovu.']);
        return;
    }
    $file = Invoices::path($no, $kind);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (Http::input('stahnout') === '1' ? 'attachment' : 'inline') . '; filename="' . (['objednavky' => 'Objednavka-', 'zalohy' => 'Zalohova-faktura-', 'prodejky' => 'Prodejka-'][$kind] ?? 'Faktura-') . $no . '.pdf"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex');
    readfile($file);
    exit;
};
Http::get('/faktura', fn() => $pdfDoklad('faktury'));
Http::get('/objednavka-pdf', fn() => $pdfDoklad('objednavky'));
Http::get('/zaloha-pdf', fn() => $pdfDoklad('zalohy'));

// ================= API pro pomocníka (Pohoda) =================

$syncAuth = function (): array {
    $token = (string) Config::get('sync_token', '');
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $given = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : (string) ($_SERVER['HTTP_X_SYNC_TOKEN'] ?? '');
    if ($token === '' || !hash_equals($token, trim($given))) Http::json(['ok' => false, 'error' => 'unauthorized'], 401);
    if (Http::method() !== 'POST') return [];
    $raw = (string) file_get_contents('php://input');
    if (strlen($raw) > 20 * 1024 * 1024) Http::json(['ok' => false, 'error' => 'too large'], 413);
    $data = json_decode($raw, true);
    if (!is_array($data)) Http::json(['ok' => false, 'error' => 'invalid json'], 400);
    return $data;
};

Http::post('/api/sync/objednavky', function () use ($syncAuth) {
    $data = $syncAuth();
    Http::json(['ok' => true] + Sync::import($data));
});

// reklamace z Pohody (agenda Reklamace): {"initial":false,"claims":[{"number":"26RZ00047","status":"Servis","received":"2026-11-20",…}]}
Http::post('/api/sync/reklamace', function () use ($syncAuth) {
    $d = $syncAuth();
    $res = Service::importPohodaClaims((array) ($d['claims'] ?? []), !empty($d['initial']));
    $mails = 0;
    foreach ($res as $x) {
        if ($x['kind'] === 'created') Notify::created($x['ret'], true); else Notify::statusChanged($x['ret'], $x['text']);
        $mails++;
    }
    Http::json(['ok' => true, 'claims' => count((array) ($d['claims'] ?? [])), 'notified' => $mails]);
});

// doklady k vratkám: GET = co vystavit (?navrhy=1 i návrhy čekající na schválení), POST {"results":[{"no":"V-2026-0001","state":"hotovo","doc_no":"260400261"}]}
Http::get('/api/sync/doklady', function () use ($syncAuth) {
    $syncAuth();
    Http::json(['ok' => true, 'returns' => Service::docPending(Http::input('navrhy') === '1')]);
});

// výměny (/api/sync/vymeny): GET = objednávky 991… k založení (?navrhy=1 i návrhy) a zálohy k hlídání platby, POST {"results":[{"no":"V-…","state":"hotovo","zf_no":"260800110"}]}
Http::get('/api/sync/vymeny', function () use ($syncAuth) {
    $syncAuth();
    $rem = Service::cron();
    Http::json(['ok' => true, 'returns' => Service::xorderPending(Http::input('navrhy') === '1'), 'reminders' => $rem]);
});

Http::post('/api/sync/vymeny', function () use ($syncAuth) {
    $d = $syncAuth();
    $n = 0;
    foreach ((array) ($d['results'] ?? []) as $x) if (is_array($x) && Service::xorderResult($x)) $n++;
    Http::json(['ok' => true, 'updated' => $n]);
});

Http::post('/api/sync/doklady', function () use ($syncAuth) {
    $d = $syncAuth();
    $n = 0;
    foreach ((array) ($d['results'] ?? []) as $x) if (is_array($x) && Service::docResult($x)) $n++;
    Http::json(['ok' => true, 'updated' => $n]);
});

// doklady v PDF: GET = čísla, ke kterým chybí PDF (?limit=…), POST {"pdfs":[{"no":"260102162","pdf":"<base64>"}]}
// zálohy jen POST {"pdfs":[{"no":"260800112","pdf":"…","email":"…","order_no":"8810…"}]} – co tisknout, hlídá pomocník sám
foreach (['faktury', 'objednavky', 'zalohy', 'prodejky'] as $kind) {
    Http::get('/api/sync/' . $kind . '-pdf', function () use ($syncAuth, $kind) {
        $syncAuth();
        Http::json(['ok' => true, 'invoices' => Invoices::missing(max(1, min(500, (int) (Http::input('limit') ?: 40))), $kind)]);
    });
    Http::post('/api/sync/' . $kind . '-pdf', function () use ($syncAuth, $kind) {
        $d = $syncAuth();
        $ok = 0; $err = [];
        foreach ((array) ($d['pdfs'] ?? []) as $x) {
            if (!is_array($x)) continue;
            $no = (string) ($x['no'] ?? $x['invoice_no'] ?? '');
            $e = !empty($x['skip']) ? Invoices::skip($no, $kind) : Invoices::save($no, (string) ($x['pdf'] ?? ''), $kind, $x);
            if ($e) $err[] = $no . ': ' . $e; else $ok++;
        }
        Http::json(['ok' => true, 'saved' => $ok, 'errors' => $err]);
    });
}
