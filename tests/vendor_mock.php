<?php
// TEMPORARY test double for a vendor API. Deleted after the vendor API test run.
header('Content-Type: application/json');
$d = json_decode(file_get_contents('php://input'), true) ?: [];
$num = $d['ticket_number'] ?? '';
echo json_encode(['external_ref' => 'EXT-' . strtoupper(substr(md5($num), 0, 8)), 'received' => $num]);
