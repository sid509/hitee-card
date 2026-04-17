<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\LangController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\BusController;
use App\Http\Controllers\ParkingController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportController;
use App\Models\User;
use App\Models\Bus;
use App\Models\Parking;
use App\Models\Card;
use Illuminate\Support\Facades\Route;

/**
 * Public Routes
 */
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication Routes
Route::controller(LoginController::class)->group(function () {
    Route::get('/login', 'showLoginForm')->name('login');
    Route::post('/login', 'login');
    Route::post('/logout', 'logout')->name('logout');
});

Route::controller(RegisterController::class)->group(function () {
    Route::get('/register', 'showRegistrationForm')->name('register');
    Route::post('/register', 'register');
});

// Forgot Password Routes
Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->name('password.request');

Route::post('/forgot-password', function () {
    // Placeholder for actual logic
    return back()->with('status', 'We have emailed your password reset link!');
})->name('password.email');

// Utility Routes
Route::get('/lang/{lang}', [LangController::class, 'switch'])->name('lang.switch');
Route::get('/theme/toggle', [ThemeController::class, 'toggle'])->name('theme.toggle');

/**
 * Authenticated Routes
 */
Route::middleware(['auth'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', function () {
        $userCount = User::count();
        $busCount = Bus::count();
        $parkingCount = Parking::count();
        $cardCount = Card::count();
        return view('dashboard', compact('userCount', 'busCount', 'parkingCount', 'cardCount'));
    })->name('dashboard');

    // Profile Management
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'show')->name('profile.show');
        Route::put('/profile', 'update')->name('profile.update');
        Route::put('/profile/password', 'password')->name('profile.password');
    });

    // Administration (Super Admin Only)
    Route::middleware(['role:super-admin'])->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class);
        Route::resource('permissions', PermissionController::class);
        
        // Support Management
        Route::controller(SupportController::class)->group(function () {
            Route::get('/supports', 'index')->name('supports.index');
            Route::get('/supports/{support}', 'show')->name('supports.show');
            Route::post('/supports/{support}/close', 'close')->name('supports.close');
        });

        // User Impersonation
        Route::get('/impersonate/take/{id}', function($id) {
            auth()->user()->impersonate(User::findOrFail($id));
            return redirect()->route('dashboard');
        })->name('impersonate');
    });

    // Stop Impersonation
    Route::get('/impersonate/leave', function() {
        auth()->user()->leaveImpersonation();
        return redirect()->route('users.index');
    })->name('impersonate.leave');

    // Business Logic Resources
    Route::resource('buses', BusController::class);
    Route::resource('parkings', ParkingController::class);
    Route::resource('cards', CardController::class);

    // Support
    Route::post('/support/send', [SupportController::class, 'send'])->name('support.send');

    // Search
    Route::controller(\App\Http\Controllers\SearchController::class)->group(function () {
        Route::get('/search/users', 'users')->name('search.users');
        Route::get('/search/merchants', 'merchants')->name('search.merchants');
        Route::get('/search/references', 'references')->name('search.references');
    });

    // Transaction Management
    Route::controller(\App\Http\Controllers\BalanceController::class)->group(function () {
        Route::get('/transactions/logs/{userId?}', 'logs')->name('transactions.logs');
        Route::post('/transactions/manual-add', 'manualAdd')->name('transactions.manual-add');
        Route::post('/transactions/manual-deduct', 'manualDeduct')->name('transactions.manual-deduct');
        Route::post('/transactions/khalti-payment', 'khaltiPayment')->name('khalti.payment');
        Route::get('/transactions/khalti-verify', 'khaltiVerify')->name('khalti.verify');

        // Merchant specific
        Route::get('/merchant/income', 'merchantTransactions')->name('merchant.income');
        Route::get('/merchant/withdrawals', 'merchantWithdrawals')->name('merchant.withdrawals');
        Route::post('/merchant/withdraw', 'merchantWithdraw')->name('merchant.withdraw');
    });
});

/**
 * Social Authentication
 */
Route::controller(SocialAuthController::class)->group(function () {
    Route::get('/auth/{provider}', 'redirect')->name('social.redirect');
    Route::get('/auth/{provider}/callback', 'callback')->name('social.callback');
});
