<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MemoService
{
    public function getUserMemoStats(string $userId, string $token): array
    {
        try {
            $baseUrl = rtrim(config('services.memo.base_url'), '/');
            $url = "{$baseUrl}/s2s/users/{$userId}/memo-stats";

            $response = Http::withToken($token)->get($url);

            if ($response->successful()) {
                $data = $response->json('data', []);
                return [
                    'sent_memos_count' => $data['sent_memos_count'] ?? 0,
                    'pending_action_count' => $data['pending_action_count'] ?? 0,
                ];
            }

            return [
                'sent_memos_count' => $response,
                'pending_action_count' => 0,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch memo stats: ' . $e->getMessage());
            return [
                'sent_memos_count' => 0,
                'pending_action_count' => 0,
            ];
        }
    }

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
