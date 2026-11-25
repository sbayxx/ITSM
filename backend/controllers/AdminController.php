<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../models/User.php';

class AdminController
{
    public function impersonate(Request $request): void
    {
        if (!Auth::isAdmin()) {
            Response::error('Forbidden', 403);
            return;
        }

        $id = (int)$request->param('id');
        $targetUser = User::find($id);
        if (!$targetUser) {
            Response::error('User not found', 404);
            return;
        }

        if ($id === Auth::id()) {
            Response::error('Cannot impersonate yourself', 422);
            return;
        }

        if ($targetUser['role'] === 'admin') {
            Response::error('Cannot impersonate another admin', 422);
            return;
        }

        $_SESSION['original_admin_id'] = Auth::id();
        $_SESSION['impersonating_id'] = $targetUser['id'];

        Auth::clearUser();

        Logger::log('impersonate', 'users', $id, 'Admin started impersonating user: ' . $targetUser['email']);

        $user = Auth::user();
        $user['permissions'] = User::getPermissions($user['id']);

        Response::success([
            'user' => $user,
            'impersonating' => true,
            'impersonator' => Auth::getImpersonator(),
        ], 'Now impersonating ' . $targetUser['name']);
    }

    public function stopImpersonate(): void
    {
        if (!isset($_SESSION['impersonating_id']) || !isset($_SESSION['original_admin_id'])) {
            Response::error('Not impersonating any user', 400);
            return;
        }

        $impersonatedId = (int)$_SESSION['impersonating_id'];
        unset($_SESSION['impersonating_id']);
        unset($_SESSION['original_admin_id']);

        Auth::clearUser();

        Logger::log('impersonate_end', 'users', $impersonatedId, 'Admin stopped impersonating user');

        $user = Auth::user();
        $user['permissions'] = User::getPermissions($user['id']);

        Response::success([
            'user' => $user,
            'impersonating' => false,
            'impersonator' => null,
        ], 'Stopped impersonating');
    }
}
