<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/Change.php';

class ChangeController
{
    public function cabVote(Request $request): void
    {
        $id = (int)$request->param('id');
        $change = Change::find($id);
        if (!$change) {
            Response::error('Change not found', 404);
        }
        $vote = $request->input('vote');
        $comments = $request->input('comments');
        if (!in_array($vote, ['approve', 'reject', 'abstain'])) {
            Response::error('Invalid vote. Must be approve, reject, or abstain', 422);
        }
        Change::addCabVote($id, Auth::id(), $vote, $comments);
        Logger::log('cab_vote', 'changes', $id, "CAB vote: {$vote}");
        Response::success(Change::find($id), 'Vote recorded');
    }

    public function implement(Request $request): void
    {
        $id = (int)$request->param('id');
        $change = Change::find($id);
        if (!$change) {
            Response::error('Change not found', 404);
        }
        Change::implement($id);
        Logger::log('implement', 'changes', $id, 'Change implemented');
        Response::success(null, 'Change implemented');
    }

    public function review(Request $request): void
    {
        $id = (int)$request->param('id');
        $change = Change::find($id);
        if (!$change) {
            Response::error('Change not found', 404);
        }
        $notes = $request->input('review_notes', '');
        Change::review($id, $notes);
        Logger::log('review', 'changes', $id, 'Post-implementation review completed');
        Response::success(null, 'Review completed');
    }
}
