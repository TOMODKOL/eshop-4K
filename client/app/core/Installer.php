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

    /** Doplnění sloupců do už nainstalované databáze (na Forpsi). Značka v storage/ zajistí, že se to spustí jen jednou. */
    public const SCHEMA = 11;
    public static function migrate(): void
    {
        $mark = APP_ROOT . '/storage/schema_v' . self::SCHEMA;
        if (is_file($mark) || !self::installed()) return;
        $cols = [
            "ALTER TABLE returns ADD COLUMN pohoda_state VARCHAR(20) NOT NULL DEFAULT ''",
            'ALTER TABLE returns ADD COLUMN pohoda_no VARCHAR(120) NULL',
            'ALTER TABLE return_lines ADD COLUMN pohoda_no VARCHAR(20) NULL',
            "ALTER TABLE return_lines ADD COLUMN pohoda_status VARCHAR(60) NOT NULL DEFAULT ''",
            "ALTER TABLE returns ADD COLUMN pohoda_status VARCHAR(60) NOT NULL DEFAULT ''",
            'ALTER TABLE returns ADD COLUMN pohoda_error TEXT NULL',
            'ALTER TABLE returns ADD COLUMN notify_pending TINYINT(1) NOT NULL DEFAULT 0',
            // v3: doklady k vratkám v Pohodě (opravný daňový doklad / faktura), vrácení dopravy
            'ALTER TABLE returns ADD COLUMN ship_refund DECIMAL(12,2) NOT NULL DEFAULT 0',
            "ALTER TABLE returns ADD COLUMN doc_state VARCHAR(20) NOT NULL DEFAULT ''",
            "ALTER TABLE returns ADD COLUMN doc_type VARCHAR(20) NOT NULL DEFAULT ''",
            'ALTER TABLE returns ADD COLUMN doc_no VARCHAR(30) NULL',
            'ALTER TABLE returns ADD COLUMN doc_error TEXT NULL',
            "ALTER TABLE return_lines ADD COLUMN new_code VARCHAR(60) NOT NULL DEFAULT ''",
            // v4: doprava, když zbytek objednávky po vrácení klesne pod hranici dopravy zdarma
            'ALTER TABLE returns ADD COLUMN ship_charge DECIMAL(12,2) NOT NULL DEFAULT 0',
            // v5: ID zásoby z Pohody → výběr velikosti stejný jako na e-shopu
            'ALTER TABLE order_items ADD COLUMN stock_id INT UNSIGNED NULL',
            // v6: výměna za jiné zboží (rozdíl v ceně, doplatek převodem / na dobírku)
            'ALTER TABLE returns ADD COLUMN swap_diff DECIMAL(12,2) NOT NULL DEFAULT 0',
            "ALTER TABLE returns ADD COLUMN pay_method VARCHAR(20) NOT NULL DEFAULT ''",
            'ALTER TABLE returns ADD COLUMN cod_fee DECIMAL(12,2) NOT NULL DEFAULT 0',
            "ALTER TABLE return_lines ADD COLUMN new_name VARCHAR(255) NOT NULL DEFAULT ''",
            'ALTER TABLE return_lines ADD COLUMN new_price DECIMAL(12,2) NULL',
            "ALTER TABLE return_lines ADD COLUMN new_url VARCHAR(500) NOT NULL DEFAULT ''",
            // v7: počet kusů nového zboží (0 = stejně jako vracené)
            'ALTER TABLE return_lines ADD COLUMN new_qty INT NOT NULL DEFAULT 0',
            // v8: číslo balíku z poznámky faktury a datum převzetí (lhůta 14 dní od převzetí)
            "ALTER TABLE orders ADD COLUMN parcel_carrier VARCHAR(10) NOT NULL DEFAULT ''",
            "ALTER TABLE orders ADD COLUMN parcel_no VARCHAR(40) NOT NULL DEFAULT ''",
            'ALTER TABLE orders ADD COLUMN delivered_date DATE NULL',
            'ALTER TABLE orders ADD COLUMN tracking_checked_at DATETIME NULL',
            // v9: výměna → přijatá objednávka 991… v Pohodě (číslo dává Client), doplatek převodem → zálohová faktura 2608…
            'ALTER TABLE returns ADD COLUMN xorder_no VARCHAR(20) NULL',
            "ALTER TABLE returns ADD COLUMN xorder_state VARCHAR(10) NOT NULL DEFAULT ''",
            'ALTER TABLE returns ADD COLUMN xorder_error TEXT NULL',
            'ALTER TABLE returns ADD COLUMN zf_no VARCHAR(20) NULL',
            'ALTER TABLE returns ADD COLUMN zf_paid TINYINT(1) NOT NULL DEFAULT 0',
            // v10: připomínky – zboží neodesláno (7 dní zákazníkovi, 14 dní prodejně), doplatek po splatnosti (3 dny)
            'ALTER TABLE returns ADD COLUMN zf_at DATETIME NULL',
            'ALTER TABLE returns ADD COLUMN reminded_at DATETIME NULL',
            'ALTER TABLE returns ADD COLUMN stale_alerted_at DATETIME NULL',
            'ALTER TABLE returns ADD COLUMN pay_reminded_at DATETIME NULL',
            // v11: automatizace – sledování zpětné zásilky Zásilkovny, opakované vytvoření kódu, kontrola zboží jedním klikem
            'ALTER TABLE returns ADD COLUMN packeta_checked_at DATETIME NULL',
            'ALTER TABLE returns ADD COLUMN packeta_tries INT NOT NULL DEFAULT 0',
            'ALTER TABLE returns ADD COLUMN checked_at DATETIME NULL',
            'ALTER TABLE returns ADD COLUMN check_mail_at DATETIME NULL',
        ];
        foreach ($cols as $sql) {
            try { Db::exec($sql); } catch (\Throwable $e) {
                if (!preg_match('/duplicate|already exists/i', $e->getMessage())) throw $e;
            }
        }
        @file_put_contents($mark, date('c'));
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
