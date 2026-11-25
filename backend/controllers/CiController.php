<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/CiItem.php';

class CiController
{
    private const TYPE_FIELDS = ['name','slug','description','icon','parent_id','color','is_active'];

    private const EDITABLE_FIELDS = [
        'name','type','ci_type_id','status','serial_number','asset_tag',
        'vendor','model','fqdn','ip_address','mac_address',
        'os','os_version','cpu','ram','disk_space',
        'version','location','department','support_group',
        'business_service','environment',
        'purchase_date','warranty_end',
        'owner_id','description','notes','is_active'
    ];

    public function index(Request $request): void
    {
        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'environment' => $request->query('environment'),
            'business_service' => $request->query('business_service'),
            'search' => $request->query('search'),
            'customer_id' => $request->query('customer_id'),
        ];
        $result = CiItem::all(array_filter($filters));
        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): void
    {
        $ci = CiItem::find((int)$request->param('id'));
        if (!$ci) {
            Response::error('CI item not found', 404);
        }
        Response::success($ci);
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
        $v->required('name')->required('type');
        $v->validate();
        $id = CiItem::create($data);
        Logger::log('create', 'ci_items', $id, 'CI created: ' . $data['name']);
        Response::success(CiItem::find($id), 'CI item created', 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $ci = CiItem::find($id);
        if (!$ci) {
            Response::error('CI item not found', 404);
        }
        $update = array_intersect_key($request->all(), array_flip(self::EDITABLE_FIELDS));
        if (!empty($update)) {
            CiItem::update($id, $update);
            Logger::log('update', 'ci_items', $id, 'CI updated');
        }
        Response::success(CiItem::find($id), 'CI item updated');
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        if (!CiItem::find($id)) {
            Response::error('CI item not found', 404);
        }
        CiItem::delete($id);
        Logger::log('delete', 'ci_items', $id, 'CI deleted');
        Response::success(null, 'CI item deleted');
    }

    public function relate(Request $request): void
    {
        $parentId = (int)$request->input('parent_ci_id');
        $childId = (int)$request->input('child_ci_id');
        if (!CiItem::find($parentId) || !CiItem::find($childId)) {
            Response::error('CI not found or unauthorized', 404);
        }
        $type = $request->input('relationship_type', 'depends_on');
        CiItem::relate($parentId, $childId, $type);
        Logger::log('relate', 'ci_items', $parentId, "Related CI #{$parentId} to #{$childId}");
        Response::success(null, 'Relationship created');
    }

    public function unrelate(Request $request): void
    {
        $parentId = (int)$request->input('parent_ci_id');
        $childId = (int)$request->input('child_ci_id');
        if (!CiItem::find($parentId) || !CiItem::find($childId)) {
            Response::error('CI not found or unauthorized', 404);
        }
        $type = $request->input('relationship_type', 'depends_on');
        CiItem::unrelate($parentId, $childId, $type);
        Response::success(null, 'Relationship removed');
    }

    public function types(): void
    {
        Response::success(CiItem::getTypes());
    }

    // --- CI Types Management ---

    public function ciTypeIndex(): void
    {
        $types = Database::fetchAll(
            "SELECT ct.*, p.name as parent_name
             FROM ci_types ct
             LEFT JOIN ci_types p ON ct.parent_id = p.id
             WHERE ct.is_active = 1
             ORDER BY ct.name"
        );
        Response::success($types);
    }

    public function ciTypeStore(Request $request): void
    {
        $data = array_intersect_key($request->all(), array_flip(self::TYPE_FIELDS));
        $v = new Validator($data);
        $v->required('name')->required('slug');
        $v->validate();
        $id = Database::insert('ci_types', $data);
        Logger::log('create', 'ci_types', $id, 'CI type created: ' . $data['name']);
        Response::success(Database::fetch("SELECT * FROM ci_types WHERE id = ?", [$id]), 'CI type created', 201);
    }

    public function ciTypeUpdate(Request $request): void
    {
        $id = (int)$request->param('id');
        $type = Database::fetch("SELECT * FROM ci_types WHERE id = ?", [$id]);
        if (!$type) {
            Response::error('CI type not found', 404);
        }
        $update = array_intersect_key($request->all(), array_flip(self::TYPE_FIELDS));
        if (!empty($update)) {
            Database::update('ci_types', $update, 'id = ?', [$id]);
            Logger::log('update', 'ci_types', $id, 'CI type updated');
        }
        Response::success(Database::fetch("SELECT * FROM ci_types WHERE id = ?", [$id]), 'CI type updated');
    }

    public function ciTypeDestroy(Request $request): void
    {
        $id = (int)$request->param('id');
        Database::delete('ci_types', 'id = ?', [$id]);
        Logger::log('delete', 'ci_types', $id, 'CI type deleted');
        Response::success(null, 'CI type deleted');
    }

    // --- CI Audit ---

    public function auditLog(Request $request): void
    {
        $ciId = (int)$request->param('id');
        if (!CiItem::find($ciId)) {
            Response::error('CI item not found', 404);
        }
        Response::success(CiItem::getCiAuditLog($ciId));
    }

    // --- CI Tags ---

    public function tagIndex(): void
    {
        $tags = Database::fetchAll("SELECT * FROM ci_tags ORDER BY name");
        Response::success($tags);
    }

    public function tagStore(Request $request): void
    {
        $data = array_intersect_key($request->all(), ['name' => true, 'color' => true]);
        $v = new Validator($data);
        $v->required('name');
        $v->validate();
        $id = Database::insert('ci_tags', $data);
        Response::success(Database::fetch("SELECT * FROM ci_tags WHERE id = ?", [$id]), 'Tag created', 201);
    }

    public function tagUpdate(Request $request): void
    {
        $id = (int)$request->param('id');
        $tag = Database::fetch("SELECT * FROM ci_tags WHERE id = ?", [$id]);
        if (!$tag) {
            Response::error('Tag not found', 404);
        }
        $data = array_intersect_key($request->all(), ['name' => true, 'color' => true]);
        $v = new Validator($data);
        $v->required('name');
        $v->validate();
        Database::update('ci_tags', $data, 'id = ?', [$id]);
        Logger::log('update', 'ci_tags', $id, 'Tag updated: ' . $data['name']);
        Response::success(Database::fetch("SELECT * FROM ci_tags WHERE id = ?", [$id]), 'Tag updated');
    }

    public function tagDestroy(Request $request): void
    {
        $id = (int)$request->param('id');
        Database::delete('ci_tags', 'id = ?', [$id]);
        Logger::log('delete', 'ci_tags', $id, 'Tag deleted');
        Response::success(null, 'Tag deleted');
    }

    public function setCiTags(Request $request): void
    {
        $ciId = (int)$request->param('id');
        if (!CiItem::find($ciId)) {
            Response::error('CI item not found', 404);
        }
        $tagIds = $request->input('tag_ids', []);
        CiItem::setTags($ciId, $tagIds);
        Response::success(null, 'Tags updated');
    }
}
