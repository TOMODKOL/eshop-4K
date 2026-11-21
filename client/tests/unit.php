<?php
// Testy logiky bez prohlížeče:  php tests/unit.php   (používá dočasnou SQLite databázi)
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/core/helpers.php';

use App\Core\Config;
use App\Core\Installer;
use App\Modules\Vratky\Packeta;
use App\Modules\Vratky\Service;
use App\Modules\Vratky\Sync;

$db = sys_get_temp_dir() . '/client4k-test-' . getmypid() . '.sqlite';
$cfg = require APP_ROOT . '/config/config.example.php';
$cfg['db'] = ['dsn' => 'sqlite:' . $db, 'user' => null, 'pass' => null];
$cfg['app_key'] = 'test-key';
$cfg['packeta']['api_password'] = 'TAJNE';
Config::load($cfg);
Installer::run();

$fail = 0;
function t(bool $c, string $m): void { global $fail; echo ($c ? 'ok   ' : 'CHYBA') . " $m\n"; if (!$c) $fail++; }

// velikosti
t(Service::sizeOptions('dres POC M') === ['XXS', 'XS', 'S', 'L', 'XL', 'XXL', '3XL', '4XL'], 'velikosti oblečení');
t(in_array('42,5', Service::sizeOptions('tretry LAKE CX239 REGULAR 43'), true), 'velikosti bot po půl čísle');
t(Service::sizeOptions('helma POC Ventral Air MIPS M/L') === ['XS/S', 'S/M', 'L/XL', 'XL/XXL'], 'kombinované velikosti');
t(Service::sizeOptions('lahev Elite 750 ml') === [], 'bez velikosti → volné pole');

// účet
t(Service::validAccount('123456789/0800') && Service::validAccount('19-2000145399/0800') && Service::validAccount('CZ6508000000192000145399'), 'platné účty');
t(!Service::validAccount('12345') && !Service::validAccount('abc/0800'), 'neplatné účty');

// lhůty
$dl = Service::deadlines(['invoice_date' => date('Y-m-d', strtotime('-30 days'))]);
t(!$dl['can_return'] && $dl['can_claim'], 'po 30 dnech jen reklamace');

// sync + položky
$r = Sync::import(['orders' => [['invoice_no' => '1001', 'order_no' => '8810001', 'invoice_date' => date('Y-m-d'), 'email' => 'A@B.cz',
    'items' => [['name' => 'dres X M', 'qty' => 2, 'unit_price' => 1000], ['name' => 'Doprava PPL', 'qty' => 1, 'unit_price' => 99]]]]]);
t($r['saved'] === 1, 'sync uložil fakturu');
$o = Service::findOrder('a@b.cz', ' 881 0001 ');
t($o !== null, 'hledání: e-mail bez ohledu na velikost písmen, číslo s mezerami');
t(Service::findOrder('jiny@b.cz', '8810001') === null, 'cizí e-mail objednávku nenajde');
$items = Service::items((int) $o['id']);
t(count($items) === 1 && $items[0]['qty_left'] === 2, 'doprava se nenabízí, 2 kusy k dispozici');

// vratka 1 kus
$id = (int) $items[0]['id'];
[$err, $data] = Service::validate($o, $items, ['act' => [$id => 'vratit'], 'qty' => [$id => 1], 'reason' => [$id => 'Nesedí velikost'],
    'ship' => 'zbox', 'account' => '123456789/0800', 'agree' => 1], []);
t($err === [], 'validace prošla');
t($data['calc']['refund'] == 1000 && $data['calc']['postage_applies'], 'vrací se 1000 Kč, poštovné se strhne');
$ret = Service::create($o, $data);
t(preg_match('/^V-\d{4}-0001$/', $ret['no']) === 1, 'číslo vratky ' . $ret['no']);
$items = Service::items((int) $o['id']);
t($items[0]['qty_left'] === 1, 'zbývá 1 kus');
[$err] = Service::validate($o, $items, ['act' => [$id => 'vratit'], 'qty' => [$id => 2], 'reason' => [$id => 'Nesedí velikost'], 'ship' => 'zbox', 'account' => '123456789/0800', 'agree' => 1], []);
t((bool) $err, 'nejde vrátit víc kusů, než zbývá');
[$err, $d2] = Service::validate($o, $items, ['act' => [$id => 'vymenit'], 'size_to' => [$id => 'L'], 'ship' => 'osobne', 'agree' => 1], []);
t($err === [] && $d2['calc']['refund'] == 0 && !$d2['calc']['postage_applies'], 'výměna: bez účtu, bez poštovného');

