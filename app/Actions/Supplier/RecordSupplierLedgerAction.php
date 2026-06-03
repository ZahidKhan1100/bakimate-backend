<?php

namespace App\Actions\Supplier;

use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Models\User;
use App\Services\SupplierBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RecordSupplierLedgerAction
{
    public function __construct(
        private readonly SupplierBalanceService $balances,
    ) {}

    /**
     * @param  array{type: string, amount_sen: int, note?: ?string}  $data
     */
    public function execute(User $user, int $supplierId, array $data): SupplierTransaction
    {
        $shop = $user->shops()->firstOrFail();
        /** @var Supplier $supplier */
        $supplier = Supplier::query()
            ->where('shop_id', $shop->id)
            ->whereKey($supplierId)
            ->firstOrFail();

        return DB::transaction(function () use ($shop, $supplier, $data) {
            $recordedAt = isset($data['recorded_at']) && $data['recorded_at'] !== null && $data['recorded_at'] !== ''
                ? CarbonImmutable::parse((string) $data['recorded_at'])->startOfDay()
                : null;

            $tx = new SupplierTransaction([
                'shop_id' => $shop->id,
                'supplier_id' => $supplier->id,
                'amount_sen' => $data['amount_sen'],
                'type' => $data['type'],
                'note' => isset($data['note']) ? (trim((string) $data['note']) ?: null) : null,
            ]);

            if ($recordedAt !== null) {
                $tx->created_at = $recordedAt;
                $tx->updated_at = $recordedAt;
            }

            $tx->save();
            $this->balances->syncCachedBalance($supplier->fresh());

            return $tx->fresh();
        });
    }
}
