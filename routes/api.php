<?php

use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\DepartmentController;
use App\Http\Controllers\v1\InstitutionController;
use App\Http\Controllers\v1\PermissionController;
use App\Http\Controllers\v1\RoleController;
use App\Http\Controllers\v1\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;




Route::prefix('v1/')->group(function () {

    Route::middleware('auth:sanctum')->get('users/me', function (Request $request) {
        return response()->json($request->user());
    });

    Route::post('users/verify-recipients', [UserController::class, 'verifyRecipients']);


    // Route::get('/users', [UserController::class, 'index']);
    // Route::get('/users/{id}', [UserController::class, 'show']);


    Route::post('login', [AuthController::class, 'login']);
    Route::post('reset-password-request', [AuthController::class, 'resetPasswordRequest']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);


    // Route::prefix('users')->group(function () {
    //     Route::get('/', [UserController::class, 'index']);
    //     Route::get('/{id}', [UserController::class, 'show']);
    //     Route::get('/institute', [UserController::class, 'byInstitute'])->middleware('auth:sanctum');
    //     Route::get('/statistics', [UserController::class, 'userStatistics'])->middleware('auth:sanctum');
    //     Route::get('/roles/{role}', [UserController::class, 'byRole'])->middleware('auth:sanctum');

    // });

    Route::middleware(['auth:sanctum'])->group(function () {


        Route::post('logout', [AuthController::class, 'logout']);

        // group users and prefix by users
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/institute', [UserController::class, 'byInstitute'])->middleware('auth:sanctum');
            Route::get('/statistics', [UserController::class, 'userStatistics'])->middleware('auth:sanctum');
            Route::get('/roles/{role}', [UserController::class, 'byRole'])->middleware('auth:sanctum');
            Route::get('/{id}', [UserController::class, 'show']);
        });

        Route::prefix('institutes')->group(function () {
            Route::get('/', [InstitutionController::class, 'index']);
            Route::get('/{id}', [InstitutionController::class, 'show']);
            Route::post('/', [InstitutionController::class, 'store']);
            Route::put('/{id}', [InstitutionController::class, 'update']);
            Route::delete('/{id}', [InstitutionController::class, 'destroy']);
        });

        Route::prefix('departments')->group(function () {
            Route::get('/', [DepartmentController::class, 'index']);
            Route::get('/{id}', [DepartmentController::class, 'show']);
            Route::post('/', [DepartmentController::class, 'store']);
            Route::put('/{id}', [DepartmentController::class, 'update']);
            Route::delete('/{id}', [DepartmentController::class, 'destroy']);
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::get('/{role}', [RoleController::class, 'show']);
            Route::post('/', [RoleController::class, 'store']);
            Route::put('/{role}', [RoleController::class, 'update']); // Changed {id} to {role}
            Route::delete('/{role}', [RoleController::class, 'destroy']); // Changed {id} to {role}
        });

        Route::prefix('permissions')->group(function () {
            Route::get('/', [PermissionController::class, 'index']);
            Route::get('/{id}', [PermissionController::class, 'show']);
            Route::post('/', [PermissionController::class, 'store']);
            Route::put('/{permission}', [PermissionController::class, 'update']);
            Route::delete('/{permission}', [PermissionController::class, 'destroy']);
        });

        Route::get('/users/statistics', [UserController::class, 'userStatistics']);
        Route::get('/departments/statistics', [DepartmentController::class, 'statistics']);
    });
});
