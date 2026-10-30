<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pengirim email.
 *
 * Bila env MAIL_HOST diisi, email dikirim lewat SMTP (fsockopen, tanpa
 * dependensi). Jika tidak, pesan ditulis ke vendor/storage/mail/*.eml
 * (outbox) supaya tetap bisa dikembangkan tanpa server mail.
 *
 * SMTP memakai AUTH LOGIN; dukungan SSL bila MAIL_SECURE=ssl.
 */

final class Mailer
{
    private static ?string $directory = null;

    public static function setDirectory(string $directory): void
    {
        self::$directory = rtrim($directory, '/\\');
    }

    public static function send(string $to, string $subject, string $body): string
    {
        $mail = self::config();
        $host = $mail['host'];

        if ($host !== '') {
            return self::sendSmtp($to, $subject, $body, $mail);
        }

        return self::sendOutbox($to, $subject, $body);
    }

    /**
     * @return array{host: string, port: int, user: string, pass: string, from: string, from_name: string, secure: string, timeout: int}
     */
    private static function config(): array
    {
        $path = (defined('FRAMEWORK_ROOT') ? FRAMEWORK_ROOT : dirname(__DIR__, 3)) . '/vendor/config/mail.php';
        $file = is_file($path) ? require $path : [];
        $file = is_array($file) ? $file : [];

        $env = static function (string $name): string {
            $value = getenv($name);

            return is_string($value) ? $value : '';
        };

        $host = $env('MAIL_HOST');
        if ($host === '') {
            $host = (string) ($file['host'] ?? '');
        }

        $port = $env('MAIL_PORT');
        $from = $env('MAIL_FROM');
        $secure = $env('MAIL_SECURE');

        return [
            'host' => $host,
            'port' => (int) ($port !== '' ? $port : ($file['port'] ?? 587)),
            'user' => trim($env('MAIL_USER') !== '' ? $env('MAIL_USER') : (string) ($file['user'] ?? '')),
            'pass' => str_replace(' ', '', $env('MAIL_PASS') !== '' ? $env('MAIL_PASS') : (string) ($file['pass'] ?? '')),
            'from' => $from !== '' ? $from : (string) ($file['from'] ?? ''),
            'from_name' => $env('MAIL_FROM_NAME') !== '' ? $env('MAIL_FROM_NAME') : (string) ($file['from_name'] ?? ''),
            'secure' => $secure !== '' ? $secure : (string) ($file['secure'] ?? ''),
            'timeout' => (int) ($env('MAIL_TIMEOUT') !== '' ? $env('MAIL_TIMEOUT') : 15),
        ];
    }

    /**
     * Menulis ke storage/mail (default, tanpa SMTP).
     */
    private static function sendOutbox(string $to, string $subject, string $body): string
    {
        if (self::$directory === null) {
            throw new \RuntimeException('Direktori mail belum diset.');
        }

        if (!is_dir(self::$directory)) {
            mkdir(self::$directory, 0775, true);
        }

        $path = self::$directory . '/' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.eml';
        $content = "To: {$to}\nSubject: {$subject}\n\n{$body}\n";

        file_put_contents($path, $content, LOCK_EX);

        return $path;
    }

    /**
     * Mengirim lewat SMTP. Kredensial dari vendor/config/mail.php
     * (MAIL_HOST dan kawanannya di env tetap menimpa file).
     *
     * @param array{host: string, port: int, user: string, pass: string, from: string, from_name: string, secure: string, timeout: int} $mail
     */
    private static function sendSmtp(string $to, string $subject, string $body, array $mail): string
    {
        $host = $mail['host'];
        $port = $mail['port'] > 0 ? $mail['port'] : 587;
        $user = $mail['user'];
        $pass = $mail['pass'];
        $from = $mail['from'] !== '' ? $mail['from'] : 'no-reply@' . (parse_url((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost');
        $secure = $mail['secure'];
        $timeout = $mail['timeout'] > 0 ? $mail['timeout'] : 15;

        $remote = ($secure === 'ssl' ? 'ssl' : 'tcp') . '://' . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'peer_name' => $host,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
            ],
        ]);
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if ($socket === false) {
            throw new \RuntimeException('SMTP tidak bisa terhubung: ' . $errstr);
        }

        stream_set_timeout($socket, $timeout);

        try {
            self::readResponse($socket);
            self::command($socket, 'EHLO ' . self::ehloName(), '250');

            if ($secure === 'tls') {
                self::command($socket, 'STARTTLS', '220');
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }

                if (!stream_socket_enable_crypto($socket, true, $crypto)) {
                    $detail = '';

                    while ($message = openssl_error_string()) {
                        $detail = $message;
                    }

                    throw new \RuntimeException('STARTTLS gagal' . ($detail !== '' ? ': ' . $detail : ''));
                }

                self::command($socket, 'EHLO ' . self::ehloName(), '250');
            }

            if ($user !== '') {
                self::command($socket, 'AUTH LOGIN', '334');
                self::command($socket, base64_encode($user), '334');
                self::command($socket, base64_encode($pass), '235');
            }

            self::command($socket, 'MAIL FROM:<' . $from . '>', '250');
            self::command($socket, 'RCPT TO:<' . $to . '>', '250');
            self::command($socket, 'DATA', '354');

            $fromHeader = $from;

            if (($mail['from_name'] ?? '') !== '') {
                $fromHeader = '"' . str_replace(['"', "\r", "\n"], '', $mail['from_name']) . '" <' . $from . '>';
            }

            $headers = 'Subject: ' . $subject . "\r\nTo: " . $to . "\r\nFrom: " . $fromHeader . "\r\n";
            self::command($socket, $headers . "\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.", '250');
            self::command($socket, 'QUIT', '221');
        } finally {
            fclose($socket);
        }

        return 'smtp:' . $to;
    }

    private static function ehloName(): string
    {
        $name = gethostname() ?: 'localhost';

        return preg_match('/^[A-Za-z0-9.-]+$/', $name) === 1 ? $name : 'localhost';
    }

    /**
     * @param resource $socket
     */
    private static function command($socket, string $line, string $expect): string
    {
        self::write($socket, $line);
        $response = self::readResponse($socket);

        if (!str_starts_with($response, $expect)) {
            $summary = trim(strtok($response, "\r\n") ?: $response);

            throw new \RuntimeException('SMTP menolak perintah: ' . $summary);
        }

        return $response;
    }

    /**
     * @param resource $socket
     */
    private static function readResponse($socket): string
    {
        $response = '';

        do {
            $line = fgets($socket, 515);

            if ($line === false) {
                throw new \RuntimeException('SMTP tidak menjawab');
            }

            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        return $response;
    }

    /**
     * @param resource $socket
     */
    private static function write($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }
}