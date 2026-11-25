<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/ServiceRequest.php';
require_once __DIR__ . '/../models/Approval.php';

class ServiceRequestController
{
    public function approve(Request $request): void
    {
        $id = (int)$request->param('id');
        $sr = ServiceRequest::find($id);
        if (!$sr) {
            Response::error('Service request not found', 404);
        }
        $step = $this->resolvePendingStep((int)$sr['ticket_id']);
        Approval::approve($step['id'], Auth::id());
        Logger::log('approve', 'service_requests', $id, 'Service request approved');
        Response::success(null, 'Service request approved');
    }

    public function reject(Request $request): void
    {
        $id = (int)$request->param('id');
        $sr = ServiceRequest::find($id);
        if (!$sr) {
            Response::error('Service request not found', 404);
        }
        $notes = $request->input('notes', '');
        if (!$notes) {
            Response::error('Reason is required when rejecting', 422);
        }
        $step = $this->resolvePendingStep((int)$sr['ticket_id']);
        Approval::reject($step['id'], Auth::id(), $notes);
        Logger::log('reject', 'service_requests', $id, 'Service request rejected');
        Response::success(null, 'Service request rejected');
    }

    public function catalog(Request $request): void
    {
        $items = Database::fetchAll("SELECT * FROM service_catalog WHERE is_active = 1 ORDER BY category, name");
        Response::success($items);
    }

    /**
     * Resolves the single active step of the chain and applies the shared
     * Approval guard, so these legacy endpoints enforce exactly the same
     * ordering, separation-of-duties and authorisation rules as /approvals/*.
     */
    private function resolvePendingStep(int $ticketId): array
    {
        $approvals = Approval::findByTicket($ticketId);
        $pending = array_values(array_filter($approvals, fn($a) => $a['status'] === 'pending'));
        if (empty($pending)) {
            Response::error('No pending approvals', 422);
        }
        $step = Approval::find((int)$pending[0]['id']);
        if (!$step) {
            Response::error('Approval not found', 404);
        }
        $guard = Approval::guardDecision($step);
        if ($guard !== null) {
            Response::error($guard[0], $guard[1]);
        }
        return $step;
    }
}