// znovu poslaná faktura nepřepíše položky s vratkou
Sync::import(['orders' => [['invoice_no' => '1001', 'invoice_date' => date('Y-m-d'), 'email' => 'a@b.cz', 'items' => [['name' => 'jiné', 'qty' => 1, 'unit_price' => 5]]]]]);
t(count(Service::items((int) $o['id'])) === 1 && Service::items((int) $o['id'])[0]['name'] === 'dres X M', 'položky s vratkou zůstaly');

// odkaz e-mailem
$tok = Service::linkToken('A@B.cz');
t(Service::linkEmail($tok) === 'a@b.cz', 'odkaz platí');
t(Service::linkEmail($tok . 'x') === null && Service::linkEmail(substr($tok, 1)) === null, 'upravený odkaz neplatí');

// Zásilkovna
$xml = Packeta::requestXml(['number' => 'V-2026-0001', 'email' => 'a@b.cz', 'value' => '1000.00']);
t(str_contains($xml, '<apiPassword>TAJNE</apiPassword>') && str_contains($xml, '<number>V-2026-0001</number>'), 'požadavek Zásilkovně');
$ok = Packeta::parseResponse('<response><status>ok</status><result><id>1234567890</id><barcode>Z1234567890</barcode><barcodeText>Z 123 4567 890</barcodeText><password>123456789</password></result></response>');
t($ok['ok'] && $ok['password'] === '123456789', 'odpověď Zásilkovny – kód');
$bad = Packeta::parseResponse('<response><status>fault</status><fault>IncorrectApiPasswordFault</fault><string>Incorrect API password.</string></response>');
t(!$bad['ok'] && str_contains($bad['error'], 'Incorrect'), 'chyba Zásilkovny se zapíše');

// prodejky a ostatní faktury (reklamace zakládá prodejna v Pohodě)
$r = Sync::import(['orders' => [
    ['invoice_no' => '26PH03231', 'source' => 'prodejna', 'invoice_date' => date('Y-m-d'), 'total' => 2490,
     'items' => [['code' => 'POC-1', 'name' => 'helma POC Ventral Air MIPS M/L', 'qty' => 1, 'unit_price' => 2490]]],
    ['invoice_no' => '260102137', 'source' => 'faktura', 'invoice_date' => date('Y-m-d'), 'email' => 'x@y.cz', 'customer_name' => 'Dvořáček Miroslav', 'phone' => '+420 604 111 222',
     'items' => [['name' => 'plášť Michelin Power Cup TLR 700x32C', 'qty' => 2, 'unit_price' => 1500]]],
    ['invoice_no' => '1002', 'order_no' => '8810002', 'invoice_date' => date('Y-m-d'), 'items' => [['name' => 'x', 'qty' => 1, 'unit_price' => 1]]],
]]);
t($r['saved'] === 2 && count($r['errors']) === 1, 'prodejka bez e-mailu projde, e-shop bez e-mailu ne');
$ph = \App\Core\Db::one("SELECT * FROM orders WHERE invoice_no = '26PH03231'");
t($ph['source'] === 'prodejna' && $ph['email'] === '', 'prodejka uložena se zdrojem');
t(Service::findOrder('x@y.cz', '260102137') === null, 'veřejný formulář nenajde neeshopový doklad');

// migrace
@unlink(APP_ROOT . '/storage/schema_v' . Installer::SCHEMA);
Installer::migrate();
t(is_file(APP_ROOT . '/storage/schema_v' . Installer::SCHEMA), 'migrace proběhne i na hotové databázi (sloupce už existují)');
@unlink(APP_ROOT . '/storage/schema_v' . Installer::SCHEMA);

// reklamace zadaná jen v Pohodě (prodejna)
$pc = ['number' => '26RZ00047', 'status' => 'Přijetí reklamace', 'received' => date('Y-m-d'), 'settle_until' => date('Y-m-d', strtotime('+30 days')),
       'document_no' => '26PH03231', 'name' => 'Jan Novák', 'phone' => '604 000 000', 'email' => 'jan@novak.cz',
       'text' => 'helma POC Ventral Air MIPS M/L', 'defect' => 'prasklý skelet', 'qty' => 1];
