<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\VkWallPostController;
use App\Http\Middleware\AdminAuthMiddleware;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('/offer', [OfferController::class, 'offer'])->middleware(ApiKeyMiddleware::class);

Route::get('/login', [AuthController::class, 'loginForm'])->name('login.form');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware(AdminAuthMiddleware::class)->group(function () {
    Route::get('/', [OfferController::class, 'index']);
    Route::get('/agents', [AgentController::class, 'list']);
    Route::get('/agents/create', [AgentController::class, 'create']);
    Route::post('/agents', [AgentController::class, 'store']);
    Route::get('/agents/edit/{id}', [AgentController::class, 'edit']);
    Route::post('/agents/save/{id}', [AgentController::class, 'save']);
    Route::post('/agents/delete/{id}', [AgentController::class, 'delete']);
    Route::get('/agents/change-token/{id}', [AgentController::class, 'changeToken']);
    Route::post('/agents/update-token/{id}', [AgentController::class, 'updateToken']);
    Route::post('/agents/token-permissions/{id}', [AgentController::class, 'getTokenPermissions']);
    Route::get('/posts', [VkWallPostController::class, 'index']);
});
