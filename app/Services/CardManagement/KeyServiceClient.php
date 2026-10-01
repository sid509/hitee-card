<?php

namespace App\Services\CardManagement;

use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the Node.js Key Service.
 *
 * The Laravel backend NEVER sees plaintext card keys. It only relays
 * card identity + the desktop's recipient public key, then stores the
 * returned opaque envelope as-is.
 */
final class KeyServiceClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutMs,
        private readonly ?string $internalServiceToken,
    ) {}

    public static function make(): self
    {
        return new self(
            config('card_management.key_service.url'),
            config('card_management.key_service.timeout_ms'),
            config('card_management.key_service.internal_service_token'),
        );
    }

    /**
     * Check if the Key Service is ready.
     */
    public function isReady(): bool
    {
        try {
            $response = Http::withHeaders(['accept' => 'application/json'])
                ->timeout($this->timeoutMs / 1000)
                ->get($this->baseUrl . '/health/ready');

            if (!$response->ok()) {
                return false;
            }
            $body = $response->json();
            return $body['success'] ?? false;
        } catch (\Throwable $e) {
            return false;
        }
    }

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
     * @return array{manifest: array, envelope: array, expiresAt: string}
     * @throws KeyServiceUnavailableError|KeyServiceRequestRejectedError
     */
    public function prepareKeyEnvelope(array $input, string $requestId): array
    {
        if (!$this->internalServiceToken) {
            throw new KeyServiceUnavailableError('Key Service authentication is not configured.');
        }

        try {
            $response = Http::withHeaders([
                'accept' => 'application/json',
                'authorization' => 'Bearer ' . $this->internalServiceToken,
                'content-type' => 'application/json',
                'x-request-id' => $requestId,
            ])
            ->timeout($this->timeoutMs / 1000)
            ->post($this->baseUrl . '/internal/v1/key-envelopes/prepare', $input);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new KeyServiceUnavailableError('Key Service request timed out.');
        }

        $body = $response->json();

        if (!$response->successful() || !($body['success'] ?? false)) {
            $message = $body['error']['message'] ?? "Key Service request failed with HTTP {$response->status()}.";
            if ($response->status() >= 400 && $response->status() < 500) {
                throw new KeyServiceRequestRejectedError($message, $body['error']['details'] ?? null);
            }
            throw new KeyServiceUnavailableError($message);
        }

        $data = $body['data'];

        // Normalize expiresAt from ISO 8601 (e.g. "2026-08-25T09:21:37.176Z")
        // to MySQL timestamp format (Y-m-d H:i:s) for database storage.
        // The value arrives in UTC; convert to app timezone so comparisons
        // against now() (Asia/Kathmandu) stay correct.
        if (isset($data['expiresAt']) && is_string($data['expiresAt'])) {
            try {
                $data['expiresAt'] = \Carbon\Carbon::parse($data['expiresAt'])
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s');
            } catch (\Exception $_) {
                // Keep original if parsing fails
            }
        }

        return $data;
    }
}
