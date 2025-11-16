<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('debts', App\Http\Controllers\DebtController::class);
    Route::resource('expenses', App\Http\Controllers\ExpenseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('expenses/analyze-receipt', [App\Http\Controllers\ExpenseController::class, 'analyzeReceipt'])->name('expenses.analyze-receipt');
    Route::resource('debts.payments', App\Http\Controllers\PaymentController::class);
});

require __DIR__.'/settings.php';
