<?php

declare(strict_types=1);

namespace App\Services\CardManagement\KeyProvision;

use App\Services\CardManagement\KeyServiceRequestRejectedError;
use App\Services\CardManagement\KeyServiceUnavailableError;
use Carbon\Carbon;

/**
 * In-process LAB-only key provider (ADR 0018).
 *
 * Byte-compatible PHP port of the Node.js Key Service pipeline:
 *   1. AES-256-GCM lab keystore decrypt (AAD: HITEE_LAB_KEYSTORE|1|hitee-lab-v1|<keyType>|<rootKeyVersion>)
 *   2. Vendor diversification: DK = 3DES-EDE-ECB(MK, D) ‖ 3DES-EDE-ECB(MK, ~D),
 *      chained over packed-BCD L1/L2/L3 inputs
 *   3. Envelope: X25519 ephemeral → HKDF-SHA256 → AES-256-GCM with canonical
 *      (sorted-key) JSON AAD — identical wire format to the Node service,
 *      so the issuer desktop cannot tell which implementation produced it.
 *
 * HARD BOUNDARY: this provider refuses to operate unless the card profile
 * environment is LAB and the requested profile is 'hitee-lab-v1'. Laravel
 * must never hold production root key material — production deployments use
 * RemoteKeyServiceProvider (separate Node service / HSM).
 */
final class LabKeyStoreProvider implements KeyProvider
{
    private const KEYSTORE_FORMAT_VERSION = 1;

    private const KEYSTORE_PROFILE_ID = 'hitee-lab-v1';

    private const KEYSTORE_AAD_PREFIX = 'HITEE_LAB_KEYSTORE';

    private const ROOT_KEY_VERSION = 'hitee-lab-roots-v1';

    private const ENVELOPE_VERSION = 1;

    private const ENVELOPE_INFO_PREFIX = 'hitee-key-envelope-v1';

    private const X25519_SPKI_PREFIX_HEX = '302A300506032B656E032100';

    private const DIVERSIFICATION_MAPPING = [
        'L1' => 'CITY_CODE_PACKED_BCD_8_BYTES',
        'L2' => 'ISSUER_CODE_PACKED_BCD_8_BYTES',
        'L3' => 'CARD_SERIAL_NUMBER_PACKED_BCD_8_BYTES',
    ];

    private const SAFE_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{2,127}$/';

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    private const UID_PATTERN = '/^[0-9A-F]{8,32}$/';

    private const BCD16_PATTERN = '/^[0-9]{16}$/';

    private ?array $document = null;

    public function isReady(): bool
    {
        return $this->health()['ready'];
    }

    public function health(): array
    {
        try {
            $document = $this->loadDocument();

            return [
                'ready' => true,
                'provider' => 'LAB_KEYSTORE_IN_PROCESS',
                'profileId' => $document['profileId'],
                'keyCount' => count($document['keys']),
            ];
        } catch (\Throwable $e) {
            return [
                'ready' => false,
                'provider' => 'LAB_KEYSTORE_IN_PROCESS',
                'reason' => $e->getMessage(),
            ];
        }
    }

