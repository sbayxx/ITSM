<?php
require_once __DIR__ . '/Auth.php';

class Middleware
{
    public static function authenticate(): callable
    {
        return function () {
            if (!Auth::check()) {
                \Response::json(['error' => 'Unauthenticated'], 401);
                return false;
            }
            return true;
        };
    }

    public static function role(string ...$roles): callable
    {
        return function () use ($roles) {
            $userRole = Auth::role();
            if (!Auth::check() || !in_array($userRole, $roles)) {
                if (!Auth::check()) {
                    \Response::json(['error' => 'Unauthenticated'], 401);
                } else {
                    \Response::json(['error' => 'Forbidden'], 403);
                }
                return false;
            }
            return true;
        };
    }

    public static function admin(): callable
    {
        return function () {
            if (!Auth::check() || !Auth::isAdmin()) {
                \Response::json(['error' => 'Forbidden'], 403);
                return false;
            }
            return true;
        };
    }
}
