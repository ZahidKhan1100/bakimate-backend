<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Transaction;
class BalanceService
{
    /**
     * Outstanding balance in sen from persisted transactions (source of truth).
     */
    public function recalculateCustomerBalance(Customer $customer): int
    {
        $rows = Transaction::query()
            ->where('customer_id', $customer->id)
            ->get(['type', 'amount_sen']);

        return $this->balanceFromTransactionRows($rows);
    }

    /**
     * @param  iterable<int, object{type: string, amount_sen: int}>  $rows
     */
    public function balanceFromTransactionRows(iterable $rows): int
    {
        $balance = 0;
        foreach ($rows as $row) {
            $delta = $this->deltaForTransaction((string) $row->type, (int) $row->amount_sen);
            $balance += $delta;
        }

        return $balance;
    }

    public function deltaForTransaction(string $type, int $amountSen): int
    {
        if ($amountSen < 0) {
            throw new \InvalidArgumentException('amount_sen must be non-negative.');
        }

        return match ($type) {
            Transaction::TYPE_CREDIT => $amountSen,
            Transaction::TYPE_PAYMENT => -$amountSen,
            default => throw new \InvalidArgumentException('Unknown transaction type: '.$type),
        };
    }

    public function syncCachedBalance(Customer $customer): void
    {
        $customer->balance_sen = $this->recalculateCustomerBalance($customer);
        $customer->save();
    }

    /**
     * Clears instalment-reminder planner fields once the debtor no longer owes anything.
     * Matches the post-payment branch in RecordPaymentOrCreditAction.
     */
    public function clearReminderFieldsWhenSettled(Customer $customer): void
    {
        $customer->refresh();
        if ($customer->balance_sen <= 0) {
            $customer->next_due_at = null;
            $customer->goal_amount_sen = null;
            $customer->goal_target_date = null;
            $customer->save();
        }
    }
}
