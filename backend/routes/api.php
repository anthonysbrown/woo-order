<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:8,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:12,1');
});

Route::get('/restaurants', [RestaurantController::class, 'index']);
Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show']);
Route::get('/restaurants/{restaurant}/menu-items', [MenuItemController::class, 'index']);

Route::middleware('jwt.auth')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::prefix('cart')->middleware(['role:customer', 'throttle:60,1'])->group(function (): void {
        Route::get('/', [CartController::class, 'show']);
        Route::post('/items', [CartController::class, 'addItem']);
        Route::patch('/items/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/items/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/', [CartController::class, 'clear']);
    });

    Route::get('/orders', [OrderController::class, 'index'])->middleware('throttle:120,1');
    Route::post('/orders', [OrderController::class, 'store'])->middleware(['role:customer', 'throttle:30,1']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware(['role:restaurant_owner,admin', 'throttle:60,1']);
    Route::get('/orders/{order}/track', [OrderController::class, 'track']);

    Route::prefix('owner')->middleware('role:restaurant_owner')->group(function (): void {
        Route::post('/restaurants/{restaurant}/menu-items', [MenuItemController::class, 'store']);
        Route::patch('/menu-items/{menuItem}', [MenuItemController::class, 'update']);
        Route::delete('/menu-items/{menuItem}', [MenuItemController::class, 'destroy']);
    });

    Route::prefix('admin')->middleware('role:admin')->group(function (): void {
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/restaurants', [AdminController::class, 'restaurants']);
        Route::patch('/restaurants/{restaurant}/active', [AdminController::class, 'updateRestaurantStatus']);
        Route::get('/activity', [ActivityController::class, 'index']);
    });
});
