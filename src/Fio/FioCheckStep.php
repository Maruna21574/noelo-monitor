<?php

declare(strict_types=1);

namespace Noelo\Monitor\Fio;

use Noelo\Monitor\Reporting\CheckRunRepository;
use Noelo\Monitor\Reporting\FeedErrorRepository;

final class FioCheckStep
{
    public function __construct(
        private readonly FioClient $client,
        private readonly FioTransactionImporter $importer,
        private readonly CheckRunRepository $checkRuns,
        private readonly FeedErrorRepository $feedErrors,
        private readonly int $lookbackDays = 10,
    ) {
    }

    /**
     * @throws FioApiException ked su data z Fio API nedostupne/nepouzitelne
     */
    public function run(int $checkRunId, \DateTimeImmutable $today = new \DateTimeImmutable()): int
    {
        $from = $today->modify("-{$this->lookbackDays} days");

        try {
            $transactions = $this->client->fetchTransactions($from, $today);
            $imported = $this->importer->import($transactions);
        } catch (FioApiException $e) {
            $this->checkRuns->markFeedStatus($checkRunId, 'fio_status', 'error');
            $this->feedErrors->record($checkRunId, 'fio_api', $e->getMessage());
            throw $e;
        }

        $this->checkRuns->markFeedStatus($checkRunId, 'fio_status', 'ok');

        return $imported;
    }
}
