<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';

$db = Database::connection();

echo "<h2>Seeding demo data...</h2>";

// Clean up any partial data from prior failed runs
$db->exec("DELETE FROM ticket_comments WHERE ticket_id > 0");
$db->exec("DELETE FROM ticket_status_history WHERE ticket_id > 0");
$db->exec("DELETE FROM changes WHERE ticket_id > 0");
$db->exec("DELETE FROM problems WHERE ticket_id > 0");
$db->exec("DELETE FROM service_requests WHERE ticket_id > 0");
$db->exec("DELETE FROM incidents WHERE ticket_id > 0");
$db->exec("DELETE FROM tickets WHERE id > 3");

// ── 10 End Users ──
$users = [
    ['Alice Jensen',    'alice@example.com',    'user'],
    ['Bob Nielsen',     'bob@example.com',      'user'],
    ['Clara Pedersen',  'clara@example.com',    'user'],
    ['David Hansen',    'david@example.com',    'user'],
    ['Eva Christensen', 'eva@example.com',      'user'],
    ['Frank Lund',      'frank@example.com',    'user'],
    ['Grith Sørensen',  'grith@example.com',    'user'],
    ['Henrik Madsen',   'henrik@example.com',   'user'],
    ['Ida Rasmussen',   'ida@example.com',      'user'],
    ['Jesper Thomsen',  'jesper@example.com',   'user'],
];

$pwHash = password_hash('demo123', PASSWORD_BCRYPT);
$userIdMap = [];
$stmt = $db->prepare("INSERT IGNORE INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'user', 1, NOW())");

foreach ($users as $i => [$name, $email]) {
    $stmt->execute([$name, $email, $pwHash]);
    $id = $db->lastInsertId();
    if ($id) {
        $userIdMap[$email] = $id;
        echo "User created: $name ($email) - ID: $id<br>";
    } else {
        $existing = Database::fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $userIdMap[$email] = $existing['id'];
            echo "User exists: $name ($email) - ID: {$existing['id']}<br>";
        }
    }
}

// assignee/agent pool
$agents = Database::fetchAll("SELECT id FROM users WHERE role IN ('admin','agent','manager')");
$agentIds = array_column($agents, 'id');

// ── 20 Tickets ──
$now = new DateTime();

function randomDate(DateTime $now, int $maxDaysAgo): string {
    $days = rand(0, $maxDaysAgo);
    $hours = rand(0, 23);
    $mins = rand(0, 59);
    $d = clone $now;
    $d->modify("-{$days} days")->modify("-{$hours} hours")->modify("-{$mins} minutes");
    return $d->format('Y-m-d H:i:s');
}

function pickAgent(array $agentIds): ?int {
    return $agentIds ? $agentIds[array_rand($agentIds)] : null;
}

function pickRequester(array $userIdMap): int {
    $ids = array_values($userIdMap);
    return $ids[array_rand($ids)];
}

$tickets = [
    // Incidents (1-8)
    ['incident', 'Critical', 'open',           'Production database server unresponsive', "The primary database server is not responding to connections. All production applications are affected.", 'high', 'high'],
    ['incident', 'High',     'in_progress',     'VPN connectivity issues for remote users', 'Remote users report intermittent VPN disconnections since the last firewall update.', 'high', 'medium'],
    ['incident', 'Medium',   'open',            'Printer not working on floor 3', 'The network printer on floor 3 shows offline status. Paper jam error persists after clearing.', 'medium', 'medium'],
    ['incident', 'Low',      'resolved',        'Password reset request', 'User unable to log in after vacation. Password has been reset successfully.', 'low', 'low'],
    ['incident', 'High',     'closed',          'Email service outage', 'Microsoft 365 experienced a 2-hour outage affecting all users. Resolved by Microsoft.', 'high', 'high'],
    ['incident', 'Critical', 'in_progress',     'Security alert - unusual login pattern', 'Multiple failed login attempts detected from an unusual IP range for admin accounts.', 'high', 'high'],
    ['incident', 'Medium',   'on_hold',         'Software license activation failed', 'Adobe Creative Cloud license activation fails with error code 105. Vendor ticket opened.', 'medium', 'medium'],
    ['incident', 'Low',      'open',            'Keyboard replacement request', 'USB keyboard has several non-functioning keys. User requested a replacement.', 'low', 'low'],
    // Service Requests (9-13)
    ['service_request', null, 'open',              'New laptop for onboarding employee', 'New employee starting next week. Need a company laptop with standard software setup.', null, null],
    ['service_request', null, 'in_fulfillment',     'Software installation request', 'User needs MATLAB and Simulink installed on their workstation for engineering projects.', null, null],
    ['service_request', null, 'fulfilled',          'Access to shared drive', 'User needs read/write access to the Marketing shared drive (\\\\server\\marketing).', null, null],
    ['service_request', null, 'rejected',           'VPN access for external consultant', 'External consultant requested full VPN access. Policy requires on-site presence first.', null, null],
    ['service_request', null, 'closed',             'Mobile phone SIM card request', 'User needs a replacement SIM card for the company mobile phone (lost device).', null, null],
    // Problems (14-16)
    ['problem', 'High',     'under_investigation', 'Recurring network outages', 'The office network experiences intermittent 5-minute outages every Tuesday around 10 AM. Multiple users affected across departments.', null, null],
    ['problem', 'Medium',   'known_error',         'Legacy application crashes on Windows 11', 'The legacy inventory management application (v3.2) crashes on Windows 11 due to compatibility issues with the graphics driver.', null, null],
    ['problem', 'Critical', 'resolved',            'Database connection pool exhaustion', 'Application servers exhaust the database connection pool under peak load, causing transaction failures. Fixed by increasing pool size and adding connection retry logic.', null, null],
    // Changes (17-20)
    ['change', null, 'submitted_for_approval', 'Server firmware update - Q2 maintenance', 'Update firmware on all production ESXi hosts to version 8.0.3. Requires planned downtime of approximately 2 hours.', null, null, 'normal', 'medium'],
    ['change', null, 'approved',               'Database migration to MySQL 8.4', 'Migrate all application databases from MySQL 5.7 to 8.4. Includes compatibility testing and rollback plan.', null, null, 'standard', 'low'],
    ['change', null, 'implemented',            'Emergency security patch - Log4j', 'Apply critical security patch for Apache Log4j vulnerability CVE-2021-44228 across all Java applications.', null, null, 'emergency', 'critical'],
    ['change', null, 'reviewed',               'Office network switch replacement', 'Replace end-of-life network switches on floors 1-3 with new managed switches. Completed successfully.', null, null, 'standard', 'low'],
];

