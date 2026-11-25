<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/CsiItem.php';

class CsiController
{
    private const EDITABLE_FIELDS = [
        'title','description','category','status','priority','expected_benefit',
        'kpi_measure','baseline_value','target_value','current_value',
        'assigned_to','review_date','action_items','source'
    ];

    public function index(Request $request): void
    {
        $filters = [
            'status' => $request->query('status'),
            'category' => $request->query('category'),
            'assigned_to' => $request->query('assigned_to'),
            'customer_id' => $request->query('customer_id'),
        ];
        $result = CsiItem::all(array_filter($filters));
        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): void
    {
        $item = CsiItem::find((int)$request->param('id'));
        if (!$item) {
            Response::error('CSI item not found', 404);
        }
        Response::success($item);
    }

    public function store(Request $request): void
    {
        $data = array_intersect_key($request->all(), array_flip([...self::EDITABLE_FIELDS, 'customer_id']));
        if (isset($data['customer_id']) && $data['customer_id'] !== '') {
            $customerId = filter_var($data['customer_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($customerId === false || !Auth::canAccessCustomer($customerId)) {
                Response::error('Forbidden', 403);
            }
            $data['customer_id'] = $customerId;
        } else {
            unset($data['customer_id']);
        }
        $v = new Validator($data);
        $v->required('title');
        $v->validate();
        $data['created_by'] = Auth::id();
        $id = CsiItem::create($data);
        Logger::log('create', 'csi_register', $id, 'CSI item created: ' . $data['title']);
        Response::success(CsiItem::find($id), 'CSI item created', 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $item = CsiItem::find($id);
        if (!$item) {
            Response::error('CSI item not found', 404);
        }
        $update = array_intersect_key($request->all(), array_flip(self::EDITABLE_FIELDS));
        if (!empty($update)) {
            CsiItem::update($id, $update);
            Logger::log('update', 'csi_register', $id, 'CSI item updated');
        }
        Response::success(CsiItem::find($id), 'CSI item updated');
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        $item = CsiItem::find($id);
        if (!$item) {
            Response::error('CSI item not found', 404);
        }
        CsiItem::delete($id);
        Logger::log('delete', 'csi_register', $id, 'CSI item deleted');
        Response::success(null, 'CSI item deleted');
    }

    public function kpiSummary(): void
    {
        Response::success(CsiItem::getKpiSummary());
    }
}
