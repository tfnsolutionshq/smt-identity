<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait NotifyTrait
{
    public function sendNotification(string $action, string $recipient, array $data = []): bool
    {
        try {
            $baseUrl = rtrim(config('services.notification.base_url', ''), '/');
            $url = "{$baseUrl}/notifications/send";

            $response = Http::post($url, [
                'service' => $this->getServiceName(),
                'action' => $action,
                'recipient' => $recipient,
                'data' => $data,
                'type' => 'email'
            ]);

            if ($response->failed()) {
                Log::error("Failed to send notification: {$response->status()}");
                return false;
            }

            return $response->json('success', false);

        } catch (\Throwable $e) {
            Log::error('Error sending notification: ' . $e->getMessage());
            return false;
        }
    }

    abstract protected function getServiceName(): string;
}
