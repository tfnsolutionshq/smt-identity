<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class DepartmentController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $query = Department::query();
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $departments = $query->with(['institution'])->paginate($perPage);

        return $this->successResponse($departments, 'departments fetched successfully');
    }

    public function store(Request $request)
    {
        $validator = app('validator')->make($request->all(), [
            'name' => 'required|string|max:255',
            'institution_id' => 'required|exists:institutions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $department = Department::create($validator->validated());
            return $this->successResponse($department, 'department created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create department: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request)
    {
        $department_id = $request->id;
        $department = Department::with('institution')->find($department_id);
        if (!$department) {
            return $this->errorResponse('Department not found', 404);
        }
        return $this->successResponse($department, 'department fetched successfully');
    }

    public function update(Request $request)
    {
        $department = Department::findOrFail($request->id);
        $validator = app('validator')->make($request->all(), [
            'name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $department->update($validator->validated());
        return $this->successResponse($department, 'department updated successfully');
    }

    public function destroy($id)
    {
        $department = Department::find($id);

        if (!$department) {
            return $this->errorResponse('Department not found', 404);
        }

        $department->delete();

        return $this->successResponse($department, 'Department deleted successfully');
    }

}
