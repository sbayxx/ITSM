<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../models/Approval.php';

class ApprovalController
{
    public function index(Request $request): void
    {
        $ticketId = (int)$request->query('ticket_id');
        if (!$ticketId) {
            Response::error('ticket_id is required', 422);
        }
        $approvals = Approval::findByTicket($ticketId);
        Response::success($approvals);
    }

    public function approve(Request $request): void
    {
        $id = (int)$request->param('id');
        $approval = $this->resolveDecidable($id);
        $comments = $request->input('comments', '');
        Approval::approve($id, Auth::id(), $comments);
        Logger::log('approve', 'approvals', $id, 'Approval step approved for ticket #' . $approval['ticket_id']);
        Response::success(null, 'Approved');
    }

    public function reject(Request $request): void
    {
        $id = (int)$request->param('id');
        $approval = $this->resolveDecidable($id);
        $comments = $request->input('comments', '');
        if (!$comments) {
            Response::error('Comments are required when rejecting', 422);
        }
        Approval::reject($id, Auth::id(), $comments);
        Logger::log('reject', 'approvals', $id, 'Approval step rejected for ticket #' . $approval['ticket_id']);
        Response::success(null, 'Rejected');
    }

    /**
     * Shared guard chain for approve/reject. Responds and exits on any failure,
     * otherwise returns the approval row ready to be decided.
     */
    private function resolveDecidable(int $id): array
    {
        $approval = Approval::find($id);
        if (!$approval) {
            Response::error('Approval not found', 404);
        }
        $guard = Approval::guardDecision($approval);
        if ($guard !== null) {
            Response::error($guard[0], $guard[1]);
        }
        return $approval;
    }
}
