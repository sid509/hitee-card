<?php

declare(strict_types=1);

namespace App\Services\CardManagement\KeyProvision;

use App\Services\CardManagement\KeyServiceUnavailableError;

/**
 * Selects the key-envelope provider from config (ADR 0018).
 *
 *   CARD_KEY_PROVIDER=service → RemoteKeyServiceProvider (production path)
 *   CARD_KEY_PROVIDER=lab     → LabKeyStoreProvider (in-process, LAB only)
 */
final class KeyProviderFactory
{
    public static function make(): KeyProvider
    {
        return match (config('card_management.key_provider.driver', 'service')) {
            'lab' => new LabKeyStoreProvider,
            'service' => new RemoteKeyServiceProvider,
            default => throw new KeyServiceUnavailableError(
                'Unknown CARD_KEY_PROVIDER driver; expected "service" or "lab".'
            ),
        };
    }
}
