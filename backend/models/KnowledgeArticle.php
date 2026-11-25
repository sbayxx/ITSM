<?php
require_once __DIR__ . '/../core/Database.php';

class KnowledgeArticle
{
    public static function find(int $id): ?array
    {
        $article = Database::fetch(
            "SELECT k.*, a.name as author_name, r.name as reviewer_name
             FROM knowledge_articles k
             LEFT JOIN users a ON k.author_id = a.id
             LEFT JOIN users r ON k.reviewer_id = r.id
             WHERE k.id = ?", [$id]
        );
        if ($article) {
            Database::query("UPDATE knowledge_articles SET views = views + 1 WHERE id = ?", [$id]);
        }
        return $article;
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];
        if (!empty($filters['status'])) {
            $where[] = 'k.status = ?';
            $bindings[] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'k.category = ?';
            $bindings[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $where[] = 'MATCH(k.title, k.body) AGAINST(? IN BOOLEAN MODE)';
            $bindings[] = $filters['search'];
        }
        $whereClause = implode(' AND ', $where);
        $total = Database::fetch("SELECT COUNT(*) as count FROM knowledge_articles k WHERE {$whereClause}", $bindings)['count'];
        [$page, $perPage, $offset] = Pagination::params();
        $rows = Database::fetchAll(
            "SELECT k.*, a.name as author_name
             FROM knowledge_articles k
             LEFT JOIN users a ON k.author_id = a.id
             WHERE {$whereClause}
             ORDER BY k.views DESC, k.updated_at DESC
             LIMIT {$perPage} OFFSET {$offset}", $bindings);
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function create(array $data): int
    {
        return Database::insert('knowledge_articles', $data);
    }

    public static function update(int $id, array $data): void
    {
        Database::update('knowledge_articles', $data, 'id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::delete('knowledge_articles', 'id = ?', [$id]);
    }

    public static function markHelpful(int $id, bool $helpful): void
    {
        $field = $helpful ? 'helpful_count' : 'not_helpful_count';
        Database::query("UPDATE knowledge_articles SET {$field} = {$field} + 1 WHERE id = ?", [$id]);
    }
}
