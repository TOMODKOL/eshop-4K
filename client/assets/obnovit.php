<?php
// Po nasazení: vyprázdní mezipaměť PHP (OPcache) na hostingu, aby se hned použily nové soubory.
// Volá ho nasad-client.ps1: /assets/obnovit.php?k=<HMAC-SHA256("obnovit-cache", app_key)>. Nic jiného neumí.
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$cfg = require dirname(__DIR__) . '/config/config.php';
$k = (string) ($_GET['k'] ?? '');
if (empty($cfg['app_key']) || !hash_equals(hash_hmac('sha256', 'obnovit-cache', (string) $cfg['app_key']), $k)) {
    http_response_code(403);
    exit('{"ok":false}');
}
$reset = function_exists('opcache_reset') ? opcache_reset() : null;
echo json_encode(['ok' => true, 'opcache_reset' => $reset,
    'validate_timestamps' => ini_get('opcache.validate_timestamps'), 'revalidate_freq' => ini_get('opcache.revalidate_freq')]);
