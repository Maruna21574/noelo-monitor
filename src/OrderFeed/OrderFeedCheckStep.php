<?php

declare(strict_types=1);

namespace Noelo\Monitor\OrderFeed;

use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\FeedErrorRepository;

/**
 * Orchestruje jeden beh kontroly objednavkoveho feedu: stiahnutie, parsovanie,
 * import a zaznamenanie stavu/chyb do check_runs a feed_errors.
 */
final class OrderFeedCheckStep
{
    public function __construct(
        private readonly OrderFeedFetcher $fetcher,
        private readonly OrderFeedParser $parser,
        private readonly OrderFeedImporter $importer,
        private readonly CheckRunRepository $checkRuns,
        private readonly FeedErrorRepository $feedErrors,
        private readonly PayedAmountAnomalyDetector $anomalyDetector = new PayedAmountAnomalyDetector(),
    ) {
    }

    /**
     * @throws OrderFeedFetchException|OrderFeedParseException ked feed nie je vobec pouzitelny
     */
    public function run(int $checkRunId): OrderFeedImportSummary
    {
        try {
            $xml = $this->fetcher->fetch();
        } catch (OrderFeedFetchException $e) {
            $this->checkRuns->markFeedStatus($checkRunId, 'order_feed_status', 'error');
            $this->feedErrors->record($checkRunId, 'order_feed', $e->getMessage());
            throw $e;
        }

        try {
            $parseResult = $this->parser->parse($xml);
        } catch (OrderFeedParseException $e) {
            $this->checkRuns->markFeedStatus($checkRunId, 'order_feed_status', 'error');
            $this->feedErrors->record($checkRunId, 'order_feed', $e->getMessage());
            throw $e;
        }

        foreach ($parseResult->skippedOrderErrors as $skippedError) {
            $this->feedErrors->record($checkRunId, 'order_feed', $skippedError);
        }

        foreach ($this->anomalyDetector->detect($parseResult->orders) as $warning) {
            $this->feedErrors->record($checkRunId, 'order_feed', $warning);
        }

        $summary = $this->importer->import($parseResult);
        $this->checkRuns->markFeedStatus($checkRunId, 'order_feed_status', 'ok');

        return $summary;
    }
}
