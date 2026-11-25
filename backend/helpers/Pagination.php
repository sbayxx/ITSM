<?php
class Pagination
{
    public static function params(): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? PAGINATION_LIMIT)));
        $offset = ($page - 1) * $perPage;
        return [$page, $perPage, $offset];
    }

    public static function meta(int $total, int $page, int $perPage): array
    {
        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => ceil($total / $perPage),
        ];
    }
}
