<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Credit invoice — {{ $customer->name }}</title>
    @include('pdf.partials.so-form-styles')
</head>
<body>
    @php /** @var \App\Models\Customer $customer */ @endphp
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="width:58%;vertical-align:top;">
                <div class="seller-name">{{ strtoupper((string) $shopName) }}</div>
                <div class="muted">
                    @foreach($shopLocationLines as $line)
                        {{ $line }}<br>
                    @endforeach
                    @if($shopContact !== '')
                        TEL:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: {{ $shopContact }}<br>
                    @endif
                </div>
            </td>
            <td style="width:42%;vertical-align:top;">
                <table class="meta-table" style="margin-top:0;">
                    <tr>
                        <td colspan="2" style="font-size:15px;font-weight:bold;text-transform:uppercase;text-align:right;padding-bottom:4px;">
                            CREDIT INVOICE / MEMO
                        </td>
                    </tr>
                    <tr>
                        <td class="meta-label r">DOCUMENT NO.&nbsp;&nbsp;</td>
                        <td class="meta-val"><strong>{{ $invoiceNo }}</strong></td>
                    </tr>
                    <tr>
                        <td class="meta-label r">DATE&nbsp;&nbsp;</td>
                        <td class="meta-val">{{ $docDate }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label r">PAGE&nbsp;&nbsp;</td>
                        <td class="meta-val upper">{{ $pageLabel ?? '1 OF 1' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label r">CURRENCY&nbsp;&nbsp;</td>
                        <td class="meta-val">{{ $currencyCode }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label r">CUSTOMER #&nbsp;&nbsp;</td>
                        <td class="meta-val">{{ $customerRef }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="box" style="margin-top:10px;">
        <div class="box-title upper">Billing / Attention</div>
        <strong>{{ strtoupper((string) $customer->name) }}</strong><br>
        ADDRESS&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: —
        @if($customer->phone)
            <br>TEL.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;: {{ $customer->phone }}
        @endif
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:3%;">NO</th>
                <th style="width:13%;">ITEM CODE</th>
                <th style="width:auto;">DESCRIPTION</th>
                <th style="width:6%;">QTY</th>
                <th style="width:7%;">UOM</th>
                <th style="width:10%;">U.PRICE<br>({{ $currencyCode }})</th>
                <th style="width:10%;">DISCOUNT<br>({{ $currencyCode }})</th>
                <th style="width:10%;">AMOUNT<br>({{ $currencyCode }})</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="c">1</td>
                <td>{{ $itemCode }}</td>
                <td>{!! $descriptionHtml !!}</td>
                <td class="c">{{ $qtyFormatted }}</td>
                <td class="c">{{ $uom !== '' ? $uom : '-' }}</td>
                <td class="r">{{ $unitPriceFormatted }}</td>
                <td class="r">{{ $discountFormatted }}</td>
                <td class="r">{{ $amountFormatted }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td style="width:48%;vertical-align:top;padding-top:0;">
                <div class="words-box">
                    <div class="words-label upper">Ringgit Malaysia Amount in Words:</div>
                    @if(($amountWords ?? '') !== '')
                        <strong class="upper">{{ $amountWords }}</strong>
                    @else
                        <span class="muted">—</span>
                    @endif
                </div>
            </td>
            <td style="width:52%;vertical-align:top;">
                <table class="totals">
                    <tr>
                        <td class="lab r">DOCUMENT TOTAL&nbsp;&nbsp;</td>
                        <td class="r" style="width:38%;"><strong>{{ $totalDocumentFormatted }}</strong></td>
                    </tr>
                    <tr>
                        <td class="lab r">OUTSTANDING BALANCE AFTER CREDIT&nbsp;&nbsp;</td>
                        <td class="r"><strong>{{ $balanceAfterFormatted }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(count($paymentInstructionsLines ?? []) > 0)
        <div class="bank-box muted" style="margin-top:10px;">
            <div class="upper" style="font-weight:bold;margin-bottom:4px;color:#000;">PAYMENT / BANK DETAILS:</div>
            @foreach($paymentInstructionsLines as $pline)
                {{ $pline }}@if(!$loop->last)<br>@endif
            @endforeach
        </div>
    @endif

    <div class="terms muted">
        <strong class="upper" style="color:#000;">TERMS:</strong>
        <ol type="1">
            <li>Amounts expressed in {{ $currencyCode }} excluding any bank or intermediary charges.</li>
            <li>This credit memo relates to ledger activity recorded through BakiMate for {{ $shopName }}.</li>
            <li>Any dispute must be communicated to the issuing shop promptly with reasonable supporting particulars.</li>
            <li>Computer-printed signatures are acknowledged where customary for internal shop records.</li>
        </ol>
        <div class="eoe">E. &amp; O.E.</div>
        <div class="sign-block">
            <div class="sign-for">For&nbsp;&nbsp;<strong>{{ strtoupper((string) $shopName) }}</strong></div>
            <div class="sign-line">AUTHORISED SIGNATURE / SHOP CHOP</div>
        </div>
        <div class="water muted">
            BakiMate · Printed {{ $issuedAt }} {{ config('app.timezone') }} · {{ $invoiceNo }}
        </div>
    </div>
</body>
</html>
