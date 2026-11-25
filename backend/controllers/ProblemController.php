<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Problem.php';

class ProblemController
{
    public function linkIncidents(Request $request): void
    {
        $id = (int)$request->param('id');
        $problem = Problem::find($id);
        if (!$problem) {
            Response::error('Problem not found', 404);
        }
        $incidentIds = $request->input('incident_ids', []);
        if (empty($incidentIds)) {
            Response::error('incident_ids required', 422);
        }
        Problem::linkIncidents($id, $incidentIds);
        Logger::log('link_incidents', 'problems', $id, 'Linked incidents to problem');
        Response::success(Problem::find($id), 'Incidents linked');
    }

    public function markKnownError(Request $request): void
    {
        $id = (int)$request->param('id');
        $problem = Problem::find($id);
        if (!$problem) {
            Response::error('Problem not found', 404);
        }
        $errorId = $request->input('known_error_id', 'KE-' . date('y') . '-' . $id);
        Problem::markKnownError($id, $errorId);
        Logger::log('known_error', 'problems', $id, "Marked as known error: {$errorId}");
        Response::success(Problem::find($id), 'Marked as known error');
    }
}
