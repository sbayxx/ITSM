<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/ManualDevice.php';
require_once __DIR__ . '/../models/Customer.php';

class ManualDeviceController
{
    public function index(Request $request): void
    {
        $filters = [
            'customer_id' => $request->query('customer_id'),
            'device_type' => $request->query('device_type'),
            'status' => $request->query('status'),
            'search' => $request->query('search'),
        ];
        $result = ManualDevice::all(array_filter($filters));
        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): void
    {
        $device = ManualDevice::find((int)$request->param('id'));
        if (!$device) {
            Response::error('Device not found', 404);
        }
        if (!Auth::canAccessCustomer((int)$device['customer_id'])) {
            Response::error('Forbidden', 403);
        }
        Response::success($device);
    }

    public function store(Request $request): void
    {
        $data = $request->all();
        $customerId = (int)($data['customer_id'] ?? 0);
        if (!$customerId) {
            Response::error('customer_id is required', 400);
        }
        if (!Auth::canAccessCustomer($customerId)) {
            Response::error('Forbidden', 403);
        }
        if (empty($data['name'])) {
            Response::error('name is required', 400);
        }

        $id = ManualDevice::create($data);
        Logger::log('create', 'manual_devices', $id, 'Manual device created: ' . $data['name']);
        Response::success(ManualDevice::find($id), 'Device created', 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $device = ManualDevice::find($id);
        if (!$device) {
            Response::error('Device not found', 404);
        }
        if (!Auth::canAccessCustomer((int)$device['customer_id'])) {
            Response::error('Forbidden', 403);
        }

        $data = $request->all();
        if (isset($data['customer_id']) && !Auth::canAccessCustomer((int)$data['customer_id'])) {
            Response::error('Forbidden', 403);
        }

        ManualDevice::update($id, $data);
        Logger::log('update', 'manual_devices', $id, 'Manual device updated');
        Response::success(ManualDevice::find($id), 'Device updated');
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        $device = ManualDevice::find($id);
        if (!$device) {
            Response::error('Device not found', 404);
        }
        if (!Auth::canAccessCustomer((int)$device['customer_id'])) {
            Response::error('Forbidden', 403);
        }
        ManualDevice::delete($id);
        Logger::log('delete', 'manual_devices', $id, 'Manual device deleted');
        Response::success(null, 'Device deleted');
    }

    public function filters(): void
    {
        Response::success([
            'device_types' => ManualDevice::getDistinctDeviceTypes(),
        ]);
    }
}
