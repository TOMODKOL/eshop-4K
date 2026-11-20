<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Odesílání e-mailů přes SMTP (Forpsi). Bez externích knihoven.
 * Když je mail.enabled = false, e-mail se jen zapíše do storage/log/mail.log.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, ?string $text = null): bool
    {
        $text ??= self::htmlToText($html);
        $from = (string) Config::get('mail.from');
        $fromName = (string) Config::get('mail.from_name', '');
        $replyTo = (string) Config::get('mail.reply_to', '');
        $boundary = 'b' . bin2hex(random_bytes(8));
        $msgId = '<' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>';

        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::addr($from, $fromName),
            'To: ' . $to,
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: ' . $msgId,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if ($replyTo) $headers[] = 'Reply-To: ' . $replyTo;

        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($text)) .
            "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($html)) .
            "--$boundary--\r\n";
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        if (!Config::get('mail.enabled')) {
            self::log("NEODESLÁNO (mail.enabled=false) → $to | $subject\n" . $text);
            return true;
        }
        try {
            self::smtp($from, $to, $data);
            self::log("odesláno → $to | $subject");
            return true;
        } catch (\Throwable $e) {
            self::log("CHYBA → $to | $subject | " . $e->getMessage());
            return false;
        }
    }

    private static function smtp(string $from, string $to, string $data): void
    {
        $host = (string) Config::get('mail.smtp_host');
        $port = (int) Config::get('mail.smtp_port', 465);
        $secure = (string) Config::get('mail.smtp_secure', 'ssl');
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 20);
        if (!$fp) throw new \RuntimeException("SMTP spojení selhalo: $errstr");
        stream_set_timeout($fp, 20);
        $read = function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 515)) !== false) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $out;
        };
        $cmd = function (string $c, int $expect) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if ((int) substr($r, 0, 3) !== $expect) throw new \RuntimeException('SMTP: ' . trim($r));
            return $r;
        };
        $r = $read();
        if ((int) substr($r, 0, 3) !== 220) throw new \RuntimeException('SMTP: ' . trim($r));
        $ehlo = 'EHLO ' . (parse_url((string) Config::get('app_url'), PHP_URL_HOST) ?: 'localhost');
        $cmd($ehlo, 250);
        if ($secure === 'tls') {
            $cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new \RuntimeException('STARTTLS selhal');
            $cmd($ehlo, 250);
        }
        $cmd('AUTH LOGIN', 334);
        $cmd(base64_encode((string) Config::get('mail.smtp_user')), 334);
        $cmd(base64_encode((string) Config::get('mail.smtp_pass')), 235);
        $cmd('MAIL FROM:<' . $from . '>', 250);
        $cmd('RCPT TO:<' . $to . '>', 250);
        $cmd('DATA', 354);
        // tečka na začátku řádku se zdvojí (RFC 5321)
        $data = preg_replace('/^\./m', '..', $data);
        $cmd($data . "\r\n.", 250);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
    }

    private static function addr(string $email, string $name): string
    {
        return $name ? self::encodeHeader($name) . ' <' . $email . '>' : $email;
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    public static function htmlToText(string $html): string
    {
        $t = preg_replace('#<(br|/p|/div|/h\d|/tr|/li)[^>]*>#i', "\n", $html);
        $t = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $t);
        $t = html_entity_decode(strip_tags((string) $t), ENT_QUOTES, 'UTF-8');
        $t = preg_replace("/[ \t]+/", ' ', $t);
        return trim((string) preg_replace("/\n\s*\n+/", "\n\n", (string) $t));
    }

    private static function log(string $line): void
    {
        $dir = APP_ROOT . '/storage/log';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        file_put_contents($dir . '/mail.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
