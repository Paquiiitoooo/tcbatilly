<?php
/**
 * TC BATILLY - Envoi d'e-mails
 *
 * send_email() utilise un SMTP authentifié si SMTP_HOST est défini
 * (cas Plesk : contact@tc-batilly.fr), sinon repli sur la fonction mail().
 * Retourne true si l'e-mail a été accepté par le serveur.
 */

if (!function_exists('send_email')) {

    /**
     * Lit une réponse SMTP (gère les réponses multi-lignes « 250- »).
     */
    function smtp_read_response($socket): string
    {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            // 4e caractère : ' ' => dernière ligne ; '-' => suite
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    }

    /**
     * Envoie une commande SMTP et vérifie le(s) code(s) de réponse attendu(s).
     */
    function smtp_command($socket, ?string $command, $expectedCodes): string
    {
        if ($command !== null) {
            fwrite($socket, $command . "\r\n");
        }
        $response = smtp_read_response($socket);
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, (array) $expectedCodes, true)) {
            $label = $command !== null ? trim($command) : 'connexion';
            throw new RuntimeException("SMTP [$label] : réponse $code — " . trim($response));
        }
        return $response;
    }

    /**
     * Envoi via SMTP authentifié. Lève une exception en cas d'échec.
     */
    function send_email_smtp(string $to, string $subject, string $body): bool
    {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $secure = SMTP_SECURE;

        $remote = ($secure === 'ssl') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new RuntimeException("SMTP : connexion impossible à {$host}:{$port} — {$errstr}");
        }
        stream_set_timeout($socket, 15);

        $ehloHost = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';

        try {
            smtp_command($socket, null, 220);
            smtp_command($socket, "EHLO {$ehloHost}", 250);

            if ($secure === 'tls') {
                smtp_command($socket, 'STARTTLS', 220);
                $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!stream_socket_enable_crypto($socket, true, $crypto)) {
                    throw new RuntimeException('SMTP : échec de la négociation STARTTLS');
                }
                smtp_command($socket, "EHLO {$ehloHost}", 250);
            }

            if (SMTP_USER !== '') {
                smtp_command($socket, 'AUTH LOGIN', 334);
                smtp_command($socket, base64_encode(SMTP_USER), 334);
                smtp_command($socket, base64_encode(SMTP_PASS), 235);
            }

            smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM . '>', 250);
            smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            smtp_command($socket, 'DATA', 354);

            $headers = [
                'Date: ' . date('r'),
                'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . '>',
                'To: <' . $to . '>',
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];

            $payload = implode("\r\n", $headers) . "\r\n\r\n"
                . chunk_split(base64_encode($body), 76, "\r\n");

            // Fin du DATA : CRLF . CRLF
            fwrite($socket, $payload . "\r\n.\r\n");
            smtp_command($socket, null, 250);

            // Best effort, on ignore une éventuelle erreur sur QUIT
            try {
                smtp_command($socket, 'QUIT', 221);
            } catch (Throwable $ignore) {
            }
        } finally {
            @fclose($socket);
        }

        return true;
    }

    /**
     * Envoi d'un e-mail. Retourne true si accepté, false sinon.
     */
    function send_email(string $to, string $subject, string $body): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
            error_log('send_email : destinataire invalide rejeté');
            return false;
        }

        if (defined('SMTP_HOST') && SMTP_HOST !== '') {
            try {
                return send_email_smtp($to, $subject, $body);
            } catch (Throwable $e) {
                error_log('send_email (SMTP) : ' . $e->getMessage());
                return false;
            }
        }

        // Repli : fonction mail() de PHP
        $headers = 'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return @mail($to, $encodedSubject, $body, $headers);
    }
}
