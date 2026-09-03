<?php

namespace App\Integrations\Unizik;

use App\Integrations\Contracts\UserIntegrationInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UnizikUserIntegration implements UserIntegrationInterface
{
    /**
     * Look up user details from Unizik external API.
     *
     * @param string $identifier
     * @return array|null
     */
    public function lookupUser(string $identifier): ?array
    {
        $url = env('EXTERNAL_USER_API_URL', 'https://api.unizik.edu.ng/cirms/v1/staff/fetch/') . $identifier;
        $cacheKey = 'unizik_user_lookup_' . md5($identifier);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($url, $identifier) {
            try {
                $response = Http::withHeaders([
                    'Nau-Contractor-Id' => env('NAU_CONTRACTOR_ID'),
                    'Nau-Api-Key' => env('NAU_API_KEY'),
                    'Nau-Access-Token' => env('NAU_ACCESS_TOKEN'),
                ])->timeout(15)->get($url);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['code']) && $data['code'] == 200 && !empty($data['message'])) {
                        $staffData = $data['message'][0];
                        $personal = $staffData['personal_details'] ?? [];
                        $work = $staffData['work_details'] ?? [];
                        $other = $staffData['other_details'] ?? [];

                        $phone = $personal['phone_number'] ?? $personal['phone'] ?? $personal['phone_no'] ?? $staffData['phone_number'] ?? $staffData['phone'] ?? '';
                        $avatar = $personal['avatar'] ?? $personal['img'] ?? $personal['avatar_url'] ?? $staffData['avatar'] ?? $staffData['img'] ?? '';

                        return [
                            'first_name' => $personal['othernames'] ?? '',
                            'last_name' => $personal['surname'] ?? '',
                            'email' => $personal['email'] ?? '',
                            'phone' => $phone,
                            'phone_number' => $phone,
                            'avatar' => $avatar,
                            'avatar_url' => $avatar,
                            'department_code' => $other['depunit_code'] ?? '',
                            'department_name' => $work['department'] ?? '',
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error("Unizik User Lookup Error: " . $e->getMessage());
            }

            return null;
        });
    }
}
