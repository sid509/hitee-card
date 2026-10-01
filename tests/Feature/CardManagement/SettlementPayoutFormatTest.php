<?php

namespace Tests\Feature\CardManagement;

use App\Services\CardManagement\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettlementPayoutFormatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Bank payout formats (NACHA/ISO 20022) are gated behind an explicit
     * opt-in because the generated layouts are not yet bank-compliant.
     */
    private function enableBankFormats(): void
    {
        config(['card_management.settlement.bank_payout_formats_enabled' => true]);
    }

    private function createBatchWithEntries(): string
    {
        $batchId = Str::uuid()->toString();
        $date = '2026-08-26';

        DB::table('cm_settlement_batches')->insert([
            'id' => $batchId,
            'settlement_date' => $date,
            'status' => 'CALCULATED',
            'commission_rate' => 0.10,
            'total_trips' => 5,
            'total_fare_minor_units' => 2500,
            'total_commission_minor_units' => 250,
            'total_payout_minor_units' => 2250,
            'calculated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cm_settlement_entries')->insert([
            'id' => Str::uuid()->toString(),
            'batch_id' => $batchId,
            'device_id' => 'VAL-001',
            'operator_id' => 'OPS-001',
            'trip_count' => 3,
            'fare_minor_units' => 1500,
            'commission_minor_units' => 150,
            'payout_minor_units' => 1350,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cm_settlement_entries')->insert([
            'id' => Str::uuid()->toString(),
            'batch_id' => $batchId,
            'device_id' => 'VAL-002',
            'operator_id' => 'OPS-002',
            'trip_count' => 2,
            'fare_minor_units' => 1000,
            'commission_minor_units' => 100,
            'payout_minor_units' => 900,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $batchId;
    }

    public function test_csv_format(): void
    {
        $batchId = $this->createBatchWithEntries();
        $service = new SettlementService();
        $csv = $service->generatePayoutFile($batchId, 'csv');

        $this->assertStringContainsString('BatchID,SettlementDate', $csv);
        $this->assertStringContainsString('DeviceID,OperatorID', $csv);
        $this->assertStringContainsString('VAL-001', $csv);
        $this->assertStringContainsString('OPS-001', $csv);
    }

    public function test_bank_formats_are_gated_by_default(): void
    {
        $batchId = $this->createBatchWithEntries();
        $service = new SettlementService();

        foreach (['nacha', 'iso20022'] as $format) {
            try {
                $service->generatePayoutFile($batchId, $format);
                $this->fail("Expected PAYOUT_FORMAT_UNAVAILABLE for {$format}");
            } catch (\App\Services\CardManagement\CardManagementError $e) {
                $this->assertSame('PAYOUT_FORMAT_UNAVAILABLE', $e->errorCode);
                $this->assertSame(422, $e->httpStatus);
            }
        }
    }

    public function test_payout_file_endpoint_rejects_bank_formats_by_default(): void
    {
        $batchId = $this->createBatchWithEntries();

        $response = $this->withHeaders(['x-workstation-id' => 'ws-test'])
            ->get("/api/v1/settlement/batches/{$batchId}/payout-file?format=nacha");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYOUT_FORMAT_UNAVAILABLE');
    }

    public function test_nacha_format_has_header_records(): void
    {
        $this->enableBankFormats();
        $batchId = $this->createBatchWithEntries();
        $service = new SettlementService();
        $nacha = $service->generatePayoutFile($batchId, 'nacha');

        $lines = explode("\n", $nacha);

        // File header starts with '1'
        $this->assertStringStartsWith('1', $lines[0]);
        // Batch header starts with '5'
        $this->assertStringStartsWith('5', $lines[1]);
        // Entry detail records start with '6'
        $this->assertStringStartsWith('6', $lines[2]);
        $this->assertStringStartsWith('6', $lines[3]);
        // Batch control starts with '8'
        $this->assertStringStartsWith('8', $lines[4]);
        // File control starts with '9'
        $this->assertStringStartsWith('9', $lines[5]);
    }

    public function test_nacha_format_padded_to_block(): void
    {
        $this->enableBankFormats();
        $batchId = $this->createBatchWithEntries();
        $service = new SettlementService();
        $nacha = $service->generatePayoutFile($batchId, 'nacha');

        $lines = explode("\n", $nacha);

        // Should be padded to a multiple of 10 records
        $this->assertEquals(0, count($lines) % 10);
    }

    public function test_iso20022_format_is_valid_xml(): void
    {
        $this->enableBankFormats();
        $batchId = $this->createBatchWithEntries();
        $service = new SettlementService();
        $xml = $service->generatePayoutFile($batchId, 'iso20022');

        $this->assertStringStartsWith('<?xml', $xml);
        $this->assertStringContainsString('pain.001.001.03', $xml);
        $this->assertStringContainsString('CstmrCdtTrfInitn', $xml);
        $this->assertStringContainsString('CdtTrfTxInf', $xml);
        $this->assertStringContainsString('OPS-001', $xml);
        $this->assertStringContainsString('OPS-002', $xml);

        // Verify it parses as valid XML
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
    }

    public function test_iso20022_has_correct_transaction_count(): void
    {
        $this->enableBankFormats();
        $batchId = $this->createBatchWithEntries();
        $service = new SettlementService();
        $xml = $service->generatePayoutFile($batchId, 'iso20022');

        $doc = simplexml_load_string($xml);
        $namespaces = $doc->getNamespaces(true);
        $doc->registerXPathNamespace('ns', $namespaces['']);

        $txns = $doc->xpath('//ns:CdtTrfTxInf');
        $this->assertCount(2, $txns);
    }

    public function test_payout_file_endpoint_csv(): void
    {
        $batchId = $this->createBatchWithEntries();

        $response = $this->withHeaders(['x-workstation-id' => 'ws-test'])
            ->get("/api/v1/settlement/batches/{$batchId}/payout-file?format=csv");

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_payout_file_endpoint_nacha(): void
    {
        $this->enableBankFormats();
        $batchId = $this->createBatchWithEntries();

        $response = $this->withHeaders(['x-workstation-id' => 'ws-test'])
            ->get("/api/v1/settlement/batches/{$batchId}/payout-file?format=nacha");

        $response->assertStatus(200);
        $this->assertStringStartsWith('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function test_payout_file_endpoint_iso20022(): void
    {
        $this->enableBankFormats();
        $batchId = $this->createBatchWithEntries();

        $response = $this->withHeaders(['x-workstation-id' => 'ws-test'])
            ->get("/api/v1/settlement/batches/{$batchId}/payout-file?format=iso20022");

        $response->assertStatus(200);
        $this->assertStringStartsWith('application/xml', $response->headers->get('Content-Type'));
    }
}
