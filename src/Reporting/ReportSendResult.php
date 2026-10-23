<?php

declare(strict_types=1);

namespace Noelo\Monitor\Reporting;

enum ReportSendResult
{
    case SENT;
    case ALREADY_SENT_TODAY;
}