$statusTimeline = [
    'open' => ['opened'],
    'in_progress' => ['opened', 'in_progress'],
    'on_hold' => ['opened', 'in_progress', 'on_hold'],
    'resolved' => ['opened', 'in_progress', 'resolved'],
    'closed' => ['opened', 'in_progress', 'resolved', 'closed'],
    'in_fulfillment' => ['opened', 'in_fulfillment'],
    'fulfilled' => ['opened', 'in_fulfillment', 'fulfilled'],
    'rejected' => ['opened', 'rejected'],
    'under_investigation' => ['opened', 'under_investigation'],
    'known_error' => ['opened', 'under_investigation', 'known_error'],
    'submitted_for_approval' => ['draft', 'submitted_for_approval'],
    'approved' => ['draft', 'submitted_for_approval', 'approved'],
    'implemented' => ['draft', 'submitted_for_approval', 'approved', 'implemented'],
    'reviewed' => ['draft', 'submitted_for_approval', 'approved', 'implemented', 'reviewed'],
];

$priorityMap = ['Critical' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1];

foreach ($tickets as $idx => $t) {
    $type = $t[0];
    $priority = $t[1];
    $status = $t[2];
    $title = $t[3];
    $description = $t[4];
    $impact = $t[5] ?? null;
    $urgency = $t[6] ?? null;
    $changeType = $t[7] ?? null;
    $riskLevel = $t[8] ?? null;

    $requesterId = pickRequester($userIdMap);
    $assigneeId = pickAgent($agentIds);
    $createdAt = randomDate($now, 30);
    $priorityNum = $priority ? ($priorityMap[$priority] ?? 2) : 2;

    // Generate ticket number
    $prefixes = ['incident' => 'INC', 'service_request' => 'SR', 'problem' => 'PBL', 'change' => 'CHG'];
    $prefix = $prefixes[$type] ?? 'TKT';
    $year = date('y');
    $count = Database::fetch("SELECT COUNT(*) as c FROM tickets WHERE ticket_number LIKE ?", ["{$prefix}-{$year}%"])['c'];
    $ticketNumber = sprintf("%s-%s-%04d", $prefix, $year, $count + 1);

    // Insert ticket
    $priorityStr = $priority ? strtolower($priority) : 'medium';
    $db->prepare("INSERT INTO tickets (ticket_number, title, description, type, status, priority, impact, urgency, reported_by, assigned_to, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([$ticketNumber, $title, $description, $type, $status, $priorityStr, $impact, $urgency, $requesterId, $assigneeId, $createdAt, $createdAt]);
    $ticketId = $db->lastInsertId();

    // Type-specific insert
    switch ($type) {
        case 'incident':
            $db->prepare("INSERT INTO incidents (ticket_id, impact, urgency) VALUES (?, ?, ?)")
               ->execute([$ticketId, $impact, $urgency]);
            break;
        case 'service_request':
            $db->prepare("INSERT INTO service_requests (ticket_id) VALUES (?)")
               ->execute([$ticketId]);
            break;
        case 'problem':
            $db->prepare("INSERT INTO problems (ticket_id) VALUES (?)")
               ->execute([$ticketId]);
            break;
        case 'change':
            $db->prepare("INSERT INTO changes (ticket_id, change_type, risk_level) VALUES (?, ?, ?)")
               ->execute([$ticketId, $changeType, $riskLevel]);
            break;
    }

    // Status history
    $timeline = $statusTimeline[$status] ?? [$status];
    $prev = null;
    foreach ($timeline as $si => $s) {
        $ts = $createdAt;
        if ($si > 0) {
            $ts = randomDate($now, 1);
        }
        $db->prepare("INSERT INTO ticket_status_history (ticket_id, from_status, to_status, user_id, created_at) VALUES (?, ?, ?, ?, ?)")
           ->execute([$ticketId, $prev, $s, $assigneeId, $ts]);
        $prev = $s;
    }

    // Add a comment on some tickets
    if ($idx % 3 === 0) {
        $commentDate = randomDate($now, 5);
        $db->prepare("INSERT INTO ticket_comments (ticket_id, user_id, body, created_at) VALUES (?, ?, ?, ?)")
           ->execute([$ticketId, $assigneeId, "Initial investigation started. Will provide an update within 2 hours.", $commentDate]);
    }

    echo "Created $type ticket #$ticketId: $title ($status)<br>";
}

echo "<h2>Demo data seeded successfully!</h2>";
echo "<p>10 end users created (password: <strong>demo123</strong>)</p>";
echo "<p>20 tickets created across Incidents, Service Requests, Problems, and Changes.</p>";