$res = Service::importPohodaClaims([$pc]);
t(count($res) === 1 && $res[0]['kind'] === 'created', 'nová reklamace z Pohody → potvrzení zákazníkovi');
$ret = $res[0]['ret'];
t(Service::displayNo($ret) === '26RZ00047' && $ret['status'] === 'dorazilo' && $ret['order_id'] == $ph['id'], 'převzato z Pohody s číslem a dokladem nákupu');
$ln = Service::lines((int) $ret['id']);
t($ln[0]['item_code'] === 'POC-1' && $ln[0]['defect_text'] === 'prasklý skelet', 'položka dohledaná na prodejce');
t(Service::importPohodaClaims([$pc]) === [], 'stejná reklamace podruhé nic nedělá');
$ch = Service::importPohodaClaims([['number' => '26RZ00047', 'status' => 'Servis']]);
t(count($ch) === 1 && $ch[0]['ret']['status'] === 'v_reseni', 'Servis v Pohodě = posuzuje dodavatel'); echo '     ', $ch[0]['text'], "\n";
$ch = Service::importPohodaClaims([['number' => '26RZ00047', 'status' => 'Serv_oprava']]);
t($ch === [], 'Serv_… je interní, zákazník nic nedostane');
$ch = Service::importPohodaClaims([['number' => '26RZ00047', 'status' => 'Zák_oprava']]);
t(count($ch) === 1 && $ch[0]['ret']['status'] === 'vyrizeno', 'Zák_oprava = vyřízeno'); echo '     ', $ch[0]['text'], "\n";

// první načtení: bez e-mailů
$res = Service::importPohodaClaims([array_merge($pc, ['number' => '26RZ00010', 'status' => 'Zák_neuznaná', 'received' => '2026-03-01'])], true);
t($res === [] && \App\Core\Db::value("SELECT status FROM returns WHERE pohoda_no = '26RZ00010'") === 'zamitnuto', 'první načtení: stav ano, e-maily ne');

// požadavek zákazníka z webu → založen v Pohodě s číslem V-…
$fa = \App\Core\Db::one("SELECT * FROM orders WHERE invoice_no = '260102137'");
$it = Service::items((int) $fa['id']);
$web = Service::create($fa, ['lines' => [['item' => $it[0], 'action' => 'reklamovat', 'qty' => 1, 'reason' => '', 'size_to' => '', 'defect_text' => 'uvolněná patka', 'photos' => []]],
    'email' => 'x@y.cz', 'customer_name' => 'Dvořáček Miroslav', 'phone' => '604', 'ship' => 'zasilkovna', 'account' => '', 'note' => '',
    'calc' => ['refund' => 0, 'postage' => null, 'postage_applies' => false]]);
t($web['pohoda_state'] === 'cekame' && $web['status'] === 'novy', 'reklamace z webu čeká na založení v Pohodě');
$res = Service::importPohodaClaims([['number' => '26RZ00048', 'status' => 'Přijetí reklamace', 'received' => date('Y-m-d'), 'document_no' => '260102137',
    'text' => 'plášť Michelin Power Cup', 'name' => 'Dvořáček', 'email' => 'x@y.cz']]);
$w2 = Service::get((int) $web['id']);
t($w2['pohoda_state'] === 'propojeno' && $w2['pohoda_no'] === '26RZ00048' && $w2['status'] === 'dorazilo', 'propojeno podle čísla dokladu nákupu');
t(count($res) === 1 && $res[0]['kind'] === 'created' && (int) $res[0]['ret']['id'] === (int) $web['id'], 'zákazník dostane potvrzení s číslem z Pohody');
t((int) \App\Core\Db::value("SELECT COUNT(*) FROM returns WHERE pohoda_no = '26RZ00048'") === 1, 'nevznikla duplicitní reklamace');

