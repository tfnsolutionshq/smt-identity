<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MemoService
{
    public function getUserApprovalStats($userId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                Log::error('MemoService: No authenticated user');
                return null;
            }

            $url = config('services.memo.base_url') . "/analytics/user/{$userId}/approval-stats";

            $response = Http::withHeaders([
                'X-API-Key' => config('services.api_key'),
            ])->get($url, [
                'institution_id' => $user->institution_id,
                'user_id' => $userId
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                return $responseData['data'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('MemoService: Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