    public function prepareKeyEnvelope(array $input, string $requestId): array
    {
        if (config('card_management.card_profile.environment') !== 'LAB') {
            throw new KeyServiceUnavailableError(
                'In-process key provider is restricted to the LAB environment; configure CARD_KEY_PROVIDER=service.'
            );
        }

        $request = $this->validateRequest($input);
        $profileId = $request['profileId'];
        if ($profileId !== self::KEYSTORE_PROFILE_ID) {
            throw new KeyServiceRequestRejectedError("Profile {$profileId} is not available from the lab provider.");
        }

        $chains = config('card_management.card_profile.lab_profile.per_key_diversification_chains', []);
        $keyIds = config('card_management.card_profile.lab_profile.key_ids', []);
        $keyTypes = $request['requestedKeyTypes'] ?? array_keys($chains);

        $unsupported = array_diff($keyTypes, array_keys($chains), array_keys($keyIds));
        if ($unsupported !== []) {
            throw new KeyServiceRequestRejectedError(
                'Profile does not define requested key types: '.implode(', ', $unsupported).'.'
            );
        }
        if ($keyTypes === []) {
            throw new KeyServiceRequestRejectedError('Profile has no usable key types.');
        }

        $issuedAt = Carbon::now('UTC');
        $expiresAt = $issuedAt->copy()->addSeconds(
            (int) config('card_management.envelope.ttl_seconds', 300)
        );
        $issuedAtIso = $issuedAt->format('Y-m-d\TH:i:s.v\Z');
        $expiresAtIso = $expiresAt->format('Y-m-d\TH:i:s.v\Z');

        $levelValues = [
            'L1' => $request['cityCode'],
            'L2' => $request['issuerCode'],
            'L3' => $request['cardNumber'],
        ];

        $derivedKeys = [];
        $rootKeyVersions = [];
        $perKeyChains = [];
        $document = $this->loadDocument();

        try {
            foreach ($keyTypes as $keyType) {
                $levels = $chains[$keyType] ?? null;
                if (! $levels) {
                    throw new KeyServiceRequestRejectedError("Key type {$keyType} has no diversification chain.");
                }

                $inputs = [];
                foreach ($levels as $level) {
                    $value = $levelValues[$level] ?? null;
                    if ($value === null || $value === '') {
                        throw new KeyServiceRequestRejectedError(
                            "Diversification input {$level} is required by key type {$keyType}."
                        );
                    }
                    $inputs[] = self::encodePackedBcd8($value);
                }

                $rootKey = $this->getRootKey($document, $keyType);
                try {
                    $diversified = self::diversify3DesChain($rootKey['material'], $inputs);
                    $derivedKeys[$keyType] = [
                        'keyId' => $keyIds[$keyType],
                        'keyVersion' => '00',
                        'value' => strtoupper(bin2hex($diversified)),
                        'valueHex' => $diversified,
                    ];
                    $rootKeyVersions[$keyType] = $rootKey['version'];
                    $perKeyChains[$keyType] = $levels;
                } finally {
                    sodium_memzero($rootKey['material']);
                    foreach ($inputs as $b) {
                        sodium_memzero($b);
                    }
                }
            }
        } catch (\Throwable $e) {
            foreach ($derivedKeys as $k) {
                sodium_memzero($k['valueHex']);
            }
            throw $e;
        }

        try {
            $diversificationInputs = array_filter([
                'L1' => $levelValues['L1'],
                'L2' => $levelValues['L2'],
            ], fn ($v) => $v !== null && $v !== '') + [
                'L3' => $levelValues['L3'],
                'encoding' => 'PACKED_BCD_8_BYTES',
                'mapping' => self::DIVERSIFICATION_MAPPING,
            ];

            $manifest = [
                'profileId' => $profileId,
                'diversificationProfileVersion' => config('card_management.card_profile.lab_profile.diversification_profile_version', 'ESTON_L1_L2_L3_V1'),
                'keyTypes' => $keyTypes,
                'rootKeyVersions' => $rootKeyVersions,
                'perKeyDiversificationChains' => $perKeyChains,
                'diversificationInputsHash' => strtoupper(hash('sha256', self::stableJson($diversificationInputs))),
            ];

            $payload = [
                'version' => 1,
                'requestId' => $request['requestId'],
                'operationId' => $request['operationId'],
                'cardId' => $request['cardId'],
                'expectedUid' => $request['expectedUid'],
                'cardNumber' => $request['cardNumber'],
                'profileId' => $profileId,
                'issuedAt' => $issuedAtIso,
                'expiresAt' => $expiresAtIso,
                'keys' => array_map(
                    fn ($k) => ['keyId' => $k['keyId'], 'keyVersion' => $k['keyVersion'], 'value' => $k['value']],
                    $derivedKeys
                ),
            ];

            $envelope = $this->createEnvelope($request, $payload, $issuedAtIso, $expiresAtIso);

            return [
                'requestId' => $request['requestId'],
                'manifest' => $manifest,
                'envelope' => $envelope,
                // Match KeyServiceClient's normalization: DB columns store
                // 'Y-m-d H:i:s' in app timezone.
                'expiresAt' => $expiresAt->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            ];
        } finally {
            foreach ($derivedKeys as $k) {
                sodium_memzero($k['valueHex']);
            }
        }
    }

