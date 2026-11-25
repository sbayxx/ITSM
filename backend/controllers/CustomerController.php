<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/Customer.php';

class CustomerController
{
    public function index(Request $request): void
    {
        $user = Auth::user();
        if ($user['role'] === 'admin') {
            $result = Customer::all(array_filter([
                'search' => $request->query('search'),
                'is_active' => $request->query('is_active'),
            ]));
            Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
        }

        $ids = Auth::accessibleCustomerIds();
        if (empty($ids)) {
            Response::paginated([], 0, 1, 20);
        }

        [$page, $perPage] = Pagination::params();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $total = Database::fetch("SELECT COUNT(*) as count FROM customers WHERE id IN ({$placeholders})", $ids)['count'];
        $rows = Database::fetchAll(
            "SELECT * FROM customers WHERE id IN ({$placeholders}) ORDER BY name ASC LIMIT {$perPage} OFFSET ?",
            array_merge($ids, [($page - 1) * $perPage])
        );
        Response::paginated($rows, $total, $page, $perPage);
    }

    public function show(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!Auth::canAccessCustomer($id)) {
            Response::error('Forbidden', 403);
        }
        $customer = Customer::find($id);
        if (!$customer) {
            Response::error('Customer not found', 404);
        }
        $settings = Customer::getSettings($id);
        $customer['settings'] = $settings;
        $customer['assigned_user_ids'] = Customer::getAssignedUserIds($id);
        Response::success($customer);
    }

    public function store(Request $request): void
    {
        if (Auth::role() !== 'admin') {
            Response::error('Forbidden', 403);
        }
        $allowed = ['name', 'slug', 'description', 'logo_url', 'is_active', 'settings'];
        $data = array_intersect_key($request->all(), array_flip($allowed));
        $v = new Validator($data);
        $v->required('name');
        $v->validate();
        $id = Customer::create($data);
        Logger::log('create', 'customers', $id, 'Customer created: ' . $data['name']);
        Response::success(Customer::find($id), 'Customer created', 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!Auth::canAccessCustomer($id)) {
            Response::error('Forbidden', 403);
        }
        $customer = Customer::find($id);
        if (!$customer) {
            Response::error('Customer not found', 404);
        }
        $allowed = ['name', 'slug', 'description', 'is_active'];
        $update = array_intersect_key($request->all(), array_flip($allowed));
        if (!empty($update)) {
            Customer::update($id, $update);
            Logger::log('update', 'customers', $id, 'Customer updated');
        }
        Response::success(Customer::find($id), 'Customer updated');
    }

    public function destroy(Request $request): void
    {
        if (Auth::role() !== 'admin') {
            Response::error('Forbidden', 403);
        }
        $id = (int)$request->param('id');
        $customer = Customer::find($id);
        if (!$customer) {
            Response::error('Customer not found', 404);
        }
        Customer::delete($id);
        Logger::log('delete', 'customers', $id, 'Customer deleted: ' . $customer['name']);
        Response::success(null, 'Customer deleted');
    }

    public function getSettings(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!Auth::canAccessCustomer($id)) {
            Response::error('Forbidden', 403);
        }
        $customer = Customer::find($id);
        if (!$customer) {
            Response::error('Customer not found', 404);
        }
        Response::success(Customer::getSettings($id));
    }

    public function updateSettings(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!Auth::canAccessCustomer($id)) {
            Response::error('Forbidden', 403);
        }
        $customer = Customer::find($id);
        if (!$customer) {
            Response::error('Customer not found', 404);
        }
        $settings = $request->all();
        Customer::updateSettings($id, $settings);
        Logger::log('update', 'customers', $id, 'Customer settings updated');
        Response::success(Customer::getSettings($id), 'Settings updated');
    }

    public function assignUsers(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!Auth::canAccessCustomer($id)) {
            Response::error('Forbidden', 403);
        }
        $customer = Customer::find($id);
        if (!$customer) {
            Response::error('Customer not found', 404);
        }
        $userIds = $request->input('user_ids', []);
        Customer::assignUsers($id, $userIds);
        Logger::log('update', 'customers', $id, 'Customer users assigned');
        Response::success(Customer::getAssignedUserIds($id), 'Users assigned');
    }

    public function getUsers(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!Auth::canAccessCustomer($id)) {
            Response::error('Forbidden', 403);
        }
        $users = User::getCustomerUsers($id);
        $assignedIds = Customer::getAssignedUserIds($id);
        Response::success([
            'users' => $users,
            'assigned_ids' => $assignedIds,
        ]);
    }

    public function myCustomers(): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::error('Unauthorized', 401);
        }
        $ids = Auth::accessibleCustomerIds();
        if (empty($ids)) {
            Response::success([]);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::fetchAll(
            "SELECT id, name, slug FROM customers WHERE id IN ({$placeholders}) AND is_active = 1 ORDER BY name",
            $ids
        );
        Response::success($rows);
    }
}
