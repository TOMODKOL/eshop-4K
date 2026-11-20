<?php
// Založí kolegu do administrace (heslo zadáte při spuštění, nikam se neukládá v čitelné podobě).
//   php bin/user.php "Tomáš Pavlíček" tomas@4kluciodkol.cz admin
//   php bin/user.php "Kuba" kuba@4kluciodkol.cz
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require APP_ROOT . '/app/core/helpers.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Db;

Config::load();
[$_, $name, $email] = array_pad($argv, 3, null);
$role = $argv[3] ?? 'staff';
if (!$name || !$email) { fwrite(STDERR, "Použití: php bin/user.php \"Jméno\" email [admin|staff]\n"); exit(1); }
if (Db::one('SELECT id FROM users WHERE email = ?', [mb_strtolower($email)])) { fwrite(STDERR, "Uživatel už existuje.\n"); exit(1); }
echo 'Heslo (min. 10 znaků): ';
system('stty -echo 2>/dev/null');
$pass = trim((string) fgets(STDIN));
system('stty echo 2>/dev/null');
echo "\n";
if (mb_strlen($pass) < 10) { fwrite(STDERR, "Heslo je moc krátké.\n"); exit(1); }
Auth::createUser($name, $email, $pass, $role === 'admin' ? 'admin' : 'staff');
echo "Hotovo: $name ($email), role $role\n";
