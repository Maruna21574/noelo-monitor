<?php

declare(strict_types=1);

namespace Noelo\Monitor\Rules;

enum OrderAvailabilityStatus: string
{
    case SHIPPABLE = 'shippable';
    case PARTIALLY_SHIPPABLE = 'partially_shippable';
    case UNAVAILABLE = 'unavailable';
}
