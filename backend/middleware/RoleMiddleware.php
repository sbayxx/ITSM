<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';

class RoleMiddleware
{
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function handle(): bool
    {
        if (!Auth::check()) {
            Response::json(['error' => 'Unauthenticated'], 401);
            return false;
        }
        $userRole = Auth::role();
        if (!in_array($userRole, $this->roles) && $userRole !== 'admin') {
            Response::json(['error' => 'Forbidden: insufficient permissions'], 403);
            return false;
        }
        return true;
    }
}
