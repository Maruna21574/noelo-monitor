<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http;

final class CurlHttpClient implements HttpClientInterface
{
    public function get(
        string $url,
        int $timeoutSeconds,
        ?string $basicAuthUsername = null,
        ?string $basicAuthPassword = null,
    ): HttpResponse {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min($timeoutSeconds, 10),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);

        if ($basicAuthUsername !== null) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, "{$basicAuthUsername}:{$basicAuthPassword}");
        }

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() je od PHP 8.0 no-op (a od 8.5 deprecated) - handle sa
        // uvolnuje automaticky, netreba ho volat.

        if ($errno !== 0 || $body === false) {
            // URL sa nikdy nevypisuje - moze obsahovat token v query stringu.
            throw new HttpTransportException("HTTP pozadovanie zlyhalo (curl errno {$errno}).");
        }

        return new HttpResponse($statusCode, $body);
    }
}
