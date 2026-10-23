<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http;

interface HttpClientInterface
{
    /**
     * @throws HttpTransportException ked spojenie zlyha uplne (DNS, timeout, TLS a pod.)
     */
    public function get(
        string $url,
        int $timeoutSeconds,
        ?string $basicAuthUsername = null,
        ?string $basicAuthPassword = null,
    ): HttpResponse;
}
