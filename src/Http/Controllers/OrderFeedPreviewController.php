<?php

declare(strict_types=1);

namespace Noelo\Monitor\Http\Controllers;

use Noelo\Monitor\Config\Env;
use Noelo\Monitor\Http\CurlHttpClient;
use Noelo\Monitor\OrderFeed\OrderFeedFetchException;
use Noelo\Monitor\OrderFeed\OrderFeedFetcher;

/**
 * Zobrazi surovy objednavkovy feed priamo v prehliadaci (pre admina, ktory je
 * uz prihlaseny do administracie) - prihlasovacie udaje k feedu (Basic Auth)
 * sa nikdy nedostanu do prehliadaca, appka feed stiahne na pozadi sama.
 *
 * POZOR: na rozdiel od dashboardu/e-mailu tento nahlad NEsanitizuje osobne
 * udaje - je to presne to, co posiela e-shop (mena, e-maily zakaznikov).
 */
final class OrderFeedPreviewController
{
    public function __construct(private readonly Env $env)
    {
    }

    public function handle(): void
    {
        $fetcher = new OrderFeedFetcher(
            httpClient: new CurlHttpClient(),
            url: $this->env->require('ORDER_FEED_URL'),
            timeoutSeconds: $this->env->getInt('ORDER_FEED_TIMEOUT_SECONDS', 15),
            maxAttempts: 1,
            retryDelayMs: 0,
            basicAuthUsername: $this->env->get('ORDER_FEED_USERNAME'),
            basicAuthPassword: $this->env->get('ORDER_FEED_PASSWORD'),
        );

        try {
            $xml = $fetcher->fetch();
        } catch (OrderFeedFetchException) {
            http_response_code(502);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Nepodarilo sa stiahnut objednavkovy feed. Skontroluj .env a skus to znova.';
            return;
        }

        header('Content-Type: application/xml; charset=UTF-8');
        echo $xml;
    }
}
