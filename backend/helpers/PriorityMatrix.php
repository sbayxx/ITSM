<?php
class PriorityMatrix
{
    private static array $matrix = [
        'high' => [
            'high' => 'critical',
            'medium' => 'high',
            'low' => 'high',
        ],
        'medium' => [
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'medium',
        ],
        'low' => [
            'high' => 'medium',
            'medium' => 'low',
            'low' => 'low',
        ],
    ];

    public static function calculate(string $impact, string $urgency): string
    {
        $impact = strtolower($impact);
        $urgency = strtolower($urgency);
        return self::$matrix[$impact][$urgency] ?? 'medium';
    }

    public static function getPriorityWeight(string $priority): int
    {
        $weights = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        return $weights[$priority] ?? 2;
    }

    public static function getResponseTime(string $priority): int
    {
        $times = ['critical' => 15, 'high' => 30, 'medium' => 60, 'low' => 240];
        return $times[$priority] ?? 60;
    }

    public static function getResolutionTime(string $priority): int
    {
        $times = ['critical' => 240, 'high' => 480, 'medium' => 1440, 'low' => 4320];
        return $times[$priority] ?? 1440;
    }
}