    // ------------------------------------------------------------------
    // Lab keystore (AES-256-GCM per-key records)
    // ------------------------------------------------------------------

    /**
     * @return array{version: int, profileId: string, keys: array<string, array>}
     */
    private function loadDocument(): array
    {
        if ($this->document !== null) {
            return $this->document;
        }

        $path = (string) config('card_management.key_provider.lab.keystore_path');
        if ($path !== '' && ! str_starts_with($path, '/')) {
            $path = base_path($path);
        }
        if (! is_file($path) || ! is_readable($path)) {
            throw new KeyServiceUnavailableError("Lab keystore not readable at {$path}.");
        }

        $document = json_decode((string) file_get_contents($path), true);
        if (! is_array($document)
            || ($document['version'] ?? null) !== self::KEYSTORE_FORMAT_VERSION
            || ($document['profileId'] ?? null) !== self::KEYSTORE_PROFILE_ID
            || ! is_array($document['keys'] ?? null)) {
            throw new KeyServiceUnavailableError('Lab keystore document is malformed.');
        }

        return $this->document = $document;
    }

    /**
     * @return array{material: string, version: string}
     */
    private function getRootKey(array $document, string $keyType): array
    {
        $record = $document['keys'][$keyType] ?? null;
        if (! is_array($record) || ($record['algorithm'] ?? null) !== 'AES-256-GCM') {
            throw new KeyServiceRequestRejectedError("Lab root key {$keyType} is not available.");
        }

        $kek = $this->labKek();
        $iv = self::decodeHexField($record['iv'] ?? '', 12, "{$keyType} IV");
        $authTag = self::decodeHexField($record['authTag'] ?? '', 16, "{$keyType} auth tag");
        $ciphertext = self::decodeHexField($record['ciphertext'] ?? '', 16, "{$keyType} ciphertext");
        $rootKeyVersion = (string) ($record['rootKeyVersion'] ?? '');

        try {
            $aad = implode('|', [
                self::KEYSTORE_AAD_PREFIX,
                (string) self::KEYSTORE_FORMAT_VERSION,
                self::KEYSTORE_PROFILE_ID,
                $keyType,
                $rootKeyVersion,
            ]);

            $key = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $kek,
                OPENSSL_RAW_DATA,
                $iv,
                $authTag,
                $aad
            );

            sodium_memzero($kek);
            sodium_memzero($iv);
            sodium_memzero($authTag);
            sodium_memzero($ciphertext);

            if ($key === false || strlen($key) !== 16) {
                throw new KeyServiceUnavailableError("Lab key {$keyType} could not be decrypted.");
            }

            if ($rootKeyVersion !== self::ROOT_KEY_VERSION) {
                sodium_memzero($key);
                throw new KeyServiceRequestRejectedError(
                    "Root-key version mismatch for {$keyType}.",
                    ['expected' => self::ROOT_KEY_VERSION, 'actual' => $rootKeyVersion]
                );
            }

            return ['material' => $key, 'version' => $rootKeyVersion];
        } catch (\Throwable $e) {
            if (! isset($key) || $key === false) {
                sodium_memzero($kek);
            }
            throw $e;
        }
    }

    private function labKek(): string
    {
        $kekFile = config('card_management.key_provider.lab.kek_file');
        $kekHex = $kekFile ? trim((string) @file_get_contents($kekFile)) : config('card_management.key_provider.lab.kek');

        if (! is_string($kekHex) || ! preg_match('/^[0-9A-Fa-f]{64}$/', $kekHex)) {
            throw new KeyServiceUnavailableError('LAB_KEYSTORE_KEK must be 64 hexadecimal characters.');
        }

        return hex2bin($kekHex);
    }

    // ------------------------------------------------------------------
    // Vendor diversification (3DES-EDE, no padding)
    //   DK = E(MK, D) ‖ E(MK, ~D), chained over each level's 8-byte input.
    // ------------------------------------------------------------------

    /**
     * @param  string[]  $inputs  8-byte binary diversification inputs
     */
    private static function diversify3DesChain(string $rootKey, array $inputs): string
    {
        if (strlen($rootKey) !== 16 || $inputs === []) {
            throw new \InvalidArgumentException('Root key must be 16 bytes with at least one input.');
        }

        $current = $rootKey;
        foreach ($inputs as $input) {
            $next = self::diversify3Des($current, $input);
            if ($current !== $rootKey) {
                sodium_memzero($current);
            }
            $current = $next;
        }

        return $current;
    }

    private static function diversify3Des(string $masterKey, string $data): string
    {
        if (strlen($masterKey) !== 16 || strlen($data) !== 8) {
            throw new \InvalidArgumentException('Master key must be 16 bytes; data must be 8 bytes.');
        }

        $inverted = $data ^ str_repeat("\xFF", 8);
        $left = openssl_encrypt($data, 'des-ede', $masterKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, '');
        $right = openssl_encrypt($inverted, 'des-ede', $masterKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, '');
        sodium_memzero($inverted);

        if ($left === false || $right === false || strlen($left) !== 8 || strlen($right) !== 8) {
            throw new KeyServiceUnavailableError('3DES diversification failed (des-ede unavailable?).');
        }

        return $left.$right;
    }

    /**
     * Sixteen decimal digits → eight packed-BCD bytes.
     */
    private static function encodePackedBcd8(string $value): string
    {
        if (! preg_match(self::BCD16_PATTERN, $value)) {
            throw new KeyServiceRequestRejectedError('Diversification input must contain exactly 16 decimal digits.');
        }

        $encoded = hex2bin($value);
        if ($encoded === false || strlen($encoded) !== 8) {
            throw new KeyServiceRequestRejectedError('Diversification input must encode to exactly 8 bytes.');
        }

        return $encoded;
    }

    // ------------------------------------------------------------------
    // Envelope: X25519 → HKDF-SHA256 → AES-256-GCM (Node-identical format)
    // ------------------------------------------------------------------

    private function createEnvelope(array $request, array $payload, string $issuedAtIso, string $expiresAtIso): array
    {
        [$recipientDer, $recipientRaw] = self::parseRecipientPublicKey($request['recipient']['publicKey']);
        $recipientFingerprint = strtoupper(hash('sha256', $recipientDer));

        $ephKeypair = sodium_crypto_box_keypair();
        $ephSecret = sodium_crypto_box_secretkey($ephKeypair);
        $ephPublicRaw = sodium_crypto_box_publickey($ephKeypair);
        $ephPublicDer = hex2bin(self::X25519_SPKI_PREFIX_HEX).$ephPublicRaw;

        $sharedSecret = sodium_crypto_scalarmult($ephSecret, $recipientRaw);
        $salt = random_bytes(32);
        $iv = random_bytes(12);
        $info = self::ENVELOPE_INFO_PREFIX."\0".$request['operationId']."\0".$request['recipient']['keyId'];
        $wrappingKey = hash_hkdf('sha256', $sharedSecret, 32, $info, $salt);

        $aad = [
            'version' => self::ENVELOPE_VERSION,
            'operationId' => $request['operationId'],
            'cardId' => $request['cardId'],
            'expectedUid' => $request['expectedUid'],
            'cardNumber' => $request['cardNumber'],
            'profileId' => $request['profileId'],
            'workstationId' => $request['workstationId'],
            'recipientKeyId' => $request['recipient']['keyId'],
            'recipientPublicKeyFingerprint' => $recipientFingerprint,
            'issuedAt' => $issuedAtIso,
            'expiresAt' => $expiresAtIso,
        ];
        if (! empty($request['cityCode'])) {
            $aad['cityCode'] = $request['cityCode'];
        }
        if (! empty($request['issuerCode'])) {
            $aad['issuerCode'] = $request['issuerCode'];
        }

        $aadJson = self::stableJson($aad);
        $tag = '';
        $ciphertext = openssl_encrypt(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'aes-256-gcm',
            $wrappingKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aadJson,
            16
        );

        sodium_memzero($ephSecret);
        sodium_memzero($recipientRaw);
        sodium_memzero($sharedSecret);
        sodium_memzero($wrappingKey);

        if ($ciphertext === false) {
            throw new KeyServiceUnavailableError('Failed to encrypt key envelope.');
        }

        return [
            'version' => self::ENVELOPE_VERSION,
            'keyAgreement' => 'X25519',
            'kdf' => 'HKDF-SHA256',
            'contentEncryption' => 'AES-256-GCM',
            'ephemeralPublicKey' => base64_encode($ephPublicDer),
            'salt' => base64_encode($salt),
            'iv' => base64_encode($iv),
            'authTag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
            'aad' => $aad,
            'aadHash' => strtoupper(hash('sha256', $aadJson)),
        ];
    }

    /**
     * @return array{0: string, 1: string} [DER bytes, raw 32-byte key]
     */
    private static function parseRecipientPublicKey(string $base64): array
    {
        $der = base64_decode($base64, true);
        $prefix = hex2bin(self::X25519_SPKI_PREFIX_HEX);
        if ($der === false || strlen($der) !== 44 || ! str_starts_with($der, $prefix)) {
            throw new KeyServiceRequestRejectedError(
                'recipient.publicKey must be a valid X25519 SPKI key.',
                ['code' => 'INVALID_RECIPIENT_PUBLIC_KEY']
            );
        }

        return [$der, substr($der, 12)];
    }

    // ------------------------------------------------------------------
    // Validation (mirrors keyEnvelope.validation.js)
    // ------------------------------------------------------------------

    private function validateRequest(array $input): array
    {
        $reject = fn (string $message) => throw new KeyServiceRequestRejectedError($message);

        $require = function (string $field, ?string $pattern = null) use ($input, $reject) {
            $value = $input[$field] ?? null;
            if (! is_string($value) || $value === '' || strlen($value) > 256) {
                $reject("{$field} is required.");
            }
            if ($pattern && ! preg_match($pattern, $value)) {
                $reject("{$field} has an invalid format.");
            }

            return $value;
        };

        $request = [
            'requestId' => $require('requestId', self::SAFE_ID_PATTERN),
            'operationId' => strtolower($require('operationId', self::UUID_PATTERN)),
            'cardId' => strtolower($require('cardId', self::UUID_PATTERN)),
            'expectedUid' => $require('expectedUid', self::UID_PATTERN),
            'cardNumber' => $require('cardNumber', self::BCD16_PATTERN),
            'profileId' => $require('profileId'),
            'workstationId' => $require('workstationId', self::SAFE_ID_PATTERN),
            'cityCode' => isset($input['cityCode']) ? $require('cityCode', self::BCD16_PATTERN) : null,
            'issuerCode' => isset($input['issuerCode']) ? $require('issuerCode', self::BCD16_PATTERN) : null,
        ];

        $recipient = $input['recipient'] ?? null;
        if (! is_array($recipient)) {
            $reject('recipient is required.');
        }
        $request['recipient'] = [
            'keyId' => isset($recipient['keyId']) && is_string($recipient['keyId']) && $recipient['keyId'] !== ''
                ? $recipient['keyId']
                : $reject('recipient.keyId is required.'),
            'publicKey' => isset($recipient['publicKey']) && is_string($recipient['publicKey'])
                ? $recipient['publicKey']
                : $reject('recipient.publicKey is required.'),
        ];

        if (isset($input['requestedKeyTypes'])) {
            if (! is_array($input['requestedKeyTypes'])) {
                $reject('requestedKeyTypes must be an array.');
            }
            $request['requestedKeyTypes'] = array_values($input['requestedKeyTypes']);
        }

        return $request;
    }

    // ------------------------------------------------------------------
    // Canonical JSON (stableJson: recursively sorted object keys)
    // ------------------------------------------------------------------

    private static function stableJson(mixed $value): string
    {
        if ($value === null || ! is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Distinguish lists from objects like JS: sequential 0..n-1 keys = list.
        if (array_is_list($value)) {
            return '['.implode(',', array_map(self::stableJson(...), $value)).']';
        }

        ksort($value, SORT_STRING);
        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = json_encode((string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                .':'.self::stableJson($item);
        }

        return '{'.implode(',', $parts).'}';
    }

    private static function decodeHexField(string $value, int $byteLength, string $fieldName): string
    {
        if (! preg_match('/^[0-9A-Fa-f]+$/', $value) || strlen($value) !== $byteLength * 2) {
            throw new KeyServiceUnavailableError("{$fieldName} is malformed.");
        }

        return hex2bin($value);
    }
}
