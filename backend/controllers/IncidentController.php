<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/Ticket.php';
require_once __DIR__ . '/../models/Incident.php';

class IncidentController
{
    public function escalate(Request $request): void
    {
        $id = (int)$request->param('id');
        $incident = Incident::find($id);
        if (!$incident) {
            Response::error('Incident not found', 404);
        }
        Incident::escalate($id);
        Logger::log('escalate', 'incidents', $id, 'Incident escalated to major');
        Response::success(Incident::find($id), 'Incident escalated');
    }

    public function linkToProblem(Request $request): void
    {
        $id = (int)$request->param('id');
        $problemId = (int)$request->input('problem_id');
        $incident = Incident::find($id);
        if (!$incident) {
            Response::error('Incident not found', 404);
        }
        Incident::linkToProblem($id, $problemId);
        Logger::log('link_problem', 'incidents', $id, "Linked to problem #{$problemId}");
        Response::success(null, 'Linked to problem');
    }
}
