<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/GdprModel.php';

class GdprController
{
    public function consent(Request $request): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::error('Unauthenticated', 401);
        }
        $type = $request->input('consent_type', 'general');
        $version = $request->input('consent_version', '1.0');
        $status = $request->input('status', 'granted');
        if (!in_array($status, ['granted', 'withdrawn'])) {
            Response::error('Invalid status', 422);
        }
        GdprModel::recordConsent($userId, $type, $version, $status);
        Logger::log('gdpr_consent', 'users', $userId, "GDPR consent {$status}: {$type} v{$version}");
        Response::success(null, 'Consent recorded');
    }

    public function consentLog(): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::error('Unauthenticated', 401);
        }
        Response::success(GdprModel::getConsentLog($userId));
    }

    public function requestErasure(Request $request): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::error('Unauthenticated', 401);
        }
        $notes = $request->input('notes', '');
        $id = GdprModel::requestErasure($userId, $userId, $notes);
        Logger::log('gdpr_erasure_request', 'users', $userId, 'GDPR erasure requested');
        Response::success(['request_id' => $id], 'Erasure request submitted');
    }

    public function processErasure(Request $request): void
    {
        $id = (int)$request->param('id');
        GdprModel::processErasure($id, Auth::id());
        Response::success(null, 'Erasure processed');
    }

    public function erasureRequests(Request $request): void
    {
        $filters = ['status' => $request->query('status')];
        $result = GdprModel::getErasureRequests(array_filter($filters));
        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function exportData(): void
    {
        $userId = Auth::id();
        if (!$userId) {
            Response::error('Unauthenticated', 401);
        }
        $data = GdprModel::exportUserData($userId);
        Logger::log('gdpr_export', 'users', $userId, 'GDPR data export');
        Response::success($data);
    }

    public function retentionPolicies(): void
    {
        Response::success(GdprModel::getDataRetentionPolicies());
    }

    public function applyRetention(): void
    {
        $results = GdprModel::applyRetentionPolicies();
        Logger::log('retention_cleanup', 'system', null, 'Data retention policies applied');
        Response::success($results, 'Retention policies applied');
    }
}
