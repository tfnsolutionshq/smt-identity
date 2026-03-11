<?php

use App\Http\Controllers\v1\RolePermissionController;
use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\DepartmentController;
use App\Http\Controllers\v1\InstitutionController;
use App\Http\Controllers\v1\PermissionController;
use App\Http\Controllers\v1\RoleController;
use App\Http\Controllers\v1\AdminAnalyticsController;
use App\Http\Controllers\v1\UserController;
use App\Http\Controllers\v1\S2SController;
use App\Http\Controllers\v1\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;




Route::prefix('v1/')->group(function () {

    Route::post('login', [AuthController::class, 'login']);
    Route::post('reset-password-request', [AuthController::class, 'resetPasswordRequest']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('reset-password-otp', [AuthController::class, 'resetPasswordWithOtp']);
    Route::post('force-change-password', [AuthController::class, 'forceChangePassword']);

    // S2S Authentication Routes
    Route::middleware('s2s')->prefix('s2s')->group(function () {
        Route::post('validate-user', [S2SController::class, 'validateUser']);
        Route::post('users-by-role', [S2SController::class, 'getUsersByRole']);
        Route::post('users-by-department', [S2SController::class, 'getUsersByDepartment']);
        Route::post('user-by-id', [S2SController::class, 'getUserById']);
        Route::post('users-by-ids', [S2SController::class, 'getUsersByIds']);
    });

    Route::middleware('auth:sanctum')->get('users/me', function (Request $request) {
        $user = $request->user()->load(['department', 'role']);
        $userData = $user->toArray();
        $userData['avatar'] = $user->avatar_url;
        return response()->json($userData);
    });

    Route::middleware('auth:sanctum')->get('current-user', [AuthController::class, 'getCurrentUser']);

    Route::post('users/verify-recipients', [UserController::class, 'verifyRecipients']);
    Route::post('users/verify-by-email', [UserController::class, 'verifyByEmail']);


    // Route::get('/users', [UserController::class, 'index']);
    // Route::get('/users/{id}', [UserController::class, 'show']);





    Route::get('test-notification', function() {
        try {
            $baseUrl = rtrim(config('services.notification.base_url', ''), '/');
            $url = "{$baseUrl}/test";

            $response = \Illuminate\Support\Facades\Http::get($url);

            return response()->json([
                'config_url' => config('services.notification.base_url'),
                'final_url' => $url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    });


    // Route::prefix('users')->group(function () {
    //     Route::get('/', [UserController::class, 'index']);
    //     Route::get('/{id}', [UserController::class, 'show']);
    //     Route::get('/institute', [UserController::class, 'byInstitute'])->middleware('auth:sanctum');
    //     Route::get('/statistics', [UserController::class, 'userStatistics'])->middleware('auth:sanctum');
    //     Route::get('/roles/{role}', [UserController::class, 'byRole'])->middleware('auth:sanctum');

    // });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('profile', [ProfileController::class, 'update']);
        Route::post('change-password', [ProfileController::class, 'changePassword']);
        Route::post('register', [AuthController::class, 'register']);
        Route::prefix('analytics/')->group(function () { // Admin analytics
            Route::get('roles-with-permissions-and-users', [AdminAnalyticsController::class, 'rolesWithPermissionsAndUsers']);
            Route::get('permission-statistics', [AdminAnalyticsController::class, 'permissionsWithRoleCounts']);
            Route::get('totals', [AdminAnalyticsController::class, 'analytics']);
            Route::get('users-with-stats', [AdminAnalyticsController::class, 'usersWithStats']);
            Route::get('department-statistics', [AdminAnalyticsController::class, 'departmentOverview']);
        });

        Route::post('logout', [AuthController::class, 'logout']);

        // group users and prefix by users
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::get('/search', [UserController::class, 'search']);
            Route::get('/my-department', [UserController::class, 'getDepartmentUsers']);
            Route::post('/bulk-upload', [UserController::class, 'bulkUpload']);
            Route::post('/assign-to-department', [UserController::class, 'assignUsersToDepartment']);
            Route::get('/institute', [UserController::class, 'byInstitute'])->middleware('auth:sanctum');
            Route::get('/statistics', [UserController::class, 'userStatistics'])->middleware('auth:sanctum');
            Route::get('/roles/{role}', [UserController::class, 'byRole'])->middleware('auth:sanctum');
            Route::get('/departments/{departmentId}', [UserController::class, 'byDepartment']);
            Route::put('/{id}', [UserController::class, 'update']);
            Route::put('/{id}/assign-role', [UserController::class, 'assignRole'])->middleware('department.head');
            Route::put('/{id}/enroll-department', [UserController::class, 'enrollInDepartment']);
            Route::put('/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::delete('/{id}', [UserController::class, 'destroy']);
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
            Route::get('/statistics', [DepartmentController::class, 'statistics']);
            Route::get('/{id}', [DepartmentController::class, 'show']);
            Route::post('/', [DepartmentController::class, 'store']);
            Route::put('/{id}', [DepartmentController::class, 'update']);
            Route::put('/{id}/enroll-user', [DepartmentController::class, 'enrollUser']);
            Route::get('/{id}/roles', [DepartmentController::class, 'getRoles']);
            Route::delete('/{id}', [DepartmentController::class, 'destroy']);
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::post('/', [RoleController::class, 'store']);
            Route::post('/department', [RoleController::class, 'createDepartmentRole']);
            Route::get('/my-department', [RoleController::class, 'getDepartmentRoles']);
            Route::get('/{role}', [RoleController::class, 'show']);
            Route::put('/{role}', [RoleController::class, 'update']); // Changed {id} to {role}
            Route::delete('/{role}', [RoleController::class, 'destroy']); // Changed {id} to {role}

            Route::prefix('{roleId}/permissions')->group(function () {
                Route::get('/', [RolePermissionController::class, 'getRolePermissions']);
                Route::post('/assign', [RolePermissionController::class, 'assignPermissions']);
                Route::put('/sync', [RolePermissionController::class, 'syncPermissions']);
                Route::delete('/remove', [RolePermissionController::class, 'removePermissions']);
                Route::post('/bulk', [RolePermissionController::class, 'bulkOperations']);
            });

            // Available permissions
            Route::get('/permissions/available', [RolePermissionController::class, 'getAllPermissions']);
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
