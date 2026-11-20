<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AiEstimateController;
use App\Http\Controllers\Api\AppealController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\JobRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\QuoteController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/categories', [CategoryController::class, 'index']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');

    Route::get('/providers', [ProviderController::class, 'index']);
    Route::get('/providers/{provider}', [ProviderController::class, 'show']);
    Route::get('/providers/{provider}/ratings', [BookingController::class, 'providerRatings']);

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/conversations', [ChatController::class, 'index']);
    Route::post('/conversations', [ChatController::class, 'open']);
    Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'storeMessage']);

    Route::get('/estimates', [AiEstimateController::class, 'index']);
    Route::get('/appeals', [AppealController::class, 'index'])->name('appeals.index');
    Route::get('/moderation-actions', [AppealController::class, 'actions'])->name('moderation.mine');

    Route::get('/job-requests', [JobRequestController::class, 'index']);
    Route::get('/job-requests/{jobRequest}', [JobRequestController::class, 'show']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::get('/bookings/{booking}/ledger', [BookingController::class, 'ledger']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/photos', [BookingController::class, 'photo']);
    Route::post('/bookings/{booking}/ratings', [BookingController::class, 'rate']);

    Route::middleware('role:customer,business_client')->group(function () {
        Route::post('/ai/estimate', [AiEstimateController::class, 'store']);
        Route::get('/payments', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'data' => \App\Models\PaymentTransaction::query()->where('user_id', $request->user()->id)->latest()->limit(30)->get(),
            ]);
        });
        Route::post('/job-requests', [JobRequestController::class, 'store']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::post('/quotes/{quote}/accept', [QuoteController::class, 'accept']);
        Route::post('/bookings/{booking}/pay', [BookingController::class, 'pay']);
        Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
        Route::post('/bookings/{booking}/dispute', [DisputeController::class, 'store']);
        Route::post('/bookings/{booking}/no-show', [BookingController::class, 'noShow']);
    });

    Route::middleware('role:provider')->group(function () {
        Route::get('/provider/profile', [ProviderController::class, 'mine']);
        Route::put('/provider/profile', [ProviderController::class, 'update']);
        Route::post('/provider/packages', [ProviderController::class, 'storePackage']);
        Route::put('/provider/packages/{package}', [ProviderController::class, 'updatePackage']);
        Route::post('/provider/photos', [ProviderController::class, 'storePhoto']);
        Route::get('/provider/earnings', [ProviderController::class, 'earnings']);
        Route::post('/job-requests/{jobRequest}/quotes', [JobRequestController::class, 'quote']);
        Route::post('/bookings/{booking}/accept', [BookingController::class, 'accept']);
        Route::post('/bookings/{booking}/start', [BookingController::class, 'start']);
        Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);
        Route::post('/bookings/{booking}/customer-absent', [BookingController::class, 'customerAbsent']);
        Route::post('/disputes/{dispute}/respond', [DisputeController::class, 'respond']);
        Route::post('/provider/appeals', [AppealController::class, 'store'])->name('appeals.store');
    });

    Route::middleware('role:business_client')->prefix('business')->group(function () {
        Route::get('/', [BusinessController::class, 'show']);
        Route::put('/', [BusinessController::class, 'update']);
        Route::post('/sites', [BusinessController::class, 'storeSite']);
        Route::put('/sites/{site}', [BusinessController::class, 'updateSite']);
        Route::post('/wallet/topup', [BusinessController::class, 'topUp']);
        Route::get('/wallet/statement', [BusinessController::class, 'statement']);
        Route::get('/plans', [BusinessController::class, 'plans']);
        Route::post('/plans', [BusinessController::class, 'storePlan']);
        Route::post('/plans/{plan}/confirm', [BusinessController::class, 'confirmPlan']);
        Route::get('/finops', [BusinessController::class, 'finops']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/providers', [AdminController::class, 'providers']);
        Route::post('/providers/{provider}/verify', [AdminController::class, 'verify']);
        Route::post('/providers/{provider}/reject', [AdminController::class, 'reject']);
        Route::post('/providers/{provider}/feature', [AdminController::class, 'feature']);
        Route::get('/disputes', [AdminController::class, 'disputes']);
        Route::post('/disputes/{dispute}/resolve', [AdminController::class, 'resolveDispute']);
        Route::post('/users/{user}/moderate', [AdminController::class, 'moderate']);
        Route::get('/appeals', [AdminController::class, 'appeals']);
        Route::post('/appeals/{appeal}/decide', [AdminController::class, 'decideAppeal']);
        Route::get('/ledger', [AdminController::class, 'ledger']);
        Route::get('/analytics', [AdminController::class, 'analytics']);
        Route::get('/settings', [AdminController::class, 'settings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);
        Route::post('/categories', [AdminController::class, 'storeCategory']);
        Route::put('/categories/{category}', [AdminController::class, 'updateCategory']);
        Route::get('/bookings', [AdminController::class, 'bookings']);
    });
});
