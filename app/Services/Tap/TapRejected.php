<?php

namespace App\Services\Tap;

use RuntimeException;

/**
 * A tap was rejected by the engine (insufficient balance, duplicate
 * tap, inactive card…). Carries a stable machine-readable `errorCode`
 * so each API surface can render it in its own envelope.
 */
class TapRejected extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus,
        public readonly array $data = [],
    ) {
        parent::__construct($message);
    }
}
