<?php

namespace App\Services\CardManagement;

/**
 * Base exception for all Card Management API domain errors.
 * Carries a canonical error code, HTTP status, and optional details.
 */
class CardManagementError extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus,
        string $message,
        public readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }
}

class VersionConflictError extends CardManagementError
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct('VERSION_CONFLICT', 409, 'Operation version mismatch.', [
            'expected' => $expected,
            'actual' => $actual,
        ]);
    }
}

class OperationNotFoundError extends CardManagementError
{
    public function __construct(string $message = 'Operation not found.')
    {
        parent::__construct('OPERATION_NOT_FOUND', 404, $message);
    }
}

class OperationNotActiveError extends CardManagementError
{
    public function __construct(string $message = 'Operation is in a terminal state.')
    {
        parent::__construct('OPERATION_NOT_ACTIVE', 409, $message);
    }
}

class InvalidStateTransitionError extends CardManagementError
{
    public function __construct(string $message = 'Requested transition is not allowed from current state.')
    {
        parent::__construct('INVALID_STATE_TRANSITION', 409, $message);
    }
}

class CardNotRegisteredError extends CardManagementError
{
    public function __construct(string $message = 'Card UID not in registry.')
    {
        parent::__construct('CARD_NOT_REGISTERED', 404, $message);
    }
}

class CardAlreadyRegisteredError extends CardManagementError
{
    public function __construct(string $message = 'Card UID already exists.')
    {
        parent::__construct('CARD_ALREADY_REGISTERED', 409, $message);
    }
}

class CardNotInitializedError extends CardManagementError
{
    public function __construct(string $message = 'Card has not been initialized.')
    {
        parent::__construct('CARD_NOT_INITIALIZED', 409, $message);
    }
}

class CardAlreadyInitializedError extends CardManagementError
{
    public function __construct(string $message = 'Card is already initialized.')
    {
        parent::__construct('CARD_ALREADY_INITIALIZED', 409, $message);
    }
}

class ActiveOperationExistsError extends CardManagementError
{
    public function __construct(string $message = 'Card already has an active operation of this type.')
    {
        parent::__construct('ACTIVE_OPERATION_EXISTS', 409, $message);
    }
}

class KeyEnvelopeNotReadyError extends CardManagementError
{
    public function __construct(string $message = 'Key envelope not yet prepared.')
    {
        parent::__construct('KEY_ENVELOPE_NOT_READY', 409, $message);
    }
}

class KeyEnvelopeExpiredError extends CardManagementError
{
    public function __construct(string $message = 'Key envelope TTL exceeded.')
    {
        parent::__construct('KEY_ENVELOPE_EXPIRED', 410, $message);
    }
}

class KeyEnvelopeAlreadyDeliveredError extends CardManagementError
{
    public function __construct(string $message = 'Key envelope already retrieved.')
    {
        parent::__construct('KEY_ENVELOPE_ALREADY_DELIVERED', 409, $message);
    }
}

class KeyEnvelopeAcknowledgementRequiredError extends CardManagementError
{
    public function __construct(string $message = 'Key envelope must be acknowledged before use.')
    {
        parent::__construct('KEY_ENVELOPE_ACKNOWLEDGEMENT_REQUIRED', 409, $message);
    }
}

class InvalidConfirmationPhraseError extends CardManagementError
{
    public function __construct(string $message = 'Confirmation phrase does not match.')
    {
        parent::__construct('INVALID_CONFIRMATION_PHRASE', 403, $message);
    }
}

class PhysicalWriteNotAuthorizedError extends CardManagementError
{
    public function __construct(string $message = 'Physical write not authorized.')
    {
        parent::__construct('PHYSICAL_WRITE_NOT_AUTHORIZED', 403, $message);
    }
}

class UidAllowlistViolationError extends CardManagementError
{
    public function __construct(string $message = 'Card UID not in allowlist.')
    {
        parent::__construct('UID_ALLOWLIST_VIOLATION', 403, $message);
    }
}

class InsufficientFundsError extends CardManagementError
{
    public function __construct(string $message = 'Debit amount exceeds balance.')
    {
        parent::__construct('INSUFFICIENT_FUNDS', 409, $message);
    }
}

class AmountInvalidError extends CardManagementError
{
    public function __construct(string $message = 'Amount is zero, negative, or exceeds limits.')
    {
        parent::__construct('AMOUNT_INVALID', 400, $message);
    }
}

class ReplacementTransferPendingError extends CardManagementError
{
    public function __construct(string $message = 'Ordinary recharge blocked during replacement transfer.')
    {
        parent::__construct('REPLACEMENT_TRANSFER_PENDING', 409, $message);
    }
}

class RecoveryRequiredError extends CardManagementError
{
    public function __construct(string $message = 'Operation in ambiguous state, manual recovery needed.')
    {
        parent::__construct('RECOVERY_REQUIRED', 409, $message);
    }
}

class KeyServiceUnavailableError extends CardManagementError
{
    public function __construct(string $message = 'Key Service is unavailable.')
    {
        parent::__construct('KEY_SERVICE_UNAVAILABLE', 502, $message);
    }
}

class KeyServiceRequestRejectedError extends CardManagementError
{
    public function __construct(string $message, ?array $details = null)
    {
        parent::__construct('KEY_SERVICE_REQUEST_REJECTED', 502, $message, $details);
    }
}
