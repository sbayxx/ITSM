<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/SlaContract.php';

class SlaController
{
    private const EDITABLE_FIELDS = ['name','priority','response_time_minutes','resolution_time_minutes','escalation_minutes','business_hours_only','is_active','description'];

    public function index(): void
    {
        Response::success(SlaContract::all());
    }

    public function show(Request $request): void
    {
        $sla = SlaContract::find((int)$request->param('id'));
        if (!$sla) {
            Response::error('SLA contract not found', 404);
        }
        Response::success($sla);
    }

    public function store(Request $request): void
    {
        $data = array_intersect_key($request->all(), array_flip(self::EDITABLE_FIELDS));
        $v = new Validator($data);
        $v->required('name')->required('priority')->required('response_time_minutes')->required('resolution_time_minutes');
        $v->inArray('priority', ['critical','high','medium','low']);
        $v->validate();
        $id = SlaContract::create($data);
        Logger::log('create', 'sla_contracts', $id, 'SLA contract created');
        Response::success(SlaContract::find($id), 'SLA contract created', 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $sla = SlaContract::find($id);
        if (!$sla) {
            Response::error('SLA contract not found', 404);
        }
        $update = array_intersect_key($request->all(), array_flip(self::EDITABLE_FIELDS));
        if (!empty($update)) {
            SlaContract::update($id, $update);
            Logger::log('update', 'sla_contracts', $id, 'SLA contract updated');
        }
        Response::success(SlaContract::find($id), 'SLA contract updated');
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        SlaContract::delete($id);
        Logger::log('delete', 'sla_contracts', $id, 'SLA contract deleted');
        Response::success(null, 'SLA contract deleted');
    }

    public function compliance(): void
    {
        Response::success(SlaContract::getComplianceReport());
    }
}
