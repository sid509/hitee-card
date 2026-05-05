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
use App\Http\Controllers\Api\Customer\MiscController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\HomepageController;
use App\Http\Controllers\Api\Customer\SupportController;
use App\Http\Controllers\Api\Customer\WalletController;
use App\Http\Controllers\Api\Customer\TapController;

// 1. Public Homepage & Search (No Auth Required)
Route::group([], function () {
    Route::get('/banners',      [BannerController::class, 'index']);
    Route::get('/buses',        [HomepageController::class, 'listBuses']);
    Route::get('/buses/{id}',   [HomepageController::class, 'showBus']);
    Route::get('/parkings',     [HomepageController::class, 'listParkings']);
    Route::get('/parkings/{id}', [HomepageController::class, 'showParking']);
});

// 2. Customer Authentication
Route::controller(AuthController::class)->prefix('auth')->group(function () {
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

    // Tap Handling
    Route::post('/tap', [TapController::class, 'processTap']);
});


/*
|--------------------------------------------------------------------------
| Merchant App APIs
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\Merchant\AuthController as MerchantAuthController;
use App\Http\Controllers\Api\Merchant\DashboardController as MerchantDashboardController;
use App\Http\Controllers\Api\Merchant\BusController as MerchantBusController;
use App\Http\Controllers\Api\Merchant\ProfileController as MerchantProfileController;
use App\Http\Controllers\Api\Merchant\SupportController as MerchantSupportController;

Route::prefix('merchant')->group(function () {
    // 1. Merchant Authentication
    Route::post('/login', [MerchantAuthController::class, 'login']);
    Route::post('/logout', [MerchantAuthController::class, 'logout'])->middleware('auth:sanctum');

    // 2. Protected Merchant Routes
    Route::middleware(['auth:sanctum'])->group(function () {
        // Dashboard & Stats
        Route::get('/dashboard/income',     [MerchantDashboardController::class, 'income']);
        Route::get('/dashboard/top-routes', [MerchantDashboardController::class, 'topRoutes']);
        Route::get('/dashboard/withdrawals', [MerchantDashboardController::class, 'withdrawals']);
        Route::get('/dashboard/arrivals',   [MerchantDashboardController::class, 'nearbyArrivals']);

        // Fleet Management (Buses)
        Route::get('/buses', [MerchantBusController::class, 'index']);
        Route::get('/buses/{id}', [MerchantBusController::class, 'show']);

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

