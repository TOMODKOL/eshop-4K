<?php
declare(strict_types=1);

namespace App\Modules\Vratky;

use App\Core\Db;

/** Fotky vady u reklamací. Ukládají se mimo veřejnou část (storage/uploads). */
final class Photos
{
    public const MAX_FILES = 6;
    public const MAX_BYTES = 12 * 1024 * 1024;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heic'];

    /** Z $_FILES['photos'] vybere soubory pro danou položku. Vrací [seznam, chyby]. */
    public static function fromUpload(?array $f, int $itemId): array
    {
        if (!$f || !isset($f['name'][$itemId])) return [[], []];
        $out = [];
        $err = [];
        $names = (array) $f['name'][$itemId];
        foreach ($names as $k => $name) {
            $code = (int) ($f['error'][$itemId][$k] ?? UPLOAD_ERR_NO_FILE);
            if ($code === UPLOAD_ERR_NO_FILE) continue;
            if ($code !== UPLOAD_ERR_OK) { $err[] = 'Fotku „' . $name . '“ se nepodařilo nahrát (je možná příliš velká).'; continue; }
            if (count($out) >= self::MAX_FILES) { $err[] = 'K jedné položce lze přidat nejvýš ' . self::MAX_FILES . ' fotek.'; break; }
            $tmp = (string) $f['tmp_name'][$itemId][$k];
            $size = (int) $f['size'][$itemId][$k];
            if ($size > self::MAX_BYTES) { $err[] = 'Fotka „' . $name . '“ je větší než 12 MB.'; continue; }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
            if (!isset(self::TYPES[$mime])) { $err[] = 'Soubor „' . $name . '“ není fotka (JPG, PNG, WEBP nebo HEIC).'; continue; }
            $out[] = ['tmp' => $tmp, 'mime' => $mime, 'size' => $size];
        }
        return [$out, $err];
    }

    public static function attach(int $lineId, array $p): void
    {
        $dir = APP_ROOT . '/storage/uploads/' . date('Y/m');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = bin2hex(random_bytes(12)) . '.' . self::TYPES[$p['mime']];
        $dest = $dir . '/' . $name;
        $ok = is_uploaded_file($p['tmp']) ? move_uploaded_file($p['tmp'], $dest) : copy($p['tmp'], $dest);
        if (!$ok) throw new \RuntimeException('Fotku se nepodařilo uložit.');
        Db::insert('return_photos', ['line_id' => $lineId, 'file' => date('Y/m') . '/' . $name, 'mime' => $p['mime'], 'size' => $p['size'], 'created_at' => Db::now()]);
    }

    public static function forLine(int $lineId): array
    {
        return Db::all('SELECT * FROM return_photos WHERE line_id = ? ORDER BY id', [$lineId]);
    }

    public static function output(int $id): void
    {
        $p = Db::one('SELECT * FROM return_photos WHERE id = ?', [$id]);
        $path = $p ? APP_ROOT . '/storage/uploads/' . $p['file'] : '';
        if (!$p || !is_file($path) || str_contains($p['file'], '..')) { http_response_code(404); exit; }
        header('Content-Type: ' . $p['mime']);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }
}
