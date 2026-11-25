<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../models/Vendor.php';

class VendorController
{
    public function index(Request $request): void
    {
        Response::success(Vendor::all([
            'customer_id' => $request->query('customer_id'),
            'is_active' => $request->query('is_active'),
        ]));
    }

    public function show(Request $request): void
    {
        $vendor = Vendor::find((int)$request->param('id'));
        if (!$vendor) Response::error('Vendor not found', 404);
        Response::success($vendor);
    }

    public function store(Request $request): void
    {
        $this->requireManager();
        $data = $request->all();
        $customerId = (int)($data['customer_id'] ?? 0);
        if (!$customerId) Response::error('customer_id is required', 422);
        if (!Auth::canAccessCustomer($customerId)) Response::error('Forbidden', 403);
        if (empty($data['name'])) Response::error('name is required', 422);
        $error = $this->validate($data);
        if ($error) Response::error($error, 422);

        $id = Vendor::create($data);
        Logger::log('create', 'vendors', $id, 'Vendor created: ' . $data['name']);
        Response::success(Vendor::find($id), 'Vendor created', 201);
    }

    public function update(Request $request): void
    {
        $this->requireManager();
        $id = (int)$request->param('id');
        $vendor = Vendor::find($id);
        if (!$vendor) Response::error('Vendor not found', 404);
        $data = $request->all();
        if (isset($data['customer_id']) && !Auth::canAccessCustomer((int)$data['customer_id'])) {
            Response::error('Forbidden', 403);
        }
        $error = $this->validate($data + $vendor);
        if ($error) Response::error($error, 422);

        Vendor::update($id, $data);
        Logger::log('update', 'vendors', $id, 'Vendor updated: ' . $vendor['name']);
        Response::success(Vendor::find($id), 'Vendor updated');
    }

    public function destroy(Request $request): void
    {
        $this->requireManager();
        $id = (int)$request->param('id');
        $vendor = Vendor::find($id);
        if (!$vendor) Response::error('Vendor not found', 404);
        Vendor::deactivate($id);
        Vendor::revokeApiKey($id);
        Logger::log('delete', 'vendors', $id, 'Vendor deactivated: ' . $vendor['name']);
        Response::success(null, 'Vendor deactivated');
    }

    /** POST /api/vendors/{id}/api-key — returns the plain key once. */
    public function createKey(Request $request): void
    {
        $this->requireManager();
        $id = (int)$request->param('id');
        $vendor = Vendor::find($id);
        if (!$vendor) Response::error('Vendor not found', 404);
        $plain = Vendor::createApiKey($id);
        Logger::log('create', 'vendor_api_keys', $id, 'API key issued for vendor: ' . $vendor['name']);
        Response::success(['api_key' => $plain, 'key_prefix' => substr($plain, 0, 10)], 'Store this key now. It will not be shown again.', 201);
    }

    public function revokeKey(Request $request): void
    {
        $this->requireManager();
        $id = (int)$request->param('id');
        $vendor = Vendor::find($id);
        if (!$vendor) Response::error('Vendor not found', 404);
        Vendor::revokeApiKey($id);
        Logger::log('delete', 'vendor_api_keys', $id, 'API key revoked for vendor: ' . $vendor['name']);
        Response::success(null, 'API key revoked');
    }

    private function requireManager(): void
    {
        if (!Auth::isAdmin() && !Auth::isCustomerAdmin()) {
            Response::error('Forbidden: insufficient permissions', 403);
        }
    }

    /**
     * The server calls this URL, so it must not point inside the network (SSRF).
     * Only https to a public host is accepted. DNS-rebinding is not covered here.
     */
    public static function checkApiUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return 'api_base_url must be an https URL';
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.internal')) {
            return 'api_base_url must point to a public host';
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $public = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if ($public === false) return 'api_base_url must point to a public host';
        }
        return null;
    }

    private function validate(array $data): ?string
    {
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address';
        }
        if (!empty($data['api_base_url'])) {
            $urlError = self::checkApiUrl((string)$data['api_base_url']);
            if ($urlError) return $urlError;
        }
        if (!empty($data['integration_mode']) && !in_array($data['integration_mode'], Vendor::MODES, true)) {
            return 'Invalid integration_mode';
        }
        if (in_array($data['integration_mode'] ?? 'email', ['api', 'both'], true) && empty($data['api_base_url'])) {
            return 'api_base_url is required for api/both mode';
        }
        if (in_array($data['integration_mode'] ?? 'email', ['email', 'both'], true) && empty($data['email'])) {
            return 'email is required for email/both mode';
        }
        return null;
    }
}
