<?php
require_once __DIR__ . '/../core/Database.php';

class YearWheelCategory
{
    public static function find(int $id): ?array
    {
        $cat = Database::fetch(
            "SELECT yc.* FROM yw_categories yc WHERE yc.id = ?",
            [$id]
        );
        if ($cat) {
            $cat['tasks'] = Database::fetchAll(
                "SELECT yt.* FROM yw_tasks yt WHERE yt.category_id = ? AND yt.deleted_at IS NULL ORDER BY yt.start_date",
                [$id]
            );
        }
        return $cat;
    }

    public static function all(array $filters = []): array
    {
        $where = ['1=1'];
        $bindings = [];

        if (!empty($filters['customer_id'])) {
            $where[] = '(yc.customer_id IS NULL OR yc.customer_id = ?)';
            $bindings[] = $filters['customer_id'];
        } else {
            $customerScope = Auth::customerScope('yc');
            if ($customerScope['sql'] !== '1=1') {
                $where[] = '(yc.customer_id IS NULL OR ' . $customerScope['sql'] . ')';
                $bindings = array_merge($bindings, $customerScope['params']);
            }
        }

        $sql = "SELECT yc.* FROM yw_categories yc WHERE " . implode(' AND ', $where) . " ORDER BY yc.sort_order ASC, yc.name ASC";
        return Database::fetchAll($sql, $bindings);
    }

    public static function create(array $data): ?int
    {
        $customerId = $data['customer_id'] ?? Auth::user()['customer_id'];
        if ($customerId !== null && !Auth::canAccessCustomer((int)$customerId)) return null;

        return Database::insert('yw_categories', [
            'name' => $data['name'],
            'color' => $data['color'] ?? '#3B82F6',
            'icon' => $data['icon'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'customer_id' => $customerId,
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $cat = self::find($id);
        if (!$cat || !Auth::canAccessCustomer($cat['customer_id'])) return false;

        $updateData = [];
        foreach (['name', 'color', 'icon', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }
        if (empty($updateData)) return false;

        Database::update('yw_categories', $updateData, 'id = ?', [$id]);
        return true;
    }

    public static function delete(int $id): bool
    {
        $cat = self::find($id);
        if (!$cat || !Auth::canAccessCustomer($cat['customer_id'])) return false;
        Database::delete('yw_categories', 'id = ?', [$id]);
        return true;
    }
}
