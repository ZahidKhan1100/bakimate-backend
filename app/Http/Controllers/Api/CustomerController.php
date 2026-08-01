<?php

namespace App\Http\Controllers\Api;

use App\Actions\Customer\CreateCustomerAction;
use App\Actions\Customer\DeleteCustomerAction;
use App\Actions\Customer\UpdateCustomerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\CustomerPromise;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $shop = $request->user()?->shops()->firstOrFail();

        $paginator = Customer::query()
            ->where('shop_id', $shop->id)
            ->orderByDesc('updated_at')
            ->paginate(25);

        return response()->json($paginator);
    }

    public function show(Request $request, int $customerId): JsonResponse
    {
        $shop = $request->user()?->shops()->firstOrFail();
        $customer = Customer::query()
            ->where('shop_id', $shop->id)
            ->whereKey($customerId)
            ->firstOrFail();

        $recentTransactions = Transaction::query()
            ->where('shop_id', $shop->id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'amount_sen' => (int) $t->amount_sen,
                'type' => $t->type,
                'note' => $t->note,
                'item_key' => $t->item_key,
                'created_at' => $t->created_at !== null ? $t->created_at->toIso8601String() : null,
            ])
            ->values()
            ->all();

        return response()->json(array_merge($customer->toArray(), [
            'promises' => $customer->promises()
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderByDesc('promised_date')
                ->limit(30)
                ->get()
                ->map(fn (CustomerPromise $p) => CustomerPromiseController::serialize($p))
                ->values()
                ->all(),
            'recent_transactions' => $recentTransactions,
        ]));
    }

    public function transactions(Request $request, int $customerId): JsonResponse
    {
        $shop = $request->user()?->shops()->firstOrFail();
        $customer = Customer::query()
            ->where('shop_id', $shop->id)
            ->whereKey($customerId)
            ->firstOrFail();

        $month = $request->query('month');
        $query = Transaction::query()
            ->where('shop_id', $shop->id)
            ->where('customer_id', $customer->id);

        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            $tz = (string) config('app.timezone', 'UTC');
            $start = CarbonImmutable::parse($month.'-01', $tz)->startOfMonth();
            $end = $start->endOfMonth();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $rows = $query
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $credit = 0;
        $payments = 0;
        foreach ($rows as $t) {
            if ($t->type === Transaction::TYPE_CREDIT) {
                $credit += (int) $t->amount_sen;
            } elseif ($t->type === Transaction::TYPE_PAYMENT) {
                $payments += (int) $t->amount_sen;
            }
        }

        return response()->json([
            'customer_id' => $customer->id,
            'month' => is_string($month) && $month !== '' ? $month : null,
            'transactions' => $rows->map(fn (Transaction $t) => [
                'id' => $t->id,
                'amount_sen' => (int) $t->amount_sen,
                'type' => $t->type,
                'note' => $t->note,
                'item_key' => $t->item_key,
                'created_at' => $t->created_at !== null ? $t->created_at->toIso8601String() : null,
            ])->values()->all(),
            'totals' => [
                'credit_given_sen' => $credit,
                'payments_collected_sen' => $payments,
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request, CreateCustomerAction $action): JsonResponse
    {
        $shop = $request->user()?->shops()->firstOrFail();
        $data = $request->validated();
        $customer = $action->execute($shop, [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ]);

        return response()->json($customer, 201);
    }

    public function update(
        UpdateCustomerRequest $request,
        UpdateCustomerAction $action,
        int $customerId,
    ): JsonResponse {
        $shop = $request->user()?->shops()->firstOrFail();
        $customer = Customer::query()
            ->where('shop_id', $shop->id)
            ->whereKey($customerId)
            ->firstOrFail();

        $customer = $action->execute($customer, $request->validated());

        return response()->json($customer);
    }

    public function destroy(Request $request, DeleteCustomerAction $action, int $customerId): JsonResponse
    {
        $shop = $request->user()?->shops()->firstOrFail();
        $customer = Customer::query()
            ->where('shop_id', $shop->id)
            ->whereKey($customerId)
            ->firstOrFail();

        $action->execute($customer);

        return response()->json(['success' => true]);
    }

    public function rotatePublicBalanceLink(Request $request, int $customerId): JsonResponse
    {
        $shop = $request->user()?->shops()->firstOrFail();
        $customer = Customer::query()
            ->where('shop_id', $shop->id)
            ->whereKey($customerId)
            ->firstOrFail();

        do {
            $token = Str::random(48);
        } while (Customer::query()->where('balance_public_token', $token)->exists());

        $customer->balance_public_token = $token;
        $customer->save();

        $base = rtrim((string) config('app.url'), '/');

        return response()->json([
            'url' => $base.'/v/'.$token,
            'path' => '/v/'.$token,
        ]);
    }
}
