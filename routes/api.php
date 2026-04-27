<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\MiscController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\HomepageController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TapController;
use App\Http\Controllers\Api\UserActivityController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Authentication API Routes
 */
Route::controller(AuthController::class)->prefix('auth')->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/social', 'socialLogin');
    Route::post('/forgot-password', 'forgotPassword');
    Route::post('/reset-password',  'resetPassword');

    // Authenticated Auth Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', 'logout');
        Route::post('/refresh', 'refresh');
    });
});

/**
 * Protected User Routes
 * All routes below require a valid Sanctum access token.
 * Data is scoped to the authenticated user only.
 */
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
    });

    // Admin-only routes (inline role check inside controllers)
    Route::prefix('admin')->group(function () {
        Route::put('/banners/{position}', [BannerController::class, 'update']);
    });
});

/**
 * Homepage & Search Routes
 * Features used on the mobile app's main dashboard.
 */
Route::get('/banners', [BannerController::class, 'index']);

// Buses
Route::get('/buses',       [HomepageController::class, 'listBuses']);
Route::get('/buses/{id}',  [HomepageController::class, 'showBus']);

// Parkings
Route::get('/parkings',      [HomepageController::class, 'listParkings']);
Route::get('/parkings/{id}', [HomepageController::class, 'showParking']);
