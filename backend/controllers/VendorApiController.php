<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Vendor.php';
require_once __DIR__ . '/../services/VendorReply.php';

/**
 * External vendor API. No session: authenticates with `Authorization: Bearer <vendor key>`.
 * A key only ever sees tickets whose vendor_id is the key's vendor.
 */
class VendorApiController
{
    private array $vendor = [];

    public function tickets(Request $request): void
    {
        $this->authenticate();
        $params = [$this->vendor['id']];
        $where = 'vendor_id = ?';
        $status = $request->query('status');
        if ($status) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }
        $rows = Database::fetchAll(
            "SELECT ticket_number, type, title, description, status, priority, external_ref,
                    vendor_requested_at, created_at, updated_at
             FROM tickets WHERE {$where} ORDER BY vendor_requested_at DESC LIMIT 200",
            $params
        );
        Response::success($rows);
    }

    public function update(Request $request): void
    {
        $this->authenticate();
        $number = (string)$request->param('ticket_number');
        $ticket = Database::fetch(
            "SELECT id FROM tickets WHERE ticket_number = ? AND vendor_id = ?",
            [$number, $this->vendor['id']]
        );
        if (!$ticket) Response::error('Ticket not found', 404);

        $data = $request->all();
        $status = isset($data['status']) ? (string)$data['status'] : null;
        $comment = isset($data['comment']) ? (string)$data['comment'] : null;
        $externalRef = isset($data['external_ref']) ? (string)$data['external_ref'] : null;
        if ($status === null && $comment === null && $externalRef === null) {
            Response::error('Provide at least one of status, comment, external_ref', 422);
        }
        if ($comment !== null && mb_strlen($comment) > 5000) Response::error('comment is too long', 422);
        if ($externalRef !== null && mb_strlen($externalRef) > 255) Response::error('external_ref is too long', 422);

        try {
            $result = VendorReply::apply(
                (int)$ticket['id'], $this->vendor['name'], $comment, $externalRef, $status, null, 'api'
            );
        } catch (VendorReplyException $e) {
            Response::error($e->getMessage(), $e->httpStatus);
        }
        Response::success($result, 'Ticket updated');
    }

    private function authenticate(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!$header && function_exists('getallheaders')) {
            $all = getallheaders();
            $header = $all['Authorization'] ?? $all['authorization'] ?? '';
        }
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            Response::error('Missing bearer token', 401);
        }
        $vendor = Vendor::findByApiKey($m[1]);
        if (!$vendor) Response::error('Invalid or revoked API key', 401);
        $this->vendor = $vendor;
    }
}
