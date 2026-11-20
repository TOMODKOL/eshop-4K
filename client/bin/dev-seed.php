<?php
// Ukázková data pro zkoušení (NE na ostrém serveru):  php bin/dev-seed.php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/core/helpers.php';
App\Core\Config::load();
App\Core\Installer::run();
$d = fn(int $days) => date('Y-m-d', strtotime("-$days days"));
$res = App\Modules\Vratky\Sync::import(['orders' => [
  ['invoice_no' => '260109001', 'order_no' => '8810000123', 'invoice_date' => $d(6), 'email' => 'jana.novakova@email.cz',
   'customer_name' => 'Jana Nováková (ukázka)', 'phone' => '+420 777 000 111', 'total' => 9820,
   'items' => [
     ['code' => 'POC-CAD-UB-M', 'name' => "dámský dres POC W's Cadence Jersey Uranium Black M", 'qty' => 1, 'unit_price' => 1990],
     ['code' => 'POC-VEN-UBM-M', 'name' => 'helma POC Ventral Air MIPS Uranium Black Matt M', 'qty' => 1, 'unit_price' => 4690],
     ['code' => 'CAS-PROM-AB-M', 'name' => 'dámský dres Castelli Promessa Jacquard Agate Blue M', 'qty' => 2, 'unit_price' => 1570],
     ['code' => '', 'name' => 'Doprava Zásilkovna', 'qty' => 1, 'unit_price' => 0],
   ]],
  ['invoice_no' => '260105555', 'order_no' => '8810000087', 'invoice_date' => $d(80), 'email' => 'jana.novakova@email.cz',
   'customer_name' => 'Jana Nováková (ukázka)', 'phone' => '', 'total' => 3290,
   'items' => [['code' => 'LAKE-CX239-42', 'name' => 'tretry LAKE CX239 Black/Silver REGULAR 42', 'qty' => 1, 'unit_price' => 3290]]],
]]);
print_r($res);
