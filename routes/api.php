<?php

use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::prefix('v1/')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('reset-password-request', [AuthController::class, 'resetPasswordRequest']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

     Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        // group users and prefix by users
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/institute', [UserController::class, 'byInstitute']);
            Route::get('/statistics', [UserController::class, 'userStatistics']);
            Route::get('/roles/{role}', [UserController::class, 'byRole']);
            Route::get('/{id}', [UserController::class, 'show']);
        });



    });


});

