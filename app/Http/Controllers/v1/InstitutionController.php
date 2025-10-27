<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Validator;

class InstitutionController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        // Get 'per_page' from query string, fallback to 10
        $perPage = $request->get('per_page', 10);

        // Optional: allow simple search or sorting later
        $query = Institution::query();

        // Optional: handle simple search (if you add ?search=)
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $institutions = $query->paginate($perPage);

        return $this->successResponse($institutions, 'Institutions fetched successfully');
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'code' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $institution = Institution::create($validator->validated());
            return $this->successResponse($institution, 'Institution created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create institution: ' . $e->getMessage(), 500);
        }
    }
}
