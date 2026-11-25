<?php
require_once __DIR__ . '/../core/Database.php';

class YearWheelTask
{
    public static function find(int $id): ?array
    {
        $where = ['yt.id = ?', 'yt.deleted_at IS NULL'];
        $bindings = [$id];

        $customerScope = Auth::customerScope('yt');
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }

        $task = Database::fetch(
            "SELECT yt.*, yc.name as category_name, yc.color as category_color, yc.icon as category_icon,
                    u.name as assigned_to_name, u.email as assigned_to_email,
                    creator.name as created_by_name
             FROM yw_tasks yt
             LEFT JOIN yw_categories yc ON yt.category_id = yc.id
             LEFT JOIN users u ON yt.assigned_to = u.id
             LEFT JOIN users creator ON yt.created_by = creator.id
             WHERE " . implode(' AND ', $where),
            $bindings
        );
        if ($task) {
            $task['subtasks'] = Database::fetchAll(
                "SELECT yt.*, yc.name as category_name, yc.color as category_color
                 FROM yw_tasks yt
                 LEFT JOIN yw_categories yc ON yt.category_id = yc.id
                 WHERE yt.parent_task_id = ? AND yt.deleted_at IS NULL ORDER BY yt.start_date",
                [$id]
            );
        }
        return $task;
    }

    public static function all(array $filters = []): array
    {
        $where = ['yt.deleted_at IS NULL'];
        $bindings = [];

        $customerScope = Auth::customerScope('yt');
        if ($customerScope['sql'] !== '1=1') {
            $where[] = $customerScope['sql'];
            $bindings = array_merge($bindings, $customerScope['params']);
        }
        if (!empty($filters['customer_id'])) {
            $where[] = 'yt.customer_id = ?';
            $bindings[] = $filters['customer_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'yt.category_id = ?';
            $bindings[] = $filters['category_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'yt.status = ?';
            $bindings[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'yt.priority = ?';
            $bindings[] = $filters['priority'];
        }
        // Not empty(): the string '0' is falsy in PHP, so a legitimate
        // "only tasks that are NOT on the wheel" filter would be dropped.
        if (array_key_exists('show_in_wheel', $filters) && $filters['show_in_wheel'] !== null && $filters['show_in_wheel'] !== '') {
            $where[] = 'yt.show_in_wheel = ?';
            $bindings[] = (int)$filters['show_in_wheel'];
        }
        if (!empty($filters['assigned_to'])) {
            $where[] = 'yt.assigned_to = ?';
            $bindings[] = $filters['assigned_to'];
        }
        if (!empty($filters['year'])) {
            $year = (int)$filters['year'];
            $where[] = '(YEAR(yt.start_date) = ? OR YEAR(yt.end_date) = ?)';
            $bindings[] = $year;
            $bindings[] = $year;
        }

        $sql = "SELECT yt.*, yc.name as category_name, yc.color as category_color, yc.icon as category_icon,
                       u.name as assigned_to_name
                FROM yw_tasks yt
                LEFT JOIN yw_categories yc ON yt.category_id = yc.id
                LEFT JOIN users u ON yt.assigned_to = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY (yt.start_date IS NULL) ASC, yt.start_date ASC, yt.title ASC";
        return Database::fetchAll($sql, $bindings);
    }

    public static function create(array $data): ?int
    {
        $userId = Auth::user()['id'];
        $customerId = $data['customer_id'] ?? Auth::user()['customer_id'];
        if ($customerId !== null && !Auth::canAccessCustomer((int)$customerId)) return null;

        $insertData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'customer_id' => $customerId ? (int)$customerId : null,
            'assigned_to' => $data['assigned_to'] ?? null,
            'status' => $data['status'] ?? 'planned',
            'priority' => $data['priority'] ?? 'medium',
            'start_date' => $data['start_date'] ?: null,
            'end_date' => $data['end_date'] ?: null,
            'is_recurring' => $data['is_recurring'] ?? 0,
            // Wheel-created tasks default to the wheel; the Tasks UI sends 0.
            'show_in_wheel' => $data['show_in_wheel'] ?? 1,
            'recurrence_config' => isset($data['recurrence_config']) ? (is_string($data['recurrence_config']) ? $data['recurrence_config'] : json_encode($data['recurrence_config'])) : null,
            'parent_task_id' => $data['parent_task_id'] ?? null,
            'created_by' => $userId,
        ];

        return Database::insert('yw_tasks', $insertData);
    }

    public static function update(int $id, array $data): bool
    {
        $task = self::find($id);
        if (!$task || !Auth::canAccessCustomer($task['customer_id'])) return false;

        $updateData = [];
        $fields = ['title', 'description', 'category_id', 'assigned_to', 'status', 'priority', 'start_date', 'end_date', 'is_recurring', 'show_in_wheel'];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $field === 'show_in_wheel' ? (int)(bool)$data[$field] : $data[$field];
            }
        }
        if (array_key_exists('recurrence_config', $data)) {
            $updateData['recurrence_config'] = is_string($data['recurrence_config']) ? $data['recurrence_config'] : json_encode($data['recurrence_config']);
        }
        if (array_key_exists('status', $data)) {
            // Re-opening a task must clear the completion stamp, otherwise it
            // keeps a completed_at while sitting in "in progress".
            $updateData['completed_at'] = $data['status'] === 'completed' ? date('Y-m-d H:i:s') : null;
        }
        if (empty($updateData)) return false;

        Database::update('yw_tasks', $updateData, 'id = ?', [$id]);
        return true;
    }

    public static function delete(int $id): bool
    {
        $task = self::find($id);
        if (!$task || !Auth::canAccessCustomer($task['customer_id'])) return false;
        Database::update('yw_tasks', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        return true;
    }

    public static function rollover(int $year): array
    {
        $tasks = self::all(['year' => $year - 1, 'show_in_wheel' => 1]);
        $created = [];
        foreach ($tasks as $task) {
            if (!$task['is_recurring']) continue;
            $existing = Database::fetch(
                "SELECT id FROM yw_tasks WHERE parent_task_id = ? AND YEAR(start_date) = ? AND deleted_at IS NULL",
                [$task['id'], $year]
            );
            if ($existing) continue;

            $config = json_decode($task['recurrence_config'] ?: '{}', true);
            $newStart = self::calculateNextDate($task['start_date'], $year, $config);
            $newEnd = $task['end_date'] ? self::calculateNextDate($task['end_date'], $year, $config) : null;

            $id = self::create([
                'title' => $task['title'],
                'description' => $task['description'],
                'category_id' => $task['category_id'],
                'customer_id' => $task['customer_id'],
                'assigned_to' => $task['assigned_to'],
                'priority' => $task['priority'],
                'start_date' => $newStart,
                'end_date' => $newEnd,
                'is_recurring' => 1,
                'recurrence_config' => $config,
                'parent_task_id' => $task['id'],
            ]);
            if ($id) $created[] = $id;
        }
        return $created;
    }

    private static function calculateNextDate(string $date, int $targetYear, array $config): string
    {
        $ts = strtotime($date);
        $month = (int)date('m', $ts);
        $day = (int)date('d', $ts);
        if (isset($config['month_offset'])) {
            $month = (($month - 1 + $config['month_offset']) % 12) + 1;
        }
        $daysInMonth = date('t', strtotime("$targetYear-$month-01"));
        if ($day > $daysInMonth) $day = $daysInMonth;
        return sprintf('%04d-%02d-%02d', $targetYear, $month, $day);
    }
}
