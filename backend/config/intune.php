<?php

$host = $_SERVER['HTTP_HOST'] ?? 'itsm.governit.dk';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

return [
    'tenant_id' => 'e4ae34ca-6e95-4065-9b5f-244d7d6df828',
    'client_id' => 'dc105afa-e48f-47d3-92ae-19f4263fec5a',
    'client_secret' => 'REDACTED_AZURE_CLIENT_SECRET',
    'oauth_redirect_uri' => $protocol . '://' . $host . '/auth/microsoft/callback',
];
