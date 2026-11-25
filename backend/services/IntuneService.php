<?php

class IntuneService
{
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $accessToken;

    private const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';
    private const SCOPE = 'https://graph.microsoft.com/.default';

    public function __construct(?array $config = null)
    {
        if ($config) {
            $this->tenantId = $config['tenant_id'] ?? '';
            $this->clientId = $config['client_id'] ?? '';
            $this->clientSecret = $config['client_secret'] ?? '';
        } else {
            $global = require __DIR__ . '/../config/intune.php';
            $this->tenantId = $global['tenant_id'];
            $this->clientId = $global['client_id'];
            $this->clientSecret = $global['client_secret'];
        }
    }

    public function authenticate(): string
    {
        $url = sprintf(self::TOKEN_URL, $this->tenantId);
        $body = http_build_query([
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => self::SCOPE,
            'grant_type' => 'client_credentials',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $err = json_decode($response, true);
            throw new RuntimeException('Auth failed: ' . ($err['error_description'] ?? $response));
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }

    public function fetchManagedDevices(): array
    {
        if (empty($this->accessToken)) {
            $this->authenticate();
        }

        $allDevices = [];
        $url = self::GRAPH_BASE . '/deviceManagement/managedDevices?$top=100';

        while ($url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $err = json_decode($response, true);
                throw new RuntimeException('Graph API error: ' . ($err['error']['message'] ?? $response));
            }

            $data = json_decode($response, true);
            $allDevices = array_merge($allDevices, $data['value'] ?? []);

            $url = null;
            $nextLink = $data['@odata.nextLink'] ?? null;
            if ($nextLink) {
                $url = $nextLink;
            }
        }

        return $allDevices;
    }

    public function fetchAzureUsers(): array
    {
        if (empty($this->accessToken)) {
            $this->authenticate();
        }

        $allUsers = [];
        $url = self::GRAPH_BASE . '/users?$top=100&$select=id,displayName,mail,userPrincipalName,jobTitle,department,accountEnabled,userType,assignedLicenses,city,country,officeLocation,companyName,mobilePhone,businessPhones';

        while ($url) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Accept: application/json',
                ],
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                $err = json_decode($response, true);
                throw new RuntimeException('Graph API error: ' . ($err['error']['message'] ?? $response));
            }

            $data = json_decode($response, true);
            $allUsers = array_merge($allUsers, $data['value'] ?? []);

            $url = null;
            $nextLink = $data['@odata.nextLink'] ?? null;
            if ($nextLink) {
                $url = $nextLink;
            }
        }

        return $allUsers;
    }

    public static function normalizeDevice(array $raw): array
    {
        return [
            'device_id' => $raw['id'] ?? '',
            'device_name' => $raw['deviceName'] ?? null,
            'managed_device_owner_type' => $raw['managedDeviceOwnerType'] ?? null,
            'enrolled_date_time' => self::toDatetime($raw['enrolledDateTime'] ?? null),
            'operating_system' => $raw['operatingSystem'] ?? null,
            'os_version' => $raw['osVersion'] ?? null,
            'device_type' => $raw['deviceType'] ?? null,
            'compliance_state' => $raw['complianceState'] ?? null,
            'last_sync_date_time' => self::toDatetime($raw['lastSyncDateTime'] ?? null),
            'user_display_name' => $raw['userDisplayName'] ?? null,
            'user_principal_name' => $raw['userPrincipalName'] ?? null,
            'serial_number' => $raw['serialNumber'] ?? null,
            'model' => $raw['model'] ?? null,
            'manufacturer' => $raw['manufacturer'] ?? null,
            'azure_ad_device_id' => $raw['azureADDeviceId'] ?? null,
            'is_supervised' => !empty($raw['isSupervised']) ? 1 : 0,
            'encrypted' => !empty($raw['encrypted']) ? 1 : 0,
            'jail_broken' => $raw['jailBroken'] ?? null,
            'free_storage_space_bytes' => $raw['freeStorageSpaceInBytes'] ?? null,
            'total_storage_space_bytes' => $raw['totalStorageSpaceInBytes'] ?? null,
            'raw_data' => json_encode($raw, JSON_UNESCAPED_UNICODE),
            'last_synced_at' => date('Y-m-d H:i:s'),
        ];
    }

    private static function toDatetime(?string $iso): ?string
    {
        if (!$iso) return null;
        $ts = strtotime($iso);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }
}
