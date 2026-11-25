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
     * Retourne [en-têtes de contenu, corps encodé] : texte seul, ou texte + HTML.
     */
    function build_mime(string $text, ?string $html): array
    {
        $encode = static fn(string $value): string => chunk_split(base64_encode($value), 76, "\r\n");

        if ($html === null) {
            return [
                ['Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64'],
                $encode($text),
            ];
        }

        $boundary = 'tcb_' . bin2hex(random_bytes(12));
        $body = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . $encode($text)
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . $encode($html)
            . "--{$boundary}--\r\n";

        return [["Content-Type: multipart/alternative; boundary=\"{$boundary}\""], $body];
    }

    /**
     * Gabarit des e-mails du site. Tout est en tableaux et styles en ligne :
     * c'est ce que les messageries acceptent. Aucune image (souvent bloquée).
     *
     * Clés : greeting, lead, code, button [label, url], notes [phrases], footer.
     */
    function email_layout(array $parts): string
    {
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $font = "Arial, Helvetica, sans-serif";

        $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            . '<body style="margin:0;padding:0;background:#ffffff;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;"><tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;">'
            . '<tr><td style="background:#14148c;padding:20px 28px;font-family:\'Arial Black\',Arial,sans-serif;font-size:20px;font-weight:900;color:#ffffff;">Tennis Club de Batilly</td></tr>'
            . '<tr><td style="background:#e8e55a;height:8px;line-height:8px;font-size:0;">&nbsp;</td></tr>'
            . '<tr><td style="padding:30px 28px 8px 28px;font-family:' . $font . ';font-size:16px;line-height:1.5;color:#0d0e24;">';

        if (!empty($parts['greeting'])) {
            $html .= '<p style="margin:0 0 14px 0;font-weight:bold;">' . $e($parts['greeting']) . '</p>';
        }
        if (!empty($parts['lead'])) {
            $html .= '<p style="margin:0 0 22px 0;">' . $e($parts['lead']) . '</p>';
        }
        if (!empty($parts['code'])) {
            $html .= '<p style="margin:0 0 22px 0;padding:14px 0;border:3px solid #14148c;text-align:center;font-family:\'Courier New\',Courier,monospace;font-size:38px;font-weight:bold;letter-spacing:10px;color:#14148c;">'
                . $e($parts['code']) . '</p>';
        }
        if (!empty($parts['button'])) {
            $html .= '<p style="margin:0 0 18px 0;"><a href="' . $e($parts['button']['url']) . '" style="display:inline-block;padding:13px 22px;background:#14148c;color:#ffffff;font-weight:bold;text-decoration:none;">'
                . $e($parts['button']['label']) . '</a></p>'
                . '<p style="margin:0 0 22px 0;font-size:13px;color:#565b78;word-break:break-all;">Si le bouton ne marche pas, copiez ce lien : ' . $e($parts['button']['url']) . '</p>';
        }
        foreach ($parts['notes'] ?? [] as $note) {
            $html .= '<p style="margin:0 0 10px 0;font-size:14px;color:#565b78;">' . $e($note) . '</p>';
        }

        $html .= '</td></tr>'
            . '<tr><td style="padding:18px 28px 0 28px;border-top:2px solid #d6d9e6;font-family:' . $font . ';font-size:12px;color:#7a7f99;">'
            . $e($parts['footer'] ?? 'Message automatique du site du Tennis Club de Batilly. Inutile d\'y répondre.')
            . '</td></tr></table></td></tr></table></body></html>';

        return $html;
    }

    /**
     * Envoi via SMTP authentifié. Lève une exception en cas d'échec.
     */
    function send_email_smtp(string $to, string $subject, string $body, ?string $html = null): bool
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

            [$mimeHeaders, $mimeBody] = build_mime($body, $html);
            $headers = array_merge([
                'Date: ' . date('r'),
                'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . '>',
                'To: <' . $to . '>',
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
            ], $mimeHeaders);

            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $mimeBody;

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
    function send_email(string $to, string $subject, string $body, ?string $html = null): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
            error_log('send_email : destinataire invalide rejeté');
            return false;
        }

        if (defined('SMTP_HOST') && SMTP_HOST !== '') {
            try {
                return send_email_smtp($to, $subject, $body, $html);
            } catch (Throwable $e) {
                error_log('send_email (SMTP) : ' . $e->getMessage());
                return false;
            }
        }

        // Repli : fonction mail() de PHP
        [$mimeHeaders, $mimeBody] = build_mime($body, $html);
        $headers = 'From: =?UTF-8?B?' . base64_encode(MAIL_FROM_NAME) . '?= <' . MAIL_FROM . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . implode("\r\n", $mimeHeaders);
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return @mail($to, $encodedSubject, $mimeBody, $headers);
    }
}
