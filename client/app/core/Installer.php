<?php
declare(strict_types=1);

namespace App\Core;

/** Vytvoření tabulek. MySQL schéma, pro SQLite (zkoušení na počítači) se převede. */
final class Installer
{
    public static function schema(string $driver): string
    {
        $sql = (string) file_get_contents(APP_ROOT . '/sql/mysql.sql');
        if ($driver !== 'sqlite') return $sql;
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/\) ENGINE=InnoDB[^;]*;/', ');', $sql);
        $sql = preg_replace('/,\s*KEY \w+ \([^)]*\)/', '', $sql);
        $sql = preg_replace('/CONSTRAINT \w+ FOREIGN KEY/', 'FOREIGN KEY', $sql);
        return str_replace(['INT UNSIGNED', 'TINYINT(1)'], ['INTEGER', 'INTEGER'], $sql);
    }

    public static function run(): void
    {
        $pdo = Db::pdo();
        $pdo->exec(self::schema((string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)));
    }

    public static function installed(): bool
    {
        try { Db::value('SELECT COUNT(*) FROM users'); return true; } catch (\Throwable) { return false; }
    }

    public static function hasUsers(): bool
    {
        return self::installed() && (int) Db::value('SELECT COUNT(*) FROM users') > 0;
    }
}
