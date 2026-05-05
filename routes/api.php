<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\InventoryController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register_limit');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login_limit');
Route::get('/products', [ProductController::class, 'index'])->middleware('throttle:api_general');
Route::get('/products/{id}', [ProductController::class, 'show'])->middleware('throttle:api_general');
Route::get('/categories', [CategoryController::class, 'index'])->middleware('throttle:api_general');
Route::get('/categories/{id}', [CategoryController::class, 'show'])->middleware('throttle:api_general');

// Protected
Route::middleware('auth:api', 'throttle:api_general')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::get('/cart', [CartController::class, 'index'])->middleware('throttle:cart_limit');
    Route::post('/cart', [CartController::class, 'store'])->middleware('throttle:cart_limit');
    Route::put('/cart/{id}', [CartController::class, 'update'])->middleware('throttle:cart_limit');
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/checkout', [OrderController::class, 'checkout'])->middleware('throttle:checkout_process');

    Route::get('/inventory/{productId}', [InventoryController::class, 'show']);

    Route::middleware('admin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        Route::put('/inventory/{productId}', [InventoryController::class, 'update']);
    });
});
Route::get('/test', function() {
    return response()->json(['message' => 'API is working']);
});