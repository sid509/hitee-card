<?php

declare(strict_types=1);

namespace App\Services\CardManagement\KeyProvision;

/**
 * Source of key envelopes for Card Management operations.
 *
 * Two providers exist (ADR 0018):
 *  - RemoteKeyServiceProvider: relays to the separate Node.js Key Service.
 *    The only provider allowed in PRODUCTION.
 *  - LabKeyStoreProvider: in-process PHP implementation that decrypts the
 *    lab keystore and produces byte-identical envelopes. LAB environment
 *    only — Laravel must never hold production root key material.
 */
interface KeyProvider
{
    /**
     * Whether the provider is ready to serve envelopes.
     */
    public function isReady(): bool;

    /**
     * Health details for diagnostics (provider name, keystore state).
     *
     * @return array{ready: bool, provider: string, ...}
     */
    public function health(): array;

    /**
     * Prepare a key envelope for an operation.
     *
     * @param array{
     *   requestId: string,
     *   operationId: string,
     *   cardId: string,
     *   expectedUid: string,
     *   cardNumber: string,
     *   profileId: string,
     *   cityCode?: string,
     *   issuerCode?: string,
     *   workstationId: string,
     *   recipient: {keyId: string, publicKey: string},
     *   requestedKeyTypes?: string[]
     * } $input
     * @return array{requestId: string, manifest: array, envelope: array, expiresAt: string}
     *
     * expiresAt is returned in 'Y-m-d H:i:s' app-timezone format, matching
     * the normalization KeyServiceClient applies, so callers can persist it
     * into the envelope table unchanged.
     */
    public function prepareKeyEnvelope(array $input, string $requestId): array;
}
