<?php

namespace App\Http\Controllers\Api;

use App\Actions\Supplier\RecordSupplierLedgerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierTransactionRequest;
use Illuminate\Http\JsonResponse;

class SupplierTransactionController extends Controller
{
    public function store(StoreSupplierTransactionRequest $request, RecordSupplierLedgerAction $action): JsonResponse
    {
        $v = $request->validated();

        $tx = $action->execute($request->user(), (int) $v['supplier_id'], [
            'amount_sen' => (int) $v['amount_sen'],
            'type' => (string) $v['type'],
            'note' => $v['note'] ?? null,
            'recorded_at' => $v['recorded_at'] ?? null,
        ]);

        return response()->json($tx, 201);
    }
}
