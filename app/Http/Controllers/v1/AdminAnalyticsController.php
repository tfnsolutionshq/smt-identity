<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Models\User;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AdminAnalyticsController extends Controller
{
    use ApiResponse;

    /**
     * Get comprehensive user analytics with memo statistics.
     */
    public function analytics(Request $request)
    {
        try {
            $institutionId = $request->user()->myInstitutionID();
            $token = $request->bearerToken();

            // Get basic user statistics
            $userStats = $this->getUserStatistics($institutionId);

            // Get memo statistics from memo service
            $memoStats = $this->getMemoStatistics($institutionId, $token);

            $analytics = array_merge($userStats, $memoStats);

            return $this->successResponse(
                $analytics,
                'User analytics retrieved successfully.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve user analytics: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get detailed users list with memo statistics and filtering.
     */
    public function usersWithStats(Request $request)
    {
        try {
            $institutionId = $request->user()->myInstitutionID();
            $token = $request->bearerToken();
            $perPage = $request->get('per_page', 20);
            $page = $request->get('page', 1);

            // Build user query with filters
            $query = User::sameInstitution()
                ->with(['department']);

            // Apply filters
            $this->applyUserFilters($query, $request);

            // Get paginated users
            $users = $query->paginate($perPage, ['*'], 'page', $page);

            // Get memo statistics for these users
            $userIds = $users->pluck('id')->toArray();
            $memoStats = $this->getUsersMemoStatistics($userIds, $institutionId, $token);

            // Combine user data with memo stats
            $usersWithStats = $users->map(function ($user) use ($memoStats) {
                $userStats = $memoStats[$user->id] ?? [
                    'total_memos' => 0,
                    'sent_memos' => 0,
                    'pending_approvals' => 0,
                    'approval_rate' => 0
                ];

                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'department' => $user->department ? [
                        'id' => $user->department->id,
                        'name' => $user->department->name
                    ] : null,
                    'status' => $user->is_active ? 'active' : 'inactive',
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'memo_stats' => $userStats
                ];
            });

            return $this->successResponse([
                'data' => $usersWithStats,
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'total_pages' => $users->lastPage(),
                ]
            ], 'Users with statistics retrieved successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve users with statistics: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get basic user statistics.
     */
    private function getUserStatistics(string $institutionId): array
    {
        $totalUsers = User::sameInstitution()->count();
        $activeUsers = User::sameInstitution()->active()->count();
        $administrators = User::sameInstitution()->whereIn('role', ['admin', 'super_admin'])->count();

        // Count users by role
        $usersByRole = User::sameInstitution()
            ->selectRaw('role, COUNT(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        // Count users by department
        $usersByDepartment = User::sameInstitution()
            ->with('department')
            ->get()
            ->groupBy('department.name')
            ->map(function ($users, $departmentName) {
                return [
                    'department' => $departmentName ?: 'No Department',
                    'count' => $users->count(),
                    'active_users' => $users->where('is_active', true)->count()
                ];
            })
            ->values();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'administrators' => $administrators,
        ];
    }

    /**
     * Get memo statistics from memo service.
     */
    private function getMemoStatistics(string $institutionId, string $token): array
    {
        try {
            $baseUrl = config('services.memo.base_url', 'http://localhost:8002/api/v1');

            $response = Http::withToken($token)
                ->timeout(10)
                ->get("{$baseUrl}/analytics/institution-stats", [
                    'institution_id' => $institutionId
                ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [
                    'total_memos' => 0,
                    'sent_memos' => 0,
                    'draft_memos' => 0,
                    'scheduled_memos' => 0
                ];
            }

            Log::warning('Failed to fetch memo statistics', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'total_memos' => 0,
                'sent_memos' => 0,
                'draft_memos' => 0,
                'scheduled_memos' => 0
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching memo statistics: ' . $e->getMessage());
            return [
                'total_memos' => 0,
                'sent_memos' => 0,
                'draft_memos' => 0,
                'scheduled_memos' => 0
            ];
        }
    }

    /**
     * Get memo statistics for specific users.
     */
    private function getUsersMemoStatistics(array $userIds, string $institutionId, string $token): array
    {
        try {
            $baseUrl = config('services.memo.base_url', 'http://127.0.0.1:800/api/v1');

            $response = Http::withToken($token)
                ->timeout(15)
                ->post("{$baseUrl}/analytics/users-stats", [
                    'user_ids' => $userIds,
                    'institution_id' => $institutionId
                ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            Log::warning('Failed to fetch users memo statistics', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [];
        } catch (\Exception $e) {
            Log::error('Error fetching users memo statistics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Apply filters to user query.
     */
    private function applyUserFilters($query, Request $request): void
    {
        // Filter by department
        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        // Filter by role
        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        // Filter by status
        if ($status = $request->get('status')) {
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Search by name
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
    }


    public function rolesWithPermissionsAndUsers(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');

            // Base query for roles
            $query = Role::query()->sameInstitute();

            // Apply search filter
            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            // Get paginated roles with permissions
            $roles = $query->with(['permissions'])->paginate($perPage);

            // Get user counts for each role
            $userCounts = DB::table('users')
                ->where('institution_id', Auth::user()->institution->id)
                ->select('role', DB::raw('COUNT(*) as user_count'))
                ->groupBy('role')
                ->get()
                ->keyBy('role');

            // Transform the response to include user counts and formatted permissions
            $transformedRoles = $roles->getCollection()->map(function ($role) use ($userCounts) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'institution_id' => $role->institution_id,
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                    'total_users' => $userCounts[$role->name]->user_count ?? 0,
                    'permissions' => $role->permissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'description' => $permission->description,
                            'created_at' => $permission->created_at,
                            'updated_at' => $permission->updated_at,
                        ];
                    }),
                    'permissions_count' => $role->permissions->count(),
                ];
            });

            // Return paginated response
            return $this->successResponse([
                'data' => $transformedRoles,
                'meta' => [
                    'current_page' => $roles->currentPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                    'total_pages' => $roles->lastPage(),
                ]
            ], 'Roles with permissions and user counts fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch roles: ' . $e->getMessage(), 500);
        }
    }

    public function permissionsWithRoleCounts(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');

            // Base query for permissions with role counts
            $query = Permission::query()->sameInstitute()
                ->withCount('roles');

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Get paginated permissions
            $permissions = $query->paginate($perPage);

            // Transform the response
            $transformedPermissions = $permissions->getCollection()->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'description' => $permission->description,
                    'institution_id' => $permission->institution_id,
                    'created_at' => $permission->created_at,
                    'updated_at' => $permission->updated_at,
                    'total_roles_with_permission' => $permission->roles_count,
                    'roles' => $permission->roles->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'description' => $role->description,
                        ];
                    }),
                ];
            });

            return $this->successResponse([
                'data' => $transformedPermissions,
                'meta' => [
                    'current_page' => $permissions->currentPage(),
                    'per_page' => $permissions->perPage(),
                    'total' => $permissions->total(),
                    'total_pages' => $permissions->lastPage(),
                ]
            ], 'Permissions with role counts fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch permissions: ' . $e->getMessage(), 500);
        }
    }

    public function departmentOverview(Request $request)
    {
        try {
            $institutionId = $request->user()->myInstitutionID();

            // 1. Get the statistics
            $totalDepartments = Department::where('institution_id', $institutionId)->count();
            $activeDepartments = Department::where('institution_id', $institutionId)
                ->where('is_active', true)
                ->count();
            $totalUsers = User::where('institution_id', $institutionId)->count();

            // 2. Get all departments with users and user counts
            $departments = Department::where('institution_id', $institutionId)
                ->with(['users' => function($query) {
                    $query->select('id', 'first_name', 'last_name', 'email', 'role', 'department_id')
                          ->where('is_active', true);
                }])
                ->withCount('users as total_users')
                ->get();

            // Format the departments with users
            $formattedDepartments = $departments->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'department_code' => $department->department_code,
                    'description' => $department->description,
                    'is_active' => $department->is_active,
                    'total_users' => $department->total_users,
                    'users' => $department->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'email' => $user->email,
                            'role' => $user->role,
                        ];
                    }),
                ];
            });

            return $this->successResponse([
                'statistics' => [
                    'total_users' => $totalUsers,
                    'total_departments' => $totalDepartments,
                    'active_departments' => $activeDepartments,
                ],
                'departments' => $formattedDepartments
            ], 'Department overview fetched successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to fetch department overview: ' . $e->getMessage(), 500);
        }
    }



}
