<?php

use App\Http\Controllers\Api\AccountDataController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CurrencyController;
use Illuminate\Support\Facades\Route;

Route::get('/currencies', [CurrencyController::class, 'currencies']);
Route::get('/rates', [CurrencyController::class, 'rates']);
Route::get('/rates/history', [CurrencyController::class, 'history']);
Route::post('/convert', [CurrencyController::class, 'convert']);
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/conversions', [AccountDataController::class, 'conversions']);
    Route::delete('/conversions/{id}', [AccountDataController::class, 'deleteConversion']);
    Route::get('/favorites', [AccountDataController::class, 'favorites']);
    Route::post('/favorites', [AccountDataController::class, 'saveFavorite']);
    Route::delete('/favorites/{id}', [AccountDataController::class, 'deleteFavorite']);
    Route::get('/alerts', [AccountDataController::class, 'alerts']);
    Route::post('/alerts', [AccountDataController::class, 'saveAlert']);
    Route::put('/alerts/{id}', [AccountDataController::class, 'updateAlert']);
    Route::delete('/alerts/{id}', [AccountDataController::class, 'deleteAlert']);
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::put('/admin/users/{id}', [AdminController::class, 'updateUser']);
    Route::get('/admin/currencies', [AdminController::class, 'currencies']);
    Route::put('/admin/currencies/{id}', [AdminController::class, 'updateCurrency']);
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::get('/admin/logs', [AdminController::class, 'logs']);
});
