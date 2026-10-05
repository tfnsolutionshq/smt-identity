<?php

namespace App\Integrations\Unizik;

use App\Integrations\Contracts\DepartmentIntegrationInterface;
use App\Models\Department;
use App\Models\Institution;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnizikDepartmentIntegration implements DepartmentIntegrationInterface
{
    /**
     * Fetch departments from the local database (persisted from Unizik API).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDepartments(Request $request)
    {
        $institution = $this->resolveInstitution($request);
        $institutionId = $institution?->id ?? null;
        $forceRefresh = $request->boolean('refresh') || $request->get('refresh') === '1';

        // If local database has no departments for this institution or refresh requested, sync from remote API
        $hasLocalDepartments = $institutionId 
            ? Department::where('institution_id', $institutionId)->exists() 
            : Department::exists();

        if ($forceRefresh || !$hasLocalDepartments) {
            $this->syncFromRemote($institutionId, $forceRefresh);
        }

        $perPage = (int) $request->get('per_page', 20);
        $page = (int) $request->get('page', 1);
        $search = $request->get('search');
        $facdiv = $request->get('facdiv') ?? $request->get('faculty');

        $cacheKey = 'unizik_dept_db_' . md5(json_encode([
            'institution_id' => $institutionId,
            'search' => $search,
            'facdiv' => $facdiv,
            'per_page' => $perPage,
            'page' => $page,
        ]));

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addWeek(), function () use ($institutionId, $search, $facdiv, $perPage) {
            $query = Department::query()->with('institution');

            if ($institutionId) {
                $query->where('institution_id', $institutionId);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('department_code', 'like', "%{$search}%");
                });
            }

            if ($facdiv) {
                $query->where(function ($q) use ($facdiv) {
                    $q->where('department_code', 'like', "{$facdiv}%")
                      ->orWhere('name', 'like', "%{$facdiv}%");
                });
            }

            return $query->paginate($perPage);
        });
    }

    /**
     * Sync departments from remote Unizik API into the local database.
     *
     * @param string|null $institutionId
     * @param bool $forceRefresh
     * @return array
     */
    public function syncFromRemote(?string $institutionId, bool $forceRefresh = false): array
    {
        $apiUrl = env('EXTERNAL_DEPARTMENT_API_URL', 'https://cirs.unizik.edu.ng/dashboard/app/hrms/offices?api');

        try {
            $departments = $this->fetchAllDepartments($apiUrl, $forceRefresh);

            if ($institutionId && !empty($departments)) {
                $this->syncToLocalDatabase($departments, $institutionId);
            }

            return $departments;
        } catch (\Throwable $e) {
            Log::error("Unizik Department Sync Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Resolve the institution for the current request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Models\Institution|null
     */
    protected function resolveInstitution(Request $request): ?Institution
    {
        if ($user = $request->user()) {
            if ($user->institution) {
                return $user->institution;
            }
            if ($user->institution_id) {
                return Institution::find($user->institution_id);
            }
        }

        return Institution::where('code', 'UNIZIK')
            ->orWhere('name', 'like', '%Unizik%')
            ->orWhere('name', 'like', '%Nnamdi Azikiwe%')
            ->first() ?? Institution::first();
    }

    /**
     * Persist departments into local database.
     *
     * @param array $departments
     * @param string $institutionId
     * @return void
     */
    protected function syncToLocalDatabase(array $departments, string $institutionId): void
    {
        try {
            $now = now();
            $records = [];

            foreach ($departments as $dept) {
                $deptId = $dept['id'] ?? $dept['department_code'] ?? null;
                if (!$deptId) {
                    continue;
                }

                $records[] = [
                    'id' => (string) $deptId,
                    'name' => $dept['name'] ?? 'Unknown Department',
                    'department_code' => $dept['department_code'] ?? $deptId,
                    'institution_id' => $institutionId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($records)) {
                foreach (array_chunk($records, 100) as $chunk) {
                    Department::upsert(
                        $chunk,
                        ['id'],
                        ['name', 'department_code', 'institution_id', 'is_active', 'updated_at']
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning("Department database sync warning: " . $e->getMessage());
        }
    }

    /**
     * Fetch all departments across all faculties and units from external API.
     *
     * @param string $apiUrl
     * @param bool $forceRefresh
     * @return array
     */
    protected function fetchAllDepartments(string $apiUrl, bool $forceRefresh = false): array
    {
        $cacheKey = 'unizik_all_departments_mapped';

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addWeek(), function () use ($apiUrl) {
            try {
                $response = Http::timeout(30)->get($apiUrl);
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch root faculties from Unizik API: " . $e->getMessage());
                return [];
            }

            if (!$response->successful()) {
                return [];
            }

            $parents = $response->json();
            if (!is_array($parents)) {
                return [];
            }

            $departments = [];
            $chunks = array_chunk($parents, 20);

            // Fetch sub-offices/departments in parallel chunks across all parent faculties and units
            foreach ($chunks as $chunk) {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $apiUrl) {
                    return array_map(function ($parent) use ($pool, $apiUrl) {
                        $code = $parent['code'] ?? '';
                        return $pool->as($code)->timeout(20)->get($apiUrl . '&facdiv=' . urlencode($code));
                    }, $chunk);
                });

                foreach ($chunk as $parent) {
                    $parentCode = $parent['code'] ?? '';
                    $parentName = trim($parent['name'] ?? '');
                    $parentType = $parent['type'] ?? 'Faculty';

                    $resp = $responses[$parentCode] ?? null;

                    if ($resp instanceof \Illuminate\Http\Client\Response && $resp->successful()) {
                        $subItems = $resp->json();

                        if (is_array($subItems)) {
                            foreach ($subItems as $subItem) {
                                $deptCode = $subItem['code'] ?? null;
                                $deptName = trim($subItem['name'] ?? 'Unknown');
                                $type = $subItem['type'] ?? 'Department';

                                // Format: "DEPARTMENT - FACULTY" (e.g. "COMPUTER SCIENCE - FACULTY OF PHYSICAL SCIENCES")
                                $displayName = $parentName ? "{$deptName} - {$parentName}" : $deptName;

                                $departments[] = [
                                    'id' => $deptCode,
                                    'name' => $displayName,
                                    'department_name' => $deptName,
                                    'faculty_name' => $parentName,
                                    'department_code' => $deptCode,
                                    'parent_code' => $parentCode,
                                    'parent_type' => $parentType,
                                    'type' => $type,
                                    'is_active' => 1,
                                ];
                            }
                        }
                    }
                }
            }

            return $departments;
        });
    }
}

