<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Support\InvoicePdfHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SupplierPdfController extends Controller
{
    private function currencyCodeFromShop(Shop $shop): string
    {
        $c = strtoupper((string) ($shop->primary_currency_code ?? 'MYR'));

        return $c !== '' ? $c : 'MYR';
    }

    /** Strip control chars and cap length so DomPDF does not choke on odd UTF-8. */
    private function plainText(?string $note, int $max = 800): string
    {
        if ($note === null || $note === '') {
            return '';
        }
        $s = html_entity_decode($note, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';

        return Str::limit($s, $max);
    }

    /**
     * @return list<string>
     */
    private function plainTextLines(?string $blob, int $maxLine = 500): array
    {
        $t = trim($this->plainText($blob, $maxLine * 20));
        if ($t === '') {
            return [];
        }
        $parts = preg_split('/\r\n|\r|\n/', $t) ?: [];
        $lines = [];
        foreach ($parts as $p) {
            $line = trim($p);
            if ($line === '') {
                continue;
            }
            $lines[] = Str::limit($line, $maxLine);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function outputPdf(string $view, array $data, string $filename): Response
    {
        try {
            @ini_set('memory_limit', '256M');

            $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (\Throwable $e) {
            Log::error('PDF generation failed', [
                'view' => $view,
                'message' => $e->getMessage(),
            ]);

            abort(500, 'Unable to generate PDF. Please try again.');
        }
    }

    /** A4 memo for a single purchase transaction (shop payable increases). */
    public function purchase(Request $request, int $supplierId, int $transactionId): Response
    {
        $shop = $request->user()?->shops()->firstOrFail();
        $currency = $this->currencyCodeFromShop($shop);

        $supplier = Supplier::query()
            ->where('shop_id', $shop->id)
            ->whereKey($supplierId)
            ->firstOrFail();

        $transaction = SupplierTransaction::query()
            ->where('shop_id', $shop->id)
            ->where('supplier_id', $supplier->id)
            ->whereKey($transactionId)
            ->firstOrFail();

        if ($transaction->type !== SupplierTransaction::TYPE_PURCHASE) {
            abort(422, 'Only purchase transactions can generate this memo.');
        }

        $supplier->refresh();

        $amountSen = (int) $transaction->amount_sen;
        $balanceSen = (int) $supplier->balance_sen;

        $memoNo = sprintf('U%d-T%d', $supplier->id, $transaction->id);
        $tz = (string) config('app.timezone', 'UTC');
        $docAt = $transaction->created_at?->timezone($tz) ?? now()->timezone($tz);
        $formatMoney = static fn (int $sen): string => number_format(abs($sen) / 100, 2);

        $amountWords = InvoicePdfHelper::amountSenToWordsUpper(abs((int) $amountSen));

        $txnNote = $this->plainText($transaction->note);

        $itemCode = '—';
        $descLines = ['PURCHASE / STOCK RECORDED AGAINST PAYABLE (AMOUNT WE OWE THE SUPPLIER)'];
        if ($txnNote !== '') {
            $descLines[] = 'NOTE: '.$txnNote;
        }
        $descriptionHtml = implode('<br>', array_map(
            static fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $descLines,
        ));

        return $this->outputPdf('pdf.supplier-purchase-memo', [
            'shopName' => (string) $shop->name,
            'shopLocationLines' => $this->plainTextLines($shop->location, 500),
            'shopContact' => $this->plainText($shop->contact, 120),
            'paymentInstructionsLines' => $this->plainTextLines($shop->payment_instructions, 520),
            'currencyCode' => $currency,
            'supplier' => $supplier,
            'supplierRef' => 'U'.$supplier->id,
            'memoNo' => $memoNo,
            'docDate' => InvoicePdfHelper::docDateDmY($docAt),
            'issuedAt' => now()->timezone($tz)->format('Y-m-d H:i'),
            'itemCode' => $itemCode,
            'descriptionHtml' => $descriptionHtml !== '' ? $descriptionHtml : 'PURCHASE RECORDED AGAINST PAYABLE',
            'qtyFormatted' => '1',
            'uom' => '',
            'unitPriceFormatted' => $formatMoney($amountSen),
            'discountFormatted' => $formatMoney(0),
            'amountFormatted' => $formatMoney($amountSen),
            'totalDocumentFormatted' => $formatMoney($amountSen),
            'balanceAfterFormatted' => $formatMoney((int) $balanceSen),
            'amountWords' => $amountWords,
            'pageLabel' => '1 OF 1',
        ], 'bakimate-supplier-purchase-'.$supplier->id.'-'.$transaction->id.'.pdf');
    }

    /** A4 voucher for a payment-out transaction (money paid to the supplier). */
    public function paymentOut(Request $request, int $supplierId, int $transactionId): Response
    {
        $shop = $request->user()?->shops()->firstOrFail();
        $currency = $this->currencyCodeFromShop($shop);

        $supplier = Supplier::query()
            ->where('shop_id', $shop->id)
            ->whereKey($supplierId)
            ->firstOrFail();

        $transaction = SupplierTransaction::query()
            ->where('shop_id', $shop->id)
            ->where('supplier_id', $supplier->id)
            ->whereKey($transactionId)
            ->firstOrFail();

        if ($transaction->type !== SupplierTransaction::TYPE_PAYMENT_OUT) {
            abort(422, 'Only payment-out transactions can generate this voucher.');
        }

        $supplier->refresh();

        $amountSen = (int) $transaction->amount_sen;
        $balanceSen = (int) $supplier->balance_sen;

        $voucherNo = sprintf('UO%d-T%d', $supplier->id, $transaction->id);
        $tz = (string) config('app.timezone', 'UTC');
        $docAt = $transaction->created_at?->timezone($tz) ?? now()->timezone($tz);
        $formatMoney = static fn (int $sen): string => number_format(abs($sen) / 100, 2);

        $amountWords = InvoicePdfHelper::amountSenToWordsUpper(abs((int) $amountSen));

        $itemCode = '—';
        $txnNote = $this->plainText($transaction->note);
        $descLines = ['PAYMENT MADE TO SUPPLIER (REDUCES OUTSTANDING PAYABLE)'];
        if ($txnNote !== '') {
            $descLines[] = 'NOTE: '.$txnNote;
        }
        $descriptionHtml = implode('<br>', array_map(
            static fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $descLines,
        ));

        return $this->outputPdf('pdf.supplier-payment-voucher', [
            'shopName' => (string) $shop->name,
            'shopLocationLines' => $this->plainTextLines($shop->location, 500),
            'shopContact' => $this->plainText($shop->contact, 120),
            'paymentInstructionsLines' => $this->plainTextLines($shop->payment_instructions, 520),
            'currencyCode' => $currency,
            'supplier' => $supplier,
            'supplierRef' => 'U'.$supplier->id,
            'voucherNo' => $voucherNo,
            'docDate' => InvoicePdfHelper::docDateDmY($docAt),
            'issuedAt' => now()->timezone($tz)->format('Y-m-d H:i'),
            'itemCode' => $itemCode,
            'descriptionHtml' => $descriptionHtml !== '' ? $descriptionHtml : 'PAYMENT MADE TO SUPPLIER',
            'qtyFormatted' => '1',
            'uom' => '',
            'unitPriceFormatted' => $formatMoney($amountSen),
            'discountFormatted' => $formatMoney(0),
            'amountFormatted' => $formatMoney($amountSen),
            'totalDocumentFormatted' => $formatMoney($amountSen),
            'balanceAfterFormatted' => $formatMoney((int) $balanceSen),
            'amountWords' => $amountWords,
            'pageLabel' => '1 OF 1',
        ], 'bakimate-supplier-payment-'.$supplier->id.'-'.$transaction->id.'.pdf');
    }
}
