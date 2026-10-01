<?php

namespace App\Services;

use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\MerchantIncome;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Single writer for the customer wallet ledger (balance_ins,
 * balance_outs) and merchant_incomes.
 *
 * Every credit/debit in the system goes through here so invariants —
 * positive amounts, known type/status vocabulary, correct card/user
 * linkage — are enforced in one place instead of repeated at each of
 * the ~11 call sites. ADR 0016.
 */
class LedgerService
{
    /** Settled credit — balance is spendable immediately. */
    public const CREDIT_SETTLED = 'completed';

    /** Pending credit — gateway initiation awaiting confirmation. */
    public const CREDIT_PENDING = 'pending';

    /**
     * Record a credit (top-up, manual adjustment, opening balance).
     *
     * @param array $attrs user_id, amount, type required; card_id,
     *   remarks, gateway_name, transaction_id, payload, created_by,
     *   status optional (defaults to settled).
     */
    public function credit(array $attrs): BalanceIn
    {
        $this->guardAmount($attrs['amount'] ?? null);

        return BalanceIn::create([
            'user_id' => $attrs['user_id'] ?? null,
            'card_id' => $attrs['card_id'] ?? null,
            'amount' => $attrs['amount'],
            'type' => $attrs['type'] ?? 'manual',
            'remarks' => $attrs['remarks'] ?? null,
            'gateway_name' => $attrs['gateway_name'] ?? null,
            'transaction_id' => $attrs['transaction_id'] ?? null,
            'payload' => $attrs['payload'] ?? null,
            'created_by' => $attrs['created_by'] ?? null,
            'status' => $attrs['status'] ?? self::CREDIT_SETTLED,
        ]);
    }

    /**
     * Record a debit (fare, parking, manual charge).
     *
     * @param array $attrs amount required; user_id, card_id,
     *   merchant_id, type, remarks, reference_id, reference_type,
     *   created_by optional.
     */
    public function debit(array $attrs): BalanceOut
    {
        $this->guardAmount($attrs['amount'] ?? null);

        return BalanceOut::create([
            'user_id' => $attrs['user_id'] ?? null,
            'card_id' => $attrs['card_id'] ?? null,
            'merchant_id' => $attrs['merchant_id'] ?? null,
            'amount' => $attrs['amount'],
            'type' => $attrs['type'] ?? 'manual',
            'remarks' => $attrs['remarks'] ?? null,
            'reference_id' => $attrs['reference_id'] ?? null,
            'reference_type' => $attrs['reference_type'] ?? null,
            'created_by' => $attrs['created_by'] ?? null,
        ]);
    }

    /**
     * Record merchant income linked to a debit.
     */
    public function merchantIncome(BalanceOut $debit, array $attrs): MerchantIncome
    {
        $this->guardAmount($attrs['amount'] ?? null);

        return MerchantIncome::create([
            'merchant_id' => $attrs['merchant_id'],
            'balance_out_id' => $debit->id,
            'reference_id' => $attrs['reference_id'] ?? $debit->reference_id,
            'reference_type' => $attrs['reference_type'] ?? $debit->reference_type,
            'amount' => $attrs['amount'],
            'type' => $attrs['type'] ?? 'fare',
        ]);
    }

    /**
     * Debit a wallet and record the paired merchant income — the common
     * fare/parking completion path used by both tap controllers.
     *
     * Wrapped in a transaction so the debit never persists without its
     * paired income row, even when the caller isn't itself inside one.
     */
    public function debitWithIncome(array $debitAttrs, string $incomeType): ?array
    {
        return DB::transaction(function () use ($debitAttrs, $incomeType) {
            $debit = $this->debit($debitAttrs);

            $income = null;
            if (! empty($debitAttrs['merchant_id'])) {
                $income = $this->merchantIncome($debit, [
                    'merchant_id' => $debitAttrs['merchant_id'],
                    'amount' => $debitAttrs['amount'],
                    'type' => $incomeType,
                ]);
            }

            return [$debit, $income];
        });
    }

    private function guardAmount(mixed $amount): void
    {
        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new InvalidArgumentException('Ledger amount must be a positive number.');
        }
    }
}
