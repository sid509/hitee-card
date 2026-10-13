<?php

declare(strict_types=1);

namespace App\Services\CardManagement\KeyProvision;

use App\Services\CardManagement\KeyServiceClient;

/**
 * KeyProvider backed by the separate Node.js Key Service over HTTP.
 * Production-safe: Laravel relays opaque envelopes and never sees
 * plaintext key material.
 */
final class RemoteKeyServiceProvider implements KeyProvider
{
    private KeyServiceClient $client;

    public function __construct(?KeyServiceClient $client = null)
    {
        $this->client = $client ?? KeyServiceClient::make();
    }

    public function isReady(): bool
    {
        return $this->client->isReady();
    }

    public function health(): array
    {
        return [
            'ready' => $this->client->isReady(),
            'provider' => 'REMOTE_KEY_SERVICE',
        ];
    }

    public function prepareKeyEnvelope(array $input, string $requestId): array
    {
        return $this->client->prepareKeyEnvelope($input, $requestId);
    }
}
