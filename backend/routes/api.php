<?php
Router::group(['prefix' => '/api', 'middleware' => ['CorsMiddleware']], function () {

    // Auth routes
    Router::post('/auth/login', [AuthController::class, 'login'], ['throttle']);
    Router::post('/auth/logout', [AuthController::class, 'logout'], ['auth']);
    Router::get('/auth/me', [AuthController::class, 'me'], ['auth']);
    Router::post('/auth/register', [AuthController::class, 'register']);
    Router::put('/auth/profile', [AuthController::class, 'updateProfile'], ['auth']);
    Router::put('/auth/password', [AuthController::class, 'updatePassword'], ['auth']);
    Router::get('/auth/csrf-token', [AuthController::class, 'csrfToken']);
    Router::get('/auth/microsoft/url', [AuthController::class, 'microsoftUrl']);
    Router::post('/auth/microsoft/callback', [AuthController::class, 'microsoftCallback']);

    // TEMP: opcache reset
    Router::get('/opcache-reset', function () {
        if (function_exists('opcache_reset')) {
            $r1 = opcache_reset();
            echo "opcache_reset(): " . ($r1 ? "SUCCESS" : "FAILED");
        } else {
            echo "opcache_reset() not available";
        }
        exit;
    });

    // Dashboard
    Router::get('/dashboard', [TicketController::class, 'dashboard'], ['auth']);

    // Tickets (unified)
    Router::get('/ticket-categories', [TicketCategoryController::class, 'index'], ['auth']);
    Router::get('/tickets', [TicketController::class, 'index'], ['auth']);
    Router::get('/tickets/{id}', [TicketController::class, 'show'], ['auth']);
    Router::post('/tickets', [TicketController::class, 'store'], ['auth']);
    Router::put('/tickets/{id}', [TicketController::class, 'update'], ['auth']);
    Router::delete('/tickets/{id}', [TicketController::class, 'destroy'], ['auth']);
    Router::post('/tickets/{id}/comments', [TicketController::class, 'addComment'], ['auth']);

    // Incidents
    Router::post('/incidents/{id}/escalate', [IncidentController::class, 'escalate'], ['auth']);
    Router::post('/incidents/{id}/link-problem', [IncidentController::class, 'linkToProblem'], ['auth']);

    // Service Requests
    Router::post('/service-requests/{id}/approve', [ServiceRequestController::class, 'approve'], ['auth']);
    Router::post('/service-requests/{id}/reject', [ServiceRequestController::class, 'reject'], ['auth']);
    Router::get('/service-catalog', [ServiceRequestController::class, 'catalog'], ['auth']);

    // Problems
    Router::post('/problems/{id}/link-incidents', [ProblemController::class, 'linkIncidents'], ['auth']);
    Router::post('/problems/{id}/known-error', [ProblemController::class, 'markKnownError'], ['auth']);

    // Changes
    Router::post('/changes/{id}/cab-vote', [ChangeController::class, 'cabVote'], ['auth']);
    Router::post('/changes/{id}/implement', [ChangeController::class, 'implement'], ['auth']);
    Router::post('/changes/{id}/review', [ChangeController::class, 'review'], ['auth']);

    // CSI
    Router::get('/csi', [CsiController::class, 'index'], ['auth']);
    Router::get('/csi/{id}', [CsiController::class, 'show'], ['auth']);
    Router::post('/csi', [CsiController::class, 'store'], ['auth']);
    Router::put('/csi/{id}', [CsiController::class, 'update'], ['auth']);
    Router::delete('/csi/{id}', [CsiController::class, 'destroy'], ['auth']);
    Router::get('/csi/kpi/summary', [CsiController::class, 'kpiSummary'], ['auth']);

    // Knowledge Base
    Router::get('/knowledge-base', [KnowledgeBaseController::class, 'index'], ['auth']);
    Router::get('/knowledge-base/{id}', [KnowledgeBaseController::class, 'show'], ['auth']);
    Router::post('/knowledge-base', [KnowledgeBaseController::class, 'store'], ['auth']);
    Router::put('/knowledge-base/{id}', [KnowledgeBaseController::class, 'update'], ['auth']);
    Router::delete('/knowledge-base/{id}', [KnowledgeBaseController::class, 'destroy'], ['auth']);
    Router::post('/knowledge-base/{id}/feedback', [KnowledgeBaseController::class, 'helpful'], ['auth']);

    // CMDB
    Router::get('/ci-items', [CiController::class, 'index'], ['auth']);
    Router::get('/ci-items/{id}', [CiController::class, 'show'], ['auth']);
    Router::post('/ci-items', [CiController::class, 'store'], ['auth']);
    Router::put('/ci-items/{id}', [CiController::class, 'update'], ['auth']);
    Router::delete('/ci-items/{id}', [CiController::class, 'destroy'], ['auth']);
    Router::post('/ci-items/relate', [CiController::class, 'relate'], ['auth']);
    Router::post('/ci-items/unrelate', [CiController::class, 'unrelate'], ['auth']);
    Router::get('/ci-items/{id}/audit', [CiController::class, 'auditLog'], ['auth']);
    Router::post('/ci-items/{id}/tags', [CiController::class, 'setCiTags'], ['auth']);

    // CI Types
    Router::get('/ci-types', [CiController::class, 'ciTypeIndex'], ['auth']);
    Router::post('/ci-types', [CiController::class, 'ciTypeStore'], ['auth']);
    Router::put('/ci-types/{id}', [CiController::class, 'ciTypeUpdate'], ['auth']);
    Router::delete('/ci-types/{id}', [CiController::class, 'ciTypeDestroy'], ['auth']);
    Router::get('/ci-types/list', [CiController::class, 'types'], ['auth']);

    // CI Tags
    Router::get('/ci-tags', [CiController::class, 'tagIndex'], ['auth']);
    Router::post('/ci-tags', [CiController::class, 'tagStore'], ['auth']);
    Router::put('/ci-tags/{id}', [CiController::class, 'tagUpdate'], ['auth']);
    Router::delete('/ci-tags/{id}', [CiController::class, 'tagDestroy'], ['auth']);

    // SLA
    Router::get('/sla-contracts', [SlaController::class, 'index'], ['auth']);
    Router::get('/sla-contracts/{id}', [SlaController::class, 'show'], ['auth']);
    Router::post('/sla-contracts', [SlaController::class, 'store'], ['auth']);
    Router::put('/sla-contracts/{id}', [SlaController::class, 'update'], ['auth']);
    Router::delete('/sla-contracts/{id}', [SlaController::class, 'destroy'], ['auth']);
    Router::get('/sla/compliance', [SlaController::class, 'compliance'], ['auth']);

    // Reports
    Router::get('/reports/ticket-volume', [ReportController::class, 'ticketVolume'], ['auth']);
    Router::get('/reports/status-distribution', [ReportController::class, 'statusDistribution'], ['auth']);
    Router::get('/reports/sla-compliance', [ReportController::class, 'slaCompliance'], ['auth']);
    Router::get('/reports/csi-progress', [ReportController::class, 'csiProgress'], ['auth']);
    Router::get('/reports/agent-performance', [ReportController::class, 'agentPerformance'], ['auth']);
    Router::get('/reports/compliance', [ReportController::class, 'compliance'], ['auth']);
    Router::get('/reports/checklists', [ReportController::class, 'checklists'], ['auth']);

    // Approvals
    Router::get('/approvals', [ApprovalController::class, 'index'], ['auth']);
    Router::post('/approvals/{id}/approve', [ApprovalController::class, 'approve'], ['auth']);
    Router::post('/approvals/{id}/reject', [ApprovalController::class, 'reject'], ['auth']);

    // Users / Admin
    Router::get('/users', [UserController::class, 'index'], ['auth']);
    Router::get('/users/{id}', [UserController::class, 'show'], ['auth']);
    Router::post('/users', [UserController::class, 'store'], ['auth', 'admin']);
    Router::put('/users/{id}', [UserController::class, 'update'], ['auth', 'admin']);
    Router::delete('/users/{id}', [UserController::class, 'destroy'], ['auth', 'admin']);
    Router::get('/roles', [UserController::class, 'roles'], ['auth']);
    Router::get('/agents', [UserController::class, 'agents'], ['auth']);
    Router::post('/users/sync-azure', [UserController::class, 'syncAzure'], ['auth', 'admin']);
    Router::get('/users/{id}/customers', [UserController::class, 'userCustomers'], ['auth', 'admin']);
    Router::put('/users/{id}/customers', [UserController::class, 'updateUserCustomers'], ['auth', 'admin']);
    Router::post('/admin/impersonate/stop', [AdminController::class, 'stopImpersonate'], ['auth']);
    Router::post('/admin/impersonate/{id}', [AdminController::class, 'impersonate'], ['auth', 'admin']);

    // GDPR
    Router::post('/gdpr/consent', [GdprController::class, 'consent'], ['auth']);
    Router::get('/gdpr/consent-log', [GdprController::class, 'consentLog'], ['auth']);
    Router::post('/gdpr/erasure-request', [GdprController::class, 'requestErasure'], ['auth']);
    Router::get('/gdpr/erasure-requests', [GdprController::class, 'erasureRequests'], ['auth', 'admin']);
    Router::post('/gdpr/erasure-requests/{id}/process', [GdprController::class, 'processErasure'], ['auth', 'admin']);
    Router::get('/gdpr/export', [GdprController::class, 'exportData'], ['auth']);
    Router::get('/gdpr/retention-policies', [GdprController::class, 'retentionPolicies'], ['auth', 'admin']);
    Router::post('/gdpr/apply-retention', [GdprController::class, 'applyRetention'], ['auth', 'admin']);

    // Manual Devices
    Router::get('/manual-devices', [ManualDeviceController::class, 'index'], ['auth']);
    Router::get('/manual-devices/filters', [ManualDeviceController::class, 'filters'], ['auth']);
    Router::get('/manual-devices/{id}', [ManualDeviceController::class, 'show'], ['auth']);
    Router::post('/manual-devices', [ManualDeviceController::class, 'store'], ['auth']);
    Router::put('/manual-devices/{id}', [ManualDeviceController::class, 'update'], ['auth']);
    Router::delete('/manual-devices/{id}', [ManualDeviceController::class, 'destroy'], ['auth']);

    // Intune
    Router::get('/intune/devices', [IntuneController::class, 'index'], ['auth']);
    Router::get('/intune/devices/{id}', [IntuneController::class, 'show'], ['auth']);
    Router::post('/intune/sync', [IntuneController::class, 'sync'], ['auth']);
    Router::get('/intune/summary', [IntuneController::class, 'summary'], ['auth']);
    Router::get('/intune/filters', [IntuneController::class, 'filters'], ['auth']);
    Router::delete('/intune/devices/{id}', [IntuneController::class, 'destroy'], ['auth']);
    Router::get('/intune/debug', [IntuneController::class, 'debug'], ['auth']);
    Router::post('/intune/clear', [IntuneController::class, 'clearAll'], ['auth', 'admin']);

    // Audit Log
    Router::get('/audit-logs', [AuditLogController::class, 'index'], ['auth']);
    Router::get('/audit-log-tables', [AuditLogController::class, 'tables'], ['auth']);
    Router::get('/audit-log-actions', [AuditLogController::class, 'actions'], ['auth']);

    // Organizations
    Router::get('/organizations', function (Request $r) {
        $result = Database::fetchAll("SELECT * FROM organizations WHERE is_active = 1 ORDER BY name");
        Response::success($result);
    }, ['auth']);

    // Customers
    Router::get('/customers', [CustomerController::class, 'index'], ['auth']);
    Router::get('/customers/my', [CustomerController::class, 'myCustomers'], ['auth']);
    Router::get('/customers/{id}', [CustomerController::class, 'show'], ['auth']);
    Router::post('/customers', [CustomerController::class, 'store'], ['auth', 'admin']);
    Router::put('/customers/{id}', [CustomerController::class, 'update'], ['auth']);
    Router::delete('/customers/{id}', [CustomerController::class, 'destroy'], ['auth', 'admin']);
    Router::get('/customers/{id}/settings', [CustomerController::class, 'getSettings'], ['auth']);
    Router::put('/customers/{id}/settings', [CustomerController::class, 'updateSettings'], ['auth']);
    Router::get('/customers/{id}/users', [CustomerController::class, 'getUsers'], ['auth']);
    Router::post('/customers/{id}/users', [CustomerController::class, 'assignUsers'], ['auth']);

    // Year Wheel
    Router::get('/year-wheel/categories', [YearWheelController::class, 'indexCategories'], ['auth']);
    Router::get('/year-wheel/categories/{id}', [YearWheelController::class, 'showCategory'], ['auth']);
    Router::post('/year-wheel/categories', [YearWheelController::class, 'storeCategory'], ['auth']);
    Router::put('/year-wheel/categories/{id}', [YearWheelController::class, 'updateCategory'], ['auth']);
    Router::delete('/year-wheel/categories/{id}', [YearWheelController::class, 'destroyCategory'], ['auth']);
    Router::get('/year-wheel/tasks', [YearWheelController::class, 'index'], ['auth']);
    Router::get('/year-wheel/tasks/{id}', [YearWheelController::class, 'show'], ['auth']);
    Router::post('/year-wheel/tasks', [YearWheelController::class, 'store'], ['auth']);
    Router::put('/year-wheel/tasks/{id}', [YearWheelController::class, 'update'], ['auth']);
    Router::delete('/year-wheel/tasks/{id}', [YearWheelController::class, 'destroy'], ['auth']);
    Router::post('/year-wheel/rollover', [YearWheelController::class, 'rollover'], ['auth']);

    // Email
    Router::post('/email/receive', [EmailController::class, 'receive']);
    Router::post('/email/test', [EmailController::class, 'test'], ['auth']);

    // Vendors (admin/customer_admin manage; any ticket-handling role can send)
    Router::get('/vendors', [VendorController::class, 'index'], ['auth']);
    Router::get('/vendors/{id}', [VendorController::class, 'show'], ['auth']);
    Router::post('/vendors', [VendorController::class, 'store'], ['auth']);
    Router::put('/vendors/{id}', [VendorController::class, 'update'], ['auth']);
    Router::delete('/vendors/{id}', [VendorController::class, 'destroy'], ['auth']);
    Router::post('/vendors/{id}/api-key', [VendorController::class, 'createKey'], ['auth']);
    Router::delete('/vendors/{id}/api-key', [VendorController::class, 'revokeKey'], ['auth']);
    Router::post('/tickets/{id}/vendor-request', [VendorRequestController::class, 'send'], ['auth']);

    // Vendor API: bearer key only, no session (see VendorApiController)
    Router::get('/v1/vendor/tickets', [VendorApiController::class, 'tickets']);
    Router::post('/v1/vendor/tickets/{ticket_number}/update', [VendorApiController::class, 'update']);
});
