<?php

declare(strict_types=1);

namespace Noelo\Monitor\Fio;

/**
 * Sprava vynimky nikdy nesmie obsahovat API token ani URL (token je jej castou).
 */
final class FioApiException extends \RuntimeException
{
}
