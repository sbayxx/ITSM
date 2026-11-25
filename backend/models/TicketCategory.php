<?php
require_once __DIR__ . '/../core/Database.php';

class TicketCategory
{
    public const TYPES = ['incident', 'service_request', 'problem', 'change'];

    /**
     * Active category tree for one ticket type: [ {id, name, children:[{id,name}]} ].
     * Problem uses the incident tree (applies_to lists both).
     */
    public static function tree(string $type): array
    {
        if (!in_array($type, self::TYPES, true)) return [];
        $roots = Database::fetchAll(
            "SELECT id, name FROM ticket_categories
             WHERE parent_id IS NULL AND is_active = 1 AND FIND_IN_SET(?, applies_to) > 0
             ORDER BY sort_order, name",
            [$type]
        );
        if (!$roots) return [];
        $ids = array_column($roots, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $subs = Database::fetchAll(
            "SELECT id, parent_id, name FROM ticket_categories
             WHERE parent_id IN ({$ph}) AND is_active = 1 ORDER BY sort_order, name",
            $ids
        );
        $byParent = [];
        foreach ($subs as $s) {
            $byParent[$s['parent_id']][] = ['id' => (int)$s['id'], 'name' => $s['name']];
        }
        return array_map(fn($r) => [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'children' => $byParent[$r['id']] ?? [],
        ], $roots);
    }

    /**
     * Checks a category/subcategory pair for a ticket type.
     * Returns null when valid, otherwise a message for the client.
     */
    public static function validate(string $type, ?string $category, ?string $subcategory): ?string
    {
        if ($category === null || $category === '') {
            return ($subcategory !== null && $subcategory !== '') ? 'subcategory requires a category' : null;
        }
        $root = Database::fetch(
            "SELECT id FROM ticket_categories
             WHERE parent_id IS NULL AND is_active = 1 AND name = ? AND FIND_IN_SET(?, applies_to) > 0",
            [$category, $type]
        );
        if (!$root) return "Invalid category for {$type}: {$category}";
        if ($subcategory === null || $subcategory === '') return null;
        $sub = Database::fetch(
            "SELECT id FROM ticket_categories WHERE parent_id = ? AND is_active = 1 AND name = ?",
            [$root['id'], $subcategory]
        );
        return $sub ? null : "Subcategory '{$subcategory}' does not belong to category '{$category}'";
    }
}
