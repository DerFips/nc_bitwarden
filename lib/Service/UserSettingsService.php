<?php
namespace OCA\NcBitwarden\Service;

use OCP\IConfig;

class UserSettingsService {
    private const SERVER_TYPE_KEY = 'server_type';
    private const CUSTOM_URL_KEY  = 'custom_url';
    private const DEVICE_ID_KEY   = 'device_identifier';

    public function __construct(
        private IConfig $config,
        private string  $appName,
    ) {}

    public function getSettings(string $userId): array {
        return [
            'server_type' => $this->config->getUserValue($userId, $this->appName, self::SERVER_TYPE_KEY, 'cloud_us'),
            'custom_url'  => $this->config->getUserValue($userId, $this->appName, self::CUSTOM_URL_KEY, ''),
            'device_id'   => $this->getOrCreateDeviceId($userId),
        ];
    }

    public function saveSettings(string $userId, string $serverType, string $customUrl): void {
        $allowed = ['cloud_us', 'cloud_eu', 'selfhosted'];
        if (!in_array($serverType, $allowed, true)) {
            throw new \InvalidArgumentException('Ungültiger Server-Typ');
        }
        if ($serverType === 'selfhosted') {
            $this->validateSelfhostedUrl($customUrl);
        }
        $this->config->setUserValue($userId, $this->appName, self::SERVER_TYPE_KEY, $serverType);
        $this->config->setUserValue($userId, $this->appName, self::CUSTOM_URL_KEY, rtrim($customUrl, '/'));
    }

    private function validateSelfhostedUrl(string $url): void {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Ungültige URL');
        }
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \InvalidArgumentException('URL konnte nicht geparst werden');
        }
        if (strtolower($parsed['scheme']) !== 'https') {
            throw new \InvalidArgumentException('Nur HTTPS-URLs erlaubt');
        }
        $host = strtolower($parsed['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException('IP-Adressen nicht erlaubt – bitte Hostname verwenden');
        }
        foreach (['localhost', '.local', '.internal', '.lan', '.corp', '.home'] as $pattern) {
            if ($host === ltrim($pattern, '.') || str_ends_with($host, $pattern)) {
                throw new \InvalidArgumentException("Interne Hostnamen nicht erlaubt: $host");
            }
        }
    }

    public function getApiUrls(string $userId): array {
        $type   = $this->config->getUserValue($userId, $this->appName, self::SERVER_TYPE_KEY, 'cloud_us');
        $custom = $this->config->getUserValue($userId, $this->appName, self::CUSTOM_URL_KEY, '');
        return match($type) {
            'cloud_us'   => ['api' => 'https://api.bitwarden.com',  'identity' => 'https://identity.bitwarden.com'],
            'cloud_eu'   => ['api' => 'https://api.bitwarden.eu',   'identity' => 'https://identity.bitwarden.eu'],
            'selfhosted' => ['api' => $custom . '/api',             'identity' => $custom . '/identity'],
            default      => throw new \RuntimeException('Unbekannter Server-Typ'),
        };
    }

    private function getOrCreateDeviceId(string $userId): string {
        $id = $this->config->getUserValue($userId, $this->appName, self::DEVICE_ID_KEY, '');
        if ($id === '') {
            $id = $this->generateUuidV4();
            $this->config->setUserValue($userId, $this->appName, self::DEVICE_ID_KEY, $id);
        }
        return $id;
    }

    private function generateUuidV4(): string {
        $bytes    = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        $hex      = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8), substr($hex, 8, 4),
            substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12)
        );
    }
}
