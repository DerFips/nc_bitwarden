<?php
namespace OCA\NcBitwarden\Service;

use OCP\Http\Client\IClientService;
use OCP\ISession;

class BitwardenProxyService {
    private const SESSION_TOKEN_KEY   = 'bw_access_token';
    private const SESSION_REFRESH_KEY = 'bw_refresh_token';
    private const SESSION_EXPIRY_KEY  = 'bw_token_expiry';

    private array $baseOptions = [
        'allow_redirects' => true,
        'timeout'         => 15,
        'connect_timeout' => 10,
    ];

    public function __construct(
        private IClientService      $httpClientService,
        private ISession            $session,
        private UserSettingsService $settingsService,
    ) {}

    /**
     * Prelogin: KDF-Parameter abrufen
     * Endpunkt seit Nov 2022: POST {identity}/accounts/prelogin
     */
    public function prelogin(string $userId, string $email): array {
        $urls   = $this->settingsService->getApiUrls($userId);
        $client = $this->httpClientService->newClient();
        try {
            $response = $client->post(
                $urls['identity'] . '/accounts/prelogin',
                array_merge($this->baseOptions, [
                    'json'    => ['email' => $email],
                    'headers' => ['Content-Type' => 'application/json'],
                ])
            );
            $data = json_decode($response->getBody(), true);
            if (!is_array($data)) {
                throw new \RuntimeException('Ungueltiger Server-Response (kein JSON)');
            }
            return $data;
        } catch (\Exception $e) {
            throw new \RuntimeException($this->extractErrorMessage($e), 0, $e);
        }
    }

    /**
     * Login: POST {identity}/connect/token  (OAuth2 Password Grant)
     */
    public function login(string $userId, array $credentials): array {
        $urls     = $this->settingsService->getApiUrls($userId);
        $settings = $this->settingsService->getSettings($userId);
        $client   = $this->httpClientService->newClient();
        try {
            $response = $client->post(
                $urls['identity'] . '/connect/token',
                array_merge($this->baseOptions, [
                    'form_params' => [
                        'grant_type'       => 'password',
                        'username'         => $credentials['email'],
                        'password'         => $credentials['passwordHash'],
                        'scope'            => 'api offline_access',
                        'client_id'        => 'web',
                        'deviceType'       => 10,
                        'deviceIdentifier' => $settings['device_id'],
                        'deviceName'       => 'Nextcloud Bitwarden App',
                    ],
                ])
            );
        } catch (\Exception $e) {
            throw new \RuntimeException($this->extractErrorMessage($e), 0, $e);
        }
        $data = json_decode($response->getBody(), true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException(
                $data['error_description'] ?? $data['error'] ?? 'Login fehlgeschlagen'
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $this->session->set(self::SESSION_TOKEN_KEY,   $data['access_token']);
        $this->session->set(self::SESSION_EXPIRY_KEY,  time() + ($data['expires_in'] ?? 3600));
        if (!empty($data['refresh_token'])) {
            $this->session->set(self::SESSION_REFRESH_KEY, $data['refresh_token']);
        }
        return $data;
    }

    public function refreshToken(string $userId): void {
        $refreshToken = $this->session->get(self::SESSION_REFRESH_KEY);
        if (!$refreshToken) {
            throw new \RuntimeException('Kein Refresh-Token – bitte erneut einloggen.');
        }
        $urls   = $this->settingsService->getApiUrls($userId);
        $client = $this->httpClientService->newClient();
        try {
            $response = $client->post(
                $urls['identity'] . '/connect/token',
                array_merge($this->baseOptions, [
                    'form_params' => [
                        'grant_type'    => 'refresh_token',
                        'refresh_token' => $refreshToken,
                        'client_id'     => 'web',
                    ],
                ])
            );
        } catch (\Exception $e) {
            throw new \RuntimeException($this->extractErrorMessage($e), 0, $e);
        }
        $data = json_decode($response->getBody(), true);
        $this->session->set(self::SESSION_TOKEN_KEY,  $data['access_token']);
        $this->session->set(self::SESSION_EXPIRY_KEY, time() + ($data['expires_in'] ?? 3600));
    }

    /**
     * Vault-API: GET/POST/PUT/DELETE {api}/...
     */
    public function apiRequest(string $userId, string $method, string $path, array $body = []): array {
        $this->ensureValidToken($userId);
        $urls   = $this->settingsService->getApiUrls($userId);
        $token  = $this->session->get(self::SESSION_TOKEN_KEY);
        $client = $this->httpClientService->newClient();
        $options = array_merge($this->baseOptions, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
        ]);
        if (!empty($body)) {
            $options['json'] = $body;
        }
        try {
            $response = match(strtoupper($method)) {
                'GET'    => $client->get($urls['api'] . $path, $options),
                'POST'   => $client->post($urls['api'] . $path, $options),
                'PUT'    => $client->put($urls['api'] . $path, $options),
                'DELETE' => $client->delete($urls['api'] . $path, $options),
                default  => throw new \InvalidArgumentException("Unbekannte HTTP-Methode: $method"),
            };
        } catch (\Exception $e) {
            throw new \RuntimeException($this->extractErrorMessage($e), 0, $e);
        }
        $responseBody = $response->getBody();
        return $responseBody ? (json_decode($responseBody, true) ?? []) : [];
    }

    private function ensureValidToken(string $userId): void {
        $expiry = (int)($this->session->get(self::SESSION_EXPIRY_KEY) ?? 0);
        if (time() >= ($expiry - 60)) {
            $this->refreshToken($userId);
        }
    }

    private function extractErrorMessage(\Exception $e): string {
        if (method_exists($e, 'getResponse') && ($resp = $e->getResponse()) !== null) {
            $bodyStr = (string)$resp->getBody();
            $data    = json_decode($bodyStr, true);
            if (isset($data['error_description'])) return $data['error_description'];
            if (isset($data['message']))            return $data['message'];
            if (isset($data['error']))              return $data['error'];
            if ($resp->getStatusCode() === 404)
                return 'API-Endpunkt nicht gefunden (404) – URL in den Einstellungen pruefen';
        }
        return $e->getMessage();
    }
}
