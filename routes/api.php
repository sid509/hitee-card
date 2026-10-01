<?php

use Illuminate\Support\Facades\Route;

/**
 * Main API Routes Entry Point
 */

/*
|--------------------------------------------------------------------------
| User/Customer App APIs
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\BannerController;
use App\Http\Controllers\Api\Customer\FareRulesController;
use App\Http\Controllers\Api\Customer\MiscController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\HomepageController;
use App\Http\Controllers\Api\Customer\SupportController;
use App\Http\Controllers\Api\Customer\WalletController;
use App\Http\Controllers\Api\Customer\TapController;
use App\Http\Controllers\Api\TestTapController;
use App\Http\Controllers\CardReaderController;
use App\Http\Controllers\CardManagement\ValidatorTapController;
use App\Http\Controllers\CardManagement\CardRegistrationBridgeController;

// Test API for Tap Toggle — bench-testing endpoints that debit real
// wallets, so they require an authenticated super-admin token.
Route::middleware('auth:sanctum')->group(function () {
    Route::match(['get', 'post'], '/test-tap', [TestTapController::class, 'handleTestTap']);
    Route::match(['get', 'post'], '/gps', [TestTapController::class, 'handleTestTap']);
});

// Card Reader API — for external scripts / Android apps.
// Authenticated + throttled: card enrollment and UID lookup expose card data,
// so they must not be callable anonymously.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('reader')->group(function () {
    Route::post('/enroll', [CardReaderController::class, 'enrollStore']);
    Route::get('/check-uid', [CardReaderController::class, 'checkUid']);
});

// 1. Public Homepage & Search (No Auth Required)
Route::group([], function () {
    Route::get('/banners',      [BannerController::class, 'index']);
    Route::get('/buses',        [HomepageController::class, 'listBuses']);
    Route::get('/buses/{id}',   [HomepageController::class, 'showBus']);
    Route::get('/parkings',     [HomepageController::class, 'listParkings']);
    Route::get('/parkings/{id}', [HomepageController::class, 'showParking']);

    // Tap Handling (Public for Validators)
    Route::post('/tap', [TapController::class, 'processTap'])->middleware('throttle:300,1');
});

// 2. Customer Authentication — throttled against credential/OTP abuse
Route::controller(AuthController::class)->prefix('auth')
    ->middleware('throttle:10,1')->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/biometric-login', 'biometricLogin');
    Route::post('/social', 'socialLogin');
    Route::post('/forgot-password', 'forgotPassword');
    Route::post('/reset-password',  'resetPassword');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
});

// 3. Protected Customer Routes
Route::middleware('auth:sanctum')->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/image', [ProfileController::class, 'updateImage']);
    Route::post('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::get('/profile/rides', [ProfileController::class, 'rides']);
    Route::get('/profile/taps',  [ProfileController::class, 'taps']);
    Route::post('/profile/language',      [ProfileController::class, 'updateLanguage']);
    Route::post('/profile/notifications', [ProfileController::class, 'updateNotification']);
    Route::get('/profile/status',         [ProfileController::class, 'status']);
    Route::post('/profile/kyc',           [ProfileController::class, 'submitKyc']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/',              [\App\Http\Controllers\Api\Customer\NotificationController::class, 'index']);
        Route::get('/unseen-count',  [\App\Http\Controllers\Api\Customer\NotificationController::class, 'unseenCount']);
        Route::post('/{id}/mark-seen', [\App\Http\Controllers\Api\Customer\NotificationController::class, 'markSeen']);
        Route::post('/mark-all-seen', [\App\Http\Controllers\Api\Customer\NotificationController::class, 'markAllSeen']);
    });

    // Card Management
    Route::prefix('cards')->group(function () {
        Route::post('/link', [\App\Http\Controllers\Api\Customer\CardController::class, 'link']);
    });

    // Support
    Route::get('/support', [SupportController::class, 'index']);
    Route::post('/support', [SupportController::class, 'store']);

    // Wallet Group
    Route::prefix('wallet')->group(function () {
        Route::get('/',             [WalletController::class, 'show']);
        Route::post('/topup',       [WalletController::class, 'topup']);
        Route::get('/categories',   [WalletController::class, 'categories']);
        Route::get('/transactions', [WalletController::class, 'transactions']);
    });

    // Misc Group
    Route::prefix('misc')->group(function () {
        Route::get('/stops',            [MiscController::class, 'searchStops']);
        Route::get('/route-finder',     [MiscController::class, 'routeFinder']);
        Route::get('/nearby',           [MiscController::class, 'nearby']);
    });

    // Card Page Stats
    Route::get('/card/stats', [\App\Http\Controllers\CardPageController::class, 'index']);
});


/*
|--------------------------------------------------------------------------
| Merchant App APIs
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\Merchant\AuthController as MerchantAuthController;
use App\Http\Controllers\Api\Merchant\DashboardController as MerchantDashboardController;
use App\Http\Controllers\Api\Merchant\BusController as MerchantBusController;
use App\Http\Controllers\Api\Merchant\ParkingController as MerchantParkingController;
use App\Http\Controllers\Api\Merchant\BannerController as MerchantBannerController;
use App\Http\Controllers\Api\Merchant\ProfileController as MerchantProfileController;
use App\Http\Controllers\Api\Merchant\SupportController as MerchantSupportController;
Route::prefix('merchant')->group(function () {
    // 1. Merchant Authentication
    Route::post('/login', [MerchantAuthController::class, 'login']);
    Route::post('/biometric-login', [MerchantAuthController::class, 'biometricLogin']);
    Route::post('/social', [MerchantAuthController::class, 'socialLogin']);
    Route::post('/logout', [MerchantAuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/forgot-password', [MerchantAuthController::class, 'forgotPassword']);
    Route::post('/reset-password',  [MerchantAuthController::class, 'resetPassword']);

    // 2. Banners (Public/Static)
    Route::get('/banners', [MerchantBannerController::class, 'index']);

    // 3. Protected Merchant Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/refresh', [MerchantAuthController::class, 'refresh']);

        // Dashboard & Stats
        Route::get('/dashboard/income',     [MerchantDashboardController::class, 'income']);
        Route::get('/dashboard/top-routes', [MerchantDashboardController::class, 'topRoutes']);
        Route::get('/dashboard/top-parkings', [MerchantDashboardController::class, 'topParkings']);
        Route::get('/dashboard/withdrawals', [MerchantDashboardController::class, 'withdrawals']);
        Route::get('/dashboard/arrivals',   [MerchantDashboardController::class, 'nearbyArrivals']);

        // Fleet Management (Buses)
        Route::get('/buses', [MerchantBusController::class, 'index']);
        Route::get('/buses/{id}', [MerchantBusController::class, 'show']);

        // Parking Management
        Route::get('/parkings', [MerchantParkingController::class, 'index']);
        Route::get('/parkings/{id}', [MerchantParkingController::class, 'show']);

        // Profile & Settings
        Route::get('/profile', [MerchantProfileController::class, 'show']);
        Route::put('/profile', [MerchantProfileController::class, 'update']);
        Route::post('/profile/image', [MerchantProfileController::class, 'updateImage']);
        Route::post('/profile/password', [MerchantProfileController::class, 'updatePassword']);
        Route::post('/profile/language', [MerchantProfileController::class, 'updateLanguage']);
        Route::post('/profile/notifications', [MerchantProfileController::class, 'updateNotification']);

        // Support
        Route::get('/support', [MerchantSupportController::class, 'index']);
        Route::post('/support', [MerchantSupportController::class, 'store']);
    });
});

/*
|--------------------------------------------------------------------------
| Fare Rules Engine API (Phase 15, 35)
|--------------------------------------------------------------------------
*/
// Public sync endpoint for validators (authenticated via device token)
Route::prefix('v1')->group(function () {
    Route::get('fare-rules', [FareRulesController::class, 'index']);
    Route::get('fare-rules/sync', [FareRulesController::class, 'sync']);
    Route::get('fare-rules/{id}', [FareRulesController::class, 'show']);
    // Validator tap processing (server-side wallet) — ADR 0013.
    // Device-token authenticated: validators send the Bearer token issued
    // at device registration.
    Route::post('validator/tap', [ValidatorTapController::class, 'processTap'])
        ->middleware([\App\Http\Middleware\CardManagement\ValidatorDeviceAuth::class, 'throttle:300,1']);

    // Write/monitoring endpoints require the workstation bearer token
    // (CardManagementAuth — enforced whenever card_management.auth.required is
    // true, which is the default; set CM_AUTH_REQUIRED=false only for local dev/tests).
    Route::middleware([\App\Http\Middleware\CardManagement\CardManagementAuth::class])->group(function () {
        Route::post('fare-rules', [FareRulesController::class, 'store']);
        Route::put('fare-rules/{id}', [FareRulesController::class, 'update']);
        Route::delete('fare-rules/{id}', [FareRulesController::class, 'destroy']);

        // Validator tap ledger — read-only JSON for monitoring taps
        Route::get('validator/taps', [ValidatorTapController::class, 'tapLedger']);

        // Card registration bridge — called by card-management-api
        Route::post('cards/register-bridge', [CardRegistrationBridgeController::class, 'registerCard'])
            ->middleware('throttle:60,1');
    });
});

