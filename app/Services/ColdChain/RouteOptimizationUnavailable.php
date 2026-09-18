<?php

declare(strict_types=1);

namespace App\Services\ColdChain;

use RuntimeException;

final class RouteOptimizationUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
