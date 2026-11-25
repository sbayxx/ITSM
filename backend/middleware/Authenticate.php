<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Response.php';

class Authenticate
{
    public function handle(): bool
    {
        if (!Auth::check()) {
            Response::json(['error' => 'Unauthenticated'], 401);
            return false;
        }
        return true;
    }
}
