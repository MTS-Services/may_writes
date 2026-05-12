<?php

use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminFileController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::inertia('/', 'welcome', [
//     'canRegister' => Features::enabled(Features::registration()),
// ])->name('home');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::post('/checkout', [CheckoutController::class, 'createSession'])->name('checkout.create');
Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
Route::get('/plans', [CheckoutController::class, 'getPlans'])->name('plans.index');
Route::post('/webhook/stripe', [WebhookController::class, 'handleStripe'])->name('webhook.stripe');
Route::match(['head', 'post'], '/webhook/trello', [WebhookController::class, 'handleTrello'])->name('webhook.trello');
Route::redirect('/admin', '/admin/dashboard');

Route::middleware(['auth', 'admin'])->get('/dashboard', fn () => redirect()->route('admin.dashboard'))->name('dashboard');

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
    Route::get('/files', [AdminFileController::class, 'index'])->name('files');
    Route::get('/files/{task}/download', [AdminFileController::class, 'download'])->name('files.download');
    Route::get('/settings', [AdminProfileController::class, 'edit'])->name('settings');
    Route::patch('/settings', [AdminProfileController::class, 'update'])->name('settings.update');
    Route::put('/settings/password', [AdminProfileController::class, 'updatePassword'])->name('settings.password');
    Route::get('/logs', function () {
        $logFile = storage_path('logs/laravel-'.date('Y-m-d').'.log');
        $lines = file_exists($logFile) ? array_slice(file($logFile), -200) : [];

        return Inertia::render('admin/logs', [
            'lines' => array_reverse($lines),
        ]);
    })->name('logs');
});

require __DIR__.'/settings.php';
