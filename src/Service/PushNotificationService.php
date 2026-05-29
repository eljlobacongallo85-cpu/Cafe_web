<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\PushTokenRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PushNotificationService
{
    public function __construct(
        private readonly PushTokenRepository $pushTokens,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function notifyUser(User $user, string $title, string $body, array $data = []): void
    {
        $accessToken = $this->getAccessToken();
        $projectId = $this->getProjectId();

        if (!$accessToken || !$projectId) {
            return;
        }

        foreach ($this->pushTokens->findActiveByUser($user) as $pushToken) {
            try {
                $response = $this->httpClient->request('POST', sprintf(
                    'https://fcm.googleapis.com/v1/projects/%s/messages:send',
                    rawurlencode($projectId)
                ), [
                    'auth_bearer' => $accessToken,
                    'json' => [
                        'message' => [
                            'token' => $pushToken->getToken(),
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => array_map('strval', $data),
                            'android' => [
                                'priority' => 'HIGH',
                            ],
                        ],
                    ],
                ]);

                if ($response->getStatusCode() >= 400) {
                    error_log('[Push] FCM rejected message: ' . $response->getContent(false));
                }
            } catch (\Throwable $e) {
                error_log('[Push] FCM send failed: ' . $e->getMessage());
            }
        }
    }

    private function getAccessToken(): ?string
    {
        $serviceAccount = $this->getServiceAccount();
        if (!$serviceAccount) {
            return null;
        }

        $now = time();
        $jwtHeader = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $jwtClaim = $this->base64UrlEncode(json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsignedJwt = $jwtHeader . '.' . $jwtClaim;
        $signature = '';
        $privateKey = str_replace('\\n', "\n", (string) $serviceAccount['private_key']);

        if (!openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsignedJwt . '.' . $this->base64UrlEncode($signature),
                ],
            ]);

            $data = $response->toArray(false);
            return isset($data['access_token']) ? (string) $data['access_token'] : null;
        } catch (\Throwable $e) {
            error_log('[Push] FCM auth failed: ' . $e->getMessage());
            return null;
        }
    }

    private function getProjectId(): ?string
    {
        $serviceAccount = $this->getServiceAccount();
        return $serviceAccount['project_id'] ?? ($_ENV['FCM_PROJECT_ID'] ?? null);
    }

    private function getServiceAccount(): ?array
    {
        $raw = $_ENV['FCM_SERVICE_ACCOUNT_JSON'] ?? '';
        if ($raw === '') {
            return null;
        }

        if (!str_starts_with(trim($raw), '{')) {
            $decoded = base64_decode($raw, true);
            if ($decoded !== false) {
                $raw = $decoded;
            }
        }

        try {
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            return null;
        }

        return $data;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
