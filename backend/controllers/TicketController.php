<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/Incident.php';
require_once __DIR__ . '/../models/ServiceRequest.php';
require_once __DIR__ . '/../models/Problem.php';
require_once __DIR__ . '/../models/Change.php';
require_once __DIR__ . '/../models/TicketCategory.php';
require_once __DIR__ . '/../models/Approval.php';
require_once __DIR__ . '/../services/MailService.php';

class TicketController
{
    public function index(Request $request): void
    {
        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'assigned_to' => $request->query('assigned_to'),
            'reported_by' => $request->query('reported_by'),
            'search' => $request->query('search'),
            'organization_id' => $request->query('organization_id'),
            'customer_id' => $request->query('customer_id'),
            'sla_breached' => $request->query('sla_breached'),
        ];
        $result = Ticket::all(array_filter($filters));
        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): void
    {
        $ticket = Ticket::find((int)$request->param('id'));
        if (!$ticket) {
            Response::error('Ticket not found', 404);
        }
        // The detail view renders the catalog item's field definitions above
        // the stored answers (service_requests.fulfillment_data), so the item
        // must travel with the ticket.
        $catalogId = (int)($ticket['service_catalog_item_id'] ?? 0);
        if ($catalogId) {
            $ticket['catalog_item'] = Database::fetch(
                "SELECT * FROM service_catalog WHERE id = ?",
                [$catalogId]
            );
        }
        Response::success($ticket);
    }

    public function store(Request $request): void
    {
        $data = $request->all();
        $v = new Validator($data);
        $v->required('type')->inArray('type', ['incident','service_request','problem','change'])
          ->required('title');
        $v->validate();
        $type = $data['type'];
        $typeFields = $data['type_data'] ?? [];
        // The catalog item arrives under three different keys depending on the client
        // (service_catalog_item_id on the ticket, catalog_item_id from the request form,
        // type_data.catalog_item_id from the SR model). Normalise before use, otherwise
        // the approval chain is never generated and the request is silently auto-approved.
        $catalogItemId = $data['service_catalog_item_id'] ?? $data['catalog_item_id'] ?? ($typeFields['catalog_item_id'] ?? null);
        $catalogItemId = $catalogItemId ? (int)$catalogItemId : null;
        $categoryError = TicketCategory::validate($type, $data['category'] ?? null, $data['subcategory'] ?? null);
        if ($categoryError !== null) {
            Response::error($categoryError, 422);
        }
        $ticketData = [
            'type' => $type,
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'impact' => $data['impact'] ?? 'medium',
            'urgency' => $data['urgency'] ?? 'medium',
            'category' => $data['category'] ?? '',
            'subcategory' => $data['subcategory'] ?? '',
            'source' => $data['source'] ?? 'portal',
            'reported_by' => $request->input('reported_by') ? (int)$request->input('reported_by') : Auth::id(),
            'requester_id' => $data['requester_id'] ?? null,
            'organization_id' => $data['organization_id'] ?? null,
            'ci_item_id' => $data['ci_item_id'] ?? null,
            'service_catalog_item_id' => $catalogItemId,
            'customer_id' => $data['customer_id'] ?? null,
            'status' => 'new',
        ];
        $ticketId = Ticket::create($ticketData);
        switch ($type) {
            case 'incident':
                $incidentData = array_intersect_key($typeFields, array_flip(['impact','urgency','is_major','major_criteria']));
                Incident::create($ticketId, $incidentData);
                break;
            case 'service_request':
                // Catalog item answers arrive as type_data.dynamic_fields (or a legacy
                // top-level dynamic_fields key) and are persisted in fulfillment_data.
                $dynamicFields = $typeFields['dynamic_fields'] ?? $data['dynamic_fields'] ?? [];
                if (!is_array($dynamicFields)) $dynamicFields = [];
                $fulfillmentData = $typeFields['fulfillment_data'] ?? [];
                if (!is_array($fulfillmentData)) $fulfillmentData = [];
                $srData = [
                    'catalog_item_id' => $catalogItemId,
                    'fulfillment_data' => $dynamicFields + $fulfillmentData,
                ];
                ServiceRequest::create($ticketId, $srData);
                Approval::generateForServiceRequest($ticketId, $catalogItemId);
                break;
            case 'problem':
                $problemData = array_intersect_key($typeFields, array_flip(['known_error','known_error_id']));
                Problem::create($ticketId, $problemData);
                break;
            case 'change':
                $changeData = array_intersect_key($typeFields, array_flip(['change_type','risk_level','impact_assessment','rollback_plan','implementation_plan','test_plan','cab_required','scheduled_start','scheduled_end']));
                $approvalChain = $typeFields['approval_chain'] ?? null;
                Change::create($ticketId, $changeData);
                Approval::generateForChange($ticketId, array_merge($changeData, ['approval_chain' => $approvalChain]));
                break;
        }
        $ticket = Ticket::find($ticketId);
        Logger::log('create', 'tickets', $ticketId, 'Ticket created: ' . $ticket['ticket_number']);
        MailService::sendTicketCreated($ticketId);
        Response::success($ticket, 'Ticket created', 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $oldTicket = Ticket::find($id);
        if (!$oldTicket) {
            Response::error('Ticket not found', 404);
        }
        $data = $request->all();
        $newCategory = array_key_exists('category', $data) ? (string)$data['category'] : null;
        $newSub = array_key_exists('subcategory', $data) ? (string)$data['subcategory'] : null;
        $oldCategory = (string)($oldTicket['category'] ?? '');
        $oldSub = (string)($oldTicket['subcategory'] ?? '');
        if (($newCategory !== null && $newCategory !== $oldCategory) || ($newSub !== null && $newSub !== $oldSub)) {
            $categoryError = TicketCategory::validate(
                $oldTicket['type'],
                $newCategory ?? $oldCategory,
                $newSub ?? $oldSub
            );
            if ($categoryError !== null) {
                Response::error($categoryError, 422);
            }
        }
        $allowed = ['title','description','status','priority','impact','urgency','category','subcategory',
                     'assigned_to','assigned_group','resolution','closure_code','closure_notes',
                     'ci_item_id','service_catalog_item_id','external_ref'];
        $update = array_intersect_key($data, array_flip($allowed));
        $changes = [];
        if (!empty($update)) {
            foreach ($update as $field => $newVal) {
                $oldVal = $oldTicket[$field] ?? null;
                if ((string)$oldVal !== (string)$newVal) {
                    $changes[$field] = ['old' => $oldVal, 'new' => $newVal];
                }
            }
            Ticket::update($id, $update);
        }
        if (!empty($data['type_data'])) {
            $typeModel = [
                'incident' => 'Incident',
                'service_request' => 'ServiceRequest',
                'problem' => 'Problem',
                'change' => 'Change',
            ][$oldTicket['type']] ?? null;
            if ($typeModel && $oldTicket['type_data']) {
                $class = new $typeModel();
                $typeUpdate = array_intersect_key($data['type_data'], array_flip(
                    $typeModel === 'Change' ? ['change_type','risk_level','impact_assessment','rollback_plan','implementation_plan','test_plan','cab_required','scheduled_start','scheduled_end']
                    : ($typeModel === 'Incident' ? ['impact','urgency','is_major','workaround','root_cause']
                    : ($typeModel === 'Problem' ? ['root_cause','workaround','known_error','known_error_id']
                    : ['approval_status','fulfillment_notes']))
                ));
                if (!empty($typeUpdate)) {
                    call_user_func([$class, 'update'], $oldTicket['type_data']['id'], $typeUpdate);
                }
            }
        }
        $ticket = Ticket::find($id);
        Logger::log('update', 'tickets', $id, 'Ticket updated: ' . $ticket['ticket_number']);
        if (!empty($changes)) {
            MailService::sendTicketUpdated($id, $changes);
        }
        Response::success($ticket, 'Ticket updated');
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        $ticket = Ticket::find($id);
        if (!$ticket) {
            Response::error('Ticket not found', 404);
        }
        // Ticket::delete() writes the audit_log entry itself; logging here as well
        // produced a duplicate row per deletion.
        Ticket::delete($id);
        Response::success(null, 'Ticket deleted');
    }

    public function addComment(Request $request): void
    {
        $id = (int)$request->param('id');
        $ticket = Ticket::find($id);
        if (!$ticket) {
            Response::error('Ticket not found', 404);
        }
        $body = $request->input('body');
        $isInternal = (bool)$request->input('is_internal', false);
        if (!$body) {
            Response::error('Comment body is required', 422);
        }
        // Writing is gated exactly like reading: only roles that may see internal
        // notes may author them, otherwise a plain user could inject "internal"
        // commentary that agents would read as privileged.
        if ($isInternal && !Ticket::canSeeInternalNotes()) {
            Response::error('You are not allowed to add internal notes', 403);
        }
        $commentId = Ticket::addComment($id, $body, $isInternal);
        $ticket = Ticket::find($id);
        Logger::log('add_comment', 'tickets', $id, 'Comment added to ' . $ticket['ticket_number']);
        if (!$isInternal) {
            MailService::sendCommentAdded($id, [
                'id' => $commentId,
                'body' => $body,
                'user_name' => Auth::user()['name'] ?? 'Agent',
            ]);
        }
        Response::success($ticket['comments'], 'Comment added');
    }

    public function dashboard(Request $request): void
    {
        $stats = Ticket::getDashboardStats();
        Response::success($stats);
    }
}