// doklad k vratce v Pohodě
$r = Sync::import(['orders' => [['invoice_no' => '260102200', 'order_no' => '8810009000', 'invoice_date' => date('Y-m-d'), 'email' => 'd@e.cz',
    'items' => [['code' => 'D-M', 'name' => 'dres Castelli Entrata M', 'qty' => 1, 'unit_price' => 2000], ['code' => 'K-L', 'name' => 'kraťasy Castelli L', 'qty' => 1, 'unit_price' => 3000],
                ['name' => 'GLS ČR - výdejní místo', 'qty' => 1, 'unit_price' => 59], ['name' => 'Dobírka GLS ČR - výdejní místo', 'qty' => 1, 'unit_price' => 59]]]]]);
$od = Service::findOrder('d@e.cz', '8810009000');
$it = Service::items((int) $od['id']);
t(count($it) === 2, 'GLS a dobírka se nenabízí k vrácení');
$mk = fn($i, $a, $extra = []) => array_merge(['item' => $i, 'action' => $a, 'qty' => 1, 'reason' => 'Nesedí velikost', 'size_to' => '', 'defect_text' => '', 'photos' => []], $extra);
$lines = [$mk($it[0], 'vratit'), $mk($it[1], 'vratit')];
$v = Service::create($od, ['lines' => $lines, 'ship' => 'zbox', 'account' => '123456789/0800', 'note' => '', 'phone' => '',
    'calc' => Service::calc($lines, 'zbox')]);
t(Service::requestDoc($v, [], 1) !== null, 'doklad nejde, dokud zboží nedorazí');
Service::setStatus($v, 'dorazilo', '', false, 1);
t(Service::requestDoc(Service::get((int) $v['id']), [], 1) === null, 'zboží dorazilo → doklad zadán');
$v = Service::get((int) $v['id']);
$plan = Service::docPlan($v);
t($v['doc_state'] === 'ceka' && $plan['type'] === 'odd' && (float) $v['ship_refund'] === 59.0, 'celá objednávka vrácena → vrací se doprava 59 Kč, dobírka ne');
t(abs($plan['total'] - (-5000 + 79 - 59)) < 0.01, 'opravný doklad: zboží − poštovné + doprava = ' . $plan['total']);
t(Service::payout($v) == 4980.0, 'zákazník dostane 4 980 Kč');
$pend = Service::docPending();
t(count($pend) === 1 && $pend[0]['invoice_no'] === '260102200' && count($pend[0]['items']) === 4, 'pomocník dostane vratku k vystavení');
t(Service::docResult(['no' => $v['no'], 'state' => 'hotovo', 'doc_no' => '260400261']) && Service::get((int) $v['id'])['doc_no'] === '260400261', 'číslo dokladu z Pohody uloženo');
t(Service::docPending() === [], 'vystavený doklad se znovu neposílá');

// výměna → faktura za 0 Kč
$r = Sync::import(['orders' => [['invoice_no' => '260102201', 'order_no' => '8810009001', 'invoice_date' => date('Y-m-d'), 'email' => 'd@e.cz',
    'items' => [['code' => 'T-43', 'name' => 'tretry LAKE CX239 REGULAR 43', 'qty' => 1, 'unit_price' => 5990]]]]]);
$o2 = Service::findOrder('d@e.cz', '8810009001');
$i2 = Service::items((int) $o2['id']);
$l2 = [$mk($i2[0], 'vymenit', ['size_to' => '44'])];
$x = Service::create($o2, ['lines' => $l2, 'ship' => 'zbox', 'account' => '', 'note' => '', 'phone' => '', 'calc' => Service::calc($l2, 'zbox')]);
Service::setStatus($x, 'dorazilo', '', false, 1);
t(str_contains((string) Service::requestDoc(Service::get((int) $x['id']), [], 1), 'kód zásoby nové velikosti'), 'výměna chce kód nové velikosti');
$lid = (int) Service::lines((int) $x['id'])[0]['id'];
t(Service::requestDoc(Service::get((int) $x['id']), [$lid => 'T-44'], 1) === null, 'výměna s kódem zadána');
$p2 = Service::docPlan(Service::get((int) $x['id']));
t($p2['type'] === 'fv' && $p2['total'] == 0 && $p2['items'][1]['name'] === 'tretry LAKE CX239 REGULAR 44' && $p2['items'][1]['code'] === 'T-44', 'výměna = faktura za 0 Kč s novou velikostí');

