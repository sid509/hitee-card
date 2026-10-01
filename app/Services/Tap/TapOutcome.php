<?php

namespace App\Services\Tap;

use App\Models\Ride;
use App\Models\Tap;

/**
 * Result of a processed tap. All money fields are in MAJOR units
 * (decimal rupees) — the ledger's convention. API adapters convert
 * to minor units where their wire contract requires it.
 */
class TapOutcome
{
    /**
     * @param string      $type           'in' | 'out'
     * @param float       $balance        wallet balance at tap time (tap-in)
     * @param float|null  $fare           charged fare (tap-out)
     * @param float|null  $balanceBefore  balance before the debit (tap-out)
     * @param float|null  $balanceAfter   balance after the debit (tap-out)
     * @param float|null  $shortfall      portion of fare not covered (tap-out)
     * @param float|null  $minimumFare    minimum fare gate applied (tap-in)
     */
    public function __construct(
        public readonly string $type,
        public readonly Ride $ride,
        public readonly Tap $tap,
        public readonly ?int $locationId,
        public readonly string $locationName,
        public readonly float $balance,
        public readonly ?float $fare = null,
        public readonly ?float $balanceBefore = null,
        public readonly ?float $balanceAfter = null,
        public readonly ?float $shortfall = null,
        public readonly ?float $minimumFare = null,
    ) {}
}
