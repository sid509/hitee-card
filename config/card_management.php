<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Card Management API Configuration
    |--------------------------------------------------------------------------
    | Implements PRODUCTION_BACKEND_API_REQUIREMENTS.md
    */

    'key_service' => [
        'url' => env('KEY_SERVICE_URL', 'http://127.0.0.1:8100'),
        'timeout_ms' => (int) env('KEY_SERVICE_TIMEOUT_MS', 5000),
        'internal_service_token' => env('KEY_SERVICE_INTERNAL_TOKEN'),
    ],

    'auth' => [
        // Secure by default: the v1 API requires the workstation bearer token
        // unless explicitly disabled (local dev / tests only).
        'required' => env('CM_AUTH_REQUIRED', true),
        'workstation_api_token' => env('CM_WORKSTATION_API_TOKEN'),
    ],

    'card_profile' => [
        'environment' => env('CARD_ENVIRONMENT', 'LAB'),
        'production_eligible' => env('CARD_ENVIRONMENT', 'LAB') === 'PRODUCTION',
        'physical_card_writes_enabled' => (bool) env('CM_PHYSICAL_CARD_WRITES_ENABLED', true),
        'card_number_prefix' => env('CARD_NUMBER_PREFIX', '9999'),
        'default_card_type_code' => env('CM_DEFAULT_CARD_TYPE_CODE', '0100'),
        'structure_version' => env('CARD_STRUCTURE_VERSION', '1.13'),
        'key_profile_version' => env('KEY_PROFILE_VERSION', 'hitee-lab-v1'),
        'lab_profile' => [
            'profile_id' => 'hitee-lab-v1',
            'card_capacity_kb' => (int) env('LAB_CARD_CAPACITY_KB', 8),
            'ef0017_required' => (bool) env('LAB_EF0017_REQUIRED', true),
            'city_code' => env('LAB_CITY_CODE', '9999000000000000'),
            'issuer_code' => env('LAB_ISSUER_CODE', '0001000000000000'),
            'application_identifier' => env('LAB_APPLICATION_IDENTIFIER', '0000000000000001'),
            'card_data_version' => env('LAB_CARD_DATA_VERSION', '00'),
            'card_enable_flag' => env('LAB_CARD_ENABLE_FLAG', '01'),
            'application_version' => env('LAB_APPLICATION_VERSION', '00'),
            'application_type' => env('LAB_APPLICATION_TYPE', '01'),
            'application_enable_flag' => env('LAB_APPLICATION_ENABLE_FLAG', '01'),
            'deposit_minor_units' => (int) env('LAB_DEPOSIT_MINOR_UNITS', 0),
            'expiry_years' => (int) env('LAB_EXPIRY_YEARS', 5),
            'diversification_profile_version' => 'ESTON_L1_L2_L3_V1',
            'diversification_mapping' => [
                'L1' => 'CITY_CODE_PACKED_BCD_8_BYTES',
                'L2' => 'ISSUER_CODE_PACKED_BCD_8_BYTES',
                'L3' => 'CARD_SERIAL_NUMBER_PACKED_BCD_8_BYTES',
            ],
            'per_key_diversification_chains' => [
                'DCCK' => ['L3'],
                'DCMK' => ['L3'],
                'DACK' => ['L1', 'L3'],
                'DAMK' => ['L1', 'L3'],
                'DPK1' => ['L1', 'L2', 'L3'],
                'DPK2' => ['L1', 'L2', 'L3'],
                'DLK1' => ['L3'],
                'DLK2' => ['L3'],
                'TAC' => ['L3'],
                'PINU' => ['L3'],
                'PINR' => ['L3'],
            ],
        ],
        'lab_card_uid_allowlist' => array_filter(
            array_map('trim', explode(',', env('LAB_CARD_UID_ALLOWLIST', '')))
        ),
    ],

    'wallet' => [
        'lab_recharge_writes_enabled' => (bool) env('LAB_RECHARGE_WRITES_ENABLED', true),
        'lab_recharge_uid_allowlist' => array_filter(
            array_map('trim', explode(',', env('LAB_RECHARGE_UID_ALLOWLIST', '')))
        ),
        'max_amount_minor_units' => (int) env('LAB_RECHARGE_MAX_AMOUNT', 100000),
        'terminal_number_hex' => env('LAB_RECHARGE_TERMINAL_NUMBER', '000000000001'),
        'lab_debit_writes_enabled' => (bool) env('LAB_DEBIT_WRITES_ENABLED', true),
        'lab_debit_uid_allowlist' => array_filter(
            array_map('trim', explode(',', env('LAB_DEBIT_UID_ALLOWLIST', '')))
        ),
        'debit_max_amount_minor_units' => (int) env('LAB_DEBIT_MAX_AMOUNT', 50000),
        'debit_terminal_number_hex' => env('LAB_DEBIT_TERMINAL_NUMBER', '000000000001'),
        'lab_recharge_reversal_writes_enabled' => (bool) env('LAB_REVERSAL_WRITES_ENABLED', true),
        'lab_recharge_reversal_uid_allowlist' => array_filter(
            array_map('trim', explode(',', env('LAB_REVERSAL_UID_ALLOWLIST', '')))
        ),
        'reversal_max_age_minutes' => (int) env('REVERSAL_MAX_AGE_MINUTES', 1440),
        'reversal_terminal_number_hex' => env('LAB_REVERSAL_TERMINAL_NUMBER', '000000000001'),
    ],

    'issuance' => [
        'lab_personalization_writes_enabled' => (bool) env('LAB_ISSUANCE_WRITES_ENABLED', true),
        'lab_personalization_uid_allowlist' => array_filter(
            array_map('trim', explode(',', env('LAB_ISSUANCE_UID_ALLOWLIST', '')))
        ),
        'issuance_office_code' => env('ISSUANCE_OFFICE_CODE', '0001'),
        'customer_number_prefix' => env('CUSTOMER_NUMBER_PREFIX', 'CUST'),
        'categories' => [
            'STANDARD' => ['card_type_code' => '0100', 'application_type' => '01', 'expiry_years' => 5, 'deposit_minor_units' => 0],
            'DISCOUNTED' => ['card_type_code' => '0300', 'application_type' => '03', 'expiry_years' => 1, 'deposit_minor_units' => 0],
            'SENIOR' => ['card_type_code' => '0400', 'application_type' => '04', 'expiry_years' => 5, 'deposit_minor_units' => 0],
            'EMPLOYEE' => ['card_type_code' => '0500', 'application_type' => '05', 'expiry_years' => 2, 'deposit_minor_units' => 0],
        ],
    ],

    'replacement' => [
        'lab_replacement_writes_enabled' => (bool) env('LAB_REPLACEMENT_WRITES_ENABLED', true),
        'lab_replacement_uid_allowlist' => array_filter(
            array_map('trim', explode(',', env('LAB_REPLACEMENT_UID_ALLOWLIST', '')))
        ),
        'max_transfer_amount_minor_units' => (int) env('REPLACEMENT_MAX_TRANSFER_AMOUNT', 100000),
    ],

    'envelope' => [
        'ttl_seconds' => (int) env('KEY_ENVELOPE_TTL_SECONDS', 300),
        'max_delivery_count' => (int) env('KEY_ENVELOPE_MAX_DELIVERIES', 2),
    ],

    'idempotency' => [
        'retention_hours' => (int) env('IDEMPOTENCY_RETENTION_HOURS', 24),
    ],

    'validator' => [
        // Base URL the validator should call to reach this platform's API.
        // Encoded into the provisioning QR as `apiUrl`. In production this MUST
        // be a host reachable from the validator device (not localhost).
        // Falls back to APP_URL . '/api/v1' when unset.
        'qr_api_url' => env('CARD_MANAGEMENT_QR_API_URL'),
        // Operator-issued secret that authorizes re-registration of an existing
        // device (e.g. after a device wipe). When unset, re-registration
        // requires the device's current API token.
        'provisioning_secret' => env('CM_VALIDATOR_PROVISIONING_SECRET'),
    ],

    'settlement' => [
        // Bank payout file formats (NACHA ACH, ISO 20022 pain.001) are not yet
        // compliant with the real specifications. Keep disabled so a
        // non-conformant file can never be delivered to a bank. CSV remains
        // available for internal settlement flows.
        'bank_payout_formats_enabled' => (bool) env('CM_SETTLEMENT_BANK_FORMATS_ENABLED', false),
    ],
];
