<?php

namespace App\Actions\Transaction;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecordPaymentOrCreditAction
{
    public function __construct(
        private readonly BalanceService $balances,
    ) {}

    /**
     * @param  array{
     *     type: string,
     *     amount_sen: int,
     *     note?: string|null,
     *     next_due_at?: string|null,
     *     item_key?: string|null,
     *     goal_amount_sen?: int|null,
     *     goal_target_date?: string|null
     * }  $data
     */
    public function execute(User $user, int $customerId, array $data): Transaction
    {
        $shop = $user->shops()->firstOrFail();
        /** @var Customer $customer */
        $customer = Customer::query()
            ->where('shop_id', $shop->id)
            ->whereKey($customerId)
            ->firstOrFail();

        $nextDueRaw = isset($data['next_due_at']) && $data['next_due_at'] !== null && $data['next_due_at'] !== ''
            ? $data['next_due_at']
            : null;

        return DB::transaction(function () use ($shop, $customer, $data, $nextDueRaw) {
            $itemKey = isset($data['item_key']) && $data['item_key'] !== null && trim((string) $data['item_key']) !== ''
                ? trim((string) $data['item_key'])
                : null;

            $recordedAt = isset($data['recorded_at']) && $data['recorded_at'] !== null && $data['recorded_at'] !== ''
                ? CarbonImmutable::parse((string) $data['recorded_at'])->startOfDay()
                : null;

            $transaction = new Transaction([
                'shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'amount_sen' => $data['amount_sen'],
                'type' => $data['type'],
                'note' => $data['note'] ?? null,
                'item_key' => $itemKey,
            ]);

            if ($recordedAt !== null) {
                $transaction->created_at = $recordedAt;
                $transaction->updated_at = $recordedAt;
            }

            $transaction->save();

            $this->balances->syncCachedBalance($customer->fresh());

            /** @var Customer $fresh */
            $fresh = $customer->fresh();
            $type = $data['type'];

            if ($type === Transaction::TYPE_CREDIT) {
                if ($nextDueRaw !== null) {
                    $fresh->next_due_at = $nextDueRaw;
                }
                if (array_key_exists('goal_amount_sen', $data) && $data['goal_amount_sen'] !== null) {
                    $fresh->goal_amount_sen = (int) $data['goal_amount_sen'];
                }
                if (
                    array_key_exists('goal_target_date', $data)
                    && $data['goal_target_date'] !== null
                    && trim((string) $data['goal_target_date']) !== ''
                ) {
                    $fresh->goal_target_date = $data['goal_target_date'];
                }
                $fresh->save();
            } elseif ($type === Transaction::TYPE_PAYMENT && $fresh->balance_sen <= 0) {
                $fresh->next_due_at = null;
                $fresh->goal_amount_sen = null;
                $fresh->goal_target_date = null;
                $fresh->save();
            }

            return $transaction->fresh();
        });
    }
}
