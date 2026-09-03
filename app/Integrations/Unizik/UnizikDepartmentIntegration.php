<?php

namespace App\Integrations\Unizik;

use App\Integrations\Contracts\DepartmentIntegrationInterface;
use App\Models\Institution;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UnizikDepartmentIntegration implements DepartmentIntegrationInterface
{
    /**
     * Fetch departments from the Unizik API.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getDepartments(Request $request)
    {
        $apiUrl = env('EXTERNAL_DEPARTMENT_API_URL', 'https://cirs.unizik.edu.ng/dashboard/app/hrms/offices?api');
        $facdiv = $request->get('facdiv') ?? $request->get('faculty');
        $forceRefresh = $request->boolean('refresh') || $request->get('refresh') === '1';

        try {
            if ($facdiv) {
                $departments = $this->fetchDepartmentsForFaculty($apiUrl, $facdiv, $forceRefresh);
            } else {
                $departments = $this->fetchAllDepartments($apiUrl, $forceRefresh);
            }

            // Resolve institution to ensure frontend contract compatibility
            $institution = $this->resolveInstitution($request);
            $institutionData = $institution ? $institution->toArray() : null;
            $institutionId = $institution?->id ?? null;

            // Just-In-Time (JIT) sync to local database to satisfy MySQL foreign keys
            if ($institutionId && !empty($departments)) {
                $this->syncToLocalDatabase($departments, $institutionId);
            }

            // Attach institution object and standard department properties
            $departments = array_map(function ($dept) use ($institutionId, $institutionData) {
                return array_merge($dept, [
                    'institution_id' => $institutionId,
                    'head_id' => null,
                    'description' => null,
                    'created_at' => null,
                    'updated_at' => null,
                    'institution' => $institutionData,
                ]);
            }, $departments);

            // Search filter
            if ($search = $request->get('search')) {
                $departments = array_values(array_filter($departments, function ($item) use ($search) {
                    return stripos($item['name'] ?? '', $search) !== false
                        || stripos($item['department_code'] ?? '', $search) !== false
                        || stripos($item['department_name'] ?? '', $search) !== false
                        || stripos($item['faculty_name'] ?? '', $search) !== false;
                }));
            }

            // Pagination
            $perPage = (int) $request->get('per_page', 20);
            $page = (int) $request->get('page', 1);
            $total = count($departments);

            $slice = array_slice($departments, ($page - 1) * $perPage, $perPage);

            return new LengthAwarePaginator($slice, $total, $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        } catch (\Exception $e) {
            Log::error("Unizik Department API Error: " . $e->getMessage());
        }

        return new LengthAwarePaginator([], 0, (int) $request->get('per_page', 20));
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
     * Just-In-Time (JIT) sync departments to local database to satisfy foreign keys.
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
                    \App\Models\Department::upsert(
                        $chunk,
                        ['id'],
                        ['name', 'department_code', 'institution_id', 'is_active', 'updated_at']
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning("JIT Department sync warning: " . $e->getMessage());
        }
    }

    /**
     * Fetch all departments across all faculties and units.
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

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($apiUrl) {
            $response = Http::timeout(15)->get($apiUrl);

            if (!$response->successful()) {
                return [];
            }

            $parents = $response->json();
            if (!is_array($parents)) {
                return [];
            }

            // Fetch sub-offices/departments in parallel for all parent faculties and units
            $responses = Http::pool(function (Pool $pool) use ($parents, $apiUrl) {
                return array_map(function ($parent) use ($pool, $apiUrl) {
                    $code = $parent['code'] ?? '';
                    return $pool->as($code)->timeout(15)->get($apiUrl . '&facdiv=' . urlencode($code));
                }, $parents);
            });

            $departments = [];

            foreach ($parents as $parent) {
                $parentCode = $parent['code'] ?? '';
                $parentName = trim($parent['name'] ?? '');
                $parentType = $parent['type'] ?? 'Faculty';

                if (isset($responses[$parentCode]) && $responses[$parentCode]->successful()) {
                    $subItems = $responses[$parentCode]->json();

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

            return $departments;
        });
    }

    /**
     * Fetch departments for a single faculty/division code.
     *
     * @param string $apiUrl
     * @param string $facdiv
     * @param bool $forceRefresh
     * @return array
     */
    protected function fetchDepartmentsForFaculty(string $apiUrl, string $facdiv, bool $forceRefresh = false): array
    {
        $cacheKey = 'unizik_dept_faculty_' . $facdiv;

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($apiUrl, $facdiv) {
            $subResponse = Http::timeout(15)->get($apiUrl . '&facdiv=' . urlencode($facdiv));

            if (!$subResponse->successful()) {
                return [];
            }

            $subItems = $subResponse->json();
            if (!is_array($subItems)) {
                return [];
            }

            // Find parent faculty name
            $parentName = '';
            $rootResponse = Http::timeout(15)->get($apiUrl);
            if ($rootResponse->successful()) {
                $parents = $rootResponse->json();
                if (is_array($parents)) {
                    foreach ($parents as $p) {
                        if (($p['code'] ?? '') === $facdiv) {
                            $parentName = trim($p['name'] ?? '');
                            break;
                        }
                    }
                }
            }

            $departments = [];
            foreach ($subItems as $subItem) {
                $deptCode = $subItem['code'] ?? null;
                $deptName = trim($subItem['name'] ?? 'Unknown');
                $type = $subItem['type'] ?? 'Department';
                $displayName = $parentName ? "{$deptName} - {$parentName}" : $deptName;

                $departments[] = [
                    'id' => $deptCode,
                    'name' => $displayName,
                    'department_name' => $deptName,
                    'faculty_name' => $parentName,
                    'department_code' => $deptCode,
                    'parent_code' => $facdiv,
                    'type' => $type,
                    'is_active' => 1,
                ];
            }

            return $departments;
        });
    }
}
