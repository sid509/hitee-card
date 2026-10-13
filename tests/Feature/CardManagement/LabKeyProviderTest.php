<?php

namespace Tests\Feature\CardManagement;

use App\Services\CardManagement\KeyProvision\KeyProviderFactory;
use App\Services\CardManagement\KeyProvision\LabKeyStoreProvider;
use App\Services\CardManagement\KeyProvision\RemoteKeyServiceProvider;
use App\Services\CardManagement\KeyServiceRequestRejectedError;
use App\Services\CardManagement\KeyServiceUnavailableError;
use Tests\TestCase;

/**
 * LabKeyStoreProvider — in-process LAB key provider (ADR 0018).
 *
 * The diversification expectations are the vendor-confirmed vectors from
 * hitee-card-issuer/services/key-service/test/vendorDiversificationChains.test.js,
 * replayed here through a real encrypted keystore + real X25519 envelope so
 * the entire pipeline (keystore decrypt → 3DES diversify → envelope) is
 * proven byte-compatible with the Node service.
 */
class LabKeyProviderTest extends TestCase
{
    private const L1 = '3530000100000000';
    private const L2 = '0001000000000000';
    private const L3 = '3530000010001209';

    // root → expected DK, per vendor vectors
    private const VECTORS = [
        'DCCK' => ['root' => 'A11B4987D66831123C2FA593D91EA39A', 'expected' => '31DE566F68E6121D571E1F4E38EF6497'],
        'DCMK' => ['root' => '71B348E573FFA9384BC112994DE9F0F1', 'expected' => '22EC35B706BCC0A3798EF7E7C5C560FB'],
        'DACK' => ['root' => 'A21BC8723A8F29823CD22112AF2399AD', 'expected' => '0C3D639216BF37B0C981276883C53C99'],
        'DPK1' => ['root' => '5A7D405B9EB78A58BCA758DAF4E6A547', 'expected' => 'DB30E2985A9E5432C18D08B416618875'],
        'DPK2' => ['root' => 'FD1127F72F1DB2B49066B4F2C140B2AB', 'expected' => '34D8BB380182A4976FCF69EC03FB76EC'],
    ];

    private const CHAINS = [
        'DCCK' => ['L3'],
        'DCMK' => ['L3'],
        'DACK' => ['L1', 'L3'],
        'DPK1' => ['L1', 'L2', 'L3'],
        'DPK2' => ['L1', 'L2', 'L3'],
    ];

    private const KEY_IDS = [
        'DCCK' => '39', 'DCMK' => '36', 'DACK' => '39', 'DPK1' => '3E:01', 'DPK2' => '3E:02',
    ];

    private string $kekHex;
    private string $keystorePath;
    private string $recipientSecret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kekHex = strtoupper(bin2hex(random_bytes(32)));
        $this->keystorePath = tempnam(sys_get_temp_dir(), 'hitee-ks-') . '.json';
        $this->writeTestKeystore();

        config([
            'card_management.card_profile.environment' => 'LAB',
            'card_management.key_provider.lab.keystore_path' => $this->keystorePath,
            'card_management.key_provider.lab.kek' => $this->kekHex,
            'card_management.key_provider.lab.kek_file' => null,
            'card_management.card_profile.lab_profile.per_key_diversification_chains' => self::CHAINS,
            'card_management.card_profile.lab_profile.key_ids' => self::KEY_IDS,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keystorePath);
        if (isset($this->recipientSecret)) {
            sodium_memzero($this->recipientSecret);
        }
        parent::tearDown();
    }

