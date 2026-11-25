<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/IntuneDevice.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../services/IntuneService.php';

class IntuneController
{
    public function index(Request $request): void
    {
        $filters = [
            'os' => $request->query('os'),
            'compliance_state' => $request->query('compliance_state'),
            'device_type' => $request->query('device_type'),
            'search' => $request->query('search'),
            'customer_id' => $request->query('customer_id'),
        ];
        $result = IntuneDevice::all(array_filter($filters));
        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): void
    {
        $device = IntuneDevice::find((int)$request->param('id'));
        if (!$device) {
            Response::error('Device not found', 404);
        }
        if (!Auth::canAccessCustomer((int)$device['customer_id'])) {
            Response::error('Forbidden', 403);
        }
        if ($device['raw_data']) {
            $device['raw_data'] = json_decode($device['raw_data'], true);
        }
        Response::success($device);
    }

    public function sync(Request $request): void
    {
        $customerId = (int)$request->input('customer_id', 0);

        if ($customerId) {
            if (!Auth::canAccessCustomer($customerId)) {
                Response::error('Forbidden', 403);
            }
            $customer = Customer::find($customerId);
            if (!$customer) {
                Response::error('Customer not found', 404);
            }
            $settings = Customer::getSettings($customerId);
            $azureConfig = $settings['azure_ad'] ?? [];
            $intuneConfig = $settings['intune'] ?? [];

            $serviceConfig = [
                'tenant_id' => $azureConfig['tenant_id'] ?? '',
                'client_id' => $azureConfig['client_id'] ?? '',
                'client_secret' => $azureConfig['client_secret'] ?? '',
            ];

            if (empty($serviceConfig['tenant_id']) || empty($serviceConfig['client_id'])) {
                Response::error('Azure AD not configured for this customer. Configure settings first.', 400);
            }
        } else {
            $serviceConfig = null;
        }

        try {
            $service = new IntuneService($serviceConfig);
            $devices = $service->fetchManagedDevices();
        } catch (RuntimeException $e) {
            Response::error('Sync failed: ' . $e->getMessage(), 502);
        }

        $imported = 0;
        foreach ($devices as $raw) {
            $normalized = IntuneService::normalizeDevice($raw);
            if ($customerId) {
                $normalized['customer_id'] = $customerId;
            }
            IntuneDevice::upsert($normalized);
            $imported++;
        }

        Logger::log('sync', 'intune_devices', $customerId ?: 0, "Intune sync: {$imported} devices for customer #{$customerId}");
        Response::success([
            'imported' => $imported,
            'customer_id' => $customerId ?: null,
            'last_synced_at' => date('Y-m-d H:i:s'),
        ], "Synced {$imported} devices");
    }

    public function summary(Request $request): void
    {
        $customerId = (int)$request->query('customer_id', 0);
        $scope = Auth::customerScope();
        $where = $scope['sql'];
        $params = $scope['params'];

        if ($customerId) {
            if (!Auth::canAccessCustomer($customerId)) {
                Response::error('Forbidden', 403);
            }
            $where = 'customer_id = ?';
            $params = [$customerId];
        }

        Response::success([
            'compliance' => Database::fetchAll(
                "SELECT compliance_state, COUNT(*) as count FROM intune_devices WHERE is_active = 1 AND {$where} GROUP BY compliance_state ORDER BY count DESC",
                $params
            ),
            'os_breakdown' => Database::fetchAll(
                "SELECT CONCAT(COALESCE(operating_system, 'Unknown'), ' ', COALESCE(os_version, '')) as os_label, COUNT(*) as count FROM intune_devices WHERE is_active = 1 AND {$where} GROUP BY operating_system, os_version ORDER BY count DESC",
                $params
            ),
            'total_devices' => (int)Database::fetch("SELECT COUNT(*) as count FROM intune_devices WHERE is_active = 1 AND {$where}", $params)['count'],
            'last_synced_at' => IntuneDevice::getLastSyncTime(),
        ]);
    }

    public function filters(): void
    {
        Response::success([
            'operating_systems' => array_column(IntuneDevice::getDistinctOs(), 'operating_system'),
            'device_types' => array_column(IntuneDevice::getDistinctDeviceTypes(), 'device_type'),
        ]);
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        $device = IntuneDevice::find($id);
        if (!$device) {
            Response::error('Device not found', 404);
        }
        if (!Auth::canAccessCustomer((int)$device['customer_id'])) {
            Response::error('Forbidden', 403);
        }
        IntuneDevice::delete($id);
        Logger::log('delete', 'intune_devices', $id, 'Intune device deleted');
        Response::success(null, 'Device removed from local cache');
    }

    public function clearAll(): void
    {
        $scope = Auth::customerScope();
        if ($scope['sql'] !== '1=1') {
            Database::delete('intune_devices', $scope['sql'], $scope['params']);
        } else {
            IntuneDevice::clearAll();
        }
        Logger::log('delete', 'intune_devices', 0, 'Intune devices cleared');
        Response::success(null, 'Devices cleared');
    }

    public function debug(): void
    {
        $checks = [
            'php_version' => phpversion(),
            'curl_loaded' => extension_loaded('curl'),
            'curl_functions' => function_exists('curl_init') && function_exists('curl_exec'),
            'json_loaded' => extension_loaded('json'),
            'openssl_loaded' => extension_loaded('openssl'),
            'config_file_exists' => file_exists(__DIR__ . '/../config/intune.php'),
            'service_file_exists' => file_exists(__DIR__ . '/../services/IntuneService.php'),
        ];

        if ($checks['curl_loaded']) {
            $ch = curl_init('https://login.microsoftonline.com/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $result = curl_exec($ch);
            $info = curl_getinfo($ch);
            $error = curl_error($ch);
            curl_close($ch);
            $checks['microsoft_connectivity'] = $info['http_code'] > 0 ? "HTTP {$info['http_code']}" : "ERROR: $error";
        } else {
            $checks['microsoft_connectivity'] = 'SKIPPED (no curl)';
        }

        Response::success($checks);
    }
}
