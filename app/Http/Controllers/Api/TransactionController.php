<?php

namespace App\Http\Controllers\Api;

use App\Actions\Transaction\RecordPaymentOrCreditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Customer;
use App\Models\Transaction;
use App\Services\BalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request, RecordPaymentOrCreditAction $action): JsonResponse
    {
        $user = $request->user();
        $shopId = $user->shops()->value('id');
        $customerId = (int) $request->validated('customer_id');

        Customer::query()
            ->where('shop_id', $shopId)
            ->whereKey($customerId)
            ->firstOrFail();

        $validated = $request->validated();

        $transaction = $action->execute($user, $customerId, [
            'type' => $validated['type'],
            'amount_sen' => (int) $validated['amount_sen'],
            'note' => $validated['note'] ?? null,
            'next_due_at' => $validated['next_due_at'] ?? null,
            'item_key' => $validated['item_key'] ?? null,
            'goal_amount_sen' => $validated['goal_amount_sen'] ?? null,
            'goal_target_date' => $validated['goal_target_date'] ?? null,
        ]);

        /** @var Customer $customerFresh */
        $customerFresh = Customer::query()
            ->where('shop_id', $shopId)
            ->whereKey($customerId)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'transaction' => $transaction,
            'customer' => $customerFresh,
        ], 201);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction, BalanceService $balances): JsonResponse
    {
        $user = $request->user();
        $shopId = (int) $user->shops()->value('id');
        abort_unless((int) $transaction->shop_id === $shopId && $shopId > 0, 404);

        $validated = $request->validated();

        $itemKeyRaw = isset($validated['item_key']) && $validated['item_key'] !== null && trim((string) $validated['item_key']) !== ''
            ? trim((string) $validated['item_key'])
            : null;

        $transaction->amount_sen = (int) $validated['amount_sen'];
        $transaction->note = $validated['note'] ?? null;
        $transaction->item_key = $transaction->type === Transaction::TYPE_CREDIT ? $itemKeyRaw : null;
        $transaction->save();

        /** @var Customer $customer */
        $customer = Customer::query()
            ->where('shop_id', $shopId)
            ->whereKey((int) $transaction->customer_id)
            ->firstOrFail();

        $balances->syncCachedBalance($customer);
        $balances->clearReminderFieldsWhenSettled($customer);

        return response()->json([
            'success' => true,
            'transaction' => $transaction->fresh(),
            'customer' => $customer->fresh(),
        ]);
    }

    public function destroy(Request $request, Transaction $transaction, BalanceService $balances): JsonResponse
    {
        $user = $request->user();
        $shopId = (int) $user->shops()->value('id');
        abort_unless((int) $transaction->shop_id === $shopId && $shopId > 0, 404);

        $customerId = (int) $transaction->customer_id;
        $transaction->delete();

        /** @var Customer $customer */
        $customer = Customer::query()
            ->where('shop_id', $shopId)
            ->whereKey($customerId)
            ->firstOrFail();

        $balances->syncCachedBalance($customer);
        $balances->clearReminderFieldsWhenSettled($customer);

        return response()->json([
            'success' => true,
            'customer' => $customer->fresh(),
        ]);
    }
}