    /** Encrypt the vector roots into a real AES-256-GCM lab keystore file. */
    private function writeTestKeystore(): void
    {
        $keys = [];
        foreach (self::VECTORS as $keyType => $v) {
            $iv = random_bytes(12);
            $tag = '';
            $aad = 'HITEE_LAB_KEYSTORE|1|hitee-lab-v1|' . $keyType . '|hitee-lab-roots-v1';
            $ct = openssl_encrypt(hex2bin($v['root']), 'aes-256-gcm', hex2bin($this->kekHex), OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
            $keys[$keyType] = [
                'algorithm' => 'AES-256-GCM',
                'rootKeyVersion' => 'hitee-lab-roots-v1',
                'iv' => strtoupper(bin2hex($iv)),
                'authTag' => strtoupper(bin2hex($tag)),
                'ciphertext' => strtoupper(bin2hex($ct)),
            ];
        }
        file_put_contents($this->keystorePath, json_encode([
            'version' => 1,
            'profileId' => 'hitee-lab-v1',
            'environment' => 'LAB',
            'keys' => $keys,
        ]));
    }

    private function prepareEnvelope(string $keyId, string $publicKeyB64): array
    {
        return (new LabKeyStoreProvider())->prepareKeyEnvelope([
            'requestId' => 'req-test-001',
            'operationId' => '3f2c9a1e-7b44-4c1d-9a2e-55aa66bb77cc',
            'cardId' => '9b1c2d3e-4f5a-4678-89ab-cdef01234567',
            'expectedUid' => '12A3E2EA',
            'cardNumber' => self::L3,
            'profileId' => 'hitee-lab-v1',
            'cityCode' => self::L1,
            'issuerCode' => self::L2,
            'workstationId' => 'ws-test-01',
            'requestedKeyTypes' => array_keys(self::VECTORS),
            'recipient' => ['keyId' => $keyId, 'publicKey' => $publicKeyB64],
        ], 'req-test-001');
    }

    /** Replicates the desktop's decryptKeyEnvelope binding + AES-GCM unwrap. */
    private function decryptEnvelope(array $envelope, string $keyId): array
    {
        $ephRaw = substr(base64_decode($envelope['ephemeralPublicKey']), 12);
        $shared = sodium_crypto_scalarmult($this->recipientSecret, $ephRaw);
        $info = "hitee-key-envelope-v1\0{$envelope['aad']['operationId']}\0{$keyId}";
        $wrap = hash_hkdf('sha256', $shared, 32, $info, base64_decode($envelope['salt']));

        $aad = $envelope['aad'];
        ksort($aad, SORT_STRING);
        $aadJsonParts = [];
        foreach ($aad as $k => $v) {
            $aadJsonParts[] = json_encode($k) . ':' . json_encode($v);
        }
        $aadJson = '{' . implode(',', $aadJsonParts) . '}';
        $this->assertSame(strtoupper(hash('sha256', $aadJson)), $envelope['aadHash'], 'aadHash mismatch');

        $plaintext = openssl_decrypt(
            base64_decode($envelope['ciphertext']),
            'aes-256-gcm',
            $wrap,
            OPENSSL_RAW_DATA,
            base64_decode($envelope['iv']),
            base64_decode($envelope['authTag']),
            $aadJson
        );

        sodium_memzero($shared);
        sodium_memzero($wrap);

        $this->assertNotFalse($plaintext, 'Envelope decryption failed — GCM tag/AAD mismatch');
        return json_decode($plaintext, true);
    }

    public function test_produces_vendor_diversification_vectors_inside_decryptable_envelope(): void
    {
        $keypair = sodium_crypto_box_keypair();
        $this->recipientSecret = sodium_crypto_box_secretkey($keypair);
        $recipientPublicDer = hex2bin('302A300506032B656E032100') . sodium_crypto_box_publickey($keypair);
        $keyId = 'test-recipient-01';

        $result = $this->prepareEnvelope($keyId, base64_encode($recipientPublicDer));

        $this->assertSame('req-test-001', $result['requestId']);
        $this->assertSame(1, $result['envelope']['version']);
        $this->assertSame('X25519', $result['envelope']['keyAgreement']);
        $this->assertSame('HKDF-SHA256', $result['envelope']['kdf']);
        $this->assertSame('AES-256-GCM', $result['envelope']['contentEncryption']);
        $this->assertSame('hitee-lab-v1', $result['envelope']['aad']['profileId']);
        $this->assertSame('ws-test-01', $result['envelope']['aad']['workstationId']);
        $this->assertSame(
            strtoupper(hash('sha256', $recipientPublicDer)),
            $result['envelope']['aad']['recipientPublicKeyFingerprint']
        );
        $this->assertSame('ESTON_L1_L2_L3_V1', $result['manifest']['diversificationProfileVersion']);
        $this->assertSame(self::CHAINS, $result['manifest']['perKeyDiversificationChains']);

        $payload = $this->decryptEnvelope($result['envelope'], $keyId);

        $this->assertSame('3f2c9a1e-7b44-4c1d-9a2e-55aa66bb77cc', $payload['operationId']);
        $this->assertSame('12A3E2EA', $payload['expectedUid']);
        $this->assertSame(self::L3, $payload['cardNumber']);

        foreach (self::VECTORS as $keyType => $v) {
            $this->assertSame(
                $v['expected'],
                $payload['keys'][$keyType]['value'],
                "{$keyType} diversification mismatch — PHP port diverges from vendor vector"
            );
            $this->assertSame(self::KEY_IDS[$keyType], $payload['keys'][$keyType]['keyId']);
            $this->assertSame('00', $payload['keys'][$keyType]['keyVersion']);
        }
    }

    public function test_refuses_outside_lab_environment(): void
    {
        config(['card_management.card_profile.environment' => 'PRODUCTION']);

        $this->expectException(KeyServiceUnavailableError::class);
        $this->prepareEnvelope('k', base64_encode(random_bytes(44)));
    }

    public function test_rejects_non_lab_profile(): void
    {
        $keypair = sodium_crypto_box_keypair();
        $der = hex2bin('302A300506032B656E032100') . sodium_crypto_box_publickey($keypair);

        $this->expectException(KeyServiceRequestRejectedError::class);
        (new LabKeyStoreProvider())->prepareKeyEnvelope([
            'requestId' => 'req-test-002',
            'operationId' => '3f2c9a1e-7b44-4c1d-9a2e-55aa66bb77cc',
            'cardId' => '9b1c2d3e-4f5a-4678-89ab-cdef01234567',
            'expectedUid' => '12A3E2EA',
            'cardNumber' => self::L3,
            'profileId' => 'hitee-production-v1',
            'workstationId' => 'ws-test-01',
            'recipient' => ['keyId' => 'k', 'publicKey' => base64_encode($der)],
        ], 'req-test-002');
    }

    public function test_factory_selects_provider(): void
    {
        config(['card_management.key_provider.driver' => 'lab']);
        $this->assertInstanceOf(LabKeyStoreProvider::class, KeyProviderFactory::make());

        config(['card_management.key_provider.driver' => 'service']);
        $this->assertInstanceOf(RemoteKeyServiceProvider::class, KeyProviderFactory::make());
    }
}
