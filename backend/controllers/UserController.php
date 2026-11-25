<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/User.php';

class UserController
{
    public function index(Request $request): void
    {
        $filters = [
            'search' => $request->query('search'),
            'role' => $request->query('role'),
            'is_active' => $request->query('is_active'),
        ];
        $result = User::all(array_filter($filters));
        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): void
    {
        $user = User::find((int)$request->param('id'));
        if (!$user) {
            Response::error('User not found', 404);
        }
        $user['permissions'] = User::getPermissions($user['id']);
        Response::success($user);
    }

    public function store(Request $request): void
    {
        $data = $request->all();
        $v = new Validator($data);
        $v->required('name')->required('email')->email('email')->required('password')->min('password', 12)
          ->regex('password', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])/', 'Password', 'Password must be at least 12 characters and contain uppercase, lowercase, digit, and special character')
          ->required('password_confirmation')->matches('password', 'password_confirmation', 'Passwords do not match')
          ->required('role')->inArray('role', ['admin','agent','manager','user'])->unique('email', 'users', 'email');
        $v->validate();

        // Whitelist real columns: passing the raw request body sent
        // password_confirmation (and other non-columns) straight into the INSERT.
        $create = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ];
        foreach (['phone', 'job_title', 'language', 'organization_id', 'customer_id', 'is_active'] as $opt) {
            if (isset($data[$opt]) && $data[$opt] !== '') {
                $create[$opt] = in_array($opt, ['organization_id', 'customer_id', 'is_active'], true)
                    ? (int)$data[$opt]
                    : $data[$opt];
            }
        }
        $userId = User::create($create);
        Logger::log('create', 'users', $userId, 'User created by admin: ' . $data['email']);
        Response::success(User::find($userId), 'User created', 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $user = User::find($id);
        if (!$user) {
            Response::error('User not found', 404);
        }
        $data = $request->all();
        $allowed = ['name','email','role','phone','job_title','is_active','language','theme','customer_id'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (isset($update['email']) && $update['email'] !== $user['email']) {
            $v = new Validator(['email' => $update['email']]);
            $v->unique('email', 'users', 'email', $id);
            $v->validate();
        }
        if (!empty($data['password'])) {
            $v = new Validator(['password' => $data['password']]);
            $v->min('password', 12)->regex('password', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])/', 'Password', 'Password must be at least 12 characters and contain uppercase, lowercase, digit, and special character');
            $v->validate();
            $update['password'] = $data['password'];
        }
        if (!empty($update)) {
            $roleChanged = isset($update['role']) && $update['role'] !== $user['role'];
            $passwordChanged = !empty($data['password']);
            User::update($id, $update);
            if ($roleChanged && $id === Auth::id()) {
                Auth::regenerateSession();
            }
            if ($passwordChanged) {
                Auth::regenerateSession();
            }
            Logger::log('update', 'users', $id, 'User updated');
        }
        Response::success(User::find($id), 'User updated');
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        $user = User::find($id);
        if (!$user) {
            Response::error('User not found', 404);
        }
        if ($id === Auth::id()) {
            Response::error('Cannot delete yourself', 422);
        }
        User::softDelete($id);
        Logger::log('delete', 'users', $id, 'User soft-deleted');
        Response::success(null, 'User deleted');
    }

    public function roles(): void
    {
        $roles = Database::fetchAll("SELECT * FROM roles ORDER BY name");
        Response::success($roles);
    }

    public function userCustomers(Request $request): void
    {
        $id = (int)$request->param('id');
        $user = User::find($id);
        if (!$user) {
            Response::error('User not found', 404);
        }
        $pivotCustomers = Customer::getUserCustomers($id);
        Response::success([
            'customer_id' => $user['customer_id'],
            'customer_name' => $user['customer_name'] ?? null,
            'assigned_customers' => $pivotCustomers,
        ]);
    }

    public function updateUserCustomers(Request $request): void
    {
        $id = (int)$request->param('id');
        $user = User::find($id);
        if (!$user) {
            Response::error('User not found', 404);
        }
        $data = $request->all();

        if (array_key_exists('customer_id', $data)) {
            $customerId = $data['customer_id'] ? (int)$data['customer_id'] : null;
            User::updateCustomerAssignment($id, $customerId);
        }

        if (isset($data['assigned_customer_ids']) && is_array($data['assigned_customer_ids'])) {
            $ids = array_map('intval', $data['assigned_customer_ids']);
            Customer::assignToUser($id, $ids);
        }

        Logger::log('update', 'users', $id, 'User customer assignments updated');
        Response::success(User::find($id), 'Customer assignments updated');
    }

    public function agents(Request $request): void
    {
        $agents = Database::fetchAll(
            "SELECT id, name, email FROM users WHERE role IN ('agent','admin','manager') AND is_active = 1 AND deleted_at IS NULL ORDER BY name"
        );
        Response::success($agents);
    }

    public function syncAzure(Request $request): void
    {
        require_once __DIR__ . '/../services/IntuneService.php';
        require_once __DIR__ . '/../models/Customer.php';

        $customerId = (int)$request->input('customer_id', 0);
        $filters = [
            'licensed_only' => $request->input('licensed_only', true),
            'enabled_only' => $request->input('enabled_only', false),
            'user_type' => $request->input('user_type'),
            'department' => $request->input('department'),
        ];

        if ($customerId) {
            if (!Auth::canAccessCustomer($customerId)) {
                Response::error('Forbidden', 403);
            }
            $settings = Customer::getSettings($customerId);
            $azureConfig = $settings['azure_ad'] ?? [];
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
            $azureUsers = $service->fetchAzureUsers();
        } catch (RuntimeException $e) {
            Response::error('Azure sync failed: ' . $e->getMessage(), 502);
        }

        $imported = 0;
        $skipped = 0;
        $filteredOut = 0;
        $errors = [];

        foreach ($azureUsers as $au) {
            $skip = false;

            if (!empty($filters['licensed_only'])) {
                $licenses = $au['assignedLicenses'] ?? [];
                if (empty($licenses)) {
                    $filteredOut++;
                    continue;
                }
            }

            if (!empty($filters['enabled_only'])) {
                if (empty($au['accountEnabled'])) {
                    $filteredOut++;
                    continue;
                }
            }

            if (!empty($filters['user_type'])) {
                if (($au['userType'] ?? '') !== $filters['user_type']) {
                    $filteredOut++;
                    continue;
                }
            }

            if (!empty($filters['department'])) {
                if (($au['department'] ?? '') !== $filters['department']) {
                    $filteredOut++;
                    continue;
                }
            }

            $email = $au['mail'] ?? $au['userPrincipalName'] ?? null;
            if (!$email) {
                $skipped++;
                continue;
            }

            $existing = Database::fetch("SELECT id FROM users WHERE email = ? AND deleted_at IS NULL", [$email]);
            if ($existing) {
                Database::update('users', [
                    'microsoft_sub' => $au['id'],
                    'name' => $au['displayName'] ?? $au['id'],
                ], 'id = ?', [$existing['id']]);
                $skipped++;
                continue;
            }

            try {
                $isActive = !empty($au['accountEnabled']) ? 1 : 0;
                $userData = [
                    'name' => $au['displayName'] ?? $email,
                    'email' => $email,
                    'password' => Auth::hashPassword(bin2hex(random_bytes(16))),
                    'role' => 'user',
                    'microsoft_sub' => $au['id'],
                    'job_title' => $au['jobTitle'] ?? null,
                    'is_active' => $isActive,
                    'email_verified_at' => date('Y-m-d H:i:s'),
                ];
                if ($customerId) {
                    $userData['customer_id'] = $customerId;
                }
                $userId = Database::insert('users', $userData);
                Logger::log('create', 'users', $userId, 'User imported from Azure AD: ' . $email);
                $imported++;
            } catch (Exception $e) {
                $errors[] = $email . ': ' . $e->getMessage();
            }
        }

        Response::success([
            'imported' => $imported,
            'skipped' => $skipped,
            'filtered_out' => $filteredOut,
            'total_in_azure' => count($azureUsers),
            'errors' => $errors,
        ], "Imported {$imported} users from Azure AD ({$skipped} already existed, {$filteredOut} filtered out)");
    }
}
