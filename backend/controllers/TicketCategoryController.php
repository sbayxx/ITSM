<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../models/TicketCategory.php';

class TicketCategoryController
{
    /** GET /api/ticket-categories?type=incident */
    public function index(Request $request): void
    {
        $type = (string)$request->query('type', '');
        if (!in_array($type, TicketCategory::TYPES, true)) {
            Response::error('type must be one of: ' . implode(', ', TicketCategory::TYPES), 422);
        }
        Response::success(TicketCategory::tree($type));
    }
}
