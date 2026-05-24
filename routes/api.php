<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerPdfController;
use App\Http\Controllers\Api\CustomerPromiseController;
use App\Http\Controllers\Api\InsightsController;
use App\Http\Controllers\Api\MonthlyStatementPdfController;
use App\Http\Controllers\Api\OverdueNotificationsController;
use App\Http\Controllers\Api\ReceiptScanController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShopDuitNowQrController;
use App\Http\Controllers\Api\ShopProfileController;
use App\Http\Controllers\Api\SubscriptionWebhookController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SupplierPdfController;
use App\Http\Controllers\Api\SupplierTransactionController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/google', [AuthController::class, 'google']);
Route::post('/auth/apple', [AuthController::class, 'apple']);
Route::post('/auth/demo', [AuthController::class, 'demo']);

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:12,1');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:4,1');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:8,1');
Route::post('/auth/check-email-verified', [AuthController::class, 'checkEmailVerified'])->middleware('throttle:30,1');
Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/shop', [ShopProfileController::class, 'show']);
    Route::patch('/shop', [ShopProfileController::class, 'update']);
    Route::post('/shop/duitnow-qr', [ShopDuitNowQrController::class, 'store']);
    Route::delete('/shop/duitnow-qr', [ShopDuitNowQrController::class, 'destroy']);
    Route::delete('/auth/account', [AuthController::class, 'destroyAccount'])->middleware('throttle:3,1440');

    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customerId}', [CustomerController::class, 'show']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/suppliers/{supplierId}', [SupplierController::class, 'show']);

    Route::get('/reports/summary', [ReportController::class, 'summary']);
    Route::get('/reports/insights', InsightsController::class);
    Route::get('/notifications/overdue', OverdueNotificationsController::class);

    Route::middleware('subscription')->group(function () {
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::patch('/customers/{customerId}', [CustomerController::class, 'update']);
        Route::delete('/customers/{customerId}', [CustomerController::class, 'destroy']);

        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::patch('/suppliers/{supplierId}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{supplierId}', [SupplierController::class, 'destroy']);
        Route::post('/supplier-transactions', [SupplierTransactionController::class, 'store']);

        Route::post('/customers/{customerId}/promises', [CustomerPromiseController::class, 'store']);
        Route::patch('/customers/{customerId}/promises/{promiseId}', [CustomerPromiseController::class, 'update']);

        Route::post('/customers/{customerId}/balance-public-link', [CustomerController::class, 'rotatePublicBalanceLink']);

        Route::post('/transactions', [TransactionController::class, 'store']);
        Route::patch('/transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

        Route::post('/receipt-scan', ReceiptScanController::class);

        Route::get('/customers/{customerId}/documents/ledger', [CustomerPdfController::class, 'ledger']);
        Route::get('/customers/{customerId}/documents/settlement', [CustomerPdfController::class, 'settlement']);
        Route::get('/customers/{customerId}/documents/credit-invoice/{transactionId}', [CustomerPdfController::class, 'creditInvoice']);
        Route::get('/customers/{customerId}/documents/payment-receipt/{transactionId}', [CustomerPdfController::class, 'paymentReceipt']);

        Route::get('/suppliers/{supplierId}/documents/purchase/{transactionId}', [SupplierPdfController::class, 'purchase']);
        Route::get('/suppliers/{supplierId}/documents/payment-out/{transactionId}', [SupplierPdfController::class, 'paymentOut']);

        Route::get('/reports/monthly-statement', MonthlyStatementPdfController::class);
    });
});

Route::post('/webhooks/subscription', [SubscriptionWebhookController::class, 'handle']);
