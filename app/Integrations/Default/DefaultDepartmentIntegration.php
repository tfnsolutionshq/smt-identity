<?php

namespace App\Integrations\Default;

use App\Integrations\Contracts\DepartmentIntegrationInterface;
use App\Models\Department;
use Illuminate\Http\Request;

class DefaultDepartmentIntegration implements DepartmentIntegrationInterface
{
    /**
     * Fetch departments from the local database.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getDepartments(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $query = Department::query();
        
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->with(['institution'])->paginate($perPage);
    }
}