// částečné vrácení pod hranici dopravy zdarma
Sync::import(['orders' => [['invoice_no' => '260102300', 'order_no' => '8810009100', 'invoice_date' => '2026-11-21', 'email' => 'f@g.cz',
    'items' => [['code' => 'A', 'name' => 'bunda A M', 'qty' => 1, 'unit_price' => 2500], ['code' => 'B', 'name' => 'čepice B', 'qty' => 1, 'unit_price' => 800],
                ['name' => 'Zásilkovna ČR', 'qty' => 1, 'unit_price' => 0]]]]]);
$o3 = Service::findOrder('f@g.cz', '8810009100');
$i3 = Service::items((int) $o3['id']);
$c1 = Service::calc([$mk($i3[1], 'vratit')], 'zbox', $o3);
t($c1['ship_charge'] == 89.0, 'vrácení čepice: zbytek 2 500 Kč < 3 000 → doprava Zásilkovna 89 Kč');
$c2 = Service::calc([$mk($i3[0], 'vratit'), $mk($i3[1], 'vratit')], 'zbox', $o3);
t($c2['ship_charge'] == 0.0, 'vrácení všeho → doprava se neúčtuje');
$c3 = Service::calc([$mk($i3[1], 'vymenit', ['size_to' => 'L'])], 'zbox', $o3);
t($c3['ship_charge'] == 0.0, 'výměna → doprava se neúčtuje');
$x3 = Service::create($o3, ['lines' => [$mk($i3[1], 'vratit')], 'ship' => 'zbox', 'account' => '123456789/0800', 'note' => '', 'phone' => '', 'calc' => $c1]);
t(Service::payout($x3) == 800 - 79 - 89, 'zákazník dostane 800 − 79 poštovné − 89 doprava = ' . Service::payout($x3));
t(Service::shippingRule('2026-11-20')['free_from'] == 2500 && Service::shippingRule('2026-11-21')['free_from'] == 3000, 'objednávka před 23. 11. podle starých podmínek');

// velikosti z e-shopu
$es = \App\Modules\Vratky\Eshop::parse((string) file_get_contents(__DIR__ . '/fixtures/eshop_variant.html'));
t($es !== null && count($es['sizes']) === 4, 'e-shop: 4 dlaždice velikostí');
t($es['sizes'][1]['value'] === '43,5' && $es['sizes'][1]['sku'] === '3023795' && $es['sizes'][1]['avail'] === 'Skladem' && $es['sizes'][1]['cls'] === 'zelena', 'e-shop: 43,5 má kód (podle obrázku) a dostupnost');
t($es['sizes'][0]['sku'] === '3023794' && $es['sizes'][2]['sku'] === '' && $es['sizes'][3]['current'], 'e-shop: kód podle názvu, 44 bez kódu, 48 = současná');
t(\App\Modules\Vratky\Eshop::parse('<html><body>nic</body></html>') === null, 'e-shop: stránka bez variant → null');
t((Service::findOrder('d@e.cz', 'FV 2601 02200')['order_no'] ?? '') === '8810009000', 'hledání podle čísla faktury (i s předponou) i objednávky');
$itE = ['id' => 77, 'name' => 'tretry X 48', 'code' => 'K', 'unit_price' => 100, 'qty_left' => 1, 'eshop' => $es];
[$e1, $d1] = Service::validate(['invoice_date' => date('Y-m-d')] + ['id' => 0], [$itE], ['act' => [77 => 'vymenit'], 'size_to' => [77 => '43,5'], 'ship' => 'zbox', 'phone' => '', 'bank_account' => '', 'name' => 'A'], []);
t(($d1['lines'][0]['new_code'] ?? '') === '3023795', 'výměna: kód nové velikosti z e-shopu');
[$e2] = Service::validate(['invoice_date' => date('Y-m-d')] + ['id' => 0], [$itE], ['act' => [77 => 'vymenit'], 'size_to' => [77 => '48'], 'ship' => 'zbox', 'phone' => '', 'bank_account' => '', 'name' => 'A'], []);
t((bool) array_filter($e2, fn($x) => str_contains($x, 'stejnou velikost')), 'výměna: stejná velikost se odmítne');

@unlink($db);
echo $fail ? "\n$fail CHYB\n" : "\nVše v pořádku.\n";
exit($fail ? 1 : 0);
