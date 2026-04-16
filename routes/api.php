<?php

use App\Http\Controllers\Api\AuthController;
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
    });
});

/**
 * Protected User Routes
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return apiResponse(true, 'User profile fetched', $request->user());
    });
});
