<?php
require_once __DIR__ . '/../core/Database.php';

class CiItem
{
    public static function find(int $id): ?array
    {
        $where = ['ci.id = ?'];
        $bindings = [$id];
        $customerScope = Auth::customerScope('ci');
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }
        $ci = Database::fetch(
            "SELECT ci.*, ct.name as ci_type_name, ct.icon as ci_type_icon, ct.color as ci_type_color
             FROM ci_items ci
             LEFT JOIN ci_types ct ON ci.ci_type_id = ct.id
             WHERE " . implode(' AND ', $where),
            $bindings
        );
        if ($ci) {
            $ci['parents'] = Database::fetchAll(
                "SELECT ci.*, cr.relationship_type FROM ci_relationships cr
                 JOIN ci_items ci ON cr.parent_ci_id = ci.id
                 WHERE cr.child_ci_id = ?", [$id]
            );
            $ci['children'] = Database::fetchAll(
                "SELECT ci.*, cr.relationship_type FROM ci_relationships cr
                 JOIN ci_items ci ON cr.child_ci_id = ci.id
                 WHERE cr.parent_ci_id = ?", [$id]
            );
            $relationships = [];
            foreach ($ci['parents'] as $p) {
                $relationships[] = ['id' => $p['id'], 'related_ci_id' => $p['id'], 'name' => $p['name'], 'relationship_type' => 'parent_of'];
            }
            foreach ($ci['children'] as $c) {
                $relationships[] = ['id' => $c['id'], 'related_ci_id' => $c['id'], 'name' => $c['name'], 'relationship_type' => 'child_of'];
            }
            $ci['relationships'] = $relationships;
            $ci['tags'] = Database::fetchAll(
                "SELECT t.* FROM ci_tags t JOIN ci_item_tags it ON t.id = it.tag_id WHERE it.ci_item_id = ?", [$id]
            );
        }
        return $ci;
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];

        $customerScope = Auth::customerScope('ci');
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }
        if (!empty($filters['type'])) {
            $where[] = 'ci.type = ?';
            $bindings[] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'ci.status = ?';
            $bindings[] = $filters['status'];
        }
        if (!empty($filters['environment'])) {
            $where[] = 'ci.environment = ?';
            $bindings[] = $filters['environment'];
        }
        if (!empty($filters['business_service'])) {
            $where[] = 'ci.business_service LIKE ?';
            $bindings[] = '%' . $filters['business_service'] . '%';
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'ci.customer_id = ?';
            $bindings[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(ci.name LIKE ? OR ci.serial_number LIKE ? OR ci.fqdn LIKE ? OR ci.asset_tag LIKE ? OR ci.location LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $bindings = array_merge($bindings, [$like, $like, $like, $like, $like]);
        }
        $whereClause = implode(' AND ', $where);
        $total = Database::fetch("SELECT COUNT(*) as count FROM ci_items ci WHERE {$whereClause}", $bindings)['count'];
        [$page, $perPage, $offset] = Pagination::params();
        $rows = Database::fetchAll(
            "SELECT ci.*, ct.name as ci_type_name, ct.icon as ci_type_icon, ct.color as ci_type_color
             FROM ci_items ci
             LEFT JOIN ci_types ct ON ci.ci_type_id = ct.id
             WHERE {$whereClause}
             ORDER BY ci.name ASC LIMIT {$perPage} OFFSET {$offset}",
            $bindings
        );
        return ['items' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function create(array $data): int
    {
        if (empty($data['customer_id'])) {
            $user = Auth::user();
            if ($user && $user['customer_id']) {
                $data['customer_id'] = $user['customer_id'];
            }
        }
        $id = Database::insert('ci_items', $data);
        self::logAudit($id, 'create', null, null, 'CI created: ' . ($data['name'] ?? ''));
        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $old = Database::fetch("SELECT * FROM ci_items WHERE id = ?", [$id]);
        Database::update('ci_items', $data, 'id = ?', [$id]);
        foreach ($data as $key => $newVal) {
            $oldVal = $old[$key] ?? null;
            if ($oldVal != $newVal) {
                self::logAudit($id, 'update', $key, $oldVal, "{$key} changed");
            }
        }
    }

    public static function delete(int $id): void
    {
        $old = Database::fetch("SELECT * FROM ci_items WHERE id = ?", [$id]);
        Database::delete('ci_items', 'id = ?', [$id]);
        self::logAudit($id, 'delete', null, null, 'CI deleted: ' . ($old['name'] ?? ''));
    }

    public static function relate(int $parentId, int $childId, string $type = 'depends_on'): void
    {
        Database::insert('ci_relationships', [
            'parent_ci_id' => $parentId,
            'child_ci_id' => $childId,
            'relationship_type' => $type,
        ]);
        self::logAudit($parentId, 'relate', 'relationship', null, "Related CI #{$parentId} to #{$childId} as {$type}");
    }

    public static function unrelate(int $parentId, int $childId, string $type = 'depends_on'): void
    {
        Database::delete('ci_relationships', 'parent_ci_id = ? AND child_ci_id = ? AND relationship_type = ?', [$parentId, $childId, $type]);
        self::logAudit($parentId, 'unrelate', 'relationship', null, "Unrelated CI #{$parentId} from #{$childId}");
    }

    public static function getTypes(): array
    {
        return Database::fetchAll("SELECT DISTINCT type FROM ci_items WHERE is_active = 1 ORDER BY type");
    }

    public static function getCiAuditLog(int $ciItemId): array
    {
        return Database::fetchAll(
            "SELECT cal.*, u.name as user_name FROM ci_audit_log cal
             LEFT JOIN users u ON cal.user_id = u.id
             WHERE cal.ci_item_id = ?
             ORDER BY cal.created_at DESC LIMIT 50",
            [$ciItemId]
        );
    }

    public static function setTags(int $ciItemId, array $tagIds): void
    {
        Database::delete('ci_item_tags', 'ci_item_id = ?', [$ciItemId]);
        foreach ($tagIds as $tagId) {
            Database::insert('ci_item_tags', ['ci_item_id' => $ciItemId, 'tag_id' => (int)$tagId]);
        }
    }

    private static function logAudit(int $ciItemId, string $action, ?string $field, mixed $oldValue, ?string $summary): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        $oldStr = is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : $oldValue;
        Database::insert('ci_audit_log', [
            'ci_item_id' => $ciItemId,
            'user_id' => $userId,
            'action' => $action,
            'field_name' => $field,
            'old_value' => $oldStr,
            'new_value' => null,
            'summary' => $summary,
        ]);
    }
}
