<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Integrations\Contracts\UserIntegrationInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    use ApiResponse;

    /**
     * Get the active integration status and capabilities.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $active = config('services.integration.active') ?? env('ACTIVE_INTEGRATION');
        $hasActive = !empty($active) && !in_array(strtolower($active), ['default', 'none', 'false']);

        $capabilities = [
            'user_lookup' => $hasActive && in_array(strtolower($active), ['unizik']),
            'department_sync' => $hasActive && in_array(strtolower($active), ['unizik']),
        ];

        return $this->successResponse([
            'active_integration' => $hasActive ? strtolower($active) : null,
            'has_active_integration' => $hasActive,
            'capabilities' => $capabilities,
        ], 'Integration status retrieved successfully');
    }

    /**
     * Look up user details from the active external integration.
     *
     * @param Request $request
     * @param string $identifier
     * @param UserIntegrationInterface $integration
     * @return \Illuminate\Http\JsonResponse
     */
    public function lookupUser(Request $request, string $identifier, UserIntegrationInterface $integration)
    {
        $userData = $integration->lookupUser($identifier);

        if (!$userData) {
            return $this->errorResponse('User details not found or external system unavailable.', 404);
        }

        return $this->successResponse($userData, 'User details fetched successfully');
    }
}
