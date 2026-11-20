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

// prodejky a ostatní faktury (reklamace zakládá prodejna)
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
t(count(Service::searchOrders('Ventral')) === 1 && count(Service::searchOrders('26PH0323')) === 1, 'hledání podle zboží a čísla prodejky');
t(count(Service::searchOrders('604111222')) === 1 && count(Service::searchOrders('dvořáček')) >= 1, 'hledání podle telefonu a jména');
$items = Service::items((int) $ph['id']);
[$err, $data] = Service::validateAdmin($ph, $items, ['act' => [$items[0]['id'] => 'vratit']], []);
t((bool) $err, 'prodejna nemůže založit vrácení, jen reklamaci');
[$err, $data] = Service::validateAdmin($ph, $items, ['act' => [$items[0]['id'] => 'reklamovat'], 'qty' => [$items[0]['id'] => 1],
    'defect' => [$items[0]['id'] => 'prasklý skelet'], 'customer_name' => 'Jan Novák', 'phone' => '604 000 000'], []);
t($err === [], 'reklamace z prodejny bez e-mailu projde');
$ret = Service::create($ph, $data, ['id' => null]);
t($ret['email'] === '' && $ret['customer_name'] === 'Jan Novák' && $ret['ship_method'] === 'osobne' && $ret['status'] === 'dorazilo' && $ret['claim_deadline'] !== null, 'reklamace uložena s kontaktem z prodejny');
\App\Modules\Vratky\Notify::created($ret, true);
\App\Modules\Vratky\Notify::statusChanged($ret, 'x');
t(true, 'bez e-mailu se nic neposílá a nespadne to');

@unlink($db);
echo $fail ? "\n$fail CHYB\n" : "\nVše v pořádku.\n";
exit($fail ? 1 : 0);
