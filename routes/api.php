<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\InstanceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/instance', InstanceController::class)->name('instance');

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:login', 'json.body'])
        ->name('auth.login');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/transactions', [TransactionController::class, 'index'])->middleware('abilities:view')->name('transactions.index');
        Route::post('/transactions', [TransactionController::class, 'store'])->middleware(['abilities:create', 'json.body'])->name('transactions.store');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->middleware('abilities:view')->name('transactions.show');
        Route::patch('/transactions/{transaction}', [TransactionController::class, 'update'])->middleware(['abilities:update', 'json.body'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->middleware('abilities:delete')->name('transactions.destroy');

        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

        Route::get('/categories', [CategoryController::class, 'index'])->middleware('abilities:view')->name('categories.index');
        Route::post('/categories', [CategoryController::class, 'store'])->middleware(['abilities:create', 'json.body'])->name('categories.store');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('abilities:view')->name('categories.show');
        Route::patch('/categories/{category}', [CategoryController::class, 'update'])->middleware(['abilities:update', 'json.body'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->middleware('abilities:delete')->name('categories.destroy');
    });
});
