<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Authentication API Routes
 */
Route::controller(AuthController::class)->prefix('auth')->group(function () {
    Route::post('/register', 'register');
    Route::post('/login', 'login');
    Route::post('/social', 'socialLogin');
    
    // Authenticated Auth Routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', 'logout');
        Route::post('/refresh', 'refresh');
    });
});

/**
 * Protected User Routes
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
});

/**
 * Public Data Routes
 */
Route::get('/nearby/buses', [SearchController::class, 'nearbyBuses']);
Route::get('/nearby/parkings', [SearchController::class, 'nearbyParkings']);
