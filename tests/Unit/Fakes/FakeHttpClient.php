<?php

declare(strict_types=1);

namespace Noelo\Monitor\Tests\Unit\Fakes;

use Noelo\Monitor\Http\HttpClientInterface;
use Noelo\Monitor\Http\HttpResponse;

final class FakeHttpClient implements HttpClientInterface
{
    private int $callCount = 0;
    private ?string $lastBasicAuthUsername = null;
    private ?string $lastBasicAuthPassword = null;

    /** @param array<int, HttpResponse|\Throwable> $scriptedResults vracane/hodene v poradi volani */
    public function __construct(private readonly array $scriptedResults)
    {
    }

    public function get(
        string $url,
        int $timeoutSeconds,
        ?string $basicAuthUsername = null,
        ?string $basicAuthPassword = null,
    ): HttpResponse {
        $this->lastBasicAuthUsername = $basicAuthUsername;
        $this->lastBasicAuthPassword = $basicAuthPassword;

        $result = $this->scriptedResults[$this->callCount] ?? end($this->scriptedResults);
        $this->callCount++;

        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }

    public function callCount(): int
    {
        return $this->callCount;
    }

    public function lastBasicAuthUsername(): ?string
    {
        return $this->lastBasicAuthUsername;
    }

    public function lastBasicAuthPassword(): ?string
    {
        return $this->lastBasicAuthPassword;
    }
}
